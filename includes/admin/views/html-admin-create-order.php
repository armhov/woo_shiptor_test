<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="shiptor-order-details">
	<div id="wc-shiptor-result"></div>
	<form id="create-shiptor-order" method="post">
		<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_order_number() );?>" />
		<input type="hidden" name="method_id" value="<?php echo esc_attr( $shiptor_method['id'] );?>" />
		<input type="hidden" name="courier" value="<?php echo esc_attr( $shiptor_method['courier'] );?>" />
		<input type="hidden" name="category" value="<?php echo esc_attr( $shiptor_method['category'] );?>" />
		<input type="hidden" name="method_name" value="<?php echo esc_attr( $shiptor_method['name'] );?>" />
		<div class="col">
			<h3><?php _e( 'Order details', 'woocommerce-shiptor' );?></h3>
			<table>
				<tbody>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Method name: ', 'woocommerce-shiptor' );?></strong></p></td>
						<td><p class="form-field"><?php echo $shiptor_method['name'];?></p></td>
					</tr>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Country: ', 'woocommerce-shiptor' );?></strong></p></td>
						<td><p class="form-field"><?php echo WC()->countries->countries[ $order->get_billing_country() ];?></p></td>
					</tr>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'City: ', 'woocommerce-shiptor' );?></strong></p></td>
						<td><p class="form-field"><?php echo $order->get_billing_city();?></p></td>
					</tr>
					<?php if( ! empty( $delivery_points ) ) : ?>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Delivery point: ', 'woocommerce-shiptor' );?></strong></p></td>
						<td><?php
							woocommerce_wp_select( array(
								'id'          => 'chosen_delivery_point',
								'value'       => $order->get_meta( '_chosen_delivery_point' ),
								'label'       => null,
								'class'		  => 'wc-enhanced-select',
								'options'     => wp_list_pluck( $delivery_points, 'address', 'id' )
							) );
						?></td>
					</tr>
					<?php elseif( in_array( $shiptor_method['category'], array( 'to-door', 'post-office', 'door-to-door', 'delivery-point-to-door' ) ) ) : ?>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Address: ', 'woocommerce-shiptor' );?></strong></p></td>
						<td><?php
							woocommerce_wp_text_input( array(
								'id'          => 'address_line',
								'value'       => $order->get_billing_address_1(),
								'class'		  => 'input-text',
								'label'       => null
							) );
						?></td>
					</tr>
					<?php endif; ?>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Payment method: ', 'woocommerce-shiptor' );?></strong></p></td>
						<td><p class="form-field"><?php echo $order->get_payment_method_title( 'edit' );?></p></td>
					</tr>
					<?php if( in_array( $order->get_billing_country(), array( 'RU', 'BY', 'KZ' ) ) ) : ?>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'C.O.D. : ', 'woocommerce-shiptor' );?></strong></p></td>
						<td><p class="form-field"><input class="input-text" type="text" name="cod" readonly value="<?php echo $order->has_status( array( 'pending', 'processing', 'failed' ) ) ? $order->get_total() : '0';?>" /></p></td>
					</tr>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Declared cost : ', 'woocommerce-shiptor' );?></strong></p></td>
						<td><p class="form-field"><input class="input-text" type="text" name="declared_cost" readonly value="<?php echo $shiptor_method['declared_cost'] > 10 ? $order->get_total() : 10;?>" /></p></td>
					</tr>
					<?php endif;?>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Order note : ', 'woocommerce-shiptor' );?></strong></p></td>
						<td>
						<?php
							woocommerce_wp_textarea_input( array(
								'id'          => 'comment',
								'value'       => $order->get_customer_note( 'edit' ),
								'label'       => null,
								'class'		  => 'input-text',
								'rows'		  => 5
							) );
						?>
						</td>
					</tr>
				</tbody>
			</table>
						
		</div>
		<div class="col">
			<h3><?php _e( 'Details of the sender', 'woocommerce-shiptor' );?></h3>
			<table>
				<tbody>
					<?php if( strstr( $shiptor_method['category'], '-to-' ) ) : ?>
						<tr>
							<td><p class="form-field"><strong><?php _e( 'Sender name', 'woocommerce-shiptor' );?></strong></p></td>
							<td><?php
								woocommerce_wp_text_input( array(
									'id'          => 'sender_name',
									'value'       => isset( $shiptor_method['sender_name'] ) ? $shiptor_method['sender_name'] : null,
									'class'		  => 'input-text',
									'label'       => null,
									'custom_attributes' => array( 'required' => 'required' )
								) );
							?></td>
						</tr>
						<tr>
							<td><p class="form-field"><strong><?php _e( 'Sender phone', 'woocommerce-shiptor' );?></strong></p></td>
							<td><?php
								woocommerce_wp_text_input( array(
									'id'          => 'sender_phone',
									'value'       => isset( $shiptor_method['sender_phone'] ) ? $shiptor_method['sender_phone'] : null,
									'class'		  => 'input-text',
									'label'       => null,
									'custom_attributes' => array( 'required' => 'required' )
								) );
							?></td>
						</tr>
						<tr>
							<td><p class="form-field"><strong><?php _e( 'Sender E-mail', 'woocommerce-shiptor' );?></strong></p></td>
							<td><?php
								woocommerce_wp_text_input( array(
									'id'          => 'sender_email',
									'value'       => isset( $shiptor_method['sender_email'] ) ? $shiptor_method['sender_email'] : null,
									'class'		  => 'input-text',
									'label'       => null,
									'custom_attributes' => array( 'required' => 'required' )
								) );
							?></td>
						</tr>
						<?php if( isset( $shiptor_method['sender_city'] ) ) : ?>
						<tr>
							<input type="hidden" name="sender_city" value="<?php echo $shiptor_method['sender_city'];?>" />
							<td><p class="form-field"><strong><?php _e( 'Sender city', 'woocommerce-shiptor' );?></strong></p></td>
							<td><?php
								$sender_city = WC_Shiptor_Autofill_Addresses::get_city_by_id( $shiptor_method['sender_city'] );
								woocommerce_wp_text_input( array(
									'id'          => 'sender_city_name',
									'value'       => $sender_city['city_name'],
									'class'		  => 'input-text',
									'custom_attributes' => array( 'readonly' => 'readonly' ),
									'label'       => null
								) );
							?>
							</td>
						</tr>
							<?php if( 0 === strpos( $shiptor_method['category'], 'delivery-point-to-' ) ) : ?>
							<tr>
								<td><p class="form-field"><strong><?php _e( 'Delivery point: ', 'woocommerce-shiptor' );?></strong></p></td>
								<td><?php
									
									$package = array();
									
									foreach( $order->get_items( 'line_item' ) as $item ) {
										$product = wc_get_product( $item->get_product_id() );
										$package['contents'][]	= array(
												'data'		=> $product,
												'quantity'	=> $item->get_quantity()
										);
									}
								
									$connect = new WC_Shiptor_Connect( 'sender_delivery_point' );
									$connect->set_kladr_id( $shiptor_method['sender_city'] );
									$connect->set_service( $shiptor_method['courier'] );
									$connect->set_shipping_method( $shiptor_method['id'] );
									$connect->set_cod( $order->get_billing_country() === 'RU' && ! $order->is_paid() ? $order->get_total() : 0 );
									$connect->set_package( $package );
									
									woocommerce_wp_select( array(
										'id'          => 'sender_delivery_point',
										'label'       => null,
										'class'		  => 'wc-enhanced-select',
										'options'     => wp_list_pluck( $connect->get_delivery_points(), 'address', 'id' ),
										'custom_attributes' => array( 'required' => 'required' )
									) );
								?></td>
							</tr>
							<?php elseif( 0 === strpos( $shiptor_method['category'], 'door-to-' ) ) : ?>
							<tr>
								<td><p class="form-field"><strong><?php _e( 'Sender address', 'woocommerce-shiptor' );?></strong></p></td>
								<td><?php
									woocommerce_wp_text_input( array(
										'id'          => 'sender_address',
										'value'       => isset( $shiptor_method['sender_address'] ) ? $shiptor_method['sender_address'] : null,
										'class'		  => 'input-text',
										'label'       => null,
										'custom_attributes' => array( 'required' => 'required' )
									) );
								?>
								</td>
							</tr>
							<?php endif;?>
						<?php endif;?>
						<tr>
							<td><p class="form-field"><strong><?php _e( 'Date', 'woocommerce-shiptor' );?></strong></p></td>
							<td><?php
									woocommerce_wp_text_input( array(
										'id'          => 'sender_order_date',
										'value'       => date( 'Y-m-d', strtotime( '+1days' ) + wc_timezone_offset() ),
										'class'		  => 'sender-order-date',
										'label'       => null,
										'placeholder' => 'YYYY-MM-DD'
									) );
								?>
							</td>
						</tr>
					<?php endif;?>
					<?php if( false === strstr( $shiptor_method['category'], '-to-' ) ) : ?>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Collect from the warehouse? (only fullfilment)', 'woocommerce-shiptor' );?></strong></p></td>
						<td><?php
							woocommerce_wp_checkbox( array(
								'id'        => 'is_fulfilment',
								'label'     => null,
								'class'   	=> 'form-field--checkbox',
								'value'		=> 'yes'
							) );
						?></td>
					</tr>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Do not collect the parcel (only fullfilment)', 'woocommerce-shiptor' );?></strong></p></td>
						<td><?php
							woocommerce_wp_checkbox( array(
								'id'        => 'no_gather',
								'label'     => null,
								'class'   	=> 'form-field--checkbox',
							) );
						?></td>
					</tr>
					<?php if( $order->get_billing_country() === 'RU' && in_array( $order->get_payment_method(), array( 'cod', 'cod_card' ) ) ) : ?>
					<tr>
						<td><p class="form-field"><strong><?php _e( 'Payment by card', 'woocommerce-shiptor' );?></strong></p></td>
						<td><?php
							woocommerce_wp_checkbox( array(
								'id'        => 'cashless_payment',
								'label'     => null,
								'class'   	=> 'form-field--checkbox',
								'value'		=> $order->get_payment_method() == 'cod_card' ? 'yes' : 'no'
							) );
						?></td>
					</tr>
					<?php endif;?>
					<?php endif;?>
				</tbody>
			</table>
		</div>
		<div class="clear"></div>
		<div class="action">
			<button type="submit" class="button button-primary"><?php _e( 'Create order', 'woocommerce-shiptor' );?></button>
		</div>
	</form>
</div>