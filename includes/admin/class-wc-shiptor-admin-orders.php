<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 27.11.2017
 * Time: 23:28
 * Project: shiptor-woo
 */

class WC_Shiptor_Admin_Order {
	
	private $connect;
	
	public function __construct() {
		
		$this->connect = new WC_Shiptor_Connect( 'admin_order' );
		$this->connect->set_debug( wc_shiptor_get_option( 'create_order_enable' ) );
	
		add_action( 'add_meta_boxes_shop_order', array( $this, 'register_metabox' ) );
		add_filter( 'woocommerce_resend_order_emails_available', array( $this, 'resend_tracking_code_email' ) );
		add_filter( 'woocommerce_order_actions', array( $this, 'resend_tracking_code_actions' ) );
		add_action( 'woocommerce_order_action_shiptor_tracking', array( $this, 'action_shiptor_tracking' ) );
		add_action( 'wp_ajax_woocommerce_shiptor_create_order', array( $this, 'create_order_ajax' ) );
		add_action( 'wp_ajax_shiptor_send_order', array( $this, 'create_single_order_ajax' ) );
		
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'shiptor_order_column' ) );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'tracking_code_orders_list' ), 100 );
			add_filter( 'bulk_actions-edit-shop_order', array( $this, 'define_bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_actions' ), 10, 3 );
			add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );
		}
		
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
		add_action( 'admin_print_styles', array( $this, 'orders_load_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'orders_load_scripts' ) );
	}
	
	public function register_metabox( $post ) {
		$order = wc_get_order( $post->ID );
		if( $this->shipping_is_shiptor( $order ) ) {
			$shiptor_id = $order->get_meta( '_shiptor_id' );
			if( $shiptor_id ) {
				add_meta_box( 'wc_shiptor_history', __( 'Shiptor order history', 'woocommerce-shiptor' ), array( $this, 'render_history' ), 'shop_order', 'side', 'default', array( 'shiptor_id' => $shiptor_id ) );
			}
			add_meta_box( 'wc_shiptor_order', sprintf( __( 'Shiptor order %s', 'woocommerce-shiptor' ), $order->get_order_number() ), array( $this, 'render_order' ), 'shop_order', 'normal', 'high' );
		}
	}
	
	public function render_history( $post, $meta ) {
		$order = wc_get_order( $post->ID );
		$transient_name = 'wc_shiptor_order_history_' . $meta['args']['shiptor_id'];
		if( false === ( $history = get_transient( $transient_name ) ) ) {
			$connect = $this->connect->get_package( $meta['args']['shiptor_id'] );
			if( $connect && isset( $connect['history'] ) ) {
				$history = $connect['history'];
				set_transient( $transient_name, $history, HOUR_IN_SECONDS );
			}
		}
		include( 'views/html-admin-order-history.php' );
	}
	
	public function render_order( $post ) {		
		$order = wc_get_order( $post->ID );
		if( $order->get_meta( '_shiptor_id' ) ) {
			$this->maybe_update_order_status( $order->get_meta( '_shiptor_id' ), $order );
			include( 'views/html-admin-edit-order.php' );
		} else {
			$shipping = current( $order->get_items( 'shipping' ) );
			$shiptor_method = $shipping->get_meta( 'shiptor_method' );
			$delivery_points = $shipping->get_meta( 'delivery_points' );		
			include( 'views/html-admin-create-order.php' );
		}
		
	}
	
	public function create_order_action( $data ) {
	
		$data = wp_parse_args( $data, array(
			'order_id'				=> 0,
			'is_fulfilment'			=> false,
			'no_gather'				=> false,
			'method_id'				=> 0,
			'courier'				=> '',
			'category'				=> '',
			'method_name'			=> '',
			'comment'				=> ''
		) );
		
		$order = wc_get_order( $data['order_id'] );
			
		if ( ! $order ) {
			throw new Exception( __( 'Invalid order', 'woocommerce-shiptor' ) );
		}
			
		$package = $products = array();
		$item_index = 0;
			
		foreach( $order->get_items( 'line_item' ) as $item ) {
			$product_id 	= $item->get_product_id();
			$variation_id 	= $item->get_variation_id();
			$product 		= wc_get_product( $variation_id ? $variation_id : $product_id );
				
			$package['contents'][$item_index]	= array(
				'data'		=> $product,
				'quantity'	=> $item->get_quantity()
			);
				
			$_article = get_post_meta( $product->get_id(), '_article', true );
				
			$article = ! empty( $_article ) ? esc_attr( $_article ) : ( $product->get_sku() ? $product->get_sku() : $product->get_id() );
				
			$products[$item_index] = array(
				'shopArticle'	=> $article,
				'count'			=> $item->get_quantity(),
				'price'			=> $product->get_price()
			);
				
			if( ! in_array( $order->get_billing_country(), array( 'RU', 'BY', 'KZ' ) ) ) {
				$products[$item_index]['englishName'] = $product->get_meta( '_eng_name' ) ? $product->get_meta( '_eng_name' ) : sanitize_title( $product->get_name() );
			}
				
			$get_products = $this->connect->get_products( $article );
				
			if( ! $get_products ) {
					
				if( $this->connect->add_product( $product ) ) {
					update_post_meta( $product->get_id(), '_added_shiptor', time() );
				}
				
			} else {
					
				$get_product = wp_list_filter( $get_products, array( 'shopArticle' => $article ) );
				$get_product = current( $get_product );
					
				if( ! empty( $get_product ) && isset( $get_product['fulfilment']['total'], $get_product['fulfilment']['waiting'] ) ) {
					update_post_meta( $product->get_id(), '_fulfilment_total', $get_product['fulfilment']['total'] );
					update_post_meta( $product->get_id(), '_fulfilment_waiting', $get_product['fulfilment']['waiting'] );
				}	
			}
				
			$item_index++;
		}
			
		$cost = $order->has_status( array( 'pending', 'processing', 'failed' ) ) ? ( $order->get_billing_country() == 'RU' ? $order->get_total() : 0 ) : 0;
			
		$atts = array(
			'external_id'	=> $order->get_order_number(),
			'is_fulfilment'	=> isset( $data['is_fulfilment'] ) && wc_string_to_bool( $data['is_fulfilment'] ),
			'no_gather'		=> isset( $data['no_gather'] ) && wc_string_to_bool( $data['no_gather'] ),
			'departure'		=> array(
				'shipping_method'	=> intval( $data['method_id'] ),
				'address'			=> array(
					'country'				=> $order->get_billing_country(),
					'receiver'				=> $order->get_formatted_billing_full_name(),
					'email'					=> $order->get_billing_email(),
					'phone'					=> $order->get_billing_phone(),
					'settlement'			=> $order->get_billing_city(),
					'administrative_area'	=> $order->get_billing_state()
				)
			),
			'products'		=> $products
		);
			
		if( in_array( $order->get_billing_country(), array( 'RU', 'BY', 'KZ' ) ) ) {
				
			$atts['declared_cost'] = $data['declared_cost'] < 10 ? 10 : $data['declared_cost'];
				
			$atts['departure']['address']['name'] = $order->get_billing_first_name();
			$atts['departure']['address']['surname'] = $order->get_billing_last_name();
			$atts['departure']['address']['kladr_id'] = $order->get_meta( '_billing_kladr_id' );
			$atts['cod'] = $cost;
				
			if( $cost > 0 ) {					
					
				if( isset( $data['cashless_payment'] ) ) {
					$atts['departure']['cashless_payment'] = wc_string_to_bool( $data['cashless_payment'] );
				}
					
				$service_id = sprintf( 'shipping_%s_%s', $data['courier'], $data['category'] );
				$found = false;
				$get_services = $this->connect->get_service();
					
				if( $get_services && isset( $get_services['services'] ) ) {
					$found_service = wp_list_filter( $get_services['services'], array( 'shop_article' => $service_id ) );
					if( ! empty( $found_service ) ) {
						$found = true;
					}
				}
					
				if( ! $found ) {
					$add_service = $this->connect->add_service( sprintf( __( 'Shipping via %s', 'woocommerce' ), $data['method_name'] ), $service_id );
					if( $add_service && isset( $add_service['shop_article'] ) && $add_service['shop_article'] == $service_id ) {
						$found = true;
					}
				}
					
				if( $found ) {
					$atts['services']	= array(
						array(
							'shopArticle'	=> $service_id,
							'count'			=> 1,
							'price'			=> $order->get_shipping_total()
						)
					);
				}
					
			}
				
		}
			
		if( ! in_array( $order->get_billing_country(), array( 'RU', 'BY', 'KZ' ) ) ) {
			$atts['departure']['address']['postal_code'] = $order->get_billing_postcode();
			$atts['departure']['address']['address_line_1'] = $order->get_billing_address_1();
		}
			
		if( isset( $data['chosen_delivery_point'] ) ) {
			$atts['departure']['delivery_point'] = intval( $data['chosen_delivery_point'] );
			if( $data['chosen_delivery_point'] !== $order->get_meta( '_chosen_delivery_point' ) ) {
				$order->update_meta_data( '_chosen_delivery_point', intval( $data['chosen_delivery_point'] ) );
			}
		}
			
		if( ! empty( $data['comment'] ) ) {
			$atts['departure']['comment'] = esc_html( $data['comment'] );
			$order->add_order_note( esc_html( $data['comment'] ), false, true );
		}
			
		$shipment_type = 'standard';
			
		switch( $data['category'] ) {
			case 'delivery-point-to-delivery-point' :
			case 'delivery-point-to-door' :
				$shipment_type = 'delivery-point';
				break;
			case 'door-to-door'	:
			case 'door-to-delivery-point' :
				$shipment_type = 'courier';
				break;
			default :
				$shipment_type = 'standard';
				break;
		}
			
		$shipment = array(
			'type'	=> $shipment_type
		);
			
		if( in_array( $shipment_type, array( 'courier', 'delivery-point' ) ) ) {
			$shipment['courier'] = $data['courier'];
			$shipment['address'] = array(
				'receiver'	=> $data['sender_name'],
				'email'		=> $data['sender_email'],
				'phone'		=> $data['sender_phone'],
				'country'	=> WC()->countries->get_base_country(),
				'kladr_id'	=> $data['sender_city']
			);
			$shipment['date'] = date( 'd.m.Y', strtotime( $data['sender_order_date'] ) );
		}
			
		if( 'delivery-point' == $shipment_type ) {
			$shipment['delivery_point'] = intval( $data['sender_delivery_point'] );
		} elseif( in_array( $shipment_type, array( 'courier', 'standard' ) ) ) {
			if( isset( $data['sender_address'] ) ) {
				$shipment['address']['street'] = $data['sender_address'];
			}
			if( isset( $data['address_line'] ) ) {
				$atts['departure']['address']['address_line_1'] = $data['address_line'];
			}
				
		}
			
		$is_export = 'aramex' === $data['courier'];
			
		$this->connect->set_package( $package );
		$result = $this->connect->add_packages( $atts, $shipment, $is_export );
			
		if( ( $is_export && isset( $result['result'] ) ) || ( ! $is_export && isset( $result['result']['packages'] ) ) ) {
			$package = $is_export ? $result['result'] : current( $result['result']['packages'] );
			$order->update_meta_data( '_shiptor_id', intval( $package['id'] ) );
			$order->update_meta_data( '_shiptor_status', $package['status'] );
			$order->update_meta_data( '_shiptor_label_url', $package['label_url'] );
			if( isset( $result['result']['tracking_number'] ) ) {
				wc_shiptor_update_tracking_code( $order, $package['tracking_number'] );
			} else {
				$order->save();
			}
				
			$this->maybe_update_order_status( intval( $package['id'] ), $order, true );
			
		} elseif( isset( $result['error'] ) ) {
			throw new Exception( $result['error'] );
		} else {
			throw new Exception( __( 'Can not create an order', 'woocommerce-shiptor' ) );
		}
	}
	
	public function create_order_ajax() {
		check_ajax_referer( 'woocommerce-shiptor-create-order', 'security' );
		
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}
		
		try {
			
			if( ! isset( $_POST['data'] ) ) {
				throw new Exception( __( 'Missing parameters', 'woocommerce-shiptor' ) );
			}
			
			wp_parse_str( $_POST['data'], $data );
			
			$this->create_order_action( $data );
			
			
			$order = wc_get_order( $data['order_id'] );
			
			ob_start();
			
			include( 'views/html-admin-edit-order.php' );
				
			wp_send_json_success( array(
				'html' => ob_get_clean(),
			) );
		
		} catch( Exception $e ) {
			wp_send_json_error( array( 'error' => $e->getMessage() ) );
		}
		
	}
	
	private function maybe_update_order_status( $shiptor_id = 0, $order, $force = false ) {
		$transient_name = 'wc_shiptor_order_status_' . $shiptor_id;
		if( false === ( $package = get_transient( $transient_name ) ) || true === $force ) {
			$connect = $this->connect->get_package( $shiptor_id );
			if( $connect && isset( $connect['status'] ) ) {
				$package = $connect;
				set_transient( $transient_name, $package, HOUR_IN_SECONDS );
				if( is_a( $order, 'WC_Order' ) ) {
					$order->update_meta_data( '_shiptor_status', $package['status'] );
					$order->update_meta_data( '_shiptor_label_url', $package['label_url'] );
					if( isset( $package['tracking_number'] ) && $package['tracking_number'] !== $order->get_meta( '_shiptor_tracking_code' ) ) {
						wc_shiptor_update_tracking_code( $order, $package['tracking_number'] );
					} else {
						$order->save();
					}
				}
			}
		}
	}
	
	private function shipping_is_shiptor( $order ) {
		$is_shiptor = false;
		$order = wc_get_order( $order );
		$shippings = $order->get_items( 'shipping' );
		foreach( $shippings as $shipping ) {
			$shiptor_method = $shipping->get_meta( 'shiptor_method' );
			if( ! empty( $shiptor_method ) ) {
				$is_shiptor = true;
				break;
			}
		}
		return $is_shiptor;
	}
	
	public function resend_tracking_code_email( $emails ) {
		return array_merge( $emails, array( 'shiptor_tracking' ) );
	}
	
	public function resend_tracking_code_actions( $emails ) {
		$emails['shiptor_tracking'] = __( 'Send shiptor tracking code', 'woocommerce-shiptor' );
		return $emails;
	}
	
	public function action_shiptor_tracking( $order ) {
		WC()->mailer()->emails['WC_Shiptor_Tracking_Email']->trigger( $order->get_id(), $order, wc_shiptor_get_tracking_codes( $order ) );
	}
	
	public function shiptor_order_column( $columns ) {
		
		$new_columns = array();

		foreach ( $columns as $column_name => $column_info ) {

			$new_columns[ $column_name ] = $column_info;

			if ( 'order_status' === $column_name ) {
				$new_columns['shiptor'] = __( 'Shiptor', 'woocommerce-shiptor' );
			}
		}

		return $new_columns;
	}
	
	public function tracking_code_orders_list( $column ) {
		global $post, $the_order;
		
		if ( 'shiptor' === $column ) {
			if ( empty( $the_order ) || $the_order->get_id() !== $post->ID ) {
				$the_order = wc_get_order( $post->ID );
			}
			
			$shipping = current( $the_order->get_items( 'shipping' ) );
			
			if( $shipping && method_exists( $shipping, 'get_meta' ) ) {
				$shiptor_method = $shipping->get_meta( 'shiptor_method' );
				
				if( $tracking_code = $the_order->get_meta( '_shiptor_tracking_code' ) ) {				
					include( 'views/html-list-table-order.php' );
				} else {
					include( 'views/html-list-table-create-order.php' );
				}
			} else {
				_e( 'N/A', 'woocommerce' );
			}
			
		}
		
	}
	
	public function define_bulk_actions( $action ) {
		$actions['send_orders'] = 'Отправить выбранные заказы';
		
		return $actions;
	}
	
	public function handle_bulk_actions( $redirect_to, $action, $ids ) {
		if ( 'send_orders' !== $action ) {
			return $redirect_to;
		}
		
		$changed = 0;
		$ids     = array_map( 'absint', $ids );
		
		foreach ( $ids as $id ) {
			
			$order = wc_get_order( $id );
			$shipping = current( $order->get_items( 'shipping' ) );
			$shiptor_method = $shipping->get_meta( 'shiptor_method' );
			$delivery_points = $shipping->get_meta( 'delivery_points' );
			$shiptor_id = $order->get_meta( '_shiptor_id' );
			
			if( ! empty( $shiptor_id ) || 0 === strpos( $shiptor_method['category'], 'delivery-point-to-' ) ) {
				continue;
			}
			
			$data = array(
				'order_id'				=> absint( $id ),
				'is_fulfilment'			=> true,
				'no_gather'				=> false,
				'method_id'				=> $shiptor_method['id'],
				'courier'				=> $shiptor_method['courier'],
				'category'				=> $shiptor_method['category'],
				'method_name'			=> $shiptor_method['name'],
				'comment'				=> $order->get_customer_note( 'edit' ),
				'address_line'			=> $order->get_billing_address_1()
			);
			
			if( in_array( $order->get_billing_country(), array( 'RU', 'BY', 'KZ' ) ) ) {
				$data['declared_cost'] = isset( $shiptor_method['declared_cost'] ) && $shiptor_method['declared_cost'] > 10 ? $order->get_total() : 10;
			}
			
			if( $order->get_billing_country() === 'RU' ) {
				$data['cashless_payment'] = $order->get_payment_method() == 'cod_card';
			}
			
			if( ! empty( $delivery_points ) ) {
				$data['chosen_delivery_point'] = $order->get_meta( '_chosen_delivery_point' );
			}
			
			if( 0 === strpos( $shiptor_method['category'], 'door-to-' ) ) {
				$data['sender_name'] = $shiptor_method['sender_name'];
				$data['sender_email'] = $shiptor_method['sender_email'];
				$data['sender_phone'] = $shiptor_method['sender_phone'];
				
				if( isset( $shiptor_method['sender_city'] ) ) {
					$data['sender_city'] = $shiptor_method['sender_city'];
				}
				
				if( isset( $shiptor_method['sender_address'] ) ) {
					$data['sender_address'] = $shiptor_method['sender_address'];
				}
				
				$data['sender_order_date'] = date( 'Y-m-d', strtotime( '+1days' ) + wc_timezone_offset() );
			}
			
			try {
				$this->create_order_action( $data );
				$changed++;
			} catch( Exception $e ) {
			
			}
		}
		
		$redirect_to = add_query_arg(
			array(
				'post_type'    => 'shop_order',
				'sended_order' => true,
				'changed'      => $changed,
				'count'		   => count( $ids ),
				'ids'          => join( ',', $ids ),
			), remove_query_arg( array( 'after_send_order' ), $redirect_to )
		);

		return esc_url_raw( $redirect_to );
	}
	
	public function admin_init() {
		if ( ! isset( $_GET['after_send_order'] ) && WC_Admin_Notices::has_notice( 'send_order_error' ) ) {
			WC_Admin_Notices::remove_notice( 'send_order_error' );
		}
	}
	
	public function create_single_order_ajax() {
		
		if ( current_user_can( 'edit_shop_orders' ) && check_admin_referer( 'shiptor-send-order' ) ) {
			
			$order  = wc_get_order( absint( $_GET['order_id'] ) );
			$shipping = current( $order->get_items( 'shipping' ) );
			$shiptor_method = $shipping->get_meta( 'shiptor_method' );
			$delivery_points = $shipping->get_meta( 'delivery_points' );
			
			$data = array(
				'order_id'				=> $order->get_id(),
				'is_fulfilment'			=> true,
				'no_gather'				=> false,
				'method_id'				=> $shiptor_method['id'],
				'courier'				=> $shiptor_method['courier'],
				'category'				=> $shiptor_method['category'],
				'method_name'			=> $shiptor_method['name'],
				'comment'				=> $order->get_customer_note( 'edit' ),
				'address_line'			=> $order->get_billing_address_1()
			);
			
			if( in_array( $order->get_billing_country(), array( 'RU', 'BY', 'KZ' ) ) ) {
				$data['declared_cost'] = isset( $shiptor_method['declared_cost'] ) && $shiptor_method['declared_cost'] > 10 ? $order->get_total() : 10;
			}
			
			if( $order->get_billing_country() === 'RU' ) {
				$data['cashless_payment'] = $order->get_payment_method() == 'cod_card';
			}
			
			if( ! empty( $delivery_points ) ) {
				$data['chosen_delivery_point'] = $order->get_meta( '_chosen_delivery_point' );
			}
			
			if( 0 === strpos( $shiptor_method['category'], 'door-to-' ) ) {
				$data['sender_name'] = $shiptor_method['sender_name'];
				$data['sender_email'] = $shiptor_method['sender_email'];
				$data['sender_phone'] = $shiptor_method['sender_phone'];
				
				if( isset( $shiptor_method['sender_city'] ) ) {
					$data['sender_city'] = $shiptor_method['sender_city'];
				}
				
				if( isset( $shiptor_method['sender_address'] ) ) {
					$data['sender_address'] = $shiptor_method['sender_address'];
				}
				
				$data['sender_order_date'] = date( 'Y-m-d', strtotime( '+1days' ) + wc_timezone_offset() );
			}
			
			try {
				$this->create_order_action( $data );
			} catch( Exception $e ) {
				WC_Admin_Notices::add_custom_notice(
					'send_order_error',
					sprintf(
						'Во время отправки заказа %1$s произошла ошибка: (%2$s). <a href="%3$s">Перейдите в заказ что бы отправить его.</a>',
						$order->get_order_number(),
						$e->getMessage(),
						esc_url( admin_url( 'post.php?post=' . absint( $order->get_id() ) ) . '&action=edit' )
					)
				);
				
				wp_safe_redirect( admin_url( 'edit.php?post_type=shop_order&after_send_order=true' ) );
				exit;
			}			
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		exit;
	}
	
	public function bulk_admin_notices() {
		global $post_type, $pagenow;
		
		if ( 'edit.php' !== $pagenow || 'shop_order' !== $post_type ) {
			return;
		}
		
		if ( isset( $_REQUEST[ 'sended_order' ] ) ) {
			$classes = array( 'notice', 'is-dismissible' );
			$message = '';
			$number = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;
			$count = isset( $_REQUEST['count'] ) ? absint( $_REQUEST['count'] ) : 0;
			
			if( 0 == $number ) {
				$classes[] = 'notice-error';
				$message = sprintf( 'Из %d ниодин заказ не был отправлен. Убедитесь что выбранные вами заказы не были отправлены ранее или у заказов не заполнены обязательные поля.', $count );
			} elseif( $count == $number ) {
				$classes[] = 'notice-success';
				$message = sprintf( _n( '%d заказ из %d был отправлен.', '%d заказа(ов) из %d были отправлены.', $number, 'woocommerce' ), number_format_i18n( $number ), number_format_i18n( $count ) );
			} else {
				$classes[] = 'notice-warning';
				$message = sprintf( '%d из %d заказа(ов) были отправлены. Возможно %d заказа(ов) не были отправлены по причине того, что они были отправлены ранее или у них не зыполнены обязательные поля.', $number, $count, $count - $number );
			}
			
			echo '<div class="' . implode( ' ', $classes ) . '"><p>' . esc_html( $message ) . '</p></div>';
		}
	}
	
	public function orders_load_style() {
		
		$screen = get_current_screen();
		
		if ( in_array( $screen->id, array( 'shop_order', 'edit-shop_order' ) ) ) {
			wp_enqueue_style( 'woocommerce-shiptor-orders', plugins_url( 'assets/admin/css/orders.css', WC_SHIPTOR_PLUGIN_FILE ), null, WC_SHIPTOR_VERSION );
		}
	}
	
	public function orders_load_scripts() {
		$screen = get_current_screen();
		
		if ( isset( $screen->id ) && in_array( $screen->id, array( 'shop_order', 'edit-shop_order' ) ) ) {
			wp_enqueue_script( 'woocommerce-shiptor-orders', plugins_url( 'assets/admin/js/orders.js', WC_SHIPTOR_PLUGIN_FILE ), array( 'wp-api', 'jquery-blockui', 'woocommerce_admin' ), WC_SHIPTOR_VERSION, true );
			wp_localize_script( 'woocommerce-shiptor-orders', 'shiptor_order_params', array(
				'nonces' => array(
					'cleate'	=> wp_create_nonce( 'woocommerce-shiptor-create-order' )
				)
			) );
		}
	}
}

new WC_Shiptor_Admin_Order();