<?php
/**
 * 文章条目（单篇，备用）。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-entry' ); ?>>
	<header class="entry-header">
		<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<div class="entry-thumbnail">
			<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'aurora-star-card' ); ?></a>
		</div>
	<?php endif; ?>

	<div class="entry-summary">
		<?php the_excerpt(); ?>
	</div>

	<footer class="entry-meta">
		<i class="fa-regular fa-clock" aria-hidden="true"></i>
		<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
		<a class="entry-more" href="<?php the_permalink(); ?>">
			<?php esc_html_e( '继续阅读', 'aurora-star' ); ?>
			<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
		</a>
	</footer>
</article>
