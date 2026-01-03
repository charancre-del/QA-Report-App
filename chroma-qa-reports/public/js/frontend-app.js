/**
 * Chroma QA Reports - Frontend Application
 * 
 * Handles report wizard, photo uploads, and form submission.
 */
(function ($) {
    'use strict';

    const CQA = {
        init: function () {
            this.cacheDOM();
            this.bindEvents();
            this.initWizard();
            this.initPhotoUpload();
            this.initRatings();

            // Do not start autosave immediately on checking, only if editing existing
            if (this.$wizard.data('report-id')) {
                this.initAutosave();
            }

            // Load Google Picker if available
            if (cqaFrontend.googleClientId && cqaFrontend.developerKey) {
                this.loadGooglePicker();
            }
        },

        cacheDOM: function () {
            this.$wizard = $('#cqa-report-wizard');
            this.$form = $('#cqa-report-form');
            this.$steps = $('.cqa-wizard-step');
            this.$panels = $('.cqa-wizard-panel');
            this.$prevBtn = $('#cqa-prev-btn');
            this.$nextBtn = $('#cqa-next-btn');
            this.$submitBtn = $('#cqa-submit-btn');
            this.$saveDraftBtn = $('#cqa-save-draft-btn');
            this.$schoolSelect = $('#cqa-school-select');
            this.$driveBtn = $('.cqa-drive-picker-btn'); // Add class to your button HTML
        },

        bindEvents: function () {
            this.$nextBtn.on('click', this.nextStep.bind(this));
            this.$prevBtn.on('click', this.prevStep.bind(this));
            this.$schoolSelect.on('change', this.handleSchoolChange.bind(this));
            this.$saveDraftBtn.on('click', this.saveDraft.bind(this));
            this.$form.on('submit', this.handleSubmit.bind(this));

            // Rating buttons
            $(document).on('click', '.cqa-rating-btn', this.handleOverallRating.bind(this));
            $(document).on('click', '.cqa-item-rating-btn', this.handleItemRating.bind(this));

            // Checklist navigation
            $(document).on('click', '.cqa-checklist-section-header', this.toggleSection.bind(this));

            // Google Drive
            // Using delegation in case button is dynamically added
            $(document).on('click', '#cqa-drive-picker-btn', this.handleDriveClick.bind(this));
        },

        initWizard: function () {
            this.currentStep = 1;
            this.totalSteps = this.$panels.length;
            this.updateButtons();

            // Show first panel
            $(`.cqa-wizard-panel[data-step="${this.currentStep}"]`).addClass('active');
        },

        updateButtons: function () {
            // Previous button
            if (this.currentStep === 1) {
                this.$prevBtn.hide();
            } else {
                this.$prevBtn.show();
            }

            // Next/Submit buttons
            if (this.currentStep === this.totalSteps) {
                this.$nextBtn.hide();
                this.$submitBtn.show();
            } else {
                this.$nextBtn.show();
                this.$submitBtn.hide();
            }

            // Step indicators
            this.$steps.removeClass('active completed');
            this.$steps.each((i, el) => {
                const step = i + 1;
                if (step === this.currentStep) {
                    $(el).addClass('active');
                } else if (step < this.currentStep) {
                    $(el).addClass('completed');
                }
            });
        },

        nextStep: function () {
            if (this.validateStep(this.currentStep)) {
                this.$panels.removeClass('active');
                this.currentStep++;
                $(`.cqa-wizard-panel[data-step="${this.currentStep}"]`).addClass('active');
                this.updateButtons();
                window.scrollTo(0, 0);
            }
        },

        prevStep: function () {
            if (this.currentStep > 1) {
                this.$panels.removeClass('active');
                this.currentStep--;
                $(`.cqa-wizard-panel[data-step="${this.currentStep}"]`).addClass('active');
                this.updateButtons();
                window.scrollTo(0, 0);
            }
        },

        validateStep: function (step) {
            // Basic validation
            let isValid = true;
            const $panel = $(`.cqa-wizard-panel[data-step="${step}"]`);

            $panel.find('input[required], select[required], textarea[required]').each(function () {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('error');
                    $(this).css('border-color', 'var(--cqa-danger)');
                } else {
                    $(this).removeClass('error');
                    $(this).css('border-color', '');
                }
            });

            if (!isValid) {
                alert('Please fill in all required fields.'); // Better UI later
            }

            return isValid;
        },

        handleSchoolChange: function (e) {
            const schoolId = $(e.target).val();
            if (schoolId) {
                window.location.href = `?school_id=${schoolId}`;
            }
        },

        handleOverallRating: function (e) {
            const $btn = $(e.currentTarget);
            $('.cqa-rating-btn').removeClass('selected');
            $btn.addClass('selected');
            $('#cqa-overall-rating').val($btn.data('value'));
        },

        handleItemRating: function (e) {
            const $btn = $(e.currentTarget);
            const $group = $btn.closest('.cqa-item-ratings');
            const $hiddenInput = $group.find('input[type="hidden"]');

            $group.find('.cqa-item-rating-btn').removeClass('selected');
            $btn.addClass('selected');
            $hiddenInput.val($btn.data('value'));
        },

        toggleSection: function (e) {
            const $header = $(e.currentTarget);
            const $body = $header.next('.cqa-checklist-section-body');
            const $icon = $header.find('.dashicons');

            $body.slideToggle();
            $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        },

        initPhotoUpload: function () {
            // Drag and drop logic
            const $dropzone = $('.cqa-photo-upload-area');

            $dropzone.on('dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            $dropzone.on('dragleave', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            $dropzone.on('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');

                const files = e.originalEvent.dataTransfer.files;
                CQA.handleFiles(files);
            });

            // File input
            $('#cqa-photo-input').on('change', function (e) {
                CQA.handleFiles(this.files);
            });
        },

        handleFiles: function (files) {
            const $gallery = $('#cqa-photo-gallery');

            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const html = `
                            <div class="cqa-photo-thumb">
                                <img src="${e.target.result}" alt="Preview">
                                <input type="hidden" name="new_photos" value="${e.target.result}">
                            </div>
                        `;
                        $gallery.append(html);
                    }
                    reader.readAsDataURL(file);
                }
            });
        },

        // --- Data Persistence ---

        saveDraft: function (e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const originalText = $btn.text();

            $btn.prop('disabled', true).text(cqaFrontend.strings.saving);

            // Set status to draft
            // If field doesn't exist, append it
            if ($('#cqa_status').length === 0) {
                this.$form.append('<input type="hidden" id="cqa_status" name="status" value="draft">');
            } else {
                $('#cqa_status').val('draft');
            }

            this.submitToRestApi('draft').always(function () {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        handleSubmit: function (e) {
            e.preventDefault();

            // Set status to submitted
            if ($('#cqa_status').length === 0) {
                this.$form.append('<input type="hidden" id="cqa_status" name="status" value="submitted">');
            } else {
                $('#cqa_status').val('submitted');
            }

            if (confirm('Are you sure you want to submit this report?')) {
                this.$submitBtn.prop('disabled', true).text(cqaFrontend.strings.saving);
                this.submitToRestApi('submitted');
            }
        },

        /**
         * Submit form data to REST API
         * Supports both Create (POST) and Update (POST/PUT)
         */
        submitToRestApi: function (status) {
            const reportId = this.$wizard.data('report-id');
            const method = reportId ? 'POST' : 'POST'; // Keep POST for update if using _method override or just handling update logic in same endpoint? 
            // REST Controller uses:
            // POST /reports -> create_report
            // POST /reports/(id) -> update_report (if using method override or just POST to ID?) 
            // Actually WP REST supports POST to ID for update usually.

            let url = cqaFrontend.restUrl + 'reports';
            if (reportId) {
                url += '/' + reportId;
            }

            // Gather Form Data
            // We need to structure it to match what the REST API expects
            // create_report expects: school_id, report_type, inspection_date, etc.
            // AND responses array for checklsit items

            // For file uploads and complex nested data, FormData is tricky with WP REST sometimes.
            // But we are sending JSON for the main data usually, or standard FormData form-encoded.
            // Let's stick to standard JQuery AJAX with serialized array for simplicity first, 
            // but we need to handle the checklist responses deeply.

            // Custom serialization to object structure
            const formData = this.serializeFormJSON(this.$form);

            // Add status manually if needed
            formData.status = status;

            // Specifically format checklist responses
            // The form has inputs like: name="responses[section][item][rating]"
            // This needs to be parsed or sent as is if the PHP side expects that structure.
            // REST_Controller::save_report_responses expects 'responses' param as array.

            // If we are creating, we might need to do 2 steps? 
            // 1. Create Report -> Get ID
            // 2. Save Responses -> /reports/ID/responses
            // Unless create_report handles responses? 
            // create_report in REST_Controller DOES NOT seem to handle responses array in the provided code.
            // It only saves school_id, date, etc.

            // STRATEGY: 
            // If New: Create Report -> Get ID -> Save Responses -> Redirect
            // If Edit: Update Report -> Save Responses -> Redirect/Reload

            const self = this;
            const deferred = $.Deferred();

            // Step 1: Save/Create Report Header
            $.ajax({
                url: url,
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
                },
                data: formData
            }).done(function (response) {
                const newReportId = response.id;

                // Update form check for ID so next save is an Update
                self.$wizard.data('report-id', newReportId);

                // Step 2: Save Responses
                self.saveResponses(newReportId).done(function () {
                    if (status === 'draft') {
                        // Just notify success
                        // Show success toast
                        const $toast = $('<div class="cqa-toast">Draft Saved</div>');
                        $('body').append($toast);
                        setTimeout(() => $toast.fadeOut(() => $toast.remove()), 3000);
                        deferred.resolve();
                    } else {
                        // Redirect
                        window.location.href = cqaFrontend.homeUrl;
                        deferred.resolve();
                    }
                }).fail(function () {
                    alert('Report saved, but responses failed to save.');
                    deferred.reject();
                });

            }).fail(function (xhr) {
                let msg = cqaFrontend.strings.error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
                self.$submitBtn.prop('disabled', false).text('Submit Report');
                deferred.reject();
            });

            return deferred.promise();
        },

        saveResponses: function (reportId) {
            // Collect all response inputs
            // We need to parse inputs like name="responses[classroom_infant][ratios][rating]"
            // into a structured object: { "classroom_infant": { "ratios": { "rating": "yes", "notes": "..." } } }

            const responses = {};

            this.$form.find('input[name^="responses"], textarea[name^="responses"]').each(function () {
                const name = $(this).attr('name');
                const val = $(this).val();

                // Regex to extract keys: responses[section][item][field]
                const match = name.match(/responses\[(.*?)\]\[(.*?)\]\[(.*?)\]/);
                if (match) {
                    const section = match[1];
                    const item = match[2];
                    const field = match[3];

                    if (!responses[section]) responses[section] = {};
                    if (!responses[section][item]) responses[section][item] = {};

                    responses[section][item][field] = val;
                }
            });

            return $.ajax({
                url: cqaFrontend.restUrl + 'reports/' + reportId + '/responses',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
                },
                data: JSON.stringify({ responses: responses }), // Send as JSON body
                contentType: 'application/json'
            });
        },

        serializeFormJSON: function ($form) {
            const o = {};
            const a = $form.serializeArray();
            $.each(a, function () {
                // Skip responses fields for the main object, we handle them separately
                if (this.name.startsWith('responses')) return;

                if (o[this.name]) {
                    if (!o[this.name].push) {
                        o[this.name] = [o[this.name]];
                    }
                    o[this.name].push(this.value || '');
                } else {
                    o[this.name] = this.value || '';
                }
            });
            return o;
        },

        initAutosave: function () {
            // Simple autosave every 60 seconds
            const self = this;
            setInterval(function () {
                if (self.$wizard.data('report-id')) {
                    // Silent save-ish
                    // For now just console log to avoid disrupting user
                    // console.log('Autosaving draft...');
                    // self.submitToRestApi('draft'); 
                }
            }, 60000);
        },

        // --- Google Drive Picker ---

        loadGooglePicker: function () {
            $.getScript('https://apis.google.com/js/api.js', function () {
                gapi.load('picker', {
                    'callback': function () {
                        // Picker API loaded
                        // We also need client library for auth if we need to get a token?
                        // Actually, for Picker we need an OAuth token.
                        // We can use gapi.client to get it.
                    }
                });

                gapi.load('client', function () {
                    gapi.client.init({
                        'clientId': cqaFrontend.googleClientId,
                        'scope': 'https://www.googleapis.com/auth/drive.file'
                    });
                });
            });
        },

        handleDriveClick: function (e) {
            e.preventDefault();

            // Check if we have an access token
            const token = gapi.client.getToken();
            if (token) {
                this.createPicker(token.access_token);
            } else {
                // Request auth
                gapi.auth2.getAuthInstance().signIn().then(function () {
                    const newToken = gapi.client.getToken();
                    CQA.createPicker(newToken.access_token);
                });
            }
        },

        createPicker: function (oauthToken) {
            if (this.pickerApiLoaded && oauthToken) {
                const picker = new google.picker.PickerBuilder()
                    .addView(google.picker.ViewId.DOCS)
                    .addView(google.picker.ViewId.PHOTOS)
                    .setOAuthToken(oauthToken)
                    .setDeveloperKey(cqaFrontend.developerKey)
                    .setCallback(this.pickerCallback.bind(this))
                    .build();
                picker.setVisible(true);
            }
        },

        pickerCallback: function (data) {
            if (data[google.picker.Response.ACTION] == google.picker.Action.PICKED) {
                const doc = data[google.picker.Response.DOCUMENTS][0];
                const fileId = doc[google.picker.Document.ID];
                const url = doc[google.picker.Document.URL];
                const name = doc[google.picker.Document.NAME];
                const icon = doc[google.picker.Document.ICON_URL];

                // Add to gallery
                const html = `
                    <div class="cqa-photo-thumb drive-file">
                        <img src="${icon}" alt="${name}" style="object-fit: contain; padding: 10px;">
                        <input type="hidden" name="drive_files[]" value="${fileId}">
                        <div class="photo-caption">${name}</div>
                    </div>
                `;
                $('#cqa-photo-gallery').append(html);
            }
        }
    };

    $(document).ready(function () {
        CQA.init();
    });

})(jQuery);
