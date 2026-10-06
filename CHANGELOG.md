# Changelog

## 1.2.0 (2026-10-06)

### Changed

- Requires `softaware/module-core` instead of `softaware/module-base`. The admin menu and ACL now sit under `Softaware_Core::core` ("Softaware"); roles that had access keep it (migrated by module-core). After updating all SoftAware modules, `softaware/module-base` can be removed.

## 1.1.0 (2026-10-06)

### Added
- `add_to_wishlist` event (Luma and Hyvä), delivered through the customer-data section; included in the GTM
  container export.

### Fixed
- Refund value now follows *Purchase Value Includes Tax / Shipping*, like the purchase event. Before, refunds always
  sent the credit memo grand total, so with tax or shipping excluded a full refund made revenue negative.
- A queued refund could be sent twice when cron and `softaware:ga4:refunds --send` ran at the same time; it is now
  claimed (optimistic lock on the attempt counter) before it is sent.
- Cart events (add_to_cart, remove_from_cart, view_cart, begin_checkout) use the cart's currency instead of the
  session's display currency.
- Cart lines with a custom price of 0 (free gifts) were reported at the catalogue price in add_to_cart.

### Security / privacy
- The GTM `<noscript>` iframe is no longer printed while Softaware Cookie Consent is active: without JavaScript a
  visitor cannot consent, so GTM must not load.

## 1.0.0

- Google Tag Manager (head + noscript, optional server-side tagging domain) or gtag.js with a GA4 measurement ID.
- Google Consent Mode: works with Softaware Cookie Consent automatically; own defaults when no consent module is used;
  optional "load only after consent".
- GA4 ecommerce events with full item data: view_item_list (category, search, related, upsell, cross-sell), select_item,
  view_item, add_to_cart, remove_from_cart, view_cart, begin_checkout, add_shipping_info, add_payment_info,
  purchase (once per order), plus login, sign_up and search.
- Cart events for every kind of cart change (AJAX and page reloads, Luma and Hyvä) through the customer-data section
  `softaware-ga4`; de-duplicated in the browser.
- Refunds from credit memos through the Measurement Protocol (cron, encrypted API secret).
- Enhanced conversions: optional SHA-256 hashed user_data.
- GTM container export (admin page and CLI).
- Debug mode with console logging and GA4 DebugView support.
