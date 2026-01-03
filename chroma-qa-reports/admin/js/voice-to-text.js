/**
 * Chroma QA Reports - Voice to Text
 *
 * Enables voice dictation for notes fields
 *
 * @package ChromaQAReports
 */

(function ($) {
    'use strict';

    window.CQA = window.CQA || {};

    /**
     * Voice to Text functionality
     */
    CQA.VoiceToText = {
        recognition: null,
        isRecording: false,
        currentTarget: null,
        supported: false,

        /**
         * Initialize voice to text
         */
        init: function () {
            // Check for Web Speech API support
            var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (!SpeechRecognition) {
                console.log('Speech recognition not supported');
                return;
            }

            this.supported = true;
            this.recognition = new SpeechRecognition();
            this.configureRecognition();
            this.addMicButtons();
            this.bindEvents();
        },

        /**
         * Configure recognition settings
         */
        configureRecognition: function () {
            var self = this;

            this.recognition.continuous = true;
            this.recognition.interimResults = true;
            this.recognition.lang = 'en-US';

            this.recognition.onstart = function () {
                self.isRecording = true;
                self.updateUI(true);
                console.log('Voice recording started');
            };

            this.recognition.onend = function () {
                self.isRecording = false;
                self.updateUI(false);
                console.log('Voice recording stopped');
            };

            this.recognition.onresult = function (event) {
                self.handleResult(event);
            };

            this.recognition.onerror = function (event) {
                console.error('Speech recognition error:', event.error);
                self.isRecording = false;
                self.updateUI(false);

                if (event.error === 'not-allowed') {
                    CQA.notify.error('Microphone access denied. Please allow microphone permissions.');
                } else if (event.error === 'no-speech') {
                    CQA.notify.show('No speech detected. Please try again.', 'info');
                }
            };
        },

        /**
         * Add microphone buttons to notes fields
         */
        addMicButtons: function () {
            if (!this.supported) return;

            var self = this;

            // Add to existing notes fields
            this.addButtonsToFields();

            // Watch for dynamically added fields
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.addedNodes.length) {
                        self.addButtonsToFields();
                    }
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });
        },

        /**
         * Add buttons to notes fields
         */
        addButtonsToFields: function () {
            var self = this;

            $('textarea[name$="[notes]"], textarea.cqa-notes-field').each(function () {
                var $textarea = $(this);

                // Skip if already has button
                if ($textarea.parent().find('.cqa-mic-btn').length) return;

                // Wrap if needed
                if (!$textarea.parent().hasClass('cqa-notes-wrapper')) {
                    $textarea.wrap('<div class="cqa-notes-wrapper"></div>');
                }

                // Add mic button
                var $btn = $(
                    '<button type="button" class="cqa-mic-btn" title="Voice input">' +
                    '<span class="dashicons dashicons-microphone"></span>' +
                    '</button>'
                );

                $textarea.parent().append($btn);
            });
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            var self = this;

            $(document).on('click', '.cqa-mic-btn', function (e) {
                e.preventDefault();
                var $textarea = $(this).siblings('textarea');
                self.toggleRecording($textarea);
            });

            // Stop on escape
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && self.isRecording) {
                    self.stopRecording();
                }
            });
        },

        /**
         * Toggle recording
         */
        toggleRecording: function ($textarea) {
            if (this.isRecording) {
                this.stopRecording();
            } else {
                this.startRecording($textarea);
            }
        },

        /**
         * Start recording
         */
        startRecording: function ($textarea) {
            this.currentTarget = $textarea;
            this.interimTranscript = '';

            // Store current value
            this.originalValue = $textarea.val();

            try {
                this.recognition.start();
            } catch (e) {
                // Already started, restart
                this.recognition.stop();
                setTimeout(() => this.recognition.start(), 100);
            }
        },

        /**
         * Stop recording
         */
        stopRecording: function () {
            this.recognition.stop();
        },

        /**
         * Handle recognition result
         */
        handleResult: function (event) {
            var finalTranscript = '';
            var interimTranscript = '';

            for (var i = event.resultIndex; i < event.results.length; i++) {
                var transcript = event.results[i][0].transcript;

                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interimTranscript += transcript;
                }
            }

            if (this.currentTarget) {
                var currentValue = this.originalValue;

                // Add space if there's existing content
                if (currentValue && !currentValue.endsWith(' ') && !currentValue.endsWith('\n')) {
                    currentValue += ' ';
                }

                // Process voice commands
                finalTranscript = this.processVoiceCommands(finalTranscript);
                interimTranscript = this.processVoiceCommands(interimTranscript);

                // Update textarea with final + interim
                var newValue = currentValue + finalTranscript;
                if (interimTranscript) {
                    this.currentTarget.val(newValue + interimTranscript);
                } else {
                    this.currentTarget.val(newValue);
                    // Update original value to include final transcript
                    this.originalValue = newValue;
                }

                // Scroll to bottom
                var textarea = this.currentTarget[0];
                textarea.scrollTop = textarea.scrollHeight;
            }
        },

        /**
         * Process voice commands
         */
        processVoiceCommands: function (text) {
            // Common dictation commands
            var commands = {
                'period': '.',
                'comma': ',',
                'question mark': '?',
                'exclamation point': '!',
                'exclamation mark': '!',
                'colon': ':',
                'semicolon': ';',
                'new line': '\n',
                'new paragraph': '\n\n',
                'open parenthesis': '(',
                'close parenthesis': ')',
                'dash': '-',
                'hyphen': '-',
            };

            Object.keys(commands).forEach(function (cmd) {
                var regex = new RegExp('\\s*' + cmd + '\\s*', 'gi');
                text = text.replace(regex, commands[cmd]);
            });

            return text;
        },

        /**
         * Update UI
         */
        updateUI: function (isRecording) {
            var $btn = this.currentTarget ? this.currentTarget.siblings('.cqa-mic-btn') : null;

            if (!$btn || !$btn.length) return;

            if (isRecording) {
                $btn.addClass('recording');
                $btn.find('.dashicons')
                    .removeClass('dashicons-microphone')
                    .addClass('dashicons-controls-pause');

                // Add recording indicator
                if (!$btn.parent().find('.cqa-voice-indicator').length) {
                    $btn.parent().append(
                        '<div class="cqa-voice-indicator">' +
                        '<span class="pulse"></span> Listening...</div>'
                    );
                }
            } else {
                $btn.removeClass('recording');
                $btn.find('.dashicons')
                    .removeClass('dashicons-controls-pause')
                    .addClass('dashicons-microphone');

                $btn.parent().find('.cqa-voice-indicator').remove();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        CQA.VoiceToText.init();
    });

})(jQuery);
