# Softaware GA4 & Google Tag Manager for Magento 2

`softaware/module-ga4-gtm` (`Softaware_Ga4`)

GA4 ecommerce tracking for Luma and Hyvä: Google Tag Manager or gtag.js, a complete GA4 dataLayer, Google Consent Mode,
server-side refunds and a GTM container you can import in one step.

## Features

- **Tags**: GTM container snippet in `<head>` plus `<noscript>` iframe after `<body>`, or gtag.js with a GA4
  measurement ID. Custom GTM script domain for server-side tagging. The `<noscript>` iframe is left out while
  Softaware Cookie Consent is active, because visitors without JavaScript cannot give consent.
- **Consent**: with *Softaware Cookie Consent* enabled the Consent Mode defaults come from that module (printed before
  the tag) and nothing else is needed. Without it you can set the defaults here (denied / granted / leave to your consent
  tool). Optionally load GTM/gtag only after the visitor accepts "Analytics" or "Marketing" (needs Softaware Cookie Consent).
- **Events** (GA4 recommended names and item structure):

  | Event | Where | How |
  |---|---|---|
  | view_item_list | category, search results, related, upsell, cross-sell lists (Luma and Hyvä) | page data |
  | select_item | click on a product link inside one of these lists | browser |
  | view_item | product page | page data |
  | add_to_cart / remove_from_cart | any cart change: product page, listing AJAX add, mini-cart, cart page qty/remove, wishlist, reorder | customer-data section |
  | view_cart | cart page | page data |
  | begin_checkout | checkout | page data |
  | add_shipping_info / add_payment_info | Luma checkout (also used by Hyvä) | RequireJS mixins |
  | purchase | order success page, once per order | page data + DB flag |
  | refund | credit memo | Measurement Protocol, cron |
  | add_to_wishlist | product added to the wish list (Luma and Hyvä) | customer-data section |
  | login / sign_up / search | login, registration, search results | section / page data |

  Items carry `item_id` (SKU or product ID), `item_name`, `item_brand`, `item_category`…`item_category5`,
  `item_variant` (child SKU of configurables), `item_list_id`/`item_list_name`/`index` in lists, `affiliation`,
  `discount`, `price`, `quantity`.
- **No double counting**: cart changes are detected server-side by comparing each cart line with its stored quantity
  (so AJAX and full-page adds are counted once, and a guest cart merged at login is not counted). Events are delivered
  through the private customer-data section `softaware-ga4` (full-page cache safe) and every event has an id that the
  browser remembers. Purchase is flagged in `softaware_ga4_order` and never output twice. Cart events use the cart's
  own currency; lines with a custom price (for example free gifts) are reported at that price, even when it is 0.
- **Refunds**: the refund value follows the same "incl. tax / incl. shipping" settings as the purchase value, so a full
  refund cancels the purchase revenue exactly. Each queued refund is claimed before it is sent, so cron and
  `softaware:ga4:refunds --send` running at the same time cannot send it twice.
- **GTM container export**: *Softaware > GA4 & GTM > GTM Container Export* downloads a container with a Google tag,
  GA4 event tags, custom event triggers and variables. Import with *Merge*. Also `bin/magento softaware:ga4:gtm-export`.
- **Debug mode**: logs every push to the browser console (`[Softaware GA4] event`) and sets `debug_mode` for gtag.js.

## Configuration

*Stores > Configuration > Softaware > GA4 & Google Tag Manager* (website and store-view scopes):

- **General**: enabled, tag type (GTM / gtag.js), GTM container ID, GTM script domain, GA4 measurement ID,
  load tags (immediately / after consent), Consent Mode defaults without Softaware Cookie Consent, debug mode.
- **Ecommerce Data**: item ID (SKU / ID), brand attribute and default brand, item categories (deepest / first assigned
  category), affiliation, currency (shown/order currency or base currency), item prices incl. tax, purchase value incl.
  tax and incl. shipping, max items per list, enhanced conversions (user_data).
- **Refunds (Measurement Protocol)**: send refunds, API secret (encrypted).

ACL: `Softaware_Ga4::export`, `Softaware_Ga4::config`.

## For developers

- Push your own events in the browser: `window.swGa4.push('add_to_wishlist', {currency: 'GBP', value: 10, items: [...]})`.
- Queue an event server-side (delivered on the next section load): `Softaware\Ga4\Model\EventQueue::add($event, $params)`.
- Register a product list for view_item_list/select_item: `Softaware\Ga4\Model\ListRegistry::add($id, $name, $products)`.
- Add page events: implement `Softaware\Ga4\Api\PageEventProviderInterface` and add it to the `providers` argument of
  block `softaware.ga4.datalayer` in a layout handle.
- Build items: `Softaware\Ga4\Model\ItemBuilder::fromProduct()/fromQuoteItem()/fromOrderItem()`.

## CLI

- `bin/magento softaware:ga4:refunds` – list queued refunds; `--send` sends pending ones now; `--dry-run` prints the payloads.
- `bin/magento softaware:ga4:gtm-export [--store=default] [--output=container.json]`.

## Compatibility

Magento Open Source / Adobe Commerce 2.4.7–2.4.9, PHP 8.2–8.5, Luma/Blank and Hyvä 1.3+ (no compatibility module
needed; the Hyvä checkout uses the Luma checkout). Requires `softaware/module-core`. Works with or without
`softaware/module-cookie-consent`.

## Limitations

- add_shipping_info / add_payment_info are implemented for the Magento (Luma) checkout. Other one-step checkouts
  need a small adapter calling `window.swGa4.push()`.
- add_payment_info fires when a payment method is selected, including a method the checkout pre-selects.
- Product lists rendered by CMS product widgets (cached block HTML) are not reported as view_item_list.
- Refunds need a GA4 Measurement Protocol API secret; when no `_ga` cookie was available at checkout (consent denied,
  admin orders) a generated client id is used, so the refund is attributed to the transaction but not to the session.
- Enhanced conversions phone numbers are only sent when stored in international format (+44…), as Google requires E.164.
