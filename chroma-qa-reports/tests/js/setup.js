// Jest setup file for WordPress and jQuery mocking

// Mock jQuery
global.$ = global.jQuery = require('jquery');

// Mock WordPress localized scripts
global.cqaFrontend = {
    restUrl: 'http://localhost/wp-json/cqa/v1/',
    nonce: 'test-nonce-12345',
    homeUrl: 'http://localhost',
    googleClientId: 'test-client-id',
    developerKey: 'test-developer-key',
    strings: {
        saving: 'Saving...',
        error: 'An error occurred'
    }
};

global.cqaAdmin = {
    restUrl: 'http://localhost/wp-json/cqa/v1/',
    nonce: 'test-nonce-12345',
    googleClientId: 'test-client-id',
    developerKey: 'test-developer-key'
};

// Mock gapi for Google APIs
global.gapi = {
    load: jest.fn((api, options) => {
        if (options && options.callback) {
            options.callback();
        }
    }),
    client: {
        init: jest.fn(),
        getToken: jest.fn(() => ({ access_token: 'test-token' }))
    },
    auth2: {
        getAuthInstance: jest.fn(() => ({
            signIn: jest.fn(() => Promise.resolve())
        }))
    }
};

global.google = {
    picker: {
        PickerBuilder: jest.fn().mockReturnValue({
            addView: jest.fn().mockReturnThis(),
            setOAuthToken: jest.fn().mockReturnThis(),
            setDeveloperKey: jest.fn().mockReturnThis(),
            setCallback: jest.fn().mockReturnThis(),
            build: jest.fn(() => ({
                setVisible: jest.fn()
            }))
        }),
        ViewId: {
            DOCS: 'DOCS',
            PHOTOS: 'PHOTOS',
            FOLDERS: 'FOLDERS'
        },
        Response: {
            ACTION: 'action',
            DOCUMENTS: 'docs'
        },
        Action: {
            PICKED: 'picked'
        },
        Document: {
            ID: 'id',
            NAME: 'name',
            URL: 'url',
            ICON_URL: 'iconUrl'
        }
    }
};

// Mock window.location
delete window.location;
window.location = {
    href: 'http://localhost',
    assign: jest.fn(),
    reload: jest.fn()
};

// Mock window.scrollTo
window.scrollTo = jest.fn();

// Mock FileReader
global.FileReader = class {
    constructor() {
        this.onload = null;
        this.onerror = null;
    }

    readAsDataURL(file) {
        setTimeout(() => {
            if (this.onload) {
                this.onload({
                    target: {
                        result: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg=='
                    }
                });
            }
        }, 0);
    }
};

// Mock alert
global.alert = jest.fn();

// Mock confirm
global.confirm = jest.fn(() => true);
