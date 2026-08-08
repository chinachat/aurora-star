<?php
/**
 * 404 模板。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container aurora-star-layout">
	<section class="error-404 not-found">
		<div class="error-404__inner">
			<div class="error-404__code">404</div>
			<h1 class="error-404__title"><?php esc_html_e( '页面不存在', 'aurora-star' ); ?></h1>
			<p class="error-404__desc"><?php esc_html_e( '你访问的页面可能已被移除或链接有误。', 'aurora-star' ); ?></p>

			<div class="error-404__actions">
				<a class="aurora-star-btn aurora-star-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<i class="fa-solid fa-house" aria-hidden="true"></i>
					<span><?php esc_html_e( '返回首页', 'aurora-star' ); ?></span>
				</a>
				<button type="button" class="aurora-star-btn aurora-star-btn-ghost" onclick="history.back();">
					<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
					<span><?php esc_html_e( '返回上一页', 'aurora-star' ); ?></span>
				</button>
			</div>

			<?php get_search_form(); ?>
		</div>
	</section>
</div>

<?php
get_footer();
