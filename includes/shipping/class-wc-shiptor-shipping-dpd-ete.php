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

class WC_Shiptor_Shipping_DPD_ETE extends WC_Shiptor_Shipping {

    protected $service = 'dpd';

    public function __construct( $instance_id = 0 ) {
        $this->id           = 'shiptor-dpd-ete';
        $this->method_title = 'DPD (сквозная)';
		$this->is_ete 		= true;
        parent::__construct( $instance_id );
    }
	
	public function get_default_allowed_group() {
		return array( 'dpd_dd', 'dpd_dt', 'dpd_tt', 'dpd_td' );
	}
	
	public function get_allowed_group_list() {
		return array(
			'dpd_dd' => 'DPD Дверь - Дверь',
			'dpd_dt' => 'DPD Дверь - ПВЗ',
			'dpd_tt' => 'DPD ПВЗ - ПВЗ',
			'dpd_td' => 'DPD ПВЗ - Дверь'
		);
	}
}