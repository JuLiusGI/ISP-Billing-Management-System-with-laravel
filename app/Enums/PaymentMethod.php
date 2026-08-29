<?php

namespace App\Enums;

/**
 * Labels for manually recorded payments. None of these imply an integrated
 * payment gateway; they describe how the money actually arrived.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Gcash = 'gcash';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::Gcash => 'GCash',
            self::Online => 'Online Payment',
            self::Other => 'Other',
        };
    }

    /** Methods where a external reference number is expected. */
    public function expectsReference(): bool
    {
        return $this !== self::Cash;
    }
}
