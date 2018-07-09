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

class WC_Shiptor_Shipping_DPD extends WC_Shiptor_Shipping {

    protected $service = 'dpd';

    public function __construct( $instance_id = 0 ) {
        $this->id           = 'shiptor-dpd';
        $this->method_title = __( 'DPD', 'woocommerce-shiptor' );
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
		return array( 'dpd_consumer', 'dpd_eparcel_courier', 'dpd_eparcel_delivery', 'dpd_consumer_delivery', 'dpd_economy_delivery', 'dpd_economy_courier' );
	}
	
	public function get_allowed_group_list() {
		return array(
			'dpd_consumer' 			=> 'DPD Курьер (Авиа)',
			'dpd_eparcel_courier' 	=> 'DPD Курьер (Авто)',
			'dpd_eparcel_delivery' 	=> 'DPD Самовывоз',
			'dpd_consumer_delivery' => 'DPD Самовывоз (Авиа)',
			'dpd_economy_delivery' 	=> 'DPD Economy ПВЗ',
			'dpd_economy_courier' 	=> 'DPD Economy Курьер'
		);
	}
}