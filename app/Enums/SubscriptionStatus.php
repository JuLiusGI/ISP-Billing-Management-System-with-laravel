<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-info',
            self::Active => 'text-bg-success',
            self::Suspended => 'text-bg-warning',
            self::Expired => 'text-bg-secondary',
            self::Cancelled => 'text-bg-danger',
        };
    }

    /** A cancelled subscription is terminal; everything else can still move. */
    public function isTerminal(): bool
    {
        return $this === self::Cancelled;
    }

    /** Only these statuses produce invoices when billing runs. */
    public function isBillable(): bool
    {
        return $this === self::Active;
    }
}
