<?php

namespace App\Enums;

/** A customer's billing standing, independent of their lifecycle status. */
enum CustomerAccountStatus: string
{
    case GoodStanding = 'good_standing';
    case Overdue = 'overdue';
    case Delinquent = 'delinquent';

    public function label(): string
    {
        return match ($this) {
            self::GoodStanding => 'Good Standing',
            self::Overdue => 'Overdue',
            self::Delinquent => 'Delinquent',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::GoodStanding => 'text-bg-success',
            self::Overdue => 'text-bg-warning',
            self::Delinquent => 'text-bg-danger',
        };
    }
}
