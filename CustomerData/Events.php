<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Softaware\Ga4\Model\Config;
use Softaware\Ga4\Model\EventQueue;

/**
 * Customer-data section "softaware-ga4": queued ecommerce events for the current visitor.
 */
class Events implements SectionSourceInterface
{
    public function __construct(
        private readonly EventQueue $queue,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{events: array<int, array<string, mixed>>}
     */
    public function getSectionData(): array
    {
        if (!$this->config->isActive()) {
            return ['events' => []];
        }
        $events = [];
        foreach ($this->queue->getPending() as $event) {
            unset($event['ts']);
            $events[] = $event;
        }
        return ['events' => $events];
    }
}
