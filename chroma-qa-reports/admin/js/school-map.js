/**
 * Chroma QA Reports - School Map
 *
 * Google Maps integration for heat map view
 *
 * @package ChromaQAReports
 */

(function ($) {
    'use strict';

    window.CQA = window.CQA || {};

    /**
     * School Map functionality
     */
    CQA.SchoolMap = {
        map: null,
        markers: [],
        infoWindow: null,
        bounds: null,

        /**
         * Initialize map
         */
        init: function () {
            if (!$('#school-map').length) return;

            // Check for Google Maps API
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                console.error('Google Maps API not loaded');
                this.showErrorMessage();
                return;
            }

            this.createMap();
            this.loadSchools();
            this.bindEvents();
        },

        /**
         * Create map instance
         */
        createMap: function () {
            var mapOptions = {
                center: { lat: 33.7490, lng: -84.3880 }, // Atlanta, GA default
                zoom: 10,
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: true,
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            };

            this.map = new google.maps.Map(
                document.getElementById('school-map'),
                mapOptions
            );

            this.infoWindow = new google.maps.InfoWindow();
            this.bounds = new google.maps.LatLngBounds();
        },

        /**
         * Load schools from API
         */
        loadSchools: function () {
            var self = this;

            CQA.api.get('analytics/regional').done(function (schools) {
                schools.forEach(function (school) {
                    self.addMarker(school);
                });

                // Fit map to markers
                if (self.markers.length > 0) {
                    self.map.fitBounds(self.bounds);
                }

                self.updateLegend(schools);
            }).fail(function () {
                console.error('Failed to load schools');
            });
        },

        /**
         * Add marker for school
         */
        addMarker: function (school) {
            var self = this;

            // Skip if no coordinates
            if (!school.latitude || !school.longitude) {
                // Try to geocode
                this.geocodeSchool(school);
                return;
            }

            var position = {
                lat: parseFloat(school.latitude),
                lng: parseFloat(school.longitude)
            };

            // Extend bounds
            this.bounds.extend(position);

            // Get marker color based on rating
            var color = this.getRatingColor(school.rating);

            var marker = new google.maps.Marker({
                position: position,
                map: this.map,
                title: school.school_name,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 12,
                    fillColor: color,
                    fillOpacity: 0.9,
                    strokeColor: '#ffffff',
                    strokeWeight: 2
                }
            });

            // Store school data on marker
            marker.schoolData = school;
            this.markers.push(marker);

            // Click handler
            marker.addListener('click', function () {
                self.showInfoWindow(marker);
            });
        },

        /**
         * Get color based on rating
         */
        getRatingColor: function (rating) {
            switch (rating) {
                case 'exceeds':
                    return '#22c55e'; // Green
                case 'meets':
                    return '#f59e0b'; // Yellow/Orange
                case 'needs_improvement':
                    return '#ef4444'; // Red
                default:
                    return '#9ca3af'; // Gray
            }
        },

        /**
         * Show info window for marker
         */
        showInfoWindow: function (marker) {
            var school = marker.schoolData;
            var ratingClass = school.rating ? school.rating.replace('_', '-') : 'unknown';
            var lastVisit = school.last_visit
                ? new Date(school.last_visit).toLocaleDateString()
                : 'Never';

            var content = `
                <div class="cqa-map-info">
                    <h4>${school.school_name}</h4>
                    <p class="rating ${ratingClass}">
                        ${school.rating ? school.rating.replace('_', ' ').toUpperCase() : 'No Reports'}
                    </p>
                    <p><strong>Region:</strong> ${school.region || 'N/A'}</p>
                    <p><strong>Last Visit:</strong> ${lastVisit}</p>
                    <div class="info-actions">
                        <a href="${cqaAdmin.adminUrl}?page=chroma-qa-reports-schools&id=${school.school_id}" class="button button-small">
                            View School
                        </a>
                        <a href="${cqaAdmin.adminUrl}?page=chroma-qa-reports-create&school_id=${school.school_id}" class="button button-small button-primary">
                            + New Report
                        </a>
                    </div>
                </div>
            `;

            this.infoWindow.setContent(content);
            this.infoWindow.open(this.map, marker);
        },

        /**
         * Geocode school address
         */
        geocodeSchool: function (school) {
            var self = this;

            if (!school.address) return;

            var geocoder = new google.maps.Geocoder();

            geocoder.geocode({ address: school.address }, function (results, status) {
                if (status === 'OK' && results[0]) {
                    var location = results[0].geometry.location;
                    school.latitude = location.lat();
                    school.longitude = location.lng();

                    // Save coordinates to server
                    CQA.api.post('schools/' + school.school_id, {
                        latitude: school.latitude,
                        longitude: school.longitude
                    });

                    self.addMarker(school);
                }
            });
        },

        /**
         * Update legend with counts
         */
        updateLegend: function (schools) {
            var counts = {
                exceeds: 0,
                meets: 0,
                needs_improvement: 0,
                none: 0
            };

            schools.forEach(function (school) {
                if (school.rating) {
                    counts[school.rating]++;
                } else {
                    counts.none++;
                }
            });

            var html = `
                <div class="cqa-map-legend">
                    <h4>School Ratings</h4>
                    <div class="legend-item">
                        <span class="dot exceeds"></span>
                        Exceeds (${counts.exceeds})
                    </div>
                    <div class="legend-item">
                        <span class="dot meets"></span>
                        Meets (${counts.meets})
                    </div>
                    <div class="legend-item">
                        <span class="dot needs-improvement"></span>
                        Needs Improvement (${counts.needs_improvement})
                    </div>
                    <div class="legend-item">
                        <span class="dot none"></span>
                        No Reports (${counts.none})
                    </div>
                </div>
            `;

            $('#map-legend').html(html);
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            var self = this;

            // Filter by rating
            $(document).on('change', '#map-filter-rating', function () {
                var rating = $(this).val();
                self.filterMarkers(rating);
            });

            // Filter by region
            $(document).on('change', '#map-filter-region', function () {
                var region = $(this).val();
                self.filterByRegion(region);
            });

            // Recenter map
            $(document).on('click', '#map-recenter', function () {
                self.map.fitBounds(self.bounds);
            });
        },

        /**
         * Filter markers by rating
         */
        filterMarkers: function (rating) {
            this.markers.forEach(function (marker) {
                if (!rating || marker.schoolData.rating === rating) {
                    marker.setVisible(true);
                } else {
                    marker.setVisible(false);
                }
            });
        },

        /**
         * Filter markers by region
         */
        filterByRegion: function (region) {
            var filteredBounds = new google.maps.LatLngBounds();
            var hasVisible = false;

            this.markers.forEach(function (marker) {
                if (!region || marker.schoolData.region === region) {
                    marker.setVisible(true);
                    filteredBounds.extend(marker.getPosition());
                    hasVisible = true;
                } else {
                    marker.setVisible(false);
                }
            });

            if (hasVisible) {
                this.map.fitBounds(filteredBounds);
            }
        },

        /**
         * Show error message
         */
        showErrorMessage: function () {
            $('#school-map').html(
                '<div class="cqa-map-error">' +
                '<span class="dashicons dashicons-location-alt"></span>' +
                '<p>Map could not be loaded.</p>' +
                '<p>Please check Google Maps API configuration in Settings.</p>' +
                '</div>'
            );
        }
    };

    // Initialize when map container exists
    $(document).ready(function () {
        if ($('#school-map').length) {
            // Wait for Google Maps to load
            if (typeof google !== 'undefined' && google.maps) {
                CQA.SchoolMap.init();
            } else {
                // Try again after a delay
                setTimeout(function () {
                    CQA.SchoolMap.init();
                }, 1000);
            }
        }
    });

})(jQuery);
