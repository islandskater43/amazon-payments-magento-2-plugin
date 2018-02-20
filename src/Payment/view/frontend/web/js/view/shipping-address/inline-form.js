define([
    'jquery',
    'uiComponent',
    'ko',
    'Amazon_Payment/js/model/storage'
], function ($, Component, ko, amazonStorage) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Amazon_Payment/shipping-address/inline-form',
            formSelector: '#co-shipping-form'
        },
        initObservable: function () {
            this._super();
            amazonStorage.isAmazonAccountLoggedIn.subscribe(function (value) {
                var elem = document.getElementById(this.formSelector);
                if (elem && value === false) {
                    document.getElementById(this.formSelector).style.display = 'block';
                }
            }, this);
            return this;
        },
        manipulateInlineForm: function () {
            if (amazonStorage.isAmazonAccountLoggedIn()) {
                var errorCount = 0;
                $(this.formSelector).find(".field").each(function () {
                    if ($(this).hasClass('_error')) {
                        errorCount ++;
                        $(this).show();
                    } else {
                        $(this).css("display", "none");
                    }
                });
                var elem = $(this.formSelector);
                if (elem) {
                    if (errorCount > 0) {
                        $(this.formSelector).show();
                    } else {
                        $(this.formSelector).hide();
                    }
                }
            }
        }
    });
});
