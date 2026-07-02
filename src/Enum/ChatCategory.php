<?php

declare(strict_types=1);

namespace App\Enum;

enum ChatCategory: string
{
    case TECHNICAL_SUPPORT = 'TECHNICAL_SUPPORT';
    case BILLING = 'BILLING';
    case RETURN = 'RETURN';
    case PRODUCT_INQUIRY = 'PRODUCT_INQUIRY';
    case SPAM = 'SPAM';
    case OTHER = 'OTHER';

    // Returns all values dynamically to keep the LLM prompt in sync
    // with the enum definition — no need to update both separately.
    public static function values(): string
    {
        return implode('" | "', array_column(self::cases(), 'value'));
    }
}