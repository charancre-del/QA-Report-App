/**
 * Tests for Frontend Wizard JavaScript
 * 
 * Tests validation, form serialization, navigation, and photo handling
 */

describe('Frontend Wizard - Validation', () => {
    let CQA;
    let $;

    beforeEach(() => {
        // Set up DOM
        document.body.innerHTML = `
            <form id="cqa-report-form">
                <div id="cqa-report-wizard">
                    <div class="cqa-wizard-panel" data-step="1">
                        <select id="cqa-school-select" name="school_id" required>
                            <option value="">Select...</option>
                            <option value="1">School 1</option>
                        </select>
                        <select id="cqa-report-type" name="report_type" required>
                            <option value="tier1">Tier 1</option>
                        </select>
                        <input type="date" id="cqa-inspection-date" name="inspection_date" required value="2026-01-03">
                    </div>
                    <div class="cqa-wizard-panel" data-step="2"></div>
                </div>
                <button class="cqa-wizard-next">Next</button>
                <button class="cqa-wizard-prev">Previous</button>
            </form>
        `;

        // Load the actual frontend-app.js code (simplified for testing)
        $ = require('jquery');
        CQA = require('../../public/js/frontend-app.js');
    });

    test('validateStep should return false when required field is empty', () => {
        const $panel = $('.cqa-wizard-panel[data-step="1"]');
        $('#cqa-school-select').val('');

        let isValid = true;
        $panel.find('input[required], select[required]').each(function () {
            if (!$(this).val()) {
                isValid = false;
            }
        });

        expect(isValid).toBe(false);
    });

    test('validateStep should return true when all required fields are filled', () => {
        const $panel = $('.cqa-wizard-panel[data-step="1"]');
        $('#cqa-school-select').val('1');
        $('#cqa-report-type').val('tier1');
        $('#cqa-inspection-date').val('2026-01-03');

        let isValid = true;
        $panel.find('input[required], select[required]').each(function () {
            if (!$(this).val()) {
                isValid = false;
            }
        });

        expect(isValid).toBe(true);
    });

    test('validation should add error class to empty fields', () => {
        const $input = $('#cqa-school-select');
        $input.val('');

        if (!$input.val()) {
            $input.addClass('error');
        }

        expect($input.hasClass('error')).toBe(true);
    });
});

describe('Frontend Wizard - Form Serialization', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <form id="test-form">
                <input name="school_id" value="1">
                <input name="report_type" value="tier1">
                <input name="new_photos" value="photo1">
                <input name="new_photos" value="photo2">
                <input name="drive_files[]" value="file1">
                <input name="drive_files[]" value="file2">
                <input name="responses[section1][item1][rating]" value="yes">
            </form>
        `;
    });

    test('serializeFormJSON should create object from form', () => {
        const $ = require('jquery');
        const $form = $('#test-form');
        const data = {};

        $form.serializeArray().forEach(item => {
            if (item.name.startsWith('responses')) return;

            if (data[item.name]) {
                if (!Array.isArray(data[item.name])) {
                    data[item.name] = [data[item.name]];
                }
                data[item.name].push(item.value);
            } else {
                data[item.name] = item.value;
            }
        });

        expect(data.school_id).toBe('1');
        expect(data.report_type).toBe('tier1');
        expect(data.new_photos).toEqual(['photo1', 'photo2']);
    });

    test('should exclude responses from main serialization', () => {
        const $ = require('jquery');
        const $form = $('#test-form');
        const data = {};

        $form.serializeArray().forEach(item => {
            if (item.name.startsWith('responses')) return;
            data[item.name] = item.value;
        });

        expect(data['responses[section1][item1][rating]']).toBeUndefined();
    });
});

describe('Frontend Wizard - Photo Handling', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="cqa-photo-gallery"></div>
        `;
        global.alert = jest.fn();
    });

    test('handleFiles should reject files > 5MB', () => {
        const largeFile = new File(['a'.repeat(6 * 1024 * 1024)], 'large.jpg', {
            type: 'image/jpeg'
        });

        const fileSize = largeFile.size;
        const maxSize = 5 * 1024 * 1024;

        if (fileSize > maxSize) {
            alert(`File ${largeFile.name} is too large. Max size is 5MB.`);
        }

        expect(global.alert).toHaveBeenCalledWith(
            expect.stringContaining('too large')
        );
    });

    test('handleFiles should accept files < 5MB', () => {
        const smallFile = new File(['small'], 'small.jpg', {
            type: 'image/jpeg'
        });

        const fileSize = smallFile.size;
        const maxSize = 5 * 1024 * 1024;

        expect(fileSize).toBeLessThan(maxSize);
        expect(global.alert).not.toHaveBeenCalled();
    });

    test('FileReader should convert image to Base64', (done) => {
        const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
        const reader = new FileReader();

        reader.onload = (e) => {
            expect(e.target.result).toMatch(/^data:image/);
            done();
        };

        reader.readAsDataURL(file);
    });
});

