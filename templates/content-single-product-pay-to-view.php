<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}

// Load the woocommerce checkout scripts.
define( 'WOOCOMMERCE_CHECKOUT', true );

// Update add to cart btn text
add_filter( 'woocommerce_product_single_add_to_cart_text', function() {
    return __( 'Pay to view', 'woocommerce' );
});

?>

<style type="text/css">
	@media (min-width: 1580px) {
		#main {
			max-width: 1520px;
		}
	}
	.woocommerce-breadcrumb,
	.woocommerce-notices-wrapper {
		display: none;
	}
</style>

<div id="product-<?php the_ID(); ?>" class="<?php echo esc_attr( implode( ' ', wc_get_product_class( '', $product ) ) ) ?> content-single-product-pay-to-view">

	<?php
	/**
	 * Hook: woocommerce_before_single_product_summary.
	 *
	 * @hooked woocommerce_show_product_sale_flash - 10
	 * @hooked woocommerce_show_product_images - 20
	 */
	// do_action( 'woocommerce_before_single_product_summary' );
	?>

	<div class="content-single-product-pay-to-view__wrapper">

		<div class="content-single-product-pay-to-view__wrapper__sidebar">
			<?php if ( is_active_sidebar( 'sidebar-pnp-pay-to-view' ) ) : ?>
				<?php dynamic_sidebar( 'sidebar-pnp-pay-to-view' ); ?>
			<?php endif; ?>
		</div>

		<div class="content-single-product-pay-to-view__wrapper__content">
			<?php
			/**
			 * Hook: woocommerce_single_product_summary.
			 *
			 * @hooked woocommerce_template_single_title - 5
			 * @hooked woocommerce_template_single_rating - 10
			 * @hooked woocommerce_template_single_price - 10
			 * @hooked woocommerce_template_single_excerpt - 20
			 * @hooked woocommerce_template_single_add_to_cart - 30
			 * @hooked woocommerce_template_single_meta - 40
			 * @hooked woocommerce_template_single_sharing - 50
			 * @hooked WC_Structured_Data::generate_product_data() - 60
			 */
			// do_action( 'woocommerce_single_product_summary' );
			?>

			<?php
				$wc_order_id = null;
				$pay_to_view = null;
				$pay_to_view_token = null;
				$wc_order_pnp_pay_to_view_token = null;

				if (isset($_GET['wc_order_id'])) {
					$wc_order_id = intval( sanitize_text_field( $_GET['wc_order_id'] ) );
					if ($order = wc_get_order( $wc_order_id )) {
						$wc_order_pnp_pay_to_view_token = $order->get_meta('pnp_pay_to_view_token');
					}
				}

				if (isset($_GET['pay_to_view'])) {
					$pay_to_view = intval( sanitize_text_field( $_GET['pay_to_view'] ) );
					if (isset($_GET['pay_to_view_token'])) {
						$pay_to_view_token = sanitize_key( sanitize_text_field( $_GET['pay_to_view_token'] ) );
					}
				}

				woocommerce_template_single_title();

				if (($pay_to_view == 1) && ($pay_to_view_token === $wc_order_pnp_pay_to_view_token)) {
					the_content();
				}
				else {
					woocommerce_template_single_excerpt();
					?>
						<div>
							<p><em>Use pingNpay 'Pay to view' to continue reading...</em></p>
						</div>
						<div class="content-single-product-pay-to-view__checkout">
							<div class="content-single-product-pay-to-view__checkout__bar">
								<!-- <div class="content-single-product-pay-to-view__checkout__bar--price">
									<div><?php woocommerce_template_single_price(); ?></div>
								</div> -->
								<?php if ( WC()->cart->get_cart_contents_count() == 0 ) : ?>
									<div class="content-single-product-pay-to-view__checkout__bar--btn">
										<div><?php woocommerce_template_single_add_to_cart(); ?></div>
									</div>
								<?php else : ?>
									<div class="content-single-product-pay-to-view__checkout__bar--btn">
										<button id="content-single-product-pay-to-view__checkout__bar--btn">To view - pay <?php woocommerce_template_single_price(); ?></button>
									</div>
									<div class="content-single-product-pay-to-view__checkout__bar--input">
										<input type="text" id="content-single-product-pay-to-view__checkout__bar--input" value="" placeholder="johnsmith$walletprovider.com" />
									</div>
								<?php endif; ?>
							</div>
						</div>
						<div style="display: none !important;">
							<?php
								// Render the woocommerce checkout.
								echo do_shortcode ('[woocommerce_checkout]');
							?>
						</div>
					<?php
				}

			?>
		</div>

	</div>

	<?php
	/**
	 * Hook: woocommerce_after_single_product_summary.
	 *
	 * @hooked woocommerce_output_product_data_tabs - 10
	 * @hooked woocommerce_upsell_display - 15
	 * @hooked woocommerce_output_related_products - 20
	 */
	// do_action( 'woocommerce_after_single_product_summary' );
	?>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
