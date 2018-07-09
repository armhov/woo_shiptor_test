<?php
/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 28.11.2017
 * Time: 1:02
 * Project: shiptor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class WC_Shiptor_Shipping extends WC_Shipping_Method {

	public $available_rates;
    
	protected $service;
	
	protected $is_ete = false;

    public function __construct( $instance_id = 0 ) {
        $this->instance_id        = absint( $instance_id );
        $this->method_description = $this->method_description ? $this->method_description : sprintf( __( '%s is a shipping method from Shiptor.', 'woocommerce-shiptor' ), $this->method_title );
		$this->has_settings       = false;
        $this->supports           = array(
            'zones',
			'shipping-zones',
            'instance-settings'
        );
        // Load the form fields.
		$this->init_form_fields();
        // Define user set variables.
        $this->title              						= $this->get_option( 'title' );
        $this->shipping_class_id  						= (int) $this->get_option( 'shipping_class_id', '-1' );
        $this->show_delivery_time 						= $this->get_option( 'show_delivery_time' );
        $this->additional_time    						= $this->get_option( 'additional_time' );
        $this->fee                						= $this->get_option( 'fee' );
        $this->fee_type           						= $this->get_option( 'fee_type' );
        $this->min_cost           						= $this->get_option( 'min_cost' );
        $this->free           	  						= $this->get_option( 'free' );
        $this->fix_cost           						= $this->get_option( 'fix_cost' );
        $this->enable_declared_cost           			= $this->get_option( 'enable_declared_cost' );
        $this->allowed_group 							= $this->get_option( 'allowed_group' );
        $this->group_titles 							= $this->get_option( 'group_titles' );
        $this->cityes_limit 	  						= $this->get_option( 'cityes_limit' );
        $this->cityes_list 		  						= $this->get_option( 'cityes_list' );
        $this->sender_city								= $this->get_option( 'sender_city' );
        $this->sender_name								= $this->get_option( 'sender_name' );
        $this->sender_email								= $this->get_option( 'sender_email' );
        $this->sender_phone								= $this->get_option( 'sender_phone' );
        $this->sender_address							= $this->get_option( 'sender_address' );
        $this->debug              						= $this->get_option( 'debug' );
		
		$this->connect			  = new WC_Shiptor_Connect( $this->id, $this->instance_id );
		$this->available_rates 	  = array();
				
        // Save admin options.
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'disable_cod_payment' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
		
    }

    protected function get_log_link() {
        return ' <a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'View logs.', 'woocommerce-shiptor' ) . '</a>';
    }

    protected function get_shipping_classes_options() {
        $shipping_classes = WC()->shipping->get_shipping_classes();
        $options          = array(
            '-1' => __( 'Any Shipping Class', 'woocommerce-shiptor' ),
            '0'  => __( 'No Shipping Class', 'woocommerce-shiptor' ),
        );
        if ( ! empty( $shipping_classes ) ) {
            $options += wp_list_pluck( $shipping_classes, 'name', 'term_id' );
        }
        return $options;
    }
	
	protected function get_city_by_id( $city_id ) {
		$city_origin = WC_Shiptor_Autofill_Addresses::get_city_by_id( $city_id );
		if( $city_origin && ! empty( $city_origin ) && isset( $city_origin['kladr_id'] ) && $city_origin['kladr_id'] > 0 ) {
			return array( $city_origin['kladr_id'] => sprintf( '%s (%s)', $city_origin['city_name'], $city_origin['state'] ) );
		}
	}

    public function init_form_fields() {

		$this->instance_form_fields = array(
            'title' => array(
                'title'       => __( 'Title', 'woocommerce-shiptor' ),
                'type'        => 'text',
                'description' => 'Этот заголовок будет отображаться только в списке добавленых методов, для вашего удобства.',
                'desc_tip'    => true,
                'default'     => $this->method_title,
            ),
            'behavior_options' => array(
                'title'   => __( 'Behavior Options', 'woocommerce-shiptor' ),
                'type'    => 'title',
                'default' => '',
            ),
            'shipping_class_id' => array(
                'title'       => __( 'Shipping Class', 'woocommerce-shiptor' ),
                'type'        => 'select',
                'description' => __( 'If necessary, select a shipping class to apply this method.', 'woocommerce-shiptor' ),
                'desc_tip'    => true,
                'default'     => '',
                'class'       => 'wc-enhanced-select',
                'options'     => $this->get_shipping_classes_options(),
            ),
            'show_delivery_time' => array(
                'title'       => __( 'Delivery Time', 'woocommerce-shiptor' ),
                'type'        => 'checkbox',
                'label'       => __( 'Show estimated delivery time', 'woocommerce-shiptor' ),
                'description' => __( 'Display the estimated delivery time in working days.', 'woocommerce-shiptor' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'additional_time' => array(
                'title'       => __( 'Additional Days', 'woocommerce-shiptor' ),
                'type'        => 'text',
                'description' => __( 'Additional working days to the estimated delivery.', 'woocommerce-shiptor' ),
                'desc_tip'    => true,
                'default'     => '0',
                'placeholder' => '0',
            ),
            'fee' => array(
                'title'       => __( 'Handling Fee', 'woocommerce-shiptor' ),
                'type'        => 'price',
                'description' => __( 'Enter an amount, e.g. 2.50, or a percentage, e.g. 5%. Leave blank to disable.', 'woocommerce-shiptor' ),
                'desc_tip'    => true,
                'placeholder' => wc_format_localized_price( 0 ),
                'default'     => '0',
            ),
			'fee_type'	=> array(
				'title'		=> __( 'Handling Fee type', 'woocommerce-shiptor' ),
				'type'		=> 'select',
				'description' => __( 'Choose how to apply a surcharge', 'woocommerce-shiptor' ),
				'desc_tip'    => true,
				'default'     => 'order',
				'class'       => 'wc-enhanced-select',
				'options'	=> array(
					'order' 	=> 'Только на стоимость корзины',
					'shipping'	=> 'Только на стоимость доставки',
					'both'		=> 'На весь заказ'
				)
			),
			'min_cost'	=> array(
				'title'		=> 'Минимальная сумма корзины',
				'type'		=> 'price',
				'description'	=> __( 'Enter minimum order price for available this method. Leave blank if you dont wanna use this option.', 'woocommerce-shiptor' ),
				'desc_tip'    => true,
				'placeholder' => wc_format_localized_price( 0 ),
				'default'     => '0'
			),
			'free'	=> array(
				'title'		=> __( 'Free shipping', 'woocommerce-shiptor' ),
				'type'		=> 'price',
				'description'	=> __( 'Enter the amount at which this method will be free', 'woocommerce-shiptor' ),
				'desc_tip'    => true,
				'placeholder' => wc_format_localized_price( 0 ),
				'default'     => '0'
			),
			'fix_cost'	=> array(
				'title'			=> __( 'Fixed cost', 'woocommerce-shiptor' ),
				'type'			=> 'price',
				'description'	=> __( 'Enter the fixed amount for this method', 'woocommerce-shiptor' ),
				'desc_tip'    	=> true,
				'placeholder' 	=> wc_format_localized_price( 0 ),
				'default'     	=> '0'
			),
			'enable_declared_cost' => array(
				'title'       => 'Страховать отправление',
                'type'        => 'checkbox',
                'label'       => 'Учитывать страховку при расчёте стоимости',
                'description' => 'Вкл/выкл страховку в стоиомсть доставки.',
                'desc_tip'    => true,
                'default'     => 'yes',
			),
			'allowed_group'	=> array(
				'title'			=> 'Разрешенные группы профиля',
				'type'			=> 'multiselect',
				'description'	=> 'Список разрешенных групп для данного профиля',
				'class'			=> 'wc-enhanced-select',
				'desc_tip'		=> true,
				'select_buttons' => true,
				'default'		=> $this->get_default_allowed_group(),
				'options'		=> $this->get_allowed_group_list()
			),
			'cityes_limit'	=> array(
				'title'			=> __( 'Delivery to cities', 'woocommerce-shiptor' ),
				'type'			=> 'select',
				'description'	=> __( 'Enable or disable this delivery method to specific cities.', 'woocommerce-shiptor' ),
				'desc_tip'		=> true,
				'default'		=> '-1',
				'class'       	=> 'wc-enhanced-select',
				'options'		=> array(
					'-1'  	=> __( 'Disabled', 'woocommerce-shiptor' ),
					'on'	=> __( 'Enable for specified cities', 'woocommerce-shiptor' ),
					'off'	=> __( 'Disable for specified cities', 'woocommerce-shiptor' )
				)
			),
			'cityes_list'	=> array(
				'title'			=> __( 'Cities', 'woocommerce-shiptor' ),
				'type'			=> 'cityes_multiselect',
				'description'	=> __( 'Cities list', 'woocommerce-shiptor' ),
				'desc_tip'		=> true,
				'class'       	=> 'city-ajax-load'
			),
			'group_titles'	=> array(
				'title'		=> 'Заголовки групп',
				'type'    	=> 'titles',
				'desc_tip'	=> true,
				'description'	=> 'Эти заголовки будут отображатся на странице корзины и оформления заказа. Если оставить пустым, то будет использовать заголовок по умолчанию.'
			),
			'end_to_end' => array(
                'title'   => __( 'End-to-end delivery options', 'woocommerce-shiptor' ),
                'type'    => 'title',
				'description'	=> __( 'Enter the parameters for end-to-end delivery. Otherwise, end-to-end delivery methods will not be available.', 'woocommerce-shiptor' )
            ),
			'sender_city'	=> array(
				'title' => __( 'Sender city', 'woocommerce-shiptor' ),
				'type'	=> 'select_city',
				'class' => 'city-ajax-load',
				'default'	=> wc_shiptor_get_option( 'city_origin' ),
				'desc_tip' => __( 'Select the city of origin.', 'woocommerce-shiptor' ),
				'custom_attributes'	=> array( 'required' => 'required' )
			),
			'sender_address'	=> array(
				'title'	=> 'Адрес отправителя',
				'type'	=> 'text',
				'desc_tip'	=> 'Введите адрес откуда будет отправляться зазаз. (только для доставки типа "от двери")'
			),
			'sender_name'	=> array(
				'title'	=> __( 'Sender name', 'woocommerce-shiptor'),
				'type'	=> 'text',
				'desc_tip'	=> true,
				'description' => __( 'Enter sender name. Can be personal name or Organization name.', 'woocommerce-shiptor' )
			),
			'sender_email'	=> array(
				'title'	=> __( 'Sender E-mail', 'woocommerce-shiptor'),
				'type'	=> 'text',
				'desc_tip'	=> true,
				'description' => __( 'Enter sender e-mail.', 'woocommerce-shiptor' ),
				'default'	=> get_option( 'admin_email' )
			),
			'sender_phone'	=> array(
				'title'	=> __( 'Sender phone', 'woocommerce-shiptor'),
				'type'	=> 'text',
				'desc_tip'	=> true,
				'description' => __( 'Enter sender phone.', 'woocommerce-shiptor' ),
				'placeholder' => '+79991234567'
			),
            'testing' => array(
                'title'   => __( 'Testing', 'woocommerce-shiptor' ),
                'type'    => 'title'
            ),
            'debug' => array(
                'title'       => __( 'Debug Log', 'woocommerce-shiptor' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'woocommerce-shiptor' ),
                'default'     => 'no',
				'desc_tip'	=> false,
                'description' => sprintf( __( 'Log %s events, such as Shiptor server requests.', 'woocommerce-shiptor' ), $this->method_title ) . $this->get_log_link(),
            ),
        );
    }
	
	public function admin_options() {
		
		wp_enqueue_script( $this->id . '-admin', plugins_url( 'assets/admin/js/shipping-method.js', WC_SHIPTOR_PLUGIN_FILE ), array( 'selectWoo' ), WC_SHIPTOR_VERSION, true );
		
		wp_localize_script(
            $this->id . '-admin',
            'wc_shiptor_admin_params',
            array(
				'placeholder'	=> __( 'Choose an city', 'woocommerce-shiptor' ),
				'ajax_url'		=> WC_AJAX::get_endpoint( "%%endpoint%%" ),
				'country_iso'	=> WC()->countries->get_base_country()
            )
        );
		
		parent::admin_options();
	}
	
	public function get_default_allowed_group() {
		return apply_filters( 'woocommerce_shiptor_default_allowed_group_' . $this->id, array(), $this );
	}
	
	public function get_allowed_group_list() {
		return apply_filters( 'woocommerce_shiptor_allowed_group_list_' . $this->id, array(), $this );
	}
	
	public function generate_cityes_multiselect_html( $key, $data ) {
		$data['options'] = array();
		$values = (array) $this->get_option( $key, array() );
		
		foreach( $values as $kladr_id ) {
			$data['options'] += $this->get_city_by_id( $kladr_id );
		}
		
		return $this->generate_multiselect_html( $key, $data );
	}
	
	public function generate_select_city_html( $key, $data ) {
		$data['type'] = 'select';
		$data['options'] = $this->get_city_by_id( $this->get_option( $key, $data['default'] ) );
		return $this->generate_select_html( $key, $data );
	}
	
	public function generate_titles_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'class'             => '',
			'desc_tip'          => false,
			'description'       => '',
		);

		$data = wp_parse_args( $data, $defaults );
		$value = (array) $this->get_option( $key, array() );
		
		ob_start();
		
		?>
		</table>
		<h3 class="wc-settings-sub-title" id="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); ?></h3>
		<table class="form-table">
			<?php foreach( $this->get_allowed_group_list() as $group => $group_name ) : ?>
			<tr valign="top" style="<?php echo ! in_array( $group, $this->allowed_group ) ? 'display:none;' : '';?>">
				<th scope="row" class="titledesc">
					<?php echo $this->get_tooltip_html( array( 'desc_tip' => sprintf( 'Заголовок для %s', esc_attr( $group_name ) ) ) ); ?>
					<label><?php echo wp_kses_post( $group_name ); ?></label>
				</th>
				<td class="forminp">
					<fieldset>
						<?php $title_value = isset( $value[ $group ] ) ? $value[ $group ] : ''; ?>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post( $group_name ); ?></span></legend>
						<input class="input-text regular-input group-titles-input" type="text" data-group="<?php echo esc_attr( $group ); ?>" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $group ); ?>]" id="<?php echo esc_attr( $field_key ); ?>_<?php echo esc_attr( $group ); ?>" value="<?php echo esc_attr( $title_value ); ?>" placeholder="<?php printf( 'Введите заголовок для метода %s', $group_name ); ?>" />
					</fieldset>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php

		return ob_get_clean();
	}
	
	public function validate_cityes_multiselect_field( $key, $value ) {
		return $this->validate_titles_field( $key, $value );
	}
	
	public function validate_titles_field( $key, $value ) {
		return $this->validate_multiselect_field( $key, $value );
	}
	
	private function get_cityes_list() {
		$list = array();
		$all_address = WC_Shiptor_Autofill_Addresses::get_all_address();
		foreach( $all_address as $address ) {
			$list[ $address['kladr_id'] ] = sprintf( '%s (%s)', $address['city_name'], $address['state'] );
		}
		return $list;
	}

    public function validate_price_field( $key, $value ) {
        $value     = is_null( $value ) ? '' : $value;
        $new_value = '' === $value ? '' : wc_format_decimal( trim( stripslashes( $value ) ) );
        if ( '%' === substr( $value, -1 ) ) {
            $new_value .= '%';
        }
        return $new_value;
    }

    public function get_service() {
        return apply_filters( 'woocommerce_shiptor_shipping_method_service', $this->service, $this->id, $this->instance_id );
    }

    protected function get_declared_value( $package ) {
        return $package['contents_cost'];
    }

    protected function get_additional_time( $package = array() ) {
        return apply_filters( 'woocommerce_shiptor_shipping_additional_time', $this->additional_time, $package );
    }

    protected function get_shipping_method_label( $label, $days, $package ) {
        if ( 'yes' === $this->show_delivery_time ) {
            return wc_shiptor_get_estimating_delivery( $label, $days, $this->get_additional_time( $package ) );
        }
        return $label;
    }
	
	protected function get_label_by_group( $label, $group ) {
		
		if( isset( $this->group_titles[ $group ] ) && ! empty( $this->group_titles[ $group ] ) ) {
			$label = esc_html( $this->group_titles[ $group ] );
		}
		
		return $label;
	}

    protected function has_only_selected_shipping_class( $package ) {
        $only_selected = true;
        if ( -1 === $this->shipping_class_id ) {
            return $only_selected;
        }
        foreach ( $package['contents'] as $item_id => $values ) {
            $product = $values['data'];
            $qty     = $values['quantity'];
            if ( $qty > 0 && $product->needs_shipping() ) {
                if ( $this->shipping_class_id !== $product->get_shipping_class_id() ) {
                    $only_selected = false;
                    break;
                }
            }
        }
        return $only_selected;
    }
	
	public function is_available( $package ) {
		
		if ( "no" === $this->enabled ) {
			return false;
		}
		
		$available = true;
		
		if ( ! $this->get_rates( $package ) ) {
			$available = false;
		}
		
		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $available, $package, $this );
	}

    public function get_rates( $package ) {

        if ( empty( $package['destination']['city'] ) || ! isset( $package['destination']['kladr_id'] ) || empty( $package['destination']['kladr_id'] ) || ! in_array( $package['destination']['country'], array( 'RU', 'BY', 'KZ' ) ) ) {
            return false;
        }
		
		if( '-1' !== $this->cityes_limit && ! empty( $this->cityes_list ) ) {
			
			$allow_city = in_array( $package['destination']['kladr_id'], $this->cityes_list );
			
			if( $this->cityes_limit == 'on' && $allow_city === false ) {
				return false;
			} elseif( 'off' == $this->cityes_limit && $allow_city === true ) {
				return false;
			}
		}

        if ( ! $this->has_only_selected_shipping_class( $package ) ) {
            return false;
        }
		
		if ( ! $this->instance_id ) {
			return false;
		}
		
		$cod = $this->get_declared_value( $package );
		
		if( $this->min_cost > 0 && $this->min_cost > $cod ) {
			return false;
		}
		
		if( empty( $this->allowed_group ) ) {
			return false;
		}
		
		$rates = array();
		
		$this->connect->set_debug( $this->debug );
		$this->connect->set_service( $this->get_service() );
		$this->connect->set_package( $package );
		$this->connect->set_kladr_id( $package['destination']['kladr_id'] );
		$this->connect->set_country_code( $package['destination']['country'] );
		
		if( $this->is_ete && ! empty( $this->sender_city ) ) {
			$this->connect->set_kladr_id_from( $this->sender_city );
		}

		$chosen_payment_method = WC()->session->get( 'chosen_payment_method' );
		
		$declared_cost = 'yes' == $this->enable_declared_cost ? $cod : 10;
		
		$this->connect->set_declared_cost( $declared_cost );
		
		if( $package['destination']['country'] == 'RU' && in_array( $chosen_payment_method, array( 'cod', 'cod_card' ) ) ) {
			$this->connect->set_cod( $cod );
			$this->connect->set_declared_cost( $cod );
			$this->connect->set_card( $chosen_payment_method === 'cod_card' );
		}
		   
		$shipping = $this->connect->get_shipping();
        
		if ( empty( $shipping ) ) {
            return false;
        }
		
		
		foreach( $shipping as $shipping_method ) {
			
			if( 'ok' !== $shipping_method['status'] ) {
				continue;
			}
			
			if( ! in_array( $shipping_method['method']['group'], $this->allowed_group ) ) {
				continue;
			}
			
			$label = $this->get_label_by_group( $shipping_method['method']['name'], $shipping_method['method']['group'] );
			
			$meta_data = array(
				'shiptor_method' => array_merge( $shipping_method['method'], array(
					'label'			=> $this->get_shipping_method_label( $label, $shipping_method['days'], $package ),
					'declared_cost' => $this->connect->get_declared_cost(),
					'show_time'		=> wc_bool_to_string( $this->show_delivery_time )
				) )
			);
			
			if( 'date' == wc_shiptor_get_option( 'sorting_type' ) ) {
				$meta_data['shiptor_method']['date'] = wc_shiptor_get_shipping_delivery_time( $shipping_method['days'], $this->additional_time );
			}
			
			foreach( array( 'sender_city', 'sender_address', 'sender_name', 'sender_email', 'sender_phone' ) as $sender_prop ) {
				if( isset( $this->$sender_prop ) && ! empty( $this->$sender_prop ) ) {
					$meta_data['shiptor_method'][$sender_prop] = $this->$sender_prop;
				}
			}
			
			if( in_array( $shipping_method['method']['category'], array( 'delivery-point', 'delivery-point-to-delivery-point', 'door-to-delivery-point' ) ) ) {
				$this->connect->set_shipping_method( $shipping_method['method']['id'] );
				$delivery_points = $this->connect->get_delivery_points();
				
				if( ! empty( $delivery_points ) ) {
					$meta_data['delivery_points'] = $delivery_points;
				}
			}
			
			$cost = $shipping_method['cost']['total']['sum'];
			
			if( $this->free > 0 && $cod >= $this->free ) {
				$cost = 0;
			} elseif( $this->fix_cost > 0 ) {
				$cost = $this->fix_cost;
			} elseif( ! empty( $this->fee ) && $this->fee !== 0 ) {
				switch( $this->fee_type ) {
					case 'order' :
						$cost += $this->get_fee( $this->fee, $cod );
						break;
					case 'shipping' :
						$cost += $this->get_fee( $this->fee, $cost );
						break;
					case 'both' :
						$cost += $this->get_fee( $this->fee, $cod + $cost );
						break;						
				}
			}
			
			$rates[] = array(
				'id'    	=> $this->get_rate_id( $shipping_method['method']['category'] . '_' . $shipping_method['method']['group'] ),
				'label' 	=> $label,
				'cost' 		=> $cost,
				'meta_data'	=> $meta_data
			);
		}
		
		if ( sizeof( $rates ) == 0 ) {
			return false;
		}
		
		$this->available_rates = $rates;

		return true;
		
    }
	
	public function calculate_shipping( $packages = array() ) {
		if ( $this->available_rates && ! empty( $this->available_rates ) ) {
			foreach ( $this->available_rates as $rate ) {
				$this->add_rate( $rate );
			}
		}
	}
	
	public function is_shiptor() {
		return in_array( $this->id, wc_get_chosen_shipping_method_ids() );
	}
	
	public function disable_cod_payment( $gateways ) {
		
		if( is_admin() ) {
			return $gateways;
		}
		
		$rates = wc_shiptor_chosen_shipping_rates();
		$rate = isset( $rates[ $this->id ] ) ? $rates[ $this->id ] : false;
		$country = WC()->customer->get_billing_country();
		
		if( $this->is_shiptor() ) {
			
			if( 'RU' !== $country ) {
				unset( $gateways['cod'] );
				unset( $gateways['cod_card'] );
			}
			
			if( isset( $gateways['cod_card'] ) && false !== $rate && 'RU' == $country && ( in_array( $rate->meta_data['shiptor_method']['group'], array( 'russian_post', 'dpd_economy_courier', 'dpd_economy_delivery' ) ) ) ) {
				unset( $gateways['cod_card'] );
			}
			
			if( isset( $gateways['cod'] ) && false !== $rate && 'RU' == $country && ( in_array( $rate->meta_data['shiptor_method']['group'], array( 'dpd_economy_courier', 'dpd_economy_delivery' ) ) ) ) {
				unset( $gateways['cod'] );
			}
		}
		
		return $gateways;
	}
	
	public function validate_checkout( $data, $errors ) {
		if( ! $this->is_shiptor() ) {
			return;
		}
		
		$rates = wc_shiptor_chosen_shipping_rates();
		$rate = $rates[ $this->id ];
		$chosen_delivery_point = WC()->session->get( 'chosen_delivery_point' );
		$chosen_payment_method = WC()->session->get( 'chosen_payment_method' );
		
		if( in_array( $rate->meta_data['shiptor_method']['category'], array( 'delivery-point', 'door-to-delivery-point', 'delivery-point-to-delivery-point' ) ) ) {
			
			if ( is_null( $chosen_delivery_point ) && ! in_array( 'empty-delivery-point', $errors->get_error_codes() ) ) { 
				
				$errors->add( 'empty-delivery-point', __( "You didn't select a delivery point.", 'woocommerce-shiptor' ) );
				
			} elseif( in_array( $chosen_payment_method, array( 'cod', 'cod_card' ) ) && ! is_null( $chosen_delivery_point ) && ! in_array( 'delivery-point-cod', $errors->get_error_codes() ) ) {
				
				$delivery_point = wp_list_filter( $rate->meta_data['delivery_points'], array( 'id' => $chosen_delivery_point ) );
				
				if( is_array( $delivery_point ) && ! empty( $delivery_point ) ) {
					
					$delivery_point = current( array_values( $delivery_point ) );
					
					if( isset( $delivery_point['cod'] ) && ! $delivery_point['cod'] ) {
						
						$errors->add( 'delivery-point-cod', __( 'The delivery point of issue selected by you does not accept payment by cash on delivery. Please choose a different payment method or other delivery point.', 'woocommerce-shiptor' ) );
					
					}
				}
			}
		}
	}
	
}