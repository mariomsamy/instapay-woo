<?php
/**
 * Plugin Name: Instapay Gateway for Egypt
 * Description: Instapay payment gateway for WooCommerce with secure receipt upload and manager verification.
 * Version: 1.3.0
 * Requires at least: 6.3
 * Tested up to: 7.1
 * WC requires at least: 8.5
 * WC tested up to: 11.1
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Recipe Codes
 * Author URI: https://recipe.codes
 * Text Domain: instapay-gateway-for-egypt
 * Update URI: https://github.com/mariomsamy/instapay-woo
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'INSTAPAY_WOO_PLUGIN_FILE', __FILE__ );
define( 'INSTAPAY_WOO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INSTAPAY_WOO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INSTAPAY_WOO_VERSION', '1.3.0' );
define( 'INSTAPAY_WOO_MAX_UPLOAD_BYTES', 5 * 1024 * 1024 );

add_action( 'before_woocommerce_init', 'instapay_woo_declare_wc_compatibility' );
function instapay_woo_declare_wc_compatibility() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
}

// ----------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------

/**
 * Read one gateway setting without instantiating the gateway.
 */
function instapay_woo_setting( $key, $default = '' ) {
    $settings = get_option( 'woocommerce_instapay_settings', array() );

    return ( is_array( $settings ) && isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) ? $settings[ $key ] : $default;
}

function instapay_woo_log( $level, $message, $context = array() ) {
    if ( function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->log( $level, $message, array_merge( array( 'source' => 'instapay-gateway-for-egypt' ), $context ) );
    }
}

/**
 * The gateway instance WooCommerce manages, or null when gateways are unavailable.
 */
function instapay_woo_gateway() {
    if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
        return null;
    }

    $gateways = WC()->payment_gateways()->payment_gateways();

    return isset( $gateways['instapay'] ) && $gateways['instapay'] instanceof WC_Gateway_Instapay ? $gateways['instapay'] : null;
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

/**
 * Statuses in which an Instapay order is still waiting for the customer to pay.
 * New orders use on-hold; pending is kept for orders placed before 1.3.0.
 */
function instapay_woo_awaiting_payment_statuses() {
    return array( 'on-hold', 'pending', 'failed' );
}

function instapay_woo_order_accepts_receipt( $order ) {
    // A paid order a manager later put on hold (e.g. for a stock check) is not awaiting payment.
    return $order instanceof WC_Order
        && 'instapay' === $order->get_payment_method()
        && ! $order->get_date_paid()
        && in_array( $order->get_status(), array_merge( instapay_woo_awaiting_payment_statuses(), array( 'payment-review' ) ), true );
}

function instapay_woo_clean_reference( $reference ) {
    $reference = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $reference );

    return strtoupper( substr( $reference, 0, 64 ) );
}

