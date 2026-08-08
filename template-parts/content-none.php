<?php
/**
 * 无内容提示。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<section class="no-results not-found">
	<div class="no-results__icon">
		<i class="fa-regular fa-folder-open" aria-hidden="true"></i>
	</div>
	<h2 class="no-results__title">
		<?php
		if ( is_search() ) {
			esc_html_e( '没有找到相关内容', 'aurora-star' );
		} else {
			esc_html_e( '这里还没有内容', 'aurora-star' );
		}
		?>
	</h2>
	<p class="no-results__desc">
		<?php
		if ( is_search() ) {
			esc_html_e( '请尝试更换关键词，或使用下方搜索。', 'aurora-star' );
		} else {
			esc_html_e( '稍后再来看看吧。', 'aurora-star' );
		}
		?>
	</p>
	<?php get_search_form(); ?>
</section>
