<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Test\Unit\Model\Event\Provider;

use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\Event\Provider\Purchase;
use CommerceGears\Ga4\Model\ItemBuilder;
use CommerceGears\Ga4\Model\ResourceModel\OrderTracking;
use CommerceGears\Ga4\Model\UserData;
use CommerceGears\Ga4\Model\UserDataNormaliser;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Order and address doubles are partial mocks used as stubs (PHPUnit 12 notice opt-out).
 */
#[AllowMockObjectsWithoutExpectations]
class PurchaseTest extends TestCase
{
    private Config&Stub $config;
    private OrderTracking&MockObject $tracking;
    private CheckoutSession&Stub $session;
    private ItemBuilder&Stub $itemBuilder;
    /** @var array<string, mixed> */
    private array $settings = [];

    protected function setUp(): void
    {
        $this->settings = ['incl_tax' => false, 'incl_shipping' => false, 'skip_zero' => false, 'user_data' => false];
        $this->config = $this->createStub(Config::class);
        $this->config->method('useBaseCurrency')->willReturn(false);
        $this->config->method('isValueInclTax')->willReturnCallback(fn () => $this->settings['incl_tax']);
        $this->config->method('isValueInclShipping')->willReturnCallback(fn () => $this->settings['incl_shipping']);
        $this->config->method('isSkipZeroValue')->willReturnCallback(fn () => $this->settings['skip_zero']);
        $this->config->method('isUserDataEnabled')->willReturnCallback(fn () => $this->settings['user_data']);
        $this->config->method('getAffiliation')->willReturn('Test Store');
        $this->tracking = $this->createMock(OrderTracking::class);
        $this->session = $this->createStub(CheckoutSession::class);
        $this->itemBuilder = $this->createStub(ItemBuilder::class);
        $this->itemBuilder->method('fromOrderItem')->willReturnCallback(static fn (OrderItem $item): array => [
            'item_id' => $item->getSku(),
            'item_name' => $item->getName(),
            'price' => (float)$item->getPrice(),
            'quantity' => (float)$item->getQtyOrdered(),
        ]);
    }

    private function provider(): Purchase
    {
        return new Purchase(
            $this->session,
            $this->itemBuilder,
            $this->config,
            $this->tracking,
            new UserData($this->config, new UserDataNormaliser())
        );
    }

