<?php
/**
 * Plugin Name: susmo Catalog
 * Description: Read-only catalog without public transactions, customer accounts or customer sessions.
 * Version: 1.0.0
 * Text Domain: susmo-catalog
 */
defined('ABSPATH') || exit;

// Company details are public business content, edited by administrators only.
add_action('admin_menu', function () {
    add_options_page('susmo Company', 'susmo Company', 'manage_options', 'susmo-company', function () {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>susmo Company</h1><p>Public company information displayed on the website. No customer information is collected here.</p><form action="options.php" method="post">';
        settings_fields('susmo_company'); do_settings_sections('susmo-company'); submit_button();
        echo '</form></div>';
    });
});
add_action('admin_init', function () {
    register_setting('susmo_company', 'susmo_company', array('type'=>'array','sanitize_callback'=>function ($values) {
        $saved = get_option('susmo_company', array());
        foreach (array('name','address','email') as $key) {
            $saved[$key] = $key === 'email' ? sanitize_email($values[$key] ?? '') : sanitize_textarea_field($values[$key] ?? '');
        }
        return $saved;
    }));
    add_settings_section('susmo_company_public','Public company details','__return_false','susmo-company');
    foreach (array('name'=>'Company name (English)','address'=>'Company address (English)','email'=>'Company email') as $key=>$label) {
        add_settings_field('susmo_company_' . $key,$label,function () use ($key) {
            $values = get_option('susmo_company',array());
            printf('<input class="large-text" type="%s" name="susmo_company[%s]" value="%s">', $key === 'email' ? 'email' : 'text', esc_attr($key),esc_attr($values[$key] ?? ''));
        },'susmo-company','susmo_company_public');
    }
});
add_shortcode('susmo_company_details',function () {
    $company = get_option('susmo_company',array());
    $html = '<dl class="company-details">';
    foreach (array('Brand'=>'brand','Company'=>'name','Address'=>'address','Email'=>'email') as $label=>$key) {
        $value = esc_html($company[$key] ?? '');
        if ($key === 'email') { $value = '<a href="' . esc_url('mailto:' . ($company[$key] ?? '')) . '">' . $value . '</a>'; }
        $html .= '<div><dt>' . esc_html($label) . '</dt><dd>' . $value . '</dd></div>';
    }
    return $html . '</dl>';
});

add_filter('pre_option_users_can_register', '__return_zero');
foreach (array('woocommerce_enable_myaccount_registration', 'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_allow_tracking', 'woocommerce_feature_order_attribution_enabled') as $option) {
    add_filter('pre_option_' . $option, function () { return 'no'; });
}
add_filter('wc_order_attribution_allow_tracking', '__return_false');
add_filter('xmlrpc_enabled', '__return_false');
add_filter('show_avatars', '__return_false');
add_filter('comments_open', '__return_false', 100);
add_filter('pings_open', '__return_false', 100);
add_filter('woocommerce_product_reviews_enabled', '__return_false');

function susmo_catalog_unavailable() {
    nocache_headers();
    wp_die('This website is a product catalog. Online transactions and customer accounts are unavailable.', 'Catalog only', array('response' => 410));
}

