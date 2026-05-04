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

            if (!confirm(hucp_admin.strings.confirm_rotate)) {
                return;
            }

            var $button = $(e.target);
            var originalText = $button.text();

            $button.text(hucp_admin.strings.rotating).prop('disabled', true);

            $.ajax({
                url: hucp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hucp_rotate_key',
                    nonce: hucp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(hucp_admin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    alert(hucp_admin.strings.error + ' Request failed.');
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

            $button.text(hucp_admin.strings.testing).prop('disabled', true);
            $result.removeClass('success error').text('');

            $.ajax({
                url: hucp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hucp_test_webhook',
                    nonce: hucp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text(hucp_admin.strings.success + ' ' + response.data.message);
                    } else {
                        $result.addClass('error').text(hucp_admin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('error').text(hucp_admin.strings.error + ' Request failed.');
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

            $button.text(hucp_admin.strings.retrying).prop('disabled', true);

            $.ajax({
                url: hucp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hucp_retry_failed',
                    nonce: hucp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(hucp_admin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    alert(hucp_admin.strings.error + ' Request failed.');
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
