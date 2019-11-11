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
        $_order = array(
          'amountOfMoney' => $amountOfMoney,
          'customer' => $customer
        );
        $hostedCheckoutSpecificInput = array(
          'local' => 'en_GB',
          'returnUrl' => esc_url_raw(add_query_arg('utm_nooverride', '1', $this->gateway->get_return_url($order)))
        );
        $request = array(
          'order' => $_order,
          'hostedCheckoutSpecificInput' => $hostedCheckoutSpecificInput
        );

/*
  'reference_id'       => $order->get_order_key(),
  'pay_method'         => 'card',
  'email'              => $this->limit_length($order->get_billing_email()),
  'description'        => 'Payment for order #' . $order->get_id(),
  'amount'             => $order->get_total(),
  'currency'           => get_woocommerce_currency(),
  'return_success_url' => esc_url_raw(add_query_arg('utm_nooverride', '1', $this->gateway->get_return_url($order))),
  'return_error_url'   => esc_url_raw($order->get_cancel_order_url_raw()),
  'callback_url'       => $this->notify_url,
*/

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
        $raw_response = wp_safe_remote_get(
            $this->endpoint . '/charges/' . $order->get_order_key(),
            array(
                'method'      => 'GET',
                'timeout'     => 30,
                'user-agent'  => 'WooCommerce/' . WC()->version,
                'headers'     => array(
                    'Content-Type'  => 'application/json;',
                    'Authorization' => 'Bearer ' . $this->gateway->get_option('api_token')
                ),
                'httpversion' => '1.1',
            )
        );

        WC_Gateway_Ingenico::log('Ingenico - get_payment_details() response: ' . wc_print_r($raw_response, true));

        if (isset($raw_response['body']) && ($response = json_decode($raw_response['body'], true))) {
            if (isset($response['data'])) {
                $data = $response['data'];
                if (isset($data['charge'])) {
                    return $data['charge'];
                }
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

        if ( ! ($details = $this->get_payment_details($order))) {
            return null;
        }

        $request = array(
            'charge_id' => $details['id'],
            'amount'    => $amount,
        );

        WC_Gateway_Ingenico::log('Ingenico - make_payment_refund() request parameters: ' . $order->get_order_number() . ': ' . wc_print_r($request, true));

        $raw_response = wp_safe_remote_post(
            $this->endpoint . '/refunds',
            array(
                'method'      => 'POST',
                'timeout'     => 300,
                'user-agent'  => 'WooCommerce/' . WC()->version,
                'headers'     => array(
                    'Content-Type'  => 'application/json;',
                    'Authorization' => 'Bearer ' . $this->gateway->get_option('api_token')
                ),
                'body' => json_encode($request),
                'httpversion' => '1.1',
            )
        );

        WC_Gateway_Ingenico::log('Ingenico - make_payment_refund() response: ' . wc_print_r($raw_response, true));

        if (isset($raw_response['body']) && ($response = json_decode($raw_response['body'], true))) {
            if (isset($response['data'])) {
                $data = $response['data'];
                if (isset($data['charge'])) {
                    $charge = $data['charge'];
                    if (isset($charge['included'])) {
                        foreach ($charge['included'] as $included) {
                            if ($included['type'] === 'refund') {
                                return $included['id'];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Limit length of an arg.
     *
     * @param  string $string Argument to limit.
     * @param  integer $limit Limit size in characters.
     * @return string
     */
    protected function limit_length($string, $limit = 127)
    {
        // As the output is to be used in http_build_query which applies URL encoding, the string needs to be
        // cut as if it was URL-encoded, but returned non-encoded (it will be encoded by http_build_query later).
        $url_encoded_str = rawurlencode($string);

        if (strlen($url_encoded_str) > $limit) {
            $string = rawurldecode(substr($url_encoded_str, 0, $limit - 3) . '...');
        }
        return $string;
    }

    protected function getAuthorizationHeaderValue($requestHeaders)
    {
        return
            static::AUTHORIZATION_ID . ' ' . static::AUTHORIZATION_TYPE. ':'.
            $this->communicatorConfiguration->getApiKeyId() .':'.
            base64_encode(
                hash_hmac(
                    static::HASH_ALGORITHM,
                    $this->getSignData($requestHeaders),
                    $this->communicatorConfiguration->getApiSecret(),
                    true
                )
            );
    }
}
