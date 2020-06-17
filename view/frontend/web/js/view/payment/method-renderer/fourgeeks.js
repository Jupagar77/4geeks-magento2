/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'crypto-js',
        'Magento_Checkout/js/model/quote',
        'Magento_Ui/js/model/messageList',
        'Magento_Payment/js/model/credit-card-validation/credit-card-number-validator',
        'Magento_Payment/js/model/credit-card-validation/expiration-date-validator',
        'mage/translate',
        'jsencrypt',
    ],
    function ($, Component, CryptoJS, quote, globalMessageList, creditCardNumberValidator, expirationDateValidator) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Bananacode_FourGeeks/payment/form'
            },

            context: function () {
                return this;
            },

            getCode: function () {
                return 'fourgeeks';
            },

            isActive: function () {
                return true;
            },

            /**
             * Get data
             * @returns {Object}
             */
            getData: function () {
                return {
                    'method': this.getCode(),
                    'additional_data': {
                        'payment_method_nonce': this.paymentMethodNonce
                    }
                };
            },

            /**
             * Set payment nonce
             * @param paymentMethodNonce
             */
            setPaymentMethodNonce: function (paymentMethodNonce) {
                this.paymentMethodNonce = paymentMethodNonce;
            },

            /**
             *
             */
            encryptMethod: function () {
                return 'AES-256-CBC';
            },

            /**
             *
             * @returns {number}
             */
            encryptMethodLength: function () {
                let encryptMethod = this.encryptMethod();
                let aesNumber = encryptMethod.match(/\d+/)[0];
                return parseInt(aesNumber);
            },

            /**
             *
             * @param string
             * @param key
             * @param self
             * @returns {*}
             */
            encrypt: (string, key, self) => {
                let iv = CryptoJS.lib.WordArray.random(16),
                    salt = CryptoJS.lib.WordArray.random(256),
                    iterations = 999,
                    encryptMethodLength = (self.encryptMethodLength() / 4),
                    hashKey = CryptoJS.PBKDF2(key, salt, {
                        'hasher': CryptoJS.algo.SHA512,
                        'keySize': (encryptMethodLength / 8),
                        'iterations': iterations
                    }),
                    encrypted = CryptoJS.AES.encrypt(string, hashKey, {'mode': CryptoJS.mode.CBC, 'iv': iv}),
                    encryptedString = CryptoJS.enc.Base64.stringify(encrypted.ciphertext),
                    output = {
                        'ciphertext': encryptedString,
                        'iv': CryptoJS.enc.Hex.stringify(iv),
                        'salt': CryptoJS.enc.Hex.stringify(salt),
                        'iterations': iterations
                    };

                return CryptoJS.enc.Base64.stringify(CryptoJS.enc.Utf8.parse(JSON.stringify(output)));
            },

            /**
             * Place order, generate payment nonce before.
             * @param data
             * @param event
             */
            placeOrder: function (data, event) {
                /**
                 * Validate cc number
                 */
                if (!creditCardNumberValidator(this.creditCardNumber()).isValid) {
                    this._showErrors($.mage.__('Invalid credit card number.'));
                    return false;
                } else {
                    const cardInfo = creditCardNumberValidator(this.creditCardNumber()).card;
                    const allowedTypes = Object.values(window.checkoutConfig.payment['ccform']['availableTypes']['fourgeeks']);
                    let allow = false;

                    for (let i = 0, l = allowedTypes.length; i < l; i++) {
                        if (cardInfo.title === allowedTypes[i]) {
                            allow = true
                        }
                    }

                    if (!allow) {
                        this._showErrors($.mage.__('Invalid credit card type.'));
                        return false;
                    }
                }

                /**
                 * Validate expiration date
                 */
                if (!expirationDateValidator(this.creditCardExpMonth() + '/' + this.creditCardExpYear()).isValid) {
                    this._showErrors($.mage.__('Invalid expiration date.'));
                    return false;
                }

                /**
                 * Validate expiration date
                 */
                if (!Number.isInteger(parseInt(this.creditCardVerificationNumber()))) {
                    this._showErrors($.mage.__('Invalid verification number.'));
                    return false;
                }

                let cardData = {
                    "exp_month": parseInt(this.creditCardExpMonth()),
                    "exp_year": parseInt(this.creditCardExpYear()),
                    "credit_card_number": parseInt(this.creditCardNumber()),
                    "credit_card_security_code_number": parseInt(this.creditCardVerificationNumber())
                };

                this.setPaymentMethodNonce(this.encrypt(JSON.stringify(cardData), window.checkoutConfig.payment[this.getCode()].nonce, this));
                this._super(data, event);
            },

            /**
             * Show error messages
             * @param msg
             * @private
             */
            _showErrors: function (msg) {
                $(window).scrollTop(0);
                globalMessageList.addErrorMessage({
                    message: msg
                });
            }
        });
    }
);
