<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_COD_Card extends WC_Gateway_COD {
	
	public function __construct() {
		$this->id                 = 'cod_card';
		$this->method_title       = __( 'Card on delivery', 'woocommerce-shiptor' );
		$this->method_description = __( 'Have your customers pay with card upon delivery.', 'woocommerce-shiptor' );
		$this->has_fields         = false;
		
		$this->init_form_fields();
		$this->init_settings();
		
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->instructions       = $this->get_option( 'instructions' );
		$this->enable_for_methods = array();
		$this->enable_for_virtual = false;
		
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );
	}
	
	public function is_available() {
		
		if( ! is_admin() ) {
			if( 'RU' !== WC()->customer->get_billing_country() || in_array( 'shiptor-russian-post', wc_get_chosen_shipping_method_ids() ) ) {
				return false;
			}
		}
		
		return parent::is_available();
	}
	
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce' ),
				'label'       => __( 'Enable cash on delivery', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Cash on delivery', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
				'default'     => __( 'Pay with cash upon delivery.', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
				'default'     => __( 'Pay with cash upon delivery.', 'woocommerce' ),
				'desc_tip'    => true,
			)
	   );
	}
}