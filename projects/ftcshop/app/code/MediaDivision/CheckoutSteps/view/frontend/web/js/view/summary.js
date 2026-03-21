define(
    [
        'jquery',
        'ko',
        'Magento_Checkout/js/view/summary',
        'Magento_Checkout/js/model/step-navigator',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/place-order',
        'Magento_CheckoutAgreements/js/model/agreements-modal'
    ],
    function(
        $,
        ko,
        Component,
        stepNavigator,
        additionalValidators,
        redirectOnSuccessAction,
        quote,
        placeOrderAction, 
        agreementsModal
    ) {
        'use strict';

        return Component.extend({
            
            redirectAfterPlaceOrder: true,
            isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
            agreementList: window.checkoutConfig.checkoutAgreements.agreements,
            modalTitle: ko.observable(null),
            modalContent: ko.observable(null),
            contentHeight: ko.observable(null),
            modalWindow: null,
            placeAgreement: false,
            agreementsConfig: function () {
                return window.checkoutConfig ? window.checkoutConfig.checkoutAgreements : {};
            },
            agreementVisible: function() {
                return this.agreementsConfig().isEnabled;
            },
            isAgreementRequired: function (element) {
                return element.mode == 1; //eslint-disable-line eqeqeq
            },
            showContent: function (element) {
                this.modalTitle(element.checkboxText);
                this.modalContent(element.content);
                this.contentHeight(element.contentHeight ? element.contentHeight : 'auto');
                agreementsModal.showModal();
            },
            initModal: function (element) {
                agreementsModal.createModal(element);
            },
            setHiddenCheckboxes: function () {
                if ($('#confirm-agreements-block').find('#multiple_agreement').prop('checked')) {
                    $('#agreement__1').prop('checked', false);
                    $('#agreement__2').prop('checked', false);
                    $('#agreement__3').prop('checked', false);
                    $('#agreement__4').prop('checked', false);
                    $('#agreement__5').prop('checked', false);
                    $('#agreement__6').prop('checked', false);
                } else {
                    $('#agreement__1').prop('checked', true);
                    $('#agreement__2').prop('checked', true);
                    $('#agreement__3').prop('checked', true);
                    $('#agreement__4').prop('checked', true);
                    $('#agreement__5').prop('checked', true);
                    $('#agreement__6').prop('checked', true);
                }
            },
            
            /**
             * After place order callback
             */
            afterPlaceOrder: function () {
                var self = this;
                var subscribeUrl = $('#newsletter-validate-detail').attr('action');
                var subscribeYes = $('#newsletter_subscribe_id').val();
                var customerEmail = window.isCustomerLoggedIn ? window.customerData.email : $('#customer-email').val();
                //console.log(customerEmail);
                //console.log(subscribeUrl);
                if(subscribeYes) {
                    $.ajax({
                        url: subscribeUrl,
                        method: 'POST',
                        data: {
                            email: customerEmail
                        }
                    }).done(function(data) {
                        console.log(data);
                    });
                }
            },

            isVisible: function () {
                return stepNavigator.isProcessed('payment');
            },
            initialize: function () {
                $(function() {
//                    $('body').on("click", '#place-order-trigger', function () {
//                        $(".payment-method._active").find('.action.primary.checkout').trigger( 'click' );
//                    });
                });
                var self = this;
                this._super();
            },

            /**
             * Place order.
             */
            placeOrder: function (data, event) {
                quote.setPaymentMethod(
                        $('input[name="payment[method]"]:checked').val()
                        );
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() &&
                    additionalValidators.validate() //&&
                    //this.isPlaceOrderActionAllowed() === true
                ) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .done(
                            function () {
                                self.afterPlaceOrder();

                                if (self.redirectAfterPlaceOrder) {
                                    redirectOnSuccessAction.execute();
                                }
                            }
                        ).always(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        );

                    return true;
                }

                return false;
            },
      
            /**
             * Get payment method data
             */
            getData: function () {
                return {
                    'method': quote.paymentMethod(),
                    'po_number': null,
                    'additional_data': null
                };
            },
            
            /**
              * @return {Boolean}
              */
            validate: function () {
                return true;
            },
            
            /**
             * @return {*}
             */
            getPlaceOrderDeferredObject: function () {
                return $.when(
                    placeOrderAction(this.getData(), this.messageContainer)
                );
            },

        });
    }
);
