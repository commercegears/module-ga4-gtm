<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Plugin;

use CommerceGears\Ga4\Model\LeadTracker;
use Magento\Framework\DataObject;

/**
 * Newsletter sign-up (frontend): generate_lead after Magento\Newsletter\Model\SubscriptionManagerInterface accepted the
 * subscription (subscribed, or waiting for the confirmation email).
 */
class NewsletterLeadPlugin
{
    /** Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED and STATUS_NOT_ACTIVE */
    private const ACCEPTED = [1, 2];

    public function __construct(private readonly LeadTracker $leadTracker)
    {
    }

    /**
     * @param \Magento\Newsletter\Model\SubscriptionManagerInterface $subject
     * @param \Magento\Newsletter\Model\Subscriber $result
     * @return \Magento\Newsletter\Model\Subscriber
     */
    public function afterSubscribe($subject, $result)
    {
        $this->track($result);
        return $result;
    }

    /**
     * @param \Magento\Newsletter\Model\SubscriptionManagerInterface $subject
     * @param \Magento\Newsletter\Model\Subscriber $result
     * @return \Magento\Newsletter\Model\Subscriber
     */
    public function afterSubscribeCustomer($subject, $result)
    {
        $this->track($result);
        return $result;
    }

    /**
     * @param mixed $subscriber
     */
    private function track($subscriber): void
    {
        // Subscriber getters are magic (DataObject), so read the fields with getData().
        if (!$subscriber instanceof DataObject
            || !in_array((int)$subscriber->getData('subscriber_status'), self::ACCEPTED, true)
        ) {
            return;
        }
        $email = (string)$subscriber->getData('subscriber_email');
        $this->leadTracker->track('newsletter', 'Newsletter', '', $email, '', LeadTracker::FORM_NEWSLETTER);
    }
}
