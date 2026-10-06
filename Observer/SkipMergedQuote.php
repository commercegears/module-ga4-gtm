<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * sales_quote_merge_before: a guest cart merged into the customer cart at login is not a new add_to_cart.
 */
class SkipMergedQuote implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        $quote?->setData(QueueCartChanges::SKIP_FLAG, true);
    }
}
