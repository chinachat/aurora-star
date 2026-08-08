<?php
/**
 * 主页/索引模板。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container aurora-star-layout">
	<?php if ( is_home() && ! is_front_page() ) : ?>
		<header class="page-header">
			<h1 class="page-title"><?php single_post_title(); ?></h1>
		</header>
	<?php endif; ?>

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
