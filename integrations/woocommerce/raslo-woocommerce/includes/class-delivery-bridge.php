<?php

namespace Raslo\WooCommerce;

class DeliveryBridge
{
    private const OPTION = 'raslo_wc_delivery_settings';

    private const GROUP = 'raslo-deliveries';

    public static function seal(string $plain): string
    {
        if (! function_exists('openssl_encrypt')) {
            throw new \RuntimeException('OpenSSL required.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Unable to protect credentials.');
        }

        return base64_encode($iv.$tag.$cipher);
    }

    public static function open(string $sealed): string
    {
        $raw = base64_decode($sealed, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new \RuntimeException('Invalid protected value.');
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) {
            throw new \RuntimeException('Unable to read protected credentials.');
        }

        return $plain;
    }

    public static function enabled(): bool
    {
        return ! empty(get_option(self::OPTION, [])['enabled']);
    }

    public static function deactivate(): void
    {
        $settings = get_option(self::OPTION, []);
        $settings['enabled'] = false;
        update_option(self::OPTION, $settings, false);
        if (function_exists('as_unschedule_all_actions')) {
            foreach (['raslo_wc_send_delivery', 'raslo_wc_poll_delivery', 'raslo_wc_reconcile_deliveries'] as $hook) {
                as_unschedule_all_actions($hook, null, self::GROUP);
            }
        }
    }

