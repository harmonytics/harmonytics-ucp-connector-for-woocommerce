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

            if (!confirm(ucpAdmin.strings.confirm_rotate)) {
                return;
            }

            var $button = $(e.target);
            var originalText = $button.text();

            $button.text(ucpAdmin.strings.rotating).prop('disabled', true);

            $.ajax({
                url: ucpAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ucp_rotate_key',
                    nonce: ucpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(ucpAdmin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    alert(ucpAdmin.strings.error + ' Request failed.');
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

            $button.text(ucpAdmin.strings.testing).prop('disabled', true);
            $result.removeClass('success error').text('');

            $.ajax({
                url: ucpAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ucp_test_webhook',
                    nonce: ucpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text(ucpAdmin.strings.success + ' ' + response.data.message);
                    } else {
                        $result.addClass('error').text(ucpAdmin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('error').text(ucpAdmin.strings.error + ' Request failed.');
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

            $button.text(ucpAdmin.strings.retrying).prop('disabled', true);

            $.ajax({
                url: ucpAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ucp_retry_failed',
                    nonce: ucpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(ucpAdmin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    alert(ucpAdmin.strings.error + ' Request failed.');
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
