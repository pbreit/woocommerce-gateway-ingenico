<?php
/**
 * Class WC_Gateway_Ingenico_Request file.
 *
 */

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Generates requests to send to Ingenico API.
 */
class WC_Gateway_Ingenico_Request
{
    /**
     * Pointer to gateway making the request.
     *
     * @var WC_Gateway_Ingenico
     */
    protected $gateway;

    /**
     * Endpoint for requests from Ingenico.
     *
     * @var string
     */
    protected $notify_url;

    /**
     * Endpoint for requests to Ingenico.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Constructor.
     *
     * @param WC_Gateway_Ingenico $gateway Ingenico gateway object.
     */
    public function __construct($gateway)
    {
        $this->gateway = $gateway;
        $this->notify_url = WC()->api_request_url('ingenico_webhook');
        $this->endpoint = $this->gateway->get_option('api_url');
        add_action( 'woocommerce_thankyou_ingenico', array( $this, 'check_response' ) );
    }

    /**
     * Get the Ingenico payment URL.
     *
     * @param  WC_Order $order Order object.
     * @return string
     */
    public function get_payment_url($order)
    {
        $amountOfMoney = array(
          'currencyCode' => get_woocommerce_currency(),
          'amount' => $order->get_total() * 100
        );
        $billingAddress = array(
          'countryCode' => 'US'
        );
        $customer = array(
          'billingAddress' => $billingAddress,
          'merchantCustomerId' => $order->get_id()
        );
        $references = array(
          'merchantReference' => $order->get_id()
        );
        $_order = array(
          'amountOfMoney' => $amountOfMoney,
          'customer' => $customer,
          'references' => $references
        );
        $hostedCheckoutSpecificInput = array(
          'local' => 'en_GB',
          'returnUrl' => esc_url_raw(add_query_arg('utm_nooverride', '1', $this->gateway->get_return_url($order)))
        );
        $request = array(
          'order' => $_order,
          'hostedCheckoutSpecificInput' => $hostedCheckoutSpecificInput
        );

        $mask = array(
            'api_token' => '***',
        );

        WC_Gateway_Ingenico::log('Ingenico - get_payment_url() request parameters: '.
          $order->get_order_number().': '.
          wc_print_r(array_merge($request, array_intersect_key($mask, $request)), true));

        $request = apply_filters('woocommerce_gateway_payment_url', $request, $order);

        $path = '/v1/'.$this->gateway->get_option('merchant_id').'/hostedcheckouts';

        $requestHeaders = array();
        $requestHeaders['Content-Type'] = 'application/json;';
        $requestHeaders['Date'] = gmdate('D, d M Y H:i:s \G\M\T', time());

        $dataToHash = "POST\n".$requestHeaders['Content-Type']."\n".$requestHeaders['Date']."\n".$path."\n";

        $requestHeaders['Authorization'] = 'GCS v1HMAC:'.
            $this->gateway->get_option('api_token').':'.
            base64_encode(
                hash_hmac(
                    'sha256',
                    $dataToHash,
                    $this->gateway->get_option('api_secret'),
                    true
                )
            );

        $raw_response = wp_safe_remote_post(
            $this->endpoint . $path,
            array(
                'method'      => 'POST',
                'timeout'     => 30,
                'user-agent'  => 'WooCommerce/' . WC()->version,
                'headers'     => $requestHeaders,
                'body'        => json_encode($request),
                'httpversion' => '1.1',
            )
        );

        WC_Gateway_Ingenico::log('Ingenico - get_payment_url() response: ' . wc_print_r($raw_response, true));

        if (isset($raw_response['body']) && ($response = json_decode($raw_response['body'], true))) {
            if (isset($response['partialRedirectUrl'])) {
                return 'https://payment.'.$response['partialRedirectUrl'];
            }
        }

        return null;
    }

    /**
     * Get the Ingenico payment details.
     *
     * @param  WC_Order $order Order object.
     * @return array
     */
    public function get_payment_details($order)
    {
        WC_Gateway_Ingenico::log('Ingenico - get_payment_details() request parameters: '.
            $order->get_order_number().': '.
            wc_print_r(array_merge($request, array_intersect_key($mask, $request)), true));

        $request = apply_filters('woocommerce_gateway_payment_url', $request, $order);

        $path = '/v1/'.$this->gateway->get_option('merchant_id').'/payments?merchantReference='.$order->get_id();

        $requestHeaders = array();
        $requestHeaders['Date'] = gmdate('D, d M Y H:i:s \G\M\T', time());
        $dataToHash = "GET\n\n".$requestHeaders['Date']."\n".$path."\n";
        $requestHeaders['Authorization'] = 'GCS v1HMAC:'.
            $this->gateway->get_option('api_token').':'.
            base64_encode(
                hash_hmac(
                    'sha256',
                    $dataToHash,
                    $this->gateway->get_option('api_secret'),
                    true
                )
            );

        $raw_response = wp_safe_remote_get(
            $this->endpoint . $path,
            array(
                'method'      => 'GET',
                'timeout'     => 30,
                'user-agent'  => 'WooCommerce/' . WC()->version,
                'headers'     => $requestHeaders,
                'httpversion' => '1.1',
            )
        );

        WC_Gateway_Ingenico::log('Ingenico - get_payment_details() response: ' . wc_print_r($raw_response, true));

        if (isset($raw_response['body']) && ($response = json_decode($raw_response['body'], true))) {
            if (isset($response['payments'])) {
                return $response['payments'][0]['id'];
            }
        }

        return null;
    }

