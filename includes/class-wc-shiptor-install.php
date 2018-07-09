<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 28.11.2017
 * Time: 0:00
 * Project: shiptor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Shiptor_Install {

    private static function get_version() {
        return get_option( 'woocommerce_shiptor_version' );
    }

    private static function update_version() {
        update_option( 'woocommerce_shiptor_version', WC_SHIPTOR_VERSION );
    }

    public static function upgrade_300() {
        global $wpdb;
        $version = self::get_version();
        if ( empty( $version ) ) {
            $wpdb->query( "UPDATE $wpdb->postmeta SET meta_key = '_shiptor_tracking_code' WHERE meta_key = 'shiptor_tracking';" );
        }
    }

    public static function upgrade_300_from_wc_260() {
        $old_options = get_option( 'woocommerce_shiptor_settings' );
        if ( $old_options ) {
            if ( isset( $old_options['tracking_history'] ) ) {
                $integration_options = get_option( 'woocommerce_shiptor-integration_settings', array(
                    'general_options' => '',
                    'tracking'        => '',
                    'enable_tracking' => 'no',
                    'tracking_debug'  => 'no',
                ) );

                $integration_options['enable_tracking'] = $old_options['tracking_history'];
                update_option( 'woocommerce_shiptor-integration_settings', $integration_options );

                unset( $old_options['tracking_history'] );
                update_option( 'woocommerce_shiptor_settings', $old_options );
            }
            if ( 'no' === $old_options['enabled'] ) {
                delete_option( 'woocommerce_shiptor_settings' );
            }
        }
    }
}