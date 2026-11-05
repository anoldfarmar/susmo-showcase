<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main" class="content-page"><div id="top"></div><?php while (have_posts()) { the_post(); the_content(); } ?></main>
<?php get_footer(); ?>
