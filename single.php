<?php
/**
 * 文章模板。
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

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-single' ); ?>>

			<?php if ( get_theme_mod( 'aurora_star_show_thumbnail', true ) && has_post_thumbnail() ) : ?>
				<div class="post-hero">
					<?php the_post_thumbnail( 'aurora-star-hero', array( 'class' => 'post-hero__img' ) ); ?>
				</div>
			<?php endif; ?>

			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>

				<div class="entry-meta">
					<span class="entry-meta__item">
						<i class="fa-regular fa-user" aria-hidden="true"></i>
						<span><?php the_author_posts_link(); ?></span>
					</span>
					<span class="entry-meta__item">
						<i class="fa-regular fa-clock" aria-hidden="true"></i>
						<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					</span>
					<?php if ( get_the_category_list() ) : ?>
						<span class="entry-meta__item">
							<i class="fa-regular fa-folder" aria-hidden="true"></i>
							<?php echo wp_kses_post( get_the_category_list( '、' ) ); ?>
						</span>
					<?php endif; ?>
					<span class="entry-meta__item">
						<i class="fa-regular fa-comment" aria-hidden="true"></i>
						<?php comments_popup_link( __( '0 评论', 'aurora-star' ), __( '1 评论', 'aurora-star' ), __( '% 评论', 'aurora-star' ) ); ?>
					</span>
				</div>
			</header>

			<?php if ( aurora_star_should_show_toc() ) : ?>
				<?php
				$aurora_star_toc_html = aurora_star_toc( get_the_ID() );
				if ( ! empty( $aurora_star_toc_html ) ) :
					?>
					<?php echo $aurora_star_toc_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<button type="button" class="aurora-star-toc-mobile-btn" data-toc-mobile-btn aria-label="<?php esc_attr_e( '打开文章目录', 'aurora-star' ); ?>">
						<i class="fa-solid fa-list" aria-hidden="true"></i>
					</button>
				<?php endif; ?>
			<?php endif; ?>

			<div class="entry-content">
				<?php
				the_content(
					sprintf(
						/* translators: %s: 文章标题。 */
						__( '继续阅读 %s', 'aurora-star' ),
						the_title( '<span class="screen-reader-text">', '</span>', false )
					)
				);

				wp_link_pages(
					array(
						'before' => '<div class="page-links">' . esc_html__( '分页：', 'aurora-star' ),
						'after'  => '</div>',
					)
				);
				?>
			</div>

			<footer class="entry-footer">
				<?php
				$tags = get_the_tags();
				if ( $tags ) :
					?>
					<div class="entry-tags">
						<i class="fa-solid fa-tags" aria-hidden="true"></i>
						<?php
						foreach ( $tags as $tag ) {
							echo '<a class="tag" href="' . esc_url( get_tag_link( $tag ) ) . '">#' . esc_html( $tag->name ) . '</a>';
						}
						?>
					</div>
				<?php endif; ?>

				<?php the_post_navigation(); ?>
			</footer>
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
