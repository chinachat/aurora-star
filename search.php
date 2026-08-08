<?php
/**
 * 搜索结果模板。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container aurora-star-layout">
	<header class="page-header">
		<h1 class="page-title">
			<?php
			/* translators: %s: 搜索关键词。 */
			printf( esc_html__( '搜索：%s', 'aurora-star' ), '<span>' . get_search_query() . '</span>' );
			?>
		</h1>
	</header>

	<?php if ( have_posts() ) : ?>

		<div class="post-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'card' );
			endwhile;
			?>
		</div>

		<?php
		the_posts_pagination(
			array(
				'prev_text' => '<i class="fa-solid fa-chevron-left" aria-hidden="true"></i> ' . __( '上一页', 'aurora-star' ),
				'next_text' => __( '下一页', 'aurora-star' ) . ' <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>',
				'class'     => 'aurora-star-pagination',
			)
		);
		?>

	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>
</div>

<?php
get_footer();
