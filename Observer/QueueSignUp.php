<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Observer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\EventQueue;
use CommerceGears\Ga4\Model\UserData;

/**
 * customer_register_success (frontend): queues "sign_up".
 */
class QueueSignUp implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly EventQueue $queue,
        private readonly UserData $userData
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isActive()) {
            return;
        }
        $customer = $observer->getEvent()->getData('customer');
        $userData = $customer instanceof CustomerInterface ? $this->userData->fromCustomer($customer) : [];
        $this->queue->add('sign_up', ['method' => 'email'], $userData ? ['user_data' => $userData] : []);
    }
}
