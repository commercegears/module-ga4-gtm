<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Block;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Softaware\Ga4\Model\Config;

/**
 * GTM / gtag.js snippet in <head> (template head.phtml) and GTM <noscript> after <body> (noscript.phtml).
 */
class Tag extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isActive(): bool
    {
        return $this->config->isActive();
    }

    public function getMode(): string
    {
        return $this->config->getMode();
    }

    public function getGtmId(): string
    {
        return $this->config->getGtmId();
    }

    public function getGtmUrl(): string
    {
        return $this->config->getGtmUrl();
    }

    public function getMeasurementId(): string
    {
        return $this->config->getMeasurementId();
    }

    /**
     * The GTM <noscript> iframe loads Google Tag Manager without JavaScript, so a visitor can never give or refuse
     * consent: it is left out while Softaware Cookie Consent is active.
     */
    public function showNoscript(): bool
    {
        return $this->isActive() && $this->getMode() === Config::MODE_GTM && !$this->config->isCookieConsentActive();
    }

    /**
     * The inline JavaScript for <head>.
     */
    public function getHeadScript(): string
    {
        $js = "window.dataLayer = window.dataLayer || [];\n"
            . "window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };\n";
        $consent = $this->config->getConsentDefault();
        if ($consent !== 'none') {
            $state = [];
            foreach (['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage', 'functionality_storage', 'personalization_storage'] as $type) {
                $state[$type] = $consent;
            }
            $state['security_storage'] = 'granted';
            if ($consent === 'denied') {
                $state['wait_for_update'] = 500;
            }
            $js .= 'gtag("consent", "default", ' . $this->json->serialize($state) . ");\n";
        }
        if ($this->getMode() === Config::MODE_GTM) {
            $load = '(function (w, d, s, l, i, u) {'
                . 'w[l] = w[l] || []; w[l].push({"gtm.start": new Date().getTime(), event: "gtm.js"});'
                . 'var f = d.getElementsByTagName(s)[0], j = d.createElement(s);'
                . 'j.async = true; j.src = u + "/gtm.js?id=" + i; f.parentNode.insertBefore(j, f);'
                . '})(window, document, "script", "dataLayer", ' . $this->json->serialize($this->getGtmId())
                . ', ' . $this->json->serialize($this->getGtmUrl()) . ');';
        } else {
            $id = $this->json->serialize($this->getMeasurementId());
            $settings = $this->config->isDebug() ? '{debug_mode: true}' : '{}';
            $load = 'gtag("js", new Date()); gtag("config", ' . $id . ', ' . $settings . ');'
                . 'var j = document.createElement("script"); j.async = true;'
                . 'j.src = "https://www.googletagmanager.com/gtag/js?id=" + ' . $id . ';'
                . 'document.head.appendChild(j);';
        }
        $category = $this->config->getLoadAfterConsent();
        if ($category !== '') {
            $js .= 'window.SwConsent && window.SwConsent.whenGranted(' . $this->json->serialize($category)
                . ', function () { ' . $load . " });\n";
        } else {
            $js .= $load . "\n";
        }
        return $js;
    }

    public function getNoscriptUrl(): string
    {
        return $this->getGtmUrl() . '/ns.html?id=' . rawurlencode($this->getGtmId());
    }

    protected function _toHtml(): string
    {
        return $this->isActive() ? parent::_toHtml() : '';
    }
}
