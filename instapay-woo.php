<?php
/**
 * Plugin Name: Instapay WooCommerce Gateway
 * Description: Instapay payment gateway for WooCommerce with secure receipt upload and AI-powered manager verification.
 * Version: 1.0.0
 * Author: Recipe Code
 * Author URI: https://recipe.codes
 * Text Domain: instapay-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'INSTAPAY_WOO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INSTAPAY_WOO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Check if WooCommerce is active on activation
register_activation_hook( __FILE__, 'instapay_woo_activation_check' );
function instapay_woo_activation_check() {
    if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        if ( is_multisite() ) {
            $plugins = get_site_option( 'active_sitewide_plugins' );
            if ( isset( $plugins['woocommerce/woocommerce.php'] ) ) {
                return;
            }
        }
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( __( 'Instapay WooCommerce Gateway requires WooCommerce to be installed and active.', 'instapay-woo' ), 'Plugin Dependency Error', array( 'back_link' => true ) );
    }
}

// Load text domain for translations
add_action( 'plugins_loaded', 'instapay_woo_load_textdomain' );
function instapay_woo_load_textdomain() {
    load_plugin_textdomain( 'instapay-woo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Add custom order status for payment review
add_action( 'init', 'instapay_woo_register_order_status' );
function instapay_woo_register_order_status() {
    register_post_status( 'wc-payment-review', array(
        'label'                     => _x( 'Payment Review', 'Order status', 'instapay-woo' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        /* translators: %s: count */
        'label_count'               => _n_noop( 'Payment Review <span class="count">(%s)</span>', 'Payment Review <span class="count">(%s)</span>', 'instapay-woo' )
    ) );
}

add_filter( 'wc_order_statuses', 'instapay_woo_add_order_status' );
function instapay_woo_add_order_status( $order_statuses ) {
    $new_statuses = array();
    foreach ( $order_statuses as $key => $status ) {
        $new_statuses[ $key ] = $status;
        // Insert it right after 'on-hold'
        if ( 'wc-on-hold' === $key ) {
            $new_statuses['wc-payment-review'] = __( 'Payment Review', 'instapay-woo' );
        }
    }
    // Fallback if on-hold wasn't found
    if ( ! isset( $new_statuses['wc-payment-review'] ) ) {
        $new_statuses['wc-payment-review'] = __( 'Payment Review', 'instapay-woo' );
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

add_filter( 'woocommerce_payment_gateways', 'instapay_woo_add_gateway' );
function instapay_woo_add_gateway( $methods ) {
    $methods[] = 'WC_Gateway_Instapay';
    return $methods;
}

// Handle secure file download for admin and customer
add_action( 'init', 'instapay_woo_secure_image_view' );
function instapay_woo_secure_image_view() {
    if ( isset( $_GET['instapay_view_receipt'] ) && isset( $_GET['order_id'] ) ) {
        $order_id = intval( $_GET['order_id'] );
        $order = wc_get_order( $order_id );
        
        $can_view = false;
        if ( current_user_can( 'edit_shop_orders' ) ) {
            $can_view = true;
        } elseif ( is_user_logged_in() && $order && $order->get_user_id() === get_current_user_id() ) {
            $can_view = true;
        }
        
        if ( ! $can_view ) {
            wp_die( 'Unauthorized access' );
        }
        
        $receipt_path = get_post_meta( $order_id, '_instapay_receipt_path', true );
        
        if ( $receipt_path && file_exists( $receipt_path ) ) {
            $file_info = wp_check_filetype( $receipt_path );
            $mime = $file_info['type'] ? $file_info['type'] : 'image/jpeg';
            header( 'Content-Type: ' . $mime );
            readfile( $receipt_path );
            exit;
        } else {
            wp_die( 'Receipt not found or has been removed.' );
        }
    }
}

// Global AJAX hooks for receipt upload
add_action( 'wp_ajax_instapay_upload_receipt', 'instapay_woo_handle_receipt_upload' );
add_action( 'wp_ajax_nopriv_instapay_upload_receipt', 'instapay_woo_handle_receipt_upload' );

function instapay_woo_process_upload_file( $file, $order_id ) {
    $allowed_mimes = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'webp'         => 'image/webp'
    );
    
    // Check file type using WordPress function
    $validate_file = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );

    if ( ! $validate_file['ext'] || ! $validate_file['type'] ) {
        return new WP_Error( 'invalid_type', __( 'Invalid file type. Only JPG, PNG, and WebP images are allowed.', 'instapay-woo' ) );
    }
    
    if ( $file['size'] > 5 * 1024 * 1024 ) {
        return new WP_Error( 'too_large', __( 'File size too large. Maximum allowed is 5MB.', 'instapay-woo' ) );
    }

    $upload_dir = wp_upload_dir();
    $secure_dir = $upload_dir['basedir'] . '/instapay_receipts';
    
    if ( ! file_exists( $secure_dir ) ) {
        wp_mkdir_p( $secure_dir );
        file_put_contents( $secure_dir . '/.htaccess', "Order deny,allow\nDeny from all" );
        file_put_contents( $secure_dir . '/index.php', "<?php // Silence is golden" );
    }

    $filename = 'order_' . $order_id . '_' . wp_generate_password( 12, false ) . '.' . $validate_file['ext'];
    $file_path = $secure_dir . '/' . $filename;

    if ( move_uploaded_file( $file['tmp_name'], $file_path ) ) {
        
        $settings = get_option( 'woocommerce_instapay_settings', array() );
        if ( isset( $settings['enable_compression'] ) && $settings['enable_compression'] === 'yes' ) {
            $editor = wp_get_image_editor( $file_path );
            if ( ! is_wp_error( $editor ) ) {
                $editor->resize( 1200, 1200, false );
                $editor->set_quality( 80 );
                $editor->save( $file_path );
            }
        }
        
        update_post_meta( $order_id, '_instapay_receipt_path', $file_path );
        update_post_meta( $order_id, '_instapay_receipt_filename', $filename );

        return true;
    }

    return new WP_Error( 'save_failed', __( 'Failed to save file securely. Please check directory permissions.', 'instapay-woo' ) );
}

