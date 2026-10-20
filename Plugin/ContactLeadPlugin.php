<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Plugin;

use CommerceGears\Ga4\Model\LeadTracker;

/**
 * Contact Us form: Magento\Contact\Model\MailInterface::send() is only reached after the form passed validation and
 * returns without an exception only when the message was accepted, so generate_lead is queued after it (frontend).
 */
class ContactLeadPlugin
{
    public function __construct(private readonly LeadTracker $leadTracker)
    {
    }

    /**
     * @param \Magento\Contact\Model\MailInterface $subject
     * @param mixed $result
     * @param string $replyTo
     * @param array<string, mixed> $variables
     * @return mixed
     */
    public function afterSend($subject, $result, $replyTo, array $variables = [])
    {
        $data = $variables['data'] ?? null;
        $phone = is_object($data) && method_exists($data, 'getData') ? (string)$data->getData('telephone') : '';
        $this->leadTracker->track('contact', 'Contact Us', '', (string)$replyTo, $phone, LeadTracker::FORM_CONTACT);
        return $result;
    }
}
