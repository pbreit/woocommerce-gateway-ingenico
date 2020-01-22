<?php
/*
  Plugin Name: Ingenico Payment Gateway
  Plugin URI:
  Description: WooCommerce payment plugin for Ingenico Payment Gateway
  Version: 0.1
 */

defined( 'ABSPATH' ) or exit;

add_action( 'plugins_loaded', 'woocommerce_ingenico', 0 );


function woocommerce_ingenico() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) )
        return;
    if ( class_exists( 'WC_Gateway_Ingenico' ) )
        return;

    class WC_Gateway_Ingenico extends WC_Payment_Gateway {
        public static $log_enabled = false;
        public static $log = false;

        public function __construct() {
            global $woocommerce;

            $this->id = 'ingenico';
            $this->icon = apply_filters('woocommerce_ingenico_icon', '' . plugin_dir_url(__FILE__) . 'ingenico.png');
            $this->has_fields = false;
            $this->order_button_text = __('Proceed to Ingenico', 'woocommerce');
            $this->method_title = __('Ingenico', 'woocommerce');
            $this->method_description = __('Redirects customers to Ingenico to enter their payment information.', 'woocommerce');

            $this->supports = array(
                'products',
            );
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->testmode = 'yes' === $this->get_option('testmode', 'no');
            $this->debug = 'yes' === $this->get_option('debug', 'no');
            $this->email = $this->get_option('email');
            $this->receiver_email = $this->get_option('receiver_email', $this->email);
            $this->api_url = $this->get_option('api_url');
            $this->api_token = $this->get_option('api_token');
            $this->api_secret = $this->get_option('api_secret');
            $this->api_merchant_id = $this->get_option('merchant_id');
            self::$log_enabled = $this->debug;

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            if ( ! $this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        /**
         * Logging method.
         *
         * @param string $message Log message.
         * @param string $level Optional. Default 'info'. Possible values:
         *     emergency|alert|critical|error|warning|notice|info|debug
         */
        public static function log( $message, $level = 'info' ) {
            if ( self::$log_enabled ) {
                if ( empty( self::$log ) ) {
                    self::$log = wc_get_logger();
                }
                self::$log->log( $level, $message, array( 'source' => 'ingenico' ) );
            }
        }

        /**
         * Get gateway icon.
         *
         * @return string
         */
        public function get_icon() {
            $icon_html = sprintf('<a href="%1$s" class="about_ingenico" onclick="javascript:window.open(\'%1$s\',\'WIOP\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . esc_attr__('What is Ingenico?', 'woocommerce') . '</a>', esc_url($this->get_icon_url($base_country)));

            $icon_html .= '<div style="text-align:center;"><img style="max-width:200px;width:100%;max-height:none;float:none;margin:0 auto;" src="' . esc_attr($this->get_icon_image($base_country)) . '" alt="' . esc_attr__('Ingenico', 'woocommerce') . '" /></div>';
            return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
        }

        /**
         * Get the link for an icon based on country.
         *
         * @param  string $country Country two letter code (ignored).
         * @return string
         */
        protected function get_icon_url($country) {
            return 'https://epayments.developer-ingenico.com/';
        }

        /**
         * Get Ingenico image.
         *
         * @param string $country Country code. (ignored)
         * @return image URL
         */
        protected function get_icon_image($country) {
            $icon = plugin_dir_url(__FILE__) . 'assets/images/ingenico.png';
            return apply_filters('woocommerce_ingenico_icon', $icon);
        }

        /**
         * Check if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use() {
            return in_array(
                get_woocommerce_currency(),
                apply_filters( 'woocommerce_ingenico_supported_currencies', array( 'AUD', 'CAD', 'CHF', 'EUR', 'GBP', 'RUB', 'USD' )),
                true);
        }

        /**
         * Admin Panel Options.
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         */
        public function admin_options() {
            if ( $this->is_valid_for_use() ) {
                parent::admin_options();
            } else {
                ?>
                <div class="inline error">
                    <p>
                        <strong><?php esc_html_e('Gateway disabled', 'woocommerce'); ?></strong>: <?php esc_html_e('Ingenico does not support your store currency.', 'woocommerce'); ?>
                    </p>
                </div>
                <?php
            }
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {
            $this->form_fields = include 'includes/settings-ingenico.php';
        }

        /**
         * Process the payment and return the result.
         *
         * @param  int $order_id Order ID.
         * @return array
         */
        public function process_payment( $order_id ) {
            global $woocommerce;

            $order = wc_get_order( $order_id );

            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-ingenico-request.php';
            $request = new WC_Gateway_Ingenico_Request( $this );

            if ($url = $request->get_payment_url( $order )) {
                $woocommerce->cart->empty_cart();
                return array(
                    'result'   => 'success',
                    'redirect' => $url,
                );
            }
            return new WP_Error('error', __('Ingenico - Could not initialize transaction.', 'woocommerce'));
        }

        /**
         * Check payment status
         */
         function check_response( $order_id ) {
             $order = wc_get_order( $order_id );
             if ( !$order || !$order->needs_payment() ) {
                 return false;
             }
             $hosted_id = $order->get_transaction_id();

             include_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-ingenico-request.php';
             $request = new WC_Gateway_Ingenico_Request();

             if ( $status = $ingenico_request->validate_transaction( $hosted_id ) ) {
                 $order->payment_complete();
             } else {
                 WC_Gateway_Ingenico::log('Received invalid response from Ingenico');
             }
         }
    }

    /**
     * Check payment status
     */
     function thank_you( $order_id ) {
         WC_Gateway_Ingenico::check_response( $order_id );
     }

    add_action( 'woocommerce_thankyou', 'thank_you', 0 );

    /**
     * Add the gateway to WooCommerce
     **/
    function add_ingenico_gateway( $gateways ) {
        $gateways[] = 'WC_Gateway_Ingenico';
        return $gateways;
    }

    add_filter( 'woocommerce_payment_gateways', 'add_ingenico_gateway' );
}
