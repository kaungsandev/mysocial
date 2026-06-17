<?php

namespace App\Enums;

enum InteractionTypeEnum: string
{
    case VIEW = 'view';
    case LIKE = 'like';
    case COMMENT = 'comment';
    case SHARE = 'share';
    case POST = 'post';
}
