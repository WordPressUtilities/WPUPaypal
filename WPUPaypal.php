<?php

/*
Plugin Name: WPU Paypal
Plugin URI:
Description: This WordPress plugin helps you make payments via PayPal
Version: 0.3.1
Thanks to: http://www.smashingmagazine.com/2011/09/05/getting-started-with-the-paypal-api/
*/

if (!defined('ABSPATH')) {
    die('Error');
}

class WPUPaypal
{

    /* Plugin options */
    private $_plugin = array(
        'id' => 'wpupaypal'
    );

    private $_messages = array();

    /**
     * Last error message(s)
     * @var array
     */
    protected $_errors = array();

    /**
     * API Credentials
     * Use the correct credentials for the environment in use (Live / Sandbox)
     * @var array
     */
    protected $_credentials = array();

    private $_mode;

    /**
     * API endpoint
     * Live - https://api-3t.paypal.com/nvp
     * Sandbox - https://api-3t.sandbox.paypal.com/nvp
     * @var string
     */
    private $_endPoint;
    private $_siteUrl;

    /**
     * API Version
     * @var string
     */
    protected $_version;

    function __construct() {

        load_plugin_textdomain($this->_plugin['id'], false, dirname(plugin_basename(__FILE__)) . '/lang/');

        $this->set_plugin();

        if (is_admin()) {
            add_action('admin_menu', array(&$this,
                'add_admin_menu'
            ));
            add_action('admin_init', array(&$this,
                'admin_page_postAction'
            ));
        }

        $this->set_options();
    }

    private function set_plugin() {
        $this->_plugin['name'] = $this->__('WPU PayPal Options');
        $this->_plugin['menu_name'] = $this->__('PayPal Options');
        $this->_plugin['options'] = array(
            'credentials_user' => array(
                'name' => $this->__('Credentials - User')
            ) ,
            'credentials_pwd' => array(
                'name' => $this->__('Credentials - PWD')
            ) ,
            'credentials_sig' => array(
                'name' => $this->__('Credentials - Sig')
            ) ,
            'version' => array(
                'name' => $this->__('API Version')
            ) ,
            'mode' => array(
                'name' => $this->__('Mode') ,
                'type' => 'select',
                'datas' => array(
                    'sandbox' => 'Sandbox',
                    'live' => 'Live',
                )
            ) ,
            'currency' => array(
                'name' => $this->__('Currency') ,
                'type' => 'select',
                'datas' => array(
                    'EUR' => 'Euro',
                    'USD' => 'US Dollar',
                )
            )
        );
    }

    public function get_prefixed_option_id($id) {
        return 'wpupaypalform_' . $id;
    }

    public function get_option($id) {
        return trim(get_option($this->get_prefixed_option_id($id)));
    }

    public function add_admin_menu() {
        add_menu_page($this->_plugin['name'], $this->_plugin['menu_name'], 'manage_options', $this->_plugin['id'], array(&$this,
            'admin_page'
        ) , '', 99);
    }

