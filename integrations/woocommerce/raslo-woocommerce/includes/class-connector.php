<?php

namespace Raslo\WooCommerce;

class Connector
{
    public const GROUP = 'raslo-catalog';

    private const ENABLED = 'raslo_wc_enabled';

    private const HASH = 'raslo_wc_key_hash';

    private static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix.'raslo_catalog';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            product_id bigint(20) unsigned NOT NULL,
            parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
            payload longtext NOT NULL,
            checksum char(64) NOT NULL,
            version bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (product_id),
            KEY parent_id (parent_id)
        ) ENGINE=InnoDB {$charset};");
        add_option(self::ENABLED, false, '', false);
    }

    public static function deactivate(): void
    {
        update_option(self::ENABLED, false, false);
        if (function_exists('as_unschedule_all_actions')) {
            foreach (['raslo_wc_rebuild', 'raslo_wc_sync_product', 'raslo_wc_reconcile', 'raslo_wc_reconcile_index'] as $hook) {
                as_unschedule_all_actions($hook, null, self::GROUP);
            }
        }
    }

    public function boot(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
        add_action('admin_menu', function () {
            add_submenu_page('woocommerce', 'Raslo', 'Raslo', 'manage_woocommerce', 'raslo-woocommerce', [$this, 'settings']);
        });
        add_action('raslo_wc_sync_product', [$this, 'sync'], 10, 1);
        add_action('raslo_wc_rebuild', [$this, 'rebuild'], 10, 1);
        add_action('raslo_wc_reconcile_index', [$this, 'reconcileIndex'], 10, 1);
        add_action('raslo_wc_reconcile', function () {
            $this->schedule('raslo_wc_rebuild', [1]);
        });
        add_action('init', function () {
            if (get_option(self::ENABLED) && function_exists('as_has_scheduled_action') && ! as_has_scheduled_action('raslo_wc_reconcile', [], self::GROUP)) {
                as_schedule_recurring_action(time() + 300, 21600, 'raslo_wc_reconcile', [], self::GROUP, true);
            }
        }, 20);
        foreach (['woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation',
            'woocommerce_delete_product', 'woocommerce_delete_product_variation', 'woocommerce_trash_product', 'woocommerce_trash_product_variation',
            'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status'] as $hook) {
            add_action($hook, [$this, 'changed'], 20, 1);
        }
        add_action('untrashed_post', [$this, 'changed'], 20, 1);
    }

    private function schedule(string $hook, array $args, int $delay = 1, bool $unique = true): void
    {
        if (! get_option(self::ENABLED) || ! function_exists('as_schedule_single_action')) {
            return;
        }
        if (! as_schedule_single_action(time() + $delay, $hook, $args, self::GROUP, $unique)) {
            update_option('raslo_wc_error', 'QUEUE_SCHEDULE_FAILED', false);
        }
    }

    public function changed($product): void
    {
        $id = is_object($product) && method_exists($product, 'get_id') ? $product->get_id() : (int) $product;
        $type = get_post_type($id);
        if ($id > 0 && (! $type || in_array($type, ['product', 'product_variation'], true))) {
            $this->schedule('raslo_wc_sync_product', [$id], 1, false);
        }
    }

    public function rebuild(int $page = 1): void
    {
        if (! get_option(self::ENABLED)) {
            return;
        }
        $ids = wc_get_products(['limit' => 50, 'page' => max(1, $page), 'orderby' => 'ID', 'order' => 'ASC',
            'status' => ['publish', 'private', 'draft', 'pending', 'trash'], 'return' => 'ids']);
        foreach ($ids as $id) {
            $this->changed((int) $id);
        }
        if (count($ids) === 50) {
            $this->schedule('raslo_wc_rebuild', [$page + 1]);
        } else {
            update_option('raslo_wc_last_scan', time(), false);
            $this->schedule('raslo_wc_reconcile_index', [0]);
        }
    }

    public function reconcileIndex(int $cursor = 0): void
    {
        if (! get_option(self::ENABLED)) {
            return;
        }
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare('SELECT product_id FROM '.self::table().' WHERE product_id > %d ORDER BY product_id ASC LIMIT 50', $cursor));
        foreach ($ids as $id) {
            $this->changed((int) $id);
        }
        if (count($ids) === 50) {
            $this->schedule('raslo_wc_reconcile_index', [(int) end($ids)]);
        }
    }

    public function sync(int $id): void
    {
        if (! get_option(self::ENABLED) || get_woocommerce_currency() !== 'AED') {
            return;
        }
        global $wpdb;
        $lockName = 'raslo-wc-'.substr(hash('sha256', self::table()), 0, 16).'-'.$id;
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $lockName));
        if ((string) $locked !== '1') {
            if ($locked === null) {
                update_option('raslo_wc_error', 'LOCK_UNAVAILABLE', false);
                throw new \RuntimeException('Catalog locking is unavailable.');
            }
            $this->schedule('raslo_wc_sync_product', [$id], 2, false);

            return;
        }
        try {
            $this->syncLocked($id);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    private function syncLocked(int $id): void
    {
        global $wpdb;
        $product = wc_get_product($id);
        if (! $product) {
            $children = $wpdb->get_col($wpdb->prepare('SELECT product_id FROM '.self::table().' WHERE parent_id = %d', $id));
            foreach ($children as $child) {
                $this->schedule('raslo_wc_sync_product', [(int) $child], 1, false);
            }
            $this->archive($id);

            return;
        }
        $parent = $product->is_type('variation') ? wc_get_product($product->get_parent_id()) : null;
        if ($product->is_type('variation') && ! $parent) {
            $this->archive($id);

            return;
        }
        $this->persist($id, $parent ? $parent->get_id() : 0, ProductMapper::map($product, $parent));
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $child) {
                $this->changed((int) $child);
            }
        }
    }

    private function archive(int $id): void
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT payload,parent_id FROM '.self::table().' WHERE product_id = %d', $id), ARRAY_A);
        $previous = $row ? json_decode($row['payload'], true) : null;
        $this->persist($id, (int) ($row['parent_id'] ?? 0), ProductMapper::tombstone($id, $previous));
    }

    private function persist(int $id, int $parentId, array $payload): void
    {
        global $wpdb;
        $table = self::table();
        $checksum = hash('sha256', wp_json_encode($payload));
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (product_id,parent_id,payload,checksum,version) VALUES (%d,%d,'','',0)", $id, $parentId));
            $row = $wpdb->get_row($wpdb->prepare("SELECT checksum,version FROM {$table} WHERE product_id = %d FOR UPDATE", $id), ARRAY_A);
            if (! $row) {
                throw new \RuntimeException('Projection row unavailable.');
            }
            if (hash_equals((string) $row['checksum'], $checksum)) {
                $wpdb->query('COMMIT');

                return;
            }
            $version = time() - 1;
            if ($version <= (int) $row['version']) {
                $wpdb->query('ROLLBACK');
                // Whole-second source versions cannot be ordered in the same second; re-read the final source state later.
                $this->schedule('raslo_wc_sync_product', [$id], 2, false);

                return;
            }
            $payload['source_updated_at'] = gmdate('Y-m-d\TH:i:sP', $version);
            if ($wpdb->update($table, ['parent_id' => $parentId, 'payload' => wp_json_encode($payload), 'checksum' => $checksum, 'version' => $version], ['product_id' => $id]) === false) {
                throw new \RuntimeException('Projection write failed.');
            }
            $wpdb->query('COMMIT');
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            update_option('raslo_wc_error', 'CATALOG_WRITE_FAILED', false);
            throw new \RuntimeException('Raslo catalog projection failed.');
        }
    }

    public function routes(): void
    {
        register_rest_route('raslo/v1', '/catalog', ['methods' => 'GET', 'permission_callback' => [$this, 'authorize'], 'callback' => [$this, 'feed']]);
    }

    public function authorize($request)
    {
        $header = (string) $request->get_header('authorization');
        $expected = (string) get_option(self::HASH, '');
        if (! get_option(self::ENABLED) || ! $expected || ! preg_match('/^Bearer ([A-Za-z0-9]{64})$/D', $header, $matches)
            || ! hash_equals($expected, hash('sha256', $matches[1]))) {
            return new \WP_Error('raslo_unauthorized', 'Invalid or disabled feed credentials.', ['status' => 401]);
        }

        return true;
    }

    public function feed($request)
    {
        if (get_woocommerce_currency() !== 'AED') {
            return new \WP_Error('raslo_currency', 'This connector currently supports AED catalogs only.', ['status' => 409]);
        }
        $cursor = (string) ($request->get_param('cursor') ?? '0');
        if (! preg_match('/^(0|[1-9][0-9]{0,17})$/D', $cursor)) {
            return new \WP_Error('raslo_cursor', 'Invalid cursor.', ['status' => 400]);
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT product_id,payload FROM '.self::table().' WHERE product_id > %d AND version > 0 ORDER BY product_id ASC LIMIT 101', (int) $cursor), ARRAY_A);
        if ($wpdb->last_error) {
            return new \WP_Error('raslo_catalog', 'Catalog unavailable.', ['status' => 503]);
        }
        $products = [];
        $selected = [];
        $bytes = 0;
        foreach ($rows as $row) {
            if (count($selected) >= 100 || ($selected && $bytes + strlen($row['payload']) > 1048576)) {
                break;
            }
            $payload = json_decode($row['payload'], true);
            if (! is_array($payload)) {
                return new \WP_Error('raslo_catalog', 'Catalog unavailable.', ['status' => 503]);
            }
            $selected[] = $row;
            $products[] = $payload;
            $bytes += strlen($row['payload']);
        }
        $more = count($selected) < count($rows);
        $last = end($selected);

        return new \WP_REST_Response(['products' => $products, 'next_cursor' => $more ? (string) $last['product_id'] : null], 200,
            ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
    }

    public function settings(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('Not allowed.');
        }
        nocache_headers();
        $plain = null;
        $notice = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            check_admin_referer('raslo_wc_settings');
            $action = sanitize_key(wp_unslash($_POST['raslo_action'] ?? ''));
            if ($action === 'disable') {
                self::deactivate();
                $notice = 'Feed disabled. Disable the corresponding connection in Raslo to hide its already imported products.';
            } elseif (in_array($action, ['enable', 'rebuild', 'rotate'], true)) {
                if (get_woocommerce_currency() !== 'AED' || ! function_exists('as_schedule_single_action')) {
                    $notice = 'AED currency and WooCommerce Action Scheduler are required.';
                } else {
                    if ($action === 'enable' || $action === 'rotate') {
                        $plain = bin2hex(random_bytes(32));
                        update_option(self::HASH, hash('sha256', $plain), false);
                    }
                    if ($action === 'enable') {
                        update_option(self::ENABLED, true, false);
                    }
                    if ($action !== 'rotate') {
                        $this->schedule('raslo_wc_rebuild', [1]);
                    }
                    $notice = 'Settings updated. Product indexing is processed through WooCommerce Scheduled Actions.';
                }
            }
        }
        global $wpdb;
        $count = (int) $wpdb->get_var('SELECT COUNT(*) FROM '.self::table().' WHERE version > 0');
        echo '<div class="wrap"><h1>Raslo Enterprise · WooCommerce</h1><p>Private, read-only product synchronization. Physical simple products and variations, AED only. Checkout stays in WooCommerce.</p>';
        if ($notice) {
            echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';
        }
        if ($plain) {
            echo '<div class="notice notice-warning"><p>Copy this feed token now; only its hash is stored. Update the Raslo Feed connection after rotation.</p><code>'.esc_html($plain).'</code></div>';
        }
        echo '<p>Feed URL: <code>'.esc_html(rest_url('raslo/v1/catalog')).'</code></p><p>State: '.(get_option(self::ENABLED) ? 'enabled' : 'disabled').' · Indexed records (including archived): '.esc_html((string) $count).'</p>';
        echo '<p>In Raslo, allow this exact store hostname and create a Feed connection using the URL and token above. Wait for indexing, then synchronize and obtain administrator publication approval. Use HTTPS and pretty permalinks.</p>';
        echo '<p>Source changes are indexed in the background; Raslo pulls every 15 minutes. Rebuild requests reconciliation. Inspect WooCommerce → Status → Scheduled Actions, group raslo-catalog.</p>';
        echo '<p>Last safe error: '.esc_html((string) get_option('raslo_wc_error', 'none')).'</p>';
        foreach (['enable' => 'Enable / issue new token', 'rebuild' => 'Rebuild product index', 'rotate' => 'Rotate feed token', 'disable' => 'Disable feed'] as $value => $label) {
            echo '<form method="post" style="display:inline-block;margin:8px">';
            wp_nonce_field('raslo_wc_settings');
            echo '<input type="hidden" name="raslo_action" value="'.esc_attr($value).'"><button class="button">'.esc_html($label).'</button></form>';
        }
        echo '</div>';
    }
}
