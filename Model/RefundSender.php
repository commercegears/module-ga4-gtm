<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use CommerceGears\Ga4\Model\ResourceModel\OrderTracking;
use CommerceGears\Ga4\Model\ResourceModel\RefundQueue;

/**
 * Sends queued refund events (used by cron and by bin/magento commercegears:ga4:send-refunds).
 */
class RefundSender
{
    public function __construct(
        private readonly RefundQueue $queue,
        private readonly OrderTracking $orderTracking,
        private readonly MeasurementProtocol $client,
        private readonly Config $config,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param callable|null $output function(string $line): void
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendPending(bool $dryRun = false, ?callable $output = null, int $limit = 100): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($this->queue->getPending($limit) as $row) {
            $refundId = (int)$row['refund_id'];
            $storeId = (int)$row['store_id'];
            if (!$this->config->isRefundEnabled($storeId)) {
                $result['skipped']++;
                continue;
            }
            $params = $this->json->unserialize((string)$row['payload']);
            $tracking = $this->orderTracking->get((int)$row['order_id']);
            $clientId = (string)($tracking['ga_client_id'] ?? '');
            if ($clientId === '') {
                // No browser client id (consent denied or order created in admin): GA4 still needs one.
                $clientId = sprintf('%d.%d', crc32('order-' . $row['order_id']), (int)strtotime((string)$row['created_at']));
            }
            if (!empty($tracking['ga_session_id'])) {
                $params['session_id'] = $tracking['ga_session_id'];
            }
            $events = [['name' => 'refund', 'params' => $params]];
            if ($dryRun) {
                $output && $output(sprintf('#%d client_id=%s %s', $refundId, $clientId, $this->json->serialize($events)));
                continue;
            }
            if (!$this->queue->claim($refundId, (int)$row['attempts'])) {
                $result['skipped']++;
                continue;
            }
            try {
                $this->client->send($storeId, $clientId, $events);
                $this->queue->markSent($refundId);
                $result['sent']++;
                $output && $output(sprintf('#%d sent (transaction %s)', $refundId, $params['transaction_id'] ?? '?'));
            } catch (\Throwable $e) {
                $this->queue->markFailed($refundId, $e->getMessage());
                $this->logger->warning('CommerceGears_Ga4: refund #' . $refundId . ' not sent: ' . $e->getMessage());
                $result['failed']++;
                $output && $output(sprintf('#%d failed: %s', $refundId, $e->getMessage()));
            }
        }
        return $result;
    }
}
