/**
 * Chroma QA Reports - Offline Manager
 *
 * Handles offline storage and sync with IndexedDB
 *
 * @package ChromaQAReports
 */

(function ($) {
    'use strict';

    window.CQA = window.CQA || {};

    /**
     * Offline Manager
     */
    CQA.OfflineManager = {
        db: null,
        isOnline: navigator.onLine,
        syncInProgress: false,

        /**
         * Initialize offline manager
         */
        init: function () {
            var self = this;

            this.openDatabase().then(function () {
                self.bindEvents();
                self.registerServiceWorker();
                self.updateOnlineStatus();
                self.checkPendingSync();
            });
        },

        /**
         * Open IndexedDB database
         */
        openDatabase: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                var request = indexedDB.open('cqa-offline', 2);

                request.onerror = function () {
                    console.error('Failed to open offline database');
                    reject(request.error);
                };

                request.onsuccess = function () {
                    self.db = request.result;
                    // console.log('Offline database ready');
                    resolve();
                };

                request.onupgradeneeded = function (e) {
                    var db = e.target.result;

                    // Draft reports store
                    if (!db.objectStoreNames.contains('drafts')) {
                        var draftsStore = db.createObjectStore('drafts', { keyPath: 'localId', autoIncrement: true });
                        draftsStore.createIndex('schoolId', 'schoolId', { unique: false });
                        draftsStore.createIndex('updatedAt', 'updatedAt', { unique: false });
                    }

                    // Pending photos store
                    if (!db.objectStoreNames.contains('pending-photos')) {
                        var photosStore = db.createObjectStore('pending-photos', { keyPath: 'localId', autoIncrement: true });
                        photosStore.createIndex('reportLocalId', 'reportLocalId', { unique: false });
                    }

                    // Cached schools store
                    if (!db.objectStoreNames.contains('schools')) {
                        db.createObjectStore('schools', { keyPath: 'id' });
                    }

                    // Cached checklists store
                    if (!db.objectStoreNames.contains('checklists')) {
                        db.createObjectStore('checklists', { keyPath: 'type' });
                    }
                };
            });
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            var self = this;

            // Online/offline detection
            window.addEventListener('online', function () {
                self.isOnline = true;
                self.updateOnlineStatus();
                self.attemptSync();
            });

            window.addEventListener('offline', function () {
                self.isOnline = false;
                self.updateOnlineStatus();
            });

            // Listen for service worker messages
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', function (event) {
                    self.handleServiceWorkerMessage(event.data);
                });
            }
        },

        /**
         * Register service worker
         */
        registerServiceWorker: function () {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/wp-content/plugins/chroma-qa-reports/service-worker.js')
                    .then(function (registration) {
                        // console.log('Service worker registered:', registration.scope);
                    })
                    .catch(function (error) {
                        console.error('Service worker registration failed:', error);
                    });
            }
        },

        /**
         * Update online status UI
         */
        updateOnlineStatus: function () {
            var $indicator = $('#offline-indicator');

            if (!$indicator.length) {
                $indicator = $('<div id="offline-indicator" class="cqa-offline-indicator"></div>');
                $('body').prepend($indicator);
            }

            if (this.isOnline) {
                $indicator.removeClass('offline').addClass('online');
                $indicator.html('<span class="dashicons dashicons-cloud"></span> Online');

                // Hide after 3 seconds when online
                setTimeout(function () {
                    $indicator.fadeOut();
                }, 3000);
            } else {
                $indicator.removeClass('online').addClass('offline');
                $indicator.html('<span class="dashicons dashicons-cloud-saved"></span> Offline Mode');
                $indicator.show();
            }
        },

        /**
         * Save draft report offline
         */
        saveDraft: function (reportData) {
            var self = this;

            return new Promise(function (resolve, reject) {
                if (!self.db) {
                    reject('Database not ready');
                    return;
                }

                var tx = self.db.transaction('drafts', 'readwrite');
                var store = tx.objectStore('drafts');

                reportData.updatedAt = new Date().toISOString();
                reportData.synced = false;

                var request = reportData.localId
                    ? store.put(reportData)
                    : store.add(reportData);

                request.onsuccess = function () {
                    reportData.localId = request.result;
                    // console.log('Draft saved offline:', reportData.localId);
                    resolve(reportData);
                };

                request.onerror = function () {
                    reject(request.error);
                };
            });
        },

        /**
         * Get all draft reports
         */
        getDrafts: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                if (!self.db) {
                    resolve([]);
                    return;
                }

                var tx = self.db.transaction('drafts', 'readonly');
                var store = tx.objectStore('drafts');
                var request = store.getAll();

                request.onsuccess = function () {
                    resolve(request.result || []);
                };

                request.onerror = function () {
                    reject(request.error);
                };
            });
        },

        /**
         * Get single draft
         */
        getDraft: function (localId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                if (!self.db) {
                    resolve(null);
                    return;
                }

                var tx = self.db.transaction('drafts', 'readonly');
                var store = tx.objectStore('drafts');
                var request = store.get(localId);

                request.onsuccess = function () {
                    resolve(request.result || null);
                };

                request.onerror = function () {
                    reject(request.error);
                };
            });
        },

        /**
         * Delete draft
         */
        deleteDraft: function (localId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                if (!self.db) {
                    reject('Database not ready');
                    return;
                }

                var tx = self.db.transaction('drafts', 'readwrite');
                var store = tx.objectStore('drafts');
                var request = store.delete(localId);

                request.onsuccess = function () {
                    resolve();
                };

                request.onerror = function () {
                    reject(request.error);
                };
            });
        },

        /**
         * Save photo for later upload
         */
        savePhoto: function (photoData) {
            var self = this;

            return new Promise(function (resolve, reject) {
                if (!self.db) {
                    reject('Database not ready');
                    return;
                }

                var tx = self.db.transaction('pending-photos', 'readwrite');
                var store = tx.objectStore('pending-photos');

                photoData.createdAt = new Date().toISOString();

                var request = store.add(photoData);

                request.onsuccess = function () {
                    photoData.localId = request.result;
                    // console.log('Photo queued for upload:', photoData.localId);
                    resolve(photoData);
                };

                request.onerror = function () {
                    reject(request.error);
                };
            });
        },

        /**
         * Get pending photos count
         */
        getPendingPhotosCount: function () {
            var self = this;

            return new Promise(function (resolve) {
                if (!self.db) {
                    resolve(0);
                    return;
                }

                var tx = self.db.transaction('pending-photos', 'readonly');
                var store = tx.objectStore('pending-photos');
                var request = store.count();

                request.onsuccess = function () {
                    resolve(request.result);
                };

                request.onerror = function () {
                    resolve(0);
                };
            });
        },

        /**
         * Cache schools for offline use
         */
        cacheSchools: function (schools) {
            var self = this;

            if (!this.db) return;

            var tx = this.db.transaction('schools', 'readwrite');
            var store = tx.objectStore('schools');

            schools.forEach(function (school) {
                store.put(school);
            });
        },

        /**
         * Get cached schools
         */
        getCachedSchools: function () {
            var self = this;

            return new Promise(function (resolve) {
                if (!self.db) {
                    resolve([]);
                    return;
                }

                var tx = self.db.transaction('schools', 'readonly');
                var store = tx.objectStore('schools');
                var request = store.getAll();

                request.onsuccess = function () {
                    resolve(request.result || []);
                };

                request.onerror = function () {
                    resolve([]);
                };
            });
        },

        /**
         * Cache checklist definitions
         */
        cacheChecklist: function (type, checklist) {
            if (!this.db) return;

            var tx = this.db.transaction('checklists', 'readwrite');
            var store = tx.objectStore('checklists');

            store.put({ type: type, data: checklist, cachedAt: new Date().toISOString() });
        },

        /**
         * Get cached checklist
         */
        getCachedChecklist: function (type) {
            var self = this;

            return new Promise(function (resolve) {
                if (!self.db) {
                    resolve(null);
                    return;
                }

                var tx = self.db.transaction('checklists', 'readonly');
                var store = tx.objectStore('checklists');
                var request = store.get(type);

                request.onsuccess = function () {
                    resolve(request.result ? request.result.data : null);
                };

                request.onerror = function () {
                    resolve(null);
                };
            });
        },

        /**
         * Check for pending sync items
         */
        checkPendingSync: function () {
            var self = this;

            Promise.all([
                this.getDrafts(),
                this.getPendingPhotosCount()
            ]).then(function (results) {
                var drafts = results[0].filter(function (d) { return !d.synced; });
                var photosCount = results[1];

                if (drafts.length > 0 || photosCount > 0) {
                    self.showSyncBadge(drafts.length, photosCount);

                    if (self.isOnline) {
                        self.attemptSync();
                    }
                }
            });
        },

        /**
         * Show sync badge
         */
        showSyncBadge: function (draftsCount, photosCount) {
            var $badge = $('#sync-badge');

            if (!$badge.length) {
                $badge = $('<div id="sync-badge" class="cqa-sync-badge"></div>');
                $('.cqa-header').append($badge);
            }

            var items = [];
            if (draftsCount > 0) items.push(draftsCount + ' draft' + (draftsCount > 1 ? 's' : ''));
            if (photosCount > 0) items.push(photosCount + ' photo' + (photosCount > 1 ? 's' : ''));

            $badge.html(
                '<span class="dashicons dashicons-update"></span> ' +
                items.join(', ') + ' pending sync'
            );
            $badge.show();
        },

        /**
         * Attempt to sync offline data
         */
        attemptSync: function () {
            var self = this;

            if (!this.isOnline || this.syncInProgress) return;

            this.syncInProgress = true;

            // Use Background Sync if available
            if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
                navigator.serviceWorker.ready.then(function (registration) {
                    return Promise.all([
                        registration.sync.register('sync-draft-reports'),
                        registration.sync.register('sync-photos')
                    ]);
                }).then(function () {
                    // console.log('Background sync registered');
                }).catch(function (error) {
                    console.error('Background sync failed, falling back:', error);
                    self.manualSync();
                });
            } else {
                // Fallback to manual sync
                this.manualSync();
            }
        },

        /**
         * Manual sync fallback
         */
        manualSync: function () {
            var self = this;

            this.getDrafts().then(function (drafts) {
                var unsyncedDrafts = drafts.filter(function (d) { return !d.synced; });

                var promises = unsyncedDrafts.map(function (draft) {
                    return CQA.api.post('reports/drafts', draft).then(function (response) {
                        draft.synced = true;
                        draft.serverId = response.id;
                        return self.saveDraft(draft);
                    });
                });

                return Promise.all(promises);
            }).then(function () {
                self.syncInProgress = false;
                $('#sync-badge').fadeOut();
                CQA.notify.success('Offline data synced successfully!');
            }).catch(function (error) {
                self.syncInProgress = false;
                console.error('Manual sync failed:', error);
            });
        },

        /**
         * Handle service worker messages
         */
        handleServiceWorkerMessage: function (data) {
            if (data.type === 'SYNC_COMPLETE') {
                if (data.success) {
                    $('#sync-badge').fadeOut();
                    CQA.notify.success('Background sync completed!');
                }
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        CQA.OfflineManager.init();
    });

})(jQuery);
