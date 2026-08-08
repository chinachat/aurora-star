<?php
/**
 * 页头。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="https://gmpg.org/xfn/11">
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( '跳到主要内容', 'aurora-star' ); ?></a>

<header class="site-header" role="banner">
	<div class="site-header__inner container">
		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<div class="site-logo"><?php the_custom_logo(); ?></div>
			<?php else : ?>
				<div class="site-logo site-logo--text">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				</div>
			<?php endif; ?>

			<?php
			$description = get_bloginfo( 'description', 'display' );
			if ( $description || is_customize_preview() ) :
				?>
				<p class="site-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>

		<nav class="site-nav" aria-label="<?php esc_attr_e( '主导航', 'aurora-star' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'menu_id'        => 'primary-menu',
					'container'      => false,
					'walker'         => new Aurora_Walker_Nav_Menu(),
					'fallback_cb'    => 'aurora_star_menu_fallback',
				)
			);
			?>
		</nav>

		<div class="site-header__tools">
			<?php if ( get_theme_mod( 'aurora_star_dark_toggle', true ) ) : ?>
				<button type="button" class="dark-toggle" data-dark-toggle aria-label="<?php esc_attr_e( '切换暗黑模式', 'aurora-star' ); ?>">
					<i class="fa-solid fa-moon" aria-hidden="true"></i>
					<i class="fa-solid fa-sun" aria-hidden="true"></i>
				</button>
			<?php endif; ?>

			<button type="button" class="nav-toggle" data-nav-toggle aria-label="<?php esc_attr_e( '打开菜单', 'aurora-star' ); ?>" aria-expanded="false">
				<i class="fa-solid fa-bars" aria-hidden="true"></i>
			</button>
		</div>
	</div>
</header>

<main id="primary" class="site-main">
