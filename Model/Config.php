<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Typed access to the commercegears_ga4 configuration section.
 */
class Config
{
    public const SECTION = 'commercegears_ga4';
    public const MODE_GTM = 'gtm';
    public const MODE_GTAG = 'gtag';
    public const DEFAULT_GTM_URL = 'https://www.googletagmanager.com';
    public const MAX_CLICK_RULES = 20;

    private const ADS_ID_PATTERN = '/^AW-\d{6,15}$/';

    /** Events of this module and GA4's reserved/automatic events: not allowed as click event names. */
    public const RESERVED_EVENTS = [
        'view_item_list', 'select_item', 'view_item', 'add_to_cart', 'remove_from_cart', 'view_cart', 'begin_checkout',
        'add_shipping_info', 'add_payment_info', 'purchase', 'refund', 'add_to_wishlist', 'login', 'sign_up', 'search',
        'generate_lead', 'conversion', 'page_view', 'first_visit', 'session_start', 'user_engagement', 'first_open',
        'screen_view', 'app_remove', 'error', 'in_app_purchase', 'ad_click', 'ad_exposure', 'ad_impression', 'ad_query',
        'ad_reward', 'ad_activeview', 'adunit_exposure', 'notification_dismiss', 'notification_foreground',
        'notification_open', 'notification_receive', 'os_update', 'app_clear_data', 'app_exception', 'app_install',
        'app_update', 'app_upgrade', 'app_store_refund', 'dynamic_link_app_open', 'dynamic_link_app_update',
        'dynamic_link_first_open', 'firebase_campaign', 'user_data',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly EncryptorInterface $encryptor,
        private readonly ModuleManager $moduleManager
    ) {
    }

    public function getValue(string $path, int|string|null $store = null): string
    {
        return trim((string)$this->scopeConfig->getValue(self::SECTION . '/' . $path, ScopeInterface::SCOPE_STORE, $store));
    }

