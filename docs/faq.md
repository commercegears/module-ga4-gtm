# GA4 & Google Tag Manager for Magento 2: FAQ

**Which events does the module send?**
The GA4 recommended ecommerce events: view_item_list, select_item, view_item, add_to_cart, remove_from_cart,
add_to_wishlist, view_cart, begin_checkout, add_shipping_info, add_payment_info, purchase and refund, plus login,
sign_up and search. Items carry item_id, item_name, item_brand, item_category to item_category5, item_variant (the
child SKU of configurable products), list ID, list name and position in lists, affiliation, discount, price and
quantity.

**Do I need Google Tag Manager?**
No. Choose **Tag Type = Google Tag Manager** to push the events to the dataLayer for your GTM tags, or **gtag.js** to
send them straight to GA4 with your measurement ID. With GTM, the admin gives you a ready-made container to import.

**Does it work with Hyvä?**
Yes, on Hyvä 1.3 and later, without a separate compatibility module. Product lists, product pages, cart changes,
wish list, login, sign-up and search are tracked on Hyvä and Luma. The Hyvä storefront uses the Luma checkout
fallback, so the checkout events are the Luma ones.

**Which checkouts are supported for add_shipping_info and add_payment_info?**
The standard Magento (Luma) checkout, which Hyvä themes also use by default. One-step checkouts such as Hyvä Checkout
need a small adapter that calls `window.swGa4.push()` when the shopper picks a shipping or payment method. All other
events, including purchase on the success page, do not depend on the checkout.

**How does it avoid counting a cart change twice?**
Cart changes are detected on the server: each cart line is compared with its stored quantity, so an AJAX add and a
full page reload are counted once, and a guest cart merged at login is not counted again. The events reach the
browser through the private customer-data section `softaware-ga4`, and every event has an id that the browser
remembers. The purchase event is flagged per order in the database and never output twice, even when the shopper
reloads the success page.

**Does it support Google Consent Mode?**
Yes, including the ad_user_data and ad_personalization signals. With Softaware Cookie Consent enabled, the consent
defaults come from that module and are printed before the tag. Without it, you choose the defaults in the settings:
denied until updated, granted, or not set (when your own consent tool sets them). With Softaware Cookie Consent you
can also load GTM or gtag.js only after the visitor accepts Analytics or Marketing.

**How are refunds tracked?**
When you create a credit memo, the module queues a refund event with the order number, value, tax, shipping and the
refunded items. Cron sends it to GA4 through the Measurement Protocol every five minutes, using the GA client ID and
session saved when the order was placed. The API secret is stored encrypted and is never sent to the browser. You
can list, dry-run and send queued refunds with `bin/magento softaware:ga4:refunds`.

**Will a refund cancel the right amount of revenue?**
Yes. The refund value follows the same **Purchase Value Includes Tax** and **Purchase Value Includes Shipping**
settings as the purchase event, so a full refund cancels the purchase revenue exactly.

**Are orders created in the admin tracked?**
The purchase event is sent from the order success page in the shopper's browser, so orders created in the admin do
not send a purchase. Refunds for any order are sent from the server; when no GA client ID was saved, a generated one
is used, so the refund is attributed to the transaction but not to a session.

**What are enhanced conversions?**
An option that adds SHA-256 hashed email, phone and name (plus city, postcode and country) as user_data to the
purchase, sign_up and login events. It is off by default; only switch it on if your privacy policy covers it. Phone
numbers are only sent when stored in international format (for example +44...).

**Can I use server-side tagging?**
Yes. Set **GTM Script Domain** to your tagging server (for example https://sgtm.example.com) and GTM is loaded from
there.

**Can I add my own events?**
Yes. In the browser call `window.swGa4.push('event_name', {...})`. On the server, queue an event with
`Softaware\Ga4\Model\EventQueue::add()`, or add page events by implementing
`Softaware\Ga4\Api\PageEventProviderInterface`. The user guide has the details.
