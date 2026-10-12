# Changelog

## 2.0.1 (2026-10-12)
- Improvement: user guide screenshots and links updated for the CommerceGears name

## 2.0.0 (2026-10-11)
- Improvement: renamed to CommerceGears (new Composer package, admin menu and settings paths)

## 1.2.4 (2026-10-11)
- Improvement: live demo links for the Luma and Hyvä storefront, with a guided tour of the events.

## 1.2.3 (2026-10-11)
- Fix: GTM container export reports an unknown store view instead of silently using the default settings.
- Improvement: the placeholder IDs GTM-XXXXXXX and G-XXXXXXXXXX are refused when entered.
- Improvement: Default Brand and Affiliation accept plain text only (up to 100 characters), checked when saved.
- Improvement: requires CommerceGears Core 1.3.

## 1.2.2 (2026-10-10)
- New: product listing, FAQ, user guide and screenshots.

## 1.2.1 (2026-10-07)
- Improvement: GTM Container Export uses the standard admin form and says when no store view sends events.
- Improvement: every setting has a short comment with its default.

## 1.2.0 (2026-10-06)
- Improvement: requires CommerceGears Core instead of CommerceGears Base; menu and permissions move under "CommerceGears".

## 1.1.0 (2026-10-06)
- New: add_to_wishlist event (Luma and Hyvä), included in the GTM container export.
- Fix: refund value follows the purchase value settings; a queued refund can no longer be sent twice.
- Fix: cart events use the cart currency; free gift lines are reported at their real price.
- Improvement: the GTM noscript iframe is left out while CommerceGears Cookie Consent is active.

## 1.0.0 (2026-10-06)
- New: first release with Google Tag Manager or gtag.js, Google Consent Mode and optional loading after consent.
- New: full GA4 ecommerce events from product lists to purchase, plus login, sign_up and search (Luma and Hyvä).
- New: refunds through the Measurement Protocol, optional enhanced conversions and debug mode.
- New: GTM container export in the admin and on the command line.
