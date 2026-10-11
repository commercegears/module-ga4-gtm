<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

/**
 * Request-scoped collection of product lists rendered on the page (category, search, related, upsell,
 * cross-sell). Filled by plugins while the page renders; read by the dataLayer block at the end of <body>.
 * Other modules can register their own lists with add().
 */
class ListRegistry
{
    /** @var array<string, array{id: string, name: string, products: iterable, category_id: int|null}> */
    private array $lists = [];

    /**
     * @param iterable<\Magento\Catalog\Model\Product> $products Loaded now or later (collections are read at the end of the page)
     */
    public function add(string $listId, string $listName, iterable $products, ?int $categoryId = null): void
    {
        if (!isset($this->lists[$listId])) {
            $this->lists[$listId] = ['id' => $listId, 'name' => $listName, 'products' => $products, 'category_id' => $categoryId];
        }
    }

    /**
     * @return array<string, array{id: string, name: string, products: iterable, category_id: int|null}>
     */
    public function getLists(): array
    {
        return $this->lists;
    }
}
