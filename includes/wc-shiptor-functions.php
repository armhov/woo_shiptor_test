<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 01.11.2017
 * Time: 15:44
 * Project: shiptor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wc_shiptor_get_customer_kladr() {
	
	$customer_id = WC()->customer->get_id();
	$kladr = wc_shiptor_get_option( 'city_origin' );
	$session_kladr_id = WC()->session->get( 'billing_kladr_id', null );
	
	if( $customer_id > 0 ) {
		
		$customer = new WC_Customer( $customer_id );
		
		if( $customer->meta_exists( 'billing_kladr_id' ) ) {
			$kladr_id = $customer->get_meta( 'billing_kladr_id' );
			
			if( ! is_null( $session_kladr_id ) && $kladr_id !== $session_kladr_id ) {
				$kladr_id = $session_kladr_id;
				$customer->update_meta_data( 'billing_kladr_id', $kladr_id );
				$customer->save();
			}
			
			if( ! empty( $kladr_id ) ) {
				$kladr = $kladr_id;
			}
		}
	
	} elseif( ! empty( $session_kladr_id ) ) {
		$kladr = $session_kladr_id;
	}
	
	return $kladr;
}

function wc_shiptor_set_customer_kladr( $kladr_id = 0 ) {
	
	$kladr_id = wc_clean( $kladr_id );
	
	WC()->session->set( 'billing_kladr_id', $kladr_id );
		
	$customer_id = WC()->customer->get_id();
		
	if( $customer_id > 0 ) {
		$customer = new WC_Customer( $customer_id );
		$customer->update_meta_data( 'billing_kladr_id', $kladr_id );
		$customer->save();
	}
}

/**
 * Добавляет текст "количество дней доставки" к названию метода доставки
 *
 * @param $name - Название метода в чистом виде
 * @param $days - количество дней
 * @param int $additional_days - количество добавочных дней
 *
 * @return mixed
 */
function wc_shiptor_get_estimating_delivery( $name, $days, $additional_days = 0 ) {
	
	if( false !== strpos( '-', $days ) ) {
		$periods = explode( '-', $days );
		$total = intval( $periods[1] ) + intval( $additional_days );
	} else {
		$total = intval( $days ) + intval( $additional_days );
	}
	
	$additional_days = wc_shiptor_get_working_days( intval( $total ) );
	
    if ( $additional_days > 0 ) {
        $name .= ' (' . sprintf( _n( '%s day', '%s days', $additional_days ), $additional_days ) . ')';
    }
    return apply_filters( 'woocommerce_shiptor_get_estimating_delivery', $name, $days, $additional_days );
}


/**
 * Возвращает количество надбавочных дней к доставке с учётом не рабочих дней компании.
 *
 * @param int $additional_days - Количество дней которые нужно прибавить
 *
 * @return int
 */
function wc_shiptor_get_working_days( $additional_days = 0 ) {
	
	$connect = new WC_Shiptor_Connect( 'get_working_days' );
	$days_off = $connect->get_days_off();
	
	for( $i = 1; $i <= $additional_days; $i++ ){
		$current = date( 'Y-m-d', strtotime( "+{$i}day" ) );
		if( in_array( $current, $days_off ) ) {
			$additional_days++;
        }
	}
	
	return $additional_days;
}

function wc_shiptor_get_shipping_delivery_time( $days = '', $additional_days = 0 ) {
	
	$total = 0;
	
	if( false !== strpos( '-', $days ) ) {
		$periods = explode( '-', $days );
		$total = intval( $periods[1] ) + intval( $additional_days );
	} else {
		$total = intval( $days ) + intval( $additional_days );
	}
	
	$additional_days = wc_shiptor_get_working_days( intval( $total ) );
	
	return time() + ( DAY_IN_SECONDS * $additional_days );
}

function wc_shiptor_get_option( $option_name, $default = null ) {
	$settings = get_option( 'woocommerce_shiptor-integration_settings' );
	if( $option_name && isset( $settings[ $option_name ] ) ) {
		return $settings[ $option_name ];
	}

	return $default;
}

/**
 * Форматирование строки стоимости доставки
 *
 * @param $value
 *
 * @return mixed
 */
function wc_shiptor_normalize_price( $value ) {
    $value = str_replace( '.', '', $value );
    $value = str_replace( ',', '.', $value );
    return $value;
}

/**
 * Возвращает ошибку по её номеру
 *
 * @param $code - код ошибки
 *
 * @return string
 */
function wc_shiptor_get_error_message( $code ) {
    $code = (string) $code;
    $messages = apply_filters( 'woocommerce_shiptor_available_error_messages', array() ); //TODO: Добавить коды ошибок с их описанием.
    return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
}

/**
 * Вешает событие на WC_Mailer для отправки трекинг-кода на емайл пользователя
 *
 * @param $order - Номер заказа
 * @param $tracking_code - Трекинг-код
 */
