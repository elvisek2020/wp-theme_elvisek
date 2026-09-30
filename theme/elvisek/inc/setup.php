<?php
/**
 * Základní nastavení šablony a úklid výstupu WordPressu.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'elvisek', EK_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 80, 'flex-width' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );

	add_editor_style( 'assets/css/editor.css' );

	register_nav_menus( array(
		'primary' => __( 'Hlavní menu', 'elvisek' ),
		'footer'  => __( 'Odkazy v patičce', 'elvisek' ),
	) );

	// Náhledy: karta 16:9, hlavní článek, úvodní obrázek článku.
	add_image_size( 'ek-card', 640, 360, true );
	add_image_size( 'ek-featured', 1100, 720, true );
	add_image_size( 'ek-hero', 1520, 640, true );
} );

// Po přepnutí na šablonu převezme menu „MENU“ z Graphene, pokud není nic přiřazeno.
add_action( 'after_switch_theme', function () {
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( empty( $locations['primary'] ) ) {
		$menu = wp_get_nav_menu_object( 'MENU' );
		if ( $menu ) {
			$locations['primary'] = $menu->term_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
	}
} );

// Obsahová šířka pro embedy.
add_action( 'after_setup_theme', function () {
	$GLOBALS['content_width'] = 760;
}, 0 );

// Úklid <head>: emoji, generator, RSD, shortlink, oEmbed discovery.
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
} );
add_filter( 'emoji_svg_url', '__return_false' );

// CSS bloků jen pro bloky, které stránka opravdu používá.
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

// Délka a konec perexu.
add_filter( 'excerpt_length', fn() => 45 );
add_filter( 'excerpt_more', fn() => '…' );

// Výpis článků: 7 na stránku (1 hlavní + 6 karet), archivy 9.
add_action( 'pre_get_posts', function ( WP_Query $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->is_archive() || $q->is_search() ) {
		$q->set( 'posts_per_page', 9 );
	}
	// Titulka: 1 hlavní článek + N karet, další strany po N kartách.
	if ( $q->is_home() ) {
		$n     = ek_home_posts();
		$paged = max( 1, (int) $q->get( 'paged' ) );
		if ( 1 === $paged ) {
			$q->set( 'posts_per_page', $n + 1 );
		} else {
			$q->set( 'posts_per_page', $n );
			$q->set( 'offset', 1 + ( $paged - 1 ) * $n );
		}
	}
} );

function ek_home_posts(): int {
	return max( 3, min( 48, (int) ek_opt( 'ek_home_posts' ) ?: 6 ) );
}

// Stránkování titulky: počet stran = 1 + zbytek po N (offset ruší výpočet WP).
add_filter( 'found_posts', function ( $found, WP_Query $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_home() ) {
		return $found;
	}
	$n     = ek_home_posts();
	$pages = max( 1, (int) ceil( max( 0, $found - 1 ) / $n ) );
	return $pages * (int) $q->get( 'posts_per_page' );
}, 10, 2 );

// Třída pro <body>, když má článek obsah (TOC) — nastavuje se v content.php.
add_filter( 'body_class', function ( $classes ) {
	if ( is_singular() && has_post_thumbnail() ) {
		$classes[] = 'has-hero';
	}
	return $classes;
} );

// Archiv bez prefixu „Rubrika:“.
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

// Upozornění v administraci, když chybí plugin ElvisEK Core (login, hardening, aktualizace…).
add_action( 'admin_notices', function () {
	if ( function_exists( 'ek_client_ip' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p><strong>Šablona ElvisEK:</strong> není aktivní plugin <em>ElvisEK Core</em> (ochrana přihlášení, zabezpečení, aktualizace). <a href="%s">Pluginy</a></p></div>',
		esc_url( admin_url( 'plugins.php' ) )
	);
} );
