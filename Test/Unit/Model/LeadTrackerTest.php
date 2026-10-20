<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Test\Unit\Model;

use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\EventQueue;
use CommerceGears\Ga4\Model\LeadTracker;
use CommerceGears\Ga4\Model\UserData;
use CommerceGears\Ga4\Model\UserDataNormaliser;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class LeadTrackerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $settings = [];
    private EventQueue&MockObject $queue;

    private function tracker(): LeadTracker
    {
        $config = $this->createStub(Config::class);
        $config->method('isActive')->willReturnCallback(fn () => $this->settings['active'] ?? true);
        $config->method('isLeadFormEnabled')->willReturnCallback(fn (string $form) => in_array($form, $this->settings['forms'] ?? ['contact'], true));
        $config->method('getLeadValue')->willReturnCallback(fn () => $this->settings['value'] ?? null);
        $config->method('useBaseCurrency')->willReturn(false);
        $config->method('getAdsSendTo')->willReturnCallback(fn (string $type) => $type === 'lead' ? ($this->settings['send_to'] ?? '') : '');
        $config->method('isUserDataEnabled')->willReturnCallback(fn () => $this->settings['user_data'] ?? false);
        $store = $this->getMockBuilder(Store::class)->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getCurrentCurrencyCode', 'getBaseCurrencyCode'])->getMock();
        $store->method('getId')->willReturn(1);
        $store->method('getCurrentCurrencyCode')->willReturn('GBP');
        $store->method('getBaseCurrencyCode')->willReturn('EUR');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->queue = $this->createMock(EventQueue::class);
        return new LeadTracker($config, $this->queue, new UserData($config, new UserDataNormaliser()), $storeManager, $this->createStub(LoggerInterface::class));
    }

    public function testContactLeadWithoutValueOrConversion(): void
    {
        $tracker = $this->tracker();
        $this->queue->expects(self::once())->method('add')
            ->with('generate_lead', ['form_id' => 'contact', 'form_name' => 'Contact Us'], [], []);
        self::assertTrue($tracker->track('contact', 'Contact Us', '', 'Jane@Example.com', '07700 900123', 'contact'));
    }

    public function testValueCurrencyConversionAndHashedUserData(): void
    {
        $this->settings = ['value' => 25.0, 'send_to' => 'AW-18506289930/AbCdEfGh12', 'user_data' => true];
        $tracker = $this->tracker();
        $this->queue->expects(self::once())->method('add')->with(
            'generate_lead',
            ['form_id' => 'contact', 'form_name' => 'Contact Us', 'currency' => 'GBP', 'value' => 25.0],
            ['user_data' => ['sha256_email_address' => hash('sha256', 'jane@example.com'), 'sha256_phone_number' => hash('sha256', '+447700900123')]],
            ['send_to' => 'AW-18506289930/AbCdEfGh12', 'value' => 25.0, 'currency' => 'GBP']
        );
        $tracker->track('contact', 'Contact Us', '', ' Jane@Example.com ', '+44 7700 900123', 'contact');
    }

    public function testDisabledFormOrModuleQueuesNothing(): void
    {
        $this->settings = ['forms' => ['contact']];
        $tracker = $this->tracker();
        $this->queue->expects(self::never())->method('add');
        self::assertFalse($tracker->track('newsletter', 'Newsletter', '', 'a@example.com', '', 'newsletter'));
        $this->settings = ['active' => false];
        self::assertFalse($tracker->track('contact', 'Contact Us', '', '', '', 'contact'));
    }

    public function testNoPersonalDataInParams(): void
    {
        $this->settings = ['forms' => ['custom']];
        $tracker = $this->tracker();
        $this->queue->expects(self::once())->method('add')
            ->with('generate_lead', ['form_id' => 'quote', 'form_name' => 'Quote request'], [], []);
        $tracker->track(' quote ', "Quote\nrequest", 'jane@example.com', '', '', 'custom');
        self::assertSame('', LeadTracker::clean('someone@example.com'));
        self::assertSame(100, mb_strlen(LeadTracker::clean(str_repeat('x', 150))));
    }

    public function testFormIdIsRequired(): void
    {
        $this->settings = ['forms' => ['custom']];
        $tracker = $this->tracker();
        $this->queue->expects(self::never())->method('add');
        self::assertFalse($tracker->track('', 'Quote request', '', '', '', 'custom'));
    }
}
