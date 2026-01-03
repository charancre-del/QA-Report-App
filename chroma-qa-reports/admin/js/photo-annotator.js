/**
 * Chroma QA Reports - Photo Annotator
 *
 * Canvas-based drawing tools for photo annotations
 *
 * @package ChromaQAReports
 */

(function ($) {
    'use strict';

    window.CQA = window.CQA || {};

    /**
     * Photo Annotator
     */
    CQA.PhotoAnnotator = {
        canvas: null,
        ctx: null,
        isDrawing: false,
        currentTool: 'arrow',
        currentColor: '#ef4444',
        currentWidth: 3,
        history: [],
        historyIndex: -1,
        startX: 0,
        startY: 0,
        originalImage: null,

        /**
         * Initialize annotator
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            var self = this;

            // Open annotator on photo click
            $(document).on('click', '.cqa-photo-item .annotate-btn', function (e) {
                e.preventDefault();
                var photoUrl = $(this).closest('.cqa-photo-item').find('.photo-preview').css('background-image');
                photoUrl = photoUrl.replace(/^url\(['"](.+)['"]\)$/, '$1');
                self.open(photoUrl, $(this).closest('.cqa-photo-item'));
            });

            // Tool selection
            $(document).on('click', '.annotator-tool', function () {
                self.currentTool = $(this).data('tool');
                $('.annotator-tool').removeClass('active');
                $(this).addClass('active');
            });

            // Color selection
            $(document).on('click', '.annotator-color', function () {
                self.currentColor = $(this).data('color');
                $('.annotator-color').removeClass('active');
                $(this).addClass('active');
            });

            // Width selection
            $(document).on('input', '#annotator-width', function () {
                self.currentWidth = parseInt($(this).val());
            });

            // Undo/Redo
            $(document).on('click', '#annotator-undo', function () { self.undo(); });
            $(document).on('click', '#annotator-redo', function () { self.redo(); });
            $(document).on('click', '#annotator-clear', function () { self.clear(); });

            // Save/Cancel
            $(document).on('click', '#annotator-save', function () { self.save(); });
            $(document).on('click', '#annotator-cancel', function () { self.close(); });
        },

        /**
         * Open annotator modal
         */
        open: function (imageUrl, $photoItem) {
            var self = this;
            this.$currentPhotoItem = $photoItem;

            // Create modal
            var html = `
                <div class="cqa-annotator-modal" id="annotator-modal">
                    <div class="annotator-container">
                        <div class="annotator-toolbar">
                            <div class="tool-group">
                                <button class="annotator-tool active" data-tool="arrow" title="Arrow">
                                    <span>↗</span>
                                </button>
                                <button class="annotator-tool" data-tool="circle" title="Circle">
                                    <span>○</span>
                                </button>
                                <button class="annotator-tool" data-tool="rectangle" title="Rectangle">
                                    <span>□</span>
                                </button>
                                <button class="annotator-tool" data-tool="line" title="Line">
                                    <span>—</span>
                                </button>
                                <button class="annotator-tool" data-tool="freehand" title="Freehand">
                                    <span>✎</span>
                                </button>
                                <button class="annotator-tool" data-tool="text" title="Text">
                                    <span>T</span>
                                </button>
                            </div>
                            <div class="tool-group colors">
                                <button class="annotator-color active" data-color="#ef4444" style="background:#ef4444"></button>
                                <button class="annotator-color" data-color="#f59e0b" style="background:#f59e0b"></button>
                                <button class="annotator-color" data-color="#22c55e" style="background:#22c55e"></button>
                                <button class="annotator-color" data-color="#3b82f6" style="background:#3b82f6"></button>
                                <button class="annotator-color" data-color="#000000" style="background:#000000"></button>
                                <button class="annotator-color" data-color="#ffffff" style="background:#ffffff;border:1px solid #ccc"></button>
                            </div>
                            <div class="tool-group">
                                <label>Width:</label>
                                <input type="range" id="annotator-width" min="1" max="10" value="3">
                            </div>
                            <div class="tool-group">
                                <button id="annotator-undo" class="annotator-btn" title="Undo">↩</button>
                                <button id="annotator-redo" class="annotator-btn" title="Redo">↪</button>
                                <button id="annotator-clear" class="annotator-btn" title="Clear">🗑</button>
                            </div>
                        </div>
                        <div class="annotator-canvas-wrapper">
                            <canvas id="annotator-canvas"></canvas>
                        </div>
                        <div class="annotator-footer">
                            <button id="annotator-cancel" class="button">Cancel</button>
                            <button id="annotator-save" class="button button-primary">Save Annotations</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(html);

            // Load image and setup canvas
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                self.setupCanvas(img);
            };
            img.src = imageUrl;
        },

        /**
         * Setup canvas with image
         */
        setupCanvas: function (img) {
            var self = this;

            this.canvas = document.getElementById('annotator-canvas');
            this.ctx = this.canvas.getContext('2d');
            this.originalImage = img;

            // Size canvas to image (max 800px width)
            var maxWidth = 800;
            var scale = img.width > maxWidth ? maxWidth / img.width : 1;

            this.canvas.width = img.width * scale;
            this.canvas.height = img.height * scale;
            this.scale = scale;

            // Draw image
            this.ctx.drawImage(img, 0, 0, this.canvas.width, this.canvas.height);

            // Save initial state
            this.saveState();

            // Bind canvas events
            $(this.canvas).on('mousedown touchstart', function (e) { self.onMouseDown(e); });
            $(this.canvas).on('mousemove touchmove', function (e) { self.onMouseMove(e); });
            $(this.canvas).on('mouseup touchend', function (e) { self.onMouseUp(e); });
        },

        /**
         * Get canvas coordinates from event
         */
        getCoords: function (e) {
            var rect = this.canvas.getBoundingClientRect();
            var x, y;

            if (e.type.startsWith('touch')) {
                var touch = e.originalEvent.touches[0] || e.originalEvent.changedTouches[0];
                x = touch.clientX - rect.left;
                y = touch.clientY - rect.top;
            } else {
                x = e.clientX - rect.left;
                y = e.clientY - rect.top;
            }

            return { x: x, y: y };
        },

        /**
         * Mouse down event
         */
        onMouseDown: function (e) {
            e.preventDefault();
            this.isDrawing = true;

            var coords = this.getCoords(e);
            this.startX = coords.x;
            this.startY = coords.y;

            if (this.currentTool === 'freehand') {
                this.ctx.beginPath();
                this.ctx.moveTo(coords.x, coords.y);
                this.ctx.strokeStyle = this.currentColor;
                this.ctx.lineWidth = this.currentWidth;
                this.ctx.lineCap = 'round';
            }

            if (this.currentTool === 'text') {
                this.addText(coords.x, coords.y);
                this.isDrawing = false;
            }
        },

        /**
         * Mouse move event
         */
        onMouseMove: function (e) {
            if (!this.isDrawing) return;
            e.preventDefault();

            var coords = this.getCoords(e);

            if (this.currentTool === 'freehand') {
                this.ctx.lineTo(coords.x, coords.y);
                this.ctx.stroke();
            } else {
                // Redraw from last state for shape preview
                this.restoreState();
                this.drawShape(this.startX, this.startY, coords.x, coords.y);
            }
        },

        /**
         * Mouse up event
         */
        onMouseUp: function (e) {
            if (!this.isDrawing) return;
            this.isDrawing = false;

            var coords = this.getCoords(e);

            if (this.currentTool !== 'freehand') {
                this.drawShape(this.startX, this.startY, coords.x, coords.y);
            }

            this.saveState();
        },

        /**
         * Draw shape based on current tool
         */
        drawShape: function (x1, y1, x2, y2) {
            this.ctx.strokeStyle = this.currentColor;
            this.ctx.fillStyle = this.currentColor;
            this.ctx.lineWidth = this.currentWidth;

            switch (this.currentTool) {
                case 'arrow':
                    this.drawArrow(x1, y1, x2, y2);
                    break;
                case 'circle':
                    this.drawCircle(x1, y1, x2, y2);
                    break;
                case 'rectangle':
                    this.drawRectangle(x1, y1, x2, y2);
                    break;
                case 'line':
                    this.drawLine(x1, y1, x2, y2);
                    break;
            }
        },

        /**
         * Draw arrow
         */
        drawArrow: function (x1, y1, x2, y2) {
            var headLength = 15;
            var angle = Math.atan2(y2 - y1, x2 - x1);

            this.ctx.beginPath();
            this.ctx.moveTo(x1, y1);
            this.ctx.lineTo(x2, y2);
            this.ctx.stroke();

            // Arrow head
            this.ctx.beginPath();
            this.ctx.moveTo(x2, y2);
            this.ctx.lineTo(
                x2 - headLength * Math.cos(angle - Math.PI / 6),
                y2 - headLength * Math.sin(angle - Math.PI / 6)
            );
            this.ctx.moveTo(x2, y2);
            this.ctx.lineTo(
                x2 - headLength * Math.cos(angle + Math.PI / 6),
                y2 - headLength * Math.sin(angle + Math.PI / 6)
            );
            this.ctx.stroke();
        },

        /**
         * Draw circle
         */
        drawCircle: function (x1, y1, x2, y2) {
            var radius = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));

            this.ctx.beginPath();
            this.ctx.arc(x1, y1, radius, 0, 2 * Math.PI);
            this.ctx.stroke();
        },

        /**
         * Draw rectangle
         */
        drawRectangle: function (x1, y1, x2, y2) {
            this.ctx.beginPath();
            this.ctx.strokeRect(x1, y1, x2 - x1, y2 - y1);
        },

        /**
         * Draw line
         */
        drawLine: function (x1, y1, x2, y2) {
            this.ctx.beginPath();
            this.ctx.moveTo(x1, y1);
            this.ctx.lineTo(x2, y2);
            this.ctx.stroke();
        },

        /**
         * Add text annotation
         */
        addText: function (x, y) {
            var self = this;
            var text = prompt('Enter annotation text:');

            if (text) {
                this.ctx.font = (14 * this.currentWidth / 3) + 'px Arial';
                this.ctx.fillStyle = this.currentColor;

                // Background for readability
                var metrics = this.ctx.measureText(text);
                var padding = 4;
                this.ctx.fillStyle = 'rgba(255,255,255,0.8)';
                this.ctx.fillRect(x - padding, y - 16, metrics.width + padding * 2, 20);

                this.ctx.fillStyle = this.currentColor;
                this.ctx.fillText(text, x, y);
                this.saveState();
            }
        },

        /**
         * Save canvas state
         */
        saveState: function () {
            // Remove any states after current index
            this.history = this.history.slice(0, this.historyIndex + 1);

            // Save current state
            this.history.push(this.canvas.toDataURL());
            this.historyIndex++;

            // Limit history
            if (this.history.length > 20) {
                this.history.shift();
                this.historyIndex--;
            }
        },

        /**
         * Restore previous state
         */
        restoreState: function () {
            if (this.historyIndex >= 0) {
                var self = this;
                var img = new Image();
                img.onload = function () {
                    self.ctx.clearRect(0, 0, self.canvas.width, self.canvas.height);
                    self.ctx.drawImage(img, 0, 0);
                };
                img.src = this.history[this.historyIndex];
            }
        },

        /**
         * Undo last action
         */
        undo: function () {
            if (this.historyIndex > 0) {
                this.historyIndex--;
                this.restoreState();
            }
        },

        /**
         * Redo action
         */
        redo: function () {
            if (this.historyIndex < this.history.length - 1) {
                this.historyIndex++;
                this.restoreState();
            }
        },

        /**
         * Clear all annotations
         */
        clear: function () {
            if (confirm('Clear all annotations?')) {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.drawImage(this.originalImage, 0, 0, this.canvas.width, this.canvas.height);
                this.saveState();
            }
        },

        /**
         * Save annotated image
         */
        save: function () {
            var self = this;

            // Get annotated image as data URL
            var dataUrl = this.canvas.toDataURL('image/jpeg', 0.9);

            // Convert to blob
            var blob = this.dataURLtoBlob(dataUrl);

            // Upload to server
            var formData = new FormData();
            formData.append('file', blob, 'annotated-image.jpg');
            formData.append('action', 'cqa_save_annotated_photo');
            formData.append('nonce', cqaAdmin.nonce);

            $.ajax({
                url: cqaAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // Update photo item preview
                        self.$currentPhotoItem.find('.photo-preview')
                            .css('background-image', 'url(' + response.data.url + ')');
                        self.$currentPhotoItem.data('has-markup', true);

                        CQA.notify.success('Annotations saved!');
                        self.close();
                    } else {
                        CQA.notify.error(response.data.message || 'Failed to save annotations.');
                    }
                },
                error: function () {
                    CQA.notify.error('Failed to save annotations.');
                }
            });
        },

        /**
         * Convert data URL to blob
         */
        dataURLtoBlob: function (dataURL) {
            var binary = atob(dataURL.split(',')[1]);
            var array = [];
            for (var i = 0; i < binary.length; i++) {
                array.push(binary.charCodeAt(i));
            }
            return new Blob([new Uint8Array(array)], { type: 'image/jpeg' });
        },

        /**
         * Close annotator modal
         */
        close: function () {
            this.history = [];
            this.historyIndex = -1;
            $('#annotator-modal').remove();
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        CQA.PhotoAnnotator.init();
    });

})(jQuery);
