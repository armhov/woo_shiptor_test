<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shiptor_status = $the_order->get_meta( '_shiptor_status' );
$status_classes = array( 'badge' );
switch( $shiptor_status ) {
	case 'lost' :
	case 'recycled' :
	case 'removed'	:
		$status_classes[] = 'badge-danger';
		break;
	case 'checking-declaration'	:
	case 'waiting-pickup' :
	case 'waiting-on-delivery-point' :
		$status_classes[] = 'badge-warning';
		break;
	case 'resend' :
	case 'returned' :
	case 'reported' :
		$status_classes[] = 'badge-info';
		break;
	case 'delivered' :
		$status_classes[] = 'badge-success';
		break;
	default :
		$status_classes[] = 'badge-secondary';
		break;	
}
?>

<div class="shiptor-table-order">
	<table>
		<tbody>
			<tr>
				<td><small><?php _e( 'Delivery method:', 'woocommerce-shiptor' );?></small></td>
				<td><small><span class="badge badge-primary"><?php echo $shiptor_method['name'];?></span></small></td>
			</tr>
			<tr>
				<td><small><?php _e( 'Tracking code:', 'woocommerce-shiptor' );?></small></td>
				<td><small><span class="badge badge-primary"><?php echo $tracking_code;?></span></small></td>
			</tr>
			<tr>
				<td><small><?php _e( 'Status:', 'woocommerce-shiptor' );?></small></td>
				<td><small><span class="<?php echo implode( ' ', $status_classes );?>"><?php echo wc_shiptor_get_status( $shiptor_status );?></span></small></td>
			</tr>
		</tbody>
	</table>
</div>