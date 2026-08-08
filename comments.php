<?php
/**
 * 评论模板。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}
?>

<section id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
			<?php
			$comment_count = get_comments_number();
			/* translators: %s: 评论数。 */
			printf( esc_html( _n( '%s 条评论', '%s 条评论', $comment_count, 'aurora-star' ) ), esc_html( number_format_i18n( $comment_count ) ) );
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php
		the_comments_navigation();
		?>

		<?php if ( ! comments_open() ) : ?>
			<p class="no-comments"><?php esc_html_e( '评论已关闭。', 'aurora-star' ); ?></p>
		<?php endif; ?>

	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply'         => __( '发表评论', 'aurora-star' ),
			'label_submit'        => __( '提交评论', 'aurora-star' ),
			'comment_notes_before' => '<p class="comment-notes">' . esc_html__( '你的邮箱不会被公开。', 'aurora-star' ) . '</p>',
		)
	);
	?>
</section>
