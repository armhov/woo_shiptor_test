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

class WC_Shiptor_Shipping_CDEK_ETE extends WC_Shiptor_Shipping {

    protected $service = 'cdek';

    public function __construct( $instance_id = 0 ) {
        $this->id           = 'shiptor-cdek-ete';
        $this->method_title = 'CDEK (сквозная)';
		$this->is_ete 		= true;
        parent::__construct( $instance_id );
    }
	
	public function get_default_allowed_group() {
		return array( 'cdek_tt', 'cdek_td', 'cdek_dt', 'cdek_dd' );
	}
	
	public function get_allowed_group_list() {
		return array(
			'cdek_tt'				=> 'CDEK ПВЗ-ПВЗ',
			'cdek_td'				=> 'CDEK ПВЗ-Дверь',
			'cdek_dt'				=> 'CDEK Дверь-ПВЗ',
			'cdek_dd'				=> 'CDEK Дверь-Дверь'
		);
	}
}