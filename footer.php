<?php
/**
 * 页脚。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main>

<footer class="site-footer">
	<div class="container">
		<?php
		if ( has_nav_menu( 'footer' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'menu_class'     => 'footer-nav',
					'container'      => false,
					'walker'         => new Aurora_Walker_Nav_Menu(),
					'depth'          => 1,
				)
			);
		}
		?>

		<div class="site-footer__copyright">
			<?php echo wp_kses_post( aurora_star_footer_copyright() ); ?>
			<span class="site-footer__sep">·</span>
			<span class="site-footer__theme"><?php esc_html_e( '由 Aurora Star 极光主题驱动', 'aurora-star' ); ?></span>
		</div>
	</div>
</footer>

<div class="mobile-menu-overlay" data-nav-overlay></div>

<div class="floating-nav" data-floating-nav>
	<button type="button" class="floating-nav__btn" data-share-btn aria-label="<?php esc_attr_e( '分享本页', 'aurora-star' ); ?>" title="<?php esc_attr_e( '分享本页', 'aurora-star' ); ?>">
		<i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
	</button>
	<button type="button" class="floating-nav__btn" data-back-top aria-label="<?php esc_attr_e( '返回顶部', 'aurora-star' ); ?>" title="<?php esc_attr_e( '返回顶部', 'aurora-star' ); ?>">
		<i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
	</button>
</div>

<div class="share-popover" data-share-popover aria-hidden="true">
	<div class="share-popover__head">
		<span><?php esc_html_e( '分享到', 'aurora-star' ); ?></span>
		<button type="button" class="share-popover__close" data-share-close aria-label="<?php esc_attr_e( '关闭', 'aurora-star' ); ?>">
			<i class="fa-solid fa-xmark" aria-hidden="true"></i>
		</button>
	</div>
	<div class="share-popover__body">
		<a class="share-item" data-share-wechat href="#" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( '分享到微信', 'aurora-star' ); ?>">
			<span class="share-item__icon share-item__icon--wechat"><i class="fa-brands fa-weixin" aria-hidden="true"></i></span>
			<span class="share-item__label"><?php esc_html_e( '微信', 'aurora-star' ); ?></span>
		</a>
		<a class="share-item" data-share-weibo href="#" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( '分享到微博', 'aurora-star' ); ?>">
			<span class="share-item__icon share-item__icon--weibo"><i class="fa-brands fa-weibo" aria-hidden="true"></i></span>
			<span class="share-item__label"><?php esc_html_e( '微博', 'aurora-star' ); ?></span>
		</a>
		<a class="share-item" data-share-qq href="#" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( '分享到 QQ', 'aurora-star' ); ?>">
			<span class="share-item__icon share-item__icon--qq"><i class="fa-brands fa-qq" aria-hidden="true"></i></span>
			<span class="share-item__label"><?php esc_html_e( 'QQ', 'aurora-star' ); ?></span>
		</a>
		<button type="button" class="share-item share-item--btn" data-share-copy aria-label="<?php esc_attr_e( '复制链接', 'aurora-star' ); ?>">
			<span class="share-item__icon share-item__icon--copy"><i class="fa-solid fa-link" aria-hidden="true"></i></span>
			<span class="share-item__label"><?php esc_html_e( '复制链接', 'aurora-star' ); ?></span>
		</button>
	</div>
</div>

<div class="share-toast" data-share-toast role="status"><?php esc_html_e( '链接已复制', 'aurora-star' ); ?></div>

<?php wp_footer(); ?>
</body>
</html>
