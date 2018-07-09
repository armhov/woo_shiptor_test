<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 17.12.2017
 * Time: 22:52
 * Project: shiptor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Shiptor_Autofill_Addresses {
	
	public static $table = 'shiptor_address';
	
	protected $ajax_endpoint = 'shiptor_autofill_address';
	
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}
	
	public function init() {
		$this->maybe_install();	
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		add_action( 'wc_ajax_' . $this->ajax_endpoint, array( $this, 'ajax_autofill' ) );
	}
	
	protected static function logger( $data ) {
		if ( apply_filters( 'woocommerce_shiptor_enable_autofill_addresses_debug', false ) ) {
			$logger = new WC_Logger();
		}
	}
	
	protected static function get_validity() {
		return apply_filters( 'woocommerce_shiptor_autofill_addresses_validity_time', 'forever' );
	}
	
	public static function get_all_address() {
		global $wpdb;		
		$table = $wpdb->prefix . self::$table;
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY count_query", ARRAY_A );
	}
	
	public static function get_city_by_id( $kladr_id = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$table;
		$city = $wpdb->get_row( $wpdb->prepare( "SELECT kladr_id, city_name, state FROM $table WHERE 1 = 1 AND kladr_id = %s", $kladr_id ), ARRAY_A );
		return array(
			'kladr_id' 	=> isset( $city['kladr_id'] ) ? $city['kladr_id'] : null,
			'state' 	=> isset( $city['state'] ) ? $city['state'] : null,
			'city_name' => isset( $city['city_name'] ) ? $city['city_name'] : null
		);
	}
	
	public static function get_address( $city_name, $country ) {
		global $wpdb;

		if ( empty( $city_name ) ) {
			return null;
		}
		
		$country = ! empty( $country ) ? $country : WC_Countries::get_base_country();
		$table    = $wpdb->prefix . self::$table;
		$address  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE country = %s AND city_name LIKE %s;", $country, $wpdb->esc_like( $city_name ) . '%' ), ARRAY_A );
		
		if ( empty( $address ) || is_null( $address ) ) {
			$address = self::fetch_address( $city_name, $country );
			if ( ! empty( $address ) && is_array( $address ) ) foreach( $address as $new_address ) self::save_address( $new_address );		
		} else {
			
			$_address = array_merge( $address, self::fetch_address( $city_name, $country ) );
			
			$address = self::array_unique_deep( $_address, 'kladr_id' );
			
			foreach( $address as $has_address ) {
				if( ! isset( $has_address['is_new'] ) && self::check_if_expired( $has_address['last_query'] ) ) {
					self::update_address( $has_address );
				} elseif( isset( $has_address['is_new'] ) && $has_address['is_new'] ) {
					self::save_address( $has_address );
				}
			}
		}
		
		return $address;
	}
	
	private static function array_unique_deep( $array, $key ) {
		$result = array();
		$keys = array();
		foreach( $array as $index => $value ) {
			if( isset( $value[$key] ) && ! in_array( $value[$key], $keys ) ) {
				$keys[] = $value[$key];
				$result[$index] = $value;
			}
		}
		
		return $result;
	}
	
	protected static function check_if_expired( $last_query ) {
		$validity = self::get_validity();
		
		if ( 'forever' !== $validity && strtotime( '+' . $validity . ' months', strtotime( $last_query ) ) < current_time( 'timestamp' ) ) {
			return true;
		}
		return false;
	}
	
	protected static function save_address( $address ) {
		global $wpdb;
		
		if( isset( $address['is_new'] ) ) unset( $address['is_new'] );

		$default = array(
			'country'     	=> '',
			'state'      	=> '',
			'city_name'		=> '',
			'kladr_id'		=> '',
			'count_query'	=> 1,
			'last_query'	=> current_time( 'mysql' ),
		);

		$address = wp_parse_args( $address, $default );

		$result = $wpdb->insert(
			$wpdb->prefix . self::$table,
			$address,
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return false !== $result;
	}
	
	protected static function delete_address( $city, $state, $country ) {
		global $wpdb;

		$wpdb->delete( $wpdb->prefix . self::$table, array( 'city_name' => $city, 'state' => $state, 'country' => $country ), array( '%s', '%s', '%s' ) );
	}
	
	protected static function update_address( $address ) {
		self::delete_address( $address['city_name'], $address['state'], $address['country'] );
		return self::save_address( $address );
	}
	
	protected static function fetch_address( $city, $country ) {
		
		self::logger( sprintf( 'Fetching address for "%s" on Shiptor Webservices...', $city ) );
		
		$address = array();
		
		try {
			$connect = new WC_Shiptor_Connect();
			$response = $connect->request( array(
				'method'	=> 'suggestSettlement',
				'params'	=> array(
					'query'			=> $city,
					'country_code' 	=> $country
				)
			) );
			
			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}
			
			if( wp_remote_retrieve_response_code( $response ) == 200 ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				
				if( isset( $data['error'] ) ) {
					throw new Exception( $data['error']['message'] );
				} elseif( isset( $data['result'] ) && is_array( $data['result'] ) && ! empty( $data['result'] ) ) {
					foreach( $data['result'] as $result ) {
						if( empty( $result['kladr_id'] ) ) continue;
						
						$address[] = array(
							'country'		=> $result['country']['code'],
							'state'			=> ( isset( $result['readable_parents'] ) && ! empty( $result['readable_parents'] ) ) ? $result['readable_parents'] : $result['administrative_area'],
							'city_name'		=> $result['name'],
							'kladr_id'		=> $result['kladr_id'],
							'last_query'	=> current_time( 'mysql' ),
							'is_new'		=> true
						);
					}
				}
			}
		
		} catch ( Exception $e ) {
			self::logger( sprintf( 'An error occurred while trying to fetch address for "%s": %s', $city, $e->getMessage() ) );
		}
		
		if ( ! empty( $address ) ) {
			self::logger( sprintf( 'Address for "%s" found successfully: %d results', $city, count( $address ) ) );
		}

		return $address;
	}
	
	public function frontend_scripts() {
		if ( is_cart() || is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) {

			wp_enqueue_script( 'woocommerce-shiptor-autofill-addresses', plugins_url( 'assets/frontend/js/autofill-address.js', WC_Shiptor::get_main_file() ), array( 'jquery', 'jquery-blockui', 'select2' ), WC_SHIPTOR_VERSION, true );

			wp_localize_script(
				'woocommerce-shiptor-autofill-addresses',
				'wc_shiptor_autofill_address_params',
				array(
					'url'   => WC_AJAX::get_endpoint( $this->ajax_endpoint )
				)
			);
		}
	}
	
	public function ajax_autofill() {
		if ( empty( $_GET['city_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing city name paramater.', 'woocommerce-shiptor' ) ) );
			exit;
		}

		$city_name = trim( wp_unslash( $_GET['city_name'] ) );
		$default_location = wc_get_customer_default_location();
		$country = isset( $_GET['country'] ) ? esc_attr( $_GET['country'] ) : $default_location['country'];

		$address = self::get_address( $city_name, $country );

		if ( empty( $address ) ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Invalid %s city name.', 'woocommerce-shiptor' ), $city_name ) ) );
			exit;
		}
		
		$address = array_filter( array_map( array( $this, 'clean_address_data' ), $address ) );

		wp_send_json_success( $address );
	}
	
	protected function clean_address_data( $array ) {
		unset( $array['ID'] );
		unset( $array['last_query'] );
		return $array;
	}
	
	public function maybe_install() {
		$version = get_option( 'woocommerce_shiptor_autofill_addresses_db_version' );

		if ( empty( $version ) ) {
			self::create_database();

			update_option( 'woocommerce_shiptor_autofill_addresses_db_version', WC_SHIPTOR_VERSION );
		}
	}
	
	public static function create_database() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$table_name = $wpdb->prefix . self::$table;
		
		$sql = "CREATE TABLE $table_name (
			ID bigint(20) NOT NULL auto_increment,
			city_name longtext NULL,
			kladr_id char(20) NULL,
			state longtext NULL,
			country char(2) NULL,
			count_query bigint(20) NULL,
			last_query datetime NULL,
			PRIMARY KEY  (ID),
			KEY kladr_id (kladr_id)
		) $charset_collate;";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		dbDelta( $sql );
	}
}

new WC_Shiptor_Autofill_Addresses;