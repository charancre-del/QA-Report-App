/**
 * Chroma QA Reports - Report Wizard (Enhanced)
 *
 * Multi-step report creation wizard with autosave, camera capture, and photo enhancements
 *
 * @package ChromaQAReports
 */

(function ($) {
    'use strict';

    var Wizard = {
        currentStep: 1,
        totalSteps: 5,
        reportId: null,
        reportData: {},
        checklist: null,
        autosaveTimer: null,
        autosaveInterval: 30000, // 30 seconds
        debounceTimer: null,
        debounceDelay: 2000, // 2 seconds
        isDirty: false,
        isSaving: false,
        photos: [],

        /**
         * Initialize the wizard
         */
        init: function () {
            this.reportId = $('#report_id').val() || null;
            this.bindEvents();
            this.loadSchoolReports();
            this.initAutosave();
            this.initPhotoUploader();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            var self = this;

            // Navigation buttons
            $('#next-step').on('click', function () {
                self.nextStep();
            });

            $('#prev-step').on('click', function () {
                self.prevStep();
            });

            // Save draft
            $('#save-draft').on('click', function () {
                self.saveDraft();
            });

            // Submit report
            $('#cqa-report-form').on('submit', function (e) {
                e.preventDefault();
                self.submitReport();
            });

            // School selection change
            $('#school_id').on('change', function () {
                self.loadSchoolReports();
                self.markDirty();
            });

            // Report type change
            $('input[name="report_type"]').on('change', function () {
                self.markDirty();
            });

            // Generate AI summary
            $('#generate-summary-btn').on('click', function () {
                self.generateAISummary();
            });

            // Track form changes for autosave
            $(document).on('change', '#cqa-report-form input, #cqa-report-form select, #cqa-report-form textarea', function () {
                self.markDirty();
                self.debouncedSave();
            });

            // Warn before leaving with unsaved changes
            $(window).on('beforeunload', function () {
                if (self.isDirty && !self.isSaving) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        },

        /**
         * Initialize autosave functionality
         */
        initAutosave: function () {
            var self = this;

            // Auto-save every 30 seconds
            this.autosaveTimer = setInterval(function () {
                if (self.isDirty && !self.isSaving) {
                    self.autosave();
                }
            }, this.autosaveInterval);

            // Add autosave status indicator
            $('.cqa-wizard-nav').append(
                '<div class="cqa-autosave-status" id="autosave-status">' +
                '<span class="status-icon"></span>' +
                '<span class="status-text"></span>' +
                '</div>'
            );
        },

        /**
         * Mark form as dirty (has unsaved changes)
         */
        markDirty: function () {
            this.isDirty = true;
            this.updateAutosaveStatus('unsaved');
        },

        /**
         * Debounced save - saves after user stops typing
         */
        debouncedSave: function () {
            var self = this;

            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            this.debounceTimer = setTimeout(function () {
                if (self.isDirty && !self.isSaving) {
                    self.autosave();
                }
            }, this.debounceDelay);
        },

        /**
         * Autosave function (silent save)
         */
        autosave: function () {
            var self = this;

            if (this.isSaving) return;

            // Must have at least school selected
            if (!$('#school_id').val()) return;

            this.isSaving = true;
            this.updateAutosaveStatus('saving');

            // Collect all data
            this.collectStepData(1);
            this.collectStepData(2);
            this.collectStepData(5);

            var data = this.reportData;
            data.status = 'draft';

            var endpoint = this.reportId ? 'reports/' + this.reportId : 'reports';
            var method = this.reportId ? 'put' : 'post';

            CQA.api[method](endpoint, data).done(function (report) {
                self.reportId = report.id;
                $('#report_id').val(report.id);

                // Save responses
                if (data.responses && Object.keys(data.responses).length > 0) {
                    CQA.api.post('reports/' + report.id + '/responses', { responses: data.responses });
                }

                self.isDirty = false;
                self.updateAutosaveStatus('saved');
            }).fail(function () {
                self.updateAutosaveStatus('error');
            }).always(function () {
                self.isSaving = false;
            });
        },

        /**
         * Update autosave status indicator
         */
        updateAutosaveStatus: function (status) {
            var $status = $('#autosave-status');
            var statusText = '';
            var statusClass = '';

            switch (status) {
                case 'saving':
                    statusText = 'Saving...';
                    statusClass = 'saving';
                    break;
                case 'saved':
                    statusText = 'All changes saved';
                    statusClass = 'saved';
                    break;
                case 'unsaved':
                    statusText = 'Unsaved changes';
                    statusClass = 'unsaved';
                    break;
                case 'error':
                    statusText = 'Save failed';
                    statusClass = 'error';
                    break;
            }

            $status.removeClass('saving saved unsaved error').addClass(statusClass);
            $status.find('.status-text').text(statusText);
        },

        /**
         * Initialize photo uploader with camera support
         */
        initPhotoUploader: function () {
            var self = this;

            // Create hidden file inputs
            var $fileInput = $('<input type="file" id="photo-file-input" accept="image/*" multiple style="display:none">');
            var $cameraInput = $('<input type="file" id="camera-input" accept="image/*" capture="environment" style="display:none">');

            $('body').append($fileInput).append($cameraInput);

            // Select photos button
            $('#select-photos-btn').on('click', function () {
                $fileInput.click();
            });

            // Camera capture button
            $('#camera-capture-btn').on('click', function () {
                $cameraInput.click();
            });

            // File input change
            $fileInput.on('change', function () {
                self.handlePhotoSelection(this.files);
                $(this).val(''); // Reset for next selection
            });

            // Camera input change
            $cameraInput.on('change', function () {
                self.handlePhotoSelection(this.files);
                $(this).val('');
            });

            // Drag and drop
            var $dropArea = $('#photo-upload-area');

            $dropArea.on('dragover dragenter', function (e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            });

            $dropArea.on('dragleave drop', function (e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
            });

            $dropArea.on('drop', function (e) {
                var files = e.originalEvent.dataTransfer.files;
                self.handlePhotoSelection(files);
            });
        },

        /**
         * Handle photo selection
         */
        handlePhotoSelection: function (files) {
            var self = this;

            for (var i = 0; i < files.length; i++) {
                var file = files[i];

                if (!file.type.startsWith('image/')) {
                    continue;
                }

                this.uploadPhoto(file);
            }
        },

        /**
         * Upload a single photo
         */
        uploadPhoto: function (file) {
            var self = this;
            var photoId = 'photo-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);

            // Create preview element with progress
            var $preview = $(
                '<div class="cqa-photo-item" id="' + photoId + '">' +
                '<div class="photo-preview">' +
                '<div class="photo-progress"><div class="progress-bar"></div></div>' +
                '</div>' +
                '<div class="photo-details">' +
                '<input type="text" class="photo-caption" placeholder="Add caption...">' +
                '<select class="photo-section">' +
                '<option value="">Select section...</option>' +
                '</select>' +
                '<button type="button" class="photo-remove" title="Remove">&times;</button>' +
                '</div>' +
                '</div>'
            );

            $('#photo-gallery').append($preview);

            // Read file for preview
            var reader = new FileReader();
            reader.onload = function (e) {
                $preview.find('.photo-preview').css('background-image', 'url(' + e.target.result + ')');
            };
            reader.readAsDataURL(file);

            // Populate section dropdown
            if (this.checklist && this.checklist.sections) {
                var $select = $preview.find('.photo-section');
                this.checklist.sections.forEach(function (section) {
                    $select.append('<option value="' + section.key + '">' + section.name + '</option>');
                });
            }

            // Upload to server
            var formData = new FormData();
            formData.append('photo', file);
            formData.append('report_id', this.reportId || 0);

            $.ajax({
                url: cqaAdmin.restUrl + 'photos',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-WP-Nonce': cqaAdmin.nonce
                },
                xhr: function () {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            var percent = (e.loaded / e.total) * 100;
                            $preview.find('.progress-bar').css('width', percent + '%');
                        }
                    });
                    return xhr;
                }
            }).done(function (response) {
                $preview.find('.photo-progress').fadeOut();
                $preview.addClass('uploaded');
                $preview.data('photoId', response.id);


                // Add to photos array
                self.photos.push({
                    id: response.id,
                    elementId: photoId,
                    driveFileId: response.drive_file_id
                });

                self.markDirty();
            }).fail(function () {
                $preview.addClass('upload-failed');
                $preview.find('.photo-progress').html('<span class="error">Upload failed</span>');
            });

            // Remove button handler
            $preview.find('.photo-remove').on('click', function () {
                var photoDbId = $preview.data('photoId');

                if (photoDbId) {
                    CQA.api.delete('photos/' + photoDbId);
                    self.photos = self.photos.filter(function (p) { return p.id !== photoDbId; });
                }

                $preview.fadeOut(function () { $(this).remove(); });
            });

            // Caption and section change handlers
            $preview.find('.photo-caption, .photo-section').on('change', function () {
                var photoDbId = $preview.data('photoId');
                if (photoDbId) {
                    CQA.api.put('photos/' + photoDbId, {
                        caption: $preview.find('.photo-caption').val(),
                        section_key: $preview.find('.photo-section').val()
                    });
                }
            });
        },

        /**
         * Go to next step
         */
        nextStep: function () {
            if (this.currentStep >= this.totalSteps) return;

            // Validate current step
            if (!this.validateStep(this.currentStep)) {
                return;
            }

            // Save current step data
            this.collectStepData(this.currentStep);

            // If moving to step 2, load checklist
            if (this.currentStep === 1) {
                this.loadChecklist();
            }

            this.currentStep++;
            this.updateView();
        },

        /**
         * Go to previous step
         */
        prevStep: function () {
            if (this.currentStep <= 1) return;

            this.currentStep--;
            this.updateView();
        },

        /**
         * Update the wizard view
         */
        updateView: function () {
            // Update panels
            $('.cqa-wizard-panel').removeClass('active');
            $('.cqa-wizard-panel[data-step="' + this.currentStep + '"]').addClass('active');

            // Update step indicators
            $('.cqa-step').removeClass('active completed');
            $('.cqa-step').each(function () {
                var step = parseInt($(this).data('step'));
                if (step < Wizard.currentStep) {
                    $(this).addClass('completed');
                } else if (step === Wizard.currentStep) {
                    $(this).addClass('active');
                }
            });

            // Update navigation
            $('#prev-step').prop('disabled', this.currentStep === 1);

            if (this.currentStep === this.totalSteps) {
                $('#next-step').hide();
                $('#submit-report').show();
            } else {
                $('#next-step').show();
                $('#submit-report').hide();
            }

            // Update progress
            $('#current-step').text(this.currentStep);

            // Special handling for review step
            if (this.currentStep === 5) {
                this.updateReviewSummary();
            }
        },

        /**
         * Validate current step
         */
        validateStep: function (step) {
            switch (step) {
                case 1:
                    if (!$('#school_id').val()) {
                        CQA.notify.error('Please select a school.');
                        return false;
                    }
                    if (!$('input[name="report_type"]:checked').val()) {
                        CQA.notify.error('Please select a report type.');
                        return false;
                    }
                    if (!$('#inspection_date').val()) {
                        CQA.notify.error('Please enter the inspection date.');
                        return false;
                    }
                    return true;

                case 2:
                    // Checklist can be partially completed
                    return true;

                case 3:
                    // Photos are optional
                    return true;

                case 4:
                    // AI summary is optional
                    return true;

                default:
                    return true;
            }
        },

        /**
         * Collect data from current step
         */
        collectStepData: function (step) {
            switch (step) {
                case 1:
                    this.reportData.school_id = $('#school_id').val();
                    this.reportData.report_type = $('input[name="report_type"]:checked').val();
                    this.reportData.inspection_date = $('#inspection_date').val();
                    this.reportData.previous_report_id = $('#previous_report_id').val() || null;
                    break;

                case 2:
                    this.reportData.responses = this.collectChecklistResponses();
                    break;

                case 5:
                    this.reportData.overall_rating = $('input[name="overall_rating"]:checked').val();
                    this.reportData.closing_notes = $('#closing_notes').val();
                    break;
            }
        },

        /**
         * Collect checklist responses
         */
        collectChecklistResponses: function () {
            var responses = {};

            $('.cqa-checklist-item').each(function () {
                var $item = $(this);
                var sectionKey = $item.data('section');
                var itemKey = $item.data('item');
                var rating = $item.find('input[name$="[rating]"]:checked').val() || 'na';
                var notes = $item.find('textarea[name$="[notes]"]').val() || '';

                if (!responses[sectionKey]) {
                    responses[sectionKey] = {};
                }

                responses[sectionKey][itemKey] = {
                    rating: rating,
                    notes: notes
                };
            });

            return responses;
        },

        /**
         * Load checklist based on report type
         */
        loadChecklist: function () {
            var self = this;
            var type = this.reportData.report_type || 'tier1';

            $('#checklist-content').html('<div class="cqa-loading"><span class="spinner is-active"></span> Loading checklist...</div>');

            CQA.api.get('checklists/' + type).done(function (checklist) {
                self.checklist = checklist;
                self.renderChecklist(checklist);
            }).fail(function () {
                CQA.notify.error('Failed to load checklist.');
            });
        },

        /**
         * Render checklist HTML
         */
        renderChecklist: function (checklist) {
            var self = this;
            var html = '';
            var navHtml = '';

            checklist.sections.forEach(function (section, index) {
                var tierBadge = section.tier === 2 ? '<span class="cqa-tier-badge">Tier 2</span>' : '';

                navHtml += '<li><a href="#section-' + section.key + '" class="' + (index === 0 ? 'active' : '') + '">' +
                    tierBadge + section.name + '</a></li>';

                html += '<div class="cqa-section" id="section-' + section.key + '">';
                html += '<h3>' + tierBadge + section.name + '</h3>';

                if (section.description) {
                    html += '<p class="section-description">' + section.description + '</p>';
                }

                section.items.forEach(function (item) {
                    html += self.renderChecklistItem(section.key, item);
                });

                html += '</div>';
            });

            $('#section-nav-list').html(navHtml);
            $('#checklist-content').html(html);

            // Bind section navigation
            $('#section-nav-list a').on('click', function (e) {
                e.preventDefault();
                var target = $(this).attr('href');

                $('#section-nav-list a').removeClass('active');
                $(this).addClass('active');

                $('#checklist-content').animate({
                    scrollTop: $(target).offset().top - $('#checklist-content').offset().top + $('#checklist-content').scrollTop()
                }, 300);
            });
        },

        /**
         * Render a single checklist item
         */
        renderChecklistItem: function (sectionKey, item) {
            var name = 'responses[' + sectionKey + '][' + item.key + ']';

            var html = '<div class="cqa-checklist-item" data-section="' + sectionKey + '" data-item="' + item.key + '">';
            html += '<div class="item-label">' + item.label + '</div>';
            html += '<div class="item-rating">';
            html += '<label class="rating-option"><input type="radio" name="' + name + '[rating]" value="yes"> <span class="rating-yes">✓ Yes</span></label>';
            html += '<label class="rating-option"><input type="radio" name="' + name + '[rating]" value="sometimes"> <span class="rating-sometimes">~ Sometimes</span></label>';
            html += '<label class="rating-option"><input type="radio" name="' + name + '[rating]" value="no"> <span class="rating-no">✗ No</span></label>';
            html += '<label class="rating-option"><input type="radio" name="' + name + '[rating]" value="na" checked> <span class="rating-na">— N/A</span></label>';
            html += '</div>';
            html += '<div class="item-notes">';
            html += '<textarea name="' + name + '[notes]" placeholder="Notes (optional)"></textarea>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        /**
         * Load previous reports for comparison dropdown
         */
        loadSchoolReports: function () {
            var schoolId = $('#school_id').val();

            if (!schoolId) {
                $('#previous_report_id').html('<option value="">No comparison (first report)</option>');
                return;
            }

            CQA.api.get('schools/' + schoolId + '/reports').done(function (reports) {
                var options = '<option value="">No comparison (first report)</option>';

                reports.forEach(function (report) {
                    var date = new Date(report.inspection_date).toLocaleDateString();
                    options += '<option value="' + report.id + '">' +
                        report.report_type + ' - ' + date +
                        ' (' + report.overall_rating + ')</option>';
                });

                $('#previous_report_id').html(options);
            });
        },

        /**
         * Generate AI Summary
         */
        generateAISummary: function () {
            var self = this;

            if (!this.reportId) {
                // Need to save report first
                this.saveDraft(function () {
                    self.generateAISummary();
                });
                return;
            }

            var $btn = $('#generate-summary-btn');
            CQA.loading.show($btn);

            CQA.api.post('reports/' + this.reportId + '/generate-summary', {}).done(function (result) {
                $('#ai-result').show();
                $('#executive-summary').html(result.executive_summary);

                var issuesHtml = '';
                if (result.issues && result.issues.length) {
                    result.issues.forEach(function (issue) {
                        issuesHtml += '<div class="issue-item ' + issue.severity + '">';
                        issuesHtml += '<span class="severity">' + issue.severity + '</span> ';
                        issuesHtml += issue.description;
                        issuesHtml += '</div>';
                    });
                }
                $('#issues-list').html(issuesHtml || '<p>No issues identified.</p>');

                var poiHtml = '';
                if (result.poi && result.poi.length) {
                    result.poi.forEach(function (poi) {
                        poiHtml += '<div class="poi-item">' + (poi.recommendation || poi) + '</div>';
                    });
                }
                $('#poi-list').html(poiHtml || '<p>No recommendations.</p>');

                CQA.notify.success('AI summary generated successfully!');
            }).fail(function (xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to generate summary.';
                CQA.notify.error(msg);
            }).always(function () {
                CQA.loading.hide($btn);
            });
        },

        /**
         * Update review summary
         */
        updateReviewSummary: function () {
            var school = $('#school_id option:selected').text();
            var type = $('input[name="report_type"]:checked').closest('.cqa-type-option').find('strong').text();
            var date = $('#inspection_date').val();

            var stats = this.calculateStats();

            var html = '<table class="cqa-review-table">';
            html += '<tr><th>School:</th><td>' + school + '</td></tr>';
            html += '<tr><th>Report Type:</th><td>' + type + '</td></tr>';
            html += '<tr><th>Inspection Date:</th><td>' + date + '</td></tr>';
            html += '<tr><th>Items Completed:</th><td>' + stats.completed + ' / ' + stats.total + '</td></tr>';
            html += '<tr><th>Yes:</th><td>' + stats.yes + '</td></tr>';
            html += '<tr><th>Needs Work:</th><td>' + stats.sometimes + '</td></tr>';
            html += '<tr><th>Non-Compliant:</th><td>' + stats.no + '</td></tr>';
            html += '<tr><th>Photos:</th><td>' + this.photos.length + '</td></tr>';
            html += '</table>';

            $('#review-summary').html(html);
        },

        /**
         * Calculate response stats
         */
        calculateStats: function () {
            var stats = { total: 0, completed: 0, yes: 0, sometimes: 0, no: 0 };

            $('.cqa-checklist-item').each(function () {
                stats.total++;
                var rating = $(this).find('input[name$="[rating]"]:checked').val();

                if (rating && rating !== 'na') {
                    stats.completed++;
                    if (rating === 'yes') stats.yes++;
                    if (rating === 'sometimes') stats.sometimes++;
                    if (rating === 'no') stats.no++;
                }
            });

            return stats;
        },

        /**
         * Save as draft
         */
        saveDraft: function (callback) {
            var self = this;

            // Collect all data
            this.collectStepData(1);
            this.collectStepData(2);
            this.collectStepData(5);

            var data = this.reportData;
            data.status = 'draft';

            var $btn = $('#save-draft');
            CQA.loading.show($btn);

            var endpoint = this.reportId ? 'reports/' + this.reportId : 'reports';
            var method = this.reportId ? 'put' : 'post';

            CQA.api[method](endpoint, data).done(function (report) {
                self.reportId = report.id;
                $('#report_id').val(report.id);

                // Save responses
                if (data.responses) {
                    CQA.api.post('reports/' + report.id + '/responses', { responses: data.responses });
                }

                self.isDirty = false;
                self.updateAutosaveStatus('saved');
                CQA.notify.success('Draft saved successfully!');

                if (typeof callback === 'function') {
                    callback();
                }
            }).fail(function (xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to save draft.';
                CQA.notify.error(msg);
            }).always(function () {
                CQA.loading.hide($btn);
            });
        },

        /**
         * Submit the report
         */
        submitReport: function () {
            var self = this;

            // Validate final step
            if (!$('input[name="overall_rating"]:checked').val()) {
                CQA.notify.error('Please select an overall rating.');
                return;
            }

            // Collect all data
            this.collectStepData(5);

            var data = this.reportData;
            data.status = 'submitted';

            var $btn = $('#submit-report');
            CQA.loading.show($btn);

            var endpoint = this.reportId ? 'reports/' + this.reportId : 'reports';
            var method = this.reportId ? 'put' : 'post';

            CQA.api[method](endpoint, data).done(function (report) {
                self.reportId = report.id;

                // Save responses
                if (data.responses) {
                    CQA.api.post('reports/' + report.id + '/responses', { responses: data.responses });
                }

                self.isDirty = false;
                CQA.notify.success('Report submitted successfully!');

                // Redirect to view
                setTimeout(function () {
                    window.location.href = cqaAdmin.adminUrl + '?page=chroma-qa-reports-view&id=' + report.id;
                }, 1500);
            }).fail(function (xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to submit report.';
                CQA.notify.error(msg);
            }).always(function () {
                CQA.loading.hide($btn);
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        if ($('#cqa-report-form').length) {
            Wizard.init();
        }
    });

})(jQuery);
