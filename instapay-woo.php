<?php
/**
 * Plugin Name: Instapay Gateway for Egypt
 * Description: Instapay payment gateway for WooCommerce with secure receipt upload and manager verification.
 * Version: 1.2.0
 * Requires at least: 6.3
 * Tested up to: 7.0
 * WC requires at least: 8.5
 * WC tested up to: 10.7
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Recipe Codes
 * Author URI: https://recipe.codes
 * Text Domain: instapay-gateway-for-egypt
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'INSTAPAY_WOO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INSTAPAY_WOO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INSTAPAY_WOO_VERSION', '1.2.0' );

add_action( 'before_woocommerce_init', 'instapay_woo_declare_wc_compatibility' );
function instapay_woo_declare_wc_compatibility() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}

function instapay_woo_get_receipts_dir() {
    $upload_dir = wp_upload_dir();

    return trailingslashit( $upload_dir['basedir'] ) . 'instapay_receipts';
}

function instapay_woo_get_receipt_view_url( $order, $admin = false ) {
    $args = array(
        'instapay_view_receipt' => 1,
        'order_id'              => $order->get_id(),
        '_iwvnonce'             => wp_create_nonce( 'instapay_view_receipt_' . $order->get_id() ),
    );

    if ( ! $admin ) {
        $args['order_key'] = $order->get_order_key();
    }

    return add_query_arg( $args, $admin ? admin_url() : home_url( '/' ) );
}

function instapay_woo_order_accepts_receipt( $order ) {
    return $order instanceof WC_Order
        && 'instapay' === $order->get_payment_method()
        && in_array( $order->get_status(), array( 'pending', 'failed', 'payment-review' ), true );
}

function instapay_woo_get_uploaded_file() {
    if ( empty( $_FILES['instapay_receipt'] ) || ! is_array( $_FILES['instapay_receipt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return new WP_Error( 'missing_file', __( 'No file was uploaded.', 'instapay-gateway-for-egypt' ) );
    }

    $file = wp_unslash( $_FILES['instapay_receipt'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( ! isset( $file['name'], $file['tmp_name'], $file['error'], $file['size'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
        return new WP_Error( 'upload_error', __( 'No file uploaded or an upload error occurred.', 'instapay-gateway-for-egypt' ) );
    }

    return array(
        'name'     => sanitize_file_name( $file['name'] ),
        'type'     => isset( $file['type'] ) ? sanitize_mime_type( $file['type'] ) : '',
        'tmp_name' => $file['tmp_name'],
        'error'    => (int) $file['error'],
        'size'     => (int) $file['size'],
    );
}

function instapay_woo_acquire_upload_lock( $order_id ) {
    $lock_name = 'instapay_woo_upload_lock_' . absint( $order_id );
    $locked_at = (int) get_option( $lock_name, 0 );

    if ( $locked_at && ( time() - $locked_at ) < 120 ) {
        return new WP_Error( 'upload_in_progress', __( 'A receipt upload is already in progress. Please wait and try again.', 'instapay-gateway-for-egypt' ) );
    }

    if ( $locked_at ) {
        delete_option( $lock_name );
    }

    if ( ! add_option( $lock_name, time(), '', false ) ) {
        return new WP_Error( 'upload_in_progress', __( 'A receipt upload is already in progress. Please wait and try again.', 'instapay-gateway-for-egypt' ) );
    }

    return $lock_name;
}

function instapay_woo_release_upload_lock( $lock_name ) {
    if ( $lock_name ) {
        delete_option( $lock_name );
    }
}

function instapay_woo_get_valid_receipt_path( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return '';
    }

    $receipt_path = $order->get_meta( '_instapay_receipt_path' );
    $receipt_file = $order->get_meta( '_instapay_receipt_filename' );
    $receipts_dir = instapay_woo_get_receipts_dir();

    if ( empty( $receipt_path ) || ! file_exists( $receipt_path ) || ! is_dir( $receipts_dir ) ) {
        return '';
    }

    $real_receipt_path = realpath( $receipt_path );
    $real_receipts_dir = realpath( $receipts_dir );
    if ( ! $real_receipt_path || ! $real_receipts_dir ) {
        return '';
    }

    $normalized_path = wp_normalize_path( $real_receipt_path );
    $normalized_dir  = trailingslashit( wp_normalize_path( $real_receipts_dir ) );
    if ( 0 !== strpos( $normalized_path, $normalized_dir ) ) {
        return '';
    }

    if ( $receipt_file && basename( $normalized_path ) !== $receipt_file ) {
        return '';
    }

    $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp' );
    $image_mime    = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $normalized_path ) : '';
    if ( ! in_array( $image_mime, $allowed_mimes, true ) ) {
        return '';
    }

    return $normalized_path;
}

function instapay_woo_delete_receipt( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $receipt_path = instapay_woo_get_valid_receipt_path( $order_id );
    if ( $receipt_path ) {
        wp_delete_file( $receipt_path );
    }

    $order->delete_meta_data( '_instapay_receipt_path' );
    $order->delete_meta_data( '_instapay_receipt_filename' );
    $order->save();
}

function instapay_woo_use_receipts_upload_dir( $upload_dir ) {
    $upload_dir['path']   = instapay_woo_get_receipts_dir();
    $upload_dir['url']    = '';
    $upload_dir['subdir'] = '';

    return $upload_dir;
}

function instapay_woo_ensure_receipts_dir() {
    $receipts_dir = instapay_woo_get_receipts_dir();
    if ( ! wp_mkdir_p( $receipts_dir ) ) {
        return new WP_Error( 'directory_creation_failed', __( 'Could not create the receipt upload directory.', 'instapay-gateway-for-egypt' ) );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    global $wp_filesystem;

    if ( $wp_filesystem ) {
        $apache_rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
        $iis_rules    = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\" /><add accessType=\"Deny\" users=\"*\" /></authorization></security></system.webServer></configuration>\n";
        $wp_filesystem->put_contents( trailingslashit( $receipts_dir ) . '.htaccess', $apache_rules, FS_CHMOD_FILE );
        $wp_filesystem->put_contents( trailingslashit( $receipts_dir ) . 'web.config', $iis_rules, FS_CHMOD_FILE );
        $wp_filesystem->put_contents( trailingslashit( $receipts_dir ) . 'index.php', "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
    }

    return $receipts_dir;
}

// Check if WooCommerce is active on activation
register_activation_hook( __FILE__, 'instapay_woo_activation_check' );
function instapay_woo_activation_check() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        if ( is_multisite() ) {
            $plugins = get_site_option( 'active_sitewide_plugins' );
            if ( isset( $plugins['woocommerce/woocommerce.php'] ) ) {
                instapay_woo_schedule_cleanup();
                return;
            }
        }
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( 'Instapay Gateway for Egypt requires WooCommerce to be installed and active.', 'instapay-gateway-for-egypt' ), esc_html__( 'Plugin Dependency Error', 'instapay-gateway-for-egypt' ), array( 'back_link' => true ) );
    }

    instapay_woo_schedule_cleanup();
}

register_deactivation_hook( __FILE__, 'instapay_woo_deactivate' );
function instapay_woo_deactivate() {
    wp_clear_scheduled_hook( 'instapay_woo_cleanup_cron' );
}

function instapay_woo_schedule_cleanup() {
    if ( ! wp_next_scheduled( 'instapay_woo_cleanup_cron' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'instapay_woo_cleanup_cron' );
    }
}

// Load text domain for translations
add_action( 'plugins_loaded', 'instapay_woo_load_textdomain' );
function instapay_woo_load_textdomain() {
    load_plugin_textdomain( 'instapay-gateway-for-egypt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Add custom order status for payment review
add_action( 'init', 'instapay_woo_register_order_status' );
function instapay_woo_register_order_status() {
    register_post_status( 'wc-payment-review', array(
        'label'                     => _x( 'Payment Review', 'Order status', 'instapay-gateway-for-egypt' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        /* translators: %s: count */
        'label_count'               => _n_noop( 'Payment Review <span class="count">(%s)</span>', 'Payment Review <span class="count">(%s)</span>', 'instapay-gateway-for-egypt' )
    ) );
}

