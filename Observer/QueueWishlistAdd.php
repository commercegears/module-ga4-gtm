<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\EventQueue;
use CommerceGears\Ga4\Model\ItemBuilder;

/**
 * Queues add_to_wishlist when a customer adds a product to the wish list (Luma and Hyvä use the same controller).
 * Delivered through the commercegears-ga4 customer-data section like add_to_cart.
 */
class QueueWishlistAdd implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly EventQueue $queue,
        private readonly ItemBuilder $itemBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');
        if (!$product instanceof Product || !$product->getId() || !$this->config->isActive()) {
            return;
        }
        try {
            $item = $observer->getEvent()->getData('item');
            $qty = is_object($item) && method_exists($item, 'getQty') ? max(1.0, (float)$item->getQty()) : 1.0;
            $entry = $this->itemBuilder->fromProduct($product, ['quantity' => $qty]);
            $this->queue->add('add_to_wishlist', [
                'currency' => $this->itemBuilder->getCurrency(),
                'value' => $this->itemBuilder->sumValue([$entry]),
                'items' => [$entry],
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('CommerceGears_Ga4: could not track add_to_wishlist: ' . $e->getMessage());
        }
    }
}
