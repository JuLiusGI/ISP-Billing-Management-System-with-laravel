<?php

namespace App\Enums;

enum BillingCycleStatus: string
{
    case Open = 'open';
    case Processing = 'processing';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Processing => 'Processing',
            self::Closed => 'Closed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'text-bg-info',
            self::Processing => 'text-bg-warning',
            self::Closed => 'text-bg-secondary',
        };
    }
}