function instapay_woo_get_uploaded_file() {
    if ( empty( $_FILES['instapay_receipt'] ) || ! is_array( $_FILES['instapay_receipt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return new WP_Error( 'missing_file', __( 'No file was uploaded.', 'instapay-gateway-for-egypt' ), array( 'status' => 400 ) );
    }

    // $_FILES is not slashed by WordPress; unslashing would break Windows temp paths.
    $file = $_FILES['instapay_receipt']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
    if ( ! isset( $file['name'], $file['tmp_name'], $file['error'], $file['size'] ) || is_array( $file['name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
        return new WP_Error( 'upload_error', __( 'No file uploaded or an upload error occurred.', 'instapay-gateway-for-egypt' ), array( 'status' => 400 ) );
    }

    return array(
        'name'     => sanitize_file_name( $file['name'] ),
        'type'     => isset( $file['type'] ) ? sanitize_mime_type( $file['type'] ) : '',
        'tmp_name' => (string) $file['tmp_name'],
        'error'    => (int) $file['error'],
        'size'     => (int) $file['size'],
    );
}

/**
 * Take an exclusive per-order lock. Uploads, manager decisions and expiry share it,
 * so they never interleave on the same order. INSERT IGNORE on the unique option_name
 * makes acquisition atomic; a lock older than $ttl is treated as abandoned.
 *
 * @return array|false Lock handle for instapay_woo_unlock_order(), or false when another request holds it.
 */
function instapay_woo_lock_order( $order_id, $ttl = 120 ) {
    global $wpdb;

    $name  = 'instapay_woo_lock_' . absint( $order_id );
    $now   = time();
    // "<timestamp>:<token>": the timestamp detects abandoned locks, the token identifies the owner.
    $value = $now . ':' . wp_generate_password( 16, false );

    // Timestamps are fixed-width, so a string comparison orders them correctly.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value < %s", $name, (string) ( $now - $ttl ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

    return 1 === (int) $inserted ? array( 'name' => $name, 'value' => $value ) : false;
}

/**
 * Release a lock only if this request still owns it; a request that outlived the TTL
 * must not release a lock another request has since taken.
 */
function instapay_woo_unlock_order( $lock ) {
    global $wpdb;

    if ( is_array( $lock ) ) {
        $wpdb->delete( $wpdb->options, array( 'option_name' => $lock['name'], 'option_value' => $lock['value'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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

/**
 * Delete an order's receipt file and its metadata. Saves only when something changed.
 */
function instapay_woo_delete_receipt( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $receipt_path = instapay_woo_get_valid_receipt_path( $order_id );
    if ( $receipt_path ) {
        wp_delete_file( $receipt_path );
    }

    if ( '' === $order->get_meta( '_instapay_receipt_path' ) && '' === $order->get_meta( '_instapay_receipt_filename' ) ) {
        return;
    }

    $order->delete_meta_data( '_instapay_receipt_path' );
    $order->delete_meta_data( '_instapay_receipt_filename' );
    $order->delete_meta_data( '_instapay_receipt_hash' );
    $order->save();
}

function instapay_woo_use_receipts_upload_dir( $upload_dir ) {
    // Build from the filtered value: calling wp_upload_dir() here would re-run this filter forever.
    $upload_dir['path']   = trailingslashit( $upload_dir['basedir'] ) . 'instapay_receipts';
    $upload_dir['url']    = '';
    $upload_dir['subdir'] = '';

    return $upload_dir;
}

/**
 * Write the web-server deny rules into the receipts directory if any are missing.
 * Plain file writes are used because WP_Filesystem may need FTP credentials and fail silently.
 */
function instapay_woo_write_protection_files( $receipts_dir ) {
    $files = array(
        '.htaccess'  => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\" /><add accessType=\"Deny\" users=\"*\" /></authorization></security></system.webServer></configuration>\n",
        'index.php'  => "<?php\n// Silence is golden.\n",
    );

    foreach ( $files as $name => $contents ) {
        $path = trailingslashit( $receipts_dir ) . $name;
        if ( ! file_exists( $path ) && false === file_put_contents( $path, $contents ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            instapay_woo_log( 'error', 'Could not write receipt directory protection file.', array( 'file' => $name ) );
        }
    }
}

function instapay_woo_ensure_receipts_dir() {
    $receipts_dir = instapay_woo_get_receipts_dir();
    if ( ! wp_mkdir_p( $receipts_dir ) ) {
        return new WP_Error( 'directory_creation_failed', __( 'Could not create the receipt upload directory.', 'instapay-gateway-for-egypt' ), array( 'status' => 500 ) );
    }

    instapay_woo_write_protection_files( $receipts_dir );

    return $receipts_dir;
}

// Updates come from GitHub releases. Return false from this filter to turn them off.
if ( apply_filters( 'instapay_woo_github_updates', true ) ) {
    require_once INSTAPAY_WOO_PLUGIN_DIR . 'includes/class-instapay-github-updater.php';
    ( new Instapay_Woo_GitHub_Updater( __FILE__, INSTAPAY_WOO_VERSION ) )->register();
}

// ----------------------------------------------------------------------
// Activation, upgrade and scheduling
// ----------------------------------------------------------------------
register_activation_hook( __FILE__, 'instapay_woo_activation_check' );
function instapay_woo_activation_check() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        if ( is_multisite() ) {
            $plugins = get_site_option( 'active_sitewide_plugins' );
            if ( isset( $plugins['woocommerce/woocommerce.php'] ) ) {
                instapay_woo_schedule_events();
                return;
            }
        }
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( 'Instapay Gateway for Egypt requires WooCommerce to be installed and active.', 'instapay-gateway-for-egypt' ), esc_html__( 'Plugin Dependency Error', 'instapay-gateway-for-egypt' ), array( 'back_link' => true ) );
    }

    instapay_woo_schedule_events();
}

register_deactivation_hook( __FILE__, 'instapay_woo_deactivate' );
function instapay_woo_deactivate() {
    wp_clear_scheduled_hook( 'instapay_woo_cleanup_cron' );
    wp_clear_scheduled_hook( 'instapay_woo_expire_cron' );
}

function instapay_woo_schedule_events() {
    if ( ! wp_next_scheduled( 'instapay_woo_cleanup_cron' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'instapay_woo_cleanup_cron' );
    }
    if ( ! wp_next_scheduled( 'instapay_woo_expire_cron' ) ) {
        wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'instapay_woo_expire_cron' );
    }
}

// Activation hooks do not run on plugin updates or on every multisite site, so
// version-gated setup runs once per site after each update.
add_action( 'init', 'instapay_woo_maybe_upgrade', 20 );
function instapay_woo_maybe_upgrade() {
    if ( INSTAPAY_WOO_VERSION === get_option( 'instapay_woo_version' ) ) {
        return;
    }

    instapay_woo_schedule_events();
    if ( is_dir( instapay_woo_get_receipts_dir() ) ) {
        instapay_woo_write_protection_files( instapay_woo_get_receipts_dir() );
    }
    update_option( 'instapay_woo_version', INSTAPAY_WOO_VERSION );
}

// Load text domain for translations
add_action( 'plugins_loaded', 'instapay_woo_load_textdomain' );
function instapay_woo_load_textdomain() {
    load_plugin_textdomain( 'instapay-gateway-for-egypt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// ----------------------------------------------------------------------
// Order status
// ----------------------------------------------------------------------
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

// Let payment_complete() accept an Instapay order that is in review.
add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', 'instapay_woo_payment_complete_statuses', 10, 2 );
function instapay_woo_payment_complete_statuses( $statuses, $order = null ) {
    if ( $order instanceof WC_Order && 'instapay' === $order->get_payment_method() ) {
        $statuses[] = 'payment-review';
    }
    return $statuses;
}

// WooCommerce only emails "order processing" for transitions it knows about; add ours.
add_filter( 'woocommerce_email_actions', 'instapay_woo_email_actions' );
function instapay_woo_email_actions( $actions ) {
    $actions[] = 'woocommerce_order_status_payment-review_to_processing';
    return $actions;
}

add_action( 'woocommerce_email', 'instapay_woo_register_email_triggers' );
function instapay_woo_register_email_triggers( $wc_emails ) {
    if ( isset( $wc_emails->emails['WC_Email_Customer_Processing_Order'] ) ) {
        add_action( 'woocommerce_order_status_payment-review_to_processing_notification', array( $wc_emails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10, 2 );
    }
}

// Payment instructions and upload link in the customer's on-hold email.
add_action( 'woocommerce_email_before_order_table', 'instapay_woo_email_instructions', 10, 3 );
function instapay_woo_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
    if ( $sent_to_admin || ! $order instanceof WC_Order || 'instapay' !== $order->get_payment_method() || ! $order->has_status( instapay_woo_awaiting_payment_statuses() ) ) {
        return;
    }

    $ipa        = instapay_woo_setting( 'instapay_ipa' );
    $phone      = instapay_woo_setting( 'instapay_phone' );
    $upload_url = $order->get_checkout_order_received_url();
    $intro      = __( 'To complete your order, send the order total by Instapay and upload a screenshot of the receipt.', 'instapay-gateway-for-egypt' );

    if ( $plain_text ) {
        echo esc_html( $intro ) . "\n";
        if ( $ipa ) {
            echo esc_html__( 'Instapay address:', 'instapay-gateway-for-egypt' ) . ' ' . esc_html( $ipa ) . "\n";
        }
        if ( $phone ) {
            echo esc_html__( 'Instapay phone:', 'instapay-gateway-for-egypt' ) . ' ' . esc_html( $phone ) . "\n";
        }
        echo esc_html__( 'Upload your receipt:', 'instapay-gateway-for-egypt' ) . ' ' . esc_url_raw( $upload_url ) . "\n\n";
        return;
    }

    echo '<h2>' . esc_html__( 'Instapay payment instructions', 'instapay-gateway-for-egypt' ) . '</h2>';
    echo '<p>' . esc_html( $intro ) . '</p><ul>';
    if ( $ipa ) {
        echo '<li>' . esc_html__( 'Instapay address:', 'instapay-gateway-for-egypt' ) . ' <strong>' . esc_html( $ipa ) . '</strong></li>';
    }
    if ( $phone ) {
        echo '<li>' . esc_html__( 'Instapay phone:', 'instapay-gateway-for-egypt' ) . ' <strong>' . esc_html( $phone ) . '</strong></li>';
    }
    echo '</ul><p><a href="' . esc_url( $upload_url ) . '">' . esc_html__( 'Upload your receipt', 'instapay-gateway-for-egypt' ) . '</a></p>';
}

// ----------------------------------------------------------------------
// Gateway, blocks and assets
// ----------------------------------------------------------------------
add_action( 'plugins_loaded', 'instapay_woo_init_gateway' );
function instapay_woo_init_gateway() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    require_once INSTAPAY_WOO_PLUGIN_DIR . 'includes/class-wc-gateway-instapay.php';
}

add_filter( 'woocommerce_payment_gateways', 'instapay_woo_add_gateway' );
function instapay_woo_add_gateway( $methods ) {
    $methods[] = 'WC_Gateway_Instapay';
    return $methods;
}

add_action( 'woocommerce_blocks_payment_method_type_registration', 'instapay_woo_register_blocks_payment_method' );
function instapay_woo_register_blocks_payment_method( $registry ) {
    if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }
    require_once INSTAPAY_WOO_PLUGIN_DIR . 'includes/class-wc-instapay-blocks.php';
    $registry->register( new WC_Instapay_Blocks() );
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

// Display hooks are registered here, once, rather than in the gateway constructor,
// so they exist even when WooCommerce has not instantiated gateways yet.
add_action( 'woocommerce_thankyou_instapay', 'instapay_woo_render_customer_panel' );
add_action( 'woocommerce_view_order', 'instapay_woo_render_customer_panel' );
function instapay_woo_render_customer_panel( $order_id ) {
    $gateway = instapay_woo_gateway();
    if ( $gateway ) {
        $gateway->thankyou_page( $order_id );
    }
}

add_action( 'add_meta_boxes', 'instapay_woo_register_meta_box', 10, 2 );
function instapay_woo_register_meta_box( $screen_id, $post_or_order = null ) {
    $order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
    if ( ! $order instanceof WC_Order || 'instapay' !== $order->get_payment_method() ) {
        return;
    }

    $screen = 'shop_order';
    if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
        $screen = wc_get_page_screen_id( 'shop-order' );
    }

    add_meta_box( 'instapay_verification_box', __( 'Instapay Verification', 'instapay-gateway-for-egypt' ), 'instapay_woo_render_meta_box', $screen, 'side', 'high' );
}

function instapay_woo_render_meta_box( $post_or_order ) {
    $gateway = instapay_woo_gateway();
    if ( $gateway ) {
        $gateway->admin_order_receipt_display( $post_or_order );
    }
}

// ----------------------------------------------------------------------
// Receipt viewer
// ----------------------------------------------------------------------
add_action( 'init', 'instapay_woo_secure_image_view' );
function instapay_woo_secure_image_view() {
    if ( ! isset( $_GET['instapay_view_receipt'], $_GET['order_id'], $_GET['_iwvnonce'] ) ) {
        return;
    }

    $order_id  = absint( wp_unslash( $_GET['order_id'] ) );
    $nonce     = sanitize_text_field( wp_unslash( $_GET['_iwvnonce'] ) );
    $order_key = isset( $_GET['order_key'] ) ? wc_clean( wp_unslash( $_GET['order_key'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wc_clean() sanitizes.
    $order     = wc_get_order( $order_id );

    if ( ! $order || ! wp_verify_nonce( $nonce, 'instapay_view_receipt_' . $order_id ) ) {
        wp_die( esc_html__( 'Invalid security token.', 'instapay-gateway-for-egypt' ), '', array( 'response' => 403 ) );
    }

    $can_view = current_user_can( 'edit_shop_orders' );
    if ( ! $can_view && is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() ) {
        $can_view = true;
    }
    if ( ! $can_view && $order_key ) {
        $can_view = hash_equals( (string) $order->get_order_key(), (string) $order_key );
    }

    if ( ! $can_view ) {
        wp_die( esc_html__( 'Unauthorized access.', 'instapay-gateway-for-egypt' ), '', array( 'response' => 403 ) );
    }

    $receipt_path = instapay_woo_get_valid_receipt_path( $order_id );
    if ( ! $receipt_path ) {
        wp_die( esc_html__( 'Receipt not found or has been removed.', 'instapay-gateway-for-egypt' ), '', array( 'response' => 404 ) );
    }

    $mime = wp_get_image_mime( $receipt_path );
    nocache_headers();
    header( 'Content-Type: ' . sanitize_mime_type( $mime ) );
    header( 'Content-Length: ' . absint( filesize( $receipt_path ) ) );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Robots-Tag: noindex, nofollow' );
    header( 'Referrer-Policy: no-referrer' );
    header( 'Content-Disposition: inline; filename="' . sanitize_file_name( basename( $receipt_path ) ) . '"' );
    readfile( $receipt_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}

// ----------------------------------------------------------------------
// Receipt upload
// ----------------------------------------------------------------------
add_action( 'wp_ajax_instapay_upload_receipt', 'instapay_woo_handle_receipt_upload' );
add_action( 'wp_ajax_nopriv_instapay_upload_receipt', 'instapay_woo_handle_receipt_upload' );

/**
 * Open an image editor for the receipt, preferring GD: GD re-encodes from raw pixels,
 * which drops all EXIF/XMP metadata (including GPS). Imagick keeps metadata unless it resizes.
 */
function instapay_woo_get_receipt_editor( $path, $mime ) {
    $only_gd = static function () {
        return array( 'WP_Image_Editor_GD' );
    };

    add_filter( 'wp_image_editors', $only_gd );
    $editor = wp_get_image_editor( $path, array( 'mime_type' => $mime ) );
    remove_filter( 'wp_image_editors', $only_gd );

    if ( is_wp_error( $editor ) ) {
        $editor = wp_get_image_editor( $path, array( 'mime_type' => $mime ) );
    }

    return $editor;
}

/**
 * Validate, store and re-encode an uploaded receipt. Does not touch the order.
 *
 * @return array|WP_Error { path, filename, hash } of the stored file.
 */
function instapay_woo_process_upload_file( $file, $order_id ) {
    $allowed_mimes = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    );

    if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) || ! isset( $file['size'] ) || ! is_readable( $file['tmp_name'] ) ) {
        return new WP_Error( 'invalid_upload', __( 'The uploaded file is incomplete.', 'instapay-gateway-for-egypt' ), array( 'status' => 400 ) );
    }

    if ( $file['size'] > INSTAPAY_WOO_MAX_UPLOAD_BYTES || filesize( $file['tmp_name'] ) > INSTAPAY_WOO_MAX_UPLOAD_BYTES ) {
        return new WP_Error( 'too_large', __( 'File size too large. Maximum allowed is 5MB.', 'instapay-gateway-for-egypt' ), array( 'status' => 400 ) );
    }

    $validate_file = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ), $allowed_mimes );
    if ( ! $validate_file['ext'] || ! $validate_file['type'] ) {
        return new WP_Error( 'invalid_type', __( 'Invalid file type. Only JPG, PNG, and WebP images are allowed.', 'instapay-gateway-for-egypt' ), array( 'status' => 400 ) );
    }

    $image_mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $file['tmp_name'] ) : $validate_file['type'];
    if ( ! in_array( $image_mime, $allowed_mimes, true ) ) {
        return new WP_Error( 'invalid_image', __( 'The uploaded file is not a valid image.', 'instapay-gateway-for-egypt' ), array( 'status' => 400 ) );
    }

    // Reject decompression bombs before an editor decodes the pixels into memory.
    $dimensions = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $file['tmp_name'] ) : getimagesize( $file['tmp_name'] );
    if ( ! $dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 10000 || $dimensions[1] > 10000 || $dimensions[0] * $dimensions[1] > 40000000 ) {
        return new WP_Error( 'invalid_dimensions', __( 'The image dimensions are too large. Please upload a normal screenshot.', 'instapay-gateway-for-egypt' ), array( 'status' => 400 ) );
    }

    // Hash the original bytes so re-use of the same screenshot can be detected.
    $hash = hash_file( 'sha256', $file['tmp_name'] );

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
    try {
        $upload = wp_handle_upload( $file, $overrides );
    } finally {
        remove_filter( 'upload_dir', 'instapay_woo_use_receipts_upload_dir' );
    }

    if ( empty( $upload['file'] ) ) {
        $message = ! empty( $upload['error'] ) ? sanitize_text_field( $upload['error'] ) : __( 'Failed to save file securely. Please check directory permissions.', 'instapay-gateway-for-egypt' );
        return new WP_Error( 'save_failed', $message, array( 'status' => 500 ) );
    }

    $file_path = $upload['file'];
    $editor    = instapay_woo_get_receipt_editor( $file_path, $image_mime );
    if ( is_wp_error( $editor ) ) {
        wp_delete_file( $file_path );
        return new WP_Error( 'process_failed', __( 'The image could not be processed. Please upload a different screenshot.', 'instapay-gateway-for-egypt' ), array( 'status' => 400 ) );
    }

    if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
        $editor->maybe_exif_rotate();
    }
    if ( 'yes' === instapay_woo_setting( 'enable_compression', 'yes' ) ) {
        // Fails harmlessly for images already below the limit.
        $editor->resize( 1200, 1200, false );
        $editor->set_quality( 80 );
    }

    // Re-encoding removes camera metadata. Fail closed: never keep the unprocessed original.
    $saved = $editor->save( $file_path, $image_mime );
    if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
        wp_delete_file( $file_path );
        return new WP_Error( 'process_failed', __( 'The image could not be processed. Please upload a different screenshot.', 'instapay-gateway-for-egypt' ), array( 'status' => 500 ) );
    }
    if ( $saved['path'] !== $file_path ) {
        wp_delete_file( $file_path );
        $file_path = $saved['path'];
    }

    // GD drops all metadata when it re-encodes; other editors (Imagick) keep it unless resizing.
    if ( ! $editor instanceof WP_Image_Editor_GD ) {
        $stripped = false;
        if ( class_exists( 'Imagick' ) ) {
            try {
                $imagick = new Imagick( $file_path );
                $imagick->stripImage();
                $stripped = $imagick->writeImage( $file_path );
                $imagick->clear();
            } catch ( Exception $e ) {
                $stripped = false;
            }
        }
        if ( ! $stripped ) {
            wp_delete_file( $file_path );
            return new WP_Error( 'process_failed', __( 'The image could not be processed. Please upload a different screenshot.', 'instapay-gateway-for-egypt' ), array( 'status' => 500 ) );
        }
    }

    return array(
        'path'     => $file_path,
        'filename' => basename( $file_path ),
        'hash'     => $hash,
    );
}

/**
 * Other orders that share this receipt's image hash or transaction reference.
 *
 * @return array{hash: int[], reference: int[]}
 */
function instapay_woo_find_related_orders( $order ) {
    $related = array(
        'hash'      => array(),
        'reference' => array(),
    );
    $lookups = array(
        'hash'      => array( '_instapay_receipt_hash', (string) $order->get_meta( '_instapay_receipt_hash' ) ),
        'reference' => array( '_instapay_transaction_reference', (string) $order->get_meta( '_instapay_transaction_reference' ) ),
    );

    foreach ( $lookups as $type => $lookup ) {
        if ( '' === $lookup[1] ) {
            continue;
        }
        // meta_key/meta_value work on both order stores; wc_get_orders() ignores meta_query on posts storage.
        $ids = wc_get_orders( array(
            'limit'      => 6,
            'return'     => 'ids',
            'meta_key'   => $lookup[0], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => $lookup[1], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ) );
        $related[ $type ] = array_values( array_diff( array_map( 'absint', $ids ), array( $order->get_id() ) ) );
    }

    return $related;
}

function instapay_woo_order_numbers( $order_ids ) {
    $numbers = array();
    foreach ( $order_ids as $order_id ) {
        $other     = wc_get_order( $order_id );
        $numbers[] = '#' . ( $other ? $other->get_order_number() : $order_id );
    }
    return implode( ', ', $numbers );
}

/**
 * Accept a receipt for an order: checks ownership, serializes with other actions on the
 * same order, limits upload frequency, stores the file and moves the order into review.
 *
 * @return true|WP_Error
 */
function instapay_woo_receive_receipt( $order_id, $order_key, $file, $reference = '', $via = 'web' ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || ! hash_equals( (string) $order->get_order_key(), (string) $order_key ) || ! instapay_woo_order_accepts_receipt( $order ) ) {
        return new WP_Error( 'invalid_order', __( 'Invalid order.', 'instapay-gateway-for-egypt' ), array( 'status' => 403 ) );
    }

    $lock = instapay_woo_lock_order( $order_id );
    if ( ! $lock ) {
        return new WP_Error( 'upload_in_progress', __( 'A receipt upload is already in progress. Please wait and try again.', 'instapay-gateway-for-egypt' ), array( 'status' => 409 ) );
    }

    try {
        $result = instapay_woo_store_receipt_locked( $order_id, $file, $reference, $via );
    } finally {
        instapay_woo_unlock_order( $lock );
    }

    if ( is_wp_error( $result ) ) {
        instapay_woo_log( 'notice', 'Receipt upload rejected.', array( 'order_id' => $order_id, 'code' => $result->get_error_code(), 'via' => $via ) );
        return $result;
    }

    if ( $result['entered_review'] ) {
        instapay_woo_notify_admin_of_receipt( wc_get_order( $order_id ) );
    }

    return true;
}

