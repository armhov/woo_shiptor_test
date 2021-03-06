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

        $is_enabled_methods = get_enabled_methods();
        $enabled_method_names = substring_enabled_methods($is_enabled_methods);

        $connect = new WC_Shiptor_Connect( 'calculate_product' );
        $connect->set_kladr_id( wc_shiptor_get_customer_kladr() );
        $connect->set_country_code( WC()->customer->get_billing_country() );
        $connect->set_package( $package );

        $shipping = $connect->get_shipping();

        $enabled_shippings = array_filter($shipping, function($item) use ($enabled_method_names) {
            if(in_array( $item['method']['courier'], $enabled_method_names )){
                return $item;
            }
        });

        $simplified_shipping_method = array();
        foreach($enabled_shippings as $item) {
            $simplified_shipping_method[] = array(
                'status'    => $item['status'],
                'method_id' => $item['method']['id'],
                'name'      => $item['method']['name'],
                'total'     => $item['cost']['total']['sum'],
                'currency'  => $item['cost']['total']['currency'],
                'readable'  => $item['cost']['total']['readable'],
                'days'      => $item['days']
            );
        };

        $settings = array();
        foreach($is_enabled_methods as $val){
            if( get_option( 'woocommerce_'.$val["method_id"].'_'.$val["instance_id"].'_settings' )){
                $settings[] = get_option('woocommerce_'.$val["method_id"].'_'.$val["instance_id"].'_settings');
            }
        }

        $ship_count = count($simplified_shipping_method);
        $set_count = count($settings);
        if(!empty($settings)){
            for( $i = 0; $i < $ship_count; $i++ ){
                for( $l = 0; $l < $set_count; $l++ ){
                    if( strpos( strtolower($simplified_shipping_method[$i]['name']), strtolower($settings[$l]['title'])) !== false ){
                        $simplified_shipping_method[$i] = array(
                            "fix_cost"   => $settings[$l]['fix_cost'],
                            "fee"        => $settings[$l]['fee'],
                            'status'     => $simplified_shipping_method[$i]['status'],
                            'method_id'  => $simplified_shipping_method[$i]['method_id'],
                            'name'       => $simplified_shipping_method[$i]['name'],
                            'total'      => $simplified_shipping_method[$i]['total'],
                            'currency'   => $simplified_shipping_method[$i]['currency'],
                            'readable'   => $simplified_shipping_method[$i]['readable'],
                            'days'       => $simplified_shipping_method[$i]['days']
                        );
                    }
                }
            }
        }

        foreach( $simplified_shipping_method as $service ) {
            if( ! empty( $service ) && is_array( $service ) ) {
                if( 'ok' !== $service['status'] ) {
                    continue;
                }
                if( isset($service['fix_cost']) && $service['fix_cost'] !=0 ){
                    $price = $service['fix_cost'];
                }elseif ( isset($service['fee']) && $service['fee'] !=0 ){
                    $price = $service['fee'] + $service['total'];
                }else{
                    $price = $service['total'];
                }
                $methods[] = array(
                    'term_id'	=> $service['method_id'],
                    'name'		=> $service['name'],
                    /* 'price'		=> floor( $service['cost']['total']['sum'] ),*/
                    'cost'		=> sprintf( __( 'From: %s %s', 'woocommerce-shiptor' ), $price, 'руб.' ),
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