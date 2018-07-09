jQuery( function( $ ) {
	var WC_Shiptor_Integration_Admin = {

		init: function() {
			$( document.body ).on( 'click', '#woocommerce_shiptor-integration_autofill_empty_database', this.empty_database );
			
			$( '#woocommerce_shiptor-integration_city_origin, #woocommerce_shiptor-cdek_sender_city, #woocommerce_shiptor-dpd_sender_city' ).select2({
				placeholder: 'Выберите город',
				minimumInputLength: 2,
				multiple: false,
				ajax: {
					url: wc_shiptor_admin_params.ajax_url.toString().replace( '%%endpoint%%', 'shiptor_autofill_address' ),
					method: 'GET',
					dataType: "json",
					delay: 350,
					data:function( params ) {
						return {
							city_name: params.term,
							country: wc_shiptor_admin_params.country_iso
						}
					},
					processResults: function ( data ) {
						if( data.success ) {
							return {
								results: $.map( data.data, function ( item ) {
									if( ! item || item.country == null || item.country !== wc_shiptor_admin_params.country_iso ) return;
									return {
										id: item.kladr_id,
										text: item.city_name + ' (' + item.state + ')'
									}
								})
							}
						}
					},
					cache: true
				},
				current: function (element, callback) {
					callback({
						'id': element.val(),
						'text': element.text()
					});
				},
				formatSelection: function( data ) { 
					return data.text; 
				}
			});
		},
		
		empty_database: function() {
			if ( ! window.confirm( wc_shiptor_admin_params.i18n.confirm_message ) ) {
				return;
			}

			$( '#mainform' ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'shiptor_autofill_addresses_empty_database',
					nonce: wc_shiptor_admin_params.empty_database_nonce
				},
				success: function( response ) {
					window.alert( response.data.message );
					$( '#mainform' ).unblock();
				}
			});
		}
	};

	WC_Shiptor_Integration_Admin.init();
});