function instapay_woo_store_receipt_locked( $order_id, $file, $reference, $via ) {
    // Re-read inside the lock: a manager or the expiry job may have changed the order.
    $order = wc_get_order( $order_id );
    if ( ! instapay_woo_order_accepts_receipt( $order ) ) {
        return new WP_Error( 'invalid_order', __( 'Invalid order.', 'instapay-gateway-for-egypt' ), array( 'status' => 403 ) );
    }

    $max_per_hour = (int) apply_filters( 'instapay_woo_max_uploads_per_hour', 5, $order );
    $window_start = (int) $order->get_meta( '_instapay_upload_window' );
    $upload_count = (int) $order->get_meta( '_instapay_upload_count' );
    if ( time() - $window_start > HOUR_IN_SECONDS ) {
        $window_start = time();
        $upload_count = 0;
    }
    if ( $upload_count >= $max_per_hour ) {
        return new WP_Error( 'too_many_uploads', __( 'Too many receipt uploads for this order. Please try again later.', 'instapay-gateway-for-egypt' ), array( 'status' => 429 ) );
    }

    $stored = instapay_woo_process_upload_file( $file, $order_id );
    if ( is_wp_error( $stored ) ) {
        return $stored;
    }

    // Image processing can take a few seconds; re-check before committing.
    $order = wc_get_order( $order_id );
    if ( ! instapay_woo_order_accepts_receipt( $order ) ) {
        wp_delete_file( $stored['path'] );
        return new WP_Error( 'invalid_order', __( 'This order can no longer accept a receipt.', 'instapay-gateway-for-egypt' ), array( 'status' => 409 ) );
    }

    $old_path = instapay_woo_get_valid_receipt_path( $order_id );

    $order->update_meta_data( '_instapay_receipt_path', $stored['path'] );
    $order->update_meta_data( '_instapay_receipt_filename', $stored['filename'] );
    $order->update_meta_data( '_instapay_receipt_hash', $stored['hash'] );
    $order->update_meta_data( '_instapay_upload_window', $window_start );
    $order->update_meta_data( '_instapay_upload_count', $upload_count + 1 );
    $order->update_meta_data( '_instapay_uploaded_at', time() );
    $order->delete_meta_data( '_instapay_receipt_rejected' );
    $order->delete_meta_data( '_instapay_rejection_reason' );
    if ( '' !== $reference ) {
        $order->update_meta_data( '_instapay_transaction_reference', $reference );
    } else {
        $order->delete_meta_data( '_instapay_transaction_reference' );
    }

    $related = instapay_woo_find_related_orders( $order );
    if ( $related['hash'] ) {
        /* translators: %s: comma-separated order numbers */
        $order->add_order_note( sprintf( __( 'Warning: this receipt image is identical to the receipt on order(s) %s. Check for a reused payment before accepting.', 'instapay-gateway-for-egypt' ), instapay_woo_order_numbers( $related['hash'] ) ) );
    }
    if ( $related['reference'] ) {
        /* translators: %s: comma-separated order numbers */
        $order->add_order_note( sprintf( __( 'Warning: this transaction reference was also submitted on order(s) %s.', 'instapay-gateway-for-egypt' ), instapay_woo_order_numbers( $related['reference'] ) ) );
    }

    $entered_review = 'payment-review' !== $order->get_status();
    if ( $entered_review ) {
        $note = 'rest' === $via
            ? __( 'Instapay receipt uploaded via API. Awaiting manager approval.', 'instapay-gateway-for-egypt' )
            : __( 'Instapay receipt uploaded. Awaiting manager approval.', 'instapay-gateway-for-egypt' );
        $order->update_status( 'payment-review', $note );
    } else {
        $order->add_order_note( __( 'The customer replaced the Instapay receipt while it was under review.', 'instapay-gateway-for-egypt' ) );
        $order->save();
    }

    // Remove the previous file only after the new one is recorded.
    if ( $old_path && $old_path !== wp_normalize_path( $stored['path'] ) ) {
        wp_delete_file( $old_path );
    }

    return array( 'entered_review' => $entered_review );
}

