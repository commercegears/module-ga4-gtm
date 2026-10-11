<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Category names and paths for the store's category tree, cached per store (invalidated on category save).
 */
class CategoryResolver
{
    private const CACHE_PREFIX = 'sw_ga4_categories_';

    /** @var array<int, array<int, array{name: string, path: string, level: int}>> */
    private array $trees = [];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly Config $config
    ) {
    }

    /**
     * Names from the top-level category down to $categoryId (store root excluded), max 5.
     *
     * @return string[]
     */
    public function getPathNames(int $categoryId, ?int $storeId = null): array
    {
        $tree = $this->getTree($storeId);
        if (!isset($tree[$categoryId])) {
            return [];
        }
        $names = [];
        foreach (explode('/', $tree[$categoryId]['path']) as $id) {
            $id = (int)$id;
            if (isset($tree[$id]) && $tree[$id]['level'] >= 2) {
                $names[] = $tree[$id]['name'];
            }
        }
        return array_slice($names, 0, 5);
    }

    /**
     * Picks the category to report from a product's category ids.
     *
     * @param int[] $categoryIds
     */
    public function pickCategory(array $categoryIds, ?int $storeId = null): ?int
    {
        $tree = $this->getTree($storeId);
        $candidates = array_values(array_filter(array_map('intval', $categoryIds), static fn (int $id): bool => isset($tree[$id])));
        if (!$candidates) {
            return null;
        }
        if ($this->config->getCategoryPathMode($storeId) === 'first') {
            return $candidates[0];
        }
        usort($candidates, static fn (int $a, int $b): int => [$tree[$b]['level'], $a] <=> [$tree[$a]['level'], $b]);
        return $candidates[0];
    }

    /**
     * @param string[] $names
     * @return array<string, string> item_category .. item_category5
     */
    public static function toItemFields(array $names): array
    {
        $fields = [];
        foreach (array_values($names) as $i => $name) {
            $fields[$i === 0 ? 'item_category' : 'item_category' . ($i + 1)] = $name;
        }
        return $fields;
    }

    /**
     * @return array<int, array{name: string, path: string, level: int}>
     */
    private function getTree(?int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);
        $storeId = (int)$store->getId();
        if (isset($this->trees[$storeId])) {
            return $this->trees[$storeId];
        }
        $cacheKey = self::CACHE_PREFIX . $storeId;
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return $this->trees[$storeId] = $this->json->unserialize($cached);
        }
        $rootId = (int)$store->getRootCategoryId();
        $collection = $this->collectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('path', ['like' => '1/' . $rootId . '/%'])
            ->addIsActiveFilter();
        $tree = [];
        foreach ($collection as $category) {
            $tree[(int)$category->getId()] = [
                'name' => (string)$category->getName(),
                'path' => (string)$category->getPath(),
                'level' => (int)$category->getLevel(),
            ];
        }
        $this->cache->save($this->json->serialize($tree), $cacheKey, [Category::CACHE_TAG], 86400);
        return $this->trees[$storeId] = $tree;
    }
}
