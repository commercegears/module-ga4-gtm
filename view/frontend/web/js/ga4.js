/**
 * CommerceGears GA4 - pushes GA4 ecommerce events to the dataLayer (GTM) or gtag (gtag.js).
 * Vanilla JS for Luma and Hyvä. Sources:
 *   1. #sw-ga4-data JSON rendered in the page (view_item, view_item_list, view_cart, begin_checkout, purchase, search)
 *   2. customer-data section "commercegears-ga4" (add_to_cart, remove_from_cart, login, sign_up) - private content,
 *      so it works with full-page cache and AJAX carts
 *   3. clicks on product links inside tracked lists (select_item)
 *   4. Luma checkout mixins (add_shipping_info, add_payment_info) via window.swGa4.push()
 *   5. clicks on elements matching the configured click events (custom event name, link_url without personal data)
 * Every queued event has an id; delivered ids are remembered in localStorage so nothing is counted twice.
 * Google Ads conversions (purchase, generate_lead) travel inside their event and are sent right after it, under the
 * same id: gtag('event', 'conversion', {send_to, ...}) in gtag mode, an ads_conversion key on the dataLayer entry in GTM.
 * Enhanced conversions: with CommerceGears Cookie Consent active, user_data is only set (gtag 'set' / dataLayer) once the
 * visitor has granted ad_user_data; when consent comes later on the same page it is set then, without repeating events.
 */
