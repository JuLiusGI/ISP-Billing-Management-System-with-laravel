<?php

namespace App\Enums;

enum CustomerType: string
{
    case Residential = 'residential';
    case Business = 'business';
    case Government = 'government';

    public function label(): string
    {
        return match ($this) {
            self::Residential => 'Residential',
            self::Business => 'Business',
            self::Government => 'Government',
        };
    }
}
