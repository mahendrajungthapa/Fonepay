<?php
/**
 * Plugin Name: Fatafat Fonepay Payment Gateway Nepal
 * Plugin URI: https://github.com/mahendrajungthapa/Fonepay
 * Description: Accept payments via Fonepay .
 * Author: Mahendra Jung Thapa
 * Author URI: http://mahendrathapa.com.np/
 * Version: 1.0.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly for security
}

// Ensure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

add_filter('woocommerce_payment_gateways', 'add_wc_gateway_fonepay');
function add_wc_gateway_fonepay($gateways) {
    $gateways[] = 'WC_Gateway_Fonepay';
    return $gateways;
}

add_action('plugins_loaded', 'init_wc_gateway_fonepay');
function init_wc_gateway_fonepay() {
class WC_Gateway_Fonepay extends WC_Payment_Gateway {
    // Explicitly declare the properties
    public $merchant_key;
    public $secret_key;
    public $testmode;

    public function __construct() {
        $this->id = 'fonepay';
        $this->method_title = 'Fonepay';
        $this->method_description = 'Pay via Fonepay.';
        $this->has_fields = true;

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->merchant_key = $this->get_option('merchant_key');
        $this->secret_key = $this->get_option('secret_key');
        $this->testmode = 'yes' === $this->get_option('testmode');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_gateway_fonepay', array($this, 'handle_fonepay_response'));
    }


        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable Fonepay Payment',
                    'default' => 'no'
                ],
                'title' => [
                    'title' => 'Title',
                    'type' => 'text',
                    'default' => 'Fonepay Payment'
                ],
                'description' => [
                    'title' => 'Description',
                    'type' => 'textarea',
                    'default' => 'Pay securely through Fonepay.'
                ],
                'merchant_key' => [
                    'title' => 'Merchant Key',
                    'type' => 'text'
                ],
                'secret_key' => [
                    'title' => 'Secret Key',
                    'type' => 'password'
                ],
                'testmode' => [
                    'title' => 'Test Mode',
                    'type' => 'checkbox',
                    'label' => 'Enable Test Mode',
                    'default' => 'yes'
                ]
            ];
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $return_url = add_query_arg(array(
                'wc-api' => 'wc_gateway_fonepay',
                'order_id' => $order_id
            ), home_url('/'));

            $params = array(
                'PID' => $this->merchant_key,
                'PRN' => strval($order_id),
                'AMT' => $order->get_total(),
                'CRN' => 'NPR',
                'DT' => date('m/d/Y'),
                'R1' => $order_id,
                'R2' => $order_id,
                'MD' => 'P',
                'RU' => $return_url,
            );

            $concatenatedParams = "{$params['PID']},{$params['MD']},{$params['PRN']},{$params['AMT']},{$params['CRN']},{$params['DT']},{$params['R1']},{$params['R2']},{$params['RU']}";
            $params['DV'] = hash_hmac('sha512', $concatenatedParams, $this->secret_key);

            $redirect_url = $this->testmode ? 'https://dev-clientapi.fonepay.com/api/merchantRequest?' : 'https://clientapi.fonepay.com/api/merchantRequest?';
            $redirect_url .= http_build_query($params, '', '&', PHP_QUERY_RFC3986);
			        return array(
            'result' => 'success',
            'redirect' => $redirect_url,
        );
    }

public function handle_fonepay_response() {
    // Attempt to reduce the initial load by checking for the presence of the 'order_id' parameter first
    if (empty($_GET['order_id'])) {
        error_log('Fonepay callback with missing order_id.');
        wp_die('Missing order details in Fonepay response.', 'Fonepay Payment Error', 400);
        return; // Early return to avoid unnecessary processing
    }

    $order_id = intval($_GET['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        error_log("Fonepay response for unknown order ID: {$order_id}");
        wp_die('Order not found.', 'Fonepay Payment Error', 400);
        return;
    }

    // Assuming 'PS' and 'RC' are required parameters from Fonepay for payment status
    if (!isset($_GET['PS'], $_GET['RC'])) {
        error_log("Invalid Fonepay response, missing PS or RC: " . print_r($_GET, true));
        wp_die('Invalid payment details received from Fonepay.', 'Fonepay Payment Error', 400);
        return;
    }

    $payment_status = $_GET['PS'];
    $response_code = $_GET['RC'];

    // Process payment based on the response
    if ($payment_status === 'true' && $response_code === 'successful') {
        $order->payment_complete();
        $redirect_url = $this->get_return_url($order);
    } elseif ($payment_status === 'false' && $response_code === 'failed') {
        $order->update_status('failed', __('Payment failed via Fonepay.', 'woocommerce'));
        $redirect_url = $this->get_return_url($order);
    } else {
        $order->add_order_note('Received unexpected Fonepay response: ' . print_r($_GET, true));
        $redirect_url = $order->get_cancel_order_url(); // Redirect user to cancel/order page in case of unexpected response
    }

    // Perform the redirect in a less error-prone manner
    if (!empty($redirect_url)) {
        echo "<script>window.location.href = '" . esc_js($redirect_url) . "';</script>";
        exit;
    }
}
	  
}

}
