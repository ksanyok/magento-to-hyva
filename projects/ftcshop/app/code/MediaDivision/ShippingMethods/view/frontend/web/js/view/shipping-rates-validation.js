define([
    'uiComponent',
    'Magento_Checkout/js/model/shipping-rates-validator',
    'Magento_Checkout/js/model/shipping-rates-validation-rules',
    '../model/shipping-rates-validator',
    '../model/shipping-rates-validation-rules'
], function (
    Component,
    defaultShippingRatesValidator,
    defaultShippingRatesValidationRules,
    mpShippingRatesValidator,
    mpShippingRatesValidationRules
) {
    'use strict';

    defaultShippingRatesValidator.registerValidator('mpflatrate', mpShippingRatesValidator);
    defaultShippingRatesValidationRules.registerRules('mpflatrate', mpShippingRatesValidationRules);

    return Component;
});
