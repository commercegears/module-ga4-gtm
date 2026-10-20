<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model\Event\Provider;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use CommerceGears\Ga4\Api\PageEventProviderInterface;
use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\ItemBuilder;
use CommerceGears\Ga4\Model\PurchaseValue;
use CommerceGears\Ga4\Model\ResourceModel\OrderTracking;
use CommerceGears\Ga4\Model\UserData;

/**
 * purchase on the order success page (plus the Google Ads purchase conversion when configured), output once per order: the order is flagged in commercegears_ga4_order before
 * the event is rendered, so a reload, the back button or a second tab never repeat it. All values come from the
 * order of the checkout session, never from the URL.
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
        $event = $this->build($order);
        // Free orders (for example a free extension) can be left out; the order stays flagged, so it never fires later.
        if ($this->config->isSkipZeroValue((int)$order->getStoreId()) && PurchaseValue::isZero((float)$event['params']['value'])) {
            return [];
        }
        return [$this->withAdsConversion($event, (int)$order->getStoreId())];
    }

    /**
     * Google Ads purchase conversion with exactly the purchase's value, currency and transaction_id. It travels inside
     * the purchase event (same once-per-order flag and browser id), so the two can never disagree.
     *
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function withAdsConversion(array $event, int $storeId): array
    {
        $sendTo = $this->config->getAdsSendTo('purchase', $storeId);
        if ($sendTo !== '') {
            $event['conversion'] = [
                'send_to' => $sendTo,
                'value' => $event['params']['value'],
                'currency' => $event['params']['currency'],
                'transaction_id' => $event['params']['transaction_id'],
            ];
        }
        return $event;
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
        $value = PurchaseValue::compute($grandTotal, $tax, $shipping, $shippingInclTax, $inclTax, $inclShipping);

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
            'value' => $value,
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
