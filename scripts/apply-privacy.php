<?php
/** Apply the repository's policy without touching any customer or session data. */
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
if (!defined('ABSPATH')) { require $root . '/.local/www/wp-load.php'; }
$source = $root . '/content/privacy-policy.html';
$content = file_get_contents($source);
if (!$content || !str_contains($content, 'privacy-contact')) { throw new RuntimeException('Privacy policy source is missing or incomplete.'); }
$page = get_page_by_path('privacy');
if ($page && $page->post_content !== $content) {
    $backupDir = $root . '/.local/backups';
    if (!is_dir($backupDir)) { mkdir($backupDir, 0777, true); }
    $backupFile = $backupDir . '/privacy-before-' . hash('sha256', $page->post_content) . '.json';
    if (!file_exists($backupFile)) {
        file_put_contents($backupFile, wp_json_encode(array('ID'=>$page->ID,'post_title'=>$page->post_title,'post_content'=>$page->post_content,'post_status'=>$page->post_status),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
$post = array('post_type'=>'page','post_name'=>'privacy','post_title'=>'Privacy Policy','post_content'=>$content,'post_status'=>'publish','comment_status'=>'closed','ping_status'=>'closed');
if ($page) { $post['ID'] = $page->ID; }
$id = wp_insert_post($post, true);
if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
update_option('wp_page_for_privacy_policy', $id);
update_option('susmo_privacy_source_sha256', hash('sha256', $content), false);
echo "susmo Privacy Policy applied: /privacy/; previous content backed up when changed.\n";
