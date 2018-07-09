( function( $ ){
	
	function getEnhancedSelectFormatString() {
		return {
			'language': {
				errorLoading: function() {
					return wc_enhanced_select_params.i18n_searching;
				},
				inputTooLong: function( args ) {
					var overChars = args.input.length - args.maximum;

					if ( 1 === overChars ) {
						return wc_enhanced_select_params.i18n_input_too_long_1;
					}

					return wc_enhanced_select_params.i18n_input_too_long_n.replace( '%qty%', overChars );
				},
				inputTooShort: function( args ) {
					var remainingChars = args.minimum - args.input.length;

					if ( 1 === remainingChars ) {
						return wc_enhanced_select_params.i18n_input_too_short_1;
					}

					return wc_enhanced_select_params.i18n_input_too_short_n.replace( '%qty%', remainingChars );
				},
				loadingMore: function() {
					return wc_enhanced_select_params.i18n_load_more;
				},
				maximumSelected: function( args ) {
					if ( args.maximum === 1 ) {
						return wc_enhanced_select_params.i18n_selection_too_long_1;
					}

					return wc_enhanced_select_params.i18n_selection_too_long_n.replace( '%qty%', args.maximum );
				},
				noResults: function() {
					return wc_enhanced_select_params.i18n_no_matches;
				},
				searching: function() {
					return wc_enhanced_select_params.i18n_searching;
				}
			}
		};
	}
	
	$( ':input.city-ajax-load' ).filter( ':not(.enhanced)' ).each( function( index, $select ) {
		var $self = $( $select );
		var select2_args = {
			placeholder: wc_shiptor_admin_params.placeholder,
			minimumInputLength: 2,
			allowClear:false,
			escapeMarkup: function( m ) {
				return m;
			},
			ajax: {
				url: wc_shiptor_admin_params.ajax_url.toString().replace( '%%endpoint%%', 'shiptor_autofill_address' ),
				method: 'GET',
				dataType: 'json',
				delay: 250,
				data: function( params ) {
					return {
						city_name: params.term,
						country: wc_shiptor_admin_params.country_iso
					}
				},
				processResults: function( data ) {
					var terms = [];
					if ( data.success ) {
						$.each( data.data, function( id, item ) {
							if( item && item.country !== null && item.country == wc_shiptor_admin_params.country_iso ) {
								terms.push( { id: item.kladr_id, text: item.city_name + ' (' + item.state + ')' } );
							}
						});
					}
					return {
						results: terms
					};
				},
				cache: true
			}
		};
		
		select2_args = $.extend( select2_args, getEnhancedSelectFormatString() );
		
		$( this ).selectWoo( select2_args ).addClass( 'enhanced' );
	});
	
	$( 'html' ).on( 'click', function( event ) {
		if ( this === event.target ) {
			$( ':input.city-ajax-load' ).filter( '.select2-hidden-accessible' ).selectWoo( 'close' );
		}
	} );
	
	$( 'select[id*="_allowed_group"]' ).on( 'change', function(e) {
		$selected = $( e.target ).val() || [];
		$( 'input.group-titles-input').closest( 'tr' ).hide();
		$selected.map( function( value ) {
			$( 'input.group-titles-input' ).filter( function(){
				return $( this ).attr( 'data-group' ) === value;
			}).closest( 'tr' ).show();
			
		} );
	});
	
})( window.jQuery );