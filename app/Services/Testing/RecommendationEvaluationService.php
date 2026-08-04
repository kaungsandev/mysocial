<?php

namespace App\Services\Testing;

use App\Enums\InteractionTypeEnum;
use App\Models\Post;
use App\Services\InterActionService;
use App\Services\InterestService;
use App\Services\MetricCalculationService;
use Closure;

class RecommendationEvaluationService
{
    public function __construct(
        private InterActionService $interActionService,
        private InterestService $interestService,
        private MetricCalculationService $metrics,
    ) {}

    /**
     * @param  object  $recommender  anything exposing recommend($userId, $page, $perPage)
     * @param  string|Closure  $engagement  'auto' for deterministic simulation,
     *                                      or Closure(Post $post, int $batch): ?string returning
     *                                      'like'|'comment'|'share'|null
     * @param  Closure|null  $onBatchComplete  fn(int $batch, array $entry): void — for live CLI feedback
     */
    public function run(
        object $recommender,
        int $userId,
        int $perPage,
        int $batches,
        string|Closure $engagement,
        ?Closure $onBatchComplete = null,
    ): array {
        $userCategoryIds = $this->metrics->getUserInterestCategoryIds($userId);

        $batchResults = [];
        $cumulativeShownIds = [];

        for ($page = 1; $page <= $batches; $page++) {
            $posts = $recommender->recommend($userId, $page, $perPage);
            $shownIds = $posts->pluck('id')->all();

            foreach ($posts as $post) {
                // Every shown post is a view, mirroring the live feed's loadMore() behavior.
                $this->interActionService->recordInteraction($post->id, $userId, InteractionTypeEnum::VIEW->value);
                $this->interestService->updateInterest($post->id, InteractionTypeEnum::VIEW->value, isPositiveInteraction: true);

                $action = $engagement === 'auto'
                    ? $this->autoDecide($post, $userCategoryIds)
                    : $engagement($post, $page);

                if ($action) {
                    $this->interActionService->recordInteraction($post->id, $userId, $action);
                    $this->interestService->updateInterest($post->id, $action, isPositiveInteraction: true);
                }
            }

            // Interest weights may have shifted this batch — recompute for the next round's ground truth.
            $userCategoryIds = $this->metrics->getUserInterestCategoryIds($userId);

            $cumulativeShownIds = array_merge($cumulativeShownIds, $shownIds);

            $entry = [
                'batch' => $page,
                'shown_post_ids' => $shownIds,
                'batch_metrics' => $this->metrics->evaluateBatch($userId, $shownIds, $userCategoryIds),
                'cumulative_metrics' => $this->metrics->evaluateBatch($userId, $cumulativeShownIds, $userCategoryIds),
            ];

            $batchResults[] = $entry;

            if ($onBatchComplete) {
                $onBatchComplete($page, $entry);
            }
        }

        return [
            'batches' => $batchResults,
            'final' => end($batchResults)['cumulative_metrics'],
        ];
    }

    /**
     * Deterministic simulated behavior: the virtual user likes a post if it belongs
     * to one of their known interest categories, and ignores it otherwise.
     * This makes automatic runs repeatable — useful for thesis appendix data.
     */
    private function autoDecide(Post $post, array $userCategoryIds): ?string
    {
        $postCategoryIds = $post->categories->pluck('id')->all();

        return array_intersect($postCategoryIds, $userCategoryIds) ? 'like' : null;
    }
}
