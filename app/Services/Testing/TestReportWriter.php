<?php

namespace App\Services\Testing;

use Illuminate\Support\Facades\Storage;

class TestReportWriter
{
    private const DIRECTORY = 'thesis-results';

    public function write(array $session): array
    {
        Storage::disk('local')->makeDirectory(self::DIRECTORY);

        $slug = now()->format('Y-m-d_His').'_user'.$session['user']['id'];
        $txtPath = self::DIRECTORY."/{$slug}.txt";
        $jsonPath = self::DIRECTORY."/{$slug}.json";

        Storage::disk('local')->put($txtPath, $this->renderText($session));
        Storage::disk('local')->put($jsonPath, json_encode($session, JSON_PRETTY_PRINT));

        return [
            'txt' => Storage::disk('local')->path($txtPath),
            'json' => Storage::disk('local')->path($jsonPath),
        ];
    }

    private function renderText(array $session): string
    {
        $lines = [];
        $lines[] = str_repeat('=', 72);
        $lines[] = 'RECOMMENDATION SYSTEM EVALUATION REPORT';
        $lines[] = str_repeat('=', 72);
        $lines[] = 'Generated at   : '.$session['generated_at'];
        $lines[] = 'Test user      : '.$session['user']['name'].' (ID: '.$session['user']['id'].')';
        $lines[] = 'Batch size K   : '.$session['k'];
        $lines[] = 'Batches run    : '.$session['batches'];
        $lines[] = 'Behavior mode  : '.$session['mode'];
        $lines[] = '';

        foreach ($session['algorithms'] as $name => $result) {
            $lines[] = str_repeat('-', 72);
            $lines[] = strtoupper($name);
            $lines[] = str_repeat('-', 72);

            foreach ($result['batches'] as $batch) {
                $bm = $batch['batch_metrics'];
                $cm = $batch['cumulative_metrics'];

                $lines[] = sprintf(
                    'Batch %d | Shown: %d | Relevant this batch: %d | P: %.3f R: %.3f F1: %.3f',
                    $batch['batch'], count($batch['shown_post_ids']), $bm['relevantFound'], $bm['precision'], $bm['recall'], $bm['f1']
                );
                $lines[] = sprintf(
                    '          Cumulative K: %-3d Relevant found: %-3d Total relevant in DB: %-4d P: %.3f R: %.3f F1: %.3f',
                    $cm['k'], $cm['relevantFound'], $cm['totalRelevant'], $cm['precision'], $cm['recall'], $cm['f1']
                );
            }

            $f = $result['final'];
            $lines[] = '';
            $lines[] = sprintf(
                'FINAL — K=%d  RelevantFound=%d  TotalRelevant=%d  Precision=%.3f  Recall=%.3f  F1=%.3f',
                $f['k'], $f['relevantFound'], $f['totalRelevant'], $f['precision'], $f['recall'], $f['f1']
            );
            $lines[] = '';
        }

        $lines[] = str_repeat('=', 72);
        $lines[] = 'SIDE-BY-SIDE COMPARISON';
        $lines[] = str_repeat('=', 72);
        foreach ($session['algorithms'] as $name => $result) {
            $f = $result['final'];
            $lines[] = sprintf('%-28s Precision: %.3f  Recall: %.3f  F1: %.3f', $name, $f['precision'], $f['recall'], $f['f1']);
        }

        return implode(PHP_EOL, $lines);
    }
}
