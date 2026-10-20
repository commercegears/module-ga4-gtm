<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Test\Unit\Model\Config;

use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\Config\Backend\AdsConversionId;
use CommerceGears\Ga4\Model\Config\Backend\ClickRules;
use CommerceGears\Ga4\Model\Config\Backend\ConversionLabel;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdsSettingsTest extends TestCase
{
    /**
     * @param array<string, string> $values paths below commercegears_ga4/
     */
    private function config(array $values): Config
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(
            static fn (string $path) => $values[substr($path, strlen('commercegears_ga4/'))] ?? null
        );
        return new Config(
            $scope,
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(EncryptorInterface::class),
            $this->createStub(ModuleManager::class)
        );
    }

    public function testConversionIdFallsBackToTheFirstAwTagId(): void
    {
        $config = $this->config(['general/additional_tag_ids' => 'G-OTHER123, AW-18506289930, AW-22222222']);
        self::assertSame('AW-18506289930', $config->getAdsConversionId());
        self::assertSame('', $config->getAdsSendTo('purchase'), 'no label, no conversion');

        $config = $this->config([
            'general/additional_tag_ids' => 'AW-18506289930',
            'ads/conversion_id' => 'AW-33333333',
            'ads/purchase_label' => 'AbC-D_efG-h12',
        ]);
        self::assertSame('AW-33333333', $config->getAdsConversionId());
        self::assertSame('AW-33333333/AbC-D_efG-h12', $config->getAdsSendTo('purchase'));
        self::assertSame('', $config->getAdsSendTo('lead'));
        self::assertSame('', $config->getAdsSendTo('other'));
        self::assertSame(['AW-18506289930', 'AW-33333333'], $config->getGtagConfigIds(), 'the conversion ID is configured too');
    }

    public function testNoConversionWithoutAnId(): void
    {
        $config = $this->config(['ads/purchase_label' => 'AbC-D_efG-h12', 'general/measurement_id' => 'G-AB12CD34EF']);
        self::assertSame('', $config->getAdsConversionId());
        self::assertSame('', $config->getAdsSendTo('purchase'));
        self::assertSame([], $config->getGtagConfigIds());
    }

    public function testGtagConfigIdsHaveNoDuplicates(): void
    {
        $config = $this->config(['general/additional_tag_ids' => 'AW-18506289930', 'ads/conversion_id' => 'AW-18506289930']);
        self::assertSame(['AW-18506289930'], $config->getGtagConfigIds());
    }

    public function testAdsIdNormalise(): void
    {
        self::assertSame('AW-18506289930', AdsConversionId::normalise(' aw-18506289930 '));
        self::assertSame('AW-18506289930', AdsConversionId::normalise('AW-18506289930/AbCdEfGh12'));
        self::assertSame('', AdsConversionId::normalise(''));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAdsIdProvider(): array
    {
        return [
            'GA4 id' => ['G-AB12CD34EF'],
            'letters' => ['AW-ABCDEFG'],
            'too short' => ['AW-123'],
            'example' => ['AW-123456789'],
        ];
    }

    #[DataProvider('invalidAdsIdProvider')]
    public function testInvalidAdsIdIsRefused(string $value): void
    {
        $this->expectException(LocalizedException::class);
        AdsConversionId::normalise($value);
    }

    public function testLabelNormalise(): void
    {
        self::assertSame('AbC-D_efG-h12', ConversionLabel::normalise(' AbC-D_efG-h12 '));
        self::assertSame('AbC-D_efG-h12', ConversionLabel::normalise('AW-18506289930/AbC-D_efG-h12'));
        self::assertSame('', ConversionLabel::normalise(''));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLabelProvider(): array
    {
        return [
            'space' => ['AbC D_efG'],
            'too short' => ['abc'],
            'quote' => ['AbC"efGh12'],
            'script' => ['<script>'],
            'example' => ['AbC-D_efG-h12_34-567'],
        ];
    }

    #[DataProvider('invalidLabelProvider')]
    public function testInvalidLabelIsRefused(string $value): void
    {
        $this->expectException(LocalizedException::class);
        ConversionLabel::normalise($value);
    }

    public function testClickRules(): void
    {
        $value = "# comment\n demo_open :  a[href*=\"demo.commercegears.com\"] \n\ncta_click: .hero a.button:not(.secondary)\n";
        self::assertSame(
            "demo_open: a[href*=\"demo.commercegears.com\"]\ncta_click: .hero a.button:not(.secondary)",
            ClickRules::normalise($value)
        );
        $config = $this->config(['clicks/rules' => ClickRules::normalise($value) . "\npurchase: a.buy"]);
        self::assertSame([
            ['event' => 'demo_open', 'selector' => 'a[href*="demo.commercegears.com"]'],
            ['event' => 'cta_click', 'selector' => '.hero a.button:not(.secondary)'],
        ], $config->getClickRules(), 'invalid lines saved earlier are skipped');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidClickProvider(): array
    {
        return [
            'no selector' => ['demo_open'],
            'empty selector' => ['demo_open:'],
            'upper case' => ['Demo_Open: a'],
            'module event' => ['purchase: a.buy'],
            'lead' => ['generate_lead: form button'],
            'reserved prefix' => ['google_click: a'],
            'GA4 automatic' => ['page_view: a'],
            'css block' => ['demo_open: a { color: red }'],
            'script' => ['demo_open: a</script><script>alert(1)'],
            'too many' => [implode("\n", array_map(static fn (int $i): string => 'e' . $i . ': a', range(1, 21)))],
        ];
    }

    #[DataProvider('invalidClickProvider')]
    public function testInvalidClickRuleIsRefused(string $value): void
    {
        $this->expectException(LocalizedException::class);
        ClickRules::normalise($value);
    }

    public function testLeadSettings(): void
    {
        $config = $this->config(['leads/forms' => 'contact,custom', 'leads/value' => '12.345']);
        self::assertTrue($config->isLeadFormEnabled('contact'));
        self::assertFalse($config->isLeadFormEnabled('newsletter'));
        self::assertSame(12.35, $config->getLeadValue());
        self::assertNull($this->config(['leads/value' => ''])->getLeadValue());
        self::assertNull($this->config(['leads/value' => '-1'])->getLeadValue());
        self::assertSame([], $this->config(['leads/forms' => ''])->getLeadForms());
    }
}
