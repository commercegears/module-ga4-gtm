/**
 * Luma checkout (also used by Hyvä stores through Hyva_LumaCheckout): add_shipping_info / add_payment_info.
 */
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/action/set-shipping-information': {
                'Softaware_Ga4/js/mixin/set-shipping-information': true
            },
            'Magento_Checkout/js/action/select-payment-method': {
                'Softaware_Ga4/js/mixin/select-payment-method': true
            }
        }
    }
};
