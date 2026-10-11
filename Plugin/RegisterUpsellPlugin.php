<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Plugin;

use Magento\Catalog\Block\Product\ProductList\Upsell;

/**
 * Luma upsell block ("We found other products you might like!").
 */
class RegisterUpsellPlugin
{
    public function __construct(private readonly RegisterLinkedProductsPlugin $linked)
    {
    }

    /**
     * @param Upsell $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetItemCollection(Upsell $subject, mixed $result): mixed
    {
        return $this->linked->register('upsell_products', 'Upsell products', $result);
    }
}
