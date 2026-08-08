<?php
/**
 * 页面模板。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container aurora-star-layout">
	<?php
	while ( have_posts() ) :
		the_post();
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'page-single' ); ?>>

			<?php if ( get_theme_mod( 'aurora_star_show_thumbnail', true ) && has_post_thumbnail() ) : ?>
				<div class="post-hero">
					<?php the_post_thumbnail( 'aurora-star-hero', array( 'class' => 'post-hero__img' ) ); ?>
				</div>
			<?php endif; ?>

			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header>

			<?php if ( aurora_star_should_show_toc() && is_page() ) : ?>
				<?php
				$aurora_star_toc_html = aurora_star_toc( get_the_ID() );
				if ( ! empty( $aurora_star_toc_html ) ) {
					echo $aurora_star_toc_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			<?php endif; ?>

			<div class="entry-content">
				<?php
				the_content();

				wp_link_pages(
					array(
						'before' => '<div class="page-links">' . esc_html__( '分页：', 'aurora-star' ),
						'after'  => '</div>',
					)
				);
				?>
			</div>
		</article>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>

	<?php endwhile; ?>
</div>

<?php
get_footer();
