<?php

namespace Raslo\WooCommerce;

// A test-only namespaced clock; production plugin calls still use PHP's real clock.
function time(): int
{
    return $GLOBALS['raslo_wc_test_time'] ?? \time();
}
