<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds GA4 "items" entries (https://developers.google.com/analytics/devguides/collection/ga4/reference/events)
 * from products, quote items, order items and credit memo items.
 */
class ItemBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly ProductDataLoader $productData,
        private readonly CategoryResolver $categoryResolver,
        private readonly CatalogHelper $catalogHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getCurrency(?int $storeId = null): string
    {
        $store = $this->storeManager->getStore($storeId);
        return $this->config->useBaseCurrency($storeId)
            ? (string)$store->getBaseCurrencyCode()
            : (string)$store->getCurrentCurrencyCode();
    }

    /**
     * @param Product[] $products
     */
    public function preloadProducts(array $products, int $storeId): void
    {
        $this->productData->preload(array_map(static fn ($p): int => (int)$p->getId(), $products), $storeId);
    }

    /**
     * @param int[] $productIds
     */
    public function preloadProductIds(array $productIds, int $storeId): void
    {
        $this->productData->preload($productIds, $storeId);
    }

    /**
     * @param array{list_id?: string, list_name?: string, index?: int, quantity?: float, category_id?: int|null} $context
     * @return array<string, mixed>
     */
    public function fromProduct(Product $product, array $context = []): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $data = $this->productData->get((int)$product->getId(), $storeId);
        $price = $this->productPrice($product, $storeId);
        $categoryId = $context['category_id'] ?? null;
        if (!$categoryId) {
            $categoryId = $this->categoryResolver->pickCategory($data['category_ids'], $storeId);
        }
        $item = [
            'item_id' => $this->itemId((int)$product->getId(), (string)($product->getData('sku') ?: $data['sku'])),
            'item_name' => (string)$product->getName(),
            'affiliation' => $this->config->getAffiliation($storeId),
            'item_brand' => $data['brand'] !== '' ? $data['brand'] : $this->config->getDefaultBrand($storeId),
        ];
        $item += CategoryResolver::toItemFields($categoryId ? $this->categoryResolver->getPathNames((int)$categoryId, $storeId) : []);
        if (isset($context['list_id'])) {
            $item['item_list_id'] = $context['list_id'];
            $item['item_list_name'] = $context['list_name'] ?? $context['list_id'];
        }
        if (isset($context['index'])) {
            $item['index'] = (int)$context['index'];
        }
        $item['price'] = $this->round($price);
        $item['quantity'] = (float)($context['quantity'] ?? 1);
        return $this->clean($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function fromQuoteItem(QuoteItem $item, ?float $quantity = null): array
    {
        $storeId = (int)$item->getStoreId() ?: (int)$this->storeManager->getStore()->getId();
        $product = $item->getProduct();
        $productId = (int)$item->getProductId();
        $data = $this->productData->get($productId, $storeId);
        $qty = (float)($item->getQty() ?: 1);
        $base = $this->config->useBaseCurrency($storeId);
        $inclTax = $this->config->isPriceInclTax($storeId);
        $unit = $inclTax
            ? (float)($base ? $item->getBasePriceInclTax() : $item->getPriceInclTax())
            : (float)($base ? $item->getBaseCalculationPrice() : $item->getCalculationPrice());
        if ($unit <= 0.0 && $product) {
            $unit = $this->productPrice($product, $storeId);
        }
        $discount = (float)($base ? $item->getBaseDiscountAmount() : $item->getDiscountAmount()) / $qty;
        $sku = $product ? (string)$product->getData('sku') : '';
        $result = [
            'item_id' => $this->itemId($productId, $sku !== '' ? $sku : $data['sku']),
            'item_name' => (string)$item->getName(),
            'affiliation' => $this->config->getAffiliation($storeId),
            'item_brand' => $data['brand'] !== '' ? $data['brand'] : $this->config->getDefaultBrand($storeId),
        ];
        $result += $this->categoryFields($data['category_ids'], $storeId);
        if ($item->getProductType() === 'configurable' && (string)$item->getSku() !== $sku) {
            $result['item_variant'] = (string)$item->getSku();
        }
        if ($discount > 0) {
            $result['discount'] = $this->round($discount);
        }
        $result['price'] = $this->round(max(0.0, $unit - $discount));
        $result['quantity'] = $quantity ?? $qty;
        return $this->clean($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function fromOrderItem(OrderItemInterface $item, ?float $quantity = null, bool $base = false): array
    {
        $storeId = (int)$item->getStoreId();
        $productId = (int)$item->getProductId();
        $data = $this->productData->get($productId, $storeId);
        $qty = (float)($item->getQtyOrdered() ?: 1);
        $inclTax = $this->config->isPriceInclTax($storeId);
        $unit = $inclTax
            ? (float)($base ? $item->getBasePriceInclTax() : $item->getPriceInclTax())
            : (float)($base ? $item->getBasePrice() : $item->getPrice());
        $discount = (float)($base ? $item->getBaseDiscountAmount() : $item->getDiscountAmount()) / $qty;
        $result = [
            'item_id' => $this->itemId($productId, $data['sku'] !== '' ? $data['sku'] : (string)$item->getSku()),
            'item_name' => (string)$item->getName(),
            'affiliation' => $this->config->getAffiliation($storeId),
            'item_brand' => $data['brand'] !== '' ? $data['brand'] : $this->config->getDefaultBrand($storeId),
        ];
        $result += $this->categoryFields($data['category_ids'], $storeId);
        if ($item->getProductType() === 'configurable' && $data['sku'] !== '' && (string)$item->getSku() !== $data['sku']) {
            $result['item_variant'] = (string)$item->getSku();
        }
        if ($discount > 0) {
            $result['discount'] = $this->round($discount);
        }
        $result['price'] = $this->round(max(0.0, $unit - $discount));
        $result['quantity'] = $quantity ?? $qty;
        return $this->clean($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function fromCreditmemoItem(CreditmemoItem $item, bool $base = false): array
    {
        $orderItem = $item->getOrderItem();
        $result = $this->fromOrderItem($orderItem, (float)$item->getQty(), $base);
        $qty = max(1.0, (float)$item->getQty());
        $inclTax = $this->config->isPriceInclTax((int)$orderItem->getStoreId());
        $row = (float)($base
            ? ($inclTax ? $item->getBaseRowTotalInclTax() : $item->getBaseRowTotal())
            : ($inclTax ? $item->getRowTotalInclTax() : $item->getRowTotal()));
        $discount = (float)($base ? $item->getBaseDiscountAmount() : $item->getDiscountAmount());
        $result['price'] = $this->round(max(0.0, ($row - $discount) / $qty));
        return $result;
    }

    /**
     * Sum of price * quantity.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function sumValue(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += (float)($item['price'] ?? 0) * (float)($item['quantity'] ?? 1);
        }
        return $this->round($sum);
    }

    public function round(float $value): float
    {
        return round($value, 2);
    }

    /**
     * Product final price (lowest for configurable/bundle) in the reporting currency.
     */
    public function productPrice(Product $product, ?int $storeId = null): float
    {
        try {
            $amount = $product->getPriceInfo()->getPrice('final_price')->getAmount();
            $price = (float)$amount->getValue(['tax', 'weee', 'weee_tax']);
        } catch (\Throwable) {
            $price = (float)$product->getFinalPrice();
        }
        if ($this->config->isPriceInclTax($storeId)) {
            $price = (float)$this->catalogHelper->getTaxPrice($product, $price, true, null, null, null, $storeId, false);
        }
        if (!$this->config->useBaseCurrency($storeId)) {
            $price = (float)$this->priceCurrency->convert($price, $storeId);
        }
        return $price;
    }

    /**
     * @param int[] $categoryIds
     * @return array<string, string>
     */
    private function categoryFields(array $categoryIds, int $storeId): array
    {
        $categoryId = $this->categoryResolver->pickCategory($categoryIds, $storeId);
        return CategoryResolver::toItemFields($categoryId ? $this->categoryResolver->getPathNames($categoryId, $storeId) : []);
    }

    private function itemId(int $productId, string $sku): string
    {
        return $this->config->useSku() && $sku !== '' ? $sku : (string)$productId;
    }

    /**
     * Drops empty strings so GA4 does not receive blank dimensions.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function clean(array $item): array
    {
        return array_filter($item, static fn ($v): bool => $v !== '' && $v !== null);
    }
}
