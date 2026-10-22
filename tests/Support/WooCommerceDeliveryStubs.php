<?php

use Illuminate\Support\Str;

require_once __DIR__.'/WordPressCatalogStubs.php';
function wp_salt($scheme)
{
    return $GLOBALS['raslo_test_salt'] ?? 'test-only-wordpress-salt';
}
function wp_generate_uuid4()
{
    return (string) Str::uuid();
}
function wc_get_order($id)
{
    return $GLOBALS['wc_orders'][$id] ?? false;
}
function wc_get_orders($args)
{
    return array_keys($GLOBALS['wc_orders'] ?? []);
}
function wp_safe_remote_request($url, $args)
{
    return ($GLOBALS['wp_http'])($url, $args);
}
function wp_remote_retrieve_response_code($response)
{
    return $response['response']['code'];
}
function wp_remote_retrieve_body($response)
{
    return $response['body'];
}
function absint($number)
{
    return abs((int) $number);
}
class RasloFakeOrder
{
    public array $meta = [];

    public array $notes = [];

    public array $values = [];

    public function __construct(public int $id = 101) {}

    public function get_id()
    {
        return $this->id;
    }

    public function get_meta($key)
    {
        return $this->meta[$key] ?? '';
    }

    public function update_meta_data($key, $value)
    {
        $this->meta[$key] = $value;
    }

    public function delete_meta_data($key)
    {
        unset($this->meta[$key]);
    }

    public function save()
    {
        $GLOBALS['wc_orders'][$this->id] = $this;
    }

    public function add_order_note($note)
    {
        $this->notes[] = $note;
    }

    public function get_items($type)
    {
        if ($type === 'shipping') {
            return $this->values['shipping'] ?? [new class
            {
                public function get_method_id()
                {
                    return 'raslo';
                }
            }];
        }

        return [new class
        {
            public function get_product()
            {
                return new class
                {
                    public function needs_shipping()
                    {
                        return true;
                    }
                };
            }

            public function get_quantity()
            {
                return 2;
            }

            public function get_name()
            {
                return 'Physical bag';
            }
        }];
    }

    public function has_status($statuses)
    {
        return in_array($this->values['status'] ?? 'processing', $statuses, true);
    }

    public function __call($method, $args)
    {
        return $this->values[$method] ?? match ($method) {
            'is_paid' => true,'get_payment_method' => 'stripe','get_currency' => 'AED','get_shipping_country' => 'AE',
            'get_shipping_state' => 'DU','get_shipping_phone' => '050 123 4567','get_billing_phone' => '+971501234567',
            'get_shipping_first_name' => 'Test','get_shipping_last_name' => 'Buyer','get_shipping_city' => 'Dubai',
            'get_shipping_address_1' => 'Tower 8','get_shipping_address_2' => 'Apartment 2','get_customer_note' => 'At reception',
            'get_date_created' => new DateTimeImmutable('now'),default => '',
        };
    }
}
class WC_Shipping_Method
{
    public $id;

    public $instance_id;

    public $method_title;

    public $method_description;

    public $supports;

    public $instance_form_fields;

    public $title;

    public array $rates = [];

    public function init_settings() {}

    public function get_option($key, $fallback = '')
    {
        return $GLOBALS['shipping_options'][$key] ?? $fallback;
    }

    public function get_rate_id()
    {
        return 'raslo:1';
    }

    public function add_rate($rate)
    {
        $this->rates[] = $rate;
    }
}
