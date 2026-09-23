( function ( $ ) {
	'use strict';

	function setUploadStatus( $status, message, state ) {
		$status.removeClass( 'is-error is-success' ).addClass( state ? 'is-' + state : '' ).text( message );
	}

	$( function () {
		$( '.instapay-receipt-form' ).each( function () {
			var $form = $( this );
			var $dropzone = $form.find( '.instapay-dropzone' );
			var $input = $form.find( '.instapay-receipt-input' );
			var $fileName = $form.find( '.instapay-file-name' );
			var $button = $form.find( '.instapay-submit-btn' );
			var $status = $form.find( '.instapay-upload-status' );
			var originalButtonText = $button.text();

			$input.on( 'change', function () {
				var file = this.files && this.files[ 0 ];
				$dropzone.toggleClass( 'has-file', Boolean( file ) );
				$fileName.toggleClass( 'is-visible', Boolean( file ) ).text( file ? file.name : '' );
			} );

			$dropzone.on( 'dragover', function ( event ) {
				event.preventDefault();
				$dropzone.addClass( 'is-dragover' );
			} ).on( 'dragleave drop', function ( event ) {
				event.preventDefault();
				$dropzone.removeClass( 'is-dragover' );
			} );

			$form.on( 'submit', function ( event ) {
				event.preventDefault();
				var file = $input[ 0 ].files && $input[ 0 ].files[ 0 ];

				if ( ! file ) {
					setUploadStatus( $status, $form.data( 'select-message' ), 'error' );
					return;
				}
				if ( file.size > 5 * 1024 * 1024 ) {
					setUploadStatus( $status, $form.data( 'size-message' ), 'error' );
					return;
				}

				$button.prop( 'disabled', true ).text( $form.data( 'uploading-message' ) );
				setUploadStatus( $status, '', '' );

				$.ajax( {
					url: $form.data( 'ajax-url' ),
					type: 'POST',
					data: new FormData( this ),
					processData: false,
					contentType: false
				} ).done( function ( response ) {
					if ( response.success ) {
						setUploadStatus( $status, response.data, 'success' );
						window.setTimeout( function () { window.location.reload(); }, 1200 );
						return;
					}
					setUploadStatus( $status, response.data || $form.data( 'error-message' ), 'error' );
					$button.prop( 'disabled', false ).text( originalButtonText );
				} ).fail( function () {
					setUploadStatus( $status, $form.data( 'error-message' ), 'error' );
					$button.prop( 'disabled', false ).text( originalButtonText );
				} );
			} );
		} );

		$( '.instapay-admin-actions' ).on( 'click', '.instapay-quick-action', function ( event ) {
			event.preventDefault();
			var $button = $( this );
			var $container = $button.closest( '.instapay-admin-actions' );
			var originalText = $button.text();

			if ( ! window.confirm( $button.data( 'confirm' ) ) ) {
				return;
			}

			$container.find( '.instapay-quick-action' ).prop( 'disabled', true );
			$button.text( instapayWooAdmin.processing );
			$.post( instapayWooAdmin.ajaxUrl, {
				action: 'instapay_quick_action',
				order_id: $container.data( 'order-id' ),
				new_status: $button.data( 'status' ),
				rejection_reason: $container.find( '#instapay_rejection_reason' ).val() || '',
				nonce: $container.data( 'nonce' )
			} ).done( function ( response ) {
				if ( response.success ) {
					window.location.reload();
					return;
				}
				window.alert( response.data || instapayWooAdmin.errorText );
				$container.find( '.instapay-quick-action' ).prop( 'disabled', false );
				$button.text( originalText );
			} ).fail( function () {
				window.alert( instapayWooAdmin.errorText );
				$container.find( '.instapay-quick-action' ).prop( 'disabled', false );
				$button.text( originalText );
			} );
		} );
	} );
}( jQuery ) );
