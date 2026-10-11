# Changelog

## 1.2.3 (2026-10-11)
- GTM container export: an unknown store view is reported instead of silently using the default settings.
- The placeholder IDs GTM-XXXXXXX and G-XXXXXXXXXX are refused when entered.
- Default Brand and Affiliation accept plain text only (up to 100 characters), checked on the server.
- Requires Softaware Core 1.3.

## 1.2.2 (2026-10-10)
- Product listing, FAQ, user guide and screenshots.

## 1.2.1 (2026-10-07)
- GTM Container Export page uses the standard admin form and explains when no store view sends events.
- Every setting has a short comment with its default.

## 1.2.0 (2026-10-06)
- Requires Softaware Core instead of the retired Softaware Base; admin menu and ACL under "Softaware".

## 1.1.0 (2026-10-06)
- New add_to_wishlist event (Luma and Hyvä), included in the GTM container export.
- Refund value follows the purchase value settings; a queued refund can no longer be sent twice.
- Cart events use the cart currency; free gift lines are reported at their real price.
- The GTM noscript iframe is left out while Softaware Cookie Consent is active.

## 1.0.0 (2026-10-06)
- First release: Google Tag Manager or gtag.js with Google Consent Mode and optional loading after consent.
- Full GA4 ecommerce events from product lists to purchase, plus login, sign_up and search (Luma and Hyvä).
- Refunds through the Measurement Protocol, optional enhanced conversions, debug mode.
- GTM container export in the admin and on the command line.
