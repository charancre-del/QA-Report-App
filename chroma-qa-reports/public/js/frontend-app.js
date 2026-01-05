/**
 * Chroma QA Reports - Frontend Application
 * 
 * Handles report wizard, photo uploads, and form submission.
 * 
 * @global cqaFrontend
 * @global jQuery
 * @global gapi
 * @global google
 */
(function () {
    'use strict';

    // Wait for jQuery to be available (handles deferred loading)
    function initApp($) {
        console.log('CQA: Frontend App Loading... jQuery v' + $.fn.jquery);

        if (typeof cqaFrontend === 'undefined') {
            console.error('CQA Error: cqaFrontend config object is missing.');
            return;
        }

        const CQA = {
            init: function () {
                // Global events (Dashboard, Reports List, etc.)
                this.bindGlobalEvents();

                // Initialize login form if present (login page doesn't need wizard features)
                if ($('#cqa-login-form').length) {
                    this.initLogin();
                }

                // Check if we're on the wizard page before initializing wizard features
                if ($('#cqa-report-wizard').length) {
                    this.cacheDOM();
                    this.bindWizardEvents();
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
                }
            },

            bindGlobalEvents: function () {
                // Delete Report (Available on Dashboard and List)
                $(document).on('click', '.cqa-delete-report', this.handleDelete.bind(this));
            },

            // Public method for inline calls (Failsafe)
            deleteReport: function (id) {
                // Trigger the logic manually by finding the button and mocking the event
                const $btn = $(`.cqa-delete-report[data-id="${id}"]`);
                if ($btn.length) {
                    const fakeEvent = { preventDefault: () => { }, currentTarget: $btn[0] };
                    this.handleDelete(fakeEvent);
                } else {
                    console.error('CQA: Delete button not found for ID', id);
                }
            },

            initLogin: function () {
                const $form = $('#cqa-login-form');
                const $error = $('#cqa-login-error');

                $form.on('submit', function (e) {
                    e.preventDefault();

                    const $btn = $form.find('button[type="submit"]');

                    $btn.prop('disabled', true);
                    $btn.find('.cqa-btn-text').hide();
                    $btn.find('.cqa-btn-loading').show();
                    $error.hide();

                    $.ajax({
                        url: cqaFrontend.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'cqa_frontend_login',
                            username: $('#username').val(),
                            password: $('#password').val(),
                            remember: $('input[name="remember"]').is(':checked') ? 1 : 0,
                            nonce: $('input[name="nonce"]').val()
                        },
                        success: function (response) {
                            if (response.success) {
                                window.location.href = response.data.redirect;
                            } else {
                                $error.text(response.data.message).show();
                                $btn.prop('disabled', false);
                                $btn.find('.cqa-btn-text').show();
                                $btn.find('.cqa-btn-loading').hide();
                            }
                        },
                        error: function () {
                            $error.text(cqaFrontend.strings.error).show();
                            $btn.prop('disabled', false);
                            $btn.find('.cqa-btn-text').show();
                            $btn.find('.cqa-btn-loading').hide();
                        }
                    });
                });
            },

            cacheDOM: function () {
                this.$wizard = $('#cqa-report-wizard');
                this.$form = $('#cqa-report-form');
                this.$steps = $('.cqa-wizard-step');
                this.$panels = $('.cqa-wizard-panel');
                this.$prevBtn = $('.cqa-wizard-prev');
                this.$nextBtn = $('.cqa-wizard-next');
                this.$submitBtn = $('#cqa-submit-report');
                this.$saveDraftBtn = $('#cqa-save-draft');
                this.$schoolSelect = $('#cqa-school-select');
                this.$driveBtn = $('.cqa-drive-picker-btn'); // Add class to your button HTML
            },

            bindWizardEvents: function () {
                // Navigation with delegation
                $(document).on('click', '.cqa-wizard-next', this.nextStep.bind(this));
                $(document).on('click', '.cqa-wizard-prev', this.prevStep.bind(this));

                // Save Draft (Delegation for multiple buttons)
                $(document).on('click', '.cqa-save-draft-btn', this.saveDraft.bind(this));

                this.$schoolSelect.on('change', this.handleSchoolChange.bind(this));
                this.$form.on('submit', this.handleSubmit.bind(this));

                // Submit button - manually trigger form submit
                this.$submitBtn.on('click', function (e) {
                    e.preventDefault();
                    $('#cqa-submit-report').closest('form').trigger('submit');
                });

                // AI Summary button
                $('#cqa-generate-summary').on('click', this.generateAISummary.bind(this));

                // Rating buttons
                $(document).on('click', '.cqa-rating-btn', this.handleOverallRating.bind(this));
                $(document).on('click', '.cqa-item-rating-btn', this.handleItemRating.bind(this));

                // Checklist navigation
                $(document).on('click', '.cqa-checklist-section-header', this.toggleSection.bind(this));

                // Google Drive delegation
                $(document).on('click', '#cqa-drive-picker-btn', this.handleDriveClick.bind(this));
            },


            initWizard: function () {
                this.currentStep = 1;
                this.totalSteps = this.$panels.length;
                this.updateButtons();

                // Show first panel
                $(`.cqa-wizard-panel[data-step="${this.currentStep}"]`).addClass('active');

                // Check for imported data
                this.checkImportedData();
                this.checkDuplicateAction();
            },

            initRatings: function () {
                // Initialize Overall Rating
                const overallVal = $('#cqa-overall-rating').val();
                if (overallVal) {
                    $(`.cqa-rating-btn[data-rating="${overallVal}"]`).addClass('selected');
                }
            },

            checkDuplicateAction: function () {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('action') === 'duplicate' && urlParams.get('id')) {
                    const id = urlParams.get('id');

                    // Fetch report data
                    $.ajax({
                        url: cqaFrontend.restUrl + 'reports/' + id,
                        method: 'GET',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
                        }
                    }).done((response) => {
                        // Transform response to match wizard expectation
                        // The 'response' object has school_id, inspection_date, etc.
                        // We need to fetch responses separately or use include_details=true if backend supports it.
                        // The REST controller 'get_report' supports 'include_details' implicitly or we need to check.
                        // Looking at class-rest-controller.php: prepare_report_response( $report, true ) is called for get_report.
                        // So response includes 'responses' and 'closing_notes'.

                        const data = {
                            school_name: response.school ? response.school.name : '', // Wizard expects school name or we set ID
                            inspection_date: new Date().toISOString().split('T')[0], // Reset date for new report
                            report_type: response.report_type,
                            closing_notes: response.closing_notes,
                            responses: {}
                        };

                        // Transform responses structure if needed
                        if (response.responses) {
                            // API returns grouped by section. 
                            // Wizard populateWizard expects { section: { item: { rating: ... } } }
                            data.responses = response.responses;
                        }

                        // Set School ID directly if possible
                        if (response.school_id) {
                            this.$schoolSelect.val(response.school_id).trigger('change');
                        }

                        // Populate
                        this.populateWizard(data);
                        alert('Report duplicated! Date has been reset to today.');
                    }).fail((xhr) => {
                        alert('Failed to load report for duplication.');
                    });
                }
            },

            checkImportedData: function () {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('action') === 'import') {
                    const data = sessionStorage.getItem('cqa_imported_data');
                    if (data) {
                        try {
                            const parsed = JSON.parse(data);
                            // Delay slightly to ensure DOM is ready and listeners bound
                            setTimeout(() => {
                                this.populateWizard(parsed);
                                sessionStorage.removeItem('cqa_imported_data');
                                alert('Report data imported successfully! Please review all fields.');
                            }, 500);
                        } catch (e) {
                            console.error('Import error', e);
                        }
                    }
                }
            },

            populateWizard: function (data) {
                // 1. Basic Info
                if (data.school_name) {
                    // Try to match school name in select
                    const $option = this.$schoolSelect.find('option').filter(function () {
                        return $(this).text().toLowerCase().includes(data.school_name.toLowerCase());
                    });
                    if ($option.length) {
                        this.$schoolSelect.val($option.val()).trigger('change');
                    }
                }

                if (data.inspection_date) {
                    $('#inspection_date').val(data.inspection_date);
                }

                if (data.report_type) {
                    // Map AI return values to exact values if slightly off
                    let type = data.report_type.toLowerCase();
                    if (type.includes('tier 2') || type.includes('tier1_tier2')) type = 'tier1_tier2';
                    else if (type.includes('tier 1') || type.includes('tier1')) type = 'tier1';
                    else if (type.includes('new') || type.includes('acquisition')) type = 'new_acquisition';

                    $(`input[name="report_type"][value="${type}"]`).prop('checked', true);
                }

                // 2. Responses (requires checklist to be loaded, but we are on step 1)
                // We'll store responses in a temporary variable and apply them when step 2 loads
                // OR we just force load step 2 if we are confident.
                // Better: Store in the form data so when we go to step 2, we can pre-fill.
                // Since step 2 generates HTML dynamically, we need to wait until nextStep is called.

                if (data.responses) {
                    this.importedResponses = data.responses;
                    // Hook into loadChecklist or nextStep to apply these
                    // We'll modify loadChecklist key
                }

                if (data.closing_notes) {
                    $('#closing_notes').val(data.closing_notes); // Step 3
                }
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

            nextStep: function (e) {
                if (e) e.preventDefault();

                if (this.validateStep(this.currentStep)) {
                    this.$panels.removeClass('active');
                    this.currentStep++;
                    $(`.cqa-wizard-panel[data-step="${this.currentStep}"]`).addClass('active');
                    this.updateButtons();
                    window.scrollTo(0, 0);

                    // Load checklist if entering step 2
                    if (this.currentStep === 2) {
                        this.loadChecklist();
                        // Auto-create draft if not exists to enable autosave
                        if (!this.$wizard.data('report-id')) {
                            this.submitToRestApi('draft', true);
                        }
                    }

                    // Update review if entering final step
                    if (this.currentStep === 4) {
                        this.updateReview();
                    }
                }
            },

            prevStep: function (e) {
                if (e) e.preventDefault();
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
                    const $input = $(this);
                    const val = $input.val();

                    if (!val) {
                        isValid = false;
                        $input.addClass('error');
                        $input.css('border-color', 'var(--cqa-danger)');
                    } else {
                        $input.removeClass('error');
                        $input.css('border-color', '');
                    }
                });

                if (!isValid) {
                    alert('Please fill in all required fields.'); // Better UI later
                }

                return isValid;
            },

            loadChecklist: function () {
                const self = this;
                const reportType = $('#cqa-report-type').val() || 'tier1';
                const $container = $('#cqa-checklist-container');

                $container.html('<div class="cqa-loading">Loading checklist...</div>');

                $.ajax({
                    url: cqaFrontend.restUrl + 'checklists/' + reportType,
                    method: 'GET',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
                    }
                }).done(function (checklist) {
                    self.checklist = checklist;
                    self.renderChecklist(checklist);

                    // IF editing, load saved responses now
                    const reportId = self.$wizard.data('report-id');
                    if (reportId) {
                        self.loadSavedResponses(reportId);
                        self.loadExistingPhotos(reportId);
                        self.loadExistingAISummary(reportId);
                    }
                }).fail(function () {
                    $container.html('<div class="cqa-error">Failed to load checklist. Please try again.</div>');
                });
            },

            loadSavedResponses: function (reportId) {
                const self = this;
                console.log('CQA: Loading saved responses for report', reportId);

                $.ajax({
                    url: cqaFrontend.restUrl + 'reports/' + reportId + '/responses',
                    method: 'GET',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
                    }
                }).done(function (responses) {
                    console.log('CQA: Saved responses loaded', responses);

                    // Iterate and fill responses
                    $.each(responses, function (sectionKey, items) {
                        $.each(items, function (itemKey, data) {
                            if (data.rating) {
                                const $container = $(`.cqa-checklist-item[data-section="${sectionKey}"][data-item="${itemKey}"]`);
                                const $btn = $container.find(`.cqa-item-rating-btn[data-value="${data.rating}"]`);

                                if ($btn.length) {
                                    $btn.addClass('selected').siblings().removeClass('selected');
                                    $container.attr('data-validation', 'valid');
                                }
                            }

                            if (data.notes) {
                                const $container = $(`.cqa-checklist-item[data-section="${sectionKey}"][data-item="${itemKey}"]`);
                                $container.find('textarea').val(data.notes);
                            }
                        });
                    });
                }).fail(function (xhr) {
                    console.error('CQA: Failed to load saved responses', xhr);
                });
            },

            loadExistingPhotos: function (reportId) {
                const self = this;
                const $gallery = $('#cqa-photo-gallery');

                // Get available sections from checklist
                const sections = self.checklist ? self.checklist.sections.map(s => ({ key: s.key, name: s.name })) : [];

                // Build section options HTML
                let sectionOptions = '<option value="general">General</option>';
                sections.forEach(s => {
                    sectionOptions += `<option value="${s.key}">${s.name}</option>`;
                });

                $.ajax({
                    url: cqaFrontend.restUrl + 'reports/' + reportId,
                    method: 'GET',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
                    }
                }).done(function (report) {
                    if (report.photos && report.photos.length > 0) {
                        report.photos.forEach(function (photo) {
                            // Skip item photos (they have pipes in section_key)
                            if (photo.section_key && photo.section_key.indexOf('|') > -1) {
                                return;
                            }

                            const currentSection = photo.section_key || 'general';
                            const currentCaption = photo.caption || '';

                            const html = `
                                <div class="cqa-photo-thumb cqa-existing-photo" data-photo-id="${photo.id}" style="display:inline-block; margin:8px; vertical-align:top; width:180px; position:relative; background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
                                    <input type="hidden" name="existing_photos[]" value="${photo.id}">
                                    <img src="${photo.thumbnail_url}" alt="Photo" style="width:100%; height:100px; object-fit:cover;">
                                    <div style="padding:8px;">
                                        <select name="photo_sections[${photo.id}]" style="width:100%; padding:4px; font-size:11px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:6px;">
                                            ${sectionOptions.replace(`value="${currentSection}"`, `value="${currentSection}" selected`)}
                                        </select>
                                        <input type="text" name="photo_captions[${photo.id}]" value="${currentCaption}" placeholder="Caption (optional)"
                                            style="width:100%; padding:4px; font-size:11px; border:1px solid #d1d5db; border-radius:4px; box-sizing:border-box;">
                                    </div>
                                    <button type="button" class="cqa-remove-photo-btn" data-id="${photo.id}" 
                                        style="position:absolute; top:4px; right:4px; background:#ef4444; color:white; border:none; border-radius:50%; width:20px; height:20px; cursor:pointer; font-size:12px;">×</button>
                                </div>
                            `;
                            $gallery.append(html);
                        });
                    }
                });
            },

            renderChecklist: function (checklist) {
                const self = this;
                let html = '';

                checklist.sections.forEach(function (section) {
                    const tierBadge = section.tier === 2 ? '<span class="cqa-tier-badge">Tier 2</span>' : '';

                    html += `
                    <div class="cqa-checklist-section">
                        <div class="cqa-checklist-section-header">
                            <h3>${tierBadge} ${section.name}</h3>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        <div class="cqa-checklist-section-body">
                            ${section.description ? `<p class="cqa-section-desc">${section.description}</p>` : ''}
                            <div class="cqa-section-items">
                `;

                    section.items.forEach(function (item) {
                        html += self.renderChecklistItem(section.key, item);
                    });

                    // Section Photo Upload Button
                    const sectionLabel = section.name.replace(/[^a-zA-Z0-9]/g, '_');
                    html += `
                            </div>
                            <div class="cqa-section-photo-upload" style="margin-top: 12px; padding: 12px; background: #f9fafb; border-radius: 8px; border: 1px dashed #d1d5db;">
                                <input type="file" class="cqa-section-photo-input" data-section="${section.key}" accept="image/*" multiple style="display: none;">
                                <button type="button" class="cqa-btn cqa-btn-sm cqa-section-photo-btn" data-section="${section.key}" style="font-size: 12px;">
                                    📷 Add ${section.name} Photo
                                </button>
                                <span style="font-size: 11px; color: #6b7280; margin-left: 8px;">Photos for this area</span>
                            </div>
                        </div>
                    </div>
                `;
                });

                $('#cqa-checklist-container').html(html);
            },

            renderChecklistItem: function (sectionKey, item) {
                // Check for existing values if we are editing (needs implementation for storage check)
                // For now, simple render
                const name = `responses[${sectionKey}][${item.key}]`;
                const photoInputId = `photo-${sectionKey}-${item.key}`;

                return `
                <div class="cqa-checklist-item" data-section="${sectionKey}" data-item="${item.key}">
                    <span class="cqa-item-label">${item.label}</span>
                    <div class="cqa-item-ratings">
                        <button type="button" class="cqa-item-rating-btn rating-yes" data-value="yes" title="Yes">✓</button>
                        <button type="button" class="cqa-item-rating-btn rating-sometimes" data-value="sometimes" title="Sometimes">~</button>
                        <button type="button" class="cqa-item-rating-btn rating-no" data-value="no" title="No">✗</button>
                        <button type="button" class="cqa-item-rating-btn rating-na selected" data-value="na" title="Not Applicable" style="min-width: 50px;">N/A</button>
                        <input type="hidden" name="${name}[rating]" value="na">
                    </div>
                    <div class="cqa-item-notes" style="width: 100%; margin-top: 12px;">
                        <textarea name="${name}[notes]" placeholder="Add notes..." style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; min-height: 60px;"></textarea>
                        <div style="margin-top: 8px;">
                            <input type="file" id="${photoInputId}" class="cqa-item-photo-input" data-section="${sectionKey}" data-item="${item.key}" accept="image/*" multiple style="display: none;">
                            <button type="button" class="cqa-btn cqa-btn-sm" onclick="document.getElementById('${photoInputId}').click();" style="font-size: 12px;">📷 Attach Photo</button>
                            <span style="font-size: 12px; color: #6b7280; margin-left: 8px;">Optional evidence</span>
                        </div>
                    </div>
                </div>
            `;
            },

            updateReview: function () {
                // Count stats
                let total = 0, yes = 0, no = 0, sometimes = 0;
                $('.cqa-item-ratings input[type="hidden"]').each(function () {
                    const val = $(this).val();
                    if (val && val !== 'na') {
                        total++;
                        if (val === 'yes') yes++;
                        else if (val === 'no') no++;
                        else if (val === 'sometimes') sometimes++;
                    }
                });

                const html = `
                <div class="cqa-review-stats">
                    <p><strong>School:</strong> ${$('#cqa-school-select option:selected').text()}</p>
                    <p><strong>Date:</strong> ${$('#cqa-inspection-date').val()}</p>
                    <p><strong>Checklist Items:</strong> ${total} Rated</p>
                    <ul>
                        <li>✅ Yes: ${yes}</li>
                        <li>⚠️ Sometimes: ${sometimes}</li>
                        <li>✗ No: ${no}</li>
                    </ul>
                    <p><strong>Photos:</strong> ${$('#cqa-photo-gallery .cqa-photo-thumb').length}</p>
                </div>
            `;
                $('#cqa-review-summary').html(html);
            },

            handleSchoolChange: function (e) {
                const schoolId = $(e.target).val();
                // Removed auto-reload to prevent losing other form data
                // School ID is captured by form submission
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

                // File input (Main)
                $('#cqa-photo-input').on('change', function (e) {
                    CQA.handleFiles(this.files);
                });

                // File input (Per Item) - Delegated
                $(document).on('change', '.cqa-item-photo-input', function (e) {
                    console.log('Photo input changed', this.files);
                    const section = $(this).data('section');
                    const item = $(this).data('item');
                    console.log('Section:', section, 'Item:', item);
                    if (this.files && this.files.length > 0) {
                        CQA.handleItemFiles(this, this.files, section, item);
                    }
                });

                // Section Photo Button - Click to trigger file input
                $(document).on('click', '.cqa-section-photo-btn', function (e) {
                    e.preventDefault();
                    const sectionKey = $(this).data('section');
                    $(this).siblings('.cqa-section-photo-input').trigger('click');
                });

                // Section Photo Input - Handle file selection
                $(document).on('change', '.cqa-section-photo-input', function (e) {
                    const sectionKey = $(this).data('section');
                    if (this.files && this.files.length > 0) {
                        CQA.handleSectionFiles(this, this.files, sectionKey);
                    }
                });

                // Photo Remove Button - Delete photo
                $(document).on('click', '.cqa-remove-photo-btn', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const $btn = $(this);
                    const photoId = $btn.data('id');
                    const $thumb = $btn.closest('.cqa-photo-thumb, .cqa-existing-photo');

                    if (photoId && confirm('Remove this photo?')) {
                        // Mark existing photo for deletion
                        $thumb.find('input[name="existing_photos[]"]').remove();
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'delete_photos[]',
                            value: photoId
                        }).appendTo($thumb.parent());
                    }

                    // Remove from UI
                    $thumb.fadeOut(200, function () { $(this).remove(); });
                });
            },

            handleFiles: function (files, sectionKey) {
                console.log('Handling files', files, 'section:', sectionKey);
                const self = this;
                const $gallery = $('#cqa-photo-gallery');
                const photoId = Date.now();

                // Get available sections from checklist
                const sections = self.checklist ? self.checklist.sections.map(s => ({ key: s.key, name: s.name })) : [];

                // Build section options HTML
                let sectionOptions = '<option value="general">General</option>';
                sections.forEach(s => {
                    const selected = (sectionKey === s.key) ? 'selected' : '';
                    sectionOptions += `<option value="${s.key}" ${selected}>${s.name}</option>`;
                });
                // Select "general" if no section provided
                if (!sectionKey) {
                    sectionOptions = sectionOptions.replace('value="general"', 'value="general" selected');
                }

                Array.from(files).forEach((file, idx) => {
                    if (file.type.startsWith('image/')) {
                        self.compressImage(file, function (compressedDataUrl) {
                            const uniqueId = photoId + '_' + idx;
                            const html = `
                            <div class="cqa-photo-thumb cqa-new-photo" style="display:inline-block; margin:8px; vertical-align:top; width:180px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; position:relative;">
                                <img src="${compressedDataUrl}" alt="Preview" style="width:100%; height:100px; object-fit:cover;">
                                <input type="hidden" name="new_photos[]" value="${compressedDataUrl}">
                                <div style="padding:8px;">
                                    <select name="new_photos_sections[]" style="width:100%; padding:4px; font-size:11px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:6px;">
                                        ${sectionOptions}
                                    </select>
                                    <input type="text" name="new_photos_captions[]" placeholder="Caption (optional)" 
                                        style="width:100%; padding:4px; font-size:11px; border:1px solid #d1d5db; border-radius:4px; box-sizing:border-box;">
                                </div>
                                <button type="button" class="cqa-remove-new-photo-btn" 
                                    style="position:absolute; top:4px; right:4px; background:#ef4444; color:white; border:none; border-radius:50%; width:20px; height:20px; cursor:pointer; font-size:12px;">×</button>
                            </div>
                        `;
                            $gallery.append(html);
                        });
                    }
                });
            },

            handleItemFiles: function (inputElement, files, sectionKey, itemKey) {
                console.log('Processing item files', files.length);
                const self = this;
                const $container = $(inputElement).closest('.cqa-item-notes');
                const photoId = Date.now();

                Array.from(files).forEach((file, idx) => {
                    if (file.type.startsWith('image/')) {
                        console.log('Compressing file:', file.name);
                        self.compressImage(file, function (compressedDataUrl) {
                            console.log('Compression complete, adding preview');
                            const uniqueId = photoId + '_' + idx;
                            const inputName = `item_photos[${sectionKey}][${itemKey}][]`;
                            const captionName = `item_photos_captions[${sectionKey}][${itemKey}][]`;

                            const html = `
                                <div style="display:inline-block; margin: 4px; position:relative; vertical-align: top; width:100px;">
                                    <img src="${compressedDataUrl}" style="height:60px; width:100%; object-fit:cover; border-radius:4px; border:1px solid #ccc;">
                                    <input type="hidden" name="${inputName}" value="${compressedDataUrl}">
                                    <input type="text" name="${captionName}" placeholder="Caption" 
                                        style="width:100%; margin-top:2px; padding:2px; font-size:10px; border:1px solid #ddd; border-radius:3px;">
                                </div>
                             `;
                            $container.append(html);
                        });
                    }
                });

                // Reset button text briefly
                $(inputElement).next('button').text('✅ Added');
                setTimeout(() => {
                    $(inputElement).next('button').text('📷 Attach Photo');
                }, 2000);
            },

            handleSectionFiles: function (inputElement, files, sectionKey) {
                console.log('Processing section files for:', sectionKey);
                // Use the main handleFiles with section key
                this.handleFiles(files, sectionKey);

                // Reset button text briefly
                $(inputElement).siblings('button').text('✅ Added');
                setTimeout(() => {
                    $(inputElement).siblings('button').text('📷 Add ' + sectionKey.replace('_', ' ') + ' Photo');
                }, 2000);
            },

            compressImage: function (file, callback) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = new Image();
                    img.onload = function () {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');

                        // Max dimensions - Reduced to 1024 for reliability
                        let width = img.width;
                        let height = img.height;
                        const maxSize = 1024; // Max width/height

                        if (width > height && width > maxSize) {
                            height = (height / width) * maxSize;
                            width = maxSize;
                        } else if (height > maxSize) {
                            width = (width / height) * maxSize;
                            height = maxSize;
                        }

                        canvas.width = width;
                        canvas.height = height;
                        ctx.drawImage(img, 0, 0, width, height);

                        // Compress to JPEG at 0.7 quality
                        const compressedDataUrl = canvas.toDataURL('image/jpeg', 0.7);
                        callback(compressedDataUrl);
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            },

            handleDelete: function (e) {
                e.preventDefault();
                console.log('CQA: Delete button clicked');

                if (!confirm('Are you sure you want to delete this report? This cannot be undone.')) {
                    console.log('CQA: Delete cancelled by user');
                    return;
                }

                const $btn = $(e.currentTarget);
                const id = $btn.data('id');
                const $card = $btn.closest('.cqa-report-card'); // Use $card naming convention

                console.log('CQA: Deleting report ID:', id);

                $btn.prop('disabled', true).text('🗑️...');

                $.ajax({
                    url: cqaFrontend.restUrl + 'reports/' + id,
                    method: 'DELETE',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
                    }
                }).done(function () {
                    console.log('CQA: Delete success');
                    $card.fadeOut(function () { $(this).remove(); });
                }).fail(function (xhr) {
                    console.error('CQA: Delete failed', xhr);
                    alert('Failed to delete report. Check console for details.');
                    $btn.prop('disabled', false).text('🗑️');
                });
            },

            generateAISummary: function (e) {
                e.preventDefault();
                const self = this;
                const $btn = $(e.currentTarget);
                const originalText = $btn.html();
                const reportId = this.$wizard.data('report-id');

                if (!reportId) {
                    alert('Please save the report as a draft first before generating AI summary.');
                    return;
                }

                $btn.prop('disabled', true).html('🤖 Generating...');

                $.ajax({
                    url: cqaFrontend.restUrl + 'reports/' + reportId + '/generate-summary',
                    method: 'POST',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
                    }
                }).done(function (response) {
                    self.renderAISummary(response);
                    $btn.prop('disabled', false).html(originalText + ' ✓');
                }).fail(function (xhr) {
                    const error = xhr.responseJSON?.message || 'Failed to generate summary. Please try again.';
                    alert('Error: ' + error);
                    $btn.prop('disabled', false).html(originalText);
                });
            },
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

                // NUCLEAR OPTION: Append school_id to Query String to bypass Body Parsing failures
                const safeSchoolId = $('#cqa-school-select').val();
                if (safeSchoolId) {
                    url += (url.includes('?') ? '&' : '?') + 'school_id=' + safeSchoolId;
                    // FALLBACK 3: Cookies (The Triple Nuclear Option)
                    document.cookie = "cqa_temp_school_id=" + safeSchoolId + "; path=/";
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

                // Force School ID from select if missing (Safety check)
                const schoolIdVal = $('#cqa-school-select').val();
                if ((!formData.school_id || formData.school_id == 0) && schoolIdVal) {
                    formData.school_id = schoolIdVal;
                }

                console.log('Submitting Report Payload:', formData); // DEBUG for User

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
                    data: JSON.stringify(formData),
                    contentType: 'application/json'
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
                        this.refreshProgress();

                        // Apply imported responses if any
                        if (this.importedResponses) {
                            $.each(this.importedResponses, (sectionKey, items) => {
                                $.each(items, (itemKey, response) => {
                                    // Rating
                                    if (response.rating) {
                                        let rating = response.rating.toLowerCase();
                                        if (rating === 'yes') rating = 'yes';
                                        else if (rating === 'no') rating = 'no';
                                        else if (rating === 'sometimes') rating = 'sometimes';
                                        else if (rating === 'na' || rating === 'n/a') rating = 'na';

                                        const $input = $(`input[name="responses[${sectionKey}][${itemKey}][rating]"][value="${rating}"]`);
                                        if ($input.length) {
                                            $input.prop('checked', true);
                                            // Trigger visual update
                                            $input.closest('.cqa-rating-group').find('.cqa-item-rating-btn').removeClass('active');
                                            $input.closest('.cqa-item-rating-btn').addClass('active');
                                        }
                                    }
                                    // Notes
                                    if (response.notes) {
                                        $(`textarea[name="responses[${sectionKey}][${itemKey}][notes]"]`).val(response.notes);
                                    }
                                });
                            });
                            this.importedResponses = null; // Clear
                            this.refreshProgress();
                        }
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

                    // Clean name (strip [] for PHP REST compatibility)
                    const name = this.name.replace('[]', '');

                    if (o[name]) {
                        if (!o[name].push) {
                            o[name] = [o[name]];
                        }
                        o[name].push(this.value || '');
                    } else {
                        // If original name had [], initialize as array even if single item
                        if (this.name.includes('[]')) {
                            o[name] = [this.value || ''];
                        } else {
                            o[name] = this.value || '';
                        }
                    }
                });
                return o;
            },

            initAutosave: function () {
                // Simple autosave every 60 seconds
                const self = this;
                setInterval(function () {
                    if (self.$wizard.data('report-id')) {
                        // Silent save
                        // console.log('Autosaving draft...');
                        self.submitToRestApi('draft', true);
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
            window.CQA = CQA; // Expose global API
            CQA.init();
        });

    } // End of initApp function

    // Initialize app when jQuery is available
    if (typeof jQuery !== 'undefined') {
        initApp(jQuery);
    } else {
        // jQuery not loaded yet - wait for it
        document.addEventListener('DOMContentLoaded', function checkjQuery() {
            if (typeof jQuery !== 'undefined') {
                initApp(jQuery);
            } else {
                // Poll for jQuery (handles deferred loading)
                var attempts = 0;
                var interval = setInterval(function () {
                    if (typeof jQuery !== 'undefined') {
                        clearInterval(interval);
                        initApp(jQuery);
                    } else if (++attempts > 50) {
                        clearInterval(interval);
                        console.error('CQA Reports: jQuery failed to load');
                    }
                }, 100);
            }
        });
    }

})();
