<?php
/*
Plugin Name: USPS Tracking Hov
Description: Allows store managers and admins to add USPS tracking numbers to WooCommerce orders via a meta box.
Version: 1.0.0
Author: Nicholas Beeson
License: GPL2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure required functions are available for AJAX
if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
    // These will be loaded by WordPress automatically for AJAX requests
    // No need to manually require them
}

class WC_USPS_Tracking_Hov {
    
    /**
     * Debug helper function
     */
    private function debug_log( $message ) {
        // Try multiple debugging methods
        error_log( 'USPS Tracking Debug: ' . $message );
        
        // Also log to WordPress debug log if available
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( 'USPS Tracking Debug: ' . $message );
        }
        
        // Log to browser console for admin users (but NOT during AJAX requests)
        if ( is_admin() && current_user_can( 'manage_options' ) && ! defined( 'DOING_AJAX' ) ) {
            echo '<script>console.log("USPS Tracking Debug: ' . esc_js( $message ) . '");</script>';
        }
    }

    /**
     * Validate USPS tracking number format
     * 
     * @param string $tracking_number The tracking number to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_tracking_number( $tracking_number ) {
        // Remove any spaces or dashes
        $tracking_number = preg_replace( '/[\s\-]/', '', $tracking_number );
        
        // USPS tracking numbers are typically 20-22 characters, alphanumeric
        // Common formats: 9400100000000000000000, 9205500000000000000000, etc.
        
        // Basic validation: 20-22 characters, alphanumeric
        if ( ! preg_match( '/^[A-Z0-9]{20,22}$/i', $tracking_number ) ) {
            return false;
        }
        
        // Additional validation: Check for common USPS prefixes
        $usps_prefixes = array(
            '94001', '92055', '93033', '94073', '94016', '94018', '94019', '94020',
            '94021', '94022', '94023', '94024', '94025', '94026', '94027', '94028',
            '94029', '94030', '94031', '94032', '94033', '94034', '94035', '94036',
            '94037', '94038', '94039', '94040', '94041', '94042', '94043', '94044',
            '94045', '94046', '94047', '94048', '94049', '94050', '94051', '94052',
            '94053', '94054', '94055', '94056', '94057', '94058', '94059', '94060',
            '94061', '94062', '94063', '94064', '94065', '94066', '94067', '94068',
            '94069', '94070', '94071', '94072', '94073', '94074', '94075', '94076',
            '94077', '94078', '94079', '94080', '94081', '94082', '94083', '94084',
            '94085', '94086', '94087', '94088', '94089', '94090', '94091', '94092',
            '94093', '94094', '94095', '94096', '94097', '94098', '94099', '95001',
            '95002', '95003', '95004', '95005', '95006', '95007', '95008', '95009',
            '95010', '95011', '95012', '95013', '95014', '95015', '95016', '95017',
            '95018', '95019', '95020', '95021', '95022', '95023', '95024', '95025',
            '95026', '95027', '95028', '95029', '95030', '95031', '95032', '95033',
            '95034', '95035', '95036', '95037', '95038', '95039', '95040', '95041',
            '95042', '95043', '95044', '95045', '95046', '95047', '95048', '95049',
            '95050', '95051', '95052', '95053', '95054', '95055', '95056', '95057',
            '95058', '95059', '95060', '95061', '95062', '95063', '95064', '95065',
            '95066', '95067', '95068', '95069', '95070', '95071', '95072', '95073',
            '95074', '95075', '95076', '95077', '95078', '95079', '95080', '95081',
            '95082', '95083', '95084', '95085', '95086', '95087', '95088', '95089',
            '95090', '95091', '95092', '95093', '95094', '95095', '95096', '95097',
            '95098', '95099'
        );
        
        $prefix = substr( $tracking_number, 0, 5 );
        if ( ! in_array( $prefix, $usps_prefixes, true ) ) {
            // If it doesn't match known prefixes, still allow it but log for monitoring
            $this->debug_log( 'Unknown USPS tracking prefix: ' . $prefix . ' for tracking number: ' . $tracking_number );
        }
        
        return true;
    }
    
    public function __construct() {
        $this->debug_log( 'Plugin constructor called' );
        if ( is_admin() ) {
            $this->debug_log( 'Admin area detected, adding meta box hook' );
            add_action( 'add_meta_boxes', array( $this, 'add_usps_tracking_meta_box_hov' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        }
    }

    /**
     * Enqueue admin scripts and localize AJAX data
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on order edit pages
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
            return;
        }
        
        // Check if we're on an order edit page
        global $post;
        if ( ! $post || get_post_type( $post ) !== 'shop_order' ) {
            return;
        }
        
        // Enqueue our script
        wp_enqueue_script(
            'usps-tracking-admin',
            plugin_dir_url( __FILE__ ) . 'js/usps-tracking-admin.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'usps-tracking-admin',
            'usps_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'usps_tracking_nonce' ),
                'strings' => array(
                    'confirm_delete' => __( 'Are you sure you want to delete this tracking number?', 'woo-usps-tracking' ),
                    'enter_tracking' => __( 'Please enter a tracking number', 'woo-usps-tracking' ),
                    'success_add' => __( 'Tracking number added successfully!', 'woo-usps-tracking' ),
                    'success_delete' => __( 'Tracking number deleted successfully!', 'woo-usps-tracking' ),
                    'error_unknown' => __( 'Unknown error occurred', 'woo-usps-tracking' ),
                    'invalid_format' => __( 'Invalid USPS tracking number format. Please enter a valid 20-22 character tracking number.', 'woo-usps-tracking' ),
                    'already_exists' => __( 'This tracking number already exists.', 'woo-usps-tracking' ),
                    'error_adding' => __( 'Error adding tracking number. Please try again.', 'woo-usps-tracking' ),
                    'error_deleting' => __( 'Error deleting tracking number. Please try again.', 'woo-usps-tracking' ),
                    'bulk_processing' => __( 'Processing bulk tracking numbers...', 'woo-usps-tracking' ),
                    'bulk_success' => __( 'Bulk operation completed successfully!', 'woo-usps-tracking' ),
                    'bulk_error' => __( 'Error processing bulk tracking numbers. Please try again.', 'woo-usps-tracking' ),
                    'no_tracking_numbers' => __( 'No tracking numbers added yet.', 'woo-usps-tracking' ),
                    'delete' => __( 'Delete', 'woo-usps-tracking' )
                )
            )
        );
    }

    /**
     * Add the USPS Tracking meta box to the order edit screen (HPOS only).
     */
    public function add_usps_tracking_meta_box_hov() {
        $this->debug_log( 'add_usps_tracking_meta_box_hov called' );
        
        // Use HPOS screen
        $screen = wc_get_page_screen_id( 'shop-order' );
        $this->debug_log( 'Using screen: ' . $screen );

        add_meta_box(
            'wc_usps_tracking_meta_box',
            __( 'USPS Tracking Numbers', 'woo-usps-tracking' ),
            array( $this, 'render_usps_tracking_meta_box_hov' ),
            $screen,
            'normal',
            'high'
        );
        
        $this->debug_log( 'Meta box added successfully' );
    }

    /**
     * Render the USPS Tracking meta box (HPOS compatible).
     */
    public function render_usps_tracking_meta_box_hov( $post_or_order_object ) {
        $this->debug_log( 'render_usps_tracking_meta_box_hov called' );
        
        $order = $post_or_order_object;
        if ( ! $order ) {
            $this->debug_log( 'Order not found' );
            echo esc_html__( 'Order not found.', 'woo-usps-tracking' );
            return;
        }
        
        $this->debug_log( 'Order ID: ' . $order->get_id() );

        // Add hidden input for order ID (used by external JavaScript)
        echo '<input type="hidden" id="post_ID" value="' . $order->get_id() . '" />';

        // Get existing tracking numbers (array)
        $tracking_numbers = $order->get_meta( '_usps_tracking_numbers' );
        $this->debug_log( 'Raw tracking numbers: ' . print_r( $tracking_numbers, true ) );
        
        if ( ! is_array( $tracking_numbers ) ) {
            $this->debug_log( 'Tracking numbers is not array, converting to empty array' );
            $tracking_numbers = array();
        }
        
        $this->debug_log( 'Final tracking numbers count: ' . count( $tracking_numbers ) );

        // Add tracking number input with bulk operations
        echo '<div style="margin-bottom:1em;">';
        echo '<h4 style="margin-bottom:0.5em;">' . esc_html__( 'Add USPS Tracking Numbers:', 'woo-usps-tracking' ) . '</h4>';
        
        // Single tracking number input
        echo '<div style="margin-bottom:1em;">';
        echo '<label for="usps_tracking_number_input"><strong>' . esc_html__( 'Single Tracking Number:', 'woo-usps-tracking' ) . '</strong></label> ';
        echo '<input type="text" id="usps_tracking_number_input" name="usps_tracking_number" style="width:250px;" placeholder="Enter USPS tracking number" /> ';
        echo '<button type="button" class="button" id="usps_add_tracking_button">' . esc_html__( 'Add', 'woo-usps-tracking' ) . '</button>';
        echo '</div>';
        
        // Bulk tracking numbers input
        echo '<div style="margin-bottom:1em;">';
        echo '<label for="usps_bulk_tracking_input"><strong>' . esc_html__( 'Bulk Tracking Numbers:', 'woo-usps-tracking' ) . '</strong></label> ';
        echo '<textarea id="usps_bulk_tracking_input" name="usps_bulk_tracking_numbers" style="width:100%; height:80px;" placeholder="' . esc_attr__( 'Paste multiple tracking numbers, one per line or separated by commas', 'woo-usps-tracking' ) . '"></textarea> ';
        echo '<button type="button" class="button button-primary" id="usps_add_bulk_tracking_button">' . esc_html__( 'Add All', 'woo-usps-tracking' ) . '</button>';
        echo '</div>';
        
        echo '<p style="font-size:12px;color:#666;margin-top:0.5em;">' . esc_html__( 'Example: 9400100000000000000000', 'woo-usps-tracking' ) . '</p>';
        echo '</div>';

        // List tracking numbers
        echo '<div style="margin-top:1em;">';
        echo '<h4 style="margin-bottom:0.5em;">' . esc_html__( 'Current Tracking Numbers:', 'woo-usps-tracking' ) . '</h4>';
        
        if ( empty( $tracking_numbers ) ) {
            echo '<p style="color:#666;font-style:italic;">' . esc_html__( 'No tracking numbers added yet.', 'woo-usps-tracking' ) . '</p>';
            $this->debug_log( 'No tracking numbers to display' );
        } else {
            echo '<ul style="list-style:disc inside;">';
            foreach ( $tracking_numbers as $i => $number ) {
                $this->debug_log( 'Displaying tracking number: ' . $number );
                $url = 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=' . urlencode( $number );
                echo '<li>';
                echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $number ) . '</a> ';
                echo '<button type="button" class="button button-link-delete" name="usps_tracking_delete" value="' . esc_attr( $i ) . '">' . esc_html__( 'Delete', 'woo-usps-tracking' ) . '</button>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        
        $this->debug_log( 'Meta box render complete' );
    }

    /**
     * AJAX handler for adding tracking numbers
     */
    public function ajax_add_tracking_number() {
        $this->debug_log( 'ajax_add_tracking_number called' );
        
        // Log POST data for debugging
        $this->debug_log( 'POST data: ' . print_r( $_POST, true ) );
        
        // Check if we have the required data
        if ( ! isset( $_POST['order_id'] ) || ! isset( $_POST['tracking_number'] ) || ! isset( $_POST['nonce'] ) ) {
            $this->debug_log( 'AJAX missing required POST data' );
            wp_send_json_error( 'Missing required data' );
        }
        
        // Check nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'usps_tracking_nonce' ) ) {
            $this->debug_log( 'AJAX nonce verification failed' );
            wp_send_json_error( 'Security check failed' );
        }
        
        // Check permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            $this->debug_log( 'AJAX user does not have manage_woocommerce capability' );
            wp_die( 'Insufficient permissions' );
        }
        
        $order_id = intval( $_POST['order_id'] );
        $tracking_number = sanitize_text_field( $_POST['tracking_number'] );
        
        $this->debug_log( 'AJAX adding tracking number: ' . $tracking_number . ' to order: ' . $order_id );
        
        if ( empty( $tracking_number ) ) {
            $this->debug_log( 'AJAX tracking number is empty' );
            wp_send_json_error( 'Tracking number cannot be empty' );
        }
        
        // Validate tracking number format
        if ( ! $this->validate_tracking_number( $tracking_number ) ) {
            $this->debug_log( 'AJAX tracking number format is invalid: ' . $tracking_number );
            wp_send_json_error( __( 'Invalid USPS tracking number format. Please enter a valid 20-22 character tracking number.', 'woo-usps-tracking' ) );
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->debug_log( 'AJAX could not get order: ' . $order_id );
            wp_send_json_error( 'Order not found' );
        }
        
        $tracking_numbers = $order->get_meta( '_usps_tracking_numbers' );
        if ( ! is_array( $tracking_numbers ) ) {
            $tracking_numbers = array();
        }
        
        if ( in_array( $tracking_number, $tracking_numbers, true ) ) {
            $this->debug_log( 'AJAX tracking number already exists' );
            wp_send_json_error( __( 'This tracking number already exists.', 'woo-usps-tracking' ) );
        }
        
        $tracking_numbers[] = $tracking_number;
        $order->update_meta_data( '_usps_tracking_numbers', $tracking_numbers );
        $order->save();
        
        $this->debug_log( 'AJAX tracking number added successfully' );
        wp_send_json_success( 'Tracking number added successfully' );
    }

    /**
     * AJAX handler for adding multiple tracking numbers in bulk
     */
    public function ajax_add_bulk_tracking_numbers() {
        $this->debug_log( 'ajax_add_bulk_tracking_numbers called' );
        
        // Log POST data for debugging
        $this->debug_log( 'POST data: ' . print_r( $_POST, true ) );
        
        // Check if we have the required data
        if ( ! isset( $_POST['order_id'] ) || ! isset( $_POST['tracking_numbers'] ) || ! isset( $_POST['nonce'] ) ) {
            $this->debug_log( 'AJAX missing required POST data' );
            wp_send_json_error( 'Missing required data' );
        }
        
        // Check nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'usps_tracking_nonce' ) ) {
            $this->debug_log( 'AJAX nonce verification failed' );
            wp_send_json_error( 'Security check failed' );
        }
        
        // Check permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            $this->debug_log( 'AJAX user does not have manage_woocommerce capability' );
            wp_die( 'Insufficient permissions' );
        }
        
        $order_id = intval( $_POST['order_id'] );
        $tracking_numbers_raw = sanitize_textarea_field( $_POST['tracking_numbers'] );
        
        $this->debug_log( 'AJAX adding bulk tracking numbers to order: ' . $order_id );
        
        if ( empty( $tracking_numbers_raw ) ) {
            $this->debug_log( 'AJAX bulk tracking numbers is empty' );
            wp_send_json_error( __( 'Please enter at least one tracking number.', 'woo-usps-tracking' ) );
        }
        
        // Parse tracking numbers (split by newlines, commas, or spaces)
        $tracking_numbers_array = preg_split( '/[\r\n,,\s]+/', $tracking_numbers_raw );
        $tracking_numbers_array = array_filter( array_map( 'trim', $tracking_numbers_array ) );
        
        if ( empty( $tracking_numbers_array ) ) {
            $this->debug_log( 'AJAX no valid tracking numbers found after parsing' );
            wp_send_json_error( __( 'No valid tracking numbers found. Please check your input.', 'woo-usps-tracking' ) );
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->debug_log( 'AJAX could not get order: ' . $order_id );
            wp_send_json_error( 'Order not found' );
        }
        
        $existing_tracking_numbers = $order->get_meta( '_usps_tracking_numbers' );
        if ( ! is_array( $existing_tracking_numbers ) ) {
            $existing_tracking_numbers = array();
        }
        
        $added_count = 0;
        $skipped_count = 0;
        $invalid_count = 0;
        $errors = array();
        
        foreach ( $tracking_numbers_array as $tracking_number ) {
            $tracking_number = trim( $tracking_number );
            
            // Skip empty entries
            if ( empty( $tracking_number ) ) {
                continue;
            }
            
            // Validate tracking number format
            if ( ! $this->validate_tracking_number( $tracking_number ) ) {
                $invalid_count++;
                $errors[] = sprintf( __( 'Invalid format: %s', 'woo-usps-tracking' ), $tracking_number );
                continue;
            }
            
            // Check if already exists
            if ( in_array( $tracking_number, $existing_tracking_numbers, true ) ) {
                $skipped_count++;
                $errors[] = sprintf( __( 'Already exists: %s', 'woo-usps-tracking' ), $tracking_number );
                continue;
            }
            
            // Add to existing array
            $existing_tracking_numbers[] = $tracking_number;
            $added_count++;
        }
        
        // Save updated tracking numbers
        $order->update_meta_data( '_usps_tracking_numbers', $existing_tracking_numbers );
        $order->save();
        
        $this->debug_log( 'AJAX bulk operation complete. Added: ' . $added_count . ', Skipped: ' . $skipped_count . ', Invalid: ' . $invalid_count );
        
        // Prepare response message
        $message = sprintf( 
            __( 'Bulk operation complete. Added: %d, Skipped: %d, Invalid: %d', 'woo-usps-tracking' ),
            $added_count,
            $skipped_count,
            $invalid_count
        );
        
        wp_send_json_success( array(
            'message' => $message,
            'added' => $added_count,
            'skipped' => $skipped_count,
            'invalid' => $invalid_count,
            'errors' => $errors
        ) );
    }

    /**
     * AJAX handler for deleting tracking numbers
     */
    public function ajax_delete_tracking_number() {
        $this->debug_log( 'ajax_delete_tracking_number called' );
        
        // Check nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'usps_tracking_nonce' ) ) {
            $this->debug_log( 'AJAX nonce verification failed' );
            wp_send_json_error( 'Security check failed' );
        }
        
        // Check permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            $this->debug_log( 'AJAX user does not have manage_woocommerce capability' );
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $order_id = intval( $_POST['order_id'] );
        $index = intval( $_POST['index'] );
        
        $this->debug_log( 'AJAX deleting tracking number at index: ' . $index . ' from order: ' . $order_id );
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->debug_log( 'AJAX could not get order: ' . $order_id );
            wp_send_json_error( 'Order not found' );
        }
        
        $tracking_numbers = $order->get_meta( '_usps_tracking_numbers' );
        if ( ! is_array( $tracking_numbers ) ) {
            $tracking_numbers = array();
        }
        
        if ( ! isset( $tracking_numbers[ $index ] ) ) {
            $this->debug_log( 'AJAX index not found: ' . $index );
            wp_send_json_error( 'Tracking number not found' );
        }
        
        unset( $tracking_numbers[ $index ] );
        $tracking_numbers = array_values( $tracking_numbers );
        $order->update_meta_data( '_usps_tracking_numbers', $tracking_numbers );
        $order->save();
        
        $this->debug_log( 'AJAX tracking number deleted successfully' );
        wp_send_json_success( 'Tracking number deleted successfully' );
    }

    public function register_handlers() {
        $this->debug_log( 'Registering handlers' );
        
        // Register AJAX handlers
        add_action( 'wp_ajax_usps_tracking_add_number', array( $this, 'ajax_add_tracking_number' ) );
        add_action( 'wp_ajax_usps_tracking_add_bulk_numbers', array( $this, 'ajax_add_bulk_tracking_numbers' ) );
        add_action( 'wp_ajax_usps_tracking_delete_number', array( $this, 'ajax_delete_tracking_number' ) );
        
        $this->debug_log( 'AJAX handlers registered: wp_ajax_usps_tracking_add_number, wp_ajax_usps_tracking_delete_number' );
        
        // Debug: Check if actions are actually registered
        global $wp_filter;
        if ( isset( $wp_filter['wp_ajax_usps_tracking_add_number'] ) ) {
            $this->debug_log( 'AJAX handler wp_ajax_usps_tracking_add_number is registered' );
        } else {
            $this->debug_log( 'AJAX handler wp_ajax_usps_tracking_add_number is NOT registered' );
        }
    }

    public static function init_hov() {
        $instance = new self();
        $instance->register_handlers();
        add_action( 'woocommerce_order_details_after_order_table', array( $instance, 'display_tracking_on_order_page_hov' ) );
    }

    /**
     * Display USPS tracking numbers on the frontend order view page for customers.
     */
    public function display_tracking_on_order_page_hov( $order ) {
        $tracking_numbers = $order->get_meta( '_usps_tracking_numbers' );
        if ( ! empty( $tracking_numbers ) && is_array( $tracking_numbers ) ) {
            echo '<section class="woocommerce-usps-tracking" style="margin-top:2em;">';
            echo '<h3>' . esc_html__( 'USPS Tracking Numbers', 'woo-usps-tracking' ) . '</h3>';
            echo '<ul style="list-style:disc inside;">';
            foreach ( $tracking_numbers as $number ) {
                $url = 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=' . urlencode( $number );
                echo '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $number ) . '</a></li>';
            }
            echo '</ul>';
            echo '</section>';
        }
    }
}

add_action( 'plugins_loaded', function() {
    WC_USPS_Tracking_Hov::init_hov();
}); 