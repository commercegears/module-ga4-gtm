<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Test\Unit\Model\Config;

use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\Config\Backend\TagIds;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TagIdsTest extends TestCase
{
    public function testValidIds(): void
    {
        foreach (['AW-18506289930', 'G-AB12CD34EF', 'DC-1234567', 'GT-ABC1234'] as $id) {
            self::assertTrue(Config::isValidTagId($id), $id);
        }
        foreach (['AW-ABC', 'UA-12345-1', 'GTM-AB12CDE', 'G-', 'aw-123456789', 'AW-123', 'X-123456'] as $id) {
            self::assertFalse(Config::isValidTagId($id), $id);
        }
    }

    public function testNormaliseTidiesTheList(): void
    {
        self::assertSame('AW-18506289930, G-AB12CD34EF', TagIds::normalise(" aw-18506289930 ,\nG-ab12cd34ef ; AW-18506289930 "));
        self::assertSame('', TagIds::normalise('  '));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'GTM container' => ['GTM-AB12CDE'],
            'universal analytics' => ['UA-1234567-1'],
            'Ads label snippet' => ['AW-18506289930/AbCdEfGh'],
            'placeholder' => ['G-XXXXXXXXXX'],
            'Google Ads example' => ['AW-123456789'],
            'too many' => [implode(',', array_map(static fn (int $i): string => 'AW-10000000' . $i, range(0, 10)))],
        ];
    }

    /**
     * @dataProvider invalidProvider
     */
    #[DataProvider('invalidProvider')]
    public function testInvalidIsRefused(string $value): void
    {
        $this->expectException(LocalizedException::class);
        TagIds::normalise($value);
    }

    public function testConfigSkipsMainIdAndInvalidEntries(): void
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnMap([
            ['commercegears_ga4/general/measurement_id', 'store', null, 'G-MAIN12345'],
            ['commercegears_ga4/general/additional_tag_ids', 'store', null, 'AW-18506289930, g-main12345, junk, G-OTHER1234'],
        ]);
        $config = new Config(
            $scope,
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(EncryptorInterface::class),
            $this->createStub(ModuleManager::class)
        );
        self::assertSame(['AW-18506289930', 'G-OTHER1234'], $config->getAdditionalTagIds());
    }
}
