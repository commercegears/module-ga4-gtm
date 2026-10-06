<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Event\Provider;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Softaware\Ga4\Api\PageEventProviderInterface;
use Softaware\Ga4\Model\Config;
use Softaware\Ga4\Model\ItemBuilder;
use Softaware\Ga4\Model\ResourceModel\OrderTracking;
use Softaware\Ga4\Model\UserData;

/**
 * purchase on the order success page, output once per order (reloading the page does not repeat it).
 */
class Purchase implements PageEventProviderInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ItemBuilder $itemBuilder,
        private readonly Config $config,
        private readonly OrderTracking $orderTracking,
        private readonly UserData $userData
    ) {
    }

    public function getEvents(): array
    {
        /** @var Order $order */
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getId() || !$this->orderTracking->markPurchaseTracked((int)$order->getId())) {
            return [];
        }
        return [$this->build($order)];
    }

    /**
     * @return array{event: string, params: array<string, mixed>, extra: array<string, mixed>, uid: string}
     */
    public function build(Order $order): array
    {
        $storeId = (int)$order->getStoreId();
        $base = $this->config->useBaseCurrency($storeId);
        $inclTax = $this->config->isValueInclTax($storeId);
        $inclShipping = $this->config->isValueInclShipping($storeId);

        $grandTotal = (float)($base ? $order->getBaseGrandTotal() : $order->getGrandTotal());
        $tax = (float)($base ? $order->getBaseTaxAmount() : $order->getTaxAmount());
        $shipping = (float)($base ? $order->getBaseShippingAmount() : $order->getShippingAmount());
        $shippingInclTax = (float)($base ? $order->getBaseShippingInclTax() : $order->getShippingInclTax());
        $value = $grandTotal;
        if (!$inclShipping) {
            $value -= $inclTax ? $shippingInclTax : $shipping;
        }
        if (!$inclTax) {
            $value -= $tax;
        }

        $items = [];
        $productIds = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $productIds[] = (int)$item->getProductId();
        }
        $this->itemBuilder->preloadProductIds($productIds, $storeId);
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = $this->itemBuilder->fromOrderItem($item, null, $base);
        }
        $params = [
            'transaction_id' => (string)$order->getIncrementId(),
            'affiliation' => $this->config->getAffiliation($storeId),
            'currency' => $base ? (string)$order->getBaseCurrencyCode() : (string)$order->getOrderCurrencyCode(),
            'value' => round(max(0.0, $value), 2),
            'tax' => round($tax, 2),
            'shipping' => round($inclTax ? $shippingInclTax : $shipping, 2),
        ];
        if ($order->getCouponCode()) {
            $params['coupon'] = (string)$order->getCouponCode();
        }
        $params['items'] = $items;
        $userData = $this->userData->fromOrder($order);
        return [
            'event' => 'purchase',
            'params' => $params,
            'extra' => $userData ? ['user_data' => $userData] : [],
            'uid' => 'purchase_' . $order->getIncrementId(),
        ];
    }
}
