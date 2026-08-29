<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case PendingInstallation = 'pending_installation';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::PendingInstallation => 'Pending Installation',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
            self::Terminated => 'Terminated',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PendingInstallation => 'text-bg-info',
            self::Active => 'text-bg-success',
            self::Inactive => 'text-bg-secondary',
            self::Suspended => 'text-bg-warning',
            self::Terminated => 'text-bg-danger',
        };
    }
}
