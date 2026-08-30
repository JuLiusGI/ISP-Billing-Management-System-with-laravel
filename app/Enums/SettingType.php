<?php

namespace App\Enums;

enum SettingType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Json = 'json';

    /** Turns the stored text value into its real PHP type. */
    public function cast(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::String => $value,
            self::Integer => (int) $value,
            // Deliberately left as a string. A decimal setting exists because
            // the value is money-adjacent, and casting through float both
            // loses precision and drops the trailing zero, so a stored 12.50
            // would read back as 12.5.
            self::Decimal => $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Json => json_decode($value, true),
        };
    }

    /** Turns a PHP value back into the text form stored in the column. */
    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Boolean => $value ? '1' : '0',
            self::Json => json_encode($value),
            default => (string) $value,
        };
    }
}
