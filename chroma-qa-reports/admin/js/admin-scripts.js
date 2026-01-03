/**
 * Chroma QA Reports - Admin Scripts
 *
 * @package ChromaQAReports
 */

(function ($) {
    'use strict';

    // Global namespace
    window.CQA = window.CQA || {};

    /**
     * API Helper
     */
    CQA.api = {
        baseUrl: cqaAdmin.restUrl,
        nonce: cqaAdmin.nonce,

        request: function (endpoint, method, data) {
            return $.ajax({
                url: this.baseUrl + endpoint,
                method: method || 'GET',
                data: data,
                headers: {
                    'X-WP-Nonce': this.nonce
                },
                contentType: 'application/json',
                dataType: 'json'
            });
        },

        get: function (endpoint, data) {
            return this.request(endpoint, 'GET', data);
        },

        post: function (endpoint, data) {
            return $.ajax({
                url: this.baseUrl + endpoint,
                method: 'POST',
                data: JSON.stringify(data),
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                },
                dataType: 'json'
            });
        },

        put: function (endpoint, data) {
            return $.ajax({
                url: this.baseUrl + endpoint,
                method: 'PUT',
                data: JSON.stringify(data),
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                },
                dataType: 'json'
            });
        },

        delete: function (endpoint) {
            return this.request(endpoint, 'DELETE');
        }
    };

    /**
     * Notifications
     */
    CQA.notify = {
        show: function (message, type) {
            type = type || 'info';

            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            $('.cqa-wrap').prepend($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function () {
                $notice.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        },

        success: function (message) {
            this.show(message, 'success');
        },

        error: function (message) {
            this.show(message, 'error');
        },

        warning: function (message) {
            this.show(message, 'warning');
        }
    };

    /**
     * Confirm dialog
     */
    CQA.confirm = function (message, callback) {
        if (confirm(message)) {
            callback();
        }
    };

    /**
     * Loading state helpers
     */
    CQA.loading = {
        show: function ($element) {
            $element.addClass('cqa-loading').prop('disabled', true);
            $element.data('original-text', $element.text());
            $element.text(cqaAdmin.strings.saving);
        },

        hide: function ($element) {
            $element.removeClass('cqa-loading').prop('disabled', false);
            $element.text($element.data('original-text'));
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        // Delete confirmation
        $('.cqa-delete-btn').on('click', function (e) {
            if (!confirm(cqaAdmin.strings.confirm_delete)) {
                e.preventDefault();
            }
        });

        // Auto-dismiss notices
        $('.notice.is-dismissible').each(function () {
            var $notice = $(this);
            setTimeout(function () {
                $notice.fadeOut();
            }, 5000);
        });

        // Toggle password visibility
        $('input[type="password"]').each(function () {
            var $input = $(this);
            var $toggle = $('<button type="button" class="button button-secondary" style="margin-left: 8px;">Show</button>');

            $toggle.on('click', function () {
                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $(this).text('Hide');
                } else {
                    $input.attr('type', 'password');
                    $(this).text('Show');
                }
            });

            $input.after($toggle);
        });

        // Form validation
        $('form').on('submit', function () {
            var valid = true;

            $(this).find('[required]').each(function () {
                if (!$(this).val()) {
                    $(this).addClass('cqa-error');
                    valid = false;
                } else {
                    $(this).removeClass('cqa-error');
                }
            });

            return valid;
        });

        // Status badge colors
        $('.cqa-badge').each(function () {
            var $badge = $(this);
            var text = $badge.text().toLowerCase().trim();

            if (text.includes('exceeds') || text.includes('approved') || text.includes('active')) {
                $badge.addClass('cqa-badge-success');
            } else if (text.includes('needs') || text.includes('improvement')) {
                $badge.addClass('cqa-badge-danger');
            } else if (text.includes('draft') || text.includes('pending')) {
                $badge.addClass('cqa-badge-warning');
            }
        });

        // Initialize Settings Page
        if ($('.cqa-settings-form').length > 0) {
            CQA.settings.init();
        }
    });

    /**
     * Settings Page Logic
     */
    CQA.settings = {
        init: function () {
            this.cacheDOM();
            this.bindEvents();

            if (cqaAdmin.googleClientId && cqaAdmin.developerKey) {
                this.loadGooglePicker();
            }
        },

        cacheDOM: function () {
            this.$driveBtn = $('#cqa-drive-picker-btn');
            this.$driveInput = $('#cqa_drive_root_folder');
        },

        bindEvents: function () {
            this.$driveBtn.on('click', this.handleDriveClick.bind(this));
        },

        loadGooglePicker: function () {
            $.getScript('https://apis.google.com/js/api.js', function () {
                gapi.load('picker', { 'callback': function () { } });
                gapi.load('client', function () {
                    gapi.client.init({
                        'clientId': cqaAdmin.googleClientId,
                        'scope': 'https://www.googleapis.com/auth/drive.file'
                    });
                });
            });
        },

        handleDriveClick: function (e) {
            e.preventDefault();
            const token = gapi.client.getToken();
            if (token) {
                this.createPicker(token.access_token);
            } else {
                gapi.auth2.getAuthInstance().signIn().then(() => {
                    const newToken = gapi.client.getToken();
                    this.createPicker(newToken.access_token);
                });
            }
        },

        createPicker: function (oauthToken) {
            const picker = new google.picker.PickerBuilder()
                .addView(google.picker.ViewId.FOLDERS)
                .setOAuthToken(oauthToken)
                .setDeveloperKey(cqaAdmin.developerKey)
                .setCallback(this.pickerCallback.bind(this))
                .build();
            picker.setVisible(true);
        },

        pickerCallback: function (data) {
            if (data[google.picker.Response.ACTION] == google.picker.Action.PICKED) {
                const doc = data[google.picker.Response.DOCUMENTS][0];
                const folderId = doc[google.picker.Document.ID];
                this.$driveInput.val(folderId);
            }
        }
    };

})(jQuery);
