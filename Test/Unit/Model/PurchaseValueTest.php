<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Test\Unit\Model;

use CommerceGears\Ga4\Model\PurchaseValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PurchaseValueTest extends TestCase
{
    /**
     * Order: subtotal 100 excl. tax, 10 discount, shipping 5 (+1 tax), tax 18 on goods + 1 on shipping.
     * Grand total = 100 - 10 + 5 + 19 = 114.
     *
     * @return array<string, array{bool, bool, float}>
     */
    public static function settingsProvider(): array
    {
        return [
            'incl. tax, incl. shipping (default)' => [true, true, 114.0],
            'excl. tax, incl. shipping' => [false, true, 95.0],
            'incl. tax, excl. shipping' => [true, false, 108.0],
            'excl. tax, excl. shipping (net goods after discount)' => [false, false, 90.0],
        ];
    }

    /**
     * @dataProvider settingsProvider
     */
    #[DataProvider('settingsProvider')]
    public function testSettings(bool $inclTax, bool $inclShipping, float $expected): void
    {
        self::assertSame($expected, PurchaseValue::compute(114.0, 19.0, 5.0, 6.0, $inclTax, $inclShipping));
    }

    public function testDigitalOrderWithoutShippingExclTax(): void
    {
        // Downloadable product 49 excl. tax, 20% VAT, no shipping.
        self::assertSame(49.0, PurchaseValue::compute(58.8, 9.8, 0.0, 0.0, false, false));
        self::assertSame(49.0, PurchaseValue::compute(58.8, 9.8, 0.0, 0.0, false, true));
    }

    public function testRoundsAndNeverNegative(): void
    {
        self::assertSame(33.33, PurchaseValue::compute(33.3333, 0.0, 0.0, 0.0, true, true));
        self::assertSame(0.0, PurchaseValue::compute(0.0, 0.0, 5.0, 5.0, false, false));
    }

    public function testZeroRule(): void
    {
        self::assertTrue(PurchaseValue::isZero(0.0));
        self::assertTrue(PurchaseValue::isZero(0.004));
        self::assertFalse(PurchaseValue::isZero(0.01));
        self::assertFalse(PurchaseValue::isZero(19.0));
        // A free extension: grand total 0.
        self::assertTrue(PurchaseValue::isZero(PurchaseValue::compute(0.0, 0.0, 0.0, 0.0, false, false)));
    }
}
