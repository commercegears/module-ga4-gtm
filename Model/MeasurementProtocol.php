<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Minimal GA4 Measurement Protocol client (https://developers.google.com/analytics/devguides/collection/protocol/ga4).
 */
class MeasurementProtocol
{
    private const ENDPOINT = 'https://www.google-analytics.com/mp/collect';
    private const DEBUG_ENDPOINT = 'https://www.google-analytics.com/debug/mp/collect';

    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly Json $json
    ) {
    }

    /**
     * @param array<int, array{name: string, params: array<string, mixed>}> $events
     * @return array{status: int, body: string}
     * @throws LocalizedException
     */
    public function send(int $storeId, string $clientId, array $events, bool $validateOnly = false): array
    {
        $measurementId = $this->config->getMeasurementId($storeId);
        $secret = $this->config->getApiSecret($storeId);
        if ($measurementId === '' || $secret === '') {
            throw new LocalizedException(__('GA4 measurement ID or Measurement Protocol API secret is missing.'));
        }
        $url = ($validateOnly ? self::DEBUG_ENDPOINT : self::ENDPOINT)
            . '?measurement_id=' . rawurlencode($measurementId) . '&api_secret=' . rawurlencode($secret);
        $curl = $this->curlFactory->create();
        $curl->setTimeout(10);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->post($url, $this->json->serialize(['client_id' => $clientId, 'events' => $events]));
        $status = (int)$curl->getStatus();
        if ($status < 200 || $status >= 300) {
            throw new LocalizedException(__('Measurement Protocol returned HTTP %1.', $status));
        }
        return ['status' => $status, 'body' => (string)$curl->getBody()];
    }
}
