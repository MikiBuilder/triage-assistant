<?php

declare(strict_types=1);

namespace App\Enum;

enum ChatSentiment: string
{
    case POSITIVE = 'POSITIVE';
    case NEUTRAL = 'NEUTRAL';
    case NEGATIVE = 'NEGATIVE';
    case FRUSTRATED = 'FRUSTRATED';

    // Same pattern as ChatCategory — values come from the enum itself.
    public static function values(): string
    {
        return implode('" | "', array_column(self::cases(), 'value'));
    }
}