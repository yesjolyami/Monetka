<?php

use App\Support\Money;

it('converts decimal string to minor units', function () {
    expect(Money::toMinor('10.50'))->toBe(1050)
        ->and(Money::toMinor('10,50'))->toBe(1050)
        ->and(Money::toMinor('0.01'))->toBe(1);
});

it('converts minor units to decimal string', function () {
    expect(Money::toDecimal(1050))->toBe('10.50')
        ->and(Money::toDecimal(-250))->toBe('-2.50');
});

it('rejects non-numeric input', function () {
    Money::toMinor('abc');
})->throws(InvalidArgumentException::class);