    public function admin_page_postAction() {
        if (empty($_POST)) {
            return;
        }

        if (!isset($_POST['wpu_paypal_credentials_test']) || !wp_verify_nonce($_POST['wpu_paypal_credentials_test'], 'wpu_paypal_credentials')) {
            $this->addMessage($this->__('Sorry, your nonce did not verify.'));
            return;
        }

        // Update fields
        foreach ($this->_plugin['options'] as $id => $option) {
            $id_field = $this->get_prefixed_option_id($id);
            $value = $this->get_option($id);
            if (isset($_POST[$id_field]) && $value != $_POST[$id_field]) {
                update_option($id_field, $_POST[$id_field]);
            }
        }

        // Update options
        $this->set_options();

        if (isset($_POST['test_values'])) {
            if ($this->testCallback()) {
                $this->addMessage($this->__('These credentials works great !') , 'updated');
            } else {
                $this->addMessage($this->__('These credentials do not work.'));
            }
        }
    }

    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . $this->_plugin['name'] . '</h1>';
        $this->admin_displayMessages();
        echo '<form action="" method="post"><table>';
        foreach ($this->_plugin['options'] as $id => $option) {
            $type = 'text';
            if (isset($option['type'])) {
                $type = $option['type'];
            }
            if (!isset($option['datas'])) {
                $option['datas'] = array(
                    'No',
                    'Yes'
                );
            }
            $id_field = $this->get_prefixed_option_id($id);
            $value = $this->get_option($id);
            $id_name = ' name="' . $id_field . '" id="' . $id_field . '"';
            echo '<tr><td style="width:150px"><label for="' . $id_field . '">' . $option['name'] . '&nbsp;:</label></td><td>';
            switch ($type) {
                case 'email':
                case 'url':
                case 'text':
                    echo '<input type="' . $type . '" ' . $id_name . ' value="' . esc_attr($value) . '" />';
                    break;

                case 'select':
                    echo '<select ' . $id_name . '>';
                    foreach ($option['datas'] as $data_k => $data_val) {
                        echo '<option ' . ($value == $data_k ? 'selected="selected"' : '') . ' value="' . $data_k . '">' . $data_val . '</option>';
                    }
                    echo '</select>';

                    break;

                default:
                    break;
            }
            echo '</td></tr>';
        }
        echo '</table>';
        wp_nonce_field('wpu_paypal_credentials', 'wpu_paypal_credentials_test');
        echo '<p><button name="test_values" type="submit" class="button">' . $this->__('Test &amp; save values') . '</button> <button name="save" type="submit" class="button button-primary">' . $this->__('Save values') . '</button></p>';
        echo '</form><hr />';
        echo '<h3>' . $this->__('Help') . '</h3>';
        echo '<ul>';
        echo '<li><a target="_blank" href="https://developer.paypal.com/webapps/developer/applications/accounts">' . $this->__('Create test accounts') . '</a></li>';
        echo '<li><a target="_blank" href="https://www.paypal.com/fr/cgi-bin/webscr?cmd=_profile-api-access">' . $this->__('Production API access') . '</a></li>';
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Set options
     */
    private function set_options() {
        $this->_version = $this->get_option('version');
        $this->_mode = $this->get_option('mode');
        if (empty($this->_mode)) {
            $this->_mode = '96.0';
        }
        $this->_currency = $this->get_option('currency');
        if (empty($this->_currency)) {
            $this->_currency = 'EUR';
        }
        $this->_credentials = array(
            'USER' => $this->get_option('credentials_user') ,
            'PWD' => $this->get_option('credentials_pwd') ,
            'SIGNATURE' => $this->get_option('credentials_sig') ,
        );

        /* Set URLs */
        if ($this->_mode == 'live') {
            $this->_endPoint = 'https://api-3t.paypal.com/nvp';
            $this->_siteUrl = 'https://www.paypal.com/';
        } else {
            $this->_endPoint = 'https://api-3t.sandbox.paypal.com/nvp';
            $this->_siteUrl = 'https://www.sandbox.paypal.com/';
        }
    }

    /**
     * Make API request
     *
     * @param string $method string API method to request
     * @param array $params Additional request parameters
     * @return array / boolean Response array / boolean false on failure
     */
    public function request($method, $params = array()) {
        $this->_errors = array();
        if (empty($method)) {

            //Check if API method is not empty
            $this->_errors = array(
                'API method is missing'
            );
            return false;
        }

        //Our request parameters
        $requestParams = array(
            'METHOD' => $method,
            'VERSION' => $this->_version
        ) + $this->_credentials;

        //Building our NVP string
        $request = http_build_query($requestParams + $params);

        //cURL settings
        $curlOptions = array(
            CURLOPT_URL => $this->_endPoint,
            CURLOPT_VERBOSE => 1,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => dirname(__FILE__) . '/inc/cacert.pem',

            //CA cert file
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $request
        );

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);

