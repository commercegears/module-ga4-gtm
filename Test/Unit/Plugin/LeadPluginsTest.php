<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Test\Unit\Plugin;

use CommerceGears\Ga4\Model\LeadTracker;
use CommerceGears\Ga4\Plugin\ContactLeadPlugin;
use CommerceGears\Ga4\Plugin\NewsletterLeadPlugin;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class LeadPluginsTest extends TestCase
{
    public function testNewsletterReadsTheMagicSubscriberFields(): void
    {
        $tracker = $this->createMock(LeadTracker::class);
        $tracker->expects(self::once())->method('track')
            ->with('newsletter', 'Newsletter', '', 'jane@example.com', '', 'newsletter');
        $plugin = new NewsletterLeadPlugin($tracker);
        $subscriber = new DataObject(['subscriber_status' => '1', 'subscriber_email' => 'jane@example.com']);
        self::assertSame($subscriber, $plugin->afterSubscribe(new \stdClass(), $subscriber));
    }

    public function testNewsletterIgnoresUnsubscribedResults(): void
    {
        $tracker = $this->createMock(LeadTracker::class);
        $tracker->expects(self::never())->method('track');
        $plugin = new NewsletterLeadPlugin($tracker);
        $plugin->afterSubscribeCustomer(new \stdClass(), new DataObject(['subscriber_status' => 3]));
        $plugin->afterSubscribe(new \stdClass(), null);
    }

    public function testContactPassesEmailAndPhoneForHashingOnly(): void
    {
        $tracker = $this->createMock(LeadTracker::class);
        $tracker->expects(self::once())->method('track')
            ->with('contact', 'Contact Us', '', 'jane@example.com', '+44 7700 900123', 'contact');
        $plugin = new ContactLeadPlugin($tracker);
        $plugin->afterSend(new \stdClass(), null, 'jane@example.com', ['data' => new DataObject(['telephone' => '+44 7700 900123'])]);
    }
}
