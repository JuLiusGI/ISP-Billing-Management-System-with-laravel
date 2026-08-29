<?php

namespace App\Enums;

enum ConnectionType: string
{
    case Fiber = 'fiber';
    case Wireless = 'wireless';
    case Dsl = 'dsl';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fiber => 'Fiber',
            self::Wireless => 'Wireless',
            self::Dsl => 'DSL',
            self::Other => 'Other',
        };
    }
}
