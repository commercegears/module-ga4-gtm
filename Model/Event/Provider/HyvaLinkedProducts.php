<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model\Event\Provider;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\LinkFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Softaware\Ga4\Api\PageEventProviderInterface;
use Softaware\Ga4\Model\Config;
use Softaware\Ga4\Model\ListRegistry;

/**
 * Hyvä renders related, upsell and cross-sell sliders through its own ProductList view model, so they are
 * registered here (same links, same order: link position) instead of with a plugin on a Hyvä class. This
 * keeps the module free of a hard dependency on Hyvä. Added in hyva_* layout handles only. Returns no events
 * itself: the lists become view_item_list events in the dataLayer block.
 */
class HyvaLinkedProducts implements PageEventProviderInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly CheckoutSession $checkoutSession,
        private readonly ListRegistry $listRegistry,
        private readonly Config $config,
        private readonly Visibility $visibility,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LinkFactory $linkFactory,
        private readonly string $context = 'product'
    ) {
    }

    public function getEvents(): array
    {
        $limit = $this->config->getMaxListItems();
        if ($this->context === 'cart') {
            $this->registerCrosssell($limit);
            return [];
        }
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product || !$product->getId()) {
            return [];
        }
        foreach (['upsell_products' => 'getUpSellProductCollection', 'related_products' => 'getRelatedProductCollection'] as $id => $method) {
            $collection = $product->{$method}()
                ->addAttributeToSelect(['name', 'sku', 'price', 'special_price', 'url_key'])
                ->setPositionOrder()
                ->addStoreFilter()
                ->setVisibility($this->visibility->getVisibleInCatalogIds())
                ->setPageSize($limit);
            $collection->addFinalPrice();
            if ($collection->getSize()) {
                $name = $id === 'related_products' ? 'Related products' : 'Upsell products';
                $this->listRegistry->add($id, (string)__($name), $collection);
            }
        }
        return [];
    }

    private function registerCrosssell(int $limit): void
    {
        $inCart = [];
        foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $inCart[] = (int)$item->getProductId();
        }
        if (!$inCart) {
            return;
        }
        $max = (int)$this->scopeConfig->getValue('checkout/cart/crosssell_count', ScopeInterface::SCOPE_STORE) ?: 4;
        $collection = $this->linkFactory->create()->useCrossSellLinks()->getProductCollection()
            ->addAttributeToSelect(['name', 'sku', 'price', 'special_price', 'url_key'])
            ->addStoreFilter()
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addProductFilter($inCart)
            ->addExcludeProductFilter($inCart)
            ->setPositionOrder()
            ->setPageSize(min($max, $limit));
        $collection->addFinalPrice();
        if ($collection->getSize()) {
            $this->listRegistry->add('crosssell_products', (string)__('Cross-sell products'), $collection);
        }
    }
}