// Run before WooCommerce's init/wp_loaded form handlers and session creation.
add_action('init', function () {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $path = rawurldecode((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $disabled_path = preg_match('~/(?:cart|checkout|my-account|order-pay|order-received|edit-address|payment-methods|add-payment-method|lost-password)(?:/|$)~i', $path);
    // API URLs use the REST guard below; page URLs return the catalog-only 410.
    if (preg_match('~^/wp-json(?:/|$)~i', $path) || isset($_GET['rest_route'])) { $disabled_path = false; }
    $disabled_parameter = false;
    foreach (array('add-to-cart', 'wc-ajax', 'wc-api', 'order_again', 'remove_item', 'apply_coupon', 'update_cart', 'woocommerce-register-nonce', 'woocommerce-login-nonce') as $key) {
        if (isset($_GET[$key]) || isset($_POST[$key])) { $disabled_parameter = true; break; }
    }
    $disabled_login = $script === 'wp-login.php' && in_array($_REQUEST['action'] ?? '', array('register', 'lostpassword', 'retrievepassword', 'resetpass', 'rp'), true);
    $disabled_script = in_array($script, array('wp-signup.php', 'wp-comments-post.php', 'wp-trackback.php', 'xmlrpc.php'), true);
    $old_page_id = absint($_GET['page_id'] ?? 0);
    if ($old_page_id && in_array($old_page_id, array_map('absint', array(
        get_option('woocommerce_cart_page_id'), get_option('woocommerce_checkout_page_id'), get_option('woocommerce_myaccount_page_id'),
    )), true)) { $disabled_path = true; }
    if ($disabled_path || $disabled_parameter || $disabled_login || $disabled_script) { susmo_catalog_unavailable(); }
}, -100);

add_action('template_redirect', function () {
    if ((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout()) ||
        (function_exists('is_account_page') && is_account_page())) { susmo_catalog_unavailable(); }
}, -100);

// Keep administrator/product-editor authentication, not customer authentication.
add_filter('authenticate', function ($user) {
    if ($user instanceof WP_User && !user_can($user, 'manage_options') && !user_can($user, 'edit_products')) {
        return new WP_Error('susmo_catalog_account_disabled', 'Customer accounts are unavailable on this website.');
    }
    return $user;
}, 100);
foreach (array('registration_errors', 'woocommerce_registration_errors') as $hook) {
    add_filter($hook, function ($errors) {
        $errors->add('susmo_catalog_registration_disabled', 'Customer registration is unavailable on this website.');
        return $errors;
    });
}
add_filter('preprocess_comment', function () { susmo_catalog_unavailable(); });

add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    $route = $request->get_route();
    if (preg_match('~^/wc/store/~', $route) || preg_match('~^/wc/v\d+/(?:customers|orders)(?:/|$)~', $route)) {
        return new WP_Error('susmo_catalog_read_only', 'Online transactions and customer data endpoints are unavailable.', array('status' => 403));
    }
    $manager = current_user_can('manage_options') || current_user_can('edit_products');
    if (!$manager && (preg_match('~^/wp/v2/(?:users|comments)(?:/|$)~', $route) ||
        (preg_match('~^/wc/~', $route) && !in_array($request->get_method(), array('GET', 'HEAD', 'OPTIONS'), true)))) {
        return new WP_Error('susmo_catalog_public_write_disabled', 'This endpoint is unavailable to visitors.', array('status' => 403));
    }
    return $result;
}, -100, 3);

/**
 * WooCommerce 11.2 initialize_session() accepts a replacement handler. Default
 * constructor/init attach request cloning, cookies and shutdown persistence.
 * Catalog handlers intentionally skip all of these, including when old browser
 * cookies or a session query token are supplied. No existing records are deleted.
 */
add_filter('woocommerce_session_handler', function () {
    if (!class_exists('Susmo_Catalog_Session', false)) {
        class Susmo_Catalog_Session extends WC_Session_Handler {
            public function __construct() {}
            public function init() {}
            public function has_session() { return false; }
            public function get_session_cookie() { return false; }
            public function get_session($customer_id, $default_value = false) { return $default_value; }
            public function set($key, $value) {}
            public function save_data($old_session_key = '') {}
            public function set_customer_session_cookie($set) {}
            public function maybe_set_customer_session_cookie() {}
            public function destroy_session() {}
            public function forget_session() {}
            public function cleanup_sessions() {}
        }
    }
    return 'Susmo_Catalog_Session';
}, 100);
add_filter('woocommerce_cart_session_initialize', '__return_false', 100);
add_filter('woocommerce_persistent_cart_enabled', '__return_false');
add_filter('woocommerce_is_purchasable', '__return_false', 100);
add_filter('woocommerce_variation_is_purchasable', '__return_false', 100);
add_filter('woocommerce_add_to_cart_validation', '__return_false', 100);
add_filter('woocommerce_available_payment_gateways', '__return_empty_array', 100);
add_filter('woocommerce_get_price_html', '__return_empty_string', 100);
add_filter('woocommerce_structured_data_product', function ($data) {
    unset($data['offers'], $data['review'], $data['aggregateRating']);
    return $data;
}, 100);
add_filter('woocommerce_product_tabs', function ($tabs) { unset($tabs['reviews']); return $tabs; }, 100);

add_action('wp', function () {
    remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    remove_action('storefront_header', 'storefront_header_cart', 60);
    remove_action('storefront_footer', 'storefront_handheld_footer_bar', 999);
});
add_filter('wp_nav_menu_objects', function ($items) {
    return array_values(array_filter($items, function ($item) {
        return !preg_match('~/(?:cart|checkout|my-account)(?:/|$|\?)~i', $item->url);
    }));
});
add_action('wp_enqueue_scripts', function () {
    foreach (array('wc-cart-fragments', 'wc-add-to-cart', 'wc-add-to-cart-variation', 'wc-checkout', 'wc-order-attribution', 'sourcebuster-js') as $handle) {
        wp_dequeue_script($handle);
    }
}, 1000);
