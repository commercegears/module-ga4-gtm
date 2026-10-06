<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Event\Provider;

use Magento\Checkout\Model\Session as CheckoutSession;
use Softaware\Ga4\Api\PageEventProviderInterface;
use Softaware\Ga4\Model\ItemBuilder;

/**
 * view_cart (cart page) and begin_checkout (checkout page) from the current quote.
 * The event name comes from the "event" constructor argument (see etc/frontend/di.xml).
 */
class QuoteEvent implements PageEventProviderInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ItemBuilder $itemBuilder,
        private readonly string $event = 'view_cart'
    ) {
    }

    public function getEvents(): array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            return [];
        }
        $storeId = (int)$quote->getStoreId();
        $products = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $products[] = (int)$item->getProductId();
        }
        if (!$products) {
            return [];
        }
        $this->itemBuilder->preloadProductIds($products, $storeId);
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = $this->itemBuilder->fromQuoteItem($item);
        }
        $params = [
            'currency' => $this->itemBuilder->getQuoteCurrency($quote),
            'value' => $this->itemBuilder->sumValue($items),
        ];
        if ($quote->getCouponCode()) {
            $params['coupon'] = (string)$quote->getCouponCode();
        }
        $params['items'] = $items;
        return [['event' => $this->event, 'params' => $params]];
    }
}