function instapay_woo_handle_receipt_upload() {
    if ( ! isset( $_POST['order_id'], $_POST['order_key'], $_POST['instapay_nonce'] ) ) {
        wp_send_json_error( __( 'Missing parameters.', 'instapay-woo' ) );
    }

    $order_id  = intval( $_POST['order_id'] );
    $order_key = sanitize_text_field( $_POST['order_key'] );

    if ( ! wp_verify_nonce( $_POST['instapay_nonce'], 'instapay_upload_nonce_' . $order_id ) ) {
        wp_send_json_error( __( 'Security verification failed.', 'instapay-woo' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_order_key() !== $order_key ) {
        wp_send_json_error( __( 'Invalid order.', 'instapay-woo' ) );
    }

    if ( empty( $_FILES['instapay_receipt'] ) || $_FILES['instapay_receipt']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( __( 'No file uploaded or an upload error occurred.', 'instapay-woo' ) );
    }

    $result = instapay_woo_process_upload_file( $_FILES['instapay_receipt'], $order_id );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    $order->update_status( 'wc-payment-review', __( 'Instapay receipt uploaded. Awaiting manager approval.', 'instapay-woo' ) );
    wp_send_json_success( __( 'Receipt uploaded successfully. Order is under review.', 'instapay-woo' ) );
}

add_action( 'wp_ajax_instapay_quick_action', 'instapay_woo_quick_action' );
function instapay_woo_quick_action() {
    check_ajax_referer( 'instapay_quick_action', 'nonce' );

    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        wp_send_json_error( __( 'Permission denied.', 'instapay-woo' ) );
    }

    $order_id   = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    $new_status = isset( $_POST['new_status'] ) ? sanitize_text_field( $_POST['new_status'] ) : '';
    $rejection_reason = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( $_POST['rejection_reason'] ) : '';

    if ( ! $order_id || ! in_array( $new_status, array( 'processing', 'completed', 'pending', 'cancelled' ) ) ) {
        wp_send_json_error( __( 'Invalid parameters.', 'instapay-woo' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( __( 'Invalid order.', 'instapay-woo' ) );
    }

    $settings = get_option( 'woocommerce_instapay_settings', array() );
    $current_user = wp_get_current_user();
    
    $note = '';
    if ( isset( $settings['enable_logging'] ) && $settings['enable_logging'] === 'yes' ) {
        $note = sprintf( __( 'Instapay payment %s by manager (%s).', 'instapay-woo' ), $new_status, $current_user->display_name );
        if ( $new_status === 'pending' && ! empty( $rejection_reason ) ) {
            $note .= ' ' . sprintf( __( 'Reason: %s', 'instapay-woo' ), $rejection_reason );
        }
    } else {
        $note = __( 'Status updated via Instapay quick action.', 'instapay-woo' );
    }

    $order->update_status( $new_status, $note );

    if ( $new_status === 'pending' && isset( $settings['enable_reject_email'] ) && $settings['enable_reject_email'] === 'yes' ) {
        // Send rejection email
        $mailer = WC()->mailer();
        $email_heading = __( 'Payment Receipt Rejected', 'instapay-woo' );
        
        ob_start();
        wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
        echo '<p>' . sprintf( __( 'Hello %s,', 'instapay-woo' ), esc_html( $order->get_billing_first_name() ) ) . '</p>';
        echo '<p>' . sprintf( __( 'We reviewed your Instapay payment receipt for order #%s, but unfortunately it was invalid or unreadable.', 'instapay-woo' ), $order->get_order_number() ) . '</p>';
        
        if ( ! empty( $rejection_reason ) ) {
            echo '<p><strong>' . __( 'Reason:', 'instapay-woo' ) . '</strong> ' . nl2br( esc_html( $rejection_reason ) ) . '</p>';
        }
        
        echo '<p><a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . __( 'Click here to upload a new receipt', 'instapay-woo' ) . '</a></p>';
        wc_get_template( 'emails/email-footer.php' );
        $message = ob_get_clean();
        
        $mailer->send( $order->get_billing_email(), __( 'Action Required: Payment Receipt Rejected', 'instapay-woo' ), $message, "Content-Type: text/html\r\n" );
    }

    wp_send_json_success( __( 'Order status updated successfully.', 'instapay-woo' ) );
}

// Clean up old receipts
if ( ! wp_next_scheduled( 'instapay_woo_cleanup_cron' ) ) {
    wp_schedule_event( time(), 'daily', 'instapay_woo_cleanup_cron' );
}

add_action( 'instapay_woo_cleanup_cron', 'instapay_woo_cleanup_receipts' );
function instapay_woo_cleanup_receipts() {
    $settings = get_option( 'woocommerce_instapay_settings', array() );
    if ( empty( $settings['enable_cleanup'] ) || $settings['enable_cleanup'] !== 'yes' ) {
        return;
    }

    // Get orders older than 30 days that are cancelled, failed, refunded
    $args = array(
        'status' => array( 'wc-cancelled', 'wc-failed', 'wc-refunded' ),
        'date_created' => '<' . ( time() - ( 30 * DAY_IN_SECONDS ) ),
        'limit' => -1,
        'return' => 'ids',
    );
    $orders = wc_get_orders( $args );
    foreach ( $orders as $order_id ) {
        $path = get_post_meta( $order_id, '_instapay_receipt_path', true );
        if ( $path && file_exists( $path ) ) {
            unlink( $path );
            delete_post_meta( $order_id, '_instapay_receipt_path' );
            delete_post_meta( $order_id, '_instapay_receipt_filename' );
        }
    }
}

// ----------------------------------------------------------------------
// Dashboard Widget
// ----------------------------------------------------------------------
add_action( 'wp_dashboard_setup', 'instapay_woo_add_dashboard_widgets' );
function instapay_woo_add_dashboard_widgets() {
    wp_add_dashboard_widget(
        'instapay_woo_dashboard_widget',
        __( 'Instapay Receipts Awaiting Review', 'instapay-woo' ),
        'instapay_woo_dashboard_widget_display'
    );
}

function instapay_woo_dashboard_widget_display() {
    $args = array(
        'status' => 'wc-payment-review',
        'limit'  => 5,
    );
    $orders = wc_get_orders( $args );

    if ( empty( $orders ) ) {
        echo '<p>' . __( 'No orders currently awaiting review.', 'instapay-woo' ) . '</p>';
        return;
    }

    echo '<ul>';
    foreach ( $orders as $order ) {
        $edit_url = admin_url( 'post.php?post=' . absint( $order->get_id() ) . '&action=edit' );
        if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $edit_url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . absint( $order->get_id() ) );
        }
        printf( 
            '<li style="margin-bottom:8px;"><a href="%s"><strong>#%s</strong></a> - %s <span style="color:#646970; font-size:12px;">(%s)</span></li>', 
            esc_url( $edit_url ), 
            $order->get_order_number(), 
            wp_strip_all_tags( $order->get_formatted_order_total() ),
            wc_format_datetime( $order->get_date_created() )
        );
    }
    echo '</ul>';
    
    $all_url = admin_url( 'edit.php?post_status=wc-payment-review&post_type=shop_order' );
    if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
        $all_url = admin_url( 'admin.php?page=wc-orders&status=payment-review' );
    }
    echo '<p><a href="' . esc_url( $all_url ) . '" class="button">' . __( 'View All Pending', 'instapay-woo' ) . '</a></p>';
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
            $new_columns['instapay_receipt'] = __( 'Instapay', 'instapay-woo' );
        }
    }
    return $new_columns;
}

