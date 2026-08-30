<?php

namespace App\Support;

/**
 * Normalises money values to a two-decimal string.
 *
 * The problem this solves is small and recurring: a database SUM() over no rows
 * comes back as 0 rather than 0.00, so an empty balance reads differently from
 * every other amount in the system. Comparisons like `$balance === '0.00'`
 * then fail on a value that is arithmetically correct.
 *
 * Done with bcadd rather than a float cast or number_format so nothing is lost
 * on the way through. Three services and two models had grown their own copy of
 * this before it was pulled out here.
 */
final class Money
{
    public const SCALE = 2;

    /** @param  string|int|float|null  $value */
    public static function of(mixed $value): string
    {
        return bcadd((string) ($value ?: '0'), '0', self::SCALE);
    }

    /** Never returns less than zero — an overpayment is credit, not a negative bill. */
    public static function atLeastZero(mixed $value): string
    {
        $normalised = self::of($value);

        return bccomp($normalised, '0', self::SCALE) === -1 ? '0.00' : $normalised;
    }
}
