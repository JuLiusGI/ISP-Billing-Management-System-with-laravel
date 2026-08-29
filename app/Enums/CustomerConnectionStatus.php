<?php

namespace App\Enums;

/** Whether the customer's physical line is up. */
enum CustomerConnectionStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Disconnected = 'disconnected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Connected => 'Connected',
            self::Disconnected => 'Disconnected',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-info',
            self::Connected => 'text-bg-success',
            self::Disconnected => 'text-bg-danger',
        };
    }
}