    private function order(float $grandTotal, float $tax, float $shipping, float $shippingTax): Order&MockObject
    {
        $item = $this->getMockBuilder(OrderItem::class)->disableOriginalConstructor()
            ->onlyMethods(['getSku', 'getName', 'getPrice', 'getQtyOrdered', 'getProductId'])->getMock();
        $item->method('getSku')->willReturn('TEST-SKU');
        $item->method('getName')->willReturn('Test Product');
        $item->method('getPrice')->willReturn($grandTotal > 0 ? 40.0 : 0.0);
        $item->method('getQtyOrdered')->willReturn(2.0);
        $item->method('getProductId')->willReturn(7);
        $address = $this->getMockBuilder(Address::class)->disableOriginalConstructor()
            ->onlyMethods(['getTelephone', 'getCountryId', 'getFirstname', 'getLastname', 'getStreet', 'getCity', 'getRegion', 'getPostcode'])
            ->getMock();
        $address->method('getTelephone')->willReturn('07700 900123');
        $address->method('getCountryId')->willReturn('GB');
        $address->method('getFirstname')->willReturn(' Jane ');
        $address->method('getLastname')->willReturn("O'Brien");
        $address->method('getStreet')->willReturn(['1 Test Street']);
        $address->method('getCity')->willReturn('London');
        $address->method('getRegion')->willReturn('');
        $address->method('getPostcode')->willReturn('SW1A 1AA');
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getStoreId', 'getIncrementId', 'getGrandTotal', 'getTaxAmount', 'getShippingAmount',
                'getShippingInclTax', 'getOrderCurrencyCode', 'getCouponCode', 'getAllVisibleItems', 'getBillingAddress', 'getCustomerEmail'])
            ->getMock();
        $order->method('getId')->willReturn(501);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('000000501');
        $order->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('getTaxAmount')->willReturn($tax);
        $order->method('getShippingAmount')->willReturn($shipping);
        $order->method('getShippingInclTax')->willReturn($shipping + $shippingTax);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getCouponCode')->willReturn(null);
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $order->method('getBillingAddress')->willReturn($address);
        $order->method('getCustomerEmail')->willReturn(' Jane.Doe@Gmail.com ');
        $this->session->method('getLastRealOrder')->willReturn($order);
        return $order;
    }

    public function testPurchaseParamsFromTheOrder(): void
    {
        $this->order(102.0, 17.0, 10.0, 2.0); // 80 goods + 15 tax, 10 shipping + 2 tax
        $this->tracking->expects(self::once())->method('markPurchaseTracked')->with(501)->willReturn(true);
        $events = $this->provider()->getEvents();
        self::assertCount(1, $events);
        $params = $events[0]['params'];
        self::assertSame('purchase', $events[0]['event']);
        self::assertSame('000000501', $params['transaction_id']);
        self::assertSame('EUR', $params['currency']);
        self::assertSame(75.0, $params['value']); // 102 - 10 shipping - 17 tax
        self::assertSame(17.0, $params['tax']);
        self::assertSame(10.0, $params['shipping']);
        self::assertSame([['item_id' => 'TEST-SKU', 'item_name' => 'Test Product', 'price' => 40.0, 'quantity' => 2.0]], $params['items']);
        self::assertSame([], $events[0]['extra']);
        self::assertSame('purchase_000000501', $events[0]['uid']);
    }

    public function testAlreadyTrackedOrderSendsNothing(): void
    {
        $this->order(102.0, 17.0, 10.0, 2.0);
        $this->tracking->method('markPurchaseTracked')->willReturn(false);
        self::assertSame([], $this->provider()->getEvents());
    }

    public function testZeroValueOrderFiresWhenSettingIsOff(): void
    {
        $this->order(0.0, 0.0, 0.0, 0.0);
        $this->tracking->method('markPurchaseTracked')->willReturn(true);
        $events = $this->provider()->getEvents();
        self::assertCount(1, $events);
        self::assertSame(0.0, $events[0]['params']['value']);
    }

    public function testZeroValueOrderIsSkippedButStillFlagged(): void
    {
        $this->settings['skip_zero'] = true;
        $this->order(0.0, 0.0, 0.0, 0.0);
        $this->tracking->expects(self::once())->method('markPurchaseTracked')->with(501)->willReturn(true);
        self::assertSame([], $this->provider()->getEvents());
    }

    public function testSkipZeroKeepsPaidOrders(): void
    {
        $this->settings['skip_zero'] = true;
        $this->order(102.0, 17.0, 10.0, 2.0);
        $this->tracking->method('markPurchaseTracked')->willReturn(true);
        self::assertCount(1, $this->provider()->getEvents());
    }

    public function testUserDataIsHashedFromTheOrder(): void
    {
        $this->settings['user_data'] = true;
        $this->order(102.0, 17.0, 10.0, 2.0);
        $this->tracking->method('markPurchaseTracked')->willReturn(true);
        $userData = $this->provider()->getEvents()[0]['extra']['user_data'];
        self::assertSame(hash('sha256', 'janedoe@gmail.com'), $userData['sha256_email_address']);
        self::assertSame(hash('sha256', '+447700900123'), $userData['sha256_phone_number']);
        self::assertSame(hash('sha256', 'jane'), $userData['address']['sha256_first_name']);
        self::assertSame(hash('sha256', 'obrien'), $userData['address']['sha256_last_name']);
        self::assertSame(hash('sha256', '1 test street'), $userData['address']['sha256_street']);
        self::assertSame('london', $userData['address']['city']);
        self::assertSame('SW1A 1AA', $userData['address']['postal_code']);
        self::assertSame('GB', $userData['address']['country']);
        self::assertArrayNotHasKey('region', $userData['address']);
    }
}
