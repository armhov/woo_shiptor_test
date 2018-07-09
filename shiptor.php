<?php
/**
 * Plugin Name: WooCommerce Shiptor
 * Plugin URI:  https://shiptor.ru/integration/modules/woo
 * Description: Добавляет методы доставки курьерской службы Shiptor в ваш магазин.
 * Author:      Максим Мартиросов
 * Author URI:  http://martirosoff.ru
 * Version:     1.0.6
 * Created by PhpStorm.
 * Date: 05.07.2017
 * Time: 14:00
 * Project: shiptor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WC_SHIPTOR_VERSION', '1.0.6' );

define( 'WC_SHIPTOR_PLUGIN_FILE', __FILE__ );	

if ( ! class_exists( 'WC_Shiptor' ) ) {
    include_once dirname( __FILE__ ) . '/includes/class-wc-shiptor.php';
    add_action( 'plugins_loaded', array( 'WC_Shiptor', 'init' ) );
}
