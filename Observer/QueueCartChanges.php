<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Psr\Log\LoggerInterface;
use Softaware\Ga4\Model\Config;
use Softaware\Ga4\Model\EventQueue;
use Softaware\Ga4\Model\ItemBuilder;

/**
 * sales_quote_save_before (frontend): compares every cart line with its stored quantity and queues
 * add_to_cart / remove_from_cart for the difference. Catches product page, listing (AJAX), mini-cart,
 * cart page, wishlist and reorder changes on Luma and Hyvä alike. Quantities already reported in this
 * request are remembered, so a quote saved twice is not counted twice; merges at login are skipped.
 */
class QueueCartChanges implements ObserverInterface
{
    public const SKIP_FLAG = 'softaware_ga4_skip_tracking';

    /** @var array<int, float> spl_object_id(item) => qty already reported */
    private array $reported = [];

    public function __construct(
        private readonly Config $config,
        private readonly EventQueue $queue,
        private readonly ItemBuilder $itemBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Quote|null $quote */
        $quote = $observer->getEvent()->getData('quote');
        if (!$quote || $quote->getData(self::SKIP_FLAG)
            || ($quote->getData('is_active') !== null && !(int)$quote->getData('is_active'))
            || !$this->config->isActive((int)$quote->getStoreId())
        ) {
            return;
        }
        try {
            $added = [];
            $removed = [];
            /** @var QuoteItem $item */
            foreach ($quote->getItemsCollection() as $item) {
                if ($item->getParentItemId() || $item->getParentItem()) {
                    continue;
                }
                $key = spl_object_id($item);
                $before = $this->reported[$key] ?? ($item->getId() ? (float)$item->getOrigData('qty') : 0.0);
                $now = $item->isDeleted() ? 0.0 : (float)$item->getQty();
                $this->reported[$key] = $now;
                $delta = round($now - $before, 4);
                if ($delta == 0.0) {
                    continue;
                }
                $entry = $this->itemBuilder->fromQuoteItem($item, abs($delta));
                if ($delta > 0) {
                    $added[] = $entry;
                } else {
                    $removed[] = $entry;
                }
            }
            $currency = $this->itemBuilder->getQuoteCurrency($quote);
            if ($added) {
                $this->queue->add('add_to_cart', [
                    'currency' => $currency,
                    'value' => $this->itemBuilder->sumValue($added),
                    'items' => $added,
                ]);
            }
            if ($removed) {
                $this->queue->add('remove_from_cart', [
                    'currency' => $currency,
                    'value' => $this->itemBuilder->sumValue($removed),
                    'items' => $removed,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Softaware_Ga4: could not track cart change: ' . $e->getMessage());
        }
    }
}