function instapay_woo_notify_admin_of_receipt( $order ) {
    if ( ! $order instanceof WC_Order || ! instapay_woo_get_valid_receipt_path( $order->get_id() ) ) {
        return;
    }

    $recipient = sanitize_email( instapay_woo_setting( 'notify_email', get_option( 'admin_email' ) ) );
    if ( ! is_email( $recipient ) ) {
        return;
    }

    $mailer        = WC()->mailer();
    $email_heading = __( 'Instapay Receipt Awaiting Review', 'instapay-gateway-for-egypt' );
    ob_start();
    wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
    /* translators: %s: order number */
    echo '<p>' . esc_html( sprintf( __( 'A receipt was uploaded for order #%s and is ready for review.', 'instapay-gateway-for-egypt' ), $order->get_order_number() ) ) . '</p>';
    echo '<p><a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Review the order', 'instapay-gateway-for-egypt' ) . '</a></p>';
    wc_get_template( 'emails/email-footer.php' );
    $message = ob_get_clean();

    // Receipts contain personal financial data; attach them only when the store opts in.
    $attachments = 'yes' === instapay_woo_setting( 'attach_receipt_email', 'no' ) ? array( instapay_woo_get_valid_receipt_path( $order->get_id() ) ) : array();

    $mailer->send(
        $recipient,
        /* translators: %s: order number */
        sprintf( __( 'Instapay receipt for order #%s', 'instapay-gateway-for-egypt' ), $order->get_order_number() ),
        $message,
        "Content-Type: text/html\r\n",
        $attachments
    );
}

