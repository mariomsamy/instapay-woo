( function () {
	'use strict';

	var registry = window.wc && window.wc.wcBlocksRegistry;
	var wcSettings = window.wc && window.wc.wcSettings;
	if ( ! registry || ! wcSettings || ! window.wp || ! window.wp.element ) {
		return;
	}

	var el = window.wp.element.createElement;
	var decode = window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities : function ( text ) { return text; };
	// Newer WooCommerce exposes gateway data under paymentMethodData; older versions under "<name>_data".
	var settings = ( wcSettings.getPaymentMethodData && wcSettings.getPaymentMethodData( 'instapay' ) ) ||
		( wcSettings.getSetting( 'paymentMethodData', {} ) || {} ).instapay ||
		wcSettings.getSetting( 'instapay_data', {} );
	var title = decode( settings.title || 'Instapay' );

	function Label() {
		return el(
			'span',
			{ className: 'instapay-blocks-label' },
			settings.icon ? el( 'img', { src: settings.icon, alt: '', className: 'instapay-blocks-icon' } ) : null,
			title
		);
	}

	function Content() {
		return el( 'div', { className: 'instapay-blocks-description' }, decode( settings.description || '' ) );
	}

	registry.registerPaymentMethod( {
		name: 'instapay',
		label: el( Label ),
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: title,
		supports: {
			features: settings.supports || [ 'products' ]
		}
	} );
}() );
