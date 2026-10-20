<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds a Google Tag Manager container export (format version 2) with everything needed for the
 * dataLayer events of this module: a Google tag, GA4 event tags, custom event triggers and variables.
 * Import it in GTM under Admin > Import Container (choose "Merge" and "Rename conflicting tags").
 */
class GtmContainerExport
{
    public const ECOMMERCE_EVENTS = [
        'view_item_list', 'select_item', 'view_item', 'add_to_cart', 'remove_from_cart', 'view_cart',
        'begin_checkout', 'add_shipping_info', 'add_payment_info', 'purchase', 'add_to_wishlist',
    ];
    public const ACCOUNT_EVENTS = ['login', 'sign_up'];

    /** GTM built-in trigger "Initialization - All Pages". */
    private const TRIGGER_INITIALIZATION = '2147479573';
    /** GTM built-in trigger "All Pages". */
    private const TRIGGER_ALL_PAGES = '2147479553';

    private int $nextId = 1;

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int|string|null $store = null): array
    {
        $this->nextId = 1;
        $storeId = $this->resolveStoreId($store);
        $measurementId = $this->config->getMeasurementId($storeId) ?: 'G-XXXXXXXXXX';
        $publicId = $this->config->getGtmId($storeId) ?: 'GTM-XXXXXXX';

        $variables = [
            'measurement_id' => $this->constant('Const - GA4 Measurement ID', $measurementId),
            'method' => $this->dataLayerVariable('DLV - method', 'method'),
            'search_term' => $this->dataLayerVariable('DLV - search_term', 'search_term'),
            'user_data' => $this->dataLayerVariable('DLV - user_data', 'user_data'),
            'consent_analytics' => $this->dataLayerVariable('DLV - sw_consent.analytics', 'sw_consent.analytics'),
            'consent_marketing' => $this->dataLayerVariable('DLV - sw_consent.marketing', 'sw_consent.marketing'),
            'form_id' => $this->dataLayerVariable('DLV - form_id', 'form_id'),
            'form_name' => $this->dataLayerVariable('DLV - form_name', 'form_name'),
            'lead_source' => $this->dataLayerVariable('DLV - lead_source', 'lead_source'),
            'value' => $this->dataLayerVariable('DLV - value', 'value'),
            'currency' => $this->dataLayerVariable('DLV - currency', 'currency'),
        ];
        $ga4Id = '{{Const - GA4 Measurement ID}}';

        $triggers = [
            'ecommerce' => $this->customEventTrigger('CE - GA4 ecommerce events', self::ECOMMERCE_EVENTS),
            'account' => $this->customEventTrigger('CE - GA4 login and sign_up', self::ACCOUNT_EVENTS),
            'search' => $this->customEventTrigger('CE - GA4 search', ['search']),
            'consent' => $this->customEventTrigger('CE - commercegears_consent_update', ['commercegears_consent_update']),
            'lead' => $this->customEventTrigger('CE - GA4 generate_lead', ['generate_lead']),
        ];

        $tags = [
            $this->tag('GA4 - Google tag', 'googtag', [
                $this->param('TEMPLATE', 'tagId', $ga4Id),
            ], [self::TRIGGER_INITIALIZATION]),
            $this->tag('GA4 - Ecommerce events', 'gaawe', [
                $this->param('BOOLEAN', 'sendEcommerceData', 'true'),
                $this->param('TEMPLATE', 'getEcommerceDataFrom', 'dataLayer'),
                $this->param('BOOLEAN', 'enhancedUserId', 'false'),
                $this->param('TEMPLATE', 'eventName', '{{Event}}'),
                $this->param('TEMPLATE', 'measurementIdOverride', $ga4Id),
            ], [$triggers['ecommerce']['triggerId']]),
            $this->tag('GA4 - login and sign_up', 'gaawe', [
                $this->param('BOOLEAN', 'sendEcommerceData', 'false'),
                $this->param('BOOLEAN', 'enhancedUserId', 'false'),
                $this->param('TEMPLATE', 'eventName', '{{Event}}'),
                $this->param('TEMPLATE', 'measurementIdOverride', $ga4Id),
                $this->eventParameters(['method' => '{{DLV - method}}']),
            ], [$triggers['account']['triggerId']]),
            $this->tag('GA4 - search', 'gaawe', [
                $this->param('BOOLEAN', 'sendEcommerceData', 'false'),
                $this->param('BOOLEAN', 'enhancedUserId', 'false'),
                $this->param('TEMPLATE', 'eventName', 'search'),
                $this->param('TEMPLATE', 'measurementIdOverride', $ga4Id),
                $this->eventParameters(['search_term' => '{{DLV - search_term}}']),
            ], [$triggers['search']['triggerId']]),
            $this->tag('GA4 - generate_lead', 'gaawe', [
                $this->param('BOOLEAN', 'sendEcommerceData', 'false'),
                $this->param('BOOLEAN', 'enhancedUserId', 'false'),
                $this->param('TEMPLATE', 'eventName', 'generate_lead'),
                $this->param('TEMPLATE', 'measurementIdOverride', $ga4Id),
                $this->eventParameters([
                    'form_id' => '{{DLV - form_id}}',
                    'form_name' => '{{DLV - form_name}}',
                    'lead_source' => '{{DLV - lead_source}}',
                    'value' => '{{DLV - value}}',
                    'currency' => '{{DLV - currency}}',
                ]),
            ], [$triggers['lead']['triggerId']]),
        ];

        // Click events configured in Click Events > Events.
        $clickEvents = array_values(array_unique(array_column($this->config->getClickRules($storeId), 'event')));
        if ($clickEvents) {
            $variables['link_url'] = $this->dataLayerVariable('DLV - link_url', 'link_url');
            $variables['link_domain'] = $this->dataLayerVariable('DLV - link_domain', 'link_domain');
            $triggers['clicks'] = $this->customEventTrigger('CE - GA4 click events', $clickEvents);
            $tags[] = $this->tag('GA4 - click events', 'gaawe', [
                $this->param('BOOLEAN', 'sendEcommerceData', 'false'),
                $this->param('BOOLEAN', 'enhancedUserId', 'false'),
                $this->param('TEMPLATE', 'eventName', '{{Event}}'),
                $this->param('TEMPLATE', 'measurementIdOverride', $ga4Id),
                $this->eventParameters(['link_url' => '{{DLV - link_url}}', 'link_domain' => '{{DLV - link_domain}}']),
            ], [$triggers['clicks']['triggerId']]);
        }

        // Google Ads conversions (Google Ads Conversions settings): conversion linker plus one conversion tag per label,
        // reading the ads_conversion object that ga4.js adds to the purchase / generate_lead entry.
        $adsTags = [];
        foreach (['purchase' => 'purchase', 'lead' => 'generate_lead'] as $type => $event) {
            $sendTo = $this->config->getAdsSendTo($type, $storeId);
            if ($sendTo !== '') {
                $adsTags[$type] = [$event, ...explode('/', substr($sendTo, 3), 2)];
            }
        }
        if ($adsTags) {
            $variables['ads_value'] = $this->dataLayerVariable('DLV - ads_conversion.value', 'ads_conversion.value');
            $variables['ads_currency'] = $this->dataLayerVariable('DLV - ads_conversion.currency', 'ads_conversion.currency');
            $variables['ads_transaction_id'] = $this->dataLayerVariable('DLV - ads_conversion.transaction_id', 'ads_conversion.transaction_id');
            $tags[] = $this->tag('Google Ads - Conversion Linker', 'gclidw', [
                $this->param('BOOLEAN', 'enableCrossDomain', 'false'),
            ], [self::TRIGGER_ALL_PAGES]);
            foreach ($adsTags as $type => [$event, $conversionId, $label]) {
                $key = $event === 'generate_lead' ? 'lead' : 'ads_' . $event;
                if (!isset($triggers[$key])) {
                    $triggers[$key] = $this->customEventTrigger('CE - ' . $event, [$event]);
                }
                $tags[] = $this->tag('Google Ads - ' . $type . ' conversion', 'awct', [
                    $this->param('TEMPLATE', 'conversionId', $conversionId),
                    $this->param('TEMPLATE', 'conversionLabel', $label),
                    $this->param('TEMPLATE', 'conversionValue', '{{DLV - ads_conversion.value}}'),
                    $this->param('TEMPLATE', 'currencyCode', '{{DLV - ads_conversion.currency}}'),
                    $this->param('TEMPLATE', 'orderId', '{{DLV - ads_conversion.transaction_id}}'),
                ], [$triggers[$key]['triggerId']]);
            }
        }

        return [
            'exportFormatVersion' => 2,
            'exportTime' => gmdate('Y-m-d H:i:s'),
            'containerVersion' => [
                'path' => 'accounts/0/containers/0/versions/0',
                'accountId' => '0',
                'containerId' => '0',
                'containerVersionId' => '0',
                'container' => [
                    'path' => 'accounts/0/containers/0',
                    'accountId' => '0',
                    'containerId' => '0',
                    'name' => 'CommerceGears GA4 ecommerce',
                    'publicId' => $publicId,
                    'usageContext' => ['WEB'],
                    'fingerprint' => '0',
                    'tagManagerUrl' => 'https://tagmanager.google.com/#/container/accounts/0/containers/0/workspaces?apiLink=container',
                ],
                'tag' => $tags,
                'trigger' => array_values($triggers),
                'variable' => array_values($variables),
                'builtInVariable' => [
                    ['accountId' => '0', 'containerId' => '0', 'type' => 'EVENT', 'name' => 'Event'],
                    ['accountId' => '0', 'containerId' => '0', 'type' => 'PAGE_URL', 'name' => 'Page URL'],
                ],
                'fingerprint' => '0',
                'tagManagerUrl' => 'https://tagmanager.google.com/#/versions/accounts/0/containers/0/versions/0?apiLink=version',
            ],
        ];
    }

    public function toJson(int|string|null $store = null): string
    {
        return (string)json_encode($this->build($store), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function resolveStoreId(int|string|null $store): int
    {
        if ($store === null || $store === '' || $store === '0' || $store === 0) {
            return 0;
        }
        try {
            return (int)$this->storeManager->getStore($store)->getId();
        } catch (\Exception) {
            throw new LocalizedException(__('Store view "%1" does not exist.', (string)$store));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $parameters
     * @param string[] $triggerIds
     * @return array<string, mixed>
     */
    private function tag(string $name, string $type, array $parameters, array $triggerIds): array
    {
        return [
            'accountId' => '0',
            'containerId' => '0',
            'tagId' => (string)$this->nextId++,
            'name' => $name,
            'type' => $type,
            'parameter' => $parameters,
            'fingerprint' => '0',
            'firingTriggerId' => $triggerIds,
            'tagFiringOption' => 'ONCE_PER_EVENT',
            'monitoringMetadata' => ['type' => 'MAP'],
            'consentSettings' => ['consentStatus' => 'NOT_SET'],
        ];
    }

    /**
     * @param string[] $events
     * @return array<string, mixed>
     */
    private function customEventTrigger(string $name, array $events): array
    {
        return [
            'accountId' => '0',
            'containerId' => '0',
            'triggerId' => (string)$this->nextId++,
            'name' => $name,
            'type' => 'CUSTOM_EVENT',
            'customEventFilter' => [[
                'type' => 'MATCH_REGEX',
                'parameter' => [
                    $this->param('TEMPLATE', 'arg0', '{{_event}}'),
                    $this->param('TEMPLATE', 'arg1', '^(' . implode('|', $events) . ')$'),
                ],
            ]],
            'fingerprint' => '0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataLayerVariable(string $name, string $key): array
    {
        return [
            'accountId' => '0',
            'containerId' => '0',
            'variableId' => (string)$this->nextId++,
            'name' => $name,
            'type' => 'v',
            'parameter' => [
                $this->param('INTEGER', 'dataLayerVersion', '2'),
                $this->param('BOOLEAN', 'setDefaultValue', 'false'),
                $this->param('TEMPLATE', 'name', $key),
            ],
            'fingerprint' => '0',
            'formatValue' => new \stdClass(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function constant(string $name, string $value): array
    {
        return [
            'accountId' => '0',
            'containerId' => '0',
            'variableId' => (string)$this->nextId++,
            'name' => $name,
            'type' => 'c',
            'parameter' => [$this->param('TEMPLATE', 'value', $value)],
            'fingerprint' => '0',
            'formatValue' => new \stdClass(),
        ];
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function eventParameters(array $params): array
    {
        $list = [];
        foreach ($params as $key => $value) {
            $list[] = [
                'type' => 'MAP',
                'map' => [
                    $this->param('TEMPLATE', 'parameter', $key),
                    $this->param('TEMPLATE', 'parameterValue', $value),
                ],
            ];
        }
        return ['type' => 'LIST', 'key' => 'eventSettingsTable', 'list' => $list];
    }

    /**
     * @return array{type: string, key: string, value: string}
     */
    private function param(string $type, string $key, string $value): array
    {
        return ['type' => $type, 'key' => $key, 'value' => $value];
    }
}
