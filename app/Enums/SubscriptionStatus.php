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

    /**
     * The statuses this one may move to.
     *
     * Kept here rather than in the service so the UI and the write path read
     * the same rules, and an illegal transition cannot be reached by posting
     * the request directly.
     *
     * @return self[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            // A new line is either switched on or abandoned.
            self::Pending => [self::Active, self::Cancelled],
            self::Active => [self::Suspended, self::Expired, self::Cancelled],
            // Suspended lines are restored by paying, or written off.
            self::Suspended => [self::Active, self::Expired, self::Cancelled],
            self::Expired => [self::Active, self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** The verb shown on the button that performs this transition. */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Pending => 'Set pending',
            self::Active => 'Activate',
            self::Suspended => 'Suspend',
            self::Expired => 'Mark expired',
            self::Cancelled => 'Cancel',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'hourglass-split',
            self::Active => 'play-circle',
            self::Suspended => 'pause-circle',
            self::Expired => 'calendar-x',
            self::Cancelled => 'x-circle',
        };
    }

    /** Only these statuses produce invoices when billing runs. */
    public function isBillable(): bool
    {
        return $this === self::Active;
    }
}
