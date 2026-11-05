<?php
/** CLI-only, repeatable brand/content migration. Never deletes existing data. */
if (PHP_SAPI !== 'cli') { exit(1); }
$project = dirname(__DIR__);
if (!defined('ABSPATH')) { require $project . '/.local/www/wp-load.php'; }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$catalog = activate_plugin('susmo-catalog/susmo-catalog.php');
if (is_wp_error($catalog)) { throw new RuntimeException($catalog->get_error_message()); }
if (get_option('susmo_brand_content_version') === '1') {
    echo "susmo content already applied; administrator edits preserved.\n";
    return;
}

// Keep a local rollback snapshot before changing the earlier placeholders.
$backupDir = $project . '/.local/backups';
if (!is_dir($backupDir)) { mkdir($backupDir, 0777, true); }
$backupFile = $backupDir . '/before-susmo-content.json';
if (!file_exists($backupFile)) {
    $pages = get_posts(array('post_type'=>'page','post_status'=>'any','numberposts'=>-1));
    file_put_contents($backupFile, wp_json_encode(array('pages'=>$pages,'blogname'=>get_option('blogname'),'products'=>array_map(fn($p)=>array('id'=>$p->get_id(),'status'=>$p->get_status()),wc_get_products(array('limit'=>-1,'status'=>array('publish','draft'))))), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
}
$company = array(
    'brand'=>'susmo',
    'name'=>'Shenzhen Dunlin E-Commerce Co., Ltd.',
    'name_zh'=>'深圳市敦林电子商务有限责任公司',
    'address'=>"Room 6B302B, Unit B, Building 6, Xuehao Ge, Shui'an Mingzhu, No. 3003 Pingshan Avenue, Liulian Community, Pingshan Subdistrict, Pingshan District, Shenzhen, China",
    'address_zh'=>'深圳市坪山区坪山街道六联社区坪山大道3003号水岸明珠学豪阁6栋B单元6B302B',
    'email'=>'2813039017@qq.com',
);
update_option('susmo_company', $company, false);
update_option('blogname', 'susmo');
update_option('blogdescription', 'Wigs, with a personal touch.');
update_option('users_can_register', '0');
update_option('default_comment_status', 'closed');
update_option('default_ping_status', 'closed');
update_option('woocommerce_enable_reviews', 'no');
update_option('woocommerce_allow_tracking', 'no');
update_option('woocommerce_feature_order_attribution_enabled', 'no');

function susmo_seed_page($slug, $title, $content, $oldSlug = '') {
    $page = get_page_by_path($slug);
    if (!$page && $oldSlug) { $page = get_page_by_path($oldSlug); }
    $data = array('post_name'=>$slug,'post_title'=>$title,'post_content'=>$content,'post_status'=>'publish','post_type'=>'page','comment_status'=>'closed','ping_status'=>'closed');
    if ($page) { $data['ID'] = $page->ID; }
    $id = wp_insert_post($data, true);
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    return $id;
}
$image = esc_url(get_stylesheet_directory_uri() . '/assets/images/wig-front.webp');
$email = esc_html($company['email']);
$name = esc_html($company['name']);
$address = esc_html($company['address']);
$homeId = susmo_seed_page('home', 'Home', '<p>Explore the susmo wig collection and get to know our company in Shenzhen, China.</p>');
$about = <<<HTML
<section class="page-heading wrap"><p class="eyebrow">ABOUT SUSMO</p><h1>A new look.<br>A story that's <em>yours.</em></h1><p>Wigs, a little inspiration and the freedom to explore a different version of your style. Welcome to susmo.</p></section>
<section class="page-split"><div class="page-split-image"><img src="$image" alt="Rooted blonde wavy bob hairstyle" width="1100" height="1204" loading="lazy"></div><div class="page-split-copy"><p class="eyebrow">OUR POINT OF VIEW</p><h2>Still you.<br>Just a little <em>different.</em></h2><p>A new hairstyle can be a fresh starting point. A softer shape, a different shade or simply a look you have always wanted to try.</p><p>At susmo, we believe exploring wigs should begin with a clear view of the details. Our collection lets you see a style from different angles, learn about its appearance and ask questions before making a choice.</p><p>We keep the focus on the wigs—and the people who make them their own.</p><a class="text-link" href="/shop/">Meet the collection ↗</a></div></section>
<section class="section wrap company-grid"><div><p class="eyebrow">THE COMPANY BEHIND THE BRAND</p><h2>From Shenzhen,<br>with <em>possibility.</em></h2><p>susmo is a wig brand operated by $name, based in Shenzhen, China.</p><p>This website introduces our products and company. For details about a style, its materials, fit or available options, you can contact our team directly.</p><a class="text-link" href="/contact/">Get in touch ↗</a></div><dl class="company-details"><div><dt>Company</dt><dd>$name</dd></div><div><dt>Based in</dt><dd>Shenzhen, China</dd></div><div><dt>Company address</dt><dd>$address</dd></div><div><dt>Email</dt><dd><a href="mailto:$email">$email</a></dd></div></dl></section>
HTML;
$about = preg_replace('~<dl class="company-details">.*?</dl>~s', '[susmo_company_details]', $about);
susmo_seed_page('about-us', 'About Us', $about, 'about');
$contact = <<<HTML
<section class="page-heading wrap"><p class="eyebrow">CONTACT US</p><h1>Good things begin<br>with a <em>hello.</em></h1><p>A question about a wig, a detail you would like to confirm, or simply want to know more about susmo? Get in touch with our team.</p></section>
<section class="wrap contact-layout"><div><div class="contact-email-card"><p class="eyebrow">EMAIL OUR TEAM</p><h2>Let's talk about your next look.</h2><a href="mailto:$email">$email ↗</a><p>Include the style name and the product details you would like to know. The email link opens your own email application.</p></div><p class="contact-note">Contact is by email. No account or on-site contact form is required.</p></div><dl class="company-details"><div><dt>Brand</dt><dd>susmo</dd></div><div><dt>Company</dt><dd>$name</dd></div><div><dt>Address</dt><dd>$address</dd></div><div><dt>Location</dt><dd>Shenzhen, China</dd></div></dl></section>
HTML;
$contact = preg_replace('~<dl class="company-details">.*?</dl>~s', '[susmo_company_details]', $contact);
susmo_seed_page('contact', 'Contact Us', $contact);
$guide = <<<'HTML'
<section class="page-heading wrap"><p class="eyebrow">THE SUSMO HAIR GUIDE</p><h1>A little knowledge.<br>A more confident <em>choice.</em></h1><p>A starting point for exploring wigs, understanding the details and caring for the style you choose.</p></section>
<div class="wrap guide-content"><section><p class="eyebrow">01 / SHAPE &amp; STYLE</p><h2>Start with a silhouette.</h2><p>Consider the overall length, texture and shape you enjoy. Look at both the front and side views to see how a style frames the face. Product photographs are a useful visual guide; exact measurements should be confirmed for the individual wig.</p></section><section><p class="eyebrow">02 / COLOR &amp; DETAILS</p><h2>Take a closer look.</h2><p>A shade can look different in daylight, indoor lighting and on different screens. Root color, highlights and the direction of a wave all contribute to the overall appearance.</p><p>Check the available images and ask our team to confirm the material, shade options and cap details for the style you are considering.</p></section><section><p class="eyebrow">03 / FIT &amp; CONSTRUCTION</p><h2>Get to know the fit.</h2><p>Cap size and construction vary by product. Confirm the relevant measurements and fitting instructions before choosing a wig. The construction images in our gallery show visible details; they do not replace the product's confirmed specifications.</p></section><section><p class="eyebrow">04 / CARE &amp; STYLING</p><h2>A little care goes a long way.</h2><p>Follow the instructions supplied for your specific wig. Washing, brushing, heat styling and coloring suitability depend on the fiber and construction.</p><ul><li>Confirm the fiber type and care instructions before washing or applying products.</li><li>Do not assume a wig is heat-safe. Confirm permitted tools and temperatures first.</li><li>Handle the cap and fibers gently, and use the recommended storage method.</li></ul></section><section><h2>Still have a question?</h2><p>Our team can help confirm the details for a particular style.</p><p><a class="text-link" href="/contact/">Contact susmo ↗</a></p></section></div>
HTML;
susmo_seed_page('hair-guide', 'Hair Guide', $guide);
$faq = <<<'HTML'
<section class="page-heading wrap"><p class="eyebrow">FREQUENTLY ASKED QUESTIONS</p><h1>A little clarity,<br>before your <em>next look.</em></h1><p>Get to know susmo, explore our product information and find out how to contact our team.</p></section><section class="wrap faq-page"><div class="faq-list"><details open><summary>What is susmo?</summary><p>susmo is a wig brand operated by Shenzhen Dunlin E-Commerce Co., Ltd., based in Shenzhen, China. Our website introduces our wig collection and company.</p></details><details><summary>What information can I find about a wig?</summary><p>Product pages include a description and photographs from different angles. Contact our team to confirm materials, cap measurements and any available options that are not listed.</p></details><details><summary>Will a color look exactly the same as the photographs?</summary><p>Screen settings and lighting can affect how a shade appears. Use the photographs as a guide and ask our team about the specific shade before making a decision.</p></details><details><summary>Can I wash, color or heat-style a wig?</summary><p>This depends on the individual product's material and construction. Confirm the product-specific care instructions first. Do not assume that all wigs can be treated in the same way.</p></details><details><summary>Can I place an order or pay on this website?</summary><p>This is a product and company catalog. There is no online checkout. For information about a product, please email our team directly.</p></details><details><summary>Do I need an account to browse?</summary><p>No. You can explore the collection and company pages without an account. This website does not offer customer registration, a shopping cart, comments or contact forms.</p></details><details><summary>How can I get in touch?</summary><p>Email <a href="mailto:2813039017@qq.com">2813039017@qq.com</a> or visit our Contact Us page for company information. We do not ask you to submit personal information through this website.</p></details></div><a class="button" href="/contact/">Contact our team ↗</a></section>
HTML;
susmo_seed_page('faq', 'FAQ', $faq);
require __DIR__ . '/apply-privacy.php';
foreach (array('shipping','returns','terms','sample-page','cart','checkout','my-account') as $slug) {
    $page = get_page_by_path($slug);
    if ($page) { wp_update_post(array('ID'=>$page->ID,'post_status'=>'draft')); }
}
foreach (array('DEMO-COLOR','DEMO-BASIC') as $sku) {
    $id = wc_get_product_id_by_sku($sku);
    if ($id) { wp_update_post(array('ID'=>$id,'post_status'=>'draft')); }
}

$attachmentIds = array();
$assetLabels = array('wig-front.webp'=>'Front view of a rooted blonde wavy bob wig', 'wig-side.webp'=>'Side view of the wavy bob wig', 'wig-cap.webp'=>'Wavy bob wig cap construction', 'wig-detail.webp'=>'Close-up detail of the wig construction');
foreach ($assetLabels as $file=>$alt) {
    $existing = get_posts(array('post_type'=>'attachment','post_status'=>'inherit','numberposts'=>1,'meta_key'=>'susmo_asset_filename','meta_value'=>$file));
    if ($existing) { $id = $existing[0]->ID; }
    else {
        $upload = wp_upload_bits($file, null, file_get_contents($project . '/theme/assets/images/' . $file));
        if (!empty($upload['error'])) { throw new RuntimeException($upload['error']); }
        $id = wp_insert_attachment(array('post_title'=>$alt,'post_mime_type'=>'image/webp','post_status'=>'inherit'),$upload['file']);
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
        update_post_meta($id,'susmo_asset_filename',$file);
    }
    update_post_meta($id,'_wp_attachment_image_alt',$alt);
    $attachmentIds[] = $id;
}
$id = wc_get_product_id_by_sku('SUSMO-WAVY-BOB');
$product = $id ? wc_get_product($id) : new WC_Product_Simple();
$product->set_name('Rooted Blonde Wavy Bob Wig');
$product->set_slug('rooted-blonde-wavy-bob');
$product->set_sku('SUSMO-WAVY-BOB');
$product->set_status('publish');
$product->set_regular_price('');
$product->set_sale_price('');
$product->set_manage_stock(false);
$product->set_reviews_allowed(false);
$product->set_short_description('Soft waves, a gently layered silhouette and blonde lengths with darker roots. A different way to see your everyday style.');
$product->set_description('Explore the style from different angles in the gallery, including the front, side and cap construction. The photographs show the featured look. For confirmed fiber type, exact measurements and available options, please contact the susmo team.');
$product->set_image_id($attachmentIds[0]);
$product->set_gallery_image_ids(array_slice($attachmentIds,1));
$attributes = array();
foreach (array('Style'=>'Wavy bob','Color shown'=>'Rooted blonde') as $label=>$value) {
    $attribute = new WC_Product_Attribute(); $attribute->set_name($label); $attribute->set_options(array($value)); $attribute->set_visible(true); $attribute->set_variation(false); $attributes[] = $attribute;
}
$product->set_attributes($attributes);
$term = term_exists('Wigs','product_cat');
if (!$term) { $term = wp_insert_term('Wigs','product_cat'); }
if (!is_wp_error($term)) { $product->set_category_ids(array((int) (is_array($term) ? $term['term_id'] : $term))); }
$product->save();
update_option('show_on_front','page'); update_option('page_on_front',$homeId);
update_option('susmo_brand_content_version','1',false);
flush_rewrite_rules(false);
echo "susmo English company pages, product gallery and catalog settings applied. Existing customer/session records were not deleted.\n";
