<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Block;

use Magento\Catalog\Model\Product;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Psr\Log\LoggerInterface;
use CommerceGears\Ga4\Api\PageEventProviderInterface;
use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\ItemBuilder;
use CommerceGears\Ga4\Model\ListRegistry;

/**
 * Rendered at the end of <body>: page events from the providers plus view_item_list for every product
 * list rendered above (and the URL map used for select_item). Only public catalogue data on cacheable
 * pages; visitor-specific events come through the customer-data section.
 */
class DataLayer extends Template
{
    /** @var string */
    protected $_template = 'CommerceGears_Ga4::datalayer.phtml';

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly ListRegistry $listRegistry,
        private readonly ItemBuilder $itemBuilder,
        private readonly JsonHexTag $json,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isActive(): bool
    {
        return $this->config->isActive();
    }

    public function getScriptUrl(): string
    {
        return $this->getViewFileUrl('CommerceGears_Ga4::js/ga4.js');
    }

    public function getPayloadJson(): string
    {
        return $this->json->serialize([
            'mode' => $this->config->getMode(),
            'debug' => $this->config->isDebug(),
            // user_data waits for ad_user_data consent when CommerceGears Cookie Consent is active.
            'consentGate' => $this->config->isCookieConsentActive(),
            'sendTo' => $this->getSendTo(),
            'events' => $this->collectEvents(),
            'lists' => $this->collectLists(),
            // Click events (public settings only): [{event, selector}]
            'clicks' => $this->config->getClickRules(),
        ]);
    }

    /**
     * gtag.js with additional Google tag IDs (or a Google Ads conversion ID): events go to the GA4 measurement IDs only
     * (main and additional G- IDs), never to Google Ads; Ads only receives the explicit conversion events.
     * Empty = gtag default (unchanged behaviour).
     *
     * @return string[]
     */
    private function getSendTo(): array
    {
        $additional = $this->config->getMode() === Config::MODE_GTAG ? $this->config->getGtagConfigIds() : [];
        if (!$additional) {
            return [];
        }
        $sendTo = [$this->config->getMeasurementId()];
        foreach ($additional as $id) {
            if (str_starts_with($id, 'G-')) {
                $sendTo[] = $id;
            }
        }
        return $sendTo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectEvents(): array
    {
        $events = [];
        foreach ((array)$this->getData('providers') as $name => $provider) {
            if (!$provider instanceof PageEventProviderInterface) {
                continue;
            }
            try {
                foreach ($provider->getEvents() as $event) {
                    $events[] = $event;
                }
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf('CommerceGears_Ga4: provider "%s" failed: %s', $name, $e->getMessage()));
            }
        }
        return $events;
    }

    /**
     * @return array<int, array{id: string, name: string, items: array<int, array<string, mixed>>, urls: string[]}>
     */
    private function collectLists(): array
    {
        $max = $this->config->getMaxListItems();
        $storeId = (int)$this->_storeManager->getStore()->getId();
        $result = [];
        foreach ($this->listRegistry->getLists() as $list) {
            try {
                $products = [];
                foreach ($list['products'] as $product) {
                    if ($product instanceof Product && $product->getId()) {
                        $products[] = $product;
                    }
                    if (count($products) >= $max) {
                        break;
                    }
                }
                if (!$products) {
                    continue;
                }
                $offset = 0;
                $collection = $list['products'];
                if (is_object($collection) && method_exists($collection, 'getCurPage') && method_exists($collection, 'getPageSize')) {
                    $offset = max(0, ((int)$collection->getCurPage() - 1) * (int)$collection->getPageSize());
                }
                $this->itemBuilder->preloadProducts($products, $storeId);
                $items = [];
                $urls = [];
                foreach ($products as $i => $product) {
                    $items[] = $this->itemBuilder->fromProduct($product, [
                        'list_id' => $list['id'],
                        'list_name' => $list['name'],
                        'index' => $offset + $i,
                        'category_id' => $list['category_id'],
                    ]);
                    $urls[] = (string)$product->getProductUrl();
                }
                $result[] = ['id' => $list['id'], 'name' => $list['name'], 'items' => $items, 'urls' => $urls];
            } catch (\Throwable $e) {
                $this->logger->warning('CommerceGears_Ga4: list "' . $list['id'] . '" failed: ' . $e->getMessage());
            }
        }
        return $result;
    }

    protected function _toHtml(): string
    {
        return $this->isActive() ? parent::_toHtml() : '';
    }
}
