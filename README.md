# CommerceGears GA4 & Google Tag Manager for Magento 2

`commercegears/module-ga4-gtm` (`CommerceGears_Ga4`)

GA4 ecommerce tracking for Luma and Hyvä: Google Tag Manager or gtag.js, a complete GA4 dataLayer, Google Consent Mode,
server-side refunds and a GTM container you can import in one step.

Documentation: [User guide](docs/user-guide.md) · [FAQ](docs/faq.md)

## Features

- **Tags**: GTM container snippet in `<head>` plus `<noscript>` iframe after `<body>`, or gtag.js with a GA4
  measurement ID. Custom GTM script domain for server-side tagging. The `<noscript>` iframe is left out while
  CommerceGears Cookie Consent is active, because visitors without JavaScript cannot give consent.
- **Google Ads with GA4 conversion import** (gtag.js): *Additional Google Tag IDs* (for example `AW-123456789`) are
  configured with `gtag('config', id)` after the GA4 measurement ID, so the Google Ads conversion linker stores the ad
  click (`_gcl_au`/`_gcl_aw`, only when ad_storage is granted). Ecommerce events are sent to the GA4 measurement IDs
  only (`send_to`), so import the GA4 purchase as your Ads conversion, or use the direct conversion tags below. In
  GTM mode the setting is ignored: add a Conversion Linker or Google tag to your container instead.
- **Google Ads conversion tags** (optional): with a *Purchase Conversion Label* the success page sends
  `gtag('event', 'conversion', {send_to: 'AW-…/label', value, currency, transaction_id})` right after the GA4 purchase,
  with the same value/currency/transaction_id and under the same once-per-order guard (it travels inside the purchase
  event), after `user_data`. A *Lead Conversion Label* does the same after generate_lead. The AW- ID comes from
  *Google Ads Conversion ID* or the first AW- ID in Additional Google Tag IDs and is configured on every page. GTM mode:
  the purchase/generate_lead dataLayer entries carry an `ads_conversion` object (cleared after the entry), and the GTM
  container export adds a Conversion Linker and Google Ads conversion tags.
- **Leads**: GA4 `generate_lead` (form_id, form_name, lead_source when known, value/currency when a Lead Value is set)
  after a server-confirmed successful submit of the Contact Us form (default) and optionally the newsletter sign-up or
  other modules' forms; queued on the server and delivered once on the next page. No form input in parameters or URLs
  (email/phone only hashed in user_data).
- **Click events**: rules like `demo_open: a[href*="demo.example.com"]` send a custom GA4 event with link_url (no
  fragment, no personal query values) and link_domain. Names used by the module or reserved by GA4 are refused.
- **Consent**: with *CommerceGears Cookie Consent* enabled the Consent Mode defaults come from that module (printed before
  the tag) and nothing else is needed. Without it you can set the defaults here (denied / granted / leave to your consent
  tool). Optionally load GTM/gtag only after the visitor accepts "Analytics" or "Marketing" (needs CommerceGears Cookie Consent).
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
  | purchase | order success page, once per order (optionally not for zero-value orders) | page data + DB flag |
  | refund | credit memo | Measurement Protocol, cron |
  | add_to_wishlist | product added to the wish list (Luma and Hyvä) | customer-data section |
  | login / sign_up / search | login, registration, search results | section / page data |
  | generate_lead | page after a successful Contact Us / newsletter / other form submit | customer-data section |
  | custom click events | click on an element matching a Click Events rule | browser |
  | Google Ads conversion | right after purchase / generate_lead when a label is set | with the event |

  Items carry `item_id` (SKU or product ID), `item_name`, `item_brand`, `item_category`…`item_category5`,
  `item_variant` (child SKU of configurables), `item_list_id`/`item_list_name`/`index` in lists, `affiliation`,
  `discount`, `price`, `quantity`.
- **No double counting**: cart changes are detected server-side by comparing each cart line with its stored quantity
  (so AJAX and full-page adds are counted once, and a guest cart merged at login is not counted). Events are delivered
  through the private customer-data section `commercegears-ga4` (full-page cache safe) and every event has an id that the
  browser remembers. Purchase is flagged in `commercegears_ga4_order` before it is output, so a reload, the back
  button or a second tab never repeat it; all purchase values come from the order, never from the URL. Cart events use the cart's
  own currency; lines with a custom price (for example free gifts) are reported at that price, even when it is 0.
- **Refunds**: the refund value follows the same "incl. tax / incl. shipping" settings as the purchase value, so a full
  refund cancels the purchase revenue exactly. Each queued refund is claimed before it is sent, so cron and
  `commercegears:ga4:refunds --send` running at the same time cannot send it twice.
- **GTM container export**: *CommerceGears > GA4 & GTM > GTM Container Export* downloads a container with a Google tag,
  GA4 event tags, custom event triggers and variables. Import with *Merge*. Also `bin/magento commercegears:ga4:gtm-export`.
- **Purchase value**: the order grand total after discounts, with or without tax and shipping (settings); currency =
  order currency (or base currency); items with item_id, item_name, price after discount and quantity.
  *Skip Zero-value Orders* leaves free orders out (they are still flagged, so they never fire later).
