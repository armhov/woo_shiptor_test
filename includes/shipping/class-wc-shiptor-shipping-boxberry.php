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

class WC_Shiptor_Shipping_Boxberry extends WC_Shiptor_Shipping {

    protected $service = 'boxberry';

    public function __construct( $instance_id = 0 ) {
        $this->id           = 'shiptor-boxberry';
        $this->method_title = __( 'Boxberry', 'woocommerce-shiptor' );
        parent::__construct( $instance_id );
		
		$exclude_fields = array(
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
    }
	
	public function get_default_allowed_group() {
		return array( 'boxberry_courier', 'boxberry_delivery_point' );
	}
	
	public function get_allowed_group_list() {
		return array(
			'boxberry_courier' 			=> 'Boxberry Курьер',
			'boxberry_delivery_point' 	=> 'Boxberry Самовывоз',
		);
	}
}