<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Completed = 'completed';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::Reversed => 'Reversed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Completed => 'text-bg-success',
            self::Reversed => 'text-bg-danger',
            self::Cancelled => 'text-bg-secondary',
        };
    }

    /**
     * Only completed payments count toward an invoice balance. Reversed and
     * cancelled rows stay in the table for the audit trail but must never be
     * treated as money received.
     */
    public function countsTowardBalance(): bool
    {
        return $this === self::Completed;
    }
}
