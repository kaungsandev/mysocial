<?php

namespace App\Services;

use App\Enums\InteractionWeightEnum;
use App\Models\Interaction;
use Illuminate\Support\Facades\Auth;

class InterActionService
{
    public function recordInteraction(int $postId, string $interactionType)
    {
        Interaction::create([
            'user_id' => Auth::id(),
            'post_id' => $postId,
            'interaction_type' => $interactionType,  // 'like' | 'comment' | 'share' | 'view' | 'post'
            'weight' => InteractionWeightEnum::forType($interactionType),
        ]);
    }

    public function removeInteraction(int $postId, string $interactionType): void
    {
        Interaction::where('user_id', '=', Auth::id(), 'and')
            ->where('post_id', $postId)
            ->where('interaction_type', $interactionType)
            ->delete();
    }
}
