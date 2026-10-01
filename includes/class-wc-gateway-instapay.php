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
	public $ask_reference;

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
		$this->ask_reference         = $this->get_option( 'ask_reference' );

		// Display hooks (thank-you, view order, meta box) are registered once in instapay-woo.php.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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
				'description' => __( 'URL of your Instapay QR code image. Upload it to your Media Library so customers do not load it from another site.', 'instapay-gateway-for-egypt' ),
			),
			'ask_reference'         => array(
				'title'   => __( 'Transaction Reference', 'instapay-gateway-for-egypt' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ask customers for the Instapay transaction reference when they upload a receipt', 'instapay-gateway-for-egypt' ),
				'description' => __( 'Managers are warned when the same reference or the same receipt image is used on more than one order.', 'instapay-gateway-for-egypt' ),
				'default' => 'yes',
			),
			'unpaid_expiry_hours'   => array(
				'title'             => __( 'Cancel Unpaid Orders After (hours)', 'instapay-gateway-for-egypt' ),
				'type'              => 'number',
				'description'       => __( 'Orders still waiting for a receipt are cancelled and their stock released after this many hours. Use 0 to keep them until you cancel them.', 'instapay-gateway-for-egypt' ),
				'default'           => '48',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			'notify_email'          => array(
				'title'       => __( 'Review Notification Email', 'instapay-gateway-for-egypt' ),
				'type'        => 'email',
				'description' => __( 'Who is emailed when a receipt is waiting for review. Leave empty to use the site admin email.', 'instapay-gateway-for-egypt' ),
				'default'     => '',
				'placeholder' => get_option( 'admin_email' ),
			),
			'attach_receipt_email'  => array(
				'title'       => __( 'Attach Receipt to Email', 'instapay-gateway-for-egypt' ),
				'type'        => 'checkbox',
				'label'       => __( 'Attach the receipt image to the review notification email', 'instapay-gateway-for-egypt' ),
				'description' => __( 'Receipts contain personal financial data. Leave this off to keep them only on your website.', 'instapay-gateway-for-egypt' ),
				'default'     => 'no',
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
			'retention_days'        => array(
				'title'             => __( 'Paid Receipt Retention (days)', 'instapay-gateway-for-egypt' ),
				'type'              => 'number',
				'description'       => __( 'Delete receipts of processing and completed orders this many days after the order was placed. Use 0 to keep them. Data protection law expects a defined retention period; keep receipts as long as you need them for accounting and disputes.', 'instapay-gateway-for-egypt' ),
				'default'           => '0',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
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

		// On-hold, like bank transfer: WooCommerce does not auto-cancel it, and it sends the
		// customer the on-hold email (with Instapay instructions) and the store the new-order email.
		$order->update_status( 'on-hold', __( 'Awaiting Instapay payment receipt.', 'instapay-gateway-for-egypt' ) );
		wc_reduce_stock_levels( $order_id );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Customer-facing payment panel on the thank-you and My Account order pages.
	 * One card that follows the order through: send payment, upload receipt, confirmation.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $this->id !== $order->get_payment_method() ) {
			return;
		}

		$status   = $order->get_status();
		$paid     = $order->has_status( wc_get_is_paid_statuses() );
		$review   = 'payment-review' === $status;
		$awaiting = instapay_woo_order_accepts_receipt( $order ) && ! $review;

		// Cancelled, refunded and other closed orders: nothing to pay or upload.
		if ( ! $paid && ! $review && ! $awaiting ) {
			return;
		}

		$receipt_path = instapay_woo_get_valid_receipt_path( $order->get_id() );
		$view_url     = $receipt_path ? instapay_woo_get_receipt_view_url( $order ) : '';
		$step         = $paid ? 4 : ( $review ? 3 : 1 );
		?>
		<section class="instapay-panel" data-copied-text="<?php esc_attr_e( 'Copied', 'instapay-gateway-for-egypt' ); ?>" data-copy-failed-text="<?php esc_attr_e( 'Press and hold to copy', 'instapay-gateway-for-egypt' ); ?>">
			<header class="instapay-panel-header">
				<img class="instapay-title-logo" src="<?php echo esc_url( INSTAPAY_WOO_PLUGIN_URL . 'img/InstaPay.webp' ); ?>" alt="" />
				<h2 class="instapay-payment-title"><?php esc_html_e( 'Pay with Instapay', 'instapay-gateway-for-egypt' ); ?></h2>
				<?php $this->render_status_pill( $paid, $review ); ?>
			</header>

			<?php $this->render_steps( $step ); ?>

			<?php if ( $paid ) : ?>
				<div class="instapay-state instapay-state-success" role="status">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<div>
						<p class="instapay-state-title"><?php esc_html_e( 'Your Instapay payment has been confirmed. Thank you!', 'instapay-gateway-for-egypt' ); ?></p>
						<p><?php esc_html_e( 'We are now preparing your order.', 'instapay-gateway-for-egypt' ); ?></p>
					</div>
				</div>
				<?php $this->render_receipt_thumbnail( $view_url ); ?>
			<?php elseif ( $review ) : ?>
				<div class="instapay-state instapay-state-review" role="status">
					<span class="dashicons dashicons-clock" aria-hidden="true"></span>
					<div>
						<p class="instapay-state-title"><?php esc_html_e( 'Receipt uploaded successfully. Your payment is under review.', 'instapay-gateway-for-egypt' ); ?></p>
						<p><?php esc_html_e( 'We will check it against your order and email you as soon as it is confirmed. You do not need to do anything else.', 'instapay-gateway-for-egypt' ); ?></p>
					</div>
				</div>
				<?php $this->render_receipt_thumbnail( $view_url ); ?>
			<?php else : ?>
				<?php $this->render_payment_details( $order ); ?>
				<?php $this->render_upload_form( $order, $receipt_path ); ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_status_pill( $paid, $review ) {
		if ( $paid ) {
			echo '<span class="instapay-pill is-paid">' . esc_html__( 'Paid', 'instapay-gateway-for-egypt' ) . '</span>';
		} elseif ( $review ) {
			echo '<span class="instapay-pill is-review">' . esc_html__( 'Under review', 'instapay-gateway-for-egypt' ) . '</span>';
		} else {
			echo '<span class="instapay-pill is-waiting">' . esc_html__( 'Waiting for payment', 'instapay-gateway-for-egypt' ) . '</span>';
		}
	}

	private function render_steps( $current ) {
		$steps = array(
			1 => __( 'Send payment', 'instapay-gateway-for-egypt' ),
			2 => __( 'Upload receipt', 'instapay-gateway-for-egypt' ),
			3 => __( 'Confirmation', 'instapay-gateway-for-egypt' ),
		);
		echo '<ol class="instapay-steps" aria-label="' . esc_attr__( 'Payment progress', 'instapay-gateway-for-egypt' ) . '">';
		foreach ( $steps as $number => $label ) {
			// Steps 1 and 2 happen on the same screen, so both are "current" until the upload.
			$state = $number < $current ? 'is-done' : ( ( $number === $current || ( 1 === $current && 2 === $number ) ) ? 'is-current' : '' );
			printf(
				'<li class="%1$s"%2$s><span class="instapay-step-dot" aria-hidden="true">%3$s</span><span class="instapay-step-label">%4$s</span></li>',
				esc_attr( $state ),
				$number === $current ? ' aria-current="step"' : '',
				'is-done' === $state ? '&#10003;' : esc_html( number_format_i18n( $number ) ),
				esc_html( $label )
			);
		}
		echo '</ol>';
	}

	private function render_copy_row( $label, $display, $value ) {
		?>
		<div class="instapay-copy-row">
			<span class="instapay-copy-label"><?php echo esc_html( $label ); ?></span>
			<span class="instapay-copy-value" dir="ltr"><?php echo wp_kses_post( $display ); ?></span>
			<button type="button" class="instapay-copy" data-copy="<?php echo esc_attr( $value ); ?>">
				<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
				<span class="instapay-copy-text"><?php esc_html_e( 'Copy', 'instapay-gateway-for-egypt' ); ?></span>
				<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
			</button>
		</div>
		<?php
	}

	private function render_payment_details( $order ) {
		$amount = wc_format_decimal( $order->get_total(), wc_get_price_decimals() );
		?>
		<div class="instapay-step-block">
			<h3 class="instapay-step-title"><span class="instapay-step-dot" aria-hidden="true">1</span><?php esc_html_e( 'Send the payment', 'instapay-gateway-for-egypt' ); ?></h3>
			<?php if ( $this->description ) : ?>
				<p class="instapay-payment-desc"><?php echo esc_html( $this->description ); ?></p>
			<?php endif; ?>
			<div class="instapay-flex-row">
				<div class="instapay-ipa-box">
					<?php
					$this->render_copy_row( __( 'Amount', 'instapay-gateway-for-egypt' ), $order->get_formatted_order_total(), $amount );
					if ( $this->instapay_ipa ) {
						$this->render_copy_row( __( 'Instapay address', 'instapay-gateway-for-egypt' ), esc_html( $this->instapay_ipa ), $this->instapay_ipa );
					}
					if ( $this->instapay_phone ) {
						$this->render_copy_row( __( 'Phone number', 'instapay-gateway-for-egypt' ), esc_html( $this->instapay_phone ), $this->instapay_phone );
					}
					?>
					<?php if ( wp_is_mobile() && $this->instapay_payment_url ) : ?>
						<a class="instapay-submit-btn instapay-quick-pay" href="<?php echo esc_url( $this->instapay_payment_url, array( 'http', 'https', 'instapay' ) ); ?>"><?php esc_html_e( 'Quick Pay via Instapay App', 'instapay-gateway-for-egypt' ); ?></a>
					<?php endif; ?>
				</div>
				<?php if ( $this->qr_code_url ) : ?>
					<figure class="instapay-qr-box">
						<img src="<?php echo esc_url( $this->qr_code_url ); ?>" alt="<?php esc_attr_e( 'Instapay QR code', 'instapay-gateway-for-egypt' ); ?>" />
						<figcaption><?php esc_html_e( 'Scan with the Instapay app', 'instapay-gateway-for-egypt' ); ?></figcaption>
					</figure>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_upload_form( $order, $receipt_path ) {
		$rejected    = $receipt_path || 'yes' === $order->get_meta( '_instapay_receipt_rejected' );
		$reason      = (string) $order->get_meta( '_instapay_rejection_reason' );
		$privacy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
		$field_id    = 'instapay_reference_' . $order->get_id();
		?>
		<div class="instapay-step-block instapay-upload-container">
			<h3 class="instapay-step-title instapay-upload-title"><span class="instapay-step-dot" aria-hidden="true">2</span><?php esc_html_e( 'Upload Payment Receipt', 'instapay-gateway-for-egypt' ); ?></h3>
			<?php if ( $rejected ) : ?>
				<div class="instapay-state instapay-state-error instapay-error-message" role="alert">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<div>
						<p class="instapay-state-title"><?php esc_html_e( 'Your previous receipt was rejected. Please upload a new valid screenshot.', 'instapay-gateway-for-egypt' ); ?></p>
						<?php if ( '' !== $reason ) : ?>
							<p><?php echo esc_html__( 'Reason:', 'instapay-gateway-for-egypt' ) . ' ' . esc_html( $reason ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			<p class="instapay-upload-desc"><?php esc_html_e( 'After paying, take a screenshot of the Instapay confirmation screen and upload it here.', 'instapay-gateway-for-egypt' ); ?></p>
			<form class="instapay-receipt-form" enctype="multipart/form-data" novalidate data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-select-message="<?php echo esc_attr__( 'Please select an image file first.', 'instapay-gateway-for-egypt' ); ?>" data-size-message="<?php echo esc_attr__( 'File size exceeds 5MB. Please upload a smaller image.', 'instapay-gateway-for-egypt' ); ?>" data-type-message="<?php echo esc_attr__( 'Please choose a JPG, PNG or WebP image.', 'instapay-gateway-for-egypt' ); ?>" data-uploading-message="<?php echo esc_attr__( 'Uploading...', 'instapay-gateway-for-egypt' ); ?>" data-error-message="<?php echo esc_attr__( 'An error occurred during upload. Please try again.', 'instapay-gateway-for-egypt' ); ?>">
				<div class="instapay-dropzone">
					<div class="instapay-dropzone-empty">
						<span class="dashicons dashicons-format-image instapay-dropzone-icon" aria-hidden="true"></span>
						<p class="instapay-dropzone-text"><?php esc_html_e( 'Tap to choose a screenshot', 'instapay-gateway-for-egypt' ); ?></p>
						<p class="instapay-dropzone-subtext"><?php esc_html_e( 'or drag it here · JPG, PNG or WebP · up to 5MB', 'instapay-gateway-for-egypt' ); ?></p>
					</div>
					<div class="instapay-preview" hidden>
						<img class="instapay-preview-image" alt="<?php esc_attr_e( 'Selected receipt preview', 'instapay-gateway-for-egypt' ); ?>" />
						<p class="instapay-file-name" aria-live="polite"></p>
						<p class="instapay-change-hint"><?php esc_html_e( 'Tap to choose a different image', 'instapay-gateway-for-egypt' ); ?></p>
					</div>
					<input type="file" name="instapay_receipt" class="instapay-receipt-input" accept="image/jpeg,image/png,image/webp" aria-label="<?php esc_attr_e( 'Receipt screenshot', 'instapay-gateway-for-egypt' ); ?>" required />
				</div>
				<?php if ( 'no' !== $this->ask_reference ) : ?>
					<p class="instapay-reference-field">
						<label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Transaction reference (optional)', 'instapay-gateway-for-egypt' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="instapay_reference" maxlength="64" autocomplete="off" dir="ltr" pattern="[A-Za-z0-9\-]*" aria-describedby="<?php echo esc_attr( $field_id ); ?>_hint" />
						<small id="<?php echo esc_attr( $field_id ); ?>_hint"><?php esc_html_e( 'Shown on the Instapay confirmation screen. It helps us find your payment faster.', 'instapay-gateway-for-egypt' ); ?></small>
					</p>
				<?php endif; ?>
				<input type="hidden" name="action" value="instapay_upload_receipt" />
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
				<input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>" />
				<?php wp_nonce_field( 'instapay_upload_nonce_' . $order->get_id(), 'instapay_nonce' ); ?>
				<button type="submit" class="instapay-submit-btn"><?php esc_html_e( 'Securely Upload Receipt', 'instapay-gateway-for-egypt' ); ?></button>
				<div class="instapay-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden><span class="instapay-progress-bar"></span></div>
				<div class="instapay-upload-status" aria-live="polite"></div>
				<p class="instapay-privacy-note">
					<span class="dashicons dashicons-lock" aria-hidden="true"></span>
					<?php esc_html_e( 'Your receipt is stored privately and used only to verify this payment.', 'instapay-gateway-for-egypt' ); ?>
					<?php if ( $privacy_url ) : ?>
						<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'instapay-gateway-for-egypt' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}

	private function render_receipt_thumbnail( $view_url ) {
		if ( ! $view_url ) {
			return;
		}
		?>
		<details class="instapay-receipt-details">
			<summary><?php esc_html_e( 'Your Uploaded Receipt', 'instapay-gateway-for-egypt' ); ?></summary>
			<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener"><img class="instapay-receipt-image" src="<?php echo esc_url( $view_url ); ?>" loading="lazy" alt="<?php esc_attr_e( 'Receipt thumbnail', 'instapay-gateway-for-egypt' ); ?>" /></a>
		</details>
		<?php
	}

	public function admin_order_receipt_display( $post_or_order_object ) {
		$order = $post_or_order_object instanceof WP_Post ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		if ( ! $order || $this->id !== $order->get_payment_method() ) {
			echo '<p>' . esc_html__( 'This order was not paid via Instapay.', 'instapay-gateway-for-egypt' ) . '</p>';
			return;
		}

		$status       = $order->get_status();
		$receipt_path = instapay_woo_get_valid_receipt_path( $order->get_id() );
		$reference    = (string) $order->get_meta( '_instapay_transaction_reference' );
		$uploaded_at  = (int) $order->get_meta( '_instapay_uploaded_at' );

		echo '<div class="instapay-admin-box">';
		echo '<p class="instapay-admin-status">';
		if ( 'payment-review' === $status ) {
			echo '<span class="instapay-pill is-review">' . esc_html__( 'Needs review', 'instapay-gateway-for-egypt' ) . '</span>';
		} elseif ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			echo '<span class="instapay-pill is-paid">' . esc_html__( 'Paid', 'instapay-gateway-for-egypt' ) . '</span>';
		} elseif ( in_array( $status, instapay_woo_awaiting_payment_statuses(), true ) ) {
			echo '<span class="instapay-pill is-waiting">' . esc_html__( 'Waiting for receipt', 'instapay-gateway-for-egypt' ) . '</span>';
		}
		echo '</p>';

		echo '<dl class="instapay-admin-facts">';
		echo '<dt>' . esc_html__( 'Amount to check', 'instapay-gateway-for-egypt' ) . '</dt><dd><strong>' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong></dd>';
		if ( '' !== $reference ) {
			echo '<dt>' . esc_html__( 'Transaction reference:', 'instapay-gateway-for-egypt' ) . '</dt><dd><code>' . esc_html( $reference ) . '</code></dd>';
		}
		if ( $uploaded_at && $receipt_path ) {
			/* translators: %s: human-readable time, e.g. "5 mins" */
			echo '<dt>' . esc_html__( 'Uploaded', 'instapay-gateway-for-egypt' ) . '</dt><dd>' . esc_html( sprintf( __( '%s ago', 'instapay-gateway-for-egypt' ), human_time_diff( $uploaded_at ) ) ) . '</dd>';
		}
		echo '</dl>';

		if ( $receipt_path ) {
			add_thickbox();
			$view_url = add_query_arg( array( 'TB_iframe' => 'true', 'width' => 600, 'height' => 800 ), instapay_woo_get_receipt_view_url( $order, true ) );

			$related = instapay_woo_find_related_orders( $order );
			if ( $related['hash'] ) {
				/* translators: %s: comma-separated order numbers */
				echo '<p class="instapay-duplicate-warning" role="alert">' . esc_html( sprintf( __( 'Warning: the same receipt image was uploaded on order(s) %s.', 'instapay-gateway-for-egypt' ), instapay_woo_order_numbers( $related['hash'] ) ) ) . '</p>';
			}
			if ( $related['reference'] ) {
				/* translators: %s: comma-separated order numbers */
				echo '<p class="instapay-duplicate-warning" role="alert">' . esc_html( sprintf( __( 'Warning: the same transaction reference was used on order(s) %s.', 'instapay-gateway-for-egypt' ), instapay_woo_order_numbers( $related['reference'] ) ) ) . '</p>';
			}

			echo '<a href="' . esc_url( $view_url ) . '" class="thickbox instapay-admin-receipt-link" title="' . esc_attr__( 'View Receipt Screenshot', 'instapay-gateway-for-egypt' ) . '"><img class="instapay-admin-receipt" src="' . esc_url( instapay_woo_get_receipt_view_url( $order, true ) ) . '" alt="' . esc_attr__( 'Receipt thumbnail', 'instapay-gateway-for-egypt' ) . '" /><span class="instapay-zoom-hint"><span class="dashicons dashicons-search" aria-hidden="true"></span> ' . esc_html__( 'View Receipt Screenshot', 'instapay-gateway-for-egypt' ) . '</span></a>';
			if ( 'payment-review' === $status ) {
				echo '<p class="instapay-review-note">' . esc_html__( 'Check the amount and date in the receipt against this order, then accept or reject it below.', 'instapay-gateway-for-egypt' ) . '</p>';
			}
		} else {
			echo '<p class="instapay-no-receipt">' . esc_html__( 'No receipt has been uploaded yet.', 'instapay-gateway-for-egypt' ) . '</p>';
		}
		?>
		<div class="instapay-admin-actions" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-receipt="<?php echo esc_attr( (string) $order->get_meta( '_instapay_receipt_filename' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'instapay_quick_action' ) ); ?>">
			<?php if ( 'payment-review' === $status ) : ?>
				<button type="button" class="button button-primary button-large instapay-quick-action instapay-accept-action" data-status="processing" data-confirm="<?php esc_attr_e( 'Accept this payment and mark the order as paid?', 'instapay-gateway-for-egypt' ); ?>"><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Accept Payment', 'instapay-gateway-for-egypt' ); ?></button>
				<details class="instapay-reject-panel">
					<summary class="button instapay-reject-toggle"><?php esc_html_e( 'Reject Payment', 'instapay-gateway-for-egypt' ); ?></summary>
					<label for="instapay_rejection_reason"><?php esc_html_e( 'Reason shown to the customer (optional)', 'instapay-gateway-for-egypt' ); ?></label>
					<textarea id="instapay_rejection_reason" rows="3" placeholder="<?php esc_attr_e( 'For example: the amount does not match the order total.', 'instapay-gateway-for-egypt' ); ?>"></textarea>
					<button type="button" class="button instapay-quick-action instapay-reject-action" data-status="on-hold" data-confirm="<?php esc_attr_e( 'Reject this receipt and require a new upload?', 'instapay-gateway-for-egypt' ); ?>"><?php esc_html_e( 'Reject and ask for a new receipt', 'instapay-gateway-for-egypt' ); ?></button>
				</details>
			<?php endif; ?>
			<?php if ( in_array( $status, array_merge( instapay_woo_awaiting_payment_statuses(), array( 'payment-review' ) ), true ) ) : ?>
				<button type="button" class="button-link button-link-delete instapay-quick-action instapay-cancel-action" data-status="cancelled" data-confirm="<?php esc_attr_e( 'Cancel this order completely?', 'instapay-gateway-for-egypt' ); ?>"><?php esc_html_e( 'Cancel Order', 'instapay-gateway-for-egypt' ); ?></button>
			<?php endif; ?>
		</div>
		</div>
		<?php
	}
}
