<?php
/**
 * Plugin Name: pingNpay small payments WC gateway
 * #Plugin URI:
 * Description: Take payments on your store using pingNpay.
 * Author: pingNpay
 * Author URI: https://pingnpay.com
 * Version: 1.0.0
 * Requires at least: 5.8
 * Tested up to: 6.0
 * WC requires at least: 6.8
 * WC tested up to: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'pnp_add_wc_gateway_class' );
function pnp_add_wc_gateway_class( $gateways ) {
    $gateways[] = 'WC_PNP_Gateway';
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'pnp_init_wc_gateway_class' );

function pnp_init_wc_gateway_class() {
    
    class WC_PNP_Gateway extends WC_Payment_Gateway {

        /**
         * Class constructor
         */
        public function __construct() {
            $this->version = '1.0.12'; // For cache busting
            $this->id = 'pnp'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true;
            $this->method_title = 'pingNpay Payments';
            $this->method_description = 'Description of pingNpay payment gateway';

            $this->supports = [
                'products'
            ];

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = $this->get_option( 'testmode' );
            $this->r_pnp_id = $this->get_option( 'r_pnp_id' );
            $this->remove_billing_details = $this->get_option( 'remove_billing_details' );
            $this->buy_it_now_mode = $this->get_option( 'buy_it_now_mode' );

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Load JavaScript
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

            // Register webhook
            add_action( 'woocommerce_api_wc_pnp', array( $this, 'webhook' ) );
        }

        /**
         * Plugin options
         */
        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable pingNpay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ],
                'testmode' => [
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ],
                'title' => [
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Pay using pingNpay',
                    'desc_tip'    => true,
                ],
                'description' => [
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your pingNpay ID.',
                ],
                'r_pnp_id' => [
                    'title' => 'Retailer pingNpay ID',
                    'type' => 'text',
                    'description' => 'Description of Retailer pingNpay ID',
                    'default' => '',
                ],
                'remove_billing_details' => [
                    'title'       => 'Billing details',
                    'label'       => 'Remove billing details for faster checkout',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no',
                ],
                'buy_it_now_mode' => [
                    'title'       => 'Buy it now mode',
                    'label'       => "Enable 'Buy it now' mode",
                    'type'        => 'checkbox',
                    'description' => 'Adding a product to cart redirects the customer to the checkout page.',
                    'default'     => 'no',
                    'desc_tip'    => true,
                ],
            ];
        }

        /**
         * You will need it if you want your custom form
         */
        public function payment_fields() {
            if ( $this->description ) {
                // Instructions for test mode.
                if ( $this->testmode ) {
                    $this->description .= '<br />TEST MODE ENABLED!';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }

            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
         
            // Action hook support for 'woocommerce_credit_card_form_start'.
            do_action( 'woocommerce_credit_card_form_start', $this->id );
         
            // p_pnp_id
            echo '<div class="form-row form-row-wide"><label>Your pingNpay ID <span class="required">*</span></label>
                <input id="p_pnp_id" name="p_pnp_id" type="text" autocomplete="off" value="" placeholder="johnsmith$walletprovider.com" />
                </div>
                <div class="clear"></div>';

            // pNp payment type default to Request to Pay.
            echo '<input id="pnp_payment_type" name="pnp_payment_type" type="hidden" value="rtp" />';
         
            // Action hook support for 'woocommerce_credit_card_form_end'.
            do_action( 'woocommerce_credit_card_form_end', $this->id );
         
            echo '<div class="clear"></div></fieldset>';
        }

        /*
         * Custom CSS and JS for the payment gateway
         */
        public function payment_scripts() {
            if ( 'no' === $this->enabled ) {
                return;
            }

            // Require SSL unless in test mode
            if ( ($this->testmode !== 'yes') && ! is_ssl() ) {
                return;
            }

            // Custom CSS and JS to be loaded on checkout pages
            wp_register_style( 'wc_gateway_pnp', plugin_dir_url(__FILE__).'dist/css/woocommerce-gateway-pnp.css', [], $this->version );
            wp_enqueue_style( 'wc_gateway_pnp');

            wp_register_script( 'wc_gateway_pnp', plugins_url( 'dist/js/woocommerce-gateway-pnp.js', __FILE__ ), ['jquery'], $this->version );
            wp_enqueue_script( 'wc_gateway_pnp' );

            // Only load on the order-received page
            if ( is_checkout() && !empty( is_wc_endpoint_url('order-received') ) ) {
                global $wp;

                wp_register_script( 'wc_gateway_pnp_order_received', plugins_url( 'dist/js/woocommerce-gateway-pnp-order-received.js', __FILE__ ), ['jquery'], $this->version );

                if (is_wc_endpoint_url( 'order-received' )) {
                    $wc_gateway_pnp_order_received_params = [
                        'ajaxurl' => admin_url( 'admin-ajax.php' ),
                        'ajaxnonce' => wp_create_nonce( 'ajax_validation' ),
                    ];
                    $wc_order_id = absint($wp->query_vars['order-received']);
                    if ($wc_order = wc_get_order( $wc_order_id )) {
                        $wc_gateway_pnp_order_received_params['wc_order_id'] = $wc_order_id;
                        $wc_gateway_pnp_order_received_params['wc_order_status'] = $wc_order->get_status();
                        $wc_gateway_pnp_order_received_params['pnp_payment_type'] = $wc_order->get_meta('pnp_payment_type');
                        if ($wc_gateway_pnp_order_received_params['pnp_payment_type'] == "direct") {
                            $wc_gateway_pnp_order_received_params['pnp_direct_payment_payload'] = $wc_order->get_meta('pnp_direct_payment_payload');
                        }
                    }
                    wp_localize_script( 'wc_gateway_pnp_order_received', 'wc_gateway_pnp_order_received_params', $wc_gateway_pnp_order_received_params);
                    wp_enqueue_script( 'wc_gateway_pnp_order_received' );
                }
            }
        }

        /*
         * Fields validation
         */
        public function validate_fields() {
            if( empty( $_POST[ 'p_pnp_id' ]) && $_POST['pnp_payment_type'] == 'rtp' ) {
                wc_add_notice(  'Payer pingNpay ID is required.', 'error' );
                return false;
            }
            return true;
        }

        /*
         * Processing the payment
         */
        public function process_payment( $order_id ) {
            global $woocommerce;

            $order = wc_get_order( $order_id );
            $p_pnp_id = sanitize_text_field( $_POST['p_pnp_id'] );
            $principal_amount = $woocommerce->cart->cart_contents_total;

            $pnp_payment_type = sanitize_text_field( $_POST['pnp_payment_type'] );
            $request = $this->pnp_wc_gateway_build_payment($order_id, $pnp_payment_type, $p_pnp_id, $principal_amount);

            if ($pnp_payment_type == 'rtp') {
                $response = wp_remote_post( $request['url'], $request['args']);
             
                if ( !is_wp_error( $response ) ) {
                    if ($response["response"]["code"] == 201) {
                        // wp_send_json_success();
                        $pnp_invoice_id = $request['pnp_invoice_id'];
                        $order->add_order_note( esc_html( 'pnp_invoice_id: '.$pnp_invoice_id ) );
                        $order->add_meta_data('pnp_invoice_id', $pnp_invoice_id, true);
                        $order->add_meta_data('pnp_payment_type', $pnp_payment_type, true);
                        $order->save();
                    }
                    else {
                        wc_add_notice(  'Try again...', 'error' );
                        return;
                    }
                }
                else {
                    wc_add_notice(  'Connection error.', 'error' );
                    return;
                }
            }
            elseif($pnp_payment_type == 'direct') {
                $pnp_invoice_id = $request['pnp_invoice_id'];
                $order->add_order_note( esc_html( 'pnp_invoice_id: '.$pnp_invoice_id ) );
                $order->add_meta_data('pnp_invoice_id', $pnp_invoice_id, true);
                $order->add_meta_data('pnp_payment_type', $pnp_payment_type, true);
                $order->add_meta_data('pnp_direct_payment_payload', $request['args']['body'], true);
                $order->save();
            }
            else {
                wc_add_notice(  'pnp_payment_type not recognised', 'error' );
                return;
            }
 
            
            $order->add_order_note( esc_html( 'Awaiting payment via pingNpay ('.$pnp_payment_type.')' ) );
 
            // Empty cart
            $woocommerce->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        }

        /**
         * Build the RTP request.
         */
        public function pnp_wc_gateway_build_payment($wc_order_id, $pnp_payment_type, $p_pnp_id, $principal_amount) {
            $pnp_invoice_id = wp_generate_uuid4();
            $timestamp = date("Y-m-d\TH:i:s.000\Z");

            $url = "";
            if ($pnp_payment_type == "rtp") {
                // Extract the API domain from the p_pnp_id.
                $split = explode( '$', $p_pnp_id );
                $domain = sanitize_text_field( $split[1] );
                // Check domain is on the allowed list.
                $allowed_domains = [
                    'west.pingnpay.com',
                    'pnpd.io'
                ];
                if ( ! in_array($domain, $allowed_domains)) {
                    throw new Exception("Invalid domain {$domain} for API RTP endpoint.");
                }
                $url = "https://api.{$domain}/rtp/{$p_pnp_id}";
            }
            
            $site_url = get_site_url();
            // $site_url = "https://xxxx-xxx-xxx-xxx-xx.eu.ngrok.io"; // DEV ONLY: proxy from the internet to localhost.
            $callback = $site_url."?wc-api=wc_pnp".'&wc_order_id='.$wc_order_id;

            $args = [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    "payment_type" => $pnp_payment_type,
                    "r_pnp_id" => sanitize_text_field( $this->r_pnp_id ),
                    "r_account_type" => "PERSONAL",
                    "principal_amount" => $principal_amount,
                    "iso_currency_code" => "GBP",
                    "initiation_type" => "ECOMM_WP",
                    "invoice_id" => $pnp_invoice_id,
                    "invoice_timestamp" => $timestamp,
                    "invoice_due_date" => $timestamp,
                    "transaction_notes" => "Order #".$wc_order_id,
                    "ecomm_callback_url" => $callback,
                ]),
            ];
            return [
                'url' => $url,
                'args' => $args,
                'pnp_invoice_id' => $pnp_invoice_id,
            ];
        }

        /*
         * In case you need a webhook
         */
        public function webhook() {
            if (isset($_GET['wc_order_id'])) {
                $wc_order_id = intval( sanitize_text_field( $_GET['wc_order_id'] ) );
                if ($order = wc_get_order( $wc_order_id )) {
                    // If error
                    if (isset($_GET['ecomm_error_message'])) {
                        $error = sanitize_text_field( $_GET['ecomm_error_message'] );
                        $order->update_status('failed', 'ecomm_error_message: '.$error);
                        $order->add_meta_data('pnp_ecomm_error_message', $error);
                        $order->save();
                        header( 'HTTP/1.1 201' );
                        die();
                    }
                    // Compare pnp_invoice_id
                    if (isset($_GET['invoice_id'])) {
                        $pnp_invoice_id = sanitize_key( sanitize_text_field( $_GET['invoice_id'] ) );
                        $order_pnp_invoice_id = $order->get_meta('pnp_invoice_id');
                        if ($pnp_invoice_id !== $order_pnp_invoice_id) {
                            $error = 'pnp invoice id mismatch.';
                            $order->update_status('failed', $error);
                            wp_send_json( [ 'error' => $error ], 404);
                            die();
                        }
                    }
                    // Compare principal_amount
                    if (isset($_GET['principal_amount'])) {
                        $pnp_principal_amount = sanitize_text_field( $_GET['principal_amount'] );
                        $wc_order_total = $order->get_total();
                        if ($pnp_principal_amount != $wc_order_total) {
                            $error = 'pnp principal amount incorrect.';
                            $order->update_status('failed', $error);
                            wp_send_json( [ 'error' => $error ], 404);
                            die();
                        }
                    }
                    // Save e2e_id as meta
                    if (isset($_GET['e2e_id'])) {
                        $pnp_e2e_id = sanitize_key( sanitize_text_field( $_GET['e2e_id'] ) );
                        // Check valid UUID4
                        if (!is_string($pnp_e2e_id) || (preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i', $pnp_e2e_id) !== 1)) {
                            $error = 'pnp e2e id is malformed.';
                            $order->update_status('failed', $error);
                            wp_send_json( [ 'error' => $error ], 404);
                            die();
                        }
                        else {
                            $order->add_order_note( esc_html( 'pnp_e2e_id: '.$pnp_e2e_id ) );
                            $order->add_meta_data('pnp_e2e_id', $pnp_e2e_id, true);
                            $order->save();

                            // Check if any products are Pay to view
                            $products = $order->get_items();
                            foreach ( $products as $product ) {
                                $product_id = $product['product_id'];
                                // Pay to view
                                if ( get_post_meta($product_id, '_pay_to_view', true ) === 'yes' ) {
                                    $product_url = get_permalink($product_id);
                                    $product_path = parse_url($product_url, PHP_URL_PATH);
                                    $pay_to_view_token = wp_generate_uuid4();

                                    $redirect_path = $product_path;
                                    $redirect_path .= '?wc_order_id='.$wc_order_id;
                                    $redirect_path .= '&pay_to_view=1';
                                    $redirect_path .= '&pay_to_view_token=' . $pay_to_view_token;

                                    $site_url = parse_url(site_url(), PHP_URL_SCHEME).'://'.parse_url(site_url(), PHP_URL_HOST);
                                    $pay_to_view_url = $site_url . $redirect_path;
                                    $pay_to_view_url = esc_url( sanitize_url( $pay_to_view_url ) );

                                    $order->add_order_note( '<a href="' . $pay_to_view_url . '" target="_blank">Pay to view URL</a><br />' . $pay_to_view_url );

                                    $order->add_meta_data('pnp_pay_to_view', 1, true);
                                    // $order->add_meta_data('pnp_pay_to_view_url', $pay_to_view_url, true);
                                    $order->add_meta_data('pnp_pay_to_view_token', $pay_to_view_token, true);
                                    $order->add_meta_data('pnp_redirect', $redirect_path, true);
                                    $order->save();

                                    break;
                                }
                                // Pay to tip
                                if ( get_post_meta($product_id, '_pay_to_tip', true ) === 'yes' ) {
                                    $product_url = get_permalink($product_id);
                                    $product_path = parse_url($product_url, PHP_URL_PATH);

                                    $redirect_path = $product_path;
                                    $redirect_path .= '?pay_to_tip=1';

                                    $site_url = parse_url(site_url(), PHP_URL_SCHEME).'://'.parse_url(site_url(), PHP_URL_HOST);

                                    $order->add_meta_data('pnp_pay_to_tip', 1, true);
                                    $order->add_meta_data('pnp_redirect', $redirect_path, true);
                                    $order->save();

                                    break;
                                }
                            }

                            /**
                             * SUCCESS! Payment complete.
                             */
                            $order->payment_complete();
                            $order->add_order_note( esc_html( 'Your order has been paid via pingNpay.' ) );
                            
                            header( 'HTTP/1.1 201' );
                            die();
                        }
                    }
                    else {
                        $error = 'pnp e2e id expected.';
                        $order->update_status('failed', $error);
                        wp_send_json( [ 'error' => $error ], 404);
                        die();
                    }
                }
                else {
                    $error = 'wc order id not found: '.$wc_order_id;
                    wp_send_json( [ 'error' => $error ], 404);
                    die();
                }
            }
            header( 'HTTP/1.1 404' );
            die();
        }

        /**
         * Disable billing details
         */
        public function pnp_wc_gateway_remove_checkout_fields( $fields ) {
            // unset( $fields['billing']['billing_email'] );
            $fields['billing']['billing_email'] = "no-reply@pingnpay.com";

            unset( $fields['billing']['billing_first_name'] );
            unset( $fields['billing']['billing_last_name'] );
            unset( $fields['billing']['billing_company'] );
            unset( $fields['billing']['billing_city'] );
            unset( $fields['billing']['billing_postcode'] );
            unset( $fields['billing']['billing_country'] );
            unset( $fields['billing']['billing_state'] );
            unset( $fields['billing']['billing_address_1'] );
            unset( $fields['billing']['billing_address_2'] );
            unset( $fields['billing']['billing_phone'] );
            return $fields;
        }
        // Set billing address fields to not required
        public function pnp_wc_gateway_unrequire_checkout_fields( $fields ) {
            $fields['billing']['billing_email']['required']   = false;
            $fields['billing']['billing_first_name']['required']   = false;
            $fields['billing']['billing_last_name']['required']   = false;
            $fields['billing']['billing_company']['required']   = false;
            $fields['billing']['billing_city']['required']      = false;
            $fields['billing']['billing_postcode']['required']  = false;
            $fields['billing']['billing_country']['required']   = false;
            $fields['billing']['billing_state']['required']     = false;
            $fields['billing']['billing_address_1']['required'] = false;
            $fields['billing']['billing_address_2']['required'] = false;
            $fields['billing']['billing_phone']['required'] = false;
            return $fields;
        }
        /**
         * Buy it now mode
         */
        // Change add to cart text
        public function pnp_wc_gateway_woocommerce_add_to_cart_button_text_single() {
            return __( 'Buy it now', 'woocommerce' );
        }
        public function pnp_wc_gateway_woocommerce_add_to_cart_button_text_archives() {
            return __( 'Buy it now', 'woocommerce' );
        }
        // Redirect to checkout
        public function pnp_wc_gateway_woocommerce_add_to_cart_redirect( $url, $adding_to_cart ) {
            return wc_get_checkout_url();
        }
    }

    /**
     * Register filters for changing the WC checkout flow.
     */
    $WC_PNP_Gateway = new WC_PNP_Gateway();
    if ($WC_PNP_Gateway->enabled === 'yes') {
        // Register filters to remove billing details
        if ($WC_PNP_Gateway->remove_billing_details === 'yes') {
            add_filter( 'woocommerce_checkout_fields', [$WC_PNP_Gateway, 'pnp_wc_gateway_remove_checkout_fields'], 100 );
            add_filter( 'woocommerce_checkout_fields', [$WC_PNP_Gateway, 'pnp_wc_gateway_unrequire_checkout_fields'] );
        }
        // Register filters for Buy it now mode
        if ($WC_PNP_Gateway->buy_it_now_mode === 'yes') {
            add_filter( 'woocommerce_product_single_add_to_cart_text', [$WC_PNP_Gateway, 'pnp_wc_gateway_woocommerce_add_to_cart_button_text_single'] );
            add_filter( 'woocommerce_product_add_to_cart_text', [$WC_PNP_Gateway, 'pnp_wc_gateway_woocommerce_add_to_cart_button_text_archives' ]);
            add_filter ('woocommerce_add_to_cart_redirect', [$WC_PNP_Gateway, 'pnp_wc_gateway_woocommerce_add_to_cart_redirect'], 10, 2 );
        }
    }

    /**
     * AJAX hook to check order status
     */
    add_action('wp_ajax_pnp_wc_gateway_check_order_status', 'pnp_wc_gateway_check_order_status');
    add_action('wp_ajax_nopriv_pnp_wc_gateway_check_order_status', 'pnp_wc_gateway_check_order_status');

    function pnp_wc_gateway_check_order_status() {
        // Check CSRF token
        check_ajax_referer( 'ajax_validation', 'ajaxnonce' );

        if (isset($_POST['wc_order_id'])) {
            $wc_order_id = intval( sanitize_text_field( $_POST['wc_order_id'] ) );
            if ($order = wc_get_order( $wc_order_id )) {
                $order_status = $order->get_status();

                $payload = [
                    'wc_order_id' => $wc_order_id,
                    'order_status' => $order_status,
                ];

                if ($redirect = $order->get_meta('pnp_redirect')) {
                    $payload['redirect'] = $redirect;
                }

                // Any ecomm error messages
                if ($error = $order->get_meta('pnp_ecomm_error_message')) {
                    $payload['error'] = $error;
                }
                wp_send_json($payload, 200);
            }
        }
        $error = 'wc order id not found: '.$wc_order_id;
        wp_send_json( [ 'error' => $error ], 404);
    }

    /**
     * pay_to_view
     *
     * Add Pay to view custom product option to products
     */
    add_filter( 'product_type_options', 'pnp_wc_gateway_custom_product_field_pay_to_view' );
    add_action( 'woocommerce_admin_process_product_object', 'pnp_wc_gateway_custom_product_field_pay_to_view_save' );

    function pnp_wc_gateway_custom_product_field_pay_to_view( $fields ) {
        $fields['pay_to_view'] = array(
            'id'                => '_pay_to_view',
            'wrapper_class'     => '',
            'label'             => __('Pay to view'),
            'description'   => __( 'The user will checkout on the product page and then return to the page to view full content', 'woocommerce' ),
            'default'           => 'no'
        );
        return $fields;
    }
    function pnp_wc_gateway_custom_product_field_pay_to_view_save( $product ) {
        $product->update_meta_data( '_pay_to_view', isset( $_POST['_pay_to_view'] ) ? 'yes' : 'no' );
    }

    /**
     * pay_to_view
     * Automatically add product to cart on visit if pay_to_view.
     */
    add_action( 'template_redirect', 'pnp_wc_gateway_auto_add_product_to_cart' );

    function pnp_wc_gateway_auto_add_product_to_cart() {
        if ( is_product() ) {
            if ( get_post_meta(get_the_ID(), '_pay_to_view', true ) === 'yes' ) {
                $product_id = get_the_ID();
                $found = false;
                //check if product already in cart
                if ( sizeof( WC()->cart->get_cart() ) > 0 ) {
                    foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
                        $_product = $values['data'];
                        if ( $_product->get_id() == $product_id )
                            $found = true;
                    }
                    // if product not found, add it
                    if ( ! $found )
                        WC()->cart->add_to_cart( $product_id );
                } else {
                    // if no products in cart, add it
                    WC()->cart->add_to_cart( $product_id );
                }
            }
        }
    }

    /**
     * pay_to_view
     * Load custom template part for content-single-product-pay-to-view.php
     */
    add_filter('wc_get_template_part', function($template, $slug, $name){
        // Custom theme templates have priority
        if(strpos($template, '/themes/') !== FALSE) return $template;

        $template_name = '';
        if($name){
            $template_name = "{$slug}-{$name}.php";
        } else if($slug){
            $template_name = "{$slug}.php";
        }
        if(!$template_name) return $template;

        static $cache = array();
        if(isset($cache[$template_name])) return $cache[$template_name];

        $plugin_template = untrailingslashit(plugin_dir_path( __FILE__ )) .'/templates/'.$template_name;
        if($plugin_template && file_exists($plugin_template)){
            $template = $plugin_template;
            $cache[$template_name] = $template;
        }
        return $template;
    }, 20, 3);

    /**
     * pay_to_view
     * Load custom template for single-product.php
     */
    add_filter( 'woocommerce_template_loader_files', function( $templates, $template_name ){
        // Capture/cache the $template_name which is a file name like single-product.php
        wp_cache_set( 'pnp_wc_gateway_wc_main_template', $template_name );
        return $templates;
    }, 10, 2 );

    add_filter( 'template_include', function( $template ){
        if ( $template_name = wp_cache_get( 'pnp_wc_gateway_wc_main_template' ) ) {
            wp_cache_delete( 'pnp_wc_gateway_wc_main_template' );
            $plugin_template = untrailingslashit(plugin_dir_path( __FILE__ )) .'/templates/'.$template_name;

            if ( $plugin_template && file_exists($plugin_template) ) {
                return $plugin_template;
            }
        }
        return $template;
    }, 11 );

    /**
     * pay_to_view
     * WP sidebar for use on the pay_to_view template
     */
    register_sidebar([
        'name' => __( 'pingNpay Sidebar (Pay to View)' ),
        'id' => 'sidebar-pnp-pay-to-view',
    ]);

    /**
     * pay_to_tip
     *
     * Add Pay to tip custom product option to products
     */
    add_filter( 'product_type_options', 'pnp_wc_gateway_custom_product_option_pay_to_tip' );
    add_action( 'woocommerce_admin_process_product_object', 'pnp_wc_gateway_custom_product_option_pay_to_tip_save' );

    function pnp_wc_gateway_custom_product_option_pay_to_tip( $fields ) {
        $fields['pay_to_tip'] = array(
            'id'                => '_pay_to_tip',
            'wrapper_class'     => '',
            'label'             => __('Pay to tip'),
            'description'   => __( 'The user will pay a tip fee on the product page and then return to the same product page', 'woocommerce' ),
            'default'           => 'no'
        );
        return $fields;
    }
    function pnp_wc_gateway_custom_product_option_pay_to_tip_save( $product ) {
        $product->update_meta_data( '_pay_to_tip', isset( $_POST['_pay_to_tip'] ) ? 'yes' : 'no' );
    }

    /**
     * pay_to_tip
     *
     * Add Pay to tip custom fields to products
     * CURRENTLY NOT IN USE - but a beautiful bit of code nevertheless
     */
    // add_action( 'woocommerce_product_options_general_product_data', 'pnp_wc_gateway_custom_product_field_pay_to_tip__fields' );
    // add_action( 'woocommerce_process_product_meta', 'pnp_wc_gateway_custom_product_field_pay_to_tip__fields_save' );

    function pnp_wc_gateway_custom_product_field_pay_to_tip__fields () {
        global $woocommerce, $post;
        echo '<div class="pnp_wc_gateway_custom_product_field_pay_to_tip__fields">';
        // Modal textarea
        woocommerce_wp_textarea_input(
            array(
                'id' => '_pay_to_tip__modal',
                'placeholder' => '',
                'label' => __('Pay to Tip: Modal Textarea', 'woocommerce')
            )
        );
        echo '</div>';
    }
    function pnp_wc_gateway_custom_product_field_pay_to_tip__fields_save ($post_id) {
        $pay_to_tip__modal = sanitize_textarea_field( $_POST['_pay_to_tip__modal'] );
        if (!empty($pay_to_tip__modal)) {
            update_post_meta($post_id, '_pay_to_tip__modal', esc_html( $pay_to_tip__modal ) );
        }
    }

    /**
     * pay_to_tip
     * Change add to cart btn text
     */
    add_filter( 'woocommerce_product_single_add_to_cart_text', function() {
        if ( is_product() ) {
            if ( get_post_meta(get_the_ID(), '_pay_to_tip', true ) === 'yes' ) {
                return __( 'Pay to tip', 'woocommerce' );
            }
        }
    });

    /**
     * pay_to_tip
     * Redirect to checkout on add to cart
     */
    add_filter ('woocommerce_add_to_cart_redirect', function( $url, $product ) {
        if ( is_object($product) && (get_post_meta($product->get_id(), '_pay_to_tip', true ) === 'yes') ) {
            return wc_get_checkout_url();
        }
    }, 10, 2 );

    /**
     * pay_to_tip
     * 
     * Add body class 'pnp-pay-to-tip'
     * 
     * If redirect url param pay_to_tip=1 then
     * Add body class 'pnp-pay-to-tip-success'
     * Hide Add to cart
     */
    add_action( 'template_redirect', 'pnp_wc_gateway_pay_to_tip_success' );

    function pnp_wc_gateway_pay_to_tip_success() {
        if ( is_product() ) {
            if ( get_post_meta(get_the_ID(), '_pay_to_tip', true ) === 'yes' ) {
                // Add class 'pnp-pay-to-tip'
                add_filter( 'body_class', function( $classes ) {
                    $classes[] = 'pnp-pay-to-tip';
                    return $classes;
                });
                $pay_to_tip = null;
                if (isset($_GET['pay_to_tip'])) {
                    $pay_to_tip = intval( sanitize_text_field( $_GET['pay_to_tip'] ) );
                }
                if ( $pay_to_tip == 1 ) {
                    // Remove Add to cart
                    // remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);

                    // Add class 'pnp-pay-to-tip-success'
                    add_filter( 'body_class', function( $classes ) {
                        $classes[] = 'pnp-pay-to-tip-success';
                        return $classes;
                    });
                }
            }
        }
    }

    /**
     * pay_to_tip
     * Shortcode for adding price buttons to Name your Price plugin
     * [pnp_gateway_name_your_price_buttons prices="1.00,3.00,5.00"]
     */
    function shortcode_pnp_gateway_name_your_price_buttons($atts){
        $a = shortcode_atts( [
            'prices' => [],
        ], $atts );

        if ($a['prices']) {
            $prices = explode(',', $a['prices']);
        }
        else {
            return "Prices attribute missing. Use comma separated values.";
        }

        ob_start();
        ?><div class="pnp-gateway-name-your-price-buttons"><?php
            foreach($prices as $price) {
            ?>
                <a href="#" data-price="<?php echo esc_attr( $price ); ?>"><?php echo get_woocommerce_currency_symbol() . esc_attr( $price ); ?></a>
            <?php
            }
        ?></div><?php
        return ob_get_clean();
    }
    add_shortcode( 'pnp_gateway_name_your_price_buttons', 'shortcode_pnp_gateway_name_your_price_buttons' );

    /**
     * pay_to_tip
     * Change "Place order" text if pay_to_tip product in cart
     */
    add_filter( 'woocommerce_order_button_text', function ( $button_text ) {
        if ( sizeof( WC()->cart->get_cart() ) > 0 ) {
            foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
                $_product = $values['data'];
                if ( get_post_meta($_product->get_id(), '_pay_to_tip', true ) === 'yes' ) {
                    $found = true;
                }
            }
            if ($found ) {
                $button_text = 'Pay tip!';
            }
        }
        return $button_text;
    });
}