function wc_shiptor_trigger_tracking_code_email( $order, $tracking_code ) {
    $mailer       = WC()->mailer();
    $notification = $mailer->emails['WC_Shiptor_Tracking_Email'];
    if ( 'yes' === $notification->enabled ) {
        if ( method_exists( $order, 'get_id' ) ) {
            $notification->trigger( $order->get_id(), $order, $tracking_code );
        } else {
            $notification->trigger( $order->id, $order, $tracking_code );
        }
    }
}

/**
 * Возвращает все трекинги по заказу
 *
 * @param $order - номер заказа
 * @return array
 */
function wc_shiptor_get_tracking_codes( $order ) {
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }
    if ( method_exists( $order, 'get_meta' ) ) {
        $code = $order->get_meta( '_shiptor_tracking_code' );
    } else {
        $code = $order->shiptor_tracking_code;
    }
    return $code;
}
/**
 * Обновляет трекинг код.
 *
 * @param  WC_Order|int $order         ID заказа или дата.
 * @param  string       $tracking_code Трекинг код.
 * @param  bool         $remove        Если передать true то удалит указанный трекинг.
 *
 * @return bool
 */
function wc_shiptor_update_tracking_code( $order, $tracking_code, $remove = false ) {

    $tracking_code = sanitize_text_field( $tracking_code );

    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }

    if ( '' === $tracking_code ) {
        if ( method_exists( $order, 'delete_meta_data' ) ) {
            $order->delete_meta_data( '_shiptor_tracking_code' );
            $order->save();
        } else {
            delete_post_meta( $order->id, '_shiptor_tracking_code' );
        }

        return true;

    } elseif ( ! $remove && ! empty( $tracking_code ) ) {
        if ( method_exists( $order, 'update_meta_data' ) ) {
            $order->update_meta_data( '_shiptor_tracking_code', $tracking_code );
            $order->save();
        } else {
            update_post_meta( $order->id, '_shiptor_tracking_code', $tracking_code );
        }

        $order->add_order_note( sprintf( __( 'Added a Shiptor tracking code: %s', 'woocommerce-shiptor' ), $tracking_code ) );

        wc_shiptor_trigger_tracking_code_email( $order, $tracking_code );

        return true;

    } elseif ( $remove ) {
        
		if ( method_exists( $order, 'delete_meta_data' ) ) {
            $order->delete_meta_data( '_shiptor_tracking_code' );
            $order->save();
        } else {
            delete_post_meta( $order->id, '_shiptor_tracking_code' );
        }

        $order->add_order_note( sprintf( __( 'Removed a Shiptor tracking code: %s', 'shiptor-shiptor' ), $tracking_code ) );
        return true;
    }
    return false;
}
/**
 * Возвращает список НП по части названия города.
 *
 * @param string $city_name.
 *
 * @return array
 */
function wc_shiptor_get_address_by_name( $city_name, $country = 'RU' ) {
    return WC_Shiptor_Autofill_Addresses::get_address( $city_name, $country );
}
/**
 * Возвращает массив строковых идентификаторов курьерских служб в системе Shiptor.
 * @return array
 */
function wc_shiptor_get_couriers() {
	return apply_filters( 'woocommerce_shiptor_couriers', array(
		'shiptor',
		'boxberry',
		'dpd',
		'iml',
		'russian-post',
		'pickpoint',
		'cdek',
		'shiptor-one-day'
	) );
}

function wc_shiptor_statuses() {
	return apply_filters( 'wc_shiptor_statuses', array(
		'new'					=> __( 'New', 'woocommerce-shiptor' ),
		'checking-declaration'	=> __( 'Check declaration', 'woocommerce-shiptor' ),
		'declaration-checked'	=> __( 'Declaration Verified', 'woocommerce-shiptor' ),
		'waiting-pickup'		=> __( 'Waiting for pick-up', 'woocommerce-shiptor' ),
		'arrived-to-warehouse'	=> __( 'Arrived at the warehouse', 'woocommerce-shiptor' ),
		'packed'				=> __( 'Packed', 'woocommerce-shiptor' ),
		'prepared-to-send'		=> __( 'Prepared to send', 'woocommerce-shiptor' ),
		'sent'					=> __( 'Sent', 'woocommerce-shiptor' ),
		'delivered'				=> __( 'Delivered', 'woocommerce-shiptor' ),
		'removed'				=> __( 'Removed', 'woocommerce-shiptor' ),
		'recycled'				=> __( 'Recycled', 'woocommerce-shiptor' ),
		'returned'				=> __( 'Waiting in line for return', 'woocommerce-shiptor' ),
		'reported'				=> __( 'Returned to the sender', 'woocommerce-shiptor' ),
		'lost'					=> __( 'Lost', 'woocommerce-shiptor' ),
		'resend'				=> __( 'Resubmitted', 'woocommerce-shiptor' ),
		'waiting-on-delivery-point' => __( 'Waiting at the delivery point', 'woocommerce-shiptor' )
	) );
}

