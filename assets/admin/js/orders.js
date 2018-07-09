( function( $, options, woocommerce_admin ){
	
	var woocommerce_admin = woocommerce_admin || {};
	
	woocommerce_admin.empty_value = 'Это поле обязательно для заполнения';
	
	$( document.body ).on( 'click', '.shiptor-order-details button[type=submit]', function( event ){
		event.preventDefault();
		var $inputs = $( '.shiptor-order-details :input[name]' ),
			$has_error = false;
		
		$.each( $inputs, function( i, input ){
			if( $( input ).is('[required]') && '' == $( input ).val() ) {
				$( document.body ).triggerHandler( 'wc_add_error_tip', [ $( input ), 'empty_value' ] );
				$has_error = true;
			}
		});
		
		if( $has_error ) {
			return false;
		}
		
		$.ajax({
			url: wp.ajax.settings.url,
			method: 'POST',
			//dataType: 'json',
			data: {
				action  : 'woocommerce_shiptor_create_order',
				security: options.nonces.cleate,
				data: $inputs.serialize()
			},
			beforeSend: function(){
				$( '#wc-shiptor-result' ).empty();
				$( '.shiptor-order-details' ).block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			},
			success: function( response ) {
				if ( response && response.data && response.data.html ) {
					$( '.shiptor-order-details' ).html( response.data.html )
				} else if( response && response.data && response.data.error ) {
					$error = $( '<div />', {
						class:'error-notice',
						text: response.data.error
					} );
					$( '#wc-shiptor-result' ).html( $error );
				}
				$( '.shiptor-order-details' ).unblock();
			}
		});
	} );
	
	$( '.sender-order-date' ).datepicker({
		dateFormat: 'yy-mm-dd',
		numberOfMonths: 1,
		showButtonPanel: true,
		minDate: '+1D'//'today'
	});

})( window.jQuery, shiptor_order_params, woocommerce_admin );