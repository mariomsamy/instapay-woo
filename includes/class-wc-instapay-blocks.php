<?php
/**
 * Instapay support for the WooCommerce block-based checkout.
 *
 * @package Instapay_Woo
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WC_Instapay_Blocks extends AbstractPaymentMethodType {

	protected $name = 'instapay';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_instapay_settings', array() );
	}

	public function is_active() {
		$gateway = instapay_woo_gateway();

		return $gateway ? $gateway->is_available() : false;
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'instapay-gateway-for-egypt-blocks',
			INSTAPAY_WOO_PLUGIN_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			INSTAPAY_WOO_VERSION,
			true
		);

		return array( 'instapay-gateway-for-egypt-blocks' );
	}

	public function get_payment_method_data() {
		$gateway = instapay_woo_gateway();

		return array(
			'title'       => $gateway ? $gateway->get_title() : __( 'Instapay', 'instapay-gateway-for-egypt' ),
			'description' => $gateway ? $gateway->get_description() : '',
			'icon'        => $gateway ? $gateway->icon : '',
			'supports'    => $gateway ? array_values( array_filter( $gateway->supports, array( $gateway, 'supports' ) ) ) : array( 'products' ),
		);
	}
}
