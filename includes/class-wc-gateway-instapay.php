<?php
/**
 * Instapay payment gateway.
 *
 * @package Instapay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Instapay extends WC_Payment_Gateway {

	public $instapay_ipa;
	public $instapay_phone;
	public $instapay_payment_url;
	public $qr_code_url;
	public $enable_reject_email;
	public $enable_compression;
	public $enable_cleanup;
	public $enable_logging;
	public $enable_rest_uploads;

	public function __construct() {
		$this->id                 = 'instapay';
		$this->icon               = apply_filters( 'woocommerce_instapay_icon', INSTAPAY_WOO_PLUGIN_URL . 'img/InstaPay.webp' );
		$this->has_fields         = false;
		$this->method_title       = __( 'Instapay (Egypt)', 'instapay-gateway-for-egypt' );
		$this->method_description = __( 'Accept Instapay payments and let customers securely upload a transaction receipt.', 'instapay-gateway-for-egypt' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title                 = $this->get_option( 'title' );
		$this->description           = $this->get_option( 'description' );
		$this->instapay_ipa          = $this->get_option( 'instapay_ipa' );
		$this->instapay_phone        = $this->get_option( 'instapay_phone' );
		$this->instapay_payment_url  = $this->get_option( 'instapay_payment_url' );
		$this->qr_code_url           = $this->get_option( 'qr_code_url' );
		$this->enable_reject_email   = $this->get_option( 'enable_reject_email' );
		$this->enable_compression    = $this->get_option( 'enable_compression' );
		$this->enable_cleanup        = $this->get_option( 'enable_cleanup' );
		$this->enable_logging        = $this->get_option( 'enable_logging' );
		$this->enable_rest_uploads   = $this->get_option( 'enable_rest_uploads' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_view_order', array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_instapay_meta_box' ), 10, 2 );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'               => array(
				'title'   => __( 'Enable/Disable', 'instapay-gateway-for-egypt' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Instapay Payment', 'instapay-gateway-for-egypt' ),
				'default' => 'yes',
			),
			'title'                 => array(
				'title'       => __( 'Title', 'instapay-gateway-for-egypt' ),
				'type'        => 'text',
				'description' => __( 'The payment method title shown at checkout.', 'instapay-gateway-for-egypt' ),
				'default'     => __( 'Instapay', 'instapay-gateway-for-egypt' ),
				'desc_tip'    => true,
			),
			'description'           => array(
				'title'       => __( 'Description', 'instapay-gateway-for-egypt' ),
				'type'        => 'textarea',
				'description' => __( 'Payment instructions shown to the customer.', 'instapay-gateway-for-egypt' ),
				'default'     => __( 'Please transfer the total amount to our Instapay account and upload the receipt screenshot.', 'instapay-gateway-for-egypt' ),
			),
			'instapay_ipa'          => array(
				'title'       => __( 'Instapay Payment Address (IPA)', 'instapay-gateway-for-egypt' ),
				'type'        => 'text',
				'description' => __( 'Your Instapay address, for example username@instapay.', 'instapay-gateway-for-egypt' ),
			),
			'instapay_phone'        => array(
				'title'       => __( 'Instapay Phone Number', 'instapay-gateway-for-egypt' ),
				'type'        => 'text',
				'description' => __( 'Your mobile number registered with Instapay.', 'instapay-gateway-for-egypt' ),
			),
			'instapay_payment_url'  => array(
				'title'       => __( 'Instapay Payment URL', 'instapay-gateway-for-egypt' ),
				'type'        => 'url',
				'description' => __( 'Optional payment URL shown on mobile devices.', 'instapay-gateway-for-egypt' ),
			),
			'qr_code_url'           => array(
				'title'       => __( 'QR Code Image URL', 'instapay-gateway-for-egypt' ),
				'type'        => 'url',
				'description' => __( 'URL of your Instapay QR code image.', 'instapay-gateway-for-egypt' ),
			),
			'enable_reject_email'   => array(
				'title'   => __( 'Rejection Emails', 'instapay-gateway-for-egypt' ),
				'type'    => 'checkbox',
				'label'   => __( 'Email the customer when a receipt is rejected', 'instapay-gateway-for-egypt' ),
				'default' => 'yes',
			),
			'enable_compression'    => array(
				'title'   => __( 'Image Compression', 'instapay-gateway-for-egypt' ),
				'type'    => 'checkbox',
				'label'   => __( 'Resize uploaded receipts to a maximum of 1200 pixels', 'instapay-gateway-for-egypt' ),
				'default' => 'yes',
			),
			'enable_cleanup'        => array(
				'title'   => __( 'Automatic Cleanup', 'instapay-gateway-for-egypt' ),
				'type'    => 'checkbox',
				'label'   => __( 'Delete receipts for failed, cancelled, or refunded orders after 30 days', 'instapay-gateway-for-egypt' ),
				'default' => 'yes',
			),
			'enable_logging'        => array(
				'title'   => __( 'Audit Notes', 'instapay-gateway-for-egypt' ),
				'type'    => 'checkbox',
				'label'   => __( 'Record manager actions in order notes', 'instapay-gateway-for-egypt' ),
				'default' => 'yes',
			),
			'enable_rest_uploads'   => array(
				'title'       => __( 'REST Uploads', 'instapay-gateway-for-egypt' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable receipt uploads through the REST API', 'instapay-gateway-for-egypt' ),
				'description' => __( 'Leave disabled unless a trusted mobile or headless client needs this endpoint.', 'instapay-gateway-for-egypt' ),
				'default'     => 'no',
			),
		);
	}

	public function is_available() {
		return parent::is_available() && 'EGP' === get_woocommerce_currency();
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		$order->update_status( 'pending', __( 'Awaiting Instapay payment receipt.', 'instapay-gateway-for-egypt' ) );
		wc_reduce_stock_levels( $order_id );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $this->id !== $order->get_payment_method() ) {
			return;
		}

		$receipt_path = instapay_woo_get_valid_receipt_path( $order->get_id() );
		$view_url     = $receipt_path ? instapay_woo_get_receipt_view_url( $order ) : '';
		?>
		<section class="instapay-payment-card">
			<h2 class="instapay-payment-title">
				<img class="instapay-title-logo" src="<?php echo esc_url( INSTAPAY_WOO_PLUGIN_URL . 'img/InstaPay.webp' ); ?>" alt="<?php esc_attr_e( 'Instapay', 'instapay-gateway-for-egypt' ); ?>" />
				<?php esc_html_e( 'Payment Instructions', 'instapay-gateway-for-egypt' ); ?>
			</h2>
			<p class="instapay-payment-desc"><?php echo esc_html( $this->description ); ?></p>
			<div class="instapay-flex-row">
				<?php if ( $this->qr_code_url ) : ?>
					<div class="instapay-qr-box"><img src="<?php echo esc_url( $this->qr_code_url ); ?>" alt="<?php esc_attr_e( 'Instapay QR code', 'instapay-gateway-for-egypt' ); ?>" /></div>
				<?php endif; ?>
				<?php if ( $this->instapay_ipa || $this->instapay_phone ) : ?>
					<div class="instapay-ipa-box">
						<p class="instapay-ipa-label"><?php esc_html_e( 'Send Payment To', 'instapay-gateway-for-egypt' ); ?></p>
						<?php if ( $this->instapay_ipa ) : ?><p class="instapay-ipa-value"><?php echo esc_html( $this->instapay_ipa ); ?></p><?php endif; ?>
						<?php if ( $this->instapay_phone ) : ?><p class="instapay-ipa-value"><?php echo esc_html( $this->instapay_phone ); ?></p><?php endif; ?>
						<?php if ( wp_is_mobile() && $this->instapay_payment_url ) : ?>
							<a class="instapay-submit-btn instapay-quick-pay" href="<?php echo esc_url( $this->instapay_payment_url, array( 'http', 'https', 'instapay' ) ); ?>"><?php esc_html_e( 'Quick Pay via Instapay App', 'instapay-gateway-for-egypt' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		if ( $receipt_path && in_array( $order->get_status(), array( 'payment-review', 'processing', 'completed' ), true ) ) {
			?>
			<div class="woocommerce-message instapay-success-message" role="alert"><?php esc_html_e( 'Receipt uploaded successfully. Your payment is under review.', 'instapay-gateway-for-egypt' ); ?></div>
			<h3><?php esc_html_e( 'Your Uploaded Receipt', 'instapay-gateway-for-egypt' ); ?></h3>
			<p><a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener"><img class="instapay-receipt-image" src="<?php echo esc_url( $view_url ); ?>" alt="<?php esc_attr_e( 'Receipt thumbnail', 'instapay-gateway-for-egypt' ); ?>" /></a></p>
			<?php
			return;
		}

		if ( $receipt_path ) {
			echo '<div class="woocommerce-error instapay-error-message" role="alert">' . esc_html__( 'Your previous receipt was rejected. Please upload a new valid screenshot.', 'instapay-gateway-for-egypt' ) . '</div>';
		}

		if ( ! instapay_woo_order_accepts_receipt( $order ) ) {
			return;
		}
		?>
		<div class="instapay-upload-container">
			<img class="instapay-upload-logo" src="<?php echo esc_url( INSTAPAY_WOO_PLUGIN_URL . 'img/InstaPay.webp' ); ?>" alt="<?php esc_attr_e( 'Instapay', 'instapay-gateway-for-egypt' ); ?>" />
			<h3 class="instapay-upload-title"><?php esc_html_e( 'Upload Payment Receipt', 'instapay-gateway-for-egypt' ); ?></h3>
			<p class="instapay-upload-desc"><?php esc_html_e( 'Please securely upload a screenshot of your successful transaction.', 'instapay-gateway-for-egypt' ); ?></p>
			<form class="instapay-receipt-form" enctype="multipart/form-data" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-select-message="<?php echo esc_attr__( 'Please select an image file first.', 'instapay-gateway-for-egypt' ); ?>" data-size-message="<?php echo esc_attr__( 'File size exceeds 5MB. Please upload a smaller image.', 'instapay-gateway-for-egypt' ); ?>" data-uploading-message="<?php echo esc_attr__( 'Uploading...', 'instapay-gateway-for-egypt' ); ?>" data-error-message="<?php echo esc_attr__( 'An error occurred during upload. Please try again.', 'instapay-gateway-for-egypt' ); ?>">
				<div class="instapay-dropzone">
					<span class="dashicons dashicons-upload instapay-dropzone-icon" aria-hidden="true"></span>
					<p class="instapay-dropzone-text"><?php esc_html_e( 'Click or drag an image here', 'instapay-gateway-for-egypt' ); ?></p>
					<p class="instapay-dropzone-subtext"><?php esc_html_e( 'Supports JPG, PNG, and WebP up to 5MB', 'instapay-gateway-for-egypt' ); ?></p>
					<input type="file" name="instapay_receipt" class="instapay-receipt-input" accept="image/jpeg,image/png,image/webp" required />
					<div class="instapay-file-name" aria-live="polite"></div>
				</div>
				<input type="hidden" name="action" value="instapay_upload_receipt" />
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
				<input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>" />
				<?php wp_nonce_field( 'instapay_upload_nonce_' . $order->get_id(), 'instapay_nonce' ); ?>
				<button type="submit" class="instapay-submit-btn"><?php esc_html_e( 'Securely Upload Receipt', 'instapay-gateway-for-egypt' ); ?></button>
				<div class="instapay-upload-status" aria-live="polite"></div>
			</form>
		</div>
		<?php
	}

	public function register_instapay_meta_box() {
		$screen = 'shop_order';
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$screen = wc_get_page_screen_id( 'shop-order' );
		}

		add_meta_box( 'instapay_verification_box', __( 'Instapay Verification', 'instapay-gateway-for-egypt' ), array( $this, 'admin_order_receipt_display' ), $screen, 'side', 'high' );
	}

	public function admin_order_receipt_display( $post_or_order_object ) {
		$order = $post_or_order_object instanceof WP_Post ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		if ( ! $order || $this->id !== $order->get_payment_method() ) {
			echo '<p>' . esc_html__( 'This order was not paid via Instapay.', 'instapay-gateway-for-egypt' ) . '</p>';
			return;
		}

		$receipt_path = instapay_woo_get_valid_receipt_path( $order->get_id() );
		if ( $receipt_path ) {
			add_thickbox();
			$view_url = add_query_arg( array( 'TB_iframe' => 'true', 'width' => 600, 'height' => 800 ), instapay_woo_get_receipt_view_url( $order, true ) );
			echo '<p>' . esc_html__( 'Customer uploaded a receipt screenshot.', 'instapay-gateway-for-egypt' ) . '</p>';
			echo '<p><a href="' . esc_url( $view_url ) . '" class="button button-primary thickbox">' . esc_html__( 'View Receipt Screenshot', 'instapay-gateway-for-egypt' ) . '</a></p>';
			echo '<p><a href="' . esc_url( $view_url ) . '" class="thickbox"><img class="instapay-admin-receipt" src="' . esc_url( instapay_woo_get_receipt_view_url( $order, true ) ) . '" alt="' . esc_attr__( 'Receipt thumbnail', 'instapay-gateway-for-egypt' ) . '" /></a></p>';
			if ( 'payment-review' === $order->get_status() ) {
				echo '<p class="instapay-review-note">' . esc_html__( 'Status is Payment Review. Accept or reject the receipt below.', 'instapay-gateway-for-egypt' ) . '</p>';
			}
		} else {
			echo '<p class="instapay-no-receipt">' . esc_html__( 'No receipt has been uploaded yet.', 'instapay-gateway-for-egypt' ) . '</p>';
		}
		?>
		<div class="instapay-admin-actions" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'instapay_quick_action' ) ); ?>">
			<hr />
			<p><strong><?php esc_html_e( 'Quick Actions:', 'instapay-gateway-for-egypt' ); ?></strong></p>
			<?php if ( 'payment-review' === $order->get_status() ) : ?>
				<label for="instapay_rejection_reason"><strong><?php esc_html_e( 'Rejection Reason (Optional):', 'instapay-gateway-for-egypt' ); ?></strong></label>
				<textarea id="instapay_rejection_reason" rows="2" placeholder="<?php esc_attr_e( 'Enter reason for rejection...', 'instapay-gateway-for-egypt' ); ?>"></textarea>
				<button type="button" class="button button-primary instapay-quick-action" data-status="processing" data-confirm="<?php esc_attr_e( 'Accept this payment and mark the order as Processing?', 'instapay-gateway-for-egypt' ); ?>"><?php esc_html_e( 'Accept Payment', 'instapay-gateway-for-egypt' ); ?></button>
				<button type="button" class="button instapay-quick-action instapay-reject-action" data-status="pending" data-confirm="<?php esc_attr_e( 'Reject this receipt and require a new upload?', 'instapay-gateway-for-egypt' ); ?>"><?php esc_html_e( 'Reject Payment', 'instapay-gateway-for-egypt' ); ?></button>
			<?php endif; ?>
			<?php if ( in_array( $order->get_status(), array( 'pending', 'payment-review' ), true ) ) : ?>
				<button type="button" class="button instapay-quick-action" data-status="cancelled" data-confirm="<?php esc_attr_e( 'Cancel this order completely?', 'instapay-gateway-for-egypt' ); ?>"><?php esc_html_e( 'Cancel Order', 'instapay-gateway-for-egypt' ); ?></button>
			<?php else : ?>
				<p><?php esc_html_e( 'No quick actions are available for the current order status.', 'instapay-gateway-for-egypt' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
