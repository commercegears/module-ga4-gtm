<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Typed access to the softaware_ga4 configuration section.
 */
class Config
{
    public const SECTION = 'softaware_ga4';
    public const MODE_GTM = 'gtm';
    public const MODE_GTAG = 'gtag';
    public const DEFAULT_GTM_URL = 'https://www.googletagmanager.com';

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

    public function isDebug(int|string|null $store = null): bool
    {
        return $this->getFlag('general/debug', $store);
    }

    /**
     * Category code to wait for (empty = load immediately). Only honoured with Softaware Cookie Consent enabled.
     */
    public function getLoadAfterConsent(int|string|null $store = null): string
    {
        $category = $this->getValue('general/load_after_consent', $store);
        return $category !== '' && $this->isCookieConsentActive($store) ? $category : '';
    }

    public function isCookieConsentActive(int|string|null $store = null): bool
    {
        return $this->moduleManager->isEnabled('Softaware_CookieConsent')
            && $this->scopeConfig->isSetFlag('softaware_cookieconsent/general/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * 'none', 'denied' or 'granted'; always 'none' when Softaware Cookie Consent sets the defaults.
     */
    public function getConsentDefault(int|string|null $store = null): string
    {
        if ($this->isCookieConsentActive($store)
            && $this->scopeConfig->isSetFlag('softaware_cookieconsent/consent_mode/enabled', ScopeInterface::SCOPE_STORE, $store)
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
