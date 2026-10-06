<?php

namespace App\Support;

/**
 * Scale-2 decimal arithmetic for money.
 *
 * Every figure in the fees module is a decimal(14,2) that arrives from the
 * database as a string. Doing sums on those as PHP floats loses kobo on
 * perfectly ordinary numbers, so all of it goes through here instead.
 */
class Money
{
    public const SCALE = 2;

    /**
     * Normalise anything money-shaped (string, int, float, null) to a
     * scale-2 decimal string.
     */
    public static function of(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_float($value)) {
            $value = sprintf('%.'.self::SCALE.'F', $value);
        }

        return bcadd((string) $value, '0', self::SCALE);
    }

    public static function add(mixed $left, mixed $right): string
    {
        return bcadd(self::of($left), self::of($right), self::SCALE);
    }

    public static function sub(mixed $left, mixed $right): string
    {
        return bcsub(self::of($left), self::of($right), self::SCALE);
    }

    /**
     * Multiplying a per-student fee by a headcount -- the one place money
     * legitimately gets multiplied in this module.
     */
    public static function multiplyByInt(mixed $value, int $times): string
    {
        return bcmul(self::of($value), (string) max($times, 0), self::SCALE);
    }

    public static function sum(iterable $values): string
    {
        $total = '0.00';

        foreach ($values as $value) {
            $total = self::add($total, $value);
        }

        return $total;
    }

    /**
     * -1, 0 or 1 -- the same contract as the spaceship operator.
     */
    public static function compare(mixed $left, mixed $right): int
    {
        return bccomp(self::of($left), self::of($right), self::SCALE);
    }

    public static function isZero(mixed $value): bool
    {
        return self::compare($value, '0') === 0;
    }

    public static function isNegative(mixed $value): bool
    {
        return self::compare($value, '0') < 0;
    }

    public static function greaterThan(mixed $left, mixed $right): bool
    {
        return self::compare($left, $right) > 0;
    }

    public static function min(mixed $left, mixed $right): string
    {
        return self::compare($left, $right) <= 0 ? self::of($left) : self::of($right);
    }

    public static function max(mixed $left, mixed $right): string
    {
        return self::compare($left, $right) >= 0 ? self::of($left) : self::of($right);
    }

    /**
     * Clamps at zero -- an outstanding balance is never negative, an
     * overpayment is reported separately.
     */
    public static function atLeastZero(mixed $value): string
    {
        return self::isNegative($value) ? '0.00' : self::of($value);
    }
}
