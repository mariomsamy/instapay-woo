( function ( $ ) {
	'use strict';

	var ALLOWED_TYPES = [ 'image/jpeg', 'image/png', 'image/webp' ];
	var MAX_BYTES = 5 * 1024 * 1024;

	function setUploadStatus( $status, message, state ) {
		$status.removeClass( 'is-error is-success' ).addClass( state ? 'is-' + state : '' ).text( message );
	}

	function copyText( text ) {
		if ( window.navigator.clipboard && window.isSecureContext ) {
			return window.navigator.clipboard.writeText( text ).catch( function () {
				return legacyCopy( text );
			} );
		}
		return legacyCopy( text );
	}

	// Fallback for http:// sites, older browsers and blocked clipboard permissions.
	function legacyCopy( text ) {
		return new Promise( function ( resolve, reject ) {
			var $temp = $( '<textarea readonly>' ).val( text ).css( { position: 'fixed', top: 0, opacity: 0 } ).appendTo( 'body' );
			$temp[ 0 ].select();
			var ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch ( e ) {
				ok = false;
			}
			$temp.remove();
			( ok ? resolve : reject )();
		} );
	}

	$( function () {
		$( document ).on( 'click', '.instapay-copy', function () {
			var $button = $( this );
			var $panel = $button.closest( '.instapay-panel' );
			var $label = $button.find( '.instapay-copy-text' );
			var original = $label.data( 'original' ) || $label.text();
			$label.data( 'original', original );

			copyText( String( $button.data( 'copy' ) ) ).then( function () {
				$button.addClass( 'is-copied' );
				$label.text( $panel.data( 'copied-text' ) || original );
			}, function () {
				$label.text( $panel.data( 'copy-failed-text' ) || original );
			} ).then( function () {
				window.setTimeout( function () {
					$button.removeClass( 'is-copied' );
					$label.text( original );
				}, 2000 );
			} );
		} );

		$( '.instapay-receipt-form' ).each( function () {
			var $form = $( this );
			var $dropzone = $form.find( '.instapay-dropzone' );
			var $input = $form.find( '.instapay-receipt-input' );
			var $empty = $form.find( '.instapay-dropzone-empty' );
			var $preview = $form.find( '.instapay-preview' );
			var $previewImage = $form.find( '.instapay-preview-image' );
			var $fileName = $form.find( '.instapay-file-name' );
			var $button = $form.find( '.instapay-submit-btn' );
			var $status = $form.find( '.instapay-upload-status' );
			var $progress = $form.find( '.instapay-progress' );
			var $progressBar = $form.find( '.instapay-progress-bar' );
			var originalButtonText = $button.text();
			var previewUrl = null;

			function validate( file ) {
				if ( ! file ) {
					return $form.data( 'select-message' );
				}
				if ( file.type && ALLOWED_TYPES.indexOf( file.type ) === -1 ) {
					return $form.data( 'type-message' );
				}
				if ( file.size > MAX_BYTES ) {
					return $form.data( 'size-message' );
				}
				return '';
			}

			function showFile( file ) {
				if ( previewUrl ) {
					window.URL.revokeObjectURL( previewUrl );
					previewUrl = null;
				}
				$dropzone.toggleClass( 'has-file', Boolean( file ) );
				$empty.prop( 'hidden', Boolean( file ) );
				$preview.prop( 'hidden', ! file );
				$fileName.text( file ? file.name : '' );
				if ( file && window.URL && window.URL.createObjectURL && ALLOWED_TYPES.indexOf( file.type ) !== -1 ) {
					previewUrl = window.URL.createObjectURL( file );
					$previewImage.attr( 'src', previewUrl ).prop( 'hidden', false );
				} else {
					$previewImage.removeAttr( 'src' ).prop( 'hidden', true );
				}
			}

			$input.on( 'change', function () {
				var file = this.files && this.files[ 0 ];
				var error = file ? validate( file ) : '';
				setUploadStatus( $status, error, error ? 'error' : '' );
				showFile( file );
			} );

			$dropzone.on( 'dragover', function ( event ) {
				event.preventDefault();
				$dropzone.addClass( 'is-dragover' );
			} ).on( 'dragleave', function () {
				$dropzone.removeClass( 'is-dragover' );
			} ).on( 'drop', function ( event ) {
				// Cancelling the drop stops the browser from opening the image, so hand the
				// dropped file to the input ourselves.
				event.preventDefault();
				$dropzone.removeClass( 'is-dragover' );
				var transfer = event.originalEvent && event.originalEvent.dataTransfer;
				if ( transfer && transfer.files && transfer.files.length ) {
					$input[ 0 ].files = transfer.files;
					$input.trigger( 'change' );
				}
			} );

			$form.on( 'submit', function ( event ) {
				event.preventDefault();
				var file = $input[ 0 ].files && $input[ 0 ].files[ 0 ];
				var error = validate( file );

				if ( error ) {
					setUploadStatus( $status, error, 'error' );
					$input.trigger( 'focus' );
					return;
				}

				$button.prop( 'disabled', true ).text( $form.data( 'uploading-message' ) );
				$form.attr( 'aria-busy', 'true' );
				setUploadStatus( $status, '', '' );
				$progress.prop( 'hidden', false ).attr( 'aria-valuenow', 0 );
				$progressBar.css( 'width', '0%' );

				$.ajax( {
					url: $form.data( 'ajax-url' ),
					type: 'POST',
					data: new FormData( this ),
					processData: false,
					contentType: false,
					xhr: function () {
						var xhr = $.ajaxSettings.xhr();
						if ( xhr.upload ) {
							xhr.upload.addEventListener( 'progress', function ( e ) {
								if ( e.lengthComputable ) {
									var percent = Math.round( ( e.loaded / e.total ) * 100 );
									$progressBar.css( 'width', percent + '%' );
									$progress.attr( 'aria-valuenow', percent );
								}
							} );
						}
						return xhr;
					}
				} ).done( function ( response ) {
					if ( response.success ) {
						setUploadStatus( $status, response.data, 'success' );
						window.setTimeout( function () { window.location.reload(); }, 1200 );
						return;
					}
					fail( response.data );
				} ).fail( function () {
					fail();
				} );

				function fail( message ) {
					setUploadStatus( $status, message || $form.data( 'error-message' ), 'error' );
					$button.prop( 'disabled', false ).text( originalButtonText );
					$form.removeAttr( 'aria-busy' );
					$progress.prop( 'hidden', true );
				}
			} );
		} );

		$( '.instapay-admin-actions' ).on( 'click', '.instapay-quick-action', function ( event ) {
			event.preventDefault();
			var $button = $( this );
			var $container = $button.closest( '.instapay-admin-actions' );
			var originalText = $button.html();

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
				receipt: $container.data( 'receipt' ) || '',
				nonce: $container.data( 'nonce' )
			} ).done( function ( response ) {
				if ( response.success ) {
					window.location.reload();
					return;
				}
				window.alert( response.data || instapayWooAdmin.errorText );
				$container.find( '.instapay-quick-action' ).prop( 'disabled', false );
				$button.html( originalText );
			} ).fail( function () {
				window.alert( instapayWooAdmin.errorText );
				$container.find( '.instapay-quick-action' ).prop( 'disabled', false );
				$button.html( originalText );
			} );
		} );
	} );
}( jQuery ) );
