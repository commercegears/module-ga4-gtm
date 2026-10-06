/**
 * add_shipping_info after the shipping step was saved successfully (once per shipping method and page).
 */
define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote'
], function (wrapper, quote) {
    'use strict';

    var lastTier = null;

    function track() {
        var ga = window.swGa4, method = quote.shippingMethod(), params, key;
        if (!ga || !ga.checkout || !method) {
            return;
        }
        var tier = [method.carrier_title, method.method_title].filter(Boolean).join(' - ') || method.carrier_code;
        if (tier === lastTier) {
            return;
        }
        lastTier = tier;
        params = {};
        for (key in ga.checkout) {
            if (Object.prototype.hasOwnProperty.call(ga.checkout, key)) {
                params[key] = ga.checkout[key];
            }
        }
        params.shipping_tier = tier;
        ga.push('add_shipping_info', params);
    }

    return function (action) {
        return wrapper.wrap(action, function (original) {
            var result = original();
            if (result && typeof result.done === 'function') {
                result.done(function () {
                    try { track(); } catch (e) { /* never break checkout */ }
                });
            }
            return result;
        });
    };
});
