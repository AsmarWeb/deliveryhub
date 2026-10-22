<?php

namespace Raslo\WooCommerce;

class ProductMapper
{
    public static function text(string $value, int $limit): string
    {
        return wp_html_excerpt(wp_strip_all_tags($value), $limit, '');
    }

    public static function url($value): ?string
    {
        if (! is_string($value) || strlen($value) > 2048) {
            return null;
        }
        $parts = wp_parse_url($value);

        return ($parts['scheme'] ?? null) === 'https' && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass']) ? $value : null;
    }

    public static function minor(string $price): int
    {
        if (! preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/D', $price, $parts)) {
            throw new \InvalidArgumentException('Unsupported price.');
        }
        $minor = (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
        if ($minor > 999999999) {
            throw new \InvalidArgumentException('Price is too large.');
        }

        return $minor;
    }

    public static function map($product, $parent = null): array
    {
        $root = $parent ?: $product;
        $supported = $product->is_type('simple') || $product->is_type('variation');
        $published = $supported && $product->get_status() === 'publish' && $root->get_status() === 'publish'
            && $root->get_catalog_visibility() !== 'hidden'
            && ! get_post_field('post_password', $product->get_id()) && ! get_post_field('post_password', $root->get_id())
            && ! $product->is_virtual() && ! $product->is_downloadable();
        $price = wc_format_decimal(wc_get_price_to_display($product), 2);
        try {
            $minor = self::minor($price);
        } catch (\InvalidArgumentException $error) {
            $minor = 0;
            $published = false;
        }
        // An unset Woo price is not a free product.
        if ($product->get_price() === '') {
            $published = false;
        }
        $owner = $parent && $product->get_manage_stock() === 'parent' ? $parent : $product;
        $stock = $owner->get_manage_stock() ? (int) floor((float) $owner->get_stock_quantity()) : 1;
        $stock = $product->is_in_stock() && $root->is_in_stock() ? max(0, min(1000000, $stock)) : 0;
        $category = null;
        foreach ($root->get_category_ids() as $id) {
            $term = get_term($id, 'product_cat');
            if ($term && ! is_wp_error($term)) {
                $category = self::text($term->name, 100);
                break;
            }
        }
        $imageId = $product->get_image_id() ?: $root->get_image_id();

        return [
            'external_id' => 'wc:'.$product->get_id(), 'name' => self::text($product->get_name(), 200) ?: 'Product '.$product->get_id(),
            'description' => self::text($product->get_description() ?: $root->get_description(), 10000),
            'sku' => self::text($product->get_sku(), 120) ?: null, 'category' => $category,
            'price_minor' => $minor, 'currency' => 'AED', 'stock_quantity' => $stock,
            'status' => in_array($product->get_status(), ['trash', 'auto-draft'], true) || ! $supported ? 'archived' : ($published ? 'active' : 'draft'),
            'image_url' => self::url($imageId ? wp_get_attachment_image_url($imageId, 'large') : null),
            'product_url' => self::url($product->get_permalink()),
        ];
    }

    public static function tombstone(int $id, ?array $previous): array
    {
        $data = $previous ?: ['external_id' => 'wc:'.$id, 'name' => 'Removed product '.$id,
            'price_minor' => 0, 'currency' => 'AED', 'description' => null, 'sku' => null,
            'category' => null, 'image_url' => null, 'product_url' => null];
        unset($data['source_updated_at']);
        $data['status'] = 'archived';
        $data['stock_quantity'] = 0;

        return $data;
    }
}
