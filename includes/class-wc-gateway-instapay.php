<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_Instapay extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'instapay';
        $this->icon               = apply_filters( 'woocommerce_instapay_icon', INSTAPAY_WOO_PLUGIN_URL . 'img/InstaPay.webp' );
        $this->has_fields         = false;
        $this->method_title       = __( 'Instapay (Egypt)', 'instapay-woo' );
        $this->method_description = __( 'Accept payments via Instapay. Customers will upload a screenshot of their transaction after checkout.', 'instapay-woo' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->instapay_ipa   = $this->get_option( 'instapay_ipa' );
        $this->instapay_phone = $this->get_option( 'instapay_phone' );
        $this->instapay_payment_url = $this->get_option( 'instapay_payment_url' );
        $this->qr_code_url    = $this->get_option( 'qr_code_url' );
        
        // Advanced Features
        $this->enable_reject_email = $this->get_option( 'enable_reject_email' );
        $this->enable_compression  = $this->get_option( 'enable_compression' );
        $this->enable_cleanup      = $this->get_option( 'enable_cleanup' );
        $this->enable_logging      = $this->get_option( 'enable_logging' );

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_action( 'woocommerce_view_order', array( $this, 'thankyou_page' ) ); // Show in My Account -> View Order
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'thankyou_page' ) ); // Quick payment link hook
        
        // Handle upload
        add_action( 'wp_ajax_instapay_upload_receipt', array( $this, 'handle_receipt_upload' ) );
        add_action( 'wp_ajax_nopriv_instapay_upload_receipt', array( $this, 'handle_receipt_upload' ) );
        
        // Admin UI - Meta Box
        add_action( 'add_meta_boxes', array( $this, 'register_instapay_meta_box' ), 10, 2 );
    }

    public function register_instapay_meta_box() {
        $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

        add_meta_box(
            'instapay_verification_box',
            __( 'Instapay Verification', 'instapay-woo' ),
            array( $this, 'admin_order_receipt_display' ),
            $screen,
            'side',
            'high'
        );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'instapay-woo' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Instapay Payment', 'instapay-woo' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __( 'Title', 'instapay-woo' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'instapay-woo' ),
                'default'     => __( 'Instapay', 'instapay-woo' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'instapay-woo' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'instapay-woo' ),
                'default'     => __( 'Please transfer the total amount to our Instapay account and upload the receipt screenshot.', 'instapay-woo' ),
            ),
            'instapay_ipa' => array(
                'title'       => __( 'Instapay Payment Address (IPA)', 'instapay-woo' ),
                'type'        => 'text',
                'description' => __( 'Your Instapay address (e.g. username@instapay).', 'instapay-woo' ),
            ),
            'instapay_phone' => array(
                'title'       => __( 'Instapay Phone Number', 'instapay-woo' ),
                'type'        => 'text',
                'description' => __( 'Your mobile number registered with Instapay.', 'instapay-woo' ),
            ),
            'instapay_payment_url' => array(
                'title'       => __( 'Instapay Payment URL', 'instapay-woo' ),
                'type'        => 'text',
                'description' => __( 'Payment URL for Instapay quick action (Shown on mobile devices only).', 'instapay-woo' ),
            ),
            'qr_code_url' => array(
                'title'       => __( 'QR Code Image URL', 'instapay-woo' ),
                'type'        => 'text',
                'description' => __( 'URL to your Instapay QR code image (upload via media library and paste link).', 'instapay-woo' ),
            ),
            'enable_reject_email' => array(
                'title'   => __( 'Rejection Emails', 'instapay-woo' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable automated emails when a receipt is rejected', 'instapay-woo' ),
                'default' => 'yes'
            ),
            'enable_compression' => array(
                'title'   => __( 'Auto Image Compression', 'instapay-woo' ),
                'type'    => 'checkbox',
                'label'   => __( 'Automatically compress and resize uploaded receipts', 'instapay-woo' ),
                'default' => 'yes'
            ),
            'enable_cleanup' => array(
                'title'   => __( 'Storage Cleanup', 'instapay-woo' ),
                'type'    => 'checkbox',
                'label'   => __( 'Automatically delete receipts for cancelled/failed orders older than 30 days', 'instapay-woo' ),
                'default' => 'no'
            ),
            'enable_logging' => array(
                'title'   => __( 'Audit Logging', 'instapay-woo' ),
                'type'    => 'checkbox',
                'label'   => __( 'Add detailed notes to the order when admins accept/reject payments', 'instapay-woo' ),
                'default' => 'yes'
            ),
        );
    }

    public function is_available() {
        if ( ! parent::is_available() ) {
            return false;
        }

        if ( function_exists( 'get_woocommerce_currency' ) && get_woocommerce_currency() !== 'EGP' ) {
            return false;
        }

        return true;
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        
        // Mark as pending payment
        $order->update_status( 'pending', __( 'Awaiting Instapay payment receipt.', 'instapay-woo' ) );
        
        // Reduce stock levels
        wc_reduce_stock_levels( $order_id );
        
        // Remove cart
        WC()->cart->empty_cart();
        
        // Return thankyou redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }

    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order || $order->get_payment_method() !== $this->id ) {
            return;
        }

        $receipt_uploaded = get_post_meta( $order_id, '_instapay_receipt_path', true );
        
        ?>
        <style>
            .instapay-payment-card { margin-bottom: 2em; padding: 25px; background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
            .instapay-payment-title { color: #6b21a8; font-size: 24px; margin-top: 0; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
            .instapay-payment-desc { font-size: 16px; color: #4b5563; margin-bottom: 20px; line-height: 1.5; }
            .instapay-flex-row { display: flex; flex-wrap: wrap; gap: 20px; align-items: center; }
            .instapay-qr-box { flex: 0 0 auto; background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; }
            .instapay-ipa-box { flex: 1 1 250px; }
            .instapay-ipa-label { margin: 0 0 8px 0; color: #6b7280; font-size: 14px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
            .instapay-ipa-value { margin: 0; font-size: 22px; font-weight: 700; color: #4c1d95; background: #f3e8ff; display: inline-block; padding: 12px 20px; border-radius: 8px; border: 1px dashed #d8b4fe; word-break: break-all; width: 100%; box-sizing: border-box; }
            .instapay-upload-container { margin-top: 30px; padding: 30px; background: #ffffff; border: 2px dashed #9333ea; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box; }
            .instapay-upload-title { margin-top: 0; color: #4c1d95; font-size: 22px; font-weight: 700; }
            .instapay-upload-desc { color: #6b7280; font-size: 15px; margin-bottom: 25px; line-height: 1.5; }
            .instapay-file-wrapper { position: relative; display: inline-block; overflow: hidden; background-color: #f3f4f6; border-radius: 8px; padding: 15px; width: 100%; max-width: 400px; text-align: left; border: 1px solid #e5e7eb; box-sizing: border-box; }
            .instapay-submit-btn { background-color: #9333ea; color: #fff; padding: 12px 30px; font-size: 16px; border-radius: 6px; font-weight: bold; border: none; cursor: pointer; transition: background-color 0.3s ease; width: 100%; max-width: 300px; }
            .instapay-submit-btn:hover { background-color: #7e22ce; }
            
            @media (max-width: 600px) {
                .instapay-payment-card { padding: 15px; }
                .instapay-payment-title { font-size: 20px; }
                .instapay-qr-box { margin: 0 auto; width: 100%; box-sizing: border-box; }
                .instapay-ipa-box { text-align: center; }
                .instapay-ipa-value { font-size: 18px; padding: 10px 15px; }
                .instapay-upload-container { padding: 20px 15px; }
                .instapay-upload-title { font-size: 18px; }
                .instapay-file-wrapper { max-width: 100%; }
                .instapay-submit-btn { max-width: 100%; }
            }
        </style>
        <?php
        echo '<section class="instapay-payment-card">';
        echo '<h2 class="instapay-payment-title">';
        echo '<img src="' . esc_url( INSTAPAY_WOO_PLUGIN_URL . 'img/InstaPay.webp' ) . '" style="height: 30px; width: auto;" alt="Instapay" />';
        echo __( 'Payment Instructions', 'instapay-woo' );
        echo '</h2>';
        echo '<p class="instapay-payment-desc">' . esc_html( $this->description ) . '</p>';
        
        echo '<div class="instapay-flex-row">';
        
        if ( ! empty( $this->qr_code_url ) ) {
            echo '<div class="instapay-qr-box">';
            echo '<img src="' . esc_url( $this->qr_code_url ) . '" alt="Instapay QR Code" style="max-width:180px; height:auto; display: block; margin: 0 auto;" />';
            echo '</div>';
        }
        
        if ( ! empty( $this->instapay_ipa ) || ! empty( $this->instapay_phone ) ) {
            echo '<div class="instapay-ipa-box">';
            echo '<p class="instapay-ipa-label">' . __( 'Send Payment To', 'instapay-woo' ) . '</p>';
            if ( ! empty( $this->instapay_ipa ) ) {
                echo '<p class="instapay-ipa-value" style="margin-bottom: 5px;">' . esc_html( $this->instapay_ipa ) . '</p>';
            }
            if ( ! empty( $this->instapay_phone ) ) {
                echo '<p class="instapay-ipa-value">' . esc_html( $this->instapay_phone ) . '</p>';
            }
            if ( wp_is_mobile() && ! empty( $this->instapay_payment_url ) ) {
                echo '<a href="' . esc_url( $this->instapay_payment_url, array( 'http', 'https', 'instapay' ) ) . '" class="instapay-submit-btn" style="display:inline-block; margin-top:15px; text-decoration:none; text-align:center;">' . __( 'Quick Pay via Instapay App', 'instapay-woo' ) . '</a>';
            }
            echo '</div>';
        }
        
        echo '</div>';
        echo '</section>';

        if ( $receipt_uploaded ) {
            $view_url = add_query_arg( array(
                'instapay_view_receipt' => 1,
                'order_id'              => $order->get_id()
            ), site_url() );

            if ( in_array( $order->get_status(), array( 'payment-review', 'processing', 'completed' ) ) ) {
                echo '<div class="woocommerce-message" role="alert" style="background-color: #e5f9e7; color: #1e4620; border-top-color: #8fae1b; margin-bottom: 20px;">' . __( 'Receipt uploaded successfully. Your payment is under review.', 'instapay-woo' ) . '</div>';
                echo '<h3>' . __( 'Your Uploaded Receipt', 'instapay-woo' ) . '</h3>';
                echo '<p><a href="' . esc_url( $view_url ) . '" target="_blank"><img src="' . esc_url( $view_url ) . '" style="max-width:100%; height:auto; border:1px solid #ccc; border-radius:4px; box-shadow: 0 1px 5px rgba(0,0,0,0.1);" alt="Receipt Thumbnail" /></a></p>';
                return;
            } else {
                echo '<div class="woocommerce-error" role="alert" style="background-color: #ffeaea; color: #c00000; border-top-color: #c00000; margin-bottom: 20px; padding: 1em; border-radius: 8px;">' . __( 'Your previous receipt was rejected. Please securely upload a new valid screenshot.', 'instapay-woo' ) . '</div>';
            }
        }

        // Upload form
        ?>
        <style>
            .instapay-dropzone { border: 2px dashed #9333ea; border-radius: 12px; padding: 40px 20px; text-align: center; background: #faf5ff; transition: all 0.3s ease; cursor: pointer; position: relative; margin-bottom: 20px; }
            .instapay-dropzone:hover, .instapay-dropzone.dragover { background: #f3e8ff; border-color: #7e22ce; }
            .instapay-dropzone input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
            .instapay-dropzone-icon { font-size: 40px; color: #9333ea; margin-bottom: 15px; }
            .instapay-dropzone-text { font-size: 18px; font-weight: 600; color: #4c1d95; margin: 0 0 5px 0; }
            .instapay-dropzone-subtext { font-size: 14px; color: #6b7280; margin: 0; }
            .instapay-file-name { margin-top: 15px; font-weight: bold; color: #15803d; display: none; background: #dcfce7; padding: 10px; border-radius: 6px; }
        </style>
        
        <div id="instapay-upload-container" class="instapay-upload-container">
            <div style="margin-bottom: 20px;">
                <img src="<?php echo esc_url( INSTAPAY_WOO_PLUGIN_URL . 'img/InstaPay.webp' ); ?>" alt="Instapay Logo" style="height: 50px; width: auto; margin: 0 auto; display: block;" />
            </div>
            <h3 class="instapay-upload-title"><?php _e( 'Upload Payment Receipt', 'instapay-woo' ); ?></h3>
            <p class="instapay-upload-desc"><?php _e( 'Please securely upload a screenshot of your successful transaction.', 'instapay-woo' ); ?></p>
            
            <form id="instapay-receipt-form" enctype="multipart/form-data">
                <div class="instapay-dropzone" id="instapay-dropzone">
                    <div class="instapay-dropzone-icon">📁</div>
                    <p class="instapay-dropzone-text"><?php _e( 'Click or Drag image here', 'instapay-woo' ); ?></p>
                    <p class="instapay-dropzone-subtext"><?php _e( 'Supports: JPG, PNG, WebP (Max 5MB)', 'instapay-woo' ); ?></p>
                    <input type="file" name="instapay_receipt" id="instapay_receipt" accept="image/jpeg,image/png,image/webp" required />
                    <div id="instapay-file-name" class="instapay-file-name"></div>
                </div>
                
                <input type="hidden" name="action" value="instapay_upload_receipt" />
                <input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>" />
                <input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>" />
                <?php wp_nonce_field( 'instapay_upload_nonce_' . $order_id, 'instapay_nonce' ); ?>
                
                <div>
                    <button type="submit" class="instapay-submit-btn" id="instapay_submit_btn"><?php _e( 'Securely Upload Receipt', 'instapay-woo' ); ?></button>
                </div>
                
                <div id="instapay-upload-status" style="margin-top:15px; font-weight:600; font-size: 15px;"></div>
            </form>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var dropzone = $('#instapay-dropzone');
                var fileInput = $('#instapay_receipt');
                var fileNameDisplay = $('#instapay-file-name');

                fileInput.on('change', function() {
                    if (this.files && this.files.length > 0) {
                        fileNameDisplay.text('Selected: ' + this.files[0].name).show();
                        dropzone.css('border-color', '#15803d').css('background', '#f0fdf4');
                    } else {
                        fileNameDisplay.hide();
                        dropzone.css('border-color', '#9333ea').css('background', '#faf5ff');
                    }
                });

                dropzone.on('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('dragover');
                });
                dropzone.on('dragleave drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('dragover');
                });

                $('#instapay-receipt-form').on('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    var $btn = $('#instapay_submit_btn');
                    var $status = $('#instapay-upload-status');
                    var fileEl = fileInput[0];
                    
                    if(fileEl.files.length === 0) {
                        $status.html('<span style="color:red;"><?php _e( 'Please select an image file first.', 'instapay-woo' ); ?></span>');
                        return;
                    }
                    
                    var fileSize = fileEl.files[0].size / 1024 / 1024; // in MB
                    if (fileSize > 5) {
                        $status.html('<span style="color:red;"><?php _e( 'File size exceeds 5MB. Please upload a smaller image.', 'instapay-woo' ); ?></span>');
                        return;
                    }

                    $btn.prop('disabled', true).text('<?php _e( 'Uploading...', 'instapay-woo' ); ?>');
                    $status.html('');

                    $.ajax({
                        url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if(response.success) {
                                $status.html('<span style="color:green;">' + response.data + '</span>');
                                setTimeout(function() {
                                    location.reload(); 
                                }, 1500);
                            } else {
                                $status.html('<span style="color:red;">' + response.data + '</span>');
                                $btn.prop('disabled', false).text('<?php _e( 'Upload Receipt', 'instapay-woo' ); ?>');
                            }
                        },
                        error: function(xhr, status, error) {
                            var errMsg = 'An error occurred during upload. Please try again.';
                            if (xhr.responseText && xhr.responseText.indexOf('{"success":false') !== -1) {
                                try {
                                    var resp = JSON.parse(xhr.responseText);
                                    if(resp.data) errMsg = resp.data;
                                } catch(e) {}
                            } else {
                                errMsg += ' (' + xhr.status + ' ' + error + ')';
                            }
                            $status.html('<span style="color:red;">' + errMsg + '</span>');
                            $btn.prop('disabled', false).text('<?php _e( 'Upload Receipt', 'instapay-woo' ); ?>');
                        }
                    });
                });
            });
        </script>
        <?php
        echo '</section>';
    }



    public function admin_order_receipt_display( $post_or_order_object ) {
        $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
        
        if ( ! $order || $order->get_payment_method() !== $this->id ) {
            echo '<p>' . __( 'This order was not paid via Instapay.', 'instapay-woo' ) . '</p>';
            return;
        }

        $receipt_path = get_post_meta( $order->get_id(), '_instapay_receipt_path', true );

        if ( $receipt_path && file_exists( $receipt_path ) ) {
            add_thickbox();
            $view_url = add_query_arg( array(
                'instapay_view_receipt' => 1,
                'order_id'              => $order->get_id(),
                'TB_iframe'             => 'true',
                'width'                 => 600,
                'height'                => 800
            ), admin_url() );

            echo '<p>' . __( 'Customer uploaded a receipt screenshot.', 'instapay-woo' ) . '</p>';
            echo '<p><a href="' . esc_url( $view_url ) . '" class="button button-primary thickbox">' . __( 'View Receipt Screenshot', 'instapay-woo' ) . '</a></p>';
            
            // Display thumbnail inline
            $thumb_url = add_query_arg( array(
                'instapay_view_receipt' => 1,
                'order_id'              => $order->get_id()
            ), admin_url() );
            echo '<p><a href="' . esc_url( $view_url ) . '" class="thickbox"><img src="' . esc_url( $thumb_url ) . '" style="max-width:100%; height:auto; border:1px solid #ccc; border-radius:4px; margin-top:10px; display:block;" alt="Receipt Thumbnail" /></a></p>';

            // Approval Actions Note
            if ( $order->get_status() === 'payment-review' ) {
                echo '<hr style="margin: 15px 0;" />';
                echo '<p style="color: #d63638; font-weight: 600;">' . __( 'Status is Payment Review. If the receipt is valid, change order status to Processing.', 'instapay-woo' ) . '</p>';
            }
        } else {
            echo '<p style="color:red; font-weight: bold;">' . __( 'No receipt uploaded yet. The customer needs to upload their screenshot.', 'instapay-woo' ) . '</p>';
        }

        // Quick Actions
        ?>
        <hr style="margin: 15px 0;" />
        <p><strong><?php _e( 'Quick Actions:', 'instapay-woo' ); ?></strong></p>
        
        <?php if ( $order->get_status() === 'payment-review' ) : ?>
            <p style="margin-bottom: 5px;"><strong><?php _e( 'Rejection Reason (Optional):', 'instapay-woo' ); ?></strong></p>
            <textarea id="instapay_rejection_reason" style="width: 100%; margin-bottom: 10px;" rows="2" placeholder="<?php esc_attr_e( 'Enter reason for rejection...', 'instapay-woo' ); ?>"></textarea>
        <?php endif; ?>

        <p>
            <button type="button" class="button button-primary instapay-quick-action" data-status="processing" data-confirm="<?php esc_attr_e( 'Are you sure you want to ACCEPT this payment and mark the order as Processing?', 'instapay-woo' ); ?>" style="margin-bottom: 5px; width: 100%; text-align: center;"><?php _e( 'Accept Payment', 'instapay-woo' ); ?></button>
            <button type="button" class="button instapay-quick-action" data-status="pending" data-confirm="<?php esc_attr_e( 'Are you sure you want to REJECT this payment and require the customer to pay/upload again?', 'instapay-woo' ); ?>" style="margin-bottom: 5px; width: 100%; text-align: center; color: #d63638; border-color: #d63638;"><?php _e( 'Reject Payment', 'instapay-woo' ); ?></button>
            <button type="button" class="button instapay-quick-action" data-status="cancelled" data-confirm="<?php esc_attr_e( 'Are you sure you want to CANCEL this order completely?', 'instapay-woo' ); ?>" style="margin-bottom: 5px; width: 100%; text-align: center;"><?php _e( 'Cancel Order', 'instapay-woo' ); ?></button>
        </p>

        <script>
        jQuery(document).ready(function($) {
            $('.instapay-quick-action').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var status = btn.data('status');
                var msg = btn.data('confirm');
                var reason = $('#instapay_rejection_reason').length ? $('#instapay_rejection_reason').val() : '';
                
                if ( confirm( msg ) ) {
                    btn.prop('disabled', true).text('<?php esc_attr_e( 'Processing...', 'instapay-woo' ); ?>');
                    $.post( ajaxurl, {
                        action: 'instapay_quick_action',
                        order_id: <?php echo intval( $order->get_id() ); ?>,
                        new_status: status,
                        rejection_reason: reason,
                        nonce: '<?php echo wp_create_nonce( "instapay_quick_action" ); ?>'
                    }, function(response) {
                        if(response.success) {
                            location.reload();
                        } else {
                            alert( response.data || 'Error' );
                            btn.prop('disabled', false);
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
}
