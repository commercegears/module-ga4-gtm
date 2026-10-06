<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Softaware\Ga4\Model\Config;
use Softaware\Ga4\Model\ResourceModel\OrderTracking;

/**
 * sales_model_service_quote_submit_success (storefront and REST checkout): remembers the GA client and
 * session id from the _ga cookies so later server-side refunds are attributed to the same user.
 */
class CaptureClientId implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CookieManagerInterface $cookieManager,
        private readonly OrderTracking $orderTracking,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface|null $order */
        $order = $observer->getEvent()->getData('order');
        if (!$order || !$order->getEntityId() || !$this->config->isActive((int)$order->getStoreId())) {
            return;
        }
        $clientId = $this->parseClientId((string)$this->cookieManager->getCookie('_ga'));
        if ($clientId === '') {
            return;
        }
        $measurementId = $this->config->getMeasurementId((int)$order->getStoreId());
        $sessionId = $measurementId !== ''
            ? $this->parseSessionId((string)$this->cookieManager->getCookie('_ga_' . substr($measurementId, 2)))
            : '';
        try {
            $this->orderTracking->saveClientId((int)$order->getEntityId(), $clientId, $sessionId);
        } catch (\Throwable $e) {
            $this->logger->warning('Softaware_Ga4: could not store GA client id: ' . $e->getMessage());
        }
    }

    /** GA1.1.1234567890.1700000000 -> 1234567890.1700000000 */
    private function parseClientId(string $cookie): string
    {
        return preg_match('/^GA\d\.\d+\.(\d+\.\d+)$/', $cookie, $m) ? $m[1] : '';
    }

    /** GS1.1.1700000000.5.1... or GS2.1.s1700000000$o5$g1... -> 1700000000 */
    private function parseSessionId(string $cookie): string
    {
        if (preg_match('/^GS2\.\d+\.s(\d+)/', $cookie, $m) || preg_match('/^GS1\.\d+\.(\d+)\./', $cookie, $m)) {
            return $m[1];
        }
        return '';
    }
}
