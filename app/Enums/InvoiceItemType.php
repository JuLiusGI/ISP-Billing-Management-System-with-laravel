<?php

namespace App\Enums;

enum InvoiceItemType: string
{
    case Subscription = 'subscription';
    case Installation = 'installation';
    case Activation = 'activation';
    case Adjustment = 'adjustment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Subscription => 'Monthly Service',
            self::Installation => 'Installation Fee',
            self::Activation => 'Activation Fee',
            self::Adjustment => 'Adjustment',
            self::Other => 'Other',
        };
    }
}
