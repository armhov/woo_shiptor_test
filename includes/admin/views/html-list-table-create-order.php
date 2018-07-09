<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$need_delivery_pount = in_array( $shiptor_method['category'], array( 'delivery-point-to-delivery-point', 'delivery-point-to-door' ), true ) === true;
?>
<div class="shiptor-table-order">
	<table>
		<tbody>
			<?php if( ! $need_delivery_pount ) : ?>
			<tr>
				<td><small><?php _e( 'Delivery method:', 'woocommerce-shiptor' );?></small></td>
				<td><small><span class="badge badge-primary"><?php echo $shiptor_method['name'];?></span></small></td>
			</tr>
			<tr>
				<td></td>
				<td><a class="button button-primary" href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=shiptor_send_order&order_id=' . $the_order->get_id() ), 'shiptor-send-order' );?>">Отправить заказ</a></td>
			</tr>
			<?php else : ?>
			<tr>
				<td><small><?php _e( 'Delivery method:', 'woocommerce-shiptor' );?></small></td>
				<td><small><span class="badge badge-primary tips" data-tip="Что бы отправить заказ необходимо выбрать ПВЗ отправителя"><?php echo $shiptor_method['name'];?></span></small></td>
			</tr>
			<?php endif;?>
		</tbody>
	</table>
</div>