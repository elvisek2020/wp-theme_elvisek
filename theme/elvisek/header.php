<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="ek-skip" href="#obsah">Přeskočit na obsah</a>

<header class="ek-header">
	<div class="ek-header__inner ek-wrap">
		<a class="ek-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> – úvod">
			<?php if ( has_custom_logo() ) : ?>
				<?php echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'thumbnail', false, array( 'class' => 'ek-logo__img', 'alt' => '' ) ); ?>
			<?php else : ?>
				<span class="ek-logo__mark" aria-hidden="true">EK</span>
			<?php endif; ?>
			<span class="ek-logo__name"><?php bloginfo( 'name' ); ?></span>
		</a>

		<button type="button" class="ek-iconbtn ek-nav-toggle" aria-expanded="false" aria-controls="ek-nav" aria-label="Menu">
			<?php echo ek_icon( 'menu' ); ?>
		</button>

		<nav id="ek-nav" class="ek-nav" aria-label="Hlavní menu">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'ek-nav__list',
				'depth'          => 2,
				'fallback_cb'    => 'ek_nav_fallback',
			) );
			?>
		</nav>

		<div class="ek-header__tools">
			<button type="button" class="ek-iconbtn" data-ek-search-toggle aria-expanded="false" aria-controls="ek-search" aria-label="Hledat">
				<?php echo ek_icon( 'search' ); ?>
			</button>
			<button type="button" class="ek-iconbtn" data-ek-theme-toggle aria-label="Přepnout světlý/tmavý režim">
				<span class="ek-when-light"><?php echo ek_icon( 'moon' ); ?></span>
				<span class="ek-when-dark"><?php echo ek_icon( 'sun' ); ?></span>
			</button>
		</div>
	</div>
	<div id="ek-search" class="ek-searchbar" hidden>
		<div class="ek-wrap"><?php get_search_form(); ?></div>
	</div>
</header>

