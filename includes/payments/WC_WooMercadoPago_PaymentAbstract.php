<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_WooMercadoPago_Payments
 */
abstract class WC_WooMercadoPago_PaymentAbstract extends WC_Payment_Gateway
{


    public $id;
    public $method_title;
    public $title;
    public $supports;
    public $description;
    public $icon;
    public $binary_mode;
    public $gateway_discount;
    public $site_data;
    public $log;
    public $sandbox;
    public $mp;
    public $ex_payments = array();
    public $method;
    public $method_description;
    public $auto_return;
    public $success_url;
    public $failure_url;
    public $pending_url;
    public $installments;
    public $two_cards_mode;
    public $form_fields;
    public $coupon_mode;
    public $payment_type;
    public $checkout_type;
    public $stock_reduce_mode;
    public $date_expiration;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->description = get_option('description');
        $this->binary_mode = get_option('binary_mode', 'no');
        $this->gateway_discount = get_option('gateway_discount', 0);
        $this->sandbox = get_option('_mp_sandbox_mode', false);
        $this->supports = array('products', 'refunds');
        $this->icon = apply_filters('woocommerce_mercadopago_icon', plugins_url('../assets/images/mercadopago.png', plugin_dir_path(__FILE__)));
        $this->site_data = WC_WooMercadoPago_Module::get_site_data();
        $this->log = WC_WooMercadoPago_Log::init_mercado_pago_log();
        $this->mp = WC_WooMercadoPago_Module::init_mercado_pago_instance();
        $this->init_settings();
        $this->mp_hooks();
    }

    /**
     * @return string
     */
    public function getMpLogo()
    {
        return '<img width="200" height="52" src="' . plugins_url('../assets/images/mplogo.png', plugin_dir_path(__FILE__)) . '"><br><br>';
    }

    /**
     * @return mixed
     */
    public function getMpIcon()
    {
        return apply_filters('woocommerce_mercadopago_icon', plugins_url('../assets/images/mercadopago.png', plugin_dir_path(__FILE__)));
    }


    /**
     * @param $description
     * @return string
     */
    public function getMethodDescription($description)
    {
        return '<img width="200" height="52" src="' . plugins_url('../assets/images/mplogo.png', plugin_dir_path(__FILE__)) . '"><br><br><strong>' . __($description, 'woocommerce-mercadopago') . '</strong>';
    }

    /**
     * @param $label
     * @return array
     */
    public function getFormFields($label)
    {
        $_site_id_v1 = get_option('_site_id_v1', '');
        $form_fields = array();
        if (empty($_site_id_v1)) {
            $form_fields = array(
                'no_credentials_title' => array(
                    'title' => sprintf(__('It appears that your credentials are not properly configured.<br/>Please, go to %s and configure it.', 'woocommerce-mercadopago'),
                        '<a href="' . esc_url(admin_url('admin.php?page=mercado-pago-settings')) . '">' . __('Mercado Pago Settings', 'woocommerce-mercadopago') .
                        '</a>'
                    ),
                    'type' => 'title'
                ),
            );
        }

        if (empty($this->settings['enabled']) || 'no' == $this->settings['enabled'] && empty($form_fields)) {
            $form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce-mercadopago'),
                    'type' => 'checkbox',
                    'label' => __('Enable ' . $label . ' Checkout', 'woocommerce-mercadopago'),
                    'default' => 'no'
                )
            );
        }

        $form_fields['checkout_options_title'] = $this->field_checkout_options_title();
        $form_fields['description'] = $this->field_description();
        $form_fields['payment_title'] = $this->field_payment_title();
        $form_fields['binary_mode'] = $this->field_binary_mode();
        $form_fields['gateway_discount'] = $this->field_gateway_discount();

        return $form_fields;
    }

    /**
     * @param $label
     * @return array
     */
    public function field_enabled($label)
    {
        $enabled = array(
            'title' => __('Enable/Disable', 'woocommerce-mercadopago'),
            'type' => 'checkbox',
            'label' => __('Enable ' . $label . ' Checkout', 'woocommerce-mercadopago'),
            'default' => 'no'
        );
        return $enabled;
    }

    /**
     * @return array
     */
    public function field_checkout_options_title()
    {
        $checkout_options_title = array(
            'title' => __('Checkout Interface: How checkout is shown', 'woocommerce-mercadopago'),
            'type' => 'title'
        );
        return $checkout_options_title;
    }

    /**
     * @return array
     */
    public function field_title()
    {
        $title = array(
            'title' => __('Title', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => __('Title shown to the client in the checkout.', 'woocommerce-mercadopago'),
            'default' => __('Mercado Pago', 'woocommerce-mercadopago')
        );

        return $title;
    }

    /**
     * @return array
     */
    public function field_description()
    {
        $description = array(
            'title' => __('Description', 'woocommerce-mercadopago'),
            'type' => 'textarea',
            'description' => __('Description shown to the client in the checkout.', 'woocommerce-mercadopago'),
            'default' => __('Pay with Mercado Pago', 'woocommerce-mercadopago')
        );
        return $description;
    }

    /**
     * @return array
     */
    public function field_payment_title()
    {
        $payment_title = array(
            'title' => __('Payment Options: How payment options behaves', 'woocommerce-mercadopago'),
            'type' => 'title'
        );
        return $payment_title;
    }

    /**
     * @return array
     */
    public function field_binary_mode()
    {
        $binary_mode = array(
            'title' => __('Binary Mode', 'woocommerce-mercadopago'),
            'type' => 'checkbox',
            'label' => __('Enable binary mode for checkout status', 'woocommerce-mercadopago'),
            'default' => 'no',
            'description' => __('When charging a credit card, only [approved] or [reject] status will be taken.', 'woocommerce-mercadopago')
        );
        return $binary_mode;
    }

    /**
     * @return array
     */
    public function field_gateway_discount()
    {
        $gateway_discount = array(
            'title' => __('Discount/Fee by Gateway', 'woocommerce-mercadopago'),
            'type' => 'number',
            'description' => __('Give a percentual (-99 to 99) discount or fee for your customers if they use this payment gateway. Use negative for fees, positive for discounts.', 'woocommerce-mercadopago'),
            'default' => '0',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '-99',
                'max' => '99'
            )
        );
        return $gateway_discount;
    }


    public function mp_hooks($is_instance = false)
    {
        add_action('woocommerce_thankyou', array( $this, 'show_ticket_script' ));
        // Used by IPN to receive IPN incomings.
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_ipn_response'));

        // Used by IPN to process valid incomings.
        add_action('valid_mercadopago_ipn_request' . strtolower(get_class($this)), array($this, 'successful_request'));

        // Process the cancel order meta box order action.
        add_action('woocommerce_order_action_cancel_order', array($this, 'process_cancel_order_meta_box_actions'));

        // Used in settings page to hook "save settings" action.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'custom_process_admin_options'));

        // Send setting of checkout to MP
        add_action('send_options_payment_gateways' . strtolower(get_class($this)), array($this, 'send_settings_mp'));

        // Apply the discounts.
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_discount'), 10);

        // Scripts for custom checkout.
        add_action('wp_enqueue_scripts', array($this, 'add_checkout_scripts'));

        // Display discount in payment method title.
        add_filter('woocommerce_gateway_title', array($this, 'get_payment_method_title'), 10, 2);

        if (!empty($this->settings['enabled']) && $this->settings['enabled'] == 'yes') {
            if (!$is_instance || get_class($this) == 'WC_WooMercadoPago_BasicGateway') {
                // Scripts for order configuration.
                add_action('woocommerce_after_checkout_form', array($this, 'add_mp_settings_script'));
                // Checkout updates.
                add_action('woocommerce_thankyou', array($this, 'update_mp_settings_script'));
            }
        }
    }


    /**
     * Mensage credentials not configured.
     *
     * @return string Error Mensage.
     */
    public function credential_missing_message()
    {
        echo '<div class="error"><p><strong> Mercado Pago: </strong>' . sprintf(__('It appears that your credentials are not properly configured.<br/>Please, go to %s and configure it.', 'woocommerce-mercadopago'), '<a href="' . esc_url(admin_url('admin.php?page=mercado-pago-settings')) . '">' . __('Mercado Pago Settings', 'woocommerce-mercadopago') . '</a>') . '</p></div>';

    }

    /**
     *
     * ========================================================================
     * SAVE CHECKOUT SETTINGS
     * ========================================================================
     *
     * Processes and saves options.
     * If there is an error thrown, will continue to save and validate fields, but will leave the
     * erroring field out.
     * @return bool was anything saved?
     */

    public function custom_process_admin_options()
    {
        $this->init_settings();
        $post_data = $this->get_post_data();

        $this->process_settings($post_data);

        do_action('send_options_payment_gateways' . strtolower(get_class($this)));

        // Apply updates
        return update_option(
            $this->get_option_key(),
            apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings)
        );
    }

    public function process_settings($post_data)
    {
        foreach ($this->get_form_fields() as $key => $field) {
            if ('title' !== $this->get_field_type($field)) {
                $value = $this->get_field_value($key, $field, $post_data);
                if ($key == 'gateway_discount') {
                    if (!is_numeric($value) || empty ($value)) {
                        $this->settings[$key] = 0;
                    } else {
                        if ($value < -99 || $value > 99 || empty ($value)) {
                            $this->settings[$key] = 0;
                        } else {
                            $this->settings[$key] = $value;
                        }
                    }
                } else {
                    $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                }
            }
        }
    }

    public function send_settings_mp()
    {
        $_site_id_v1 = get_option('_site_id_v1', '');
        $is_test_user = get_option('_test_user_v1', false);
        if (!empty($_site_id_v1)) {
            // Analytics.
            if (!$is_test_user) {
                $this->mp->analytics_save_settings($this->define_settings_to_send());
            }

            if (get_class($this) == 'WC_WooMercadoPago_BasicGateway') {
                // Two cards mode.
                $this->mp->set_two_cards_mode($this->two_cards_mode);
            }
        }
    }

    public function define_settings_to_send()
    {
        $infra_data = WC_WooMercadoPago_Module::get_common_settings();
        $infra_data['checkout_custom_credit_card'] = ($this->settings['enabled'] == 'yes' ? 'true' : 'false');
        $infra_data['checkout_custom_credit_card_coupon'] = ($this->settings['coupon_mode'] == 'yes' ? 'true' : 'false');
        return $infra_data;
    }

    /*
     * ========================================================================
     * HANDLES ORDER
     * ========================================================================
     *
     * Handles the manual order cancellation in server-side.
     *
     */

    public function process_cancel_order_meta_box_actions($order)
    {

        $used_gateway = (method_exists($order, 'get_meta')) ? $order->get_meta('_used_gateway') : get_post_meta($order->id, '_used_gateway', true);
        $payments = (method_exists($order, 'get_meta')) ? $order->get_meta('_Mercado_Pago_Payment_IDs') : get_post_meta($order->id, '_Mercado_Pago_Payment_IDs', true);

        // A watchdog to prevent operations from other gateways.
        if ($used_gateway != get_class($this)) {
            return;
        }

        $this->write_log(__FUNCTION__, 'cancelling payments for ' . $payments);

        // Canceling the order and all of its payments.
        if ($this->mp != null && !empty($payments)) {
            $payment_ids = explode(', ', $payments);
            foreach ($payment_ids as $p_id) {
                $response = $this->mp->cancel_payment($p_id);
                $message = $response['response']['message'];
                $status = $response['status'];
                $this->write_log(__FUNCTION__,
                    'cancel payment of id ' . $p_id . ' => ' .
                    ($status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message)
                );
            }
        } else {
            $this->write_log(__FUNCTION__, 'no payments or credentials invalid');
        }
    }

    /*
     * ========================================================================
     * WRITE LOG
     * ========================================================================
     */


    /*
	 * ========================================================================
	 * CHECKOUT BUSINESS RULES (CLIENT SIDE)
	 * ========================================================================
	 */

    public function payment_fields()
    {
        if ($description = $this->get_description()) {
            echo wpautop(wptexturize($description));
        }
        if ($this->supports('products')) {
            $this->credit_card_form();
        }
    }

    public function add_mp_settings_script()
    {

        $public_key = get_option('_mp_public_key');
        $is_test_user = get_option('_test_user_v1', false);

        if (!empty($public_key) && !$is_test_user) {

            $w = WC_WooMercadoPago_Module::woocommerce_instance();
            $available_payments = array();
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            foreach ($gateways as $g) {
                $available_payments[] = $g->id;
            }
            $available_payments = str_replace('-', '_', implode(', ', $available_payments));
            if (wp_get_current_user()->ID != 0) {
                $logged_user_email = wp_get_current_user()->user_email;
            } else {
                $logged_user_email = null;
            }
            ?>
            <script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
            <script type="text/javascript">
                try {
                    var MA = ModuleAnalytics;
                    MA.setPublicKey('<?php echo $public_key; ?>');
                    MA.setPlatform('WooCommerce');
                    MA.setPlatformVersion('<?php echo $w->version; ?>');
                    MA.setModuleVersion('<?php echo WC_WooMercadoPago_Module::VERSION; ?>');
                    MA.setPayerEmail('<?php echo($logged_user_email != null ? $logged_user_email : ""); ?>');
                    MA.setUserLogged( <?php echo(empty($logged_user_email) ? 0 : 1); ?> );
                    MA.setInstalledModules('<?php echo $available_payments; ?>');
                    MA.post();
                } catch (err) {
                }
            </script>
            <?php
        }
    }

    public function update_mp_settings_script($order_id)
    {

        $public_key = get_option('_mp_public_key');
        $is_test_user = get_option('_test_user_v1', false);
        if (!empty($public_key) && !$is_test_user) {
            if (get_post_meta($order_id, '_used_gateway', true) != get_class($this)) {
                return;
            }
            $this->write_log(__FUNCTION__, 'updating order of ID ' . $order_id);
            echo '<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
			<script type="text/javascript">
				try {
					var MA = ModuleAnalytics;
					MA.setPublicKey( "' . $public_key . '" );
					MA.setPaymentType( "' . $this->payment_type . '" );
					MA.setCheckoutType( "' . $this->checkout_type . '" );
					MA.put();
				} catch(err) {}
			</script>';
        }
    }

    /**
     * Summary: Receive post data and applies a discount based in the received values.
     * Description: Receive post data and applies a discount based in the received values.
     */
    public function add_discount()
    {

        if (!isset($_POST['mercadopago_custom'])) {
            return;
        }

        if (is_admin() && !defined('DOING_AJAX') || is_cart()) {
            return;
        }

        $custom_checkout = $_POST['mercadopago_custom'];
        if (isset($custom_checkout['discount']) && !empty($custom_checkout['discount']) &&
            isset($custom_checkout['coupon_code']) && !empty($custom_checkout['coupon_code']) &&
            $custom_checkout['discount'] > 0 && WC()->session->chosen_payment_method == $this->id) {

            $this->write_log(__FUNCTION__, 'custom checkout trying to apply discount...');

            $value = ($this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP') ?
                floor($custom_checkout['discount'] / $custom_checkout['currency_ratio']) :
                floor($custom_checkout['discount'] / $custom_checkout['currency_ratio'] * 100) / 100;
            global $woocommerce;
            if (apply_filters(
                'wc_mercadopago_custommodule_apply_discount',
                0 < $value, $woocommerce->cart)
            ) {
                $woocommerce->cart->add_fee(sprintf(
                    __('Discount for %s coupon', 'woocommerce-mercadopago'),
                    esc_attr($custom_checkout['campaign']
                    )), ($value * -1), false
                );
            }
        }

    }

    // Display the discount in payment method title.
    public function get_payment_method_title($title, $id)
    {
        if (!is_checkout() && !(defined('DOING_AJAX') && DOING_AJAX)) {
            return $title;
        }
        if ($title != $this->title || $this->gateway_discount == 0) {
            return $title;
        }
        if (!is_numeric($this->gateway_discount) || $this->gateway_discount < -99 || $this->gateway_discount > 99) {
            return $title;
        }
        $total = (float)WC()->cart->subtotal;
        $price_percent = $this->gateway_discount / 100;
        if ($price_percent > 0) {
            $title .= ' (' . __('Discount of', 'woocommerce-mercadopago') . ' ' .
                strip_tags(wc_price($total * $price_percent)) . ')';
        } elseif ($price_percent < 0) {
            $title .= ' (' . __('Fee of', 'woocommerce-mercadopago') . ' ' .
                strip_tags(wc_price(-$total * $price_percent)) . ')';
        }
        return $title;
    }

    public function add_checkout_scripts()
    {
        if (is_checkout() && $this->is_available() && $this->checkout_type == "custom") {
            if (!get_query_var('order-received')) {
                wp_enqueue_style(
                    'woocommerce-mercadopago-style',
                    plugins_url('assets/css/custom_checkout_mercadopago.css', plugin_dir_path(__FILE__))
                );
                wp_enqueue_script(
                    'mercado-pago-module-custom-js',
                    'https://secure.mlstatic.com/sdk/javascript/v1/mercadopago.js'
                );
            }
        }
    }

    /**
     * ========================================================================
     * PROCESS PAYMENT.
     * ========================================================================
     *
     * Process the payment. Override this in your gateway. When implemented, this should.
     * return the success and redirect in an array. e.g:
     *
     *        return array(
     *            'result'   => 'success',
     *            'redirect' => $this->get_return_url( $order )
     *        );
     *
     * @param int $order_id Order ID.
     * @return array
     */

    public function process_payment($order_id)
    {

        $order = wc_get_order($order_id);

        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data('_used_gateway', get_class($this));
            $order->save();
        } else {
            update_post_meta($order_id, '_used_gateway', get_class($this));
        }

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );

    }

    /**
     * Handles the manual order refunding in server-side.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {

        $payments = get_post_meta($order_id, '_Mercado_Pago_Payment_IDs', true);

        // Validate.
        if ($this->mp == null || empty($payments)) {
            $this->write_log(__FUNCTION__, 'no payments or credentials invalid');
            return false;
        }

        // Processing data about this refund.
        $total_available = 0;
        $payment_structs = array();
        $payment_ids = explode(', ', $payments);
        foreach ($payment_ids as $p_id) {
            $p = get_post_meta($order_id, 'Mercado Pago - Payment ' . $p_id, true);
            $p = explode('/', $p);
            $paid_arr = explode(' ', substr($p[2], 1, -1));
            $paid = ((float)$paid_arr[1]);
            $refund_arr = explode(' ', substr($p[3], 1, -1));
            $refund = ((float)$refund_arr[1]);
            $p_struct = array('id' => $p_id, 'available_to_refund' => $paid - $refund);
            $total_available += $paid - $refund;
            $payment_structs[] = $p_struct;
        }
        $this->write_log(__FUNCTION__,
            'refunding ' . $amount . ' because of ' . $reason . ' and payments ' .
            json_encode($payment_structs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Do not allow refund more than available or invalid amounts.
        if ($amount > $total_available || $amount <= 0) {
            return false;
        }

        // Iteratively refunfind amount, taking in consideration multiple payments.
        $remaining_to_refund = $amount;
        foreach ($payment_structs as $to_refund) {
            if ($remaining_to_refund <= $to_refund['available_to_refund']) {
                // We want to refund an amount that is less than the available for this payment, so we
                // can just refund and return.
                $response = $this->mp->partial_refund_payment(
                    $to_refund['id'], $remaining_to_refund,
                    $reason, $this->invoice_prefix . $order_id
                );
                $message = $response['response']['message'];
                $status = $response['status'];
                $this->write_log(__FUNCTION__,
                    'refund payment of id ' . $p_id . ' => ' .
                    ($status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message)
                );
                if ($status >= 200 && $status < 300) {
                    return true;
                } else {
                    return false;
                }
            } elseif ($to_refund['available_to_refund'] > 0) {
                // We want to refund an amount that exceeds the available for this payment, so we
                // totally refund this payment, and try to complete refund in other/next payments.
                $response = $this->mp->partial_refund_payment(
                    $to_refund['id'], $to_refund['available_to_refund'],
                    $reason, $this->invoice_prefix . $order_id
                );
                $message = $response['response']['message'];
                $status = $response['status'];
                $this->write_log(__FUNCTION__,
                    'refund payment of id ' . $p_id . ' => ' .
                    ($status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message)
                );
                if ($status < 200 || $status >= 300) {
                    return false;
                }
                $remaining_to_refund -= $to_refund['available_to_refund'];
            }
            if ($remaining_to_refund == 0)
                return true;
        }

        // Reaching here means that there we run out of payments, and there is an amount
        // remaining to be refund, which is impossible as it implies refunding more than
        // available on paid amounts.
        return false;
    }

    /*
	 * ========================================================================
	 * AUXILIARY AND FEEDBACK METHODS (SERVER SIDE)
	 * ========================================================================
	 */
    // Called automatically by WooCommerce, verify if Module is available to use.
    public function is_available()
    {

        if (!did_action('wp_loaded')) {
            return false;
        }

        global $woocommerce;
        $w_cart = $woocommerce->cart;

        // Check for recurrent product checkout.
        if (isset($w_cart)) {
            if (WC_WooMercadoPago_Module::is_subscription($w_cart->get_cart())) {
                return false;
            }
        }

        // Check if this gateway is enabled and credential actived
        $_mp_public_key = get_option('_mp_public_key');
        $access_token = get_option('_mp_access_token');
        $_site_id_v1 = get_option('_site_id_v1');
        $available = ('yes' == $this->settings['enabled']) &&
            !empty($_mp_public_key) &&
            !empty($access_token) &&
            !empty($_site_id_v1);
        return $available;

        // Available especific rule of gateway method
        if (!$this->mp_config_rule_is_available()) {
            return false;
        }
    }

    // Enter a gateway method-specific rule within this function
    public function mp_config_rule_is_available()
    {
        return true;
    }

    // Get the URL to admin page.
    protected function admin_url()
    {
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {
            return admin_url(
                'admin.php?page=wc-settings&tab=checkout&section=' . $this->id
            );
        }
        return admin_url(
            'admin.php?page=woocommerce_settings&tab=payment_gateways&section=' . get_class($this)
        );
    }

    /*
	 * ========================================================================
	 * IPN MECHANICS (SERVER SIDE)
	 * ========================================================================
	 */

    /**
     * Summary: This call checks any incoming notifications from Mercado Pago server.
     * Description: This call checks any incoming notifications from Mercado Pago server.
     */
    public function check_ipn_response()
    {


        @ob_clean();
        $this->write_log(
            __FUNCTION__,
            'received _get content: ' .
            json_encode($_GET, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        // Setup sandbox mode.
        $this->mp->sandbox_mode($this->sandbox);
        // Over here, $_GET should come with this JSON structure:
        // {
        // 	"topic": <string>,
        // 	"id": <string>
        // }
        // If not, the IPN is corrupted in some way.
        $data = $_GET;
        if (isset($data['coupon_id']) && !empty($data['coupon_id'])) {
            // Process coupon evaluations.
            if (isset($data['payer']) && !empty($data['payer'])) {
                $response = $this->mp->check_discount_campaigns($data['amount'], $data['payer'], $data['coupon_id']);
                header('HTTP/1.1 200 OK');
                header('Content-Type: application/json');
                echo json_encode($response);
            } else {
                $obj = new stdClass();
                $obj->status = 404;
                $obj->response = array(
                    'message' => __('Please, inform your email in billing address to use this feature', 'woocommerce-mercadopago'),
                    'error' => 'payer_not_found',
                    'status' => 404,
                    'cause' => array()
                );
                header('HTTP/1.1 200 OK');
                header('Content-Type: application/json');
                echo json_encode($obj);
            }
            exit(0);
        } else if (!isset($data['data_id']) || !isset($data['type'])) {
            // Received IPN call from v0.
            $this->write_log(
                __FUNCTION__,
                'data_id or type not set: ' .
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            if (!isset($data['id']) || !isset($data['topic'])) {
                $this->write_log(
                    __FUNCTION__,
                    'Mercado Pago Request failure: ' .
                    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
                wp_die(__('Mercado Pago Request Failure', 'woocommerce-mercadopago'));
            } else {
                // At least, check if its a v0 ipn.
                header('HTTP/1.1 200 OK');
            }
        } else {
            // Needed informations are present, so start process then.
            try {
                if ($data['type'] == 'payment') {
                    $access_token = array('access_token' => $this->mp->get_access_token());
                    $payment_info = $this->mp->get('/v1/payments/' . $data['data_id'], $access_token, false);
                    if (!is_wp_error($payment_info) && ($payment_info['status'] == 200 || $payment_info['status'] == 201)) {
                        if ($payment_info['response']) {
                            header('HTTP/1.1 200 OK');
                            do_action('valid_mercadopago_ipn_request', $payment_info['response']);
                        }
                    } else {
                        $this->write_log(
                            __FUNCTION__,
                            'error when processing received data: ' .
                            json_encode($payment_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        );
                    }
                }
            } catch (MercadoPagoException $ex) {
                $this->write_log(
                    __FUNCTION__,
                    'MercadoPagoException: ' .
                    json_encode($ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }
        }
    }

    /**
     * Summary: Properly handles each case of notification, based in payment status.
     * Description: Properly handles each case of notification, based in payment status.
     */


    /*
	 * ========================================================================
	 * MERCADO ENVIOS
	 * ========================================================================
	 */

    /**
     * Summary: Check IPN data and updates Mercado Envios tag and informaitons.
     * Description: Check IPN data and updates Mercado Envios tag and informaitons.
     */
    public function check_mercado_envios($merchant_order)
    {
        $order_key = $merchant_order['external_reference'];
        if (!empty($order_key)) {
            $invoice_prefix = get_option('_mp_store_identificator', 'WC-');
            $order_id = (int)str_replace($invoice_prefix, '', $order_key);
            $order = wc_get_order($order_id);
            if (count($merchant_order['shipments']) > 0) {
                foreach ($merchant_order['shipments'] as $shipment) {
                    $shipment_id = $shipment['id'];
                    // Get shipping data on merchant_order.
                    $shipment_name = $shipment['shipping_option']['name'];
                    $shipment_cost = $shipment['shipping_option']['cost'];
                    $shipping_method_id = $shipment['shipping_option']['shipping_method_id'];
                    // Get data shipping selected on checkout.
                    $shipping_meta = $order->get_items('shipping');
                    $order_item_shipping_id = null;
                    $method_id = null;
                    foreach ($shipping_meta as $key => $shipping) {
                        $order_item_shipping_id = $key;
                        $method_id = $shipping['method_id'];
                    }
                    $free_shipping_text = '';
                    $free_shipping_status = 'no';
                    if ($shipment_cost == 0) {
                        $free_shipping_status = 'yes';
                        $free_shipping_text = ' (' . __('Free Shipping', 'woocommerce') . ')';
                    }
                    // WooCommerce 3.0 or later.
                    if (method_exists($order, 'get_id')) {
                        $shipping_item = $order->get_item($order_item_shipping_id);
                        $shipping_item->set_order_id($order->get_id());
                        // Update shipping cost and method title.
                        $shipping_item->set_props(array(
                            'method_title' => 'Mercado Envios - ' . $shipment_name . $free_shipping_text,
                            'method_id' => $method_id,
                            'total' => wc_format_decimal($shipment_cost),
                        ));
                        $shipping_item->save();
                        $order->calculate_shipping();
                    } else {
                        // Update shipping cost and method title.
                        $r = $order->update_shipping($order_item_shipping_id, array(
                            'method_title' => 'Mercado Envios - ' . $shipment_name . $free_shipping_text,
                            'method_id' => $method_id,
                            'cost' => wc_format_decimal($shipment_cost)
                        ));
                    }
                    // WTF? FORCE UPDATE SHIPPING: https://docs.woocommerce.com/wc-apidocs/source-class-WC_Abstract_Order.html#541
                    $order->set_total(wc_format_decimal($shipment_cost), 'shipping');
                    // Update total order.
                    $order->set_total(
                        wc_format_decimal($order->get_subtotal())
                        + wc_format_decimal($order->get_total_shipping())
                        + wc_format_decimal($order->get_total_tax())
                        - wc_format_decimal($order->get_total_discount())
                    );
                    // Update additional info.
                    wc_update_order_item_meta($order_item_shipping_id, 'shipping_method_id', $shipping_method_id);
                    wc_update_order_item_meta($order_item_shipping_id, 'free_shipping', $free_shipping_status);
                    $access_token = $this->mp->get_access_token();
                    $request = array(
                        'uri' => '/shipments/' . $shipment_id,
                        'params' => array(
                            'access_token' => $access_token
                        )
                    );
                    $email = (wp_get_current_user()->ID != 0) ? wp_get_current_user()->user_email : null;
                    MeliRestClient::set_email($email);
                    $shipments_data = MeliRestClient::get($request, '');
                    switch ($shipments_data['response']['substatus']) {
                        case 'ready_to_print':
                            $substatus_description = __('Tag ready to print', 'woocommerce-mercadopago');
                            break;
                        case 'printed':
                            $substatus_description = __('Tag printed', 'woocommerce-mercadopago');
                            break;
                        case 'stale':
                            $substatus_description = __('Unsuccessful', 'woocommerce-mercadopago');
                            break;
                        case 'delayed':
                            $substatus_description = __('Delayed shipping', 'woocommerce-mercadopago');
                            break;
                        case 'receiver_absent':
                            $substatus_description = __('Missing recipient for delivery', 'woocommerce-mercadopago');
                            break;
                        case 'returning_to_sender':
                            $substatus_description = __('In return to sender', 'woocommerce-mercadopago');
                            break;
                        case 'claimed_me':
                            $substatus_description = __('Buyer initiates complaint and requested a refund.', 'woocommerce-mercadopago');
                            break;
                        default:
                            $substatus_description = $shipments_data['response']['substatus'];
                            break;
                    }
                    if ($substatus_description == '') {
                        $substatus_description = $shipments_data['response']['status'];
                    }
                    $order->add_order_note('Mercado Envios: ' . $substatus_description);
                    $this->write_log(__FUNCTION__, 'Mercado Envios - shipments_data : ' . json_encode($shipments_data, JSON_PRETTY_PRINT));
                    // Add tracking number in meta data to use in order page.
                    update_post_meta($order_id, '_mercadoenvios_tracking_number', $shipments_data['response']['tracking_number']);
                    // Add shipiment_id in meta data to use in order page.
                    update_post_meta($order_id, '_mercadoenvios_shipment_id', $shipment_id);
                    // Add status in meta data to use in order page.
                    update_post_meta($order_id, '_mercadoenvios_status', $shipments_data['response']['status']);
                    // Add substatus in meta data to use in order page.
                    update_post_meta($order_id, '_mercadoenvios_substatus', $shipments_data['response']['substatus']);
                    // Send email to customer.
                    $tracking_id = $shipments_data['response']['tracking_number'];
                    if (isset($order->billing_email) && isset($tracking_id)) {
                        $list_of_items = array();
                        $items = $order->get_items();
                        foreach ($items as $item) {
                            $product = new WC_product($item['product_id']);
                            if (method_exists($product, 'get_description')) {
                                $product_title = WC_WooMercadoPago_Module::utf8_ansi(
                                    $product->get_name()
                                );
                            } else {
                                $product_title = WC_WooMercadoPago_Module::utf8_ansi(
                                    $product->post->post_title
                                );
                            }
                            array_push($list_of_items, $product_title . ' x ' . $item['qty']);
                        }
                        wp_mail(
                            $order->billing_email,
                            __('Order', 'woocommerce-mercadopago') . ' ' . $order_id . ' - ' . __('Mercado Envios Tracking ID', 'woocommerce-mercadopago'),
                            __('Hello,', 'woocommerce-mercadopago') . "\r\n\r\n" .
                            __('Your order', 'woocommerce-mercadopago') . ' ' . ' [ ' . implode(', ', $list_of_items) . ' ] ' .
                            __('made in', 'woocommerce-mercadopago') . ' ' . get_site_url() . ' ' .
                            __('used Mercado Envios as its shipment method.', 'woocommerce-mercadopago') . "\r\n" .
                            __('You can track it with the following Tracking ID:', 'woocommerce-mercadopago') . ' ' . $tracking_id . ".\r\n\r\n" .
                            __('Best regards.', 'woocommerce-mercadopago')
                        );
                    }
                }
            }
        }
    }

}