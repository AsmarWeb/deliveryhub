<?php

// Minimal WordPress/WooCommerce boundary doubles. These do not replace a live-store acceptance test.
if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
function wp_html_excerpt($value, $limit, $suffix = '')
{
    return mb_substr($value, 0, $limit);
}
function wp_strip_all_tags($value)
{
    return strip_tags($value);
}
function wp_parse_url($value)
{
    return parse_url($value);
}
function wp_json_encode($value)
{
    return json_encode($value);
}
function get_post_field($field, $id)
{
    return $GLOBALS['wc_passwords'][$id] ?? '';
}
function get_post_type($id)
{
    return isset($GLOBALS['wc_products'][$id]) ? 'product' : null;
}
function wc_get_product($id)
{
    return $GLOBALS['wc_products'][$id] ?? false;
}
function wc_get_products($args)
{
    return $GLOBALS['wc_product_ids'] ?? [];
}
function wc_get_price_to_display($product)
{
    return $product->get_price() === '' ? 0 : $product->get_price();
}
function wc_format_decimal($value, $precision)
{
    return number_format((float) $value, $precision, '.', '');
}
function get_woocommerce_currency()
{
    return $GLOBALS['wc_currency'] ?? 'AED';
}
function get_term($id, $taxonomy)
{
    return (object) ['name' => 'Bags'];
}
function is_wp_error($value)
{
    return $value instanceof WP_Error;
}
function wp_get_attachment_image_url($id, $size)
{
    return 'https://shop.example/image-'.$id.'.jpg';
}
function get_option($key, $default = false)
{
    return $GLOBALS['wp_options'][$key] ?? $default;
}
function update_option($key, $value, $autoload = false)
{
    $GLOBALS['wp_options'][$key] = $value;
}
function add_action($hook, $callback, $priority = 10, $arguments = 1)
{
    $GLOBALS['wp_hooks'][$hook][] = $callback;
}
function register_rest_route($namespace, $path, $options)
{
    $GLOBALS['wp_routes'][$namespace.$path] = $options;
}
function as_schedule_single_action($time, $hook, $args, $group, $unique = false)
{
    $GLOBALS['wp_scheduled'][] = compact('time', 'hook', 'args', 'group', 'unique');

    return count($GLOBALS['wp_scheduled']);
}
function as_has_scheduled_action($hook, $args, $group)
{
    return false;
}
function as_schedule_recurring_action(...$args)
{
    return 1;
}
function as_unschedule_all_actions($hook, $args, $group)
{
    return null;
}
class WP_Error
{
    public function __construct(public $code, public $message, public $data) {}
}
class WP_REST_Response
{
    public function __construct(public $data, public $status, public $headers = []) {}
}
class RasloFakeProduct
{
    public function __construct(public int $id, public string $type = 'simple', public array $values = []) {}

    public function get_id()
    {
        return $this->id;
    }

    public function is_type($type)
    {
        return $this->type === $type;
    }

    public function __call($name, $args)
    {
        return $this->values[$name] ?? match ($name) {
            'get_status' => 'publish', 'get_catalog_visibility' => 'visible', 'is_virtual', 'is_downloadable', 'get_manage_stock' => false,
            'get_price' => '129.00', 'get_name' => 'Travel Bag', 'get_description' => '<b>Plain description</b>', 'get_sku' => 'BAG',
            'get_stock_quantity' => null, 'is_in_stock' => true, 'get_category_ids' => [1], 'get_image_id' => 1,
            'get_permalink' => 'https://shop.example/product/'.$this->id, 'get_children' => [], 'get_parent_id' => 0,
            default => throw new RuntimeException($name),
        };
    }
}
class RasloFakeWpdb
{
    public string $prefix = 'wp_';

    public string $last_error = '';

    public PDO $pdo;

    public int $lockResult = 1;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE wp_raslo_catalog (product_id INTEGER PRIMARY KEY,parent_id INTEGER,payload TEXT,checksum TEXT,version INTEGER)');
    }

    public function prepare($query, ...$args)
    {
        $i = 0;

        return preg_replace_callback('/%[ds]/', function ($m) use ($args, &$i) {
            $value = $args[$i++];

            return $m[0] === '%d' ? (string) (int) $value : $this->pdo->quote($value);
        }, $query);
    }

    public function query($query)
    {
        $this->last_error = '';
        try {
            if ($query === 'START TRANSACTION') {
                return $this->pdo->beginTransaction();
            }
            if ($query === 'COMMIT') {
                return $this->pdo->commit();
            }
            if ($query === 'ROLLBACK') {
                return $this->pdo->rollBack();
            }

            return $this->pdo->exec(str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $query));
        } catch (Throwable $e) {
            $this->last_error = 'TEST_DB_ERROR';

            return false;
        }
    }

    public function get_results($query, $format = null)
    {
        return $this->pdo->query(str_replace(' FOR UPDATE', '', $query))->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_row($query, $format = null)
    {
        return $this->get_results($query)[0] ?? null;
    }

    public function get_col($query)
    {
        return $this->pdo->query($query)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function get_var($query)
    {
        if (str_contains($query, 'GET_LOCK')) {
            return $this->lockResult;
        }
        if (str_contains($query, 'RELEASE_LOCK')) {
            return 1;
        }

        return $this->pdo->query($query)->fetchColumn();
    }

    public function update($table, $values, $where)
    {
        $sets = [];
        $conditions = [];
        foreach ($values as $key => $value) {
            $sets[] = $key.'='.$this->pdo->quote((string) $value);
        }
        foreach ($where as $key => $value) {
            $conditions[] = $key.'='.$this->pdo->quote((string) $value);
        }

        return $this->query('UPDATE '.$table.' SET '.implode(',', $sets).' WHERE '.implode(' AND ', $conditions));
    }
}
