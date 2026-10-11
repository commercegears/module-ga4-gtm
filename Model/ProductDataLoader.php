<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * Loads SKU, brand and category ids for many products with two queries (avoids N+1 in lists and orders).
 */
class ProductDataLoader
{
    /** @var array<string, array{sku: string, brand: string, category_ids: int[]}> */
    private array $data = [];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ResourceConnection $resource,
        private readonly Config $config
    ) {
    }

    /**
     * @param int[] $productIds
     */
    public function preload(array $productIds, int $storeId): void
    {
        $missing = [];
        foreach (array_unique(array_map('intval', $productIds)) as $id) {
            if ($id > 0 && !isset($this->data[$storeId . '_' . $id])) {
                $missing[] = $id;
            }
        }
        if (!$missing) {
            return;
        }
        $brandAttribute = $this->config->getBrandAttribute($storeId);
        $collection = $this->collectionFactory->create()
            ->setStoreId($storeId)
            ->addIdFilter($missing);
        if ($brandAttribute !== '') {
            $collection->addAttributeToSelect($brandAttribute);
        }
        $usesSource = false;
        if ($brandAttribute !== '') {
            $attribute = $collection->getEntity()->getAttribute($brandAttribute);
            $usesSource = $attribute && $attribute->usesSource();
        }
        $rows = [];
        foreach ($collection as $product) {
            $brand = '';
            if ($brandAttribute !== '') {
                $value = $usesSource ? $product->getAttributeText($brandAttribute) : $product->getData($brandAttribute);
                $brand = is_array($value) ? implode(', ', $value) : (is_scalar($value) ? (string)$value : '');
                if ($value === false) {
                    $brand = '';
                }
            }
            $rows[(int)$product->getId()] = ['sku' => (string)$product->getSku(), 'brand' => trim($brand), 'category_ids' => []];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_category_product'), ['product_id', 'category_id'])
            ->where('product_id IN (?)', $missing)
            ->order(['product_id', 'position', 'category_id']);
        foreach ($connection->fetchAll($select) as $row) {
            if (isset($rows[(int)$row['product_id']])) {
                $rows[(int)$row['product_id']]['category_ids'][] = (int)$row['category_id'];
            }
        }
        foreach ($missing as $id) {
            $this->data[$storeId . '_' . $id] = $rows[$id] ?? ['sku' => '', 'brand' => '', 'category_ids' => []];
        }
    }

    /**
     * @return array{sku: string, brand: string, category_ids: int[]}
     */
    public function get(int $productId, int $storeId): array
    {
        $this->preload([$productId], $storeId);
        return $this->data[$storeId . '_' . $productId];
    }
}
