<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

/**
 * The purchase (and refund) "value": the grand total, which already includes discounts, minus tax and/or shipping
 * as configured. Kept free of Magento objects so the rule is easy to test.
 */
class PurchaseValue
{
    /**
     * @param float $grandTotal      order grand total (after discounts, incl. tax and shipping)
     * @param float $tax             total tax, including tax on shipping
     * @param float $shipping        shipping excl. tax
     * @param float $shippingInclTax shipping incl. tax
     */
    public static function compute(
        float $grandTotal,
        float $tax,
        float $shipping,
        float $shippingInclTax,
        bool $inclTax,
        bool $inclShipping
    ): float {
        $value = $grandTotal;
        if (!$inclShipping) {
            $value -= $inclTax ? $shippingInclTax : $shipping;
        }
        if (!$inclTax) {
            $value -= $tax;
        }
        return round(max(0.0, $value), 2);
    }

    /**
     * Free orders (for example a free extension) report a value of 0.
     */
    public static function isZero(float $value): bool
    {
        return round($value, 2) <= 0.0;
    }
}
