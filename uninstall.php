<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 27.11.2017
 * Time: 23:36
 * Project: shiptor-woo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}shiptor_cityes" );