        //Sending our request - $response will hold the API response
        $response = curl_exec($ch);

        //Checking for cURL errors
        if (curl_errno($ch)) {
            $this->_errors = curl_error($ch);
            curl_close($ch);
            return false;

            //Handle errors


        } else {
            curl_close($ch);
            $responseArray = array();
            parse_str($response, $responseArray);

            // Break the NVP string to an array
            return $responseArray;
        }
    }

    function SetExpressCheckout($details, $redirection = true) {

        //Our request parameters
        $requestParams = array(
            'RETURNURL' => $details['successurl'],
            'CANCELURL' => $details['returnurl'],
        );

        $orderParams = array(
            'PAYMENTREQUEST_0_AMT' => $details['total'],
            'PAYMENTREQUEST_0_SHIPPINGAMT' => '0',
            'PAYMENTREQUEST_0_CURRENCYCODE' => $this->_currency,
            'PAYMENTREQUEST_0_ITEMAMT' => $details['total']
        );

        $item = array(
            'L_PAYMENTREQUEST_0_NAME0' => $details['name'],
            'L_PAYMENTREQUEST_0_DESC0' => $details['desc'],
            'L_PAYMENTREQUEST_0_AMT0' => $details['total'],
            'L_PAYMENTREQUEST_0_QTY0' => '1'
        );

        $response = $this->request('SetExpressCheckout', $requestParams + $orderParams + $item);

        if ($redirection && is_array($response) && $response['ACK'] == 'Success') {

            // Request successful
            $token = $response['TOKEN'];
            header('Location: ' . $this->_siteUrl . 'webscr?cmd=_express-checkout&token=' . urlencode($token));
            die;
        } else {
            return $response;
        }
    }

    public function GetExpressCheckoutDetails($details) {
        $transactionId = null;

        if (!$this->isPaypalCallback()) {
            return $transactionId;
        }

        // Token parameter exists
        // Get checkout details, including buyer information.
        // We can save it for future reference or cross-check with the data we have

        $checkoutDetails = $this->request('GetExpressCheckoutDetails', array(
            'TOKEN' => $_GET['token']
        ));

        // Complete the checkout transaction
        $requestParams = array(
            'TOKEN' => $_GET['token'],
            'PAYMENTACTION' => 'Sale',
            'PAYERID' => $_GET['PayerID'],
            'PAYMENTREQUEST_0_AMT' => $details['total'],
            'PAYMENTREQUEST_0_CURRENCYCODE' => $this->_currency
        );

        $response = $this->request('DoExpressCheckoutPayment', $requestParams);

        // Payment successful
        if (is_array($response) && $response['ACK'] == 'Success') {

            // We'll fetch the transaction ID for internal bookkeeping
            $transactionId = $response['PAYMENTINFO_0_TRANSACTIONID'];
        }

        return $transactionId;
    }

    /* Utilities */

    public function isPaypalCallback() {
        return isset($_GET['token'], $_GET['PayerID']) && !empty($_GET['token']);
    }

    public function testCallback() {

        // Test credentials
        $response = $this->SetExpressCheckout(array(
            'successurl' => site_url() ,
            'returnurl' => site_url() ,
            'total' => 10,
            'name' => 'Test',
            'desc' => 'Test callback',
        ) , false);

        return (is_array($response) && $response['ACK'] == 'Success');
    }

    /* Utilities WordPress */

    public function addMessage($message, $type = 'error') {
        $this->_messages[] = array(
            'content' => $message,
            'type' => $type
        );
    }

    public function admin_displayMessages() {
        if (empty($this->_messages)) {
            return;
        }
        foreach ($this->_messages as $message) {
            echo '<div class="' . $message['type'] . '"><p>' . $message['content'] . '</p></div>';
        }
        $this->_messages = array();
    }

    function __($string) {
        return __($string, $this->_plugin['id']);
    }
}

$WPUPaypal = new WPUPaypal();