    public function boot(): void
    {
        add_action('admin_menu', function () {
            add_submenu_page('woocommerce', 'Raslo deliveries', 'Raslo deliveries', 'manage_woocommerce', 'raslo-deliveries', [$this, 'settings']);
        });
        add_action('woocommerce_shipping_init', function () {
            require_once __DIR__.'/class-shipping-method.php';
        });
        add_filter('woocommerce_shipping_methods', function ($methods) {
            $methods['raslo'] = ShippingMethod::class;

            return $methods;
        });
        add_filter('woocommerce_available_payment_gateways', function ($gateways) {
            $chosen = function_exists('WC') && WC()->session ? WC()->session->get('chosen_shipping_methods', []) : [];

            return self::gatewaysForShipping($gateways, (array) $chosen);
        });
        add_action('woocommerce_payment_complete', [$this, 'changed']);
        add_action('woocommerce_order_status_processing', [$this, 'changed']);
        add_action('woocommerce_order_status_completed', [$this, 'changed']);
        add_action('raslo_wc_send_delivery', [$this, 'send']);
        add_action('raslo_wc_poll_delivery', [$this, 'poll']);
        add_action('raslo_wc_reconcile_deliveries', [$this, 'reconcile']);
        add_action('init', function () {
            if (self::enabled() && function_exists('as_has_scheduled_action') && ! as_has_scheduled_action('raslo_wc_reconcile_deliveries', [], self::GROUP)) {
                as_schedule_recurring_action(time() + 60, 300, 'raslo_wc_reconcile_deliveries', [], self::GROUP, true);
            }
        }, 30);
        add_filter('woocommerce_order_actions', function ($actions) {
            $actions['raslo_retry_delivery'] = 'Raslo: retry/create delivery';

            return $actions;
        });
        add_action('woocommerce_order_action_raslo_retry_delivery', function ($order) {
            $this->changed($order->get_id(), true);
        });
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'adminStatus']);
        add_action('woocommerce_order_details_after_order_table', [$this, 'customerStatus']);
        add_action('rest_api_init', function () {
            register_rest_route('raslo/v1', '/delivery-events', [
                'methods' => 'POST', 'permission_callback' => [$this, 'authorizeEvent'], 'callback' => [$this, 'event'],
            ]);
        });
    }

    private function schedule(string $hook, int $id, int $delay = 2): void
    {
        if (self::enabled() && function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time() + $delay, $hook, [$id], self::GROUP, false);
        }
    }

    public function changed($id, bool $manual = false): void
    {
        if (! self::enabled()) {
            return;
        }
        $order = wc_get_order($id);
        if (! $order) {
            return;
        }
        $settings = get_option(self::OPTION, []);
        if (! $manual && $order->get_date_created() && ($order->get_date_created()?->getTimestamp() ?? 0) < ($settings['enabled_at'] ?? PHP_INT_MAX)) {
            return;
        }
        if ($manual) {
            if (in_array($order->get_meta('_raslo_error'), ['HTTP_400', 'HTTP_422'], true)) {
                $order->delete_meta_data('_raslo_delivery_payload');
                $order->delete_meta_data('_raslo_idempotency');
            } $order->update_meta_data('_raslo_attempts', 0);
            $order->update_meta_data('_raslo_manual', true);
            $order->save();
        }
        $this->schedule('raslo_wc_send_delivery', (int) $id);
    }

    public static function gatewaysForShipping(array $gateways, array $chosen): array
    {
        foreach ($chosen as $method) {
            if ($method === 'raslo' || (is_string($method) && str_starts_with($method, 'raslo:'))) {
                unset($gateways['cod']);
                break;
            }
        }

        return $gateways;
    }

    public static function eligible($order): bool
    {
        $shipping = array_values($order->get_items('shipping'));

        return $order->is_paid() && ! $order->has_status(['cancelled', 'refunded', 'failed']) && $order->get_payment_method() !== 'cod'
            && $order->get_currency() === 'AED' && $order->get_shipping_country() === 'AE'
            && count($shipping) === 1 && $shipping[0]->get_method_id() === 'raslo';
    }

    public static function payload($order): array
    {
        if (! self::eligible($order)) {
            throw new \RuntimeException('ORDER_NOT_ELIGIBLE');
        }
        $physical = [];
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (! $product) {
                throw new \RuntimeException('PRODUCT_MISSING');
            }
            if ($product->needs_shipping()) {
                $physical[] = $item->get_quantity().' × '.$item->get_name();
            }
        }
        if (! $physical) {
            throw new \RuntimeException('NO_PHYSICAL_ITEMS');
        }
        $states = ['AZ' => 'abu_dhabi', 'DU' => 'dubai', 'SH' => 'sharjah', 'AJ' => 'ajman', 'UQ' => 'umm_al_quwain', 'RK' => 'ras_al_khaimah', 'FU' => 'fujairah'];
        $emirate = $states[$order->get_shipping_state()] ?? null;
        if (! $emirate) {
            throw new \RuntimeException('EMIRATE_REQUIRED');
        }
        $phone = trim($order->get_shipping_phone() ?: $order->get_billing_phone());
        $phone = strtr($phone, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $phone = preg_replace('/[\s()\-]/', '', $phone);
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        } elseif (preg_match('/^0[2-9][0-9]{7,8}$/', $phone)) {
            $phone = '+971'.substr($phone, 1);
        } elseif (preg_match('/^971[0-9]{8,9}$/', $phone)) {
            $phone = '+'.$phone;
        }
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
            throw new \RuntimeException('PHONE_REQUIRED');
        }
        $line = trim($order->get_shipping_address_1().' '.$order->get_shipping_address_2());
        $name = trim($order->get_shipping_first_name().' '.$order->get_shipping_last_name());
        $city = trim($order->get_shipping_city());
        if (mb_strlen($line) < 5 || ! $name || ! $city) {
            throw new \RuntimeException('ADDRESS_REQUIRED');
        }

        return ['external_order_id' => 'wc:'.$order->get_id(), 'currency' => 'AED', 'payment_status' => 'paid',
            'recipient' => ['contact_name' => ProductMapper::text($name, 200), 'contact_phone' => $phone, 'city' => ProductMapper::text($city, 120),
                'emirate' => $emirate, 'address_line' => ProductMapper::text($line, 1000)],
            'package_description' => ProductMapper::text(implode(', ', $physical), 200),
            'instructions' => ProductMapper::text($order->get_customer_note(), 1000)];
    }

    private function locked(int $id, callable $callback): bool
    {
        global $wpdb;
        $key = 'raslo-delivery-'.substr(hash('sha256', (defined('DB_NAME') ? DB_NAME : 'test').':'.$wpdb->prefix.'-'.$id), 0, 40);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $key)) !== '1') {
            $this->schedule('raslo_wc_send_delivery', $id, 10);

            return false;
        }
        try {
            $callback();

            return true;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
        }
    }

    private function request(string $method, string $path, ?array $payload = null, ?string $idempotency = null): array
    {
        $settings = get_option(self::OPTION, []);
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.self::open($settings['key'] ?? '')];
        if ($idempotency) {
            $headers['Idempotency-Key'] = $idempotency;
        }
        $args = ['method' => $method, 'timeout' => 20, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 65536, 'headers' => $headers];
        if ($payload !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($payload);
        }
        $response = wp_safe_remote_request(rtrim($settings['base'], '/').$path, $args);
        if (is_wp_error($response)) {
            throw new \RuntimeException('NETWORK_ERROR');
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('HTTP_'.$code);
        }
        if (! is_array($body) || ! is_array($body['data'] ?? null)) {
            throw new \RuntimeException('INVALID_RESPONSE');
        }

        return $body['data'];
    }

    public function send($id): void
    {
        if (! self::enabled()) {
            return;
        }
        $this->locked((int) $id, function () use ($id) {
            $order = wc_get_order($id);
            if (! $order) {
                return;
            }
            if ($order->get_meta('_raslo_delivery_id')) {
                $this->schedule('raslo_wc_poll_delivery', (int) $id, 30);

                return;
            }
            if (! self::eligible($order)) {
                return;
            }
            $settings = get_option(self::OPTION, []);
            if (! $order->get_meta('_raslo_manual') && ($order->get_date_created()?->getTimestamp() ?? 0) < ($settings['enabled_at'] ?? PHP_INT_MAX)) {
                return;
            }
            $attempts = (int) $order->get_meta('_raslo_attempts');
            if ($attempts >= 12) {
                return;
            }
            try {
                // Freeze the exact payload before any network attempt so retries cannot create a second delivery.
                $snapshot = $order->get_meta('_raslo_delivery_payload');
                if (! $snapshot) {
                    $payload = self::payload($order);
                    $order->update_meta_data('_raslo_delivery_payload', self::seal(wp_json_encode($payload)));
                    $order->update_meta_data('_raslo_idempotency', wp_generate_uuid4());
                } else {
                    $payload = json_decode(self::open($snapshot), true, 512, JSON_THROW_ON_ERROR);
                }
                $order->update_meta_data('_raslo_attempts', $attempts + 1);
                $order->save();
                $data = $this->request('POST', '/deliveries', $payload, $order->get_meta('_raslo_idempotency'));
                $this->apply($order, $data, true);
                $order->delete_meta_data('_raslo_delivery_payload');
                $order->update_meta_data('_raslo_error', '');
                $order->save();
                $this->schedule('raslo_wc_poll_delivery', (int) $id, 300);
            } catch (\Throwable $error) {
                $code = preg_match('/^(HTTP_[0-9]{3}|NETWORK_ERROR|INVALID_RESPONSE|ORDER_NOT_ELIGIBLE|PRODUCT_MISSING|NO_PHYSICAL_ITEMS|EMIRATE_REQUIRED|PHONE_REQUIRED|ADDRESS_REQUIRED)$/', $error->getMessage()) ? $error->getMessage() : 'DELIVERY_SYNC_FAILED';
                $order->update_meta_data('_raslo_attempts', $attempts + 1);
                $order->update_meta_data('_raslo_error', $code);
                if ($attempts === 0) {
                    $order->add_order_note('Raslo delivery needs review: '.$code.'. Check WooCommerce → Raslo deliveries and retry using the order action.');
                }
                if (in_array($code, ['HTTP_400', 'HTTP_401', 'HTTP_403', 'HTTP_409', 'HTTP_422', 'ORDER_NOT_ELIGIBLE', 'PRODUCT_MISSING', 'NO_PHYSICAL_ITEMS', 'EMIRATE_REQUIRED', 'PHONE_REQUIRED', 'ADDRESS_REQUIRED'], true)) {
                    $order->update_meta_data('_raslo_attempts', 12);
                }
                $order->save();
                $this->schedule('raslo_wc_send_delivery', (int) $id, min(3600, 60 * (2 ** min($attempts, 6))));
            }
        });
    }

    public function poll($id): void
    {
        if (! self::enabled()) {
            return;
        }
        $this->locked((int) $id, function () use ($id) {
            $order = wc_get_order($id);
            $remote = $order ? $order->get_meta('_raslo_delivery_id') : null;
            if (! $remote) {
                return;
            }
            if (in_array($order->get_meta('_raslo_delivery_status'), ['completed', 'cancelled'], true) || (int) $order->get_meta('_raslo_next_poll_at') > time()) {
                return;
            }
            $order->update_meta_data('_raslo_next_poll_at', time() + 300);
            $order->save();
            try {
                $this->apply($order, $this->request('GET', '/deliveries/'.rawurlencode($remote)), false);
                $order->update_meta_data('_raslo_error', '');
                $order->save();
            } catch (\Throwable $error) {
                $order->update_meta_data('_raslo_error', 'STATUS_POLL_FAILED');
                $order->save();
            }
            $this->schedule('raslo_wc_poll_delivery', (int) $id, 300);
        });
    }

    public function apply($order, array $data, bool $initial = false): void
    {
        $statuses = ['draft', 'pending_pricing', 'priced', 'matching', 'assigned', 'accepted', 'pickup', 'in_transit', 'delivered', 'completed', 'cancelled', 'pickup_failed', 'delivery_failed', 'rescheduled', 'expired'];
        if (($data['external_order_id'] ?? null) !== 'wc:'.$order->get_id() || ! is_string($data['id'] ?? null)
            || ! preg_match('/^[a-f0-9-]{36}$/', $data['id']) || ! is_int($data['sequence'] ?? null) || $data['sequence'] < 1 || ! in_array($data['status'] ?? null, $statuses, true)
            || ! is_string($data['tracking_number'] ?? null) || strlen($data['tracking_number']) > 100) {
            throw new \RuntimeException('INVALID_RESPONSE');
        }
        $bound = $order->get_meta('_raslo_delivery_id');
        if ($bound && $bound !== $data['id']) {
            throw new \RuntimeException('INVALID_RESPONSE');
        }
        if (! $initial && ! $bound) {
            throw new \RuntimeException('DELIVERY_NOT_BOUND');
        }
        $sequence = (int) $order->get_meta('_raslo_delivery_sequence');
        if ($bound && $data['sequence'] <= $sequence) {
            if ($data['sequence'] === $sequence) {
                $this->tracking($order, $data);
                $order->save();
            }

            return;
        }
        $order->update_meta_data('_raslo_delivery_id', $data['id']);
        $order->update_meta_data('_raslo_tracking_number', $data['tracking_number']);
        $order->update_meta_data('_raslo_delivery_status', $data['status']);
        $order->update_meta_data('_raslo_delivery_sequence', $data['sequence']);
        $this->tracking($order, $data);
        $order->add_order_note('Raslo delivery '.$data['tracking_number'].': '.$data['status'].'. Delivery pricing/approval is managed in Raslo.');
        $order->save();
    }

    private function tracking($order, array $data): void
    {
        if (array_key_exists('tracking_url', $data) && $data['tracking_url'] === null) {
            $order->delete_meta_data('_raslo_tracking_url');
        }
        if (! empty($data['tracking_url'])) {
            $url = ProductMapper::url($data['tracking_url']);
            $settings = get_option(self::OPTION, []);
            if ($url && wp_parse_url($url)['host'] === wp_parse_url($settings['base'])['host']) {
                $order->update_meta_data('_raslo_tracking_url', $url);
            }
        }
    }

    public function authorizeEvent($request)
    {
        if (! self::enabled() || strlen($request->get_body()) > 65536) {
            return new \WP_Error('raslo_unauthorized', 'Invalid event.', ['status' => 401]);
        }
        try {
            $settings = get_option(self::OPTION, []);
            $secret = self::open($settings['secret'] ?? '');
        } catch (\Throwable $e) {
            return new \WP_Error('raslo_unauthorized', 'Invalid event.', ['status' => 401]);
        }
        $stamp = $request->get_header('x-raslo-timestamp');
        $signature = $request->get_header('x-raslo-signature');
        if (! is_string($stamp) || ! preg_match('/^[0-9]{10}$/', $stamp) || abs(time() - (int) $stamp) > 300
            || ! is_string($signature) || ! hash_equals('v1='.hash_hmac('sha256', $stamp.'.'.$request->get_body(), $secret), $signature)) {
            return new \WP_Error('raslo_unauthorized', 'Invalid event.', ['status' => 401]);
        }

        return true;
    }

    public function event($request)
    {
        $payload = json_decode($request->get_body(), true);
        if (! is_array($payload)) {
            return new \WP_Error('raslo_invalid_event', 'Invalid event.', ['status' => 400]);
        }
        $data = $payload['data'] ?? null;
        if (($payload['type'] ?? null) !== 'delivery.status_changed' || ($payload['id'] ?? null) !== $request->get_header('x-raslo-event-id')
            || ! is_array($data) || ! is_string($data['external_order_id'] ?? null) || ! preg_match('/^wc:([1-9][0-9]*)$/', $data['external_order_id'] ?? '', $matches)) {
            return new \WP_Error('raslo_invalid_event', 'Invalid event.', ['status' => 400]);
        }
        $result = true;
        $acquired = $this->locked((int) $matches[1], function () use ($matches, $data, &$result) {
            $order = wc_get_order((int) $matches[1]);
            if (! $order || ! $order->get_meta('_raslo_delivery_id')) {
                $result = new \WP_Error('raslo_order_pending', 'Delivery binding is not ready.', ['status' => 409]);

                return;
            }
            try {
                $this->apply($order, $data, false);
            } catch (\Throwable $e) {
                $result = new \WP_Error('raslo_invalid_event', 'Event does not match this delivery.', ['status' => 409]);
            }
        });
        if (! $acquired) {
            return new \WP_Error('raslo_order_busy', 'Delivery update is busy; retry.', ['status' => 409]);
        }

        return $result === true ? new \WP_REST_Response(['received' => true], 200) : $result;
    }

    public function reconcile($page = 1): void
    {
        if (! self::enabled()) {
            return;
        }
        $settings = get_option(self::OPTION, []);
        $orders = wc_get_orders(['limit' => 50, 'page' => max(1, (int) $page), 'orderby' => 'date', 'order' => 'DESC', 'date_created' => '>'.(time() - 30 * 86400), 'return' => 'ids']);
        foreach ($orders as $id) {
            $order = wc_get_order($id);
            if ($order && in_array($order->get_meta('_raslo_delivery_status'), ['completed', 'cancelled'], true)) {
                continue;
            }
            if ($order && $order->get_meta('_raslo_delivery_id')) {
                $this->schedule('raslo_wc_poll_delivery', (int) $id, 5);
            } else {
                $this->changed($id);
            }
        }
        if (count($orders) === 50 && (int) $page < 100) {
            as_schedule_single_action(time() + 5, 'raslo_wc_reconcile_deliveries', [(int) $page + 1], self::GROUP, false);
        }
    }

    public function adminStatus($order): void
    {
        echo '<div style="clear:both"><h3>Raslo delivery</h3><p>'.esc_html($order->get_meta('_raslo_tracking_number').' · '.$order->get_meta('_raslo_delivery_status')).'</p>';
        if ($order->get_meta('_raslo_error')) {
            echo '<p>'.esc_html($order->get_meta('_raslo_error')).' — review settings and use the Raslo retry order action.</p>';
        }
        echo '<p>Raslo delivery pricing and approval remain in the Raslo merchant dashboard. Cancelling/refunding this WooCommerce order does not automatically cancel a delivery.</p></div>';
    }

    public function customerStatus($order): void
    {
        if (! $order->get_meta('_raslo_delivery_id')) {
            return;
        }
        echo '<section><h2>Raslo / رسلو</h2><p>'.esc_html($order->get_meta('_raslo_tracking_number').' · '.$order->get_meta('_raslo_delivery_status')).'</p>';
        $url = ProductMapper::url($order->get_meta('_raslo_tracking_url') ?: null);
        if ($url) {
            echo '<a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">Track delivery / تتبع التوصيل</a>';
        }
        echo '</section>';
    }

    public function settings(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        nocache_headers();
        $notice = '';
        $settings = get_option(self::OPTION, []);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            check_admin_referer('raslo-deliveries');
            try {
                $base = rtrim(trim(wp_unslash($_POST['base'] ?? '')), '/');
                $parts = wp_parse_url($base);
                if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443) || ! str_ends_with($parts['path'] ?? '', '/api/integrations/v1')) {
                    throw new \RuntimeException('Use the HTTPS API base ending in /api/integrations/v1.');
                }
                $key = trim(wp_unslash($_POST['api_key'] ?? ''));
                $secret = trim(wp_unslash($_POST['secret'] ?? ''));
                if ($key !== '' && ! preg_match('/^raslo_delivery_[a-f0-9]{64}$/', $key)) {
                    throw new \RuntimeException('Enter the dedicated Raslo delivery key, not the catalog Feed token.');
                }
                if ($secret !== '' && ! preg_match('/^[a-f0-9]{64}$/', $secret)) {
                    throw new \RuntimeException('Invalid webhook signing secret.');
                }
                $enabled = ! empty($_POST['enabled']);
                if ($enabled && $key === '' && empty($settings['key'])) {
                    throw new \RuntimeException('Delivery key is required.');
                }
                if ($enabled && ! function_exists('as_schedule_single_action')) {
                    throw new \RuntimeException('WooCommerce Action Scheduler is required.');
                }
                $settings = ['base' => $base, 'enabled' => $enabled, 'enabled_at' => (! empty($settings['enabled']) && $enabled) ? ($settings['enabled_at'] ?? time()) : time(),
                    'key' => $key !== '' ? self::seal($key) : ($settings['key'] ?? ''), 'secret' => $secret !== '' ? self::seal($secret) : ($settings['secret'] ?? '')];
                update_option(self::OPTION, $settings, false);
                $notice = 'Settings saved. New eligible prepaid orders will be queued. Configure a Raslo shipping method in your UAE shipping zone.';
            } catch (\Throwable $e) {
                $notice = 'Settings not saved. '.$e->getMessage();
            }
        }
        echo '<div class="wrap"><h1>Raslo delivery orders · 0.2.0</h1>';
        if ($notice) {
            echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';
        }
        echo '<p>Prepaid AED orders, physical products, one Raslo shipping line and UAE shipping addresses only. COD is not supported. Your checkout shipping fee is not the final Raslo quote: approve the delivery price in Raslo before dispatch. WordPress/WooCommerce order status and refunds are not changed automatically.</p><p>Webhook URL: <code>'.esc_html(rest_url('raslo/v1/delivery-events')).'</code></p><form method="post">';
        wp_nonce_field('raslo-deliveries');
        echo '<p><label><input type="checkbox" name="enabled" value="1" '.checked(! empty($settings['enabled']), true, false).'> Enable delivery forwarding for new eligible orders</label></p><p><label>Raslo HTTPS API base<br><input style="width:min(650px,100%)" name="base" type="url" required value="'.esc_attr($settings['base'] ?? '').'" placeholder="https://raslo.example/api/integrations/v1"></label></p><p><label>Delivery API key (leave empty to retain)<br><input style="width:min(650px,100%)" name="api_key" type="password" autocomplete="new-password"></label></p><p><label>Webhook signing secret (leave empty to retain)<br><input style="width:min(650px,100%)" name="secret" type="password" autocomplete="new-password"></label></p><button class="button button-primary">Save delivery settings</button></form><p>Schedule Action Scheduler processing, including raslo_wc_send_delivery, raslo_wc_poll_delivery and raslo_wc_reconcile_deliveries. Keys are protected with the WordPress auth salt; changing salts requires saving credentials again. Existing orders require an explicit Raslo retry action.</p></div>';
    }
}
