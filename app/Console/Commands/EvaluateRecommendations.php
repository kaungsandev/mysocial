<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Services\CollaborativeRecommendationService;
use App\Services\ContentBasedRecommendationService;
use App\Services\Testing\PopularityBaselineService;
use App\Services\Testing\RecommendationEvaluationService;
use App\Services\Testing\TestReportWriter;
use App\Services\Testing\TestUserProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class EvaluateRecommendations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recommend:evaluate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively test and compare recommendation algorithms and record metrics for the thesis';

    /**
     * Execute the console command.
     */
    public function handle(
        TestUserProvisioner $userProvisioner,
        RecommendationEvaluationService $evaluationService,
        TestReportWriter $reportWriter,
    ): int {
        intro('📊 Recommendation System Evaluation Tool');

        $user = $this->resolveTestUser($userProvisioner);
        Auth::loginUsingId($user->id); // InterestService relies on Auth::user()

        $k = (int) text(
            label: 'Batch size (K) — posts per batch',
            default: '10',
            validate: fn ($v) => is_numeric($v) && $v > 0 ? null : 'Enter a positive number',
        );

        $batches = (int) text(
            label: 'Number of batches to run',
            default: '5',
            validate: fn ($v) => is_numeric($v) && $v > 0 ? null : 'Enter a positive number',
        );

        $mode = select(
            label: 'How should user behavior be simulated?',
            options: [
                'auto' => 'Automatic — likes posts matching pre-selected interests (repeatable)',
                'manual' => 'Manual — you decide per post (realistic demo, slower)',
            ],
            default: 'auto',
        );

        note("User: {$user->name} (ID: {$user->id}) | K={$k} | Batches={$batches} | Mode={$mode}");

        if (! confirm('Start test run? This will record real interactions for this user.', default: true)) {
            outro('Cancelled.');

            return self::SUCCESS;
        }

        $interestSnapshot = $userProvisioner->snapshotInterests($user->id);

        $arms = [
            'Popularity Baseline (Control)' => app(PopularityBaselineService::class),
            'Content-Based Filtering' => app(ContentBasedRecommendationService::class),
            'Collaborative Filtering' => app(CollaborativeRecommendationService::class),
        ];

        $results = [];

        foreach ($arms as $label => $recommender) {
            info("Running: {$label}");

            // Reset to an identical baseline before every arm — fair A/B/C comparison.
            $userProvisioner->restoreInterests($user->id, $interestSnapshot);
            $userProvisioner->clearInteractions($user->id);

            $decision = $mode === 'manual' ? $this->manualDecisionResolver() : 'auto';

            $results[$label] = $evaluationService->run(
                recommender: $recommender,
                userId: $user->id,
                perPage: $k,
                batches: $batches,
                engagement: $decision,
                onBatchComplete: function (int $batchNum, array $entry) use ($label) {
                    $cm = $entry['cumulative_metrics'];
                    $this->line(sprintf(
                        '  [%s] Batch %d — P: %.3f  R: %.3f  F1: %.3f',
                        $label, $batchNum, $cm['precision'], $cm['recall'], $cm['f1']
                    ));
                },
            );
        }

        // Leave the user clean afterward.
        $userProvisioner->restoreInterests($user->id, $interestSnapshot);
        $userProvisioner->clearInteractions($user->id);

        $this->renderComparisonTable($results);

        $paths = $reportWriter->write([
            'generated_at' => now()->toDateTimeString(),
            'user' => ['id' => $user->id, 'name' => $user->name],
            'k' => $k,
            'batches' => $batches,
            'mode' => $mode,
            'algorithms' => $results,
        ]);

        note("Text report : {$paths['txt']}");
        note("Raw JSON    : {$paths['json']}");

        outro('✅ Evaluation complete.');

        return self::SUCCESS;
    }

    private function resolveTestUser(TestUserProvisioner $provisioner)
    {
        $choice = select(
            label: 'Select a test user',
            options: [
                'new' => 'Create a new test user',
                'existing' => 'Use an existing test user',
            ],
        );

        if ($choice === 'existing') {
            $users = $provisioner->existingTestUsers();

            if ($users->isEmpty()) {
                info('No existing test users found — creating a new one instead.');
            } else {
                $id = select(
                    label: 'Choose a test user',
                    options: $users->mapWithKeys(fn ($u) => [$u->id => "{$u->name} (ID: {$u->id})"])->all(),
                );

                return $users->firstWhere('id', $id);
            }
        }

        $name = text(label: 'Test user name', default: 'Tester '.now()->format('His'));

        $categories = Category::orderBy('name')->pluck('name', 'id')->all();

        $selectedIds = multiselect(
            label: 'Select initial interests (minimum 3)',
            options: $categories,
            required: true,
        );

        if (count($selectedIds) < 3) {
            $this->error('Please select at least 3 categories.');

            return $this->resolveTestUser($provisioner);
        }

        return $provisioner->createTestUser($name, $selectedIds);
    }

    private function manualDecisionResolver(): \Closure
    {
        return function (Post $post, int $batch) {
            $categories = $post->categories->pluck('name')->implode(', ');
            $this->line("\nBatch {$batch} — \"{$post->title}\" [{$categories}]");

            $action = select(
                label: 'Action for this post',
                options: [
                    'none' => 'Just view (no engagement)',
                    'like' => 'Like',
                    'comment' => 'Comment',
                    'share' => 'Share',
                ],
            );

            return $action === 'none' ? null : $action;
        };
    }

    private function renderComparisonTable(array $results): void
    {
        $rows = [];

        foreach ($results as $label => $result) {
            $f = $result['final'];
            $rows[] = [
                $label,
                $f['k'],
                $f['relevantFound'],
                $f['totalRelevant'],
                number_format($f['precision'], 3),
                number_format($f['recall'], 3),
                number_format($f['f1'], 3),
            ];
        }

        table(['Algorithm', 'K', 'Relevant Found', 'Total Relevant', 'Precision', 'Recall', 'F1'], $rows);
    }
}
