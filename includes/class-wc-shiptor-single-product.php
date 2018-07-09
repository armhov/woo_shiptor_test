<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Shiptor_Single_Product {
	
	public function __construct() {
		if( 'yes' !== wc_shiptor_get_option( 'calculate_in_product' ) ) {
			return;
		}
		add_action( 'woocommerce_single_product_summary', array( $this, 'calculate_shipping' ), 35 );
		add_action( 'wp_ajax_shiptor_get_shipping_methods', array( $this, 'get_methods' ) );
		add_action( 'wp_ajax_nopriv_shiptor_get_shipping_methods', array( $this, 'get_methods' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
	}
	
	public function load_scripts() {
		global $post;

		if ( ! did_action( 'before_woocommerce_init' ) ) {
			return;
		}
		
		wp_register_style( 'shiptor-product', plugins_url( '/assets/frontend/css/product.css' , WC_Shiptor::get_main_file() ), array( 'select2' ) );
		wp_register_style( 'simplebar', '//cdn.jsdelivr.net/npm/simplebar@latest/dist/simplebar.css', array() );
		wp_register_script( 'simplebar', '//cdn.jsdelivr.net/npm/simplebar@latest/dist/simplebar.js', array(), WC_SHIPTOR_VERSION, true  );
		wp_register_script( 'shiptor-calculate-shipping', plugins_url( '/assets/frontend/js/product.js', WC_Shiptor::get_main_file() ), array( 'jquery', 'wp-util', 'selectWoo', 'jquery-blockui', 'underscore', 'backbone' ), WC_SHIPTOR_VERSION, true );
		
		if( is_product() ) {
			
			wp_enqueue_style( 'shiptor-product' );
			wp_enqueue_style( 'simplebar' );
			wp_enqueue_script( 'shiptor-calculate-shipping' );
			wp_enqueue_script( 'simplebar' );
			
			wp_localize_script( 'shiptor-calculate-shipping', 'shiptor_product_shipping', array(
				'ajax_url'	=> WC_AJAX::get_endpoint( 'shiptor_autofill_address' ),
				'i18n'	=> array(
					'select_state_text'    => esc_attr__( 'Select an option&hellip;', 'woocommerce' ),
					'no_matches'           => _x( 'No matches found', 'enhanced select', 'woocommerce' ),
					'ajax_error'           => _x( 'Loading failed', 'enhanced select', 'woocommerce' ),
					'input_too_short_1'    => _x( 'Please enter 1 or more characters', 'enhanced select', 'woocommerce' ),
					'input_too_short_n'    => _x( 'Please enter %qty% or more characters', 'enhanced select', 'woocommerce' ),
					'input_too_long_1'     => _x( 'Please delete 1 character', 'enhanced select', 'woocommerce' ),
					'input_too_long_n'     => _x( 'Please delete %qty% characters', 'enhanced select', 'woocommerce' ),
					'selection_too_long_1' => _x( 'You can only select 1 item', 'enhanced select', 'woocommerce' ),
					'selection_too_long_n' => _x( 'You can only select %qty% items', 'enhanced select', 'woocommerce' ),
					'load_more'            => _x( 'Loading more results&hellip;', 'enhanced select', 'woocommerce' ),
					'searching'            => _x( 'Searching&hellip;', 'enhanced select', 'woocommerce' ),
					'choose_city_text' 	   => __( 'Choose an city', 'woocommerce-shiptor' )
				),
				'location'	=> array(
					'id'	=> wc_shiptor_get_customer_kladr(),
					'country'	=> WC()->customer->get_billing_country()
				),
				'methods'	=> $this->get_shipping( $post->ID ),
				'nonce'		=> wp_create_nonce( 'wc_shiptor_shipping_methods_nonce' ),
				'post_id'	=> $post->ID
			) );
		}
	}
	
	function calculate_shipping() {
		global $product;
		wc_get_template( 'single-product/calculate-shipping.php', array(
			'product'	=> $product
		), '', WC_Shiptor::get_templates_path() );
	}
	
	private function get_shipping( $product_id ) {
		$methods = array();
		$product = wc_get_product( $product_id );
		
		$package['contents'][]	= array(
			'data'		=> $product,
			'quantity'	=> 1
		);

        global $wpdb;
        $is_enabled_methods = array();
        $result = $wpdb->get_results("SELECT method_id, is_enabled FROM wp_woocommerce_shipping_zone_methods ");
        if( !empty( $result ) && is_array( $result ) ){
            foreach($result as $row) {
                if($row->is_enabled == 1){
                    $is_enabled_methods[] = $row->method_id;
                }
            }
        }

        $new_arr = array();
        $count = count($is_enabled_methods);

        for($i = 0; $i < $count; $i ++){
            $new_arr[] = substr($is_enabled_methods[$i], 8);
        }

		$connect = new WC_Shiptor_Connect( 'calculate_product' );
		$connect->set_kladr_id( wc_shiptor_get_customer_kladr() );
		$connect->set_country_code( WC()->customer->get_billing_country() );
		$connect->set_package( $package );

        $shipping = $connect->get_shipping();

        $enabled_shippings = array_filter($shipping, function($item) use ($new_arr) {
            if(in_array( $item['method']['courier'], $new_arr )){
               return $item;
            }
        });

		foreach($enabled_shippings as $service ) {
			
			if( ! empty( $service ) && is_array( $service ) ) {
                if( 'ok' !== $service['status'] ) {
                    continue;
                }
                $methods[] = array(
                    'term_id'	=> $service['method']['id'],
                    'name'		=> $service['method']['name'],
                    'price'		=> floor( $service['cost']['total']['sum'] ),
                    'cost'		=> sprintf( __( 'From: %s', 'woocommerce-shiptor' ), $service['cost']['total']['readable'] ),
                    'days'		=> $service['days']
                );
			}
		}
		return $methods;
	}
	
	public function get_methods() {
		if ( ! isset( $_POST['security'], $_POST['params'], $_POST['post_id'] ) ) {
			wp_send_json_error( 'missing_fields' );
			exit;
		}

		if ( ! wp_verify_nonce( $_POST['security'], 'wc_shiptor_shipping_methods_nonce' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		
		$changes = $_POST['params'];
		
		WC()->session->set( 'billing_kladr_id', $changes['id'] );
		
		$customer_id = WC()->customer->get_id();
		
		if( $customer_id > 0 ) {
			$customer = new WC_Customer( $customer_id );
			$customer->update_meta_data( 'billing_kladr_id', $changes['id'] );
			$customer->set_billing_country( $changes['country'] );
			$customer->set_billing_state( $changes['state'] );
			$customer->save();
		}
		
		wp_send_json_success( array(
			'methods' => $this->get_shipping( $_POST['post_id'] )
		) );

	}
}

new WC_Shiptor_Single_Product();
?>