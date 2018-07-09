<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 28.11.2017
 * Time: 1:04
 * Project: shiptor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Shiptor_Shipping_Shiptor_One_Day extends WC_Shiptor_Shipping {

    protected $service = 'shiptor-one-day';

    public function __construct( $instance_id = 0 ) {
        $this->id           		= 'shiptor-shiptor-one-day';
        $this->method_title 		= __( 'Shiptor One Day', 'woocommerce-shiptor' );
        $this->method_description 	= 'Доставка в день приема при доставке посылок на склад или при формировании посылок до 12:00.';
        parent::__construct( $instance_id );
		
		$exclude_fields = array(
			'show_delivery_time',
			'additional_time',
			'cityes_limit',
			'cityes_list',
			'end_to_end',
			'sender_address',
			'sender_city',
			'sender_name',
			'sender_email',
			'sender_phone'
		);
		
		foreach( $exclude_fields as $field ) {
			if( isset( $this->instance_form_fields[ $field ] ) ) {
				unset( $this->instance_form_fields[ $field ] );
			}
		}
		
		add_filter( 'woocommerce_shipping_shiptor-shiptor-one-day_is_available', array( $this, 'check_allowed_time' ) );
    }
	
	public function get_default_allowed_group() {
		return array( 'shiptor_one_day' );
	}
	
	public function get_allowed_group_list() {
		return array(
			'shiptor_one_day' => 'Shiptor Today'
		);
	}
	
	public function check_allowed_time( $available ) {
		
		if( $available ) {
			$available = strtotime( 'today 12:00' ) > current_time( 'timestamp' ) || strtotime( 'today 21:00' ) < current_time( 'timestamp' );
		}
		
		return $available;
	}
}