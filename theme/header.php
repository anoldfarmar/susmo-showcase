<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<div class="announcement">A little change. A little more you.</div>
<header class="brand-header">
    <div class="header-inner">
        <a class="brand-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="susmo home"><img src="<?php echo esc_url(susmo_asset('susmo-logo.svg')); ?>" alt="susmo" width="584" height="170"></a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation"><span class="menu-lines" aria-hidden="true"></span><span class="menu-label">Menu</span></button>
        <nav id="primary-navigation" class="primary-navigation" aria-label="Main navigation"><?php susmo_nav(); ?></nav>
        <a class="header-contact" href="<?php echo esc_url(home_url('/contact/')); ?>">Let's talk <span aria-hidden="true">↗</span></a>
    </div>
</header>
