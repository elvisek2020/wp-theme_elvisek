<?php
/**
 * Projektové nastavení šablony: logo, menu, velikosti obrázků, perex, ikony pro iOS.
 * Obecné věci (title-tag, náhledy, html5, úklid <head>, upozornění na plugin) dělá kanon (inc/ekt/common.php).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'elvisek', EKT_DIR . '/languages' );

	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 80, 'flex-width' => true ) );

	add_editor_style( 'assets/css/editor.css' );

	register_nav_menus( array(
		'primary' => 'Hlavní menu',
		'footer'  => 'Odkazy v patičce',
	) );

	// Náhledy: karta 16:9, hlavní článek, úvodní obrázek článku.
	add_image_size( 'ek-card', 640, 360, true );
	add_image_size( 'ek-featured', 1100, 720, true );
	add_image_size( 'ek-hero', 1520, 640, true );
} );

// Obsahová šířka pro embedy.
$GLOBALS['content_width'] = 760;

// Délka a konec perexu.
add_filter( 'excerpt_length', fn() => 45 );
add_filter( 'excerpt_more', fn() => '…' );

// Třída pro <body>, když má článek úvodní obrázek.
add_filter( 'body_class', function ( $classes ) {
	if ( is_singular() && has_post_thumbnail() ) {
		$classes[] = 'has-hero';
	}
	return $classes;
} );

// Archiv bez prefixu „Rubrika:“.
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

// Ikona pro iOS vždy jako PNG z šablony (ikona webu se jinak ukládá jako WebP, to Apple nemusí vzít).
add_filter( 'site_icon_meta_tags', function ( $tags ) {
	$tags   = array_filter( (array) $tags, fn( $t ) => ! str_contains( $t, 'apple-touch-icon' ) );
	$tags[] = sprintf( '<link rel="apple-touch-icon" href="%s" />', esc_url( EKT_URI . '/assets/icons/apple-touch-icon.png' ) );
	return $tags;
} );
add_action( 'wp_head', function () {
	if ( ! has_site_icon() ) {
		printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( EKT_URI . '/assets/icons/apple-touch-icon.png' ) );
	}
}, 99 );
