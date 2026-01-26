/**
 * WooCommerce UCP Admin JavaScript
 */
(function($) {
    'use strict';

    var UCP_Admin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            $('#ucp-rotate-key').on('click', this.rotateKey.bind(this));
            $('#ucp-test-webhook').on('click', this.testWebhook.bind(this));
            $('#ucp-retry-failed').on('click', this.retryFailed.bind(this));
        },

        /**
         * Rotate signing key
         */
        rotateKey: function(e) {
            e.preventDefault();

            if (!confirm(ucp_wc_admin.strings.confirm_rotate)) {
                return;
            }

            var $button = $(e.target);
            var originalText = $button.text();

            $button.text(ucp_wc_admin.strings.rotating).prop('disabled', true);

            $.ajax({
                url: ucp_wc_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ucp_wc_rotate_key',
                    nonce: ucp_wc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(ucp_wc_admin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    alert(ucp_wc_admin.strings.error + ' Request failed.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Test webhook
         */
        testWebhook: function(e) {
            e.preventDefault();

            var $button = $(e.target);
            var $result = $('#ucp-test-result');
            var originalText = $button.text();

            $button.text(ucp_wc_admin.strings.testing).prop('disabled', true);
            $result.removeClass('success error').text('');

            $.ajax({
                url: ucp_wc_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ucp_wc_test_webhook',
                    nonce: ucp_wc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text(ucp_wc_admin.strings.success + ' ' + response.data.message);
                    } else {
                        $result.addClass('error').text(ucp_wc_admin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('error').text(ucp_wc_admin.strings.error + ' Request failed.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Retry failed webhooks
         */
        retryFailed: function(e) {
            e.preventDefault();

            var $button = $(e.target);
            var originalText = $button.text();

            $button.text(ucp_wc_admin.strings.retrying).prop('disabled', true);

            $.ajax({
                url: ucp_wc_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ucp_wc_retry_failed',
                    nonce: ucp_wc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(ucp_wc_admin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    alert(ucp_wc_admin.strings.error + ' Request failed.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        }
    };

    $(document).ready(function() {
        UCP_Admin.init();
    });

})(jQuery);
