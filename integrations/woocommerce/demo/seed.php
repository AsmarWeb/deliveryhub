<?php

// Run through wp eval-file only in this isolated lab.
if (! defined('ABSPATH') || ! class_exists('WC_Product_Simple')) {
    throw new RuntimeException('WooCommerce is required.');
}
update_option('woocommerce_currency', 'AED');
foreach ([['RASLO-LAB-BAG', 'تجريبي — حقيبة WooCommerce', '129.00', 8], ['RASLO-LAB-AUDIO', 'تجريبي — سماعات WooCommerce', '89.00', 5]] as [$sku, $name, $price, $stock]) {
    $id = wc_get_product_id_by_sku($sku);
    $product = $id ? wc_get_product($id) : new WC_Product_Simple;
    $product->set_name($name);
    $product->set_sku($sku);
    $product->set_regular_price($price);
    $product->set_description('بيانات مختبر محلي فقط. غير مخصص للبيع.');
    $product->set_manage_stock(true);
    $product->set_stock_quantity($stock);
    $product->set_status('publish');
    $product->save();
}
WP_CLI::success('Two demo WooCommerce products created. Enable the Raslo connector in the WordPress dashboard.');
