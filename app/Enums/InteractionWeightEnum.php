<?php

namespace App\Enums;

enum InteractionWeightEnum: int
{
    case VIEW = 1;
    case LIKE = 3;
    case COMMENT = 5;
    case SHARE = 4;
    case POST = 7;  // creating a post in a category = strongest signal

    public static function forType(string $type): int
    {
        return match ($type) {
            'view' => self::VIEW->value,
            'like' => self::LIKE->value,
            'comment' => self::COMMENT->value,
            'share' => self::SHARE->value,
            'post' => self::POST->value,
            default => 1,
        };
    }
}
