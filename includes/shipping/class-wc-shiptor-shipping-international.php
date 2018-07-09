<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Shiptor_Shipping_International extends WC_Shiptor_Shipping {

    protected $service = 'aramex';

    public function __construct( $instance_id = 0 ) {
        $this->id           = 'shiptor-aramex';
        $this->method_title = __( 'Shiptor International', 'woocommerce-shiptor' );
        parent::__construct( $instance_id );
		
		$exclude_fields = array(
			'cityes_limit',
			'cityes_list',
			'enable_declared_cost',
			'end_to_end',
			'sender_city',
			'sender_address',
			'sender_name',
			'sender_email',
			'sender_phone'
		);
		
		foreach( $exclude_fields as $field ) {
			if( isset( $this->instance_form_fields[ $field ] ) ) {
				unset( $this->instance_form_fields[ $field ] );
			}
		}
    }
	
	public function get_rates( $package ) {
		if ( empty( $package['destination']['country'] ) || in_array( $package['destination']['country'], array( 'RU', 'BY', 'KZ' ) ) ) {
            return false;
        }

        if ( $this->is_shiptor() && ! $this->has_only_selected_shipping_class( $package ) ) {
            return false;
        }
		
		if ( ! $this->instance_id ) {
			return false;
		}
		
		if( $this->min_cost > 0 && $this->min_cost > $this->get_declared_value( $package ) ) {
			return false;
		}
		
		$rates = array();
		
		$this->connect->set_debug( $this->debug );
		$this->connect->set_package( $package );
		$this->connect->set_country_code( $package['destination']['country'] );
		
		$shipping = $this->connect->get_international_shipping();
        
		if ( empty( $shipping ) ) {
            return false;
        }
		
		foreach( $shipping as $shipping_method ) {
			
			if( 'ok' !== $shipping_method['status'] ) {
				continue;
			}
			
			$label = $this->get_label_by_group( $shipping_method['method']['name'], $shipping_method['method']['group'] );
			
			$meta_data = array(
				'shiptor_method' => array_merge( $shipping_method['method'], array(
					'label'	=> $this->get_shipping_method_label( $label, $shipping_method['days'], $package )
				) )
			);
			
			$cost = $shipping_method['cost']['total']['sum'];
			
			if( $this->fix_cost > 0 ) {
				$cost = $this->fix_cost;
			} elseif( $this->free > 0 && $this->get_declared_value( $package ) >= $this->free ) {
				$cost = wc_format_localized_price( 0 );
			} elseif( ! empty( $this->fee ) && $this->fee !== 0 ) {
				switch( $this->fee_type ) {
					case 'order' :
						$cost += $this->get_fee( $this->fee, $this->get_declared_value( $package ) );
						break;
					case 'shipping' :
						$cost += $this->get_fee( $this->fee, $cost );
						break;
					case 'both' :
						$cost += $this->get_fee( $this->fee, $this->get_declared_value( $package ) + $cost );
						break;						
				}
			}
			
			$rates[] = array(
				'id'    	=> $this->get_rate_id( $shipping_method['method']['group'] ),
				'label' 	=> $label,
				'cost' 		=> $cost,
				'meta_data'	=> $meta_data
			);
		}
		
		if ( sizeof( $rates ) == 0 ) {
			return false;
		}
		
		$this->available_rates = $rates;

		return true;
	}
	
	public function get_default_allowed_group() {
		return array( 'aramex_courier' );
	}
	
	public function get_allowed_group_list() {
		return array(
			'aramex_courier' => 'Shiptor International'
		);
	}
}