function instapay_woo_handle_receipt_upload() {
    if ( ! isset( $_POST['order_id'], $_POST['order_key'], $_POST['instapay_nonce'] ) ) {
        wp_send_json_error( __( 'Missing parameters.', 'instapay-gateway-for-egypt' ) );
    }

    $order_id  = absint( wp_unslash( $_POST['order_id'] ) );
    $order_key = wc_clean( wp_unslash( $_POST['order_key'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wc_clean() sanitizes.
    $nonce     = sanitize_text_field( wp_unslash( $_POST['instapay_nonce'] ) );
    $reference = isset( $_POST['instapay_reference'] ) ? instapay_woo_clean_reference( wp_unslash( $_POST['instapay_reference'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- allowlist regex in instapay_woo_clean_reference().

    if ( ! wp_verify_nonce( $nonce, 'instapay_upload_nonce_' . $order_id ) ) {
        wp_send_json_error( __( 'Security verification failed.', 'instapay-gateway-for-egypt' ) );
    }

    $file = instapay_woo_get_uploaded_file();
    if ( is_wp_error( $file ) ) {
        wp_send_json_error( $file->get_error_message() );
    }

    $result = instapay_woo_receive_receipt( $order_id, $order_key, $file, $reference, 'web' );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( __( 'Receipt uploaded successfully. Order is under review.', 'instapay-gateway-for-egypt' ) );
}

// ----------------------------------------------------------------------
// Manager decisions
// ----------------------------------------------------------------------
add_action( 'wp_ajax_instapay_quick_action', 'instapay_woo_quick_action' );
function instapay_woo_quick_action() {
    check_ajax_referer( 'instapay_quick_action', 'nonce' );

    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        wp_send_json_error( __( 'Permission denied.', 'instapay-gateway-for-egypt' ) );
    }

    $order_id         = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
    $new_status       = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';
    $rejection_reason = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rejection_reason'] ) ) : '';
    $receipt          = isset( $_POST['receipt'] ) ? sanitize_file_name( wp_unslash( $_POST['receipt'] ) ) : '';

    // Before 1.3.0 a rejection sent "pending"; rejected orders now wait in on-hold.
    if ( 'pending' === $new_status ) {
        $new_status = 'on-hold';
    }

    if ( ! $order_id || ! in_array( $new_status, array( 'processing', 'on-hold', 'cancelled' ), true ) ) {
        wp_send_json_error( __( 'Invalid parameters.', 'instapay-gateway-for-egypt' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || 'instapay' !== $order->get_payment_method() ) {
        wp_send_json_error( __( 'Invalid order.', 'instapay-gateway-for-egypt' ) );
    }

    $lock = instapay_woo_lock_order( $order_id );
    if ( ! $lock ) {
        wp_send_json_error( __( 'Another action on this order is in progress. Reload the page and try again.', 'instapay-gateway-for-egypt' ) );
    }

    try {
        $result = instapay_woo_apply_decision_locked( $order_id, $new_status, $rejection_reason, $receipt );
    } finally {
        instapay_woo_unlock_order( $lock );
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    if ( 'on-hold' === $new_status && 'yes' === instapay_woo_setting( 'enable_reject_email', 'yes' ) ) {
        instapay_woo_send_rejection_email( wc_get_order( $order_id ), $rejection_reason );
    }

    wp_send_json_success( __( 'Order status updated successfully.', 'instapay-gateway-for-egypt' ) );
}

function instapay_woo_apply_decision_locked( $order_id, $new_status, $rejection_reason, $receipt ) {
    // Re-read inside the lock so a second click or a second manager sees the result of the first.
    $order = wc_get_order( $order_id );

    $allowed_transitions = array(
        'payment-review' => array( 'processing', 'on-hold', 'cancelled' ),
        'on-hold'        => array( 'cancelled' ),
        'pending'        => array( 'cancelled' ),
        'failed'         => array( 'cancelled' ),
    );
    $current_status = $order->get_status();
    if ( empty( $allowed_transitions[ $current_status ] ) || ! in_array( $new_status, $allowed_transitions[ $current_status ], true ) ) {
        return new WP_Error( 'invalid_transition', __( 'This order status change is not allowed. The order may already have been updated; reload the page.', 'instapay-gateway-for-egypt' ) );
    }

    if ( 'processing' === $new_status && ! instapay_woo_get_valid_receipt_path( $order_id ) ) {
        return new WP_Error( 'missing_receipt', __( 'A valid receipt is required before accepting payment.', 'instapay-gateway-for-egypt' ) );
    }
    // Accept, reject or cancel a receipt in review only if it is the one the manager was shown.
    if ( 'payment-review' === $current_status && ( '' === $receipt || ! hash_equals( (string) $order->get_meta( '_instapay_receipt_filename' ), $receipt ) ) ) {
        return new WP_Error( 'receipt_changed', __( 'The customer uploaded a new receipt since this page was loaded. Reload the page and review it first.', 'instapay-gateway-for-egypt' ) );
    }

    $current_user = wp_get_current_user();
    if ( 'yes' === instapay_woo_setting( 'enable_logging', 'yes' ) ) {
        $labels = array(
            'processing' => __( 'accepted', 'instapay-gateway-for-egypt' ),
            'on-hold'    => __( 'rejected', 'instapay-gateway-for-egypt' ),
            'cancelled'  => __( 'cancelled', 'instapay-gateway-for-egypt' ),
        );
        /* translators: 1: action (accepted, rejected or cancelled), 2: manager display name */
        $note = sprintf( __( 'Instapay payment %1$s by manager (%2$s).', 'instapay-gateway-for-egypt' ), $labels[ $new_status ], $current_user->display_name );
        if ( 'on-hold' === $new_status && '' !== $rejection_reason ) {
            /* translators: %s: rejection reason */
            $note .= ' ' . sprintf( __( 'Reason: %s', 'instapay-gateway-for-egypt' ), $rejection_reason );
        }
    } else {
        $note = __( 'Status updated via Instapay quick action.', 'instapay-gateway-for-egypt' );
    }

    if ( 'processing' === $new_status ) {
        $order->add_order_note( $note );
        // payment_complete() records the paid date and transaction reference, and moves
        // the order to processing or, for virtual/downloadable orders, completed.
        if ( ! $order->payment_complete( (string) $order->get_meta( '_instapay_transaction_reference' ) ) ) {
            return new WP_Error( 'payment_complete_failed', __( 'The payment could not be marked as complete. Check the WooCommerce logs.', 'instapay-gateway-for-egypt' ) );
        }
        return true;
    }

    if ( 'on-hold' === $new_status ) {
        // Shown to the customer next to the new upload form.
        $order->update_meta_data( '_instapay_receipt_rejected', 'yes' );
        $order->update_meta_data( '_instapay_rejection_reason', $rejection_reason );
    }
    $order->update_status( $new_status, $note );

    return true;
}

function instapay_woo_send_rejection_email( $order, $rejection_reason ) {
    if ( ! $order instanceof WC_Order || ! is_email( $order->get_billing_email() ) ) {
        return;
    }

    $mailer        = WC()->mailer();
    $email_heading = __( 'Payment Receipt Rejected', 'instapay-gateway-for-egypt' );

    ob_start();
    wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
    /* translators: %s: customer first name */
    echo '<p>' . esc_html( sprintf( __( 'Hello %s,', 'instapay-gateway-for-egypt' ), $order->get_billing_first_name() ) ) . '</p>';
    /* translators: %s: order number */
    echo '<p>' . esc_html( sprintf( __( 'We reviewed your Instapay payment receipt for order #%s, but unfortunately it was invalid or unreadable.', 'instapay-gateway-for-egypt' ), $order->get_order_number() ) ) . '</p>';

    if ( '' !== $rejection_reason ) {
        echo '<p><strong>' . esc_html__( 'Reason:', 'instapay-gateway-for-egypt' ) . '</strong> ' . nl2br( esc_html( $rejection_reason ) ) . '</p>';
    }

    echo '<p><a href="' . esc_url( $order->get_checkout_order_received_url() ) . '">' . esc_html__( 'Click here to upload a new receipt', 'instapay-gateway-for-egypt' ) . '</a></p>';
    wc_get_template( 'emails/email-footer.php' );
    $message = ob_get_clean();

    $mailer->send( $order->get_billing_email(), __( 'Action Required: Payment Receipt Rejected', 'instapay-gateway-for-egypt' ), $message, "Content-Type: text/html\r\n" );
}

// ----------------------------------------------------------------------
// Scheduled jobs
// ----------------------------------------------------------------------

/**
 * Delete receipts on Instapay orders in the given statuses created before $before.
 * Batched and bounded so a large store cannot exhaust a cron run.
 */
function instapay_woo_purge_receipts( $statuses, $before ) {
    for ( $batch = 0; $batch < 20; $batch++ ) {
        $order_ids = wc_get_orders( array(
            'payment_method' => 'instapay',
            'status'         => $statuses,
            'date_created'   => '<' . $before,
            'limit'          => 100,
            'return'         => 'ids',
            'meta_key'       => '_instapay_receipt_filename', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_compare'   => 'EXISTS',
        ) );

        foreach ( $order_ids as $order_id ) {
            instapay_woo_delete_receipt( $order_id );
        }

        if ( count( $order_ids ) < 100 ) {
            return;
        }
    }
}

/**
 * Remove receipt files that no order references (crashed or superseded uploads, deleted orders).
 */
function instapay_woo_sweep_orphan_receipts() {
    $receipts_dir = instapay_woo_get_receipts_dir();
    $files        = is_dir( $receipts_dir ) ? glob( trailingslashit( $receipts_dir ) . 'order_*' ) : array();
    if ( ! $files ) {
        return;
    }

    shuffle( $files );
    foreach ( array_slice( $files, 0, 500 ) as $path ) {
        if ( ! is_file( $path ) || filemtime( $path ) > time() - DAY_IN_SECONDS ) {
            continue;
        }
        if ( ! preg_match( '/^order_(\d+)_[A-Za-z0-9]+(?:-\d+)?\.(?:jpe?g|jpe|png|webp)$/i', basename( $path ), $matches ) ) {
            continue;
        }
        $order = wc_get_order( (int) $matches[1] );
        if ( $order && basename( $path ) === $order->get_meta( '_instapay_receipt_filename' ) ) {
            continue;
        }
        wp_delete_file( $path );
    }
}

add_action( 'instapay_woo_cleanup_cron', 'instapay_woo_cleanup_receipts' );
function instapay_woo_cleanup_receipts() {
    if ( 'yes' === instapay_woo_setting( 'enable_cleanup', 'yes' ) ) {
        instapay_woo_purge_receipts( array( 'cancelled', 'failed', 'refunded' ), time() - 30 * DAY_IN_SECONDS );
    }

    $retention_days = absint( instapay_woo_setting( 'retention_days', 0 ) );
    if ( $retention_days > 0 ) {
        instapay_woo_purge_receipts( array( 'processing', 'completed' ), time() - $retention_days * DAY_IN_SECONDS );
    }

    instapay_woo_sweep_orphan_receipts();
}

add_action( 'instapay_woo_expire_cron', 'instapay_woo_expire_unpaid_orders' );
function instapay_woo_expire_unpaid_orders() {
    $hours = absint( instapay_woo_setting( 'unpaid_expiry_hours', 48 ) );
    if ( ! $hours ) {
        return;
    }

    $cutoff    = time() - $hours * HOUR_IN_SECONDS;
    $order_ids = wc_get_orders( array(
        'payment_method' => 'instapay',
        'status'         => array( 'on-hold' ),
        'date_modified'  => '<' . $cutoff,
        'limit'          => 50,
        'return'         => 'ids',
    ) );

    foreach ( $order_ids as $order_id ) {
        $lock = instapay_woo_lock_order( $order_id );
        if ( ! $lock ) {
            continue;
        }
        try {
            $order    = wc_get_order( $order_id );
            $modified = $order ? $order->get_date_modified() : null;
            // Only orders the customer placed; manual admin orders are left alone.
            if ( $order && $order->has_status( 'on-hold' ) && ! $order->get_date_paid() && $modified && $modified->getTimestamp() < $cutoff
                && in_array( $order->get_created_via(), array( 'checkout', 'store-api' ), true ) ) {
                /* translators: %d: number of hours */
                $order->update_status( 'cancelled', sprintf( __( 'Instapay payment was not received within %d hours.', 'instapay-gateway-for-egypt' ), $hours ) );
            }
        } finally {
            instapay_woo_unlock_order( $lock );
        }
    }
}

add_action( 'woocommerce_before_delete_order', 'instapay_woo_delete_receipt_file_on_order_delete' );
function instapay_woo_delete_receipt_file_on_order_delete( $order_id ) {
    $receipt_path = instapay_woo_get_valid_receipt_path( $order_id );
    if ( $receipt_path ) {
        wp_delete_file( $receipt_path );
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
        'status'  => 'wc-payment-review',
        'limit'   => 5,
        'orderby' => 'modified',
        'order'   => 'ASC',
    );
    $orders = wc_get_orders( $args );

    if ( empty( $orders ) ) {
        echo '<p>' . esc_html__( 'No orders currently awaiting review.', 'instapay-gateway-for-egypt' ) . '</p>';
        return;
    }

    echo '<ul class="instapay-dashboard-list">';
    foreach ( $orders as $order ) {
        $uploaded_at = (int) $order->get_meta( '_instapay_uploaded_at' );
        $since       = $uploaded_at ? $uploaded_at : ( $order->get_date_modified() ? $order->get_date_modified()->getTimestamp() : time() );
        printf(
            '<li class="instapay-dashboard-order"><a href="%s"><strong>#%s</strong></a> <span class="instapay-dashboard-amount">%s</span> <span class="instapay-dashboard-wait">%s</span></li>',
            esc_url( $order->get_edit_order_url() ),
            esc_html( $order->get_order_number() ),
            wp_kses_post( $order->get_formatted_order_total() ),
            /* translators: %s: human-readable time, e.g. "2 hours" */
            esc_html( sprintf( __( 'waiting %s', 'instapay-gateway-for-egypt' ), human_time_diff( $since ) ) )
        );
    }
    echo '</ul>';

    $all_url = admin_url( 'edit.php?post_status=wc-payment-review&post_type=shop_order' );
    if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
        $all_url = admin_url( 'admin.php?page=wc-orders&status=wc-payment-review' );
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
    if ( 'instapay_receipt' !== $column ) {
        return;
    }

    $order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
    if ( ! $order || 'instapay' !== $order->get_payment_method() ) {
        echo '<span class="instapay-receipt-empty">-</span>';
        return;
    }

    // Metadata is enough for an icon; the file itself is validated when viewed.
    if ( 'payment-review' === $order->get_status() ) {
        echo '<span class="dashicons dashicons-camera instapay-receipt-review" title="' . esc_attr__( 'Receipt uploaded - Awaiting review', 'instapay-gateway-for-egypt' ) . '"></span>';
    } elseif ( '' !== $order->get_meta( '_instapay_receipt_filename' ) ) {
        echo '<span class="dashicons dashicons-yes-alt instapay-receipt-attached" title="' . esc_attr__( 'Receipt attached', 'instapay-gateway-for-egypt' ) . '"></span>';
    } else {
        echo '<span class="instapay-receipt-empty">-</span>';
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
            'order_id'              => array(
                'required'          => true,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ( $value ) {
                    return absint( $value ) > 0;
                },
            ),
            'order_key'             => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'transaction_reference' => array(
                'required'          => false,
                'sanitize_callback' => 'instapay_woo_clean_reference',
            ),
        ),
    ) );
}

function instapay_woo_rest_permissions_check( $request ) {
    if ( 'yes' !== instapay_woo_setting( 'enable_rest_uploads', 'no' ) ) {
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
    $file = instapay_woo_get_uploaded_file();
    if ( is_wp_error( $file ) ) {
        return new WP_Error( 'instapay_rest_no_file', $file->get_error_message(), array( 'status' => 400 ) );
    }

    $result = instapay_woo_receive_receipt(
        absint( $request->get_param( 'order_id' ) ),
        wc_clean( $request->get_param( 'order_key' ) ),
        $file,
        (string) $request->get_param( 'transaction_reference' ),
        'rest'
    );
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return rest_ensure_response( array(
        'success' => true,
        'message' => __( 'Receipt uploaded successfully.', 'instapay-gateway-for-egypt' )
    ) );
}

// ----------------------------------------------------------------------
// Privacy (WordPress personal data tools)
// ----------------------------------------------------------------------
add_action( 'admin_init', 'instapay_woo_privacy_policy_content' );
function instapay_woo_privacy_policy_content() {
    if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
        return;
    }

    $content = '<p>' . __( 'When you pay with Instapay, we ask you to upload a screenshot of your payment receipt and, optionally, the transaction reference. We use them only to verify your payment. The receipt may show your name, account or phone number, the amount and the date.', 'instapay-gateway-for-egypt' ) . '</p>'
        . '<p>' . __( 'Receipts are stored privately on this website and are visible only to you and to store staff who handle orders. Receipts for cancelled, failed or refunded orders are deleted automatically after 30 days. You can ask us to export or delete your data.', 'instapay-gateway-for-egypt' ) . '</p>';

    wp_add_privacy_policy_content( __( 'Instapay Gateway for Egypt', 'instapay-gateway-for-egypt' ), wp_kses_post( $content ) );
}

function instapay_woo_orders_for_email( $email_address, $page ) {
    return wc_get_orders( array(
        'customer'       => $email_address,
        'payment_method' => 'instapay',
        'limit'          => 10,
        'page'           => max( 1, (int) $page ),
    ) );
}

add_filter( 'wp_privacy_personal_data_exporters', 'instapay_woo_register_privacy_exporter' );
function instapay_woo_register_privacy_exporter( $exporters ) {
    $exporters['instapay-gateway-for-egypt'] = array(
        'exporter_friendly_name' => __( 'Instapay receipts', 'instapay-gateway-for-egypt' ),
        'callback'               => 'instapay_woo_privacy_exporter',
    );
    return $exporters;
}

function instapay_woo_privacy_exporter( $email_address, $page = 1 ) {
    $orders = instapay_woo_orders_for_email( $email_address, $page );
    $items  = array();

    foreach ( $orders as $order ) {
        $data = array(
            array(
                'name'  => __( 'Order number', 'instapay-gateway-for-egypt' ),
                'value' => $order->get_order_number(),
            ),
            array(
                'name'  => __( 'Receipt on file', 'instapay-gateway-for-egypt' ),
                'value' => instapay_woo_get_valid_receipt_path( $order->get_id() ) ? __( 'Yes', 'instapay-gateway-for-egypt' ) : __( 'No', 'instapay-gateway-for-egypt' ),
            ),
        );
        $reference = (string) $order->get_meta( '_instapay_transaction_reference' );
        if ( '' !== $reference ) {
            $data[] = array(
                'name'  => __( 'Transaction reference', 'instapay-gateway-for-egypt' ),
                'value' => $reference,
            );
        }

        $items[] = array(
            'group_id'    => 'instapay-receipts',
            'group_label' => __( 'Instapay receipts', 'instapay-gateway-for-egypt' ),
            'item_id'     => 'instapay-receipt-' . $order->get_id(),
            'data'        => $data,
        );
    }

    return array(
        'data' => $items,
        'done' => count( $orders ) < 10,
    );
}

add_filter( 'wp_privacy_personal_data_erasers', 'instapay_woo_register_privacy_eraser' );
function instapay_woo_register_privacy_eraser( $erasers ) {
    $erasers['instapay-gateway-for-egypt'] = array(
        'eraser_friendly_name' => __( 'Instapay receipts', 'instapay-gateway-for-egypt' ),
        'callback'             => 'instapay_woo_privacy_eraser',
    );
    return $erasers;
}

/**
 * Follows WooCommerce's "Remove personal data from orders on request" setting, so receipts
 * are erased exactly when the rest of the order's personal data is.
 */
function instapay_woo_privacy_eraser( $email_address, $page = 1 ) {
    $orders   = instapay_woo_orders_for_email( $email_address, $page );
    $erase    = 'yes' === get_option( 'woocommerce_erasure_request_removes_order_data', 'no' );
    $removed  = false;
    $retained = false;
    $messages = array();

    foreach ( $orders as $order ) {
        if ( '' === $order->get_meta( '_instapay_receipt_filename' ) && '' === $order->get_meta( '_instapay_transaction_reference' ) ) {
            continue;
        }
        if ( ! $erase ) {
            $retained = true;
            continue;
        }
        instapay_woo_delete_receipt( $order->get_id() );
        $order = wc_get_order( $order->get_id() );
        $order->delete_meta_data( '_instapay_transaction_reference' );
        $order->delete_meta_data( '_instapay_rejection_reason' );
        $order->save();
        $removed = true;
    }

    if ( $retained ) {
        $messages[] = __( 'Instapay receipts were kept because WooCommerce is set to keep order data on erasure requests.', 'instapay-gateway-for-egypt' );
    }

    return array(
        'items_removed'  => $removed,
        'items_retained' => $retained,
        'messages'       => $messages,
        'done'           => count( $orders ) < 10,
    );
}
