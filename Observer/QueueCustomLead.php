<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use CommerceGears\Ga4\Model\LeadTracker;

/**
 * commercegears_ga4_lead (frontend), dispatched by other modules after their own successful form submit:
 * $eventManager->dispatch('commercegears_ga4_lead', ['form_id' => 'quote', 'form_name' => 'Quote request',
 * 'lead_source' => 'product page', 'email' => $email]). Needs "Other forms" in Leads > Forms.
 */
class QueueCustomLead implements ObserverInterface
{
    public function __construct(private readonly LeadTracker $leadTracker)
    {
    }

    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $this->leadTracker->track(
            (string)$event->getData('form_id'),
            (string)$event->getData('form_name'),
            (string)$event->getData('lead_source'),
            (string)$event->getData('email'),
            (string)$event->getData('telephone'),
            LeadTracker::FORM_CUSTOM
        );
    }
}
