<?php
/**
 * 归档模板（分类/标签/日期/作者）。
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
		<?php
		the_archive_title( '<h1 class="page-title">', '</h1>' );
		the_archive_description( '<div class="archive-description">', '</div>' );
		?>
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
