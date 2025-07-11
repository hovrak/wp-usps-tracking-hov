/**
 * USPS Tracking Admin JavaScript
 * Handles adding and deleting tracking numbers via AJAX
 */
jQuery(document).ready(function($) {
    'use strict';
    
    /**
     * Show WordPress admin notice
     * @param {string} message - The message to display
     * @param {string} type - The notice type (success, error, warning, info)
     */
    function showAdminNotice(message, type) {
        // Remove any existing notices first
        $('.usps-tracking-notice').remove();
        
        // Create the notice HTML
        var noticeClass = 'notice notice-' + type + ' is-dismissible usps-tracking-notice';
        var noticeHtml = '<div class="' + noticeClass + '"><p>' + message + '</p></div>';
        
        // Add the notice to the page
        $('.wrap h1').after(noticeHtml);
        
        // Make it dismissible if WordPress has the functionality
        if (typeof wp !== 'undefined' && wp.media && wp.media.view) {
            // WordPress 4.2+ dismissible notices
            $('.usps-tracking-notice').on('click', '.notice-dismiss', function() {
                $(this).parent().fadeOut();
            });
        }
        
        // Auto-hide success notices after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $('.usps-tracking-notice').fadeOut();
            }, 5000);
        }
    }
    
    /**
     * Helper to refresh the tracking numbers list div via AJAX
     */
    function refreshTrackingNumbersList() {
        var orderId = $('#post_ID').val();
        $.post(usps_ajax.ajax_url, {
            action: 'usps_tracking_get_numbers_html',
            order_id: orderId,
            nonce: usps_ajax.nonce
        }, function(response) {
            if (response.success && response.data) {
                $('#usps-tracking-numbers-list').replaceWith(response.data);
            }
        });
    }
    
    
    // Handle Add button click
    $('#usps_add_tracking_button').on('click', function(e) {
        e.preventDefault();
        console.log('USPS Tracking: Add button clicked');
        
        var trackingInput = $('#usps_tracking_number_input');
        var trackingNumber = trackingInput.val().trim();
        
        if (!trackingNumber) {
            showAdminNotice(usps_ajax.strings.validation_empty, 'error');
            trackingInput.focus();
            return;
        }
        
        // Client-side validation
        var cleanTracking = trackingNumber.replace(/[\s\-]/g, '');
        if (cleanTracking.length < 20 || cleanTracking.length > 22) {
            showAdminNotice(usps_ajax.strings.validation_length, 'error');
            trackingInput.focus();
            return;
        }
        
        if (!/^[A-Z0-9]+$/i.test(cleanTracking)) {
            showAdminNotice(usps_ajax.strings.validation_characters, 'error');
            trackingInput.focus();
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true).text(usps_ajax.strings.loading_add);
        
        console.log('USPS Tracking: Adding tracking number:', trackingNumber);
        
        // Create form data
        var formData = new FormData();
        formData.append('action', 'usps_tracking_add_number');
        formData.append('order_id', $('#post_ID').val());
        formData.append('tracking_number', trackingNumber);
        formData.append('nonce', usps_ajax.nonce);
        
        // Send AJAX request
        $.ajax({
            url: usps_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('USPS Tracking: AJAX response:', response);
                if (response.success) {
                    showAdminNotice(response.data.message || usps_ajax.strings.success_add, 'success');
                    // Clear the input field
                    $('#usps_tracking_number_input').val('');
                    // Refresh the tracking numbers list
                    refreshTrackingNumbersList();
                } else {
                    showAdminNotice('Error: ' + (response.data || usps_ajax.strings.error_unknown), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('USPS Tracking: AJAX error:', error);
                var errorMessage = usps_ajax.strings.error_unknown;
                
                if (status === 'timeout') {
                    errorMessage = usps_ajax.strings.error_timeout;
                } else if (status === 'error') {
                    if (xhr.status === 403) {
                        errorMessage = usps_ajax.strings.error_permission;
                    } else if (xhr.status === 404) {
                        errorMessage = usps_ajax.strings.error_order_not_found;
                    } else if (xhr.status >= 500) {
                        errorMessage = usps_ajax.strings.error_server;
                    } else if (xhr.status === 0) {
                        errorMessage = usps_ajax.strings.error_network;
                    }
                }
                
                showAdminNotice('Error: ' + errorMessage, 'error');
            },
            complete: function() {
                // Restore button state
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Handle Delete button clicks
    $(document).on('click', 'button[name="usps_tracking_delete"]', function(e) {
        e.preventDefault();
        
        if (!confirm(usps_ajax.strings.confirm_delete)) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        var index = $button.val();
        
        // Show loading state
        $button.prop('disabled', true).text(usps_ajax.strings.loading_delete);
        
        console.log('USPS Tracking: Deleting tracking number at index:', index);
        
        // Create form data
        var formData = new FormData();
        formData.append('action', 'usps_tracking_delete_number');
        formData.append('order_id', $('#post_ID').val());
        formData.append('index', index);
        formData.append('nonce', usps_ajax.nonce);
        
        // Send AJAX request
        $.ajax({
            url: usps_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('USPS Tracking: AJAX response:', response);
                if (response.success) {
                    showAdminNotice(response.data.message || usps_ajax.strings.success_delete, 'success');
                    // Refresh the tracking numbers list
                    refreshTrackingNumbersList();
                } else {
                    showAdminNotice('Error: ' + (response.data || usps_ajax.strings.error_unknown), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('USPS Tracking: AJAX error:', error);
                var errorMessage = usps_ajax.strings.error_unknown;
                
                if (status === 'timeout') {
                    errorMessage = usps_ajax.strings.error_timeout;
                } else if (status === 'error') {
                    if (xhr.status === 403) {
                        errorMessage = usps_ajax.strings.error_permission;
                    } else if (xhr.status === 404) {
                        errorMessage = usps_ajax.strings.error_order_not_found;
                    } else if (xhr.status >= 500) {
                        errorMessage = usps_ajax.strings.error_server;
                    } else if (xhr.status === 0) {
                        errorMessage = usps_ajax.strings.error_network;
                    }
                }
                
                showAdminNotice('Error: ' + errorMessage, 'error');
            },
            complete: function() {
                // Restore button state
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
}); 