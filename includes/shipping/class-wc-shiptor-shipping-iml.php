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

class WC_Shiptor_Shipping_IML extends WC_Shiptor_Shipping {

    protected $service = 'iml';

    public function __construct( $instance_id = 0 ) {
        $this->id           = 'shiptor-iml';
        $this->method_title = __( 'IML', 'woocommerce-shiptor' );
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
		return array( 'iml_courier', 'iml_delivery' );
	}
	
	public function get_allowed_group_list() {
		return array(
			'iml_courier' 	=> 'IML Курьер',
			'iml_delivery' 	=> 'IML Самовывоз'
		);
	}
}