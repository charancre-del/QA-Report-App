/**
 * Chroma QA Reports - Keyboard Navigation
 *
 * Enables keyboard shortcuts for rapid checklist completion
 *
 * @package ChromaQAReports
 */

(function ($) {
    'use strict';

    window.CQA = window.CQA || {};

    /**
     * Keyboard Navigation
     */
    CQA.KeyboardNav = {
        enabled: true,
        currentIndex: 0,
        items: [],

        /**
         * Initialize keyboard navigation
         */
        init: function () {
            this.bindEvents();
            this.showHelpTooltip();
        },

        /**
         * Bind keyboard events
         */
        bindEvents: function () {
            var self = this;

            $(document).on('keydown', function (e) {
                if (!self.enabled) return;
                if (self.isTypingInInput(e)) return;

                self.handleKeyPress(e);
            });

            // Update items when checklist loads
            $(document).on('DOMNodeInserted', '.cqa-checklist-item', function () {
                self.refreshItems();
            });

            // Toggle keyboard nav
            $('#toggle-keyboard-nav').on('click', function () {
                self.enabled = !self.enabled;
                $(this).toggleClass('active', self.enabled);
                CQA.notify.show(
                    self.enabled ? 'Keyboard navigation enabled' : 'Keyboard navigation disabled',
                    'info'
                );
            });
        },

        /**
         * Check if user is typing in an input
         */
        isTypingInInput: function (e) {
            var target = e.target;
            return target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT' ||
                target.isContentEditable;
        },

        /**
         * Handle key press
         */
        handleKeyPress: function (e) {
            var key = e.key;

            switch (key) {
                // Rating hotkeys
                case '1':
                    this.setRating('yes');
                    e.preventDefault();
                    break;
                case '2':
                    this.setRating('sometimes');
                    e.preventDefault();
                    break;
                case '3':
                    this.setRating('no');
                    e.preventDefault();
                    break;
                case '4':
                    this.setRating('na');
                    e.preventDefault();
                    break;

                // Navigation
                case 'ArrowDown':
                case 'j':
                    this.moveNext();
                    e.preventDefault();
                    break;
                case 'ArrowUp':
                case 'k':
                    this.movePrev();
                    e.preventDefault();
                    break;

                // Notes
                case 'n':
                case 'Enter':
                    this.focusNotes();
                    e.preventDefault();
                    break;

                // Escape notes
                case 'Escape':
                    this.blurNotes();
                    break;

                // Flag item
                case 'f':
                    this.toggleFlag();
                    e.preventDefault();
                    break;

                // Section navigation
                case '[':
                    this.prevSection();
                    e.preventDefault();
                    break;
                case ']':
                    this.nextSection();
                    e.preventDefault();
                    break;

                // Help
                case '?':
                    this.showHelp();
                    e.preventDefault();
                    break;
            }
        },

        /**
         * Refresh checklist items
         */
        refreshItems: function () {
            this.items = $('.cqa-checklist-item').toArray();
        },

        /**
         * Get current item
         */
        getCurrentItem: function () {
            if (this.items.length === 0) {
                this.refreshItems();
            }
            return $(this.items[this.currentIndex]);
        },

        /**
         * Set rating on current item
         */
        setRating: function (rating) {
            var $item = this.getCurrentItem();
            if (!$item.length) return;

            $item.find('input[name$="[rating]"][value="' + rating + '"]')
                .prop('checked', true)
                .trigger('change');

            // Visual feedback
            this.flashItem($item, 'rating-set');

            // Auto-advance if setting Yes
            if (rating === 'yes') {
                setTimeout(function () {
                    CQA.KeyboardNav.moveNext();
                }, 150);
            }
        },

        /**
         * Move to next item
         */
        moveNext: function () {
            if (this.currentIndex < this.items.length - 1) {
                this.currentIndex++;
                this.scrollToCurrentItem();
            }
        },

        /**
         * Move to previous item
         */
        movePrev: function () {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.scrollToCurrentItem();
            }
        },

        /**
         * Scroll to current item
         */
        scrollToCurrentItem: function () {
            var $item = this.getCurrentItem();
            if (!$item.length) return;

            // Remove highlight from all items
            $('.cqa-checklist-item').removeClass('keyboard-focus');

            // Add highlight to current
            $item.addClass('keyboard-focus');

            // Scroll into view
            var container = $('#checklist-content');
            var itemTop = $item.offset().top;
            var containerTop = container.offset().top;
            var scrollTop = container.scrollTop();

            container.animate({
                scrollTop: scrollTop + (itemTop - containerTop) - 100
            }, 150);
        },

        /**
         * Focus notes field
         */
        focusNotes: function () {
            var $item = this.getCurrentItem();
            if (!$item.length) return;

            $item.find('textarea[name$="[notes]"]').focus();
        },

        /**
         * Blur notes field
         */
        blurNotes: function () {
            $('textarea:focus').blur();
        },

        /**
         * Toggle flag on current item
         */
        toggleFlag: function () {
            var $item = this.getCurrentItem();
            if (!$item.length) return;

            $item.toggleClass('flagged');

            var $flagBtn = $item.find('.flag-btn');
            if (!$flagBtn.length) {
                $item.find('.item-label').append(
                    '<span class="flag-btn" title="Flagged for review">🚩</span>'
                );
            } else {
                $flagBtn.toggleClass('active');
            }

            this.updateFlagCount();
        },

        /**
         * Update flag count
         */
        updateFlagCount: function () {
            var count = $('.cqa-checklist-item.flagged').length;
            var $counter = $('#flag-counter');

            if (count > 0) {
                if (!$counter.length) {
                    $('.cqa-wizard-nav').prepend(
                        '<span id="flag-counter" class="cqa-flag-counter">' +
                        '🚩 <span class="count">' + count + '</span> flagged</span>'
                    );
                } else {
                    $counter.find('.count').text(count);
                }
            } else {
                $counter.remove();
            }
        },

        /**
         * Go to previous section
         */
        prevSection: function () {
            var $nav = $('#section-nav-list a.active');
            var $prev = $nav.parent().prev().find('a');
            if ($prev.length) {
                $prev.click();
            }
        },

        /**
         * Go to next section
         */
        nextSection: function () {
            var $nav = $('#section-nav-list a.active');
            var $next = $nav.parent().next().find('a');
            if ($next.length) {
                $next.click();
            }
        },

        /**
         * Flash item for visual feedback
         */
        flashItem: function ($item, className) {
            $item.addClass(className);
            setTimeout(function () {
                $item.removeClass(className);
            }, 300);
        },

        /**
         * Show help tooltip on first load
         */
        showHelpTooltip: function () {
            if (localStorage.getItem('cqa_keyboard_help_shown')) return;

            setTimeout(function () {
                CQA.notify.show(
                    '⌨️ Keyboard shortcuts available! Press ? for help.',
                    'info'
                );
                localStorage.setItem('cqa_keyboard_help_shown', '1');
            }, 2000);
        },

        /**
         * Show keyboard shortcuts help
         */
        showHelp: function () {
            var html = '<div class="cqa-keyboard-help">';
            html += '<h3>⌨️ Keyboard Shortcuts</h3>';
            html += '<table>';
            html += '<tr><th>Key</th><th>Action</th></tr>';
            html += '<tr><td><kbd>1</kbd></td><td>Rate as Yes</td></tr>';
            html += '<tr><td><kbd>2</kbd></td><td>Rate as Sometimes</td></tr>';
            html += '<tr><td><kbd>3</kbd></td><td>Rate as No</td></tr>';
            html += '<tr><td><kbd>4</kbd></td><td>Rate as N/A</td></tr>';
            html += '<tr><td><kbd>↓</kbd> / <kbd>j</kbd></td><td>Next item</td></tr>';
            html += '<tr><td><kbd>↑</kbd> / <kbd>k</kbd></td><td>Previous item</td></tr>';
            html += '<tr><td><kbd>n</kbd> / <kbd>Enter</kbd></td><td>Edit notes</td></tr>';
            html += '<tr><td><kbd>Esc</kbd></td><td>Exit notes</td></tr>';
            html += '<tr><td><kbd>f</kbd></td><td>Flag item</td></tr>';
            html += '<tr><td><kbd>[</kbd></td><td>Previous section</td></tr>';
            html += '<tr><td><kbd>]</kbd></td><td>Next section</td></tr>';
            html += '<tr><td><kbd>?</kbd></td><td>Show this help</td></tr>';
            html += '</table>';
            html += '<p class="tip">💡 Tip: Rating "Yes" auto-advances to next item</p>';
            html += '</div>';

            // Show in modal
            $('body').append(
                '<div class="cqa-modal-overlay" id="keyboard-help-modal">' +
                '<div class="cqa-modal">' + html +
                '<button class="button" onclick="jQuery(\'#keyboard-help-modal\').remove()">Close</button>' +
                '</div></div>'
            );
        }
    };

    // Initialize on document ready when on checklist step
    $(document).ready(function () {
        if ($('.cqa-checklist-container').length) {
            CQA.KeyboardNav.init();
        }
    });

})(jQuery);
