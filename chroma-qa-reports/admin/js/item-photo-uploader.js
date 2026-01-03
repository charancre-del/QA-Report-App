/**
 * Item Photo Uploader - Attach photos to individual checklist items
 *
 * @package ChromaQAReports
 */

(function ($) {
    'use strict';

    window.CQA = window.CQA || {};

    /**
     * Item Photo Uploader
     */
    CQA.ItemPhotoUploader = {
        currentItemKey: null,
        currentSectionKey: null,
        photos: {}, // itemKey => [photos]

        /**
         * Initialize the uploader.
         */
        init: function () {
            this.createModal();
            this.bindEvents();
            this.injectPhotoButtons();
        },

        /**
         * Inject camera buttons into each checklist item.
         */
        injectPhotoButtons: function () {
            var self = this;

            // Add photo button to each checklist item
            $('.cqa-checklist-item').each(function () {
                var $item = $(this);
                var itemKey = $item.data('item-key');
                var sectionKey = $item.closest('.cqa-checklist-section').data('section-key');

                if (!itemKey) return;

                // Check if button already exists
                if ($item.find('.cqa-item-photo-btn').length > 0) return;

                // Create photo button
                var $photoBtn = $('<button type="button" class="cqa-item-photo-btn" title="Attach Photo">' +
                    '<span class="dashicons dashicons-camera"></span>' +
                    '<span class="cqa-photo-count"></span>' +
                    '</button>');

                $photoBtn.data('item-key', itemKey);
                $photoBtn.data('section-key', sectionKey);

                // Insert after rating buttons
                var $ratingButtons = $item.find('.cqa-rating-buttons');
                if ($ratingButtons.length) {
                    $ratingButtons.after($photoBtn);
                } else {
                    $item.find('.cqa-item-label').after($photoBtn);
                }
            });

            // Update photo counts
            this.updateAllPhotoCounts();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function () {
            var self = this;

            // Photo button click
            $(document).on('click', '.cqa-item-photo-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.currentItemKey = $(this).data('item-key');
                self.currentSectionKey = $(this).data('section-key');
                self.openModal();
            });

            // Close modal
            $(document).on('click', '.cqa-photo-modal-close, .cqa-photo-modal-overlay', function () {
                self.closeModal();
            });

            // File input change
            $(document).on('change', '#cqa-item-photo-input', function () {
                self.handleFileSelect(this.files);
            });

            // Camera capture
            $(document).on('click', '#cqa-item-camera-btn', function () {
                $('#cqa-item-camera-input').click();
            });

            $(document).on('change', '#cqa-item-camera-input', function () {
                self.handleFileSelect(this.files);
            });

            // Delete photo
            $(document).on('click', '.cqa-item-photo-delete', function () {
                var photoId = $(this).closest('.cqa-item-photo-thumb').data('photo-id');
                self.deletePhoto(photoId);
            });

            // Drag and drop
            $(document).on('dragover', '#cqa-item-photo-dropzone', function (e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });

            $(document).on('dragleave', '#cqa-item-photo-dropzone', function () {
                $(this).removeClass('dragover');
            });

            $(document).on('drop', '#cqa-item-photo-dropzone', function (e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                self.handleFileSelect(e.originalEvent.dataTransfer.files);
            });
        },

        /**
         * Create the photo upload modal.
         */
        createModal: function () {
            var modalHtml = `
                <div id="cqa-item-photo-modal" class="cqa-photo-modal" style="display:none;">
                    <div class="cqa-photo-modal-overlay"></div>
                    <div class="cqa-photo-modal-content">
                        <div class="cqa-photo-modal-header">
                            <h3>📷 Attach Photos to Item</h3>
                            <button type="button" class="cqa-photo-modal-close">&times;</button>
                        </div>
                        <div class="cqa-photo-modal-body">
                            <div id="cqa-item-photo-dropzone" class="cqa-dropzone">
                                <span class="dashicons dashicons-upload"></span>
                                <p>Drag photos here or click to select</p>
                                <input type="file" id="cqa-item-photo-input" accept="image/*" multiple style="display:none;">
                                <input type="file" id="cqa-item-camera-input" accept="image/*" capture="environment" style="display:none;">
                                <div class="cqa-dropzone-buttons">
                                    <button type="button" class="button" onclick="document.getElementById('cqa-item-photo-input').click()">
                                        <span class="dashicons dashicons-images-alt2"></span> Select Files
                                    </button>
                                    <button type="button" class="button" id="cqa-item-camera-btn">
                                        <span class="dashicons dashicons-camera"></span> Take Photo
                                    </button>
                                </div>
                            </div>
                            <div id="cqa-item-photo-list" class="cqa-photo-list"></div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            // Add modal styles
            var styles = `
                <style id="cqa-item-photo-styles">
                    .cqa-item-photo-btn {
                        background: none;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        padding: 4px 8px;
                        cursor: pointer;
                        margin-left: 8px;
                        display: inline-flex;
                        align-items: center;
                        gap: 4px;
                        transition: all 0.2s;
                    }
                    .cqa-item-photo-btn:hover {
                        background: #f0f0f1;
                        border-color: #6366f1;
                        color: #6366f1;
                    }
                    .cqa-item-photo-btn .dashicons {
                        font-size: 16px;
                        width: 16px;
                        height: 16px;
                    }
                    .cqa-item-photo-btn .cqa-photo-count {
                        background: #6366f1;
                        color: white;
                        font-size: 10px;
                        padding: 2px 6px;
                        border-radius: 10px;
                        display: none;
                    }
                    .cqa-item-photo-btn .cqa-photo-count.has-photos {
                        display: inline-block;
                    }
                    .cqa-photo-modal {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        z-index: 100000;
                    }
                    .cqa-photo-modal-overlay {
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0,0,0,0.5);
                    }
                    .cqa-photo-modal-content {
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        background: white;
                        border-radius: 8px;
                        width: 90%;
                        max-width: 600px;
                        max-height: 80vh;
                        overflow: hidden;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                    }
                    .cqa-photo-modal-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 16px 20px;
                        border-bottom: 1px solid #e5e7eb;
                    }
                    .cqa-photo-modal-header h3 {
                        margin: 0;
                        font-size: 18px;
                    }
                    .cqa-photo-modal-close {
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                        color: #6b7280;
                    }
                    .cqa-photo-modal-body {
                        padding: 20px;
                        max-height: 60vh;
                        overflow-y: auto;
                    }
                    .cqa-dropzone {
                        border: 2px dashed #d1d5db;
                        border-radius: 8px;
                        padding: 30px;
                        text-align: center;
                        transition: all 0.2s;
                    }
                    .cqa-dropzone.dragover {
                        border-color: #6366f1;
                        background: #f0f0ff;
                    }
                    .cqa-dropzone .dashicons {
                        font-size: 40px;
                        width: 40px;
                        height: 40px;
                        color: #9ca3af;
                    }
                    .cqa-dropzone-buttons {
                        margin-top: 16px;
                        display: flex;
                        gap: 10px;
                        justify-content: center;
                    }
                    .cqa-photo-list {
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                        gap: 12px;
                        margin-top: 20px;
                    }
                    .cqa-item-photo-thumb {
                        position: relative;
                        border-radius: 8px;
                        overflow: hidden;
                        aspect-ratio: 1;
                    }
                    .cqa-item-photo-thumb img {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                    }
                    .cqa-item-photo-delete {
                        position: absolute;
                        top: 4px;
                        right: 4px;
                        background: rgba(239,68,68,0.9);
                        color: white;
                        border: none;
                        border-radius: 50%;
                        width: 24px;
                        height: 24px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .cqa-item-photo-thumb.uploading::after {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(255,255,255,0.8);
                    }
                    .cqa-item-photo-thumb.uploading::before {
                        content: '';
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        width: 24px;
                        height: 24px;
                        margin: -12px;
                        border: 3px solid #6366f1;
                        border-top-color: transparent;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                        z-index: 1;
                    }
                    @keyframes spin {
                        to { transform: rotate(360deg); }
                    }
                </style>
            `;

            if ($('#cqa-item-photo-styles').length === 0) {
                $('head').append(styles);
            }
        },

        /**
         * Open the photo modal.
         */
        openModal: function () {
            var self = this;
            $('#cqa-item-photo-modal').fadeIn(200);
            this.loadItemPhotos();
        },

        /**
         * Close the photo modal.
         */
        closeModal: function () {
            $('#cqa-item-photo-modal').fadeOut(200);
            this.currentItemKey = null;
            this.currentSectionKey = null;
            $('#cqa-item-photo-list').empty();
        },

        /**
         * Load existing photos for the current item.
         */
        loadItemPhotos: function () {
            var self = this;
            var $list = $('#cqa-item-photo-list');
            $list.empty();

            // Check local cache first
            var itemPhotos = this.photos[this.currentItemKey] || [];

            itemPhotos.forEach(function (photo) {
                self.addPhotoToList(photo);
            });
        },

        /**
         * Handle file selection.
         */
        handleFileSelect: function (files) {
            var self = this;

            Array.from(files).forEach(function (file) {
                if (!file.type.startsWith('image/')) return;

                // Create preview
                var reader = new FileReader();
                reader.onload = function (e) {
                    var tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

                    // Add to list with loading state
                    var $thumb = $('<div class="cqa-item-photo-thumb uploading" data-temp-id="' + tempId + '">' +
                        '<img src="' + e.target.result + '">' +
                        '</div>');
                    $('#cqa-item-photo-list').append($thumb);

                    // Upload to server
                    self.uploadPhoto(file, tempId);
                };
                reader.readAsDataURL(file);
            });

            // Reset file inputs
            $('#cqa-item-photo-input').val('');
            $('#cqa-item-camera-input').val('');
        },

        /**
         * Upload photo to server.
         */
        uploadPhoto: function (file, tempId) {
            var self = this;
            var formData = new FormData();
            formData.append('file', file);
            formData.append('section_key', this.currentSectionKey);
            formData.append('item_key', this.currentItemKey);
            formData.append('report_id', $('#report_id').val() || 0);

            $.ajax({
                url: cqaAdmin.restUrl + 'photos/upload',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-WP-Nonce': cqaAdmin.nonce
                },
                success: function (response) {
                    var $thumb = $('[data-temp-id="' + tempId + '"]');
                    $thumb.removeClass('uploading');
                    $thumb.attr('data-photo-id', response.id);
                    $thumb.append('<button type="button" class="cqa-item-photo-delete">&times;</button>');

                    // Add to local cache
                    if (!self.photos[self.currentItemKey]) {
                        self.photos[self.currentItemKey] = [];
                    }
                    self.photos[self.currentItemKey].push(response);

                    // Update count
                    self.updatePhotoCount(self.currentItemKey);

                    CQA.notify.success('Photo uploaded successfully');
                },
                error: function (xhr) {
                    $('[data-temp-id="' + tempId + '"]').remove();
                    CQA.notify.error('Failed to upload photo');
                }
            });
        },

        /**
         * Delete a photo.
         */
        deletePhoto: function (photoId) {
            var self = this;

            if (!confirm('Delete this photo?')) return;

            $.ajax({
                url: cqaAdmin.restUrl + 'photos/' + photoId,
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': cqaAdmin.nonce
                },
                success: function () {
                    $('[data-photo-id="' + photoId + '"]').remove();

                    // Remove from local cache
                    if (self.photos[self.currentItemKey]) {
                        self.photos[self.currentItemKey] = self.photos[self.currentItemKey].filter(function (p) {
                            return p.id !== photoId;
                        });
                    }

                    self.updatePhotoCount(self.currentItemKey);
                    CQA.notify.success('Photo deleted');
                },
                error: function () {
                    CQA.notify.error('Failed to delete photo');
                }
            });
        },

        /**
         * Add photo to the modal list.
         */
        addPhotoToList: function (photo) {
            var thumbUrl = photo.thumbnail_url || 'https://drive.google.com/thumbnail?id=' + photo.drive_file_id + '&sz=w200';
            var $thumb = $('<div class="cqa-item-photo-thumb" data-photo-id="' + photo.id + '">' +
                '<img src="' + thumbUrl + '">' +
                '<button type="button" class="cqa-item-photo-delete">&times;</button>' +
                '</div>');
            $('#cqa-item-photo-list').append($thumb);
        },

        /**
         * Update photo count badge for an item.
         */
        updatePhotoCount: function (itemKey) {
            var count = (this.photos[itemKey] || []).length;
            var $btn = $('.cqa-item-photo-btn[data-item-key="' + itemKey + '"]');
            var $count = $btn.find('.cqa-photo-count');

            if (count > 0) {
                $count.text(count).addClass('has-photos');
            } else {
                $count.text('').removeClass('has-photos');
            }
        },

        /**
         * Update all photo counts.
         */
        updateAllPhotoCounts: function () {
            var self = this;
            Object.keys(this.photos).forEach(function (itemKey) {
                self.updatePhotoCount(itemKey);
            });
        },

        /**
         * Set photos data (called when loading existing report).
         */
        setPhotos: function (photosData) {
            var self = this;
            this.photos = {};

            photosData.forEach(function (photo) {
                if (photo.item_key) {
                    if (!self.photos[photo.item_key]) {
                        self.photos[photo.item_key] = [];
                    }
                    self.photos[photo.item_key].push(photo);
                }
            });

            this.updateAllPhotoCounts();
        },

        /**
         * Get all photos for submission.
         */
        getAllPhotos: function () {
            var allPhotos = [];
            var self = this;

            Object.keys(this.photos).forEach(function (itemKey) {
                self.photos[itemKey].forEach(function (photo) {
                    allPhotos.push({
                        id: photo.id,
                        item_key: itemKey,
                        section_key: photo.section_key
                    });
                });
            });

            return allPhotos;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function () {
        // Initialize after checklist is rendered
        $(document).on('cqa:checklistRendered', function () {
            CQA.ItemPhotoUploader.init();
        });

        // Also try to initialize on page load
        setTimeout(function () {
            if ($('.cqa-checklist-item').length > 0) {
                CQA.ItemPhotoUploader.init();
            }
        }, 1000);
    });

})(jQuery);
