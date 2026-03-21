define(['jquery', 'Magento_Checkout/js/model/quote'], function ($, quote) {
    'use strict';

    var mixin = {
        defaults: {
            template: 'MediaDivision_CheckoutSteps/shipping-information'
        },

        getPaymentMethodTitle: function () {
            return $('input[name="payment[method]"]:checked').parent('div').find('label').text();
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});
