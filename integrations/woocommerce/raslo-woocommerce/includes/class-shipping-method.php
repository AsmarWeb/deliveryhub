<?php

namespace Raslo\WooCommerce;

class ShippingMethod extends \WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'raslo';
        $this->instance_id = absint($instance_id);
        $this->method_title = 'Raslo / رسلو';
        $this->method_description = 'Prepaid UAE deliveries. Your checkout charge is separate from the final Raslo delivery price.';
        $this->supports = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];
        $this->instance_form_fields = [
            'title' => ['title' => 'Checkout label', 'type' => 'text', 'default' => 'Raslo / رسلو'],
            'cost' => ['title' => 'Customer shipping charge (AED)', 'type' => 'price', 'default' => '', 'description' => 'Set your own checkout charge. This is not a quote or price approval from Raslo.'],
        ];
        $this->init_settings();
        $this->title = $this->get_option('title', 'Raslo / رسلو');
        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
    }

    public function calculate_shipping($package = [])
    {
        if (! DeliveryBridge::enabled() || get_woocommerce_currency() !== 'AED' || ($package['destination']['country'] ?? '') !== 'AE') {
            return;
        }
        if (function_exists('WC') && WC()->cart && count(WC()->cart->get_shipping_packages()) !== 1) {
            return;
        }
        $cost = $this->get_option('cost', '');
        if (! is_string($cost) || ! preg_match('/^\d{1,5}(?:\.\d{1,2})?$/', $cost)) {
            return;
        }
        $this->add_rate(['id' => $this->get_rate_id(), 'label' => $this->title, 'cost' => $cost, 'package' => $package]);
    }
}
