<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order\Creditmemo;
use Psr\Log\LoggerInterface;
use Softaware\Ga4\Model\Config;
use Softaware\Ga4\Model\ItemBuilder;
use Softaware\Ga4\Model\ResourceModel\RefundQueue;

/**
 * sales_order_creditmemo_save_after: stores the refund event; cron sends it (no HTTP call in the admin request).
 */
class QueueRefund implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ItemBuilder $itemBuilder,
        private readonly RefundQueue $queue,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Creditmemo|null $creditmemo */
        $creditmemo = $observer->getEvent()->getData('creditmemo');
        if (!$creditmemo || !$creditmemo->getId()) {
            return;
        }
        $storeId = (int)$creditmemo->getStoreId();
        if (!$this->config->isRefundEnabled($storeId)) {
            return;
        }
        try {
            $order = $creditmemo->getOrder();
            $base = $this->config->useBaseCurrency($storeId);
            $items = [];
            foreach ($creditmemo->getAllItems() as $item) {
                $orderItem = $item->getOrderItem();
                if (!$orderItem || $orderItem->getParentItemId() || (float)$item->getQty() <= 0) {
                    continue;
                }
                $items[] = $this->itemBuilder->fromCreditmemoItem($item, $base);
            }
            $params = [
                'currency' => $base ? (string)$order->getBaseCurrencyCode() : (string)$order->getOrderCurrencyCode(),
                'transaction_id' => (string)$order->getIncrementId(),
                'value' => round((float)($base ? $creditmemo->getBaseGrandTotal() : $creditmemo->getGrandTotal()), 2),
                'tax' => round((float)($base ? $creditmemo->getBaseTaxAmount() : $creditmemo->getTaxAmount()), 2),
                'shipping' => round((float)($base ? $creditmemo->getBaseShippingAmount() : $creditmemo->getShippingAmount()), 2),
                'affiliation' => $this->config->getAffiliation($storeId),
            ];
            if ($items) {
                $params['items'] = $items;
            }
            $this->queue->add((int)$order->getId(), (int)$creditmemo->getId(), $storeId, $this->json->serialize($params));
        } catch (\Throwable $e) {
            $this->logger->warning('Softaware_Ga4: could not queue refund: ' . $e->getMessage());
        }
    }
}
