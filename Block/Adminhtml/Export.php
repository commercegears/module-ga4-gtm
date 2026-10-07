<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Softaware\Ga4\Model\Config;
use Softaware\Ga4\Model\GtmContainerExport;

class Export extends Template
{
    /** @var string */
    protected $_template = 'Softaware_Ga4::export.phtml';

    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array{id: int, name: string, measurement_id: string, gtm_id: string}>
     */
    public function getStores(): array
    {
        $stores = [[
            'id' => 0,
            'name' => (string)__('Default configuration'),
            'measurement_id' => $this->config->getMeasurementId(0),
            'gtm_id' => $this->config->getGtmId(0),
        ]];
        foreach ($this->_storeManager->getStores() as $store) {
            $stores[] = [
                'id' => (int)$store->getId(),
                'name' => $store->getWebsite()->getName() . ' / ' . $store->getName(),
                'measurement_id' => $this->config->getMeasurementId((int)$store->getId()),
                'gtm_id' => $this->config->getGtmId((int)$store->getId()),
            ];
        }
        return $stores;
    }

    /**
     * @return string[]
     */
    public function getEcommerceEvents(): array
    {
        return GtmContainerExport::ECOMMERCE_EVENTS;
    }

    /**
     * Store views where the module is switched on (for the status line above the form).
     */
    public function countEnabledStores(): int
    {
        $count = 0;
        foreach ($this->_storeManager->getStores() as $store) {
            if ($this->config->isEnabled((int)$store->getId())) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Store views that send events with gtag.js instead of GTM (the container is not used there).
     */
    public function countGtagStores(): int
    {
        $count = 0;
        foreach ($this->_storeManager->getStores() as $store) {
            $id = (int)$store->getId();
            if ($this->config->isEnabled($id) && $this->config->getMode($id) === Config::MODE_GTAG) {
                $count++;
            }
        }
        return $count;
    }

    public function getDownloadUrl(): string
    {
        return $this->getUrl('swga4/export/download');
    }

    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'softaware_ga4']);
    }
}