add_filter( 'wc_order_statuses', 'instapay_woo_add_order_status' );
function instapay_woo_add_order_status( $order_statuses ) {
    $new_statuses = array();
    foreach ( $order_statuses as $key => $status ) {
        $new_statuses[ $key ] = $status;
        // Insert it right after 'on-hold'
        if ( 'wc-on-hold' === $key ) {
            $new_statuses['wc-payment-review'] = __( 'Payment Review', 'instapay-gateway-for-egypt' );
        }
    }
    // Fallback if on-hold wasn't found
    if ( ! isset( $new_statuses['wc-payment-review'] ) ) {
        $new_statuses['wc-payment-review'] = __( 'Payment Review', 'instapay-gateway-for-egypt' );
    }
    return $new_statuses;
}

// Initialize gateway class
add_action( 'plugins_loaded', 'instapay_woo_init_gateway' );
function instapay_woo_init_gateway() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    require_once INSTAPAY_WOO_PLUGIN_DIR . 'includes/class-wc-gateway-instapay.php';
}

add_action( 'wp_enqueue_scripts', 'instapay_woo_enqueue_frontend_assets' );
function instapay_woo_enqueue_frontend_assets() {
    if ( ! function_exists( 'is_checkout' ) || ( ! is_checkout() && ! is_account_page() ) ) {
        return;
    }

    wp_enqueue_style( 'instapay-gateway-for-egypt', INSTAPAY_WOO_PLUGIN_URL . 'assets/css/gateway.css', array( 'dashicons' ), INSTAPAY_WOO_VERSION );
    wp_enqueue_script( 'instapay-gateway-for-egypt', INSTAPAY_WOO_PLUGIN_URL . 'assets/js/gateway.js', array( 'jquery' ), INSTAPAY_WOO_VERSION, true );
}

