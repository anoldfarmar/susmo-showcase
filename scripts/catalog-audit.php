<?php
/** Aggregate-only audit: never prints customer, administrator or order records. */
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/.local/www/wp-load.php';
global $wpdb;
function susmo_table_count($suffix) {
    global $wpdb;
    $table = $wpdb->prefix . $suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { return null; }
    return (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . esc_sql($table) . '`');
}
echo wp_json_encode(array(
    'customer_roles'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE '%capabilities' AND meta_value LIKE '%customer%'"),
    'customer_lookup'=>susmo_table_count('wc_customer_lookup'),
    'hpos_orders'=>susmo_table_count('wc_orders'),
    'legacy_orders'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_refund')"),
    'sessions'=>susmo_table_count('woocommerce_sessions'),
    'comments'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}"),
    'catalog_active'=>in_array('susmo-catalog/susmo-catalog.php', get_option('active_plugins'), true),
    'registration_enabled'=>(bool)get_option('users_can_register'),
)) . "\n";
