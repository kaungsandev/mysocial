<?php

// app/Services/Concerns/CalculatesCosineSimilarity.php

namespace App\Services\Concerns;

use Illuminate\Support\Collection;

trait CalculatesCosineSimilarity
{
    /**
     * Cosine similarity between two sparse vectors (associative collections).
     * Works regardless of what the keys represent (post_id for CF, category_id for CBF).
     *
     * cosine similarity = A⋅B​ / ∣∣A∣∣×∣∣B∣∣
     * A⋅B = (A1​×B1​)+(A2​×B2​)+... Multiply matching ratings and sum them
     * ∣∣A∣∣= √(A1² + A2² + ...) Length of vector A
     * ∣∣B∣∣= √(B1² + B2² + ...) Length of vector B
     */
    protected function cosineSimilarity(Collection $userProfile, Collection $itemProfile): float
    {
        $dot = 0.0;
        foreach ($userProfile as $key => $val) {
            if ($itemProfile->has($key)) {
                $dot += $val * $itemProfile->get($key);
            }
        }

        if ($dot === 0.0) {
            return 0.0;
        }

        $magA = sqrt($userProfile->sum(fn ($value) => $value * $value));
        $magB = sqrt($itemProfile->sum(fn ($value) => $value * $value));

        if ($magA === 0.0 || $magB === 0.0) {
            return 0.0;
        }

        return $dot / ($magA * $magB);
    }
}