add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'instapay_woo_render_order_column', 10, 2 ); // HPOS
add_action( 'manage_shop_order_posts_custom_column', 'instapay_woo_render_order_column', 10, 2 ); // Legacy

function instapay_woo_render_order_column( $column, $order_id ) {
    if ( 'instapay_receipt' === $column ) {
        $order = wc_get_order( $order_id );
        if ( $order && $order->get_payment_method() === 'instapay' ) {
            if ( $order->get_status() === 'payment-review' ) {
                echo '<span class="dashicons dashicons-camera" style="color: #f59e0b;" title="' . esc_attr__( 'Receipt uploaded - Awaiting review', 'instapay-woo' ) . '"></span>';
            } elseif ( get_post_meta( $order_id, '_instapay_receipt_path', true ) ) {
                echo '<span class="dashicons dashicons-yes-alt" style="color: #10b981;" title="' . esc_attr__( 'Receipt attached', 'instapay-woo' ) . '"></span>';
            } else {
                echo '<span style="color: #94a3b8;">-</span>';
            }
        } else {
            echo '<span style="color: #cbd5e1;">-</span>';
        }
    }
}

// ----------------------------------------------------------------------
// Attach Receipt to Admin New Order Email
// ----------------------------------------------------------------------
add_filter( 'woocommerce_email_attachments', 'instapay_woo_attach_receipt_to_email', 10, 4 );
function instapay_woo_attach_receipt_to_email( $attachments, $email_id, $order, $email ) {
    if ( ! $order instanceof WC_Order ) {
        return $attachments;
    }
    
    // Attach to the admin "new_order" email or "customer_processing" just in case.
    if ( in_array( $email_id, array( 'new_order' ) ) ) {
        if ( $order->get_payment_method() === 'instapay' ) {
            $path = get_post_meta( $order->get_id(), '_instapay_receipt_path', true );
            if ( $path && file_exists( $path ) ) {
                $attachments[] = $path;
            }
        }
    }
    
    return $attachments;
}

