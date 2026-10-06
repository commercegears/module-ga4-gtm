<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Plugin;

use Magento\Checkout\Block\Cart\Crosssell;

/**
 * Luma cart page cross-sell block.
 */
class RegisterCrosssellPlugin
{
    public function __construct(private readonly RegisterLinkedProductsPlugin $linked)
    {
    }

    /**
     * @param Crosssell $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetItems(Crosssell $subject, mixed $result): mixed
    {
        return $this->linked->register('crosssell_products', 'Cross-sell products', $result);
    }
}