function wc_shiptor_get_status( $key ) {
	$statuses = wc_shiptor_statuses();
	if( in_array( $key, array_keys( $statuses ) ) ) {
		return $statuses[ $key ];
	}
	
	return __( 'N/A', 'woocommerce' );
}

function wc_shiptor_chosen_shipping_rates() {
	
	$rates = array();
	
	if( ! is_admin() ) {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		
		foreach ( WC()->shipping->get_packages() as $i => $package ) {
			if ( ! isset( $chosen_shipping_methods[ $i ], $package['rates'][ $chosen_shipping_methods[ $i ] ] ) ) continue;
			$rate = $package['rates'][ $chosen_shipping_methods[ $i ] ];
			$rates[ $rate->get_method_id() ] = $rate;
		}
	}	
	
	return $rates;
}

add_action( 'shiptor_update_order_statuses', 'wc_shiptor_update_order_statuses_action' );
function wc_shiptor_update_order_statuses_action() {
	
	$orders = wc_get_orders( array(
		'limit'		=> -1,
		'status' 	=> array( 'pending', 'processing', 'on-hold' ),
		'shiptor'	=> true
	) );
		
	$connect = new WC_Shiptor_Connect( 'order_status' );
	$connect->set_debug( 'yes' );
		
	foreach( $orders as $order ) {
		if( ! is_a( $order, 'WC_Order' ) ) {
			continue;
		}
			
		$package = $connect->get_package( $order->get_meta( '_shiptor_id' ) );
			
		if( is_array( $package ) ) {
			if( $package['status'] !== $order->get_meta( '_shiptor_status' ) ) {
				if( 'delivered' == $package['status'] ) {
					$order->set_status( 'completed' );
				}
				$order->update_meta_data( '_shiptor_status', $package['status'] );
				$order->save();
			}
		}
	}
}

function wc_handle_shiptor_query_var( $query, $query_vars ) {
	if ( ! empty( $query_vars['shiptor'] ) ) {
		$query['meta_query'][] = array(
			'key' => '_shiptor_id'
		);
	}

	return $query;
}
add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'wc_handle_shiptor_query_var', 10, 2 );

function wc_shiptor_has_virtual_product_package( $package ) {
	
	foreach ( $package['contents'] as $item_id => $product ) {
		if( $product['data']->is_virtual() ) {
			return true;
		}
	}
	return false;
}

add_filter( 'wc_add_to_cart_message_html', 'wc_shiptor_add_to_cart_virtual_product_message_html', 10, 2 );
function wc_shiptor_add_to_cart_virtual_product_message_html( $message, $products ) {
	$added_text = '';
	foreach( ( array ) $products as $product_id => $qty ) {
		$product = wc_get_product( $product_id );
		if( $product->is_virtual() ) {
			$added_text = sprintf( __( '"%s" is a <strong>virtual item</strong>. It will not be possible to place it as one order together with ordinary items.', 'woocommerce-shiptor' ), strip_tags( get_the_title( $product_id ) ) );
		}
	}
	
	if( ! empty( $added_text ) ) {
		$message = $message . sprintf( '<p style="margin-bottom:0;">%s</p>', $added_text );
	}
	return $message;
}

function wc_shiptor_find_mixed_product_type_in_cart() {
	
	if ( ! WC()->cart->is_empty() ) {
		$has_virtual = $has_simple = array();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if( $cart_item['data']->is_virtual() ) {
				$has_virtual[] = $cart_item['product_id'];
			} else {
				$has_simple[] = $cart_item['product_id'];
			}
		}
		
		return ( count( $has_virtual ) > 0 && count( $has_simple ) > 0 ) === true;
	}
	return false;
}

add_action( 'woocommerce_after_checkout_validation', 'wc_shiptor_after_checkout_validation', 10, 2 );
add_action( 'woocommerce_check_cart_items', 'wc_shiptor_add_notice_before_checkout_form', 10 );

function wc_shiptor_add_notice_before_checkout_form() {
	if( wc_shiptor_find_mixed_product_type_in_cart() ) {
		wc_print_notice( __( 'There are items in the basket which do not require delivery. Please make them as separate orders.', 'woocommerce-shiptor' ), 'error' );
	}
}

function wc_shiptor_after_checkout_validation( $data, $errors ) {
	if( wc_shiptor_find_mixed_product_type_in_cart() ) {
		$errors->add( 'mixed_product_in_cart', __( 'You cannot continue placing your order because there are "miscellaneous" items in your basket. Please delete either real or virtual items from the basket and continue placing the order.', 'woocommerce-shiptor' ) );
	}
}