    /**
     * Make refund for the Ingenico payment.
     *
     * @param  WC_Order $order Order object.
     * @return array
     */
    public function make_payment_refund($order, $amount)
    {

        if ( ! ($payment_id = $this->get_payment_details($order))) {
            return null;
        }

        $data = array();
        $data['amountOfMoney']['currencyCode'] = get_woocommerce_currency();
        $data['amountOfMoney']['amount'] = $order->get_total() * 100;

        WC_Gateway_Ingenico::log('Ingenico - make_payment_refund() request parameters: '.
            $order->get_order_number().': '.
            wc_print_r(array_merge($request, array_intersect_key($mask, $request)), true));

        $request = apply_filters('woocommerce_gateway_payment_url', $request, $order);
        $path = '/v1/'.$this->gateway->get_option('merchant_id').'/payments/'.$payment_id.'/refund';
        $requestHeaders = array();
        $requestHeaders['Content-Type'] = 'application/json;';
        $requestHeaders['Date'] = gmdate('D, d M Y H:i:s \G\M\T', time());
        $dataToHash = "POST\n".$requestHeaders['Content-Type']."\n".$requestHeaders['Date']."\n".$path."\n";
        $requestHeaders['Authorization'] = 'GCS v1HMAC:'.
            $this->gateway->get_option('api_token').':'.
            base64_encode(
                hash_hmac(
                    'sha256',
                    $dataToHash,
                    $this->gateway->get_option('api_secret'),
                    true
                )
            );

        $raw_response = wp_safe_remote_get(
            $this->endpoint . $path,
            array(
                'method'      => 'GET',
                'timeout'     => 30,
                'user-agent'  => 'WooCommerce/' . WC()->version,
                'headers'     => $requestHeaders,
                'httpversion' => '1.1',
            )
        );

        WC_Gateway_Ingenico::log('Ingenico - make_payment_refund() response: ' . wc_print_r($raw_response, true));

        if (isset($raw_response['body']) && ($response = json_decode($raw_response['body'], true))) {
            if (isset($response['payments'][0]['id'])) {
                return $response['payments'][0]['id'];
            }
        }

        return null;
    }

    /**
     * Validate transaction status and authenticity
     *
     * @param  string $transaction TX ID.
     * @return bool|array False or result array if successful and valid.
     */
    protected function validate_transaction( $transaction ) {

      WC_Gateway_Ingenico::log('Ingenico - validate_transaction() request parameters: '.
          $order->get_order_number().': '.
          wc_print_r(array_merge($request, array_intersect_key($mask, $request)), true));

      $request = apply_filters('woocommerce_gateway_payment_url', $request, $order);

      $path = '/v1/'.$this->gateway->get_option('merchant_id').'/hostedcheckouts/'.$transactionid;

      $requestHeaders = array();
      $requestHeaders['Date'] = gmdate('D, d M Y H:i:s \G\M\T', time());
      $dataToHash = "GET\n\n".$requestHeaders['Date']."\n".$path."\n";
      $requestHeaders['Authorization'] = 'GCS v1HMAC:'.
          $this->gateway->get_option('api_token').':'.
          base64_encode(
              hash_hmac(
                  'sha256',
                  $dataToHash,
                  $this->gateway->get_option('api_secret'),
                  true
              )
          );

      $res = wp_safe_remote_get(
          $this->endpoint . $path,
          array(
              'method'      => 'GET',
              'timeout'     => 30,
              'user-agent'  => 'WooCommerce/' . WC()->version,
              'headers'     => $requestHeaders,
              'httpversion' => '1.1',
          )
      );

      WC_Gateway_Ingenico::log('Ingenico - get_payment_details() response: ' . wc_print_r($res, true));

      if ( is_wp_error( $res ) ) {
          return false;
      }

      $body = wp_remote_retrieve_body( $res );
      $json = json_decode( $body );
      if (isset($response['status']) &&  $response['status']=='PAYMENT_CREATED') {
        return true;
      }

      return null;
    }

    /**
     * Check payment status
     */
    public function check_response() {
      if ( empty( $_REQUEST['hostedCheckoutId'] )) {
        return;
      }
      $transaction    = wc_clean( wp_unslash( $_REQUEST['hostedCheckoutId'] ) );
      if ( ! $order || ! $order->needs_payment() ) {
        return false;
      }
      $transaction_result = $this->validate_transaction( $transaction );
      if ( $transaction_result ) {
        WC_Gateway_Ingenico::log( 'check_response(): ' . wc_print_r( $status, true ) );
        $this->payment_complete( $order, $transaction, __( 'Payment completed', 'woocommerce' ) );
      } else {
        WC_Gateway_Ingenico::log( 'Received invalid response from Ingenico' );
      }
    }
}
