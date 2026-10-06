<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Plugin;

use Magento\Catalog\Block\Product\ProductList\Related;
use Softaware\Ga4\Model\Config;
use Softaware\Ga4\Model\ListRegistry;

/**
 * Luma related / upsell / cross-sell blocks. (Hyvä renders these lists through its ProductList view model;
 * see Model\Event\Provider\HyvaLinkedProducts.)
 */
class RegisterLinkedProductsPlugin
{
    public function __construct(
        private readonly ListRegistry $registry,
        private readonly Config $config
    ) {
    }

    /**
     * @param Related $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetItems(Related $subject, mixed $result): mixed
    {
        return $this->register('related_products', 'Related products', $result);
    }

    /**
     * Used by plugin "softaware_ga4_register_upsell" (Upsell::getItemCollection()).
     */
    public function register(string $listId, string $listName, mixed $result): mixed
    {
        if (is_iterable($result) && $this->config->isActive()) {
            $this->registry->add($listId, (string)__($listName), $result);
        }
        return $result;
    }
}