add_action( 'admin_enqueue_scripts', 'instapay_woo_enqueue_admin_assets' );
function instapay_woo_enqueue_admin_assets() {
    $screen          = get_current_screen();
    $screen_id       = $screen ? $screen->id : '';
    $allowed_screens = array( 'dashboard', 'shop_order', 'woocommerce_page_wc-orders', 'woocommerce_page_wc-settings' );

    if ( ! in_array( $screen_id, $allowed_screens, true ) ) {
        return;
    }

    wp_enqueue_style( 'instapay-gateway-for-egypt', INSTAPAY_WOO_PLUGIN_URL . 'assets/css/gateway.css', array(), INSTAPAY_WOO_VERSION );
    wp_enqueue_script( 'instapay-gateway-for-egypt', INSTAPAY_WOO_PLUGIN_URL . 'assets/js/gateway.js', array( 'jquery' ), INSTAPAY_WOO_VERSION, true );
    wp_localize_script(
        'instapay-gateway-for-egypt',
        'instapayWooAdmin',
        array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'errorText'  => __( 'An unexpected error occurred. Please try again.', 'instapay-gateway-for-egypt' ),
            'processing' => __( 'Processing...', 'instapay-gateway-for-egypt' ),
        )
    );
}

add_filter( 'woocommerce_payment_gateways', 'instapay_woo_add_gateway' );
function instapay_woo_add_gateway( $methods ) {
    $methods[] = 'WC_Gateway_Instapay';
    return $methods;
}

