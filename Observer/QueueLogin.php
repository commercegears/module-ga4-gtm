<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Observer;

use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\EventQueue;
use CommerceGears\Ga4\Model\UserData;

/**
 * customer_login (frontend): queues "login" (not after a registration, which queues "sign_up").
 */
class QueueLogin implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly EventQueue $queue,
        private readonly UserData $userData
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isActive() || $this->queue->wasQueuedInThisRequest('sign_up')) {
            return;
        }
        $customer = $observer->getEvent()->getData('customer');
        $extra = [];
        if ($customer instanceof Customer) {
            $userData = $this->userData->fromCustomer($customer->getDataModel());
            $extra = $userData ? ['user_data' => $userData] : [];
        }
        $this->queue->add('login', ['method' => 'email'], $extra);
    }
}
