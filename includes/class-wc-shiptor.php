<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 27.11.2017
 * Time: 23:34
 * Project: shiptor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WC_Shiptor {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ), -1 );

        if ( class_exists( 'WC_Integration' ) ) {
            self::includes();
            if ( is_admin() ) {
                self::admin_includes();
            }
            add_filter( 'woocommerce_integrations', array( __CLASS__, 'include_integrations' ) );
            add_filter( 'woocommerce_shipping_methods', array( __CLASS__, 'include_methods' ) );
			add_filter( 'woocommerce_shiptor_shipping_methods_array', array( __CLASS__, 'check_allowed_methods' ) );
			add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'payment_cod_via_card' ) );
            add_filter( 'woocommerce_email_classes', array( __CLASS__, 'include_emails' ) );
			add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
			add_filter( 'plugin_action_links_' . plugin_basename( WC_SHIPTOR_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
        } else {
            add_action( 'admin_notices', array( __CLASS__, 'woocommerce_missing_notice' ) );
        }
		
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
    }
	
	public static function cron_schedules( $schedules ) {
		$schedules['one_min'] = array(
			'interval' => 60,
			'display'  => __( 'Every minute', 'woocommerce-shiptor' ),
		);
		$schedules['five_min'] = array(
			'interval' => HOUR_IN_SECONDS / 12,
			'display'  => __( 'Every five minutes', 'woocommerce-shiptor' ),
		);
		$schedules['fifteen_min'] = array(
			'interval' => HOUR_IN_SECONDS / 4,
			'display'  => __( 'Every fifteen minutes', 'woocommerce-shiptor' ),
		);
		$schedules['half_hour'] = array(
			'interval' => HOUR_IN_SECONDS / 2,
			'display'  => __( 'Every an half hour', 'woocommerce-shiptor' ),
		);
		return $schedules;
	}

    public static function load_plugin_textdomain() {
        load_plugin_textdomain( 'woocommerce-shiptor', false, dirname( plugin_basename( WC_SHIPTOR_PLUGIN_FILE ) ) . '/languages/' );
    }

    private static function includes() {
        include_once dirname( __FILE__ ) . '/wc-shiptor-functions.php';
        include_once dirname( __FILE__ ) . '/class-wc-shiptor-install.php';
        include_once dirname( __FILE__ ) . '/class-wc-shiptor-package.php';
        include_once dirname( __FILE__ ) . '/class-wc-shiptor-connect.php';
        include_once dirname( __FILE__ ) . '/class-wc-shiptor-autofill-addresses.php';
		include_once dirname( __FILE__ ) . '/class-wc-shiptor-checkout.php';
        include_once dirname( __FILE__ ) . '/class-wc-shiptor-tracking-history.php';
        include_once dirname( __FILE__ ) . '/class-wc-shiptor-rest-api.php';
        include_once dirname( __FILE__ ) . '/class-wc-shiptor-single-product.php';
        include_once dirname( __FILE__ ) . '/class-wc-shiptor-gateway-cod.php';

        include_once dirname( __FILE__ ) . '/class-wc-shiptor-integration.php';

        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.6.0', '>=' ) ) {
            include_once dirname( __FILE__ ) . '/abstracts/abstract-wc-shiptor-shipping.php';
            //TODO: Добавить пути к другим методам
            foreach ( glob( plugin_dir_path( __FILE__ ) . '/shipping/*.php' ) as $filename ) {
                include_once $filename;
            }

            //WC_Shiptor_Install::upgrade_300_from_wc_260();

        } else {
            include_once dirname( __FILE__ ) . '/shipping/class-wc-shiptor-shipping-legacy.php'; //TODO: Если будет спрос для вукоммерца версии меньше чем 2.6 то опишем этот класс. Пока пусто.
        }

        //WC_Shiptor_Install::upgrade_300();
    }

    private static function admin_includes() {
        include_once dirname( __FILE__ ) . '/admin/class-wc-shiptor-admin-orders.php';
        include_once dirname( __FILE__ ) . '/admin/class-wc-shiptor-product-data.php';
        include_once dirname( __FILE__ ) . '/admin/class-wc-shiptor-admin-help.php';
		
		if ( ! empty( $_GET['page'] ) ) {
			switch ( $_GET['page'] ) {
				case 'shiptor-setup' :
					include_once( dirname( __FILE__ ) . '/admin/class-wc-shiptor-setup-wizard.php' );
				break;
			}
		}
    }

    public static function include_integrations( $integrations ) {
        return array_merge( $integrations, array( 'WC_Shiptor_Integration' ) );
    }

    public static function include_methods( $methods ) {

        $methods['shiptor-legacy'] = 'WC_Shiptor_Shipping_Legacy';

        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.6.0', '>=' ) ) {
            			
			$shiptor_methods = apply_filters( 'woocommerce_shiptor_shipping_methods_array', array(
				'shiptor-dpd'				=> 'WC_Shiptor_Shipping_DPD',
				'shiptor-dpd-ete'			=> 'WC_Shiptor_Shipping_DPD_ETE',
				'shiptor-iml'				=> 'WC_Shiptor_Shipping_IML',
				'shiptor-cdek'				=> 'WC_Shiptor_Shipping_CDEK',
				'shiptor-cdek-ete'			=> 'WC_Shiptor_Shipping_CDEK_ETE',
				'shiptor-aramex'			=> 'WC_Shiptor_Shipping_International',
				'shiptor-shiptor'			=> 'WC_Shiptor_Shipping_Shiptor',
				'shiptor-boxberry'			=> 'WC_Shiptor_Shipping_Boxberry',
				'shiptor-pickpoint'			=> 'WC_Shiptor_Shipping_Pickpoint',
				'shiptor-russian-post'		=> 'WC_Shiptor_Shipping_Russian_Post',
				'shiptor-shiptor-one-day'	=> 'WC_Shiptor_Shipping_Shiptor_One_Day'
			) );

            $old_options = get_option( 'woocommerce_shiptor_settings' );

            if ( empty( $old_options ) ) {
                unset( $methods['shiptor-legacy'] );
            }
			
			if( isset( $methods['shiptor-international'] ) ) {
				unset( $methods['shiptor-international'] );
			}
			
			$methods = array_merge( $methods, $shiptor_methods );
        }
        
		return apply_filters( 'woocommerce_shiptor_shipping_methods', $methods );
    }
	
	public static function check_allowed_methods( $methods ) {
		
		$new_methods = array();
		
		$transient_name = 'woocommerce_shiptor_allowed_methods';

		if( false === ( $allowed_methods = get_transient( $transient_name ) ) ) {
			
			$connect = new WC_Shiptor_Connect( 'allowed_methods' );
			$response = $connect->request( array( 'method' => 'getShippingMethods' ), false );
			
			if ( ! is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) ) {
				$result = json_decode( wp_remote_retrieve_body( $response ), true );
				if( isset( $result['result'] ) ) {
					$allowed_methods = $result['result'];
					set_transient( $transient_name, $allowed_methods, DAY_IN_SECONDS );
				}
			}
		}
		
		foreach( ( array ) $allowed_methods as $method ) {
			if( ! isset( $method['courier'] ) ) continue;
			
			if( in_array( $method['courier'], array( 'dpd', 'cdek' ) ) ) {
				$name = 'shiptor-' . $method['courier'] . '-ete';
				$new_methods[ $name ] = $methods[ $name ];
			}
			
			$index = 'shiptor-' . $method['courier'];
			
			if( in_array( $index, array_keys( $methods ) ) ) {
				$new_methods[ $index ] = $methods[ $index ];
			}
		}
		
		return $new_methods;
	}
	
	public static function payment_cod_via_card( $methods ) {
		if( class_exists( 'WC_Gateway_COD' ) ) {
			array_push( $methods, 'WC_Gateway_COD_Card' );
		}
		return $methods;
	}

    public static function include_emails( $emails ) {
        if ( ! isset( $emails['WC_Shiptor_Tracking_Email'] ) ) {
            $emails['WC_Shiptor_Tracking_Email'] = include dirname( __FILE__ ) . '/emails/class-wc-shiptor-tracking-email.php';
        }
        return $emails;
    }
	
	public static function plugin_row_meta( $links, $file ) {
		if ( $file === plugin_basename( WC_SHIPTOR_PLUGIN_FILE ) ) {
			$row_meta = array(
				'help' => '<a href="https://shiptor.ru/help/integration/woo#woocommerce-about" title="Посмотреть документацию" target="_blank">Документация</a>',
				'demo' => '<a href="http://woo.shiptor.ru" title="Посмотреть демо сайт">Демо</a>'
			);
			return array_merge( $links, $row_meta );
		}
		return (array) $links;
	}
	
	public static function plugin_action_links( $links ) {
		return array_merge( array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=integration' ) . '">Настройки</a>',
		), $links );
	}

    public static function woocommerce_missing_notice() {
        include_once dirname( __FILE__ ) . '/admin/views/html-admin-missing-dependencies.php';
    }

    public static function get_main_file() {
        return WC_SHIPTOR_PLUGIN_FILE;
    }

    public static function get_plugin_path() {
        return plugin_dir_path( WC_SHIPTOR_PLUGIN_FILE );
    }

    public static function get_templates_path() {
        return self::get_plugin_path() . 'templates/';
    }
}