// Handle secure file download for admin and customer
add_action( 'init', 'instapay_woo_secure_image_view' );
function instapay_woo_secure_image_view() {
    if ( ! isset( $_GET['instapay_view_receipt'], $_GET['order_id'], $_GET['_iwvnonce'] ) ) {
        return;
    }

    $order_id  = absint( wp_unslash( $_GET['order_id'] ) );
    $nonce     = sanitize_text_field( wp_unslash( $_GET['_iwvnonce'] ) );
    $order_key = isset( $_GET['order_key'] ) ? wc_clean( wp_unslash( $_GET['order_key'] ) ) : '';
    $order     = wc_get_order( $order_id );

    if ( ! $order || ! wp_verify_nonce( $nonce, 'instapay_view_receipt_' . $order_id ) ) {
        wp_die( esc_html__( 'Invalid security token.', 'instapay-gateway-for-egypt' ) );
    }

    $can_view = current_user_can( 'edit_shop_orders' );
    if ( ! $can_view && is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() ) {
        $can_view = true;
    }
    if ( ! $can_view && $order_key ) {
        $can_view = hash_equals( (string) $order->get_order_key(), (string) $order_key );
    }

    if ( ! $can_view ) {
        wp_die( esc_html__( 'Unauthorized access.', 'instapay-gateway-for-egypt' ) );
    }

    $receipt_path = instapay_woo_get_valid_receipt_path( $order_id );
    if ( ! $receipt_path ) {
        wp_die( esc_html__( 'Receipt not found or has been removed.', 'instapay-gateway-for-egypt' ) );
    }

    $mime = wp_get_image_mime( $receipt_path );
    nocache_headers();
    header( 'Content-Type: ' . sanitize_mime_type( $mime ) );
    header( 'Content-Length: ' . absint( filesize( $receipt_path ) ) );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Disposition: inline; filename="' . sanitize_file_name( basename( $receipt_path ) ) . '"' );
    readfile( $receipt_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}

// Global AJAX hooks for receipt upload
add_action( 'wp_ajax_instapay_upload_receipt', 'instapay_woo_handle_receipt_upload' );
add_action( 'wp_ajax_nopriv_instapay_upload_receipt', 'instapay_woo_handle_receipt_upload' );

function instapay_woo_process_upload_file( $file, $order_id ) {
    $allowed_mimes = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    );

    if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) || ! isset( $file['size'] ) ) {
        return new WP_Error( 'invalid_upload', __( 'The uploaded file is incomplete.', 'instapay-gateway-for-egypt' ) );
    }

    $validate_file = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ), $allowed_mimes );

    if ( ! $validate_file['ext'] || ! $validate_file['type'] ) {
        return new WP_Error( 'invalid_type', __( 'Invalid file type. Only JPG, PNG, and WebP images are allowed.', 'instapay-gateway-for-egypt' ) );
    }
    $image_mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $file['tmp_name'] ) : $validate_file['type'];
    if ( ! in_array( $image_mime, $allowed_mimes, true ) ) {
        return new WP_Error( 'invalid_image', __( 'The uploaded file is not a valid image.', 'instapay-gateway-for-egypt' ) );
    }

    if ( $file['size'] > 5 * 1024 * 1024 ) {
        return new WP_Error( 'too_large', __( 'File size too large. Maximum allowed is 5MB.', 'instapay-gateway-for-egypt' ) );
    }

    $secure_dir = instapay_woo_ensure_receipts_dir();
    if ( is_wp_error( $secure_dir ) ) {
        return $secure_dir;
    }

    $file['name'] = 'order_' . absint( $order_id ) . '_' . wp_generate_password( 12, false ) . '.' . $validate_file['ext'];
    $overrides    = array(
        'test_form' => false,
        'mimes'     => $allowed_mimes,
    );

    require_once ABSPATH . 'wp-admin/includes/file.php';
    add_filter( 'upload_dir', 'instapay_woo_use_receipts_upload_dir' );
    $upload = wp_handle_upload( $file, $overrides );
    remove_filter( 'upload_dir', 'instapay_woo_use_receipts_upload_dir' );

    if ( ! empty( $upload['file'] ) ) {
        $file_path = $upload['file'];
        $filename  = basename( $file_path );
        $settings = get_option( 'woocommerce_instapay_settings', array() );
        $editor   = wp_get_image_editor( $file_path );
        if ( ! is_wp_error( $editor ) ) {
            if ( isset( $settings['enable_compression'] ) && 'yes' === $settings['enable_compression'] ) {
                $editor->resize( 1200, 1200, false );
                $editor->set_quality( 80 );
            }
            // Re-encoding also removes unneeded camera metadata from receipts.
            $editor->save( $file_path );
        }
        instapay_woo_delete_receipt( $order_id );
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_delete_file( $file_path );
            return new WP_Error( 'invalid_order', __( 'Invalid order.', 'instapay-gateway-for-egypt' ) );
        }

        $order->update_meta_data( '_instapay_receipt_path', $file_path );
        $order->update_meta_data( '_instapay_receipt_filename', $filename );
        $order->save();

        return true;
    }

    $message = ! empty( $upload['error'] ) ? sanitize_text_field( $upload['error'] ) : __( 'Failed to save file securely. Please check directory permissions.', 'instapay-gateway-for-egypt' );
    return new WP_Error( 'save_failed', $message );
}

function instapay_woo_notify_admin_of_receipt( $order ) {
    $receipt_path = instapay_woo_get_valid_receipt_path( $order->get_id() );
    $admin_email  = sanitize_email( get_option( 'admin_email' ) );
    if ( ! $receipt_path || ! is_email( $admin_email ) ) {
        return;
    }

    $mailer        = WC()->mailer();
    $email_heading = __( 'Instapay Receipt Awaiting Review', 'instapay-gateway-for-egypt' );
    ob_start();
    wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
    echo '<p>' . esc_html( sprintf( __( 'A receipt was uploaded for order #%s and is ready for review.', 'instapay-gateway-for-egypt' ), $order->get_order_number() ) ) . '</p>';
    echo '<p><a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Review the order', 'instapay-gateway-for-egypt' ) . '</a></p>';
    wc_get_template( 'emails/email-footer.php' );
    $message = ob_get_clean();

    $mailer->send(
        $admin_email,
        sprintf( __( 'Instapay receipt for order #%s', 'instapay-gateway-for-egypt' ), $order->get_order_number() ),
        $message,
        "Content-Type: text/html\r\n",
        array( $receipt_path )
    );
}

function instapay_woo_handle_receipt_upload() {
    if ( ! isset( $_POST['order_id'], $_POST['order_key'], $_POST['instapay_nonce'] ) ) {
        wp_send_json_error( __( 'Missing parameters.', 'instapay-gateway-for-egypt' ) );
    }

    $order_id  = absint( wp_unslash( $_POST['order_id'] ) );
    $order_key = wc_clean( wp_unslash( $_POST['order_key'] ) );
    $nonce     = sanitize_text_field( wp_unslash( $_POST['instapay_nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'instapay_upload_nonce_' . $order_id ) ) {
        wp_send_json_error( __( 'Security verification failed.', 'instapay-gateway-for-egypt' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || ! hash_equals( (string) $order->get_order_key(), (string) $order_key ) || ! instapay_woo_order_accepts_receipt( $order ) ) {
        wp_send_json_error( __( 'Invalid order.', 'instapay-gateway-for-egypt' ) );
    }

    $file = instapay_woo_get_uploaded_file();
    if ( is_wp_error( $file ) ) {
        wp_send_json_error( $file->get_error_message() );
    }

    $lock = instapay_woo_acquire_upload_lock( $order_id );
    if ( is_wp_error( $lock ) ) {
        wp_send_json_error( $lock->get_error_message() );
    }

    $result = instapay_woo_process_upload_file( $file, $order_id );
    instapay_woo_release_upload_lock( $lock );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    $order->update_status( 'payment-review', __( 'Instapay receipt uploaded. Awaiting manager approval.', 'instapay-gateway-for-egypt' ) );
    instapay_woo_notify_admin_of_receipt( $order );
    wp_send_json_success( __( 'Receipt uploaded successfully. Order is under review.', 'instapay-gateway-for-egypt' ) );
}

add_action( 'wp_ajax_instapay_quick_action', 'instapay_woo_quick_action' );
function instapay_woo_quick_action() {
    check_ajax_referer( 'instapay_quick_action', 'nonce' );

    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        wp_send_json_error( __( 'Permission denied.', 'instapay-gateway-for-egypt' ) );
    }

    $order_id         = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
    $new_status       = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';
    $rejection_reason = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rejection_reason'] ) ) : '';

    if ( ! $order_id || ! in_array( $new_status, array( 'processing', 'pending', 'cancelled' ), true ) ) {
        wp_send_json_error( __( 'Invalid parameters.', 'instapay-gateway-for-egypt' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || 'instapay' !== $order->get_payment_method() ) {
        wp_send_json_error( __( 'Invalid order.', 'instapay-gateway-for-egypt' ) );
    }

    $allowed_transitions = array(
        'payment-review' => array( 'processing', 'pending', 'cancelled' ),
        'pending'        => array( 'cancelled' ),
    );
    $current_status = $order->get_status();
    if ( empty( $allowed_transitions[ $current_status ] ) || ! in_array( $new_status, $allowed_transitions[ $current_status ], true ) ) {
        wp_send_json_error( __( 'This order status change is not allowed.', 'instapay-gateway-for-egypt' ) );
    }

    if ( in_array( $new_status, array( 'processing', 'completed' ), true ) && ! instapay_woo_get_valid_receipt_path( $order_id ) ) {
        wp_send_json_error( __( 'A valid receipt is required before accepting payment.', 'instapay-gateway-for-egypt' ) );
    }

    $settings = get_option( 'woocommerce_instapay_settings', array() );
    $current_user = wp_get_current_user();
    
    $note = '';
    if ( isset( $settings['enable_logging'] ) && $settings['enable_logging'] === 'yes' ) {
        $note = sprintf( __( 'Instapay payment %s by manager (%s).', 'instapay-gateway-for-egypt' ), $new_status, $current_user->display_name );
        if ( $new_status === 'pending' && ! empty( $rejection_reason ) ) {
            $note .= ' ' . sprintf( __( 'Reason: %s', 'instapay-gateway-for-egypt' ), $rejection_reason );
        }
    } else {
        $note = __( 'Status updated via Instapay quick action.', 'instapay-gateway-for-egypt' );
    }

    $order->update_status( $new_status, $note );

    if ( $new_status === 'pending' && isset( $settings['enable_reject_email'] ) && $settings['enable_reject_email'] === 'yes' ) {
        // Send rejection email
        $mailer = WC()->mailer();
        $email_heading = __( 'Payment Receipt Rejected', 'instapay-gateway-for-egypt' );
        
        ob_start();
        wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
        echo '<p>' . esc_html( sprintf( __( 'Hello %s,', 'instapay-gateway-for-egypt' ), $order->get_billing_first_name() ) ) . '</p>';
        echo '<p>' . esc_html( sprintf( __( 'We reviewed your Instapay payment receipt for order #%s, but unfortunately it was invalid or unreadable.', 'instapay-gateway-for-egypt' ), $order->get_order_number() ) ) . '</p>';
        
        if ( ! empty( $rejection_reason ) ) {
            echo '<p><strong>' . esc_html__( 'Reason:', 'instapay-gateway-for-egypt' ) . '</strong> ' . nl2br( esc_html( $rejection_reason ) ) . '</p>';
        }
        
        echo '<p><a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Click here to upload a new receipt', 'instapay-gateway-for-egypt' ) . '</a></p>';
        wc_get_template( 'emails/email-footer.php' );
        $message = ob_get_clean();
        
        $mailer->send( $order->get_billing_email(), __( 'Action Required: Payment Receipt Rejected', 'instapay-gateway-for-egypt' ), $message, "Content-Type: text/html\r\n" );
    }

    wp_send_json_success( __( 'Order status updated successfully.', 'instapay-gateway-for-egypt' ) );
}

add_action( 'instapay_woo_cleanup_cron', 'instapay_woo_cleanup_receipts' );
function instapay_woo_cleanup_receipts() {
    $settings = get_option( 'woocommerce_instapay_settings', array() );
    if ( empty( $settings['enable_cleanup'] ) || $settings['enable_cleanup'] !== 'yes' ) {
        return;
    }

    // Get orders older than 30 days that are cancelled, failed, refunded
    $args = array(
        'status' => array( 'cancelled', 'failed', 'refunded' ),
        'date_created' => '<' . ( time() - ( 30 * DAY_IN_SECONDS ) ),
        'limit' => -1,
        'return' => 'ids',
    );
    $orders = wc_get_orders( $args );
    foreach ( $orders as $order_id ) {
        instapay_woo_delete_receipt( $order_id );
    }
}

// ----------------------------------------------------------------------
// Dashboard Widget
// ----------------------------------------------------------------------
add_action( 'wp_dashboard_setup', 'instapay_woo_add_dashboard_widgets' );
function instapay_woo_add_dashboard_widgets() {
    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        return;
    }

    wp_add_dashboard_widget(
        'instapay_woo_dashboard_widget',
        __( 'Instapay Receipts Awaiting Review', 'instapay-gateway-for-egypt' ),
        'instapay_woo_dashboard_widget_display'
    );
}

function instapay_woo_dashboard_widget_display() {
    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        return;
    }

    $args = array(
        'status' => 'wc-payment-review',
        'limit'  => 5,
    );
    $orders = wc_get_orders( $args );

    if ( empty( $orders ) ) {
        echo '<p>' . esc_html__( 'No orders currently awaiting review.', 'instapay-gateway-for-egypt' ) . '</p>';
        return;
    }

    echo '<ul>';
    foreach ( $orders as $order ) {
        $edit_url = admin_url( 'post.php?post=' . absint( $order->get_id() ) . '&action=edit' );
        if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $edit_url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . absint( $order->get_id() ) );
        }
        printf(
            '<li class="instapay-dashboard-order"><a href="%s"><strong>#%s</strong></a> - %s <span>(%s)</span></li>',
            esc_url( $edit_url ), 
            esc_html( $order->get_order_number() ),
            wp_kses_post( $order->get_formatted_order_total() ),
            esc_html( wc_format_datetime( $order->get_date_created() ) )
        );
    }
    echo '</ul>';
    
    $all_url = admin_url( 'edit.php?post_status=wc-payment-review&post_type=shop_order' );
    if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
        $all_url = admin_url( 'admin.php?page=wc-orders&status=payment-review' );
    }
    echo '<p><a href="' . esc_url( $all_url ) . '" class="button">' . esc_html__( 'View All Pending', 'instapay-gateway-for-egypt' ) . '</a></p>';
}

// ----------------------------------------------------------------------
// Orders List Custom Column
// ----------------------------------------------------------------------
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'instapay_woo_add_order_column' ); // HPOS
add_filter( 'manage_shop_order_posts_columns', 'instapay_woo_add_order_column' ); // Legacy

function instapay_woo_add_order_column( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $name ) {
        $new_columns[ $key ] = $name;
        if ( 'order_status' === $key ) {
            $new_columns['instapay_receipt'] = __( 'Instapay', 'instapay-gateway-for-egypt' );
        }
    }
    return $new_columns;
}

add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'instapay_woo_render_order_column', 10, 2 ); // HPOS
add_action( 'manage_shop_order_posts_custom_column', 'instapay_woo_render_order_column', 10, 2 ); // Legacy

function instapay_woo_render_order_column( $column, $order_or_id ) {
    if ( 'instapay_receipt' === $column ) {
        $order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
        if ( $order && $order->get_payment_method() === 'instapay' ) {
            if ( $order->get_status() === 'payment-review' ) {
                echo '<span class="dashicons dashicons-camera instapay-receipt-review" title="' . esc_attr__( 'Receipt uploaded - Awaiting review', 'instapay-gateway-for-egypt' ) . '"></span>';
            } elseif ( instapay_woo_get_valid_receipt_path( $order->get_id() ) ) {
                echo '<span class="dashicons dashicons-yes-alt instapay-receipt-attached" title="' . esc_attr__( 'Receipt attached', 'instapay-gateway-for-egypt' ) . '"></span>';
            } else {
                echo '<span class="instapay-receipt-empty">-</span>';
            }
        } else {
            echo '<span class="instapay-receipt-empty">-</span>';
        }
    }
}

// ----------------------------------------------------------------------
// Headless REST API Endpoint
// ----------------------------------------------------------------------
add_action( 'rest_api_init', 'instapay_woo_register_rest_route' );
function instapay_woo_register_rest_route() {
    register_rest_route( 'instapay-gateway-for-egypt/v1', '/receipt', array(
        'methods'             => 'POST',
        'callback'            => 'instapay_woo_rest_upload_receipt',
        'permission_callback' => 'instapay_woo_rest_permissions_check',
        'args'                => array(
            'order_id'  => array(
                'required'          => true,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ( $value ) {
                    return absint( $value ) > 0;
                },
            ),
            'order_key' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ) );
}

function instapay_woo_rest_permissions_check( $request ) {
    $settings = get_option( 'woocommerce_instapay_settings', array() );
    if ( empty( $settings['enable_rest_uploads'] ) || 'yes' !== $settings['enable_rest_uploads'] ) {
        return new WP_Error( 'instapay_rest_disabled', __( 'REST receipt uploads are disabled.', 'instapay-gateway-for-egypt' ), array( 'status' => 403 ) );
    }

    $order_id  = absint( $request->get_param( 'order_id' ) );
    $order_key = wc_clean( $request->get_param( 'order_key' ) );
    
    $order = wc_get_order( $order_id );
    if ( ! $order || ! hash_equals( (string) $order->get_order_key(), (string) $order_key ) || ! instapay_woo_order_accepts_receipt( $order ) ) {
        return new WP_Error( 'instapay_rest_unauthorized', __( 'Invalid order ID or key.', 'instapay-gateway-for-egypt' ), array( 'status' => 401 ) );
    }
    return true;
}

function instapay_woo_rest_upload_receipt( $request ) {
    $order_id = absint( $request->get_param( 'order_id' ) );
    $file     = instapay_woo_get_uploaded_file();

    if ( is_wp_error( $file ) ) {
        return new WP_Error( 'instapay_rest_no_file', $file->get_error_message(), array( 'status' => 400 ) );
    }

    $lock = instapay_woo_acquire_upload_lock( $order_id );
    if ( is_wp_error( $lock ) ) {
        return new WP_Error( 'instapay_rest_upload_busy', $lock->get_error_message(), array( 'status' => 409 ) );
    }

    $result = instapay_woo_process_upload_file( $file, $order_id );
    instapay_woo_release_upload_lock( $lock );

    if ( is_wp_error( $result ) ) {
        return $result; // Will output 400/500 automatically in REST
    }

    $order = wc_get_order( $order_id );
    $order->update_status( 'payment-review', __( 'Instapay receipt uploaded via API. Awaiting manager approval.', 'instapay-gateway-for-egypt' ) );
    instapay_woo_notify_admin_of_receipt( $order );

    return rest_ensure_response( array(
        'success' => true,
        'message' => __( 'Receipt uploaded successfully.', 'instapay-gateway-for-egypt' )
    ) );
}
