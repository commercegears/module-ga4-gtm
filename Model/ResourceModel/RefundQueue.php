<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * softaware_ga4_refund: refund events to send through the Measurement Protocol.
 */
class RefundQueue
{
    private const TABLE = 'softaware_ga4_refund';
    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function add(int $orderId, int $creditmemoId, int $storeId, string $payload): void
    {
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            ['order_id' => $orderId, 'creditmemo_id' => $creditmemoId, 'store_id' => $storeId, 'payload' => $payload],
            ['payload']
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPending(int $limit = 100): array
    {
        $connection = $this->resource->getConnection();
        return $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE))
                ->where('status IN (?)', ['pending', 'failed'])
                ->where('attempts < ?', self::MAX_ATTEMPTS)
                ->order('refund_id ASC')
                ->limit($limit)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLatest(int $limit = 20): array
    {
        $connection = $this->resource->getConnection();
        return $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE))
                ->order('refund_id DESC')
                ->limit($limit)
        );
    }

    /**
     * Takes one attempt at a queued refund. Returns false when another process (cron and the CLI at the same time)
     * already took it, so a refund is never sent twice.
     */
    public function claim(int $refundId, int $attempts): bool
    {
        return $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TABLE),
            ['attempts' => $attempts + 1],
            ['refund_id = ?' => $refundId, 'attempts = ?' => $attempts, 'status IN (?)' => ['pending', 'failed']]
        ) === 1;
    }

    public function markSent(int $refundId): void
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TABLE),
            ['status' => 'sent', 'sent_at' => gmdate('Y-m-d H:i:s'), 'last_error' => null],
            ['refund_id = ?' => $refundId]
        );
    }

    public function markFailed(int $refundId, string $error): void
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TABLE),
            ['status' => 'failed', 'last_error' => mb_substr($error, 0, 2000)],
            ['refund_id = ?' => $refundId]
        );
    }
}
