var config = {
    'config': {
        'mixins': {
           'Magento_Checkout/js/view/shipping': {
               'MediaDivision_CheckoutSteps/js/view/shipping-payment-mixin': true
           },
           'Magento_Checkout/js/view/payment': {
               'MediaDivision_CheckoutSteps/js/view/shipping-payment-mixin': true
           },
           'Magento_Checkout/js/view/shipping-information': {
                'MediaDivision_CheckoutSteps/js/view/shipping-information': true
            }
       }
    },
    'map': {
        '*': {
            'Magento_Checkout/js/view/shipping-information/address-renderer/default': 'MediaDivision_CheckoutSteps/js/view/shipping-information/address-renderer/default'
        }
    }
};
