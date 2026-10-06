/**
 * add_payment_info when a payment method is chosen (once per method and page).
 */
define([
    'require',
    'mage/utils/wrapper'
], function (require, wrapper) {
    'use strict';

    var lastMethod = null;

    function title(code) {
        var paymentService, found;
        // Loaded lazily: payment-service itself depends on select-payment-method (circular dependency).
        try {
            paymentService = require('Magento_Checkout/js/model/payment-service');
        } catch (e) {
            paymentService = null;
        }
        found = !paymentService ? null : (paymentService.getAvailablePaymentMethods() || []).filter(function (m) {
            return m.method === code;
        })[0];
        if (found && found.title) {
            return found.title;
        }
        found = document.querySelector('label[for="' + code + '"]');
        return found && found.textContent.trim() ? found.textContent.trim() : code;
    }

    function track(method) {
        var ga = window.swGa4, params = {}, key;
        if (!ga || !ga.checkout || !method || !method.method || method.method === lastMethod) {
            return;
        }
        lastMethod = method.method;
        for (key in ga.checkout) {
            if (Object.prototype.hasOwnProperty.call(ga.checkout, key)) {
                params[key] = ga.checkout[key];
            }
        }
        params.payment_type = title(method.method);
        ga.push('add_payment_info', params);
    }

    return function (action) {
        return wrapper.wrap(action, function (original, paymentMethod) {
            var result = original(paymentMethod);
            // Deferred so that add_shipping_info (pushed when the shipping step is saved) comes first
            // when the checkout pre-selects a method on entering the payment step.
            setTimeout(function () {
                try { track(paymentMethod); } catch (e) { /* never break checkout */ }
            }, 0);
            return result;
        });
    };
});