    public function getFlag(string $path, int|string|null $store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::SECTION . '/' . $path, ScopeInterface::SCOPE_STORE, $store);
    }

    public function isEnabled(int|string|null $store = null): bool
    {
        return $this->getFlag('general/enabled', $store);
    }

    public function getMode(int|string|null $store = null): string
    {
        return $this->getValue('general/mode', $store) === self::MODE_GTAG ? self::MODE_GTAG : self::MODE_GTM;
    }

    public function getGtmId(int|string|null $store = null): string
    {
        return $this->getValue('general/gtm_id', $store);
    }

    public function getGtmUrl(int|string|null $store = null): string
    {
        return rtrim($this->getValue('general/gtm_url', $store) ?: self::DEFAULT_GTM_URL, '/');
    }

    public function getMeasurementId(int|string|null $store = null): string
    {
        return $this->getValue('general/measurement_id', $store);
    }

    /**
     * Tags are configured enough to output anything.
     */
    public function isActive(int|string|null $store = null): bool
    {
        if (!$this->isEnabled($store)) {
            return false;
        }
        return $this->getMode($store) === self::MODE_GTM
            ? $this->getGtmId($store) !== ''
            : $this->getMeasurementId($store) !== '';
    }

    /**
     * Extra Google tag IDs (Google Ads AW-, Floodlight DC-, Google tag GT-, other GA4 G-) for gtag.js mode, in the
     * order entered, without the main measurement ID and without duplicates. Invalid entries are skipped.
     *
     * @return string[]
     */
    public function getAdditionalTagIds(int|string|null $store = null): array
    {
        $main = strtoupper($this->getMeasurementId($store));
        return array_values(array_filter(
            self::parseTagIds($this->getValue('general/additional_tag_ids', $store)),
            static fn (string $id): bool => self::isValidTagId($id) && $id !== $main
        ));
    }

    /**
     * Splits a comma, space or line separated list of tag IDs, upper-cased and de-duplicated.
     *
     * @return string[]
     */
    public static function parseTagIds(string $value): array
    {
        $ids = preg_split('/[\s,;]+/', strtoupper($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique($ids));
    }

    /**
     * G-XXXXXXX (GA4), AW-123456789 (Google Ads), DC-1234567 (Floodlight) or GT-XXXXXXX (Google tag).
     */
    public static function isValidTagId(string $id): bool
    {
        return (bool)preg_match('/^(?:G-[A-Z0-9]{4,15}|AW-\d{6,15}|DC-\d{5,15}|GT-[A-Z0-9]{4,15})$/', $id);
    }

    /**
     * Every Google tag ID gtag.js configures after the GA4 measurement ID: the Additional Google Tag IDs plus the
     * Google Ads conversion ID (when it is not in that list already), so the conversion has a configured destination.
     *
     * @return string[]
     */
    public function getGtagConfigIds(int|string|null $store = null): array
    {
        $ids = $this->getAdditionalTagIds($store);
        $adsId = $this->getAdsConversionId($store);
        if ($adsId !== '' && !in_array($adsId, $ids, true)) {
            $ids[] = $adsId;
        }
        return $ids;
    }

    /**
     * Google Ads conversion ID (AW-…): the setting, or else the first AW- ID of the Additional Google Tag IDs.
     */
    public function getAdsConversionId(int|string|null $store = null): string
    {
        $id = strtoupper($this->getValue('ads/conversion_id', $store));
        if ($id !== '') {
            return preg_match(self::ADS_ID_PATTERN, $id) ? $id : '';
        }
        foreach ($this->getAdditionalTagIds($store) as $tagId) {
            if (str_starts_with($tagId, 'AW-')) {
                return $tagId;
            }
        }
        return '';
    }

    /**
     * send_to of a Google Ads conversion ("AW-123/label") for 'purchase' or 'lead'; empty = no conversion event.
     */
    public function getAdsSendTo(string $type, int|string|null $store = null): string
    {
        if (!in_array($type, ['purchase', 'lead'], true)) {
            return '';
        }
        $label = $this->getValue('ads/' . $type . '_label', $store);
        $id = $this->getAdsConversionId($store);
        return $id !== '' && self::isValidConversionLabel($label) ? $id . '/' . $label : '';
    }

    /**
     * Google Ads conversion labels: letters, digits, "-" and "_" (for example AbC-D_efG-h12_34-567).
     */
    public static function isValidConversionLabel(string $label): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9_-]{6,40}$/', $label);
    }

    /**
     * Forms whose successful submission sends generate_lead ('contact', 'newsletter', 'custom').
     *
     * @return string[]
     */
    public function getLeadForms(int|string|null $store = null): array
    {
        return preg_split('/\s*,\s*/', $this->getValue('leads/forms', $store), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public function isLeadFormEnabled(string $form, int|string|null $store = null): bool
    {
        return in_array($form, $this->getLeadForms($store), true);
    }

    /**
     * Value sent with generate_lead (and the lead conversion); null = no value and currency.
     */
    public function getLeadValue(int|string|null $store = null): ?float
    {
        $value = $this->getValue('leads/value', $store);
        return $value !== '' && is_numeric($value) && (float)$value >= 0 ? round((float)$value, 2) : null;
    }

    /**
     * Click events: [['event' => 'demo_open', 'selector' => 'a[href*="demo.example.com"]'], ...]. Invalid lines are skipped.
     *
     * @return array<int, array{event: string, selector: string}>
     */
    public function getClickRules(int|string|null $store = null): array
    {
        $rules = [];
        foreach (self::parseClickRules($this->getValue('clicks/rules', $store)) as $rule) {
            if (self::clickRuleError($rule['event'], $rule['selector']) === null) {
                $rules[] = ['event' => $rule['event'], 'selector' => $rule['selector']];
            }
        }
        return array_slice($rules, 0, self::MAX_CLICK_RULES);
    }

    /**
     * One rule per line: "event_name: CSS selector" (the first ":" separates them). Empty lines and lines starting
     * with "#" are ignored.
     *
     * @return array<int, array{event: string, selector: string, line: string}>
     */
    public static function parseClickRules(string $value): array
    {
        $rules = [];
        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, ':');
            $rules[] = [
                'event' => $pos === false ? $line : trim(substr($line, 0, $pos)),
                'selector' => $pos === false ? '' : trim(substr($line, $pos + 1)),
                'line' => $line,
            ];
        }
        return $rules;
    }

    /**
     * Why a click rule cannot be used (null = valid). Event names follow GA4's rules and must not be an event this
     * module (or GA4 itself) sends, so a click can never be counted as a purchase or a lead.
     */
    public static function clickRuleError(string $event, string $selector): ?string
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', $event)) {
            return 'event';
        }
        if (in_array($event, self::RESERVED_EVENTS, true) || preg_match('/^(?:ga_|google_|gtag_|firebase_)/', $event)) {
            return 'reserved';
        }
        if ($selector === '' || strlen($selector) > 300 || preg_match('/[{};\x00-\x1f]|<\//', $selector)) {
            return 'selector';
        }
        return null;
    }

    public function isDebug(int|string|null $store = null): bool
    {
        return $this->getFlag('general/debug', $store);
    }

    /**
     * Category code to wait for (empty = load immediately). Only honoured with CommerceGears Cookie Consent enabled.
     */
    public function getLoadAfterConsent(int|string|null $store = null): string
    {
        $category = $this->getValue('general/load_after_consent', $store);
        return $category !== '' && $this->isCookieConsentActive($store) ? $category : '';
    }

    public function isCookieConsentActive(int|string|null $store = null): bool
    {
        return $this->moduleManager->isEnabled('CommerceGears_CookieConsent')
            && $this->scopeConfig->isSetFlag('commercegears_cookieconsent/general/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * 'none', 'denied' or 'granted'; always 'none' when CommerceGears Cookie Consent sets the defaults.
     */
    public function getConsentDefault(int|string|null $store = null): string
    {
        if ($this->isCookieConsentActive($store)
            && $this->scopeConfig->isSetFlag('commercegears_cookieconsent/consent_mode/enabled', ScopeInterface::SCOPE_STORE, $store)
        ) {
            return 'none';
        }
        $value = $this->getValue('general/consent_default', $store);
        return in_array($value, ['denied', 'granted'], true) ? $value : 'none';
    }

    public function useSku(int|string|null $store = null): bool
    {
        return $this->getValue('data/item_id', $store) !== 'id';
    }

    public function getBrandAttribute(int|string|null $store = null): string
    {
        return $this->getValue('data/brand_attribute', $store);
    }

    public function getDefaultBrand(int|string|null $store = null): string
    {
        return $this->getValue('data/default_brand', $store);
    }

    public function getCategoryPathMode(int|string|null $store = null): string
    {
        return $this->getValue('data/category_path', $store) === 'first' ? 'first' : 'deepest';
    }

    public function getAffiliation(int|string|null $store = null): string
    {
        $affiliation = $this->getValue('data/affiliation', $store);
        if ($affiliation === '') {
            try {
                $affiliation = (string)$this->storeManager->getStore($store)->getFrontendName();
            } catch (\Exception) {
                $affiliation = '';
            }
        }
        return $affiliation;
    }

    public function useBaseCurrency(int|string|null $store = null): bool
    {
        return $this->getValue('data/currency', $store) === 'base';
    }

    public function isPriceInclTax(int|string|null $store = null): bool
    {
        return $this->getFlag('data/price_incl_tax', $store);
    }

    public function isValueInclTax(int|string|null $store = null): bool
    {
        return $this->getFlag('data/value_incl_tax', $store);
    }

    public function isValueInclShipping(int|string|null $store = null): bool
    {
        return $this->getFlag('data/value_incl_shipping', $store);
    }

    public function getMaxListItems(int|string|null $store = null): int
    {
        $max = (int)$this->getValue('data/max_list_items', $store);
        return $max > 0 ? min($max, 200) : 50;
    }

    /**
     * No purchase event for orders whose purchase value is 0 (the order is still marked as tracked).
     */
    public function isSkipZeroValue(int|string|null $store = null): bool
    {
        return $this->getFlag('data/skip_zero_value', $store);
    }

    public function isUserDataEnabled(int|string|null $store = null): bool
    {
        return $this->getFlag('data/user_data', $store);
    }

    public function isRefundEnabled(int|string|null $store = null): bool
    {
        return $this->isEnabled($store) && $this->getFlag('measurement_protocol/refund_enabled', $store)
            && $this->getMeasurementId($store) !== '' && $this->getApiSecret($store) !== '';
    }

    public function getApiSecret(int|string|null $store = null): string
    {
        $value = $this->getValue('measurement_protocol/api_secret', $store);
        return $value === '' ? '' : trim($this->encryptor->decrypt($value));
    }
}
