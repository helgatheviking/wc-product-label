<?php
/**
 * Single Product Label Thumbnails
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/label-thumbnails.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


global $post, $product;

$wrapper_classes   = apply_filters( 'woocommerce_single_product_image_gallery_classes', array(
	'woocommerce-product-label-gallery'
) );

$attachment_ids = array_filter( explode( ',', $product->get_meta('_product_label_gallery') ) );

$html = '';

if ( $attachment_ids ) {
	foreach ( $attachment_ids as $attachment_id ) {
	
		$attributes      = array(
			'title'                   => get_post_field( 'post_title', $attachment_id ),
			'data-caption'            => get_post_field( 'post_excerpt', $attachment_id )
		);

		$html  .= '<div class="woocommerce-product-label-image">';
		$html .= wp_get_attachment_image( $attachment_id, 'label_thumbnail', false, $attributes );
 		$html .= '</div>';

	}
}

if( $html ) {
?>
<div class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>" >
	<figure class="woocommerce-product-label-gallery__wrapper">

	<?php 
		echo apply_filters( 'woocommerce_product_label_thumbnail_html', $html, $attachment_id ); 
		do_action( 'woocommerce_product_label_thumbnails' );
	?>
	</figure>
</div>
<?php }