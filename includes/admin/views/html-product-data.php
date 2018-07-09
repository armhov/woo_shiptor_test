<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="shiptor_product_data" class="panel woocommerce_options_panel hidden">
	<div class="options_group">
		<?php
			woocommerce_wp_text_input( array(
				'id'          => '_eng_name',
				'label'       => __( 'English product name', 'woocommerce-shiptor' ),
				'placeholder' => __( 'Enter product name in English.', 'woocommerce-shiptor' ),
				'desc_tip'    => true,
				'description' => __( 'Enter product name in English. It is necessary for international delivery.', 'woocommerce-shiptor' ),
				'type'        => 'text'
			) );
			
			woocommerce_wp_text_input( array(
				'id'          => '_article',
				'label'       => __( 'Article', 'woocommerce-shiptor' ),
				'placeholder' => $product_object->get_sku(),
				'desc_tip'    => true,
				'description' => __( 'Enter original article on the product.', 'woocommerce-shiptor' ),
				'type'        => 'text'
			) );
		?>
	</div>
	<div class="options_group">
		<?php
		/*
			woocommerce_wp_checkbox( array(
				'id'            => '_fulfilment',
				'label'         => __( 'Is fulfilment', 'woocommerce-shiptor' ),
				'description'   => __( 'Is product on fulfilment?', 'woocommerce-shiptor' ),
			) );
		*/	
			woocommerce_wp_checkbox( array(
				'id'            => '_fragile',
				'label'         => __( 'Is fragile', 'woocommerce-shiptor' ),
				'description'   => __( 'Is fragile product?', 'woocommerce-shiptor' ),
			) );
			
			woocommerce_wp_checkbox( array(
				'id'            => '_danger',
				'label'         => __( 'Is danger', 'woocommerce-shiptor' ),
				'description'   => __( 'Is danger product?', 'woocommerce-shiptor' ),
			) );
			
			woocommerce_wp_checkbox( array(
				'id'            => '_perishable',
				'label'         => __( 'Is perishable', 'woocommerce-shiptor' ),
				'description'   => __( 'Is product perishable?', 'woocommerce-shiptor' ),
			) );
			
			woocommerce_wp_checkbox( array(
				'id'            => '_need_box',
				'label'         => __( 'Need box', 'woocommerce-shiptor' ),
				'description'   => __( 'Need to pack this product?', 'woocommerce-shiptor' ),
			) );
		?>
	</div>
</div>