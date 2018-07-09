<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 28.11.2017
 * Time: 0:16
 * Project: shiptor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Shiptor_Integration extends WC_Integration {

    public function __construct() {

        $this->id           = 'shiptor-integration';
        $this->method_title = __( sprintf( 'Shiptor %s', WC_SHIPTOR_VERSION ), 'woocommerce-shiptor' );
        
		$this->init_form_fields();
        $this->init_settings();
        
		$this->api_token               = $this->get_option( 'api_token' );
        $this->city_origin             = $this->get_option( 'city_origin' );
        $this->update_interval         = $this->get_option( 'update_interval' );
        $this->minimum_weight 		   = $this->get_option( 'minimum_weight' );
        $this->minimum_height 		   = $this->get_option( 'minimum_height' );
        $this->minimum_width 		   = $this->get_option( 'minimum_width' );
        $this->minimum_length 		   = $this->get_option( 'minimum_length' );
        $this->tracking_enable         = $this->get_option( 'tracking_enable' );
        $this->enable_tracking_debug   = $this->get_option( 'enable_tracking_debug' );
        $this->create_order_enable     = $this->get_option( 'create_order_enable' );
        $this->autofill_validity       = $this->get_option( 'autofill_validity' );
        $this->autofill_empty_database = $this->get_option( 'autofill_empty_database' );
        $this->autofill_debug          = $this->get_option( 'autofill_debug' );

		// API settings actions.
		add_filter( 'woocommerce_shiptor_api_token', array( $this, 'setup_api_token' ), 10 );
		add_filter( 'woocommerce_shiptor_city_origin', array( $this, 'setup_city_origin' ), 10 );
		add_filter( 'woocommerce_shiptor_update_interval', array( $this, 'setup_update_interval' ), 10 );
		// Product options.
		add_filter( 'woocommerce_shiptor_default_weight', array( $this, 'setup_default_weight' ), 10 );
		add_filter( 'woocommerce_shiptor_default_height', array( $this, 'setup_default_height' ), 10 );
		add_filter( 'woocommerce_shiptor_default_width', array( $this, 'setup_default_width' ), 10 );
		add_filter( 'woocommerce_shiptor_default_length', array( $this, 'setup_default_length' ), 10 );
        // Tracking history actions.
        add_filter( 'woocommerce_shiptor_enable_tracking_history', array( $this, 'setup_tracking_history' ), 10 );
        // Autofill address actions.
        add_filter( 'woocommerce_shiptor_enable_autofill_addresses_debug', array( $this, 'setup_autofill_addresses_debug' ), 10 );
        add_filter( 'woocommerce_shiptor_autofill_addresses_validity_time', array( $this, 'setup_autofill_addresses_validity_time' ), 10 );
        add_action( 'wp_ajax_shiptor_autofill_addresses_empty_database', array( $this, 'ajax_empty_database' ) );
		
		// Actions.
        add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    protected function get_tracking_log_link() {
        return sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( array( 'page' => 'wc-status', 'tab' => 'logs', 'log_file' => sanitize_file_name( wp_hash( $this->id ) ) . '.log' ), admin_url( 'admin.php') ) ), __( 'View logs.', 'woocommerce-shiptor' ) );
    }
	
	protected function get_common_log_url() {
		$url = str_replace(
			wp_normalize_path( untrailingslashit( ABSPATH ) ),
			site_url(),
			wp_normalize_path( wc_get_log_file_path( 'shiptor-common-log' ) )
		);
		return esc_url_raw( $url );
	}
	
	protected function get_common_log_del() {
		$url = wp_nonce_url( add_query_arg( array( 'handle' => 'shiptor-common-log' ), admin_url( 'admin.php?page=wc-status&tab=logs' ) ), 'remove_log' );
		return esc_url( $url );
	}

    public function init_form_fields() {
        
		$city_origin = WC_Shiptor_Autofill_Addresses::get_city_by_id( wc_shiptor_get_option( 'city_origin' ) );
		if( empty( $city_origin['kladr_id'] ) ) {
			$city_origin = array(
				'kladr_id' => '77000000000',
				'city_name' => 'Москва',
				'state'		=> 'г.Москва'
			);
		}
		
		$this->form_fields = array(
            'api_settings'    => array(
                'title'       => __( 'Shiptor integration API', 'woocommerce-shiptor' ),
                'type'        => 'title',
                'description' => __( 'Main settings for Shiptor integration', 'woocommerce-shiptor' ),
            ),
            'api_token'       => array(
                'title'       => __( 'API token', 'woocommerce-shiptor' ),
                'type'        => 'text',
				'placeholder' => __( 'Enter API Token here', 'woocommerce-shiptor' ),	
                'description' => __( 'The Token API is required for the plugin to work. You can get it in your personal account https://shiptor.ru/account/settings/api', 'woocommerce-shiptor' ),
				'custom_attributes'	  => array( 'required' => 'required' )
            ),
            'city_origin'     => array(
                'title'       => 'Город',
                'class'       => 'wc-enhanced-select',
                'type'		  => 'select_city_origin',
				'default'	  => '77000000000',
                'description' => 'Выберите город доставки и отправления (сквозная) по умолчанию.'
            ),
            'update_interval' => array(
                'title'       => __( 'Status update interval', 'woocommerce-shiptor' ),
                'type'        => 'select',
                'default'     => 'five_min',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'Set the refresh interval for the delivery order processing statuses', 'woocommerce-shiptor' ),
                'options'     => array(
                    'one_min'     	=> __( 'One minute', 'woocommerce-shiptor' ),
                    'five_min'     	=> __( 'Five minutes', 'woocommerce-shiptor' ),
                    'fifteen_min'   => __( '15 minutes', 'woocommerce-shiptor' ),
                    'half_hour'    	=> __( '30 minutes', 'woocommerce-shiptor' ),
                    'hourly'    	=> __( 'One hour', 'woocommerce-shiptor' )
                )
            ),
			'methods_sort'	  => array(
				'title'		=> __( 'Sort shipping methods', 'woocommerce-shiptor' ),
				'type'		=> 'title'
			),
			'sorting_type'	=> array(
				'title'		=> 'Сортировать методы',
				'type'		=> 'select',
				'class'     => 'wc-enhanced-select',
				'label'		=> 'Выберите каким образом сортировать методы доставки.',
				'options'	=> array(
					'-1'	=> 'По-умолчанию',
					'cost'	=> 'По стоимости',
					'date'	=> 'По времени доставки'
				)
			),
			'shipping_class_sort'	=> array(
				'title'		=> __( 'Enable/Disable', 'woocommerce-shiptor' ),
				'type'    	=> 'checkbox',
				'label'   => __( 'Enable shipping methods sorting by shipping classes. (Not available yet.)', 'woocommerce-shiptor' ),
				'disabled'	=> true,
				'default' => 'no'
			),
            'package_default' => array(
                'title'       => __( 'Product options', 'woocommerce-shiptor' ),
                'type'        => 'title',
                'description' => __( 'These parameters will be used by default if the item is not filled with these parameters', 'woocommerce-shiptor' )
            ),
            'minimum_weight' => array(
                'title' 		=> __( 'Default weight, (kg)', 'woocommerce-shiptor' ),
                'type' 			=> 'decimal',
				'css'			=> 'width:50px;',
                'default'		=> 0.5,
				'custom_attributes'	  => array( 'required' => 'required' )
            ),
            'minimum_height' => array(
                'title' 		=> __( 'Default height, (cm)', 'woocommerce-shiptor' ),
                'type' 			=> 'decimal',
				'css'			=> 'width:50px;',
                'default'		=> 15,
				'custom_attributes'	  => array( 'required' => 'required' )
            ),
            'minimum_width' => array(
                'title' 		=> __( 'Default Width, (cm.)', 'woocommerce-shiptor' ),
                'type' 			=> 'decimal',
				'css'			=> 'width:50px;',
                'default'		=> 15,
				'custom_attributes'	  => array( 'required' => 'required' )
            ),
            'minimum_length' => array(
                'title' 		=> __( 'Default length, (cm.)', 'woocommerce-shiptor' ),
                'type' 			=> 'decimal',
				'css'			=> 'width:50px;',
                'default'		=> 15,
				'custom_attributes'	  => array( 'required' => 'required' )
            ),
            'tracking'        => array(
                'title'       => __( 'Tracking History Table', 'woocommerce-shiptor' ),
                'type'        => 'title',
                'description' => __( 'Displays a table with informations about the shipping in My Account > View Order page.', 'woocommerce-shiptor' ),
            ),
            'tracking_enable'         => array(
                'title'   => __( 'Enable/Disable', 'woocommerce-shiptor' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Tracking History Table', 'woocommerce-shiptor' ),
                'default' => 'no',
            ),
			'enable_tracking_debug'	=> array(
				'title'   => __( 'Enable/Disable', 'woocommerce-shiptor' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Tracking History debug', 'woocommerce-shiptor' ),
                'default' => 'no',
			),
			'create_order_enable'	=> array(
				'title'   => __( 'Enable/Disable', 'woocommerce-shiptor' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable create order debug', 'woocommerce-shiptor' ),
                'default' => 'no',
			),
			'calculate_in_product' => array(
				'title'	  => __( 'Enable/Disable', 'woocommerce-shiptor' ),
				'type'    => 'checkbox',
                'label'   => __( 'Show delivery methods in the product card', 'woocommerce-shiptor' ),
                'default' => 'no'
			),
            'autofill_addresses'      => array(
                'title'       => __( 'Autofill Addresses', 'woocommerce-shiptor' ),
                'type'        => 'title',
                'description' => __( 'Displays a table with informations about the shipping in My Account > View Order page.', 'woocommerce-shiptor' ),
            ),
            'autofill_validity'       => array(
                'title'       => __( 'Cities list Validity', 'woocommerce-shiptor' ),
                'type'        => 'select',
                'default'     => '3',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'Defines how long a cities will stay saved in the database before a new query.', 'woocommerce-shiptor' ),
                'options'     => array(
                    '1'       => __( '1 month', 'woocommerce-shiptor' ),
                    '2'       => sprintf( __( '%d months', 'woocommerce-shiptor' ), 2 ),
                    '3'       => sprintf( __( '%d months', 'woocommerce-shiptor' ), 3 ),
                    '4'       => sprintf( __( '%d months', 'woocommerce-shiptor' ), 4 ),
                    '5'       => sprintf( __( '%d months', 'woocommerce-shiptor' ), 5 ),
                    '6'       => sprintf( __( '%d months', 'woocommerce-shiptor' ), 6 ),
                    '7'       => sprintf( __( '%d months', 'woocommerce-shiptor' ), 7 ),
                    '8'       => sprintf( __( '%d months', 'woocommerce-shiptor' ), 8 ),
                    '9'       => sprintf( __( '%d months', 'woocommerce-shiptor' ), 9 ),
                    '10'      => sprintf( __( '%d months', 'woocommerce-shiptor' ), 10 ),
                    '11'      => sprintf( __( '%d months', 'woocommerce-shiptor' ), 11 ),
                    '12'      => sprintf( __( '%d months', 'woocommerce-shiptor' ), 12 ),
                    'forever' => __( 'Forever', 'woocommerce-shiptor' ),
                ),
            ),
            'autofill_empty_database' => array(
                'title'       => __( 'Empty Database', 'woocommerce-shiptor' ),
                'type'        => 'button',
                'label'       => __( 'Empty Database', 'woocommerce-shiptor' ),
                'description' => __( 'Delete all the saved cities in the database, use this option if you have issues with outdated cities.', 'woocommerce-shiptor' ),
            ),
            'autofill_debug'          => array(
                'title'       => __( 'Debug Log', 'woocommerce-shiptor' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging for Autofill Addresses', 'woocommerce-shiptor' ),
                'default'     => 'no',
                'description' => sprintf( __( 'Log %s events, such as Shiptor servers requests.', 'woocommerce-shiptor' ), __( 'Autofill Addresses', 'woocommerce-shiptor' ) ) . $this->get_tracking_log_link(),
            ),
			'enable_common_log' => array(
				'title'		=> __( 'Enable common debug log', 'woocommerce-shiptor' ),
				'type'      => 'checkbox',
				'default'   => 'no',
				'description' => sprintf( __( 'Log file location: <code>%s</code>. <a href="%s">View log</a> | <a download="download" href="%s">Download log</a> | <a href="%s">Remove log</a>', 'woocommerce-shiptor' ), wc_get_log_file_path( 'shiptor-common-log' ), esc_url( add_query_arg( 'log_file', wc_get_log_file_name( 'shiptor-common-log' ), admin_url( 'admin.php?page=wc-status&tab=logs' ) ) ), $this->get_common_log_url(), $this->get_common_log_del() )
			)
        );
    }

    public function admin_options() {

        echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';

        echo wp_kses_post( wpautop( $this->get_method_description() ) );

        include WC_Shiptor::get_plugin_path() . 'includes/admin/views/html-admin-help-message.php';

        echo '<div><input type="hidden" name="section" value="' . esc_attr( $this->id ) . '" /></div>';
        echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>';

        wp_enqueue_style( $this->id . '-admin', plugins_url( 'assets/admin/css/integration.css', WC_Shiptor::get_main_file() ), array(), WC_SHIPTOR_VERSION );
        wp_enqueue_script( $this->id . '-admin', plugins_url( 'assets/admin/js/integration.js', WC_Shiptor::get_main_file() ), array( 'jquery', 'jquery-blockui', 'select2' ), WC_SHIPTOR_VERSION, true );

        wp_localize_script(
            $this->id . '-admin',
            'wc_shiptor_admin_params',
            array(
                'i18n' => array(
                    'confirm_message' => sprintf( __( 'Are you sure you want to delete all (%d) cities from the database? If you delete all cities, the settings associated with cities may be reset.', 'woocommerce-shiptor' ), count( WC_Shiptor_Autofill_Addresses::get_all_address() ))
                ),
                'empty_database_nonce' 	=> wp_create_nonce( 'woocommerce_shiptor_autofill_addresses_nonce' ),
				'ajax_url'				=> WC_AJAX::get_endpoint( "%%endpoint%%" ),
				'country_iso'			=> WC()->countries->get_base_country()
            )
        );
    }
	
	public function generate_select_city_origin_html( $key, $data ) {
		
		$city_origin = WC_Shiptor_Autofill_Addresses::get_city_by_id( $this->get_option( $key ) );
		
		if( empty( $city_origin['kladr_id'] ) ) {
			$city_origin = array(
				'kladr_id' => '77000000000',
				'city_name' => 'Москва',
				'state'		=> 'г.Москва'
			);
		}
		
		$data['type'] = 'select';
		$data['options'] = array( $city_origin['kladr_id'] => sprintf( '%s (%s)', $city_origin['city_name'], $city_origin['state'] ) );
		
		return $this->generate_select_html( $key, $data );
	}

    public function generate_button_html( $key, $data ) {
        $field_key = $this->get_field_key( $key );
        $defaults  = array(
            'title'       => '',
            'label'       => '',
            'desc_tip'    => false,
            'description' => '',
        );
        $data = wp_parse_args( $data, $defaults );
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
                <?php echo $this->get_tooltip_html( $data );?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <button class="button-secondary" type="button" id="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['label'] ); ?></button>
                    <?php echo $this->get_description_html( $data );?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
	
	public function setup_api_token() {
		return $this->api_token;
	}
	
	public function setup_city_origin() {
		return $this->city_origin;
	}
	
	public function setup_update_interval() {
		return $this->update_interval;
	}
	
	public function setup_default_weight() {
		return $this->minimum_weight;
	}
	
	public function setup_default_height() {
		return $this->minimum_height;
	}
	
	public function setup_default_width() {
		return $this->minimum_width;
	}
	
	public function setup_default_length() {
		return $this->minimum_length;
	}

    public function setup_tracking_history() {
        return 'yes' === $this->tracking_enable;
    }

    public function setup_autofill_addresses_debug() {
        return 'yes' === $this->autofill_debug;
    }

    public function setup_autofill_addresses_validity_time() {
        return $this->autofill_validity;
    }

    public function ajax_empty_database() {
        global $wpdb;
        if ( ! isset( $_POST['nonce'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing parameters!', 'woocommerce-shiptor' ) ) );
            exit;
        }
        if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woocommerce_shiptor_autofill_addresses_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid nonce!', 'woocommerce-shiptor' ) ) );
            exit;
        }
        $table_name = $wpdb->prefix . WC_Shiptor_Autofill_Addresses::$table;
        $wpdb->query( "DROP TABLE IF EXISTS $table_name;" );
        WC_Shiptor_Autofill_Addresses::create_database();
        wp_send_json_success( array( 'message' => __( 'Cities database emptied successfully!', 'woocommerce-shiptor' ) ) );
    }
	
	public function process_admin_options() {
		parent::process_admin_options();
		
		if( empty( $this->settings['api_token'] ) ) {
			$message = __( 'Empty API token. ', 'woocommerce-shiptor' ) . __( 'The Token API is required for the plugin to work. You can get it in your personal account https://shiptor.ru/account/settings/api', 'woocommerce-shiptor' );
			WC_Admin_Notices::add_custom_notice( $this->id . '_api_token', $message );
		} else {
			if( WC_Admin_Notices::has_notice( $this->id . '_api_token' ) ) {
				WC_Admin_Notices::remove_notice( $this->id . '_api_token' );
			}
		}
		
		if( $this->settings['update_interval'] !== $this->update_interval ) {
			wp_clear_scheduled_hook( 'shiptor_update_order_statuses' );
			wp_schedule_event( time(), $this->settings['update_interval'], 'shiptor_update_order_statuses' );
		}
	}
}