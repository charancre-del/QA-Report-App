/**
 * Chroma QA Reports - Duplicate Report Feature
 *
 * Enables copying a previous report as starting point
 *
 * @package ChromaQAReports
 */

(function ($) {
    'use strict';

    window.CQA = window.CQA || {};

    /**
     * Duplicate Report functionality
     */
    CQA.DuplicateReport = {
        sourceReportId: null,
        sourceData: null,

        /**
         * Initialize duplicate functionality
         */
        init: function () {
            this.bindEvents();
            this.checkUrlParams();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            var self = this;

            // Duplicate button in reports list
            $(document).on('click', '.cqa-duplicate-btn', function (e) {
                e.preventDefault();
                var reportId = $(this).data('report-id');
                self.startDuplicate(reportId);
            });

            // Duplicate from dropdown in wizard step 1
            $('#duplicate_from').on('change', function () {
                var reportId = $(this).val();
                if (reportId) {
                    self.loadSourceReport(reportId);
                } else {
                    self.clearSourceData();
                }
            });

            // Apply duplicate data button
            $('#apply-duplicate-btn').on('click', function () {
                self.applyDuplicateData();
            });
        },

        /**
         * Check URL for duplicate parameter
         */
        checkUrlParams: function () {
            var urlParams = new URLSearchParams(window.location.search);
            var duplicateFrom = urlParams.get('duplicate_from');

            if (duplicateFrom) {
                this.loadSourceReport(duplicateFrom);
                $('#duplicate_from').val(duplicateFrom);
            }
        },

        /**
         * Start duplicate workflow - redirect to create with param
         */
        startDuplicate: function (reportId) {
            window.location.href = cqaAdmin.adminUrl +
                '?page=chroma-qa-reports-create&duplicate_from=' + reportId;
        },

        /**
         * Load source report data
         */
        loadSourceReport: function (reportId) {
            var self = this;

            $('#duplicate-status').html('<span class="spinner is-active"></span> Loading report data...');

            CQA.api.get('reports/' + reportId).done(function (report) {
                self.sourceReportId = reportId;
                self.sourceData = report;

                // Also load responses
                CQA.api.get('reports/' + reportId + '/responses').done(function (responses) {
                    self.sourceData.responses = responses;
                    self.showDuplicatePreview();
                });
            }).fail(function () {
                CQA.notify.error('Failed to load source report.');
                $('#duplicate-status').empty();
            });
        },

        /**
         * Show duplicate preview
         */
        showDuplicatePreview: function () {
            var report = this.sourceData;
            var date = new Date(report.inspection_date).toLocaleDateString();

            var html = '<div class="cqa-duplicate-preview">';
            html += '<h4>📋 Duplicating from:</h4>';
            html += '<p><strong>' + (report.school_name || 'Report #' + report.id) + '</strong></p>';
            html += '<p>Date: ' + date + ' | Type: ' + report.report_type + '</p>';
            html += '<p>Items to copy: ' + Object.keys(report.responses || {}).length + ' sections</p>';
            html += '<button type="button" class="button button-primary" id="apply-duplicate-btn">';
            html += '✓ Apply to Current Report</button>';
            html += '<button type="button" class="button" id="clear-duplicate-btn">';
            html += '✗ Clear</button>';
            html += '</div>';

            $('#duplicate-status').html(html);

            $('#clear-duplicate-btn').on('click', function () {
                CQA.DuplicateReport.clearSourceData();
            });
        },

        /**
         * Apply duplicate data to current form
         */
        applyDuplicateData: function () {
            var self = this;

            if (!this.sourceData) {
                CQA.notify.error('No source report loaded.');
                return;
            }

            // Set school if not already set
            if (!$('#school_id').val() && this.sourceData.school_id) {
                $('#school_id').val(this.sourceData.school_id).trigger('change');
            }

            // Set report type
            if (this.sourceData.report_type) {
                $('input[name="report_type"][value="' + this.sourceData.report_type + '"]')
                    .prop('checked', true);
            }

            // Set previous report for comparison
            $('#previous_report_id').val(this.sourceReportId);

            // Store responses to apply after checklist loads
            if (this.sourceData.responses) {
                this.pendingResponses = this.sourceData.responses;

                // Wait for checklist to load then apply
                var checkInterval = setInterval(function () {
                    if ($('.cqa-checklist-item').length > 0) {
                        clearInterval(checkInterval);
                        self.applyResponsesToChecklist();
                    }
                }, 500);

                // Timeout after 10 seconds
                setTimeout(function () {
                    clearInterval(checkInterval);
                }, 10000);
            }

            CQA.notify.success('Report data applied! Proceed to checklist to see copied items.');

            // Update status
            $('#duplicate-status').html(
                '<div class="cqa-duplicate-applied">✓ Data applied from previous report. ' +
                'Review and update items as needed.</div>'
            );
        },

        /**
         * Apply responses to loaded checklist
         */
        applyResponsesToChecklist: function () {
            var responses = this.pendingResponses;
            var appliedCount = 0;

            if (!responses) return;

            // Responses could be grouped by section or flat
            if (Array.isArray(responses)) {
                // Flat array of response objects
                responses.forEach(function (resp) {
                    var $item = $('.cqa-checklist-item[data-section="' + resp.section_key + '"][data-item="' + resp.item_key + '"]');
                    if ($item.length) {
                        $item.find('input[name$="[rating]"][value="' + resp.rating + '"]').prop('checked', true);
                        $item.find('textarea[name$="[notes]"]').val(resp.notes || '');
                        appliedCount++;
                    }
                });
            } else {
                // Grouped by section
                Object.keys(responses).forEach(function (sectionKey) {
                    var sectionResponses = responses[sectionKey];
                    Object.keys(sectionResponses).forEach(function (itemKey) {
                        var resp = sectionResponses[itemKey];
                        var $item = $('.cqa-checklist-item[data-section="' + sectionKey + '"][data-item="' + itemKey + '"]');
                        if ($item.length) {
                            var rating = resp.rating || resp;
                            var notes = resp.notes || '';
                            $item.find('input[name$="[rating]"][value="' + rating + '"]').prop('checked', true);
                            $item.find('textarea[name$="[notes]"]').val(notes);
                            appliedCount++;
                        }
                    });
                });
            }

            CQA.notify.success('Applied ' + appliedCount + ' checklist responses from previous report.');
        },

        /**
         * Clear source data
         */
        clearSourceData: function () {
            this.sourceReportId = null;
            this.sourceData = null;
            this.pendingResponses = null;
            $('#duplicate_from').val('');
            $('#duplicate-status').empty();
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        CQA.DuplicateReport.init();
    });

})(jQuery);
