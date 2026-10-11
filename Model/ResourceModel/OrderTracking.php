<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * commercegears_ga4_order: GA client id per order and the "purchase already sent" flag.
 */
class OrderTracking
{
    private const TABLE = 'commercegears_ga4_order';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function saveClientId(int $orderId, string $clientId, string $sessionId): void
    {
        $connection = $this->resource->getConnection();
        $connection->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            ['order_id' => $orderId, 'ga_client_id' => $clientId ?: null, 'ga_session_id' => $sessionId ?: null],
            ['ga_client_id', 'ga_session_id']
        );
    }

    /**
     * Atomically marks the purchase as tracked. Returns false when it had already been tracked.
     */
    public function markPurchaseTracked(int $orderId): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $affected = $connection->query(
            'INSERT INTO ' . $connection->quoteIdentifier($table) . ' (order_id, purchase_tracked_at) VALUES (?, UTC_TIMESTAMP())'
            . ' ON DUPLICATE KEY UPDATE purchase_tracked_at = IFNULL(purchase_tracked_at, VALUES(purchase_tracked_at))',
            [$orderId]
        )->rowCount();
        return $affected > 0;
    }

    /**
     * @return array{ga_client_id: ?string, ga_session_id: ?string}|null
     */
    public function get(int $orderId): ?array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE), ['ga_client_id', 'ga_session_id'])
                ->where('order_id = ?', $orderId)
        );
        return $row ?: null;
    }
}
