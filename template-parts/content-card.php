<?php
/**
 * 卡片式文章条目（首页/归档列表）。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
	<a class="post-card__link" href="<?php the_permalink(); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="post-card__media">
				<?php the_post_thumbnail( 'aurora-star-card', array( 'class' => 'post-card__img' ) ); ?>
			</div>
		<?php else : ?>
			<div class="post-card__media post-card__media--placeholder">
				<i class="fa-regular fa-image" aria-hidden="true"></i>
			</div>
		<?php endif; ?>

		<div class="post-card__body">
			<?php
			$cats = get_the_category();
			if ( ! empty( $cats ) ) :
				?>
				<div class="post-card__cats">
					<?php
					foreach ( array_slice( $cats, 0, 2 ) as $cat ) {
						echo '<span class="post-card__cat">' . esc_html( $cat->name ) . '</span>';
					}
					?>
				</div>
			<?php endif; ?>

			<h2 class="post-card__title"><?php the_title(); ?></h2>

			<div class="post-card__excerpt"><?php the_excerpt(); ?></div>

			<div class="post-card__meta">
				<span class="post-card__meta-item">
					<i class="fa-regular fa-clock" aria-hidden="true"></i>
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				</span>
				<span class="post-card__meta-item">
					<i class="fa-regular fa-eye" aria-hidden="true"></i>
					<?php
					$views = get_post_meta( get_the_ID(), 'aurora_star_views', true );
					echo esc_html( $views ? $views : 0 );
					?>
				</span>
				<span class="post-card__meta-item post-card__meta-more">
					<?php esc_html_e( '阅读全文', 'aurora-star' ); ?>
					<i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
				</span>
			</div>
		</div>
	</a>
</article>
