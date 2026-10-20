<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Server-side queue for events that happen in POST/AJAX requests (add_to_cart, remove_from_cart, login, sign_up,
 * generate_lead).
 * Stored in the session and delivered to the browser through the "commercegears-ga4" customer-data section,
 * so it works with full-page cache, Luma AJAX carts and Hyvä. Events stay in the section for a while
 * (a section response can be lost when the page navigates away mid-request, e.g. Luma's cart update);
 * each has a unique id and the browser remembers delivered ids, so an event is pushed exactly once.
 */
class EventQueue
{
    private const SESSION_KEY = 'commercegears_ga4_events';
    private const MAX_EVENTS = 20;
    private const LIFETIME = 1800;

    /** Tells ga4.js on the next page that events are waiting (Luma does not always reload the section). */
    public const PENDING_COOKIE = 'sw_ga4_pending';

    /** @var array<string, bool> event names queued during this request */
    private array $queuedNow = [];

    public function __construct(
        private readonly CustomerSession $session,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $extra top-level dataLayer keys (for example user_data)
     * @param array<string, mixed> $conversion Google Ads conversion fired right after the event, under the same
     *                                         once-only id (send_to, value, currency, transaction_id)
     */
    public function add(string $event, array $params, array $extra = [], array $conversion = []): void
    {
        $events = $this->getPending();
        $entry = [
            'uid' => bin2hex(random_bytes(8)),
            'event' => $event,
            'params' => $params,
            'extra' => $extra,
            'ts' => time(),
        ];
        if ($conversion) {
            $entry['conversion'] = $conversion;
        }
        $events[] = $entry;
        $this->session->setData(self::SESSION_KEY, array_slice($events, -self::MAX_EVENTS));
        $this->queuedNow[$event] = true;
        try {
            $this->cookieManager->setPublicCookie(
                self::PENDING_COOKIE,
                '1',
                $this->cookieMetadataFactory->createPublicCookieMetadata()
                    ->setDuration(self::LIFETIME)
                    ->setPath('/')
                    ->setSameSite('Lax')
            );
        } catch (\Throwable $e) {
            $this->logger->debug('CommerceGears_Ga4: pending cookie not set: ' . $e->getMessage());
        }
    }

    public function wasQueuedInThisRequest(string $event): bool
    {
        return isset($this->queuedNow[$event]);
    }

    /**
     * Events of the last 30 minutes (oldest first). They are not removed: the browser de-duplicates by uid.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPending(): array
    {
        $events = $this->session->getData(self::SESSION_KEY) ?: [];
        $limit = time() - self::LIFETIME;
        return array_values(array_filter(
            is_array($events) ? $events : [],
            static fn ($e): bool => is_array($e) && ($e['ts'] ?? 0) > $limit
        ));
    }
}