- **Enhanced conversions** (optional): `user_data` with SHA-256 hex of the normalised email (lower case, no spaces,
  Gmail dots removed), phone (E.164; national numbers converted with the address country), first and last name and
  street (lower case, symbols removed), plus city, region, postcode and country in plain text, as Google expects. With
  CommerceGears Cookie Consent it is set (`gtag('set', 'user_data', ...)` before the event, or the purchase dataLayer
  entry in GTM mode) only when the visitor has granted ad_user_data (Marketing). If the visitor accepts later on the
  success page it is set then (`commercegears_user_data` dataLayer event in GTM mode) and the purchase is not repeated.
- **Debug mode**: logs every push to the browser console (`[CommerceGears GA4] event`) and sets `debug_mode` for gtag.js.

## Configuration

*Stores > Configuration > CommerceGears > GA4 & Google Tag Manager* (website and store-view scopes):

- **General**: enabled, tag type (GTM / gtag.js), GTM container ID, GTM script domain, GA4 measurement ID,
  additional Google tag IDs (gtag.js), load tags (immediately / after consent), Consent Mode defaults without
  CommerceGears Cookie Consent, debug mode.
- **Ecommerce Data**: item ID (SKU / ID), brand attribute and default brand, item categories (deepest / first assigned
  category), affiliation, currency (shown/order currency or base currency), item prices incl. tax, purchase value incl.
  tax and incl. shipping, skip zero-value orders, max items per list, enhanced conversions (user_data).
- **Google Ads Conversions**: conversion ID (`ads/conversion_id`, empty = first AW- of Additional Google Tag IDs),
  purchase conversion label (`ads/purchase_label`), lead conversion label (`ads/lead_label`); all empty by default.
- **Leads (generate_lead)**: forms (`leads/forms`, default: Contact Us form), lead value (`leads/value`, empty).
- **Click Events**: events (`clicks/rules`, one `event_name: CSS selector` per line, empty).
- **Refunds (Measurement Protocol)**: send refunds, API secret (encrypted).

ACL: `CommerceGears_Ga4::export`, `CommerceGears_Ga4::config`.

## For developers

- Push your own events in the browser: `window.swGa4.push('add_to_wishlist', {currency: 'GBP', value: 10, items: [...]})`.
- Queue an event server-side (delivered on the next section load): `CommerceGears\Ga4\Model\EventQueue::add($event, $params)`.
- Register a product list for view_item_list/select_item: `CommerceGears\Ga4\Model\ListRegistry::add($id, $name, $products)`.
- Add page events: implement `CommerceGears\Ga4\Api\PageEventProviderInterface` and add it to the `providers` argument of
  block `commercegears.ga4.datalayer` in a layout handle.
- Build items: `CommerceGears\Ga4\Model\ItemBuilder::fromProduct()/fromQuoteItem()/fromOrderItem()`.
- Report a lead from your own form (Leads > Forms = Other forms): `CommerceGears\Ga4\Model\LeadTracker::track($formId,
  $formName, $leadSource, $email)` after the submit succeeded, or dispatch `commercegears_ga4_lead` with `form_id`,
  `form_name`, `lead_source`, `email`, `telephone`.

## CLI

- `bin/magento commercegears:ga4:refunds` – list queued refunds; `--send` sends pending ones now; `--dry-run` prints the payloads.
- `bin/magento commercegears:ga4:gtm-export [--store=default] [--output=container.json]`.

## Compatibility

Magento Open Source / Adobe Commerce 2.4.6–2.4.9, PHP 8.2–8.5 (Magento 2.4.6: PHP 8.2), Luma/Blank and Hyvä 1.3+ (no compatibility module
needed; the Hyvä checkout uses the Luma checkout). Requires `commercegears/module-core`. Works with or without
`commercegears/module-cookie-consent`.

## Limitations

- add_shipping_info / add_payment_info are implemented for the Magento (Luma) checkout. Other one-step checkouts
  need a small adapter calling `window.swGa4.push()`.
- add_payment_info fires when a payment method is selected, including a method the checkout pre-selects.
- Product lists rendered by CMS product widgets (cached block HTML) are not reported as view_item_list.
- Refunds need a GA4 Measurement Protocol API secret; when no `_ga` cookie was available at checkout (consent denied,
  admin orders) a generated client id is used, so the refund is attributed to the transaction but not to the session.
- Enhanced conversions phone numbers in national format are converted for EU/EEA countries, the UK, Switzerland,
  Türkiye, the US, Canada, Australia and New Zealand; other national numbers are left out (Google requires E.164).
- With CommerceGears Cookie Consent, user_data is only set after an explicit Marketing consent, also for visitors in
  regions where Consent Mode grants by default.
- The GTM container's Google Ads conversion tags do not switch on enhanced conversions; enable "Include user-provided
  data" in the tag yourself if you use it.
- Newsletter leads: a guest who subscribes again with an address that is still waiting for confirmation is counted
  again. Contact form leads are tracked through Magento's contact mail service; contact forms of other modules that do
  not use it need the developer API above.
