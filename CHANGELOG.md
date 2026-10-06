# Changelog

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
