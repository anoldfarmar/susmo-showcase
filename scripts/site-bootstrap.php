<?php
/** CLI-only local bootstrap. Never deploy this script into the document root. */
if (PHP_SAPI !== 'cli') { exit(1); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$root = dirname(__DIR__);
$local = $root . '/.local';
$www = $local . '/www';
$phase = $argv[1] ?? '';
if (!in_array($phase, array('install', 'seed', 'routes', 'check'), true)) {
    fwrite(STDERR, "Expected install, seed, routes or check.\n"); exit(1);
}
$accessFile = $local . '/site-access.json';
$configFile = $www . '/wp-config.php';

if ($phase === 'install') {
    $runtime = json_decode(file_get_contents($local . '/runtime.json'), true, 512, JSON_THROW_ON_ERROR);
    $rootClient = parse_ini_file($runtime['RootClientConfig'], true, INI_SCANNER_RAW)['client'];
    $db = new mysqli('127.0.0.1', 'root', $rootClient['password'], '', 3307);
    if (!file_exists($accessFile)) {
        if (file_exists($configFile) || $db->query("SHOW DATABASES LIKE 'aws_local_store'")->num_rows ||
            $db->query("SELECT User FROM mysql.user WHERE User = 'aws_local_store'")->num_rows) {
            throw new RuntimeException('Existing site/database/account found without local provisioning credentials. No reset performed.');
        }
        $access = array(
            'url' => 'http://127.0.0.1:8080', 'admin_url' => 'http://127.0.0.1:8080/wp-admin/',
            'admin_user' => 'local_manager', 'admin_password' => bin2hex(random_bytes(18)),
            'admin_email' => 'admin@example.test', 'database' => 'aws_local_store',
            'database_user' => 'aws_local_store', 'database_password' => bin2hex(random_bytes(24)),
        );
        file_put_contents($accessFile, json_encode($access, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    } else { $access = json_decode(file_get_contents($accessFile), true, 512, JSON_THROW_ON_ERROR); }
    if ($access['database'] !== 'aws_local_store' || $access['database_user'] !== 'aws_local_store' ||
        !preg_match('/^[a-f0-9]{48}$/', $access['database_password'])) {
        throw new RuntimeException('Unexpected local database credentials; no changes performed.');
    }
    $db->query('CREATE DATABASE IF NOT EXISTS aws_local_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->query("CREATE USER IF NOT EXISTS 'aws_local_store'@'localhost' IDENTIFIED BY '" . $access['database_password'] . "'");
    $db->query("GRANT ALL PRIVILEGES ON aws_local_store.* TO 'aws_local_store'@'localhost'");
    $db->close();
    if (!file_exists($configFile)) {
        $values = array(
            'DB_NAME' => $access['database'], 'DB_USER' => $access['database_user'],
            'DB_PASSWORD' => $access['database_password'], 'DB_HOST' => '127.0.0.1:3307',
            'DB_CHARSET' => 'utf8mb4', 'DB_COLLATE' => '',
            'WP_HOME' => $access['url'], 'WP_SITEURL' => $access['url'],
            'WP_ENVIRONMENT_TYPE' => 'local', 'WP_DEBUG' => true,
            'WP_DEBUG_LOG' => str_replace('\\', '/', $local . '/logs/wordpress-debug.log'),
            'WP_DEBUG_DISPLAY' => false, 'DISALLOW_FILE_EDIT' => true,
            'WP_MEMORY_LIMIT' => '256M', 'WP_MAX_MEMORY_LIMIT' => '256M',
        );
        foreach (array('AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT') as $name) {
            $values[$name] = bin2hex(random_bytes(32));
        }
        $config = "<?php\n";
        foreach ($values as $name => $value) { $config .= 'define(' . var_export($name, true) . ', ' . var_export($value, true) . ");\n"; }
        $config .= "\$table_prefix = 'wp_';\nif (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }\nrequire_once ABSPATH . 'wp-settings.php';\n";
        file_put_contents($configFile, $config, LOCK_EX);
    }
    $muDir = $www . '/wp-content/mu-plugins';
    if (!is_dir($muDir)) { mkdir($muDir, 0777, true); }
    $guardFile = $muDir . '/local-development.php';
    if (!file_exists($guardFile)) {
        file_put_contents($guardFile, <<<'PHP'
<?php
/** Local-only safeguards; this file belongs to the disposable local runtime. */
defined('ABSPATH') || exit;
if (wp_get_environment_type() === 'local') {
    add_filter('pre_wp_mail', '__return_false');
    add_filter('woocommerce_available_payment_gateways', '__return_empty_array');
    add_filter('wp_robots', function ($robots) { $robots['noindex'] = true; $robots['nofollow'] = true; return $robots; });
}
PHP
        );
    }
    define('WP_INSTALLING', true);
}

$_SERVER['HTTP_HOST'] = '127.0.0.1:8080';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8080';
$_SERVER['REQUEST_URI'] = '/';
require_once $www . '/wp-load.php';

if ($phase === 'routes') {
    // Use a fresh request after installing WooCommerce and changing permalinks.
    flush_rewrite_rules(false);
    echo "WordPress and WooCommerce permalink rules refreshed.\n";
    exit;
}

if ($phase === 'install') {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if (!is_blog_installed()) {
        wp_install('Local Development Store', $access['admin_user'], $access['admin_email'], false, '', $access['admin_password'], 'en_US');
    }
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        $result = activate_plugin('woocommerce/woocommerce.php');
        if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
    }
    $catalog = activate_plugin('susmo-catalog/susmo-catalog.php');
    if (is_wp_error($catalog)) { throw new RuntimeException($catalog->get_error_message()); }
    if (get_stylesheet() !== 'local-store') { switch_theme('local-store'); }
    echo "WordPress installed; WooCommerce and project child theme activated.\n";
    exit;
}

if ($phase === 'seed') { require __DIR__ . '/seed-susmo.php'; }
if (!is_blog_installed() || get_stylesheet() !== 'local-store' || !class_exists('WooCommerce') || !function_exists('susmo_catalog_unavailable')) {
    throw new RuntimeException('Catalog installation/active theme/plugin validation failed.');
}
$product = wc_get_product(wc_get_product_id_by_sku('SUSMO-WAVY-BOB'));
if (!$product || $product->get_status() !== 'publish') { throw new RuntimeException('Catalog product missing.'); }
foreach (array('about-us','contact','faq','hair-guide') as $slug) {
    if (!get_page_by_path($slug)) { throw new RuntimeException('Missing company/guide page: ' . $slug); }
}
if (DB_USER === 'root') { throw new RuntimeException('Application must not use database root.'); }
$versions = array('WordPress'=>get_bloginfo('version'), 'WooCommerce'=>WC_VERSION, 'Storefront'=>wp_get_theme('storefront')->get('Version'), 'ChildTheme'=>wp_get_theme()->get('Version'));
file_put_contents($local . '/site-versions.json', json_encode($versions, JSON_PRETTY_PRINT) . "\n");
echo json_encode(array('status'=>'ok', 'versions'=>$versions, 'catalog_mode'=>true, 'database_user_is_root'=>false), JSON_UNESCAPED_SLASHES) . "\n";
