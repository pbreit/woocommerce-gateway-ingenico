<?php
/**
 * Class WC_Gateway_Ingenico_Request file.
 *
 */

defined( 'ABSPATH' ) or exit;

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
    public function __construct( $gateway ) {
        $this->gateway = $gateway;
        $this->endpoint = $this->gateway->get_option('api_url');
    }

    /**
     * Get the Ingenico payment URL.
     *
     * @param  WC_Order $order Order object.
     * @return string
     */
    public function get_payment_url( $order ) {
        $amountOfMoney = array(
          'currencyCode' => get_woocommerce_currency(),
          'amount' => round( $order->get_total() * 100 )
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
          'locale' => 'en_US',
          'showResultPage' => false,
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
                $order->set_transaction_id( $response['hostedCheckoutId'] );
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
    public function get_payment_details( $order ) {

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
     * Validate transaction status and authenticity
     *
     * @param  string $transaction TX ID.
     * @return bool|array False or result array if successful and valid.
     */
    public function validate_transaction( $hosted_id ) {

      $path = '/v1/'.$this->gateway->get_option('merchant_id').'/hostedcheckouts/'.$hosted_id;

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
}