// ----------------------------------------------------------------------
// Headless REST API Endpoint
// ----------------------------------------------------------------------
add_action( 'rest_api_init', 'instapay_woo_register_rest_route' );
function instapay_woo_register_rest_route() {
    register_rest_route( 'wc/v3', '/instapay/upload', array(
        'methods' => 'POST',
        'callback' => 'instapay_woo_rest_upload_receipt',
        'permission_callback' => 'instapay_woo_rest_permissions_check',
    ) );
}

function instapay_woo_rest_permissions_check( $request ) {
    $order_id = $request->get_param( 'order_id' );
    $order_key = $request->get_param( 'order_key' );
    
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_order_key() !== $order_key ) {
        return new WP_Error( 'instapay_rest_unauthorized', __( 'Invalid order ID or key.', 'instapay-woo' ), array( 'status' => 401 ) );
    }
    return true;
}

function instapay_woo_rest_upload_receipt( $request ) {
    $order_id = $request->get_param( 'order_id' );
    
    if ( empty( $_FILES['instapay_receipt'] ) || $_FILES['instapay_receipt']['error'] !== UPLOAD_ERR_OK ) {
        return new WP_Error( 'instapay_rest_no_file', __( 'No file uploaded.', 'instapay-woo' ), array( 'status' => 400 ) );
    }

    $result = instapay_woo_process_upload_file( $_FILES['instapay_receipt'], $order_id );

    if ( is_wp_error( $result ) ) {
        return $result; // Will output 400/500 automatically in REST
    }

    $order = wc_get_order( $order_id );
    $order->update_status( 'wc-payment-review', __( 'Instapay receipt uploaded via API. Awaiting manager approval.', 'instapay-woo' ) );

    return rest_ensure_response( array(
        'success' => true,
        'message' => __( 'Receipt uploaded successfully.', 'instapay-woo' )
    ) );
}
