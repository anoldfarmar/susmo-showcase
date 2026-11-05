<?php defined('ABSPATH') || exit; ?>
<footer class="brand-footer">
    <div class="footer-top wrap">
        <div class="footer-brand"><a href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url(susmo_asset('susmo-logo.svg')); ?>" alt="susmo" width="584" height="170" loading="lazy"></a><p>A fresh look.<br>A feeling that's yours.</p></div>
        <div><h2>Explore</h2><a href="<?php echo esc_url(home_url('/shop/')); ?>">Wig Collection</a><a href="<?php echo esc_url(home_url('/hair-guide/')); ?>">Hair Guide</a><a href="<?php echo esc_url(home_url('/faq/')); ?>">FAQs</a></div>
        <div><h2>Our company</h2><a href="<?php echo esc_url(home_url('/about-us/')); ?>">About Us</a><a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact Us</a><a href="<?php echo esc_url(home_url('/privacy/')); ?>">Privacy Policy</a></div>
        <div class="footer-contact"><h2>Say hello</h2><a href="<?php echo esc_url('mailto:' . susmo_company('email')); ?>"><?php echo esc_html(susmo_company('email')); ?> <span aria-hidden="true">↗</span></a><p><?php echo esc_html(susmo_company('name')); ?></p><p><?php echo esc_html(susmo_company('address')); ?></p></div>
    </div>
    <div class="footer-bottom wrap"><span>© <?php echo esc_html(wp_date('Y')); ?> susmo. All rights reserved.</span><span>Wigs &amp; a world of possibility.</span><a class="footer-privacy" href="<?php echo esc_url(home_url('/privacy/')); ?>">Privacy Policy</a><a class="back-top" href="#top">Back to top ↑</a></div>
</footer>
<?php wp_footer(); ?>
</body></html>
