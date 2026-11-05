<?php
/** susmo presentation layer. Catalog restrictions live in the catalog plugin. */
defined('ABSPATH') || exit;

function susmo_asset($name) { return get_stylesheet_directory_uri() . '/assets/images/' . $name; }
function susmo_company($key) {
    $company = get_option('susmo_company', array());
    return isset($company[$key]) ? (string) $company[$key] : '';
}
function susmo_nav() {
    $items = array('Home'=>'/', 'Wigs'=>'/shop/', 'About Us'=>'/about-us/', 'Hair Guide'=>'/hair-guide/', 'FAQ'=>'/faq/', 'Contact Us'=>'/contact/');
    foreach ($items as $label=>$path) {
        $active = $path === '/' ? is_front_page() : (trailingslashit((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) === $path);
        printf('<a href="%s"%s>%s</a>', esc_url(home_url($path)), $active ? ' aria-current="page"' : '', esc_html($label));
    }
}
function susmo_product_card($product) {
    if (!$product) { return; }
    echo '<article class="product-card"><a class="product-card-image" href="' . esc_url($product->get_permalink()) . '">';
    echo $product->get_image('large', array('loading'=>'lazy'));
    echo '<span class="image-link-label">Explore style <span aria-hidden="true">↗</span></span></a>';
    echo '<div class="product-card-caption"><div><span class="eyebrow">THE SUSMO COLLECTION</span><h3><a href="' . esc_url($product->get_permalink()) . '">' . esc_html($product->get_name()) . '</a></h3></div><span class="product-card-arrow" aria-hidden="true">↗</span></div></article>';
}
add_action('wp_enqueue_scripts', function () {
    foreach (array('storefront-style', 'storefront-woocommerce-style', 'storefront-fonts', 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen') as $handle) { wp_dequeue_style($handle); }
    foreach (array('storefront-navigation', 'storefront-handheld-footer-bar') as $handle) { wp_dequeue_script($handle); }
    wp_enqueue_style('susmo-style', get_stylesheet_uri(), array(), (string) filemtime(get_stylesheet_directory() . '/style.css'));
    wp_enqueue_script('susmo-site', get_stylesheet_directory_uri() . '/assets/site.js', array(), (string) filemtime(get_stylesheet_directory() . '/assets/site.js'), true);
}, 100);
add_filter('template_include', function ($template) {
    if (function_exists('is_product') && is_product()) { return get_stylesheet_directory() . '/product.php'; }
    if ((function_exists('is_shop') && is_shop()) || is_tax('product_cat') || is_tax('product_tag')) { return get_stylesheet_directory() . '/catalog.php'; }
    return $template;
}, 100);
add_filter('body_class', function ($classes) { $classes[] = 'susmo-site'; return $classes; });
add_action('after_setup_theme', function () { add_theme_support('title-tag'); add_theme_support('post-thumbnails'); });
