<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Cancelled => 'Cancelled',
            self::Void => 'Void',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Unpaid => 'text-bg-warning',
            self::PartiallyPaid => 'text-bg-info',
            self::Paid => 'text-bg-success',
            self::Overdue => 'text-bg-danger',
            self::Cancelled, self::Void => 'text-bg-dark',
        };
    }

    /**
     * Cancelled and void invoices carry no balance and are excluded from
     * receivables, ageing and revenue figures.
     */
    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled, self::Void], true);
    }

    /** Statuses that still represent money owed to the ISP. */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::Unpaid, self::PartiallyPaid, self::Overdue], true);
    }

    /** A cancelled or void invoice must never accept further payment. */
    public function acceptsPayment(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Void, self::Draft], true);
    }

    /** @return self[] */
    public static function outstanding(): array
    {
        return [self::Unpaid, self::PartiallyPaid, self::Overdue];
    }
}
