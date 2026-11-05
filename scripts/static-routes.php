<?php
/** Read-only inventory of published catalog content. No credentials or private records. */
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/.local/www/wp-load.php';
if (!in_array('susmo-catalog/susmo-catalog.php', get_option('active_plugins'), true)) {
    throw new RuntimeException('Catalog restrictions must be active.');
}
$routes = array('/', '/shop/');
$excluded = array_filter(array_map('intval', array(get_option('woocommerce_cart_page_id'), get_option('woocommerce_checkout_page_id'), get_option('woocommerce_myaccount_page_id'))));
foreach (get_posts(array('post_type'=>array('page','product'), 'post_status'=>'publish', 'numberposts'=>-1)) as $post) {
    if (in_array($post->ID, $excluded, true) || post_password_required($post)) { continue; }
    $routes[] = wp_parse_url(get_permalink($post), PHP_URL_PATH);
}
foreach (get_terms(array('taxonomy'=>array('product_cat','product_tag'), 'hide_empty'=>true)) as $term) {
    $url = get_term_link($term);
    if (!is_wp_error($url)) { $routes[] = wp_parse_url($url, PHP_URL_PATH); }
}
echo wp_json_encode(array_values(array_unique($routes)), JSON_UNESCAPED_SLASHES) . "\n";
