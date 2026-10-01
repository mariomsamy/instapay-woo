<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen.
 * WordPress warns that deleting a plugin deletes its data; receipts contain
 * personal financial data, so they are not left behind unprotected.
 *
 * @package Instapay_Woo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

wp_clear_scheduled_hook( 'instapay_woo_cleanup_cron' );
wp_clear_scheduled_hook( 'instapay_woo_expire_cron' );

delete_option( 'woocommerce_instapay_settings' );
delete_option( 'instapay_woo_version' );
delete_site_transient( 'instapay_woo_github_release' );

// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'instapay_woo_lock_' ) . '%', $wpdb->esc_like( 'instapay_woo_upload_lock_' ) . '%' ) );

// Receipt files.
$instapay_woo_upload_dir   = wp_upload_dir();
$instapay_woo_receipts_dir = trailingslashit( $instapay_woo_upload_dir['basedir'] ) . 'instapay_receipts';
if ( is_dir( $instapay_woo_receipts_dir ) ) {
	foreach ( (array) scandir( $instapay_woo_receipts_dir ) as $instapay_woo_name ) {
		$instapay_woo_file = trailingslashit( $instapay_woo_receipts_dir ) . $instapay_woo_name;
		if ( is_file( $instapay_woo_file ) ) {
			wp_delete_file( $instapay_woo_file );
		}
	}
	@rmdir( $instapay_woo_receipts_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}

// Receipt metadata, for both order storage engines.
$instapay_woo_meta_keys = array( '_instapay_receipt_path', '_instapay_receipt_filename', '_instapay_receipt_hash', '_instapay_transaction_reference', '_instapay_receipt_rejected', '_instapay_rejection_reason', '_instapay_upload_count', '_instapay_upload_window', '_instapay_uploaded_at' );
$instapay_woo_in        = implode( ',', array_fill( 0, count( $instapay_woo_meta_keys ), '%s' ) );
// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ($instapay_woo_in)", $instapay_woo_meta_keys ) );
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_orders_meta' ) ) === $wpdb->prefix . 'wc_orders_meta' ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key IN ($instapay_woo_in)", $instapay_woo_meta_keys ) );
}
// phpcs:enable
