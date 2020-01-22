<?php
/**
 * Settings for Ingenico Gateway.
 */

defined( 'ABSPATH' ) or exit;

return array(
    'enabled'     => array(
        'title'   => __('Enable/Disable', 'woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Enable Ingenico', 'woocommerce'),
        'default' => 'no',
    ),
    'title'       => array(
        'title'       => __('Title', 'woocommerce'),
        'type'        => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
        'default'     => __('Ingenico', 'woocommerce'),
        'desc_tip'    => true,
    ),
    'description' => array(
        'title'       => __('Description', 'woocommerce'),
        'type'        => 'text',
        'desc_tip'    => true,
        'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
        'default'     => __('Pay via Ingenico; you can pay with your credit card.', 'woocommerce'),
    ),
    'testmode'    => array(
        'title'       => __('Ingenico sandbox', 'woocommerce'),
        'type'        => 'checkbox',
        'label'       => __('Enable Ingenico sandbox', 'woocommerce'),
        'default'     => 'no',
        'description' => __('Ingenico sandbox can be used to test payments. API token must be set up for test payments.')
    ),
    'debug'       => array(
        'title'       => __('Debug log', 'woocommerce'),
        'type'        => 'checkbox',
        'label'       => __('Enable logging', 'woocommerce'),
        'default'     => 'no',
        'description' => sprintf(__('Log Ingenico events, such as requests, responses, inside %s Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woocommerce'), '<code>' . WC_Log_Handler_File::get_log_file_path('Ingenico') . '</code>'),
    ),
    'api_details' => array(
        'title'       => __('API credentials', 'woocommerce'),
        'type'        => 'title',
        'description' => sprintf(__('Enter your Ingenico API credentials. Learn how to access your <a href="%s">Ingenico API</a>.', 'woocommerce'), 'https://epayments.developer-ingenico.com/'),
    ),
    'api_url'     => array(
        'title'       => __('Ingenico API url', 'woocommerce'),
        'type'        => 'text',
        'description' => '',
        'default'     => 'https://world.preprod.api-ingenico.com',
        'desc_tip'    => true,
        'placeholder' => '',
    ),
    'merchant_id' => array(
        'title'       => __('Ingenico Merchant ID', 'woocommerce'),
        'type'        => 'text',
        'description' => '',
        'default'     => '',
        'desc_tip'    => true,
        'placeholder' => '',
    ),
    'api_token'   => array(
        'title'       => __('Ingenico API token', 'woocommerce'),
        'type'        => 'text',
        'description' => '',
        'default'     => '',
        'desc_tip'    => true,
        'placeholder' => '',
    ),
    'api_secret'   => array(
        'title'       => __('Ingenico API secret', 'woocommerce'),
        'type'        => 'password',
        'description' => '',
        'default'     => '',
        'desc_tip'    => true,
        'placeholder' => '',
    ),
    'variant'      => array(
        'title'       => __('Ingenico Variant', 'woocommerce'),
        'type'        => 'text',
        'description' => '',
        'default'     => '100',
        'desc_tip'    => true,
        'placeholder' => '',
    ),
);
