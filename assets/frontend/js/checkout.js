(function( $ ){
	
	var make_map = function() {
		
		ymaps.ready(function () {

            var contactmap;
            var map_container = 'delivery_points';
            var points = $( '#' + map_container ).data( 'points' );
					
            ymaps.geocode( $('#billing_city option:selected').text() , { results: 1 }).then( function( response ){

                var getCoordinats = response.geoObjects.get(0).geometry.getCoordinates();
						
                contactmap = new ymaps.Map( map_container, {
                    center: getCoordinats,
                    zoom: 10,
                    behaviors: ['default', 'scrollZoom'],
                    controls: ['zoomControl', 'fullscreenControl']
                });

                $.map( points, function( point, i ) {
                    addPointToMap( point, i );
                });

                function addPointToMap( point, i ) {
							
					var $text_cod = point.cod ? '<span style="color:green;">Есть</span>' : '<span style="color:red;">Нет</span>';
					var $text_card = point.card ? '<span style="color:green;">Есть</span>' : '<span style="color:red;">Нет</span>';
					var $work_schedule = point.work_schedule ? '<p>Режим работы: ' + point.work_schedule + '</p>' : '';
					var $trip_description = point.trip_description ? '<p>' + point.trip_description + '</p>' : '';
                            
					var placemark = new ymaps.Placemark( [ point.gps_location.latitude, point.gps_location.longitude ], {
                        balloonContentBody: [
							'<strong>[' + point.courier.toUpperCase() + ']</strong>',
                            '<address>' + point.address + '</address>',
							$work_schedule,
							$trip_description,
							'<span>Наложенный платёж: ' + $text_cod + '</span><br />',
							'<span>Оплата картой: ' + $text_card + '</span>'
                        ].join(''),
                            shiptorElemValue: point.id,
                            shiptorElemIndex: i
                    }, {
						preset: "islands#blueCircleDotIcon",
                        iconColor: '#1faee9',
                        balloonCloseButton: true,
                        hideIconOnBalloonOpen: false
					} );

                    placemark.events.add('click', function( event ) {
                            
						//$("#billing_address_1").val( point.address ).trigger('change');
						$("#billing_address_1").val( point.address );
						
						$.post( shiptor_checkout_params.delivery_point_url, {
							'delivery_point':point.id
						}, function( data ) {
							console.log( data );
						} );
                    } );							

                    contactmap.geoObjects.add( placemark );
							
                };

                if( typeof( contactmap.geoObjects.getLength() ) != 'undefined' && contactmap.geoObjects.getLength() > 1 ) {
                    contactmap.setBounds( contactmap.geoObjects.getBounds() );
                } else {
                    contactmap.setCenter( contactmap.geoObjects.get(0).geometry.getCoordinates() );
                }
            });
        });
	}
	
	$( document.body ).on( 'updated_checkout', function() {
		make_map();
		$( '.shiptor-delivery-points' ).removeClass( 'processing' ).unblock();
		
		$( '#billing_address_1' ).bind( 'keyup blur', function( e ) {
			$( '#billing_to_door_address' ).val( e.target.value );
		});
	});
	
	$( document.body ).on( 'update_checkout', function() {
		$( '.shiptor-delivery-points' ).addClass( 'processing' ).block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
	});
	
	$( document.body ).on( 'payment_method_selected', function( e ) {
		$( document.body ).trigger( 'update_checkout', { update_shipping_method: true } );
	} );
	
})( window.jQuery );