<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Queues GA4 generate_lead after a form was submitted successfully on the server (never on a button click), plus the
 * Google Ads lead conversion when a lead conversion label is set. Delivered on the next page through the
 * customer-data section, exactly once (same id as the event). Parameters are fixed form names, never form input;
 * the visitor's email/phone only go into hashed user_data (Enhanced Conversions setting, consent respected by ga4.js).
 *
 * Other modules: call track('quote_request', 'Quote request') after their own successful submit, or dispatch the
 * event "commercegears_ga4_lead" with form_id, form_name and optionally lead_source and email. Both need
 * "Other forms" in Leads > Forms.
 */
class LeadTracker
{
    public const EVENT = 'generate_lead';
    public const FORM_CONTACT = 'contact';
    public const FORM_NEWSLETTER = 'newsletter';
    public const FORM_CUSTOM = 'custom';

    public function __construct(
        private readonly Config $config,
        private readonly EventQueue $queue,
        private readonly UserData $userData,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string $type   'contact', 'newsletter' or 'custom' (the Leads > Forms option that must be on)
     * @param string $formId short id such as "contact"; plain text only
     * @return bool true when the event was queued
     */
    public function track(
        string $formId,
        string $formName,
        string $leadSource = '',
        string $email = '',
        string $phone = '',
        string $type = self::FORM_CUSTOM
    ): bool {
        try {
            $store = $this->storeManager->getStore();
            $storeId = (int)$store->getId();
            if (!$this->config->isActive($storeId) || !$this->config->isLeadFormEnabled($type, $storeId)) {
                return false;
            }
            $params = array_filter([
                'form_id' => self::clean($formId),
                'form_name' => self::clean($formName),
                'lead_source' => self::clean($leadSource),
            ], static fn (string $v): bool => $v !== '');
            if (!isset($params['form_id'])) {
                return false;
            }
            $value = $this->config->getLeadValue($storeId);
            if ($value !== null) {
                $params['currency'] = $this->config->useBaseCurrency($storeId)
                    ? (string)$store->getBaseCurrencyCode()
                    : (string)$store->getCurrentCurrencyCode();
                $params['value'] = $value;
            }
            $conversion = [];
            $sendTo = $this->config->getAdsSendTo('lead', $storeId);
            if ($sendTo !== '') {
                $conversion = ['send_to' => $sendTo];
                if ($value !== null) {
                    $conversion['value'] = $value;
                    $conversion['currency'] = $params['currency'];
                }
            }
            $userData = $this->userData->fromContact($email, $phone, $storeId);
            $this->queue->add(self::EVENT, $params, $userData ? ['user_data' => $userData] : [], $conversion);
            return true;
        } catch (\Throwable $e) {
            // Tracking must never break the form submission.
            $this->logger->warning('CommerceGears_Ga4: generate_lead not queued: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Plain text up to 100 characters; anything that looks like an email address is dropped (no PII in params).
     */
    public static function clean(string $value): string
    {
        $value = trim((string)preg_replace('/[\x00-\x1f<>"]+/u', ' ', $value));
        if ($value === '' || str_contains($value, '@')) {
            return '';
        }
        return mb_substr((string)preg_replace('/\s+/u', ' ', $value), 0, 100);
    }
}
