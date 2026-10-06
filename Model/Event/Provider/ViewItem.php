<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Event\Provider;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Softaware\Ga4\Api\PageEventProviderInterface;
use Softaware\Ga4\Model\ItemBuilder;

/**
 * view_item on the product page.
 */
class ViewItem implements PageEventProviderInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly ItemBuilder $itemBuilder
    ) {
    }

    public function getEvents(): array
    {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product || !$product->getId()) {
            return [];
        }
        $category = $this->registry->registry('current_category');
        $item = $this->itemBuilder->fromProduct($product, [
            'category_id' => $category && $category->getId() ? (int)$category->getId() : null,
        ]);
        return [[
            'event' => 'view_item',
            'params' => [
                'currency' => $this->itemBuilder->getCurrency(),
                'value' => $item['price'] ?? 0,
                'items' => [$item],
            ],
        ]];
    }
}