(function () {
    'use strict';

    var w = window, d = document;
    if (w.swGa4 && w.swGa4.ready) {
        return;
    }
    var dataEl = d.getElementById('sw-ga4-data');
    var data = {};
    try {
        data = JSON.parse(dataEl ? dataEl.textContent : '{}') || {};
    } catch (e) {
        data = {};
    }
    var mode = data.mode || 'gtm';
    var debug = !!data.debug;
    var consentGate = !!data.consentGate;
    var sendTo = Array.isArray(data.sendTo) && data.sendTo.length ? data.sendTo : null;
    var pendingUserData = null;
    var SEEN_KEY = 'sw_ga4_seen';
    var ECOMMERCE = ['view_item_list', 'select_item', 'view_item', 'add_to_cart', 'remove_from_cart', 'view_cart',
        'begin_checkout', 'add_shipping_info', 'add_payment_info', 'purchase', 'refund', 'add_to_wishlist'];

    w.dataLayer = w.dataLayer || [];
    if (typeof w.gtag !== 'function') {
        w.gtag = function () { w.dataLayer.push(arguments); };
    }

    function readSeen() {
        try {
            return JSON.parse(w.localStorage.getItem(SEEN_KEY) || '[]') || [];
        } catch (e) {
            return [];
        }
    }

    function remember(uid) {
        try {
            var seen = readSeen();
            seen.push(uid);
            w.localStorage.setItem(SEEN_KEY, JSON.stringify(seen.slice(-100)));
        } catch (e) { /* storage disabled: in-memory guard below still prevents doubles on this page */ }
    }

    var seenNow = {};

    function log(name, params, extra) {
        if (debug && w.console) {
            console.log('%c[CommerceGears GA4]%c ' + name, 'color:#fff;background:#1a73e8;padding:1px 4px;border-radius:2px', 'font-weight:bold', params, extra || '');
        }
    }

    /**
     * ad_user_data granted? Always true without CommerceGears Cookie Consent (another consent tool or Consent Mode
     * decides). With it, true only after the visitor accepted the category that controls ad_user_data (Marketing).
     */
    function userDataAllowed() {
        if (!consentGate) {
            return true;
        }
        var cc = w.SwConsent;
        if (!cc || typeof cc.consentTypes !== 'function') {
            return false;
        }
        var granted = cc.state && Array.isArray(cc.state.c) ? cc.state.c : ((cc.config && cc.config.required) || []);
        return cc.consentTypes(granted).ad_user_data === 'granted';
    }

    function applyUserData(userData, standalone) {
        if (mode === 'gtag') {
            w.gtag('set', 'user_data', userData);
        } else if (standalone) {
            w.dataLayer.push({ event: 'commercegears_user_data', user_data: userData });
        }
        log('user_data', {}, standalone ? 'set after consent' : '');
    }

    d.addEventListener('sw-consent:change', function () {
        if (pendingUserData && userDataAllowed()) {
            var userData = pendingUserData;
            pendingUserData = null;
            applyUserData(userData, true);
        }
    });

    /**
     * @param {string} name   GA4 event name
     * @param {Object} params event parameters (items, value, currency ...)
     * @param {Object} [extra] top-level keys such as user_data
     * @param {string} [uid]  unique id: the event is pushed only once per browser
     * @param {Object} [conversion] Google Ads conversion sent right after the event (send_to, value, currency, transaction_id)
     */
    function push(name, params, extra, uid, conversion) {
        if (!name) {
            return false;
        }
        if (uid) {
            if (seenNow[uid] || readSeen().indexOf(uid) !== -1) {
                return false;
            }
            seenNow[uid] = true;
            remember(uid);
        }
        params = params || {};
        extra = extra || {};
        var key, original = params;
        if (extra.user_data) {
            var rest = {};
            for (key in extra) {
                if (Object.prototype.hasOwnProperty.call(extra, key) && key !== 'user_data') { rest[key] = extra[key]; }
            }
            if (userDataAllowed()) {
                pendingUserData = null;
                if (mode === 'gtag') {
                    applyUserData(extra.user_data, false); // before the event
                } else {
                    rest.user_data = extra.user_data;
                }
            } else {
                pendingUserData = extra.user_data; // set once ad_user_data is granted on this page
                log('user_data', {}, 'waiting for ad_user_data consent');
            }
            extra = rest;
        }
        if (mode === 'gtag') {
            if (sendTo && !params.send_to) {
                var copy = {};
                for (key in params) {
                    if (Object.prototype.hasOwnProperty.call(params, key)) { copy[key] = params[key]; }
                }
                copy.send_to = sendTo;
                params = copy;
            }
            w.gtag('event', name, params);
            if (conversion && conversion.send_to) {
                // After user_data (set before the event above) and after the GA4 event.
                w.gtag('event', 'conversion', conversion);
            }
        } else {
            var entry = { event: name };
            if (ECOMMERCE.indexOf(name) !== -1) {
                w.dataLayer.push({ ecommerce: null });
                entry.ecommerce = params;
            } else {
                for (key in params) {
                    if (Object.prototype.hasOwnProperty.call(params, key)) { entry[key] = params[key]; }
                }
            }
            for (key in extra) {
                if (Object.prototype.hasOwnProperty.call(extra, key)) { entry[key] = extra[key]; }
            }
            if (conversion && conversion.send_to) {
                entry.ads_conversion = conversion;
            }
            w.dataLayer.push(entry);
            if (entry.ads_conversion) {
                // Only the event that carries it may read ads_conversion (GTM keeps keys for later events).
                w.dataLayer.push({ ads_conversion: null });
            }
        }
        log(name, original, extra);
        if (conversion && conversion.send_to) {
            log('conversion', conversion);
        }
        if (name === 'begin_checkout') {
            api.checkout = original;
        }
        return true;
    }

    var api = w.swGa4 = {
        ready: true,
        push: push,
        checkout: null,
        lists: data.lists || [],
        debug: debug
    };

    /* ------------------------------------------------------------- page events and lists */

    (data.events || []).forEach(function (e) {
        push(e.event, e.params, e.extra, e.uid, e.conversion);
    });

    var linkMap = {};
    function normalise(url) {
        try {
            var u = new URL(url, location.href);
            return u.host + u.pathname.replace(/\/$/, '');
        } catch (e) {
            return '';
        }
    }

    (data.lists || []).forEach(function (list) {
        push('view_item_list', { item_list_id: list.id, item_list_name: list.name, items: list.items });
        (list.urls || []).forEach(function (url, i) {
            var key = normalise(url);
            if (key && !linkMap[key]) {
                linkMap[key] = { list: list, item: list.items[i] };
            }
        });
    });

    d.addEventListener('click', function (e) {
        var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) {
            return;
        }
        var match = linkMap[normalise(link.href)];
        if (match) {
            push('select_item', {
                item_list_id: match.list.id,
                item_list_name: match.list.name,
                items: [match.item]
            });
        }
    }, true);

    /* ------------------------------------------------------------- click events (configured selectors) */

    var clickRules = Array.isArray(data.clicks) ? data.clicks : [];
    // Query parameters kept in link_url: short codes only (tour=ga4-gtm, utm_source=...), never values that could
    // identify a person.
    var SENSITIVE_PARAM = /mail|name|phone|tel|token|key|pass|secret|auth|sess|sid|code|hash|user|customer|address|zip|post/i;

    function linkParams(el) {
        var a = el.closest('a[href]') || (el.querySelector ? el.querySelector('a[href]') : null);
        if (!a) {
            return {};
        }
        var u;
        try {
            u = new URL(a.getAttribute('href'), location.href);
        } catch (e) {
            return {};
        }
        if (!/^https?:$/.test(u.protocol)) {
            return {}; // mailto:, tel:, javascript: ...
        }
        var keep = [];
        u.searchParams.forEach(function (value, name) {
            if (/^[A-Za-z0-9_.-]{1,64}$/.test(value) && /^[A-Za-z0-9_.-]{1,40}$/.test(name) && !SENSITIVE_PARAM.test(name)) {
                keep.push(name + '=' + value);
            }
        });
        return { link_url: u.origin + u.pathname + (keep.length ? '?' + keep.join('&') : ''), link_domain: u.hostname };
    }

    if (clickRules.length) {
        d.addEventListener('click', function (e) {
            var target = e.target && e.target.closest ? e.target : null;
            if (!target) {
                return;
            }
            var fired = {};
            clickRules.forEach(function (rule) {
                if (!rule || !rule.event || fired[rule.event]) {
                    return;
                }
                var el = null;
                try {
                    el = target.closest(rule.selector);
                } catch (err) {
                    return; // invalid selector: ignored
                }
                if (el) {
                    fired[rule.event] = true;
                    push(rule.event, linkParams(el));
                }
            });
        }, true);
    }

    /* ------------------------------------------------------------- private events (customer data) */

    function handleSection(section) {
        if (!section || !Array.isArray(section.events)) {
            return;
        }
        section.events.forEach(function (e) {
            push(e.event, e.params, e.extra, e.uid, e.conversion);
        });
    }

    function clearPendingCookie() {
        d.cookie = 'sw_ga4_pending=; path=/; max-age=0; SameSite=Lax';
    }

    if (w.hyva) {
        // Hyvä: private content is dispatched on window after every (re)load.
        w.addEventListener('private-content-loaded', function (event) {
            handleSection(event.detail && event.detail.data ? event.detail.data['commercegears-ga4'] : null);
        });
    } else if (typeof w.require === 'function') {
        // Luma (and the Luma checkout used by Hyvä).
        w.require(['Magento_Customer/js/customer-data'], function (customerData) {
            var section = customerData.get('commercegears-ga4');
            handleSection(section());
            section.subscribe(function (value) {
                handleSection(value);
                clearPendingCookie();
            });
        });
    }

    // Events were queued but the section response may have been lost (page navigated away mid-request):
    // fetch the section once directly. Already delivered events are skipped by their uid.
    if (/(?:^|; )sw_ga4_pending=1/.test(d.cookie) && w.fetch) {
        w.fetch((w.BASE_URL || '/') + 'customer/section/load/?sections=commercegears-ga4', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (json) {
            if (json) {
                handleSection(json['commercegears-ga4']);
                clearPendingCookie();
            }
        }).catch(function () { /* try again on the next page */ });
    }
})();