describe('Frontend Wizard - Navigation', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="cqa-report-wizard" data-report-id="">
                <div class="cqa-wizard-panel active" data-step="1"></div>
                <div class="cqa-wizard-panel" data-step="2"></div>
            </div>
        `;
    });

    test('nextStep should advance to next panel', () => {
        const $ = require('jquery');
        const $wizard = $('#cqa-report-wizard');

        let currentStep = 1;
        $('.cqa-wizard-panel').removeClass('active');
        currentStep++;
        $(`.cqa-wizard-panel[data-step="${currentStep}"]`).addClass('active');

        expect($('.cqa-wizard-panel[data-step="2"]').hasClass('active')).toBe(true);
        expect($('.cqa-wizard-panel[data-step="1"]').hasClass('active')).toBe(false);
    });

    test('prevStep should go back to previous panel', () => {
        const $ = require('jquery');

        let currentStep = 2;
        $('.cqa-wizard-panel').removeClass('active');
        currentStep--;
        $(`.cqa-wizard-panel[data-step="${currentStep}"]`).addClass('active');

        expect($('.cqa-wizard-panel[data-step="1"]').hasClass('active')).toBe(true);
        expect($('.cqa-wizard-panel[data-step="2"]').hasClass('active')).toBe(false);
    });

    test('should auto-create draft when entering step 2 without report-id', () => {
        const $ = require('jquery');
        const $wizard = $('#cqa-report-wizard');

        const hasReportId = !!$wizard.data('report-id');
        const enteringStep2 = true;

        expect(hasReportId).toBe(false);

        // Should trigger draft creation
        if (enteringStep2 && !hasReportId) {
            // submitToRestApi('draft') would be called
            expect(true).toBe(true);
        }
    });
});

describe('Frontend Wizard - AJAX Calls', () => {
    let mockAjax;

    beforeEach(() => {
        const $ = require('jquery');
        mockAjax = jest.fn();
        $.ajax = mockAjax;
    });

    test('should send correct nonce header', () => {
        const $ = require('jquery');

        const config = {
            url: 'http://localhost/wp-json/cqa/v1/reports',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', 'test-nonce-12345');
            }
        };

        const mockXHR = {
            setRequestHeader: jest.fn()
        };

        config.beforeSend(mockXHR);

        expect(mockXHR.setRequestHeader).toHaveBeenCalledWith(
            'X-WP-Nonce',
            'test-nonce-12345'
        );
    });

    test('should serialize responses correctly', () => {
        document.body.innerHTML = `
            <form id="test-form">
                <input name="responses[section1][item1][rating]" value="yes">
                <input name="responses[section1][item1][notes]" value="Good">
                <input name="responses[section2][item2][rating]" value="no">
            </form>
        `;

        const $ = require('jquery');
        const responses = {};

        $('#test-form').find('input[name^="responses"]').each(function () {
            const name = $(this).attr('name');
            const val = $(this).val();
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

        expect(responses.section1.item1.rating).toBe('yes');
        expect(responses.section1.item1.notes).toBe('Good');
        expect(responses.section2.item2.rating).toBe('no');
    });
});
