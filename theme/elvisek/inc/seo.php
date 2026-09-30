<?php
/**
 * Základní SEO bez pluginu: meta description, Open Graph, Twitter karta, JSON-LD.
 */

defined( 'ABSPATH' ) || exit;

function ek_plain_title(): string {
	return trim( html_entity_decode( wp_strip_all_tags( single_post_title( '', false ) ), ENT_QUOTES, 'UTF-8' ) );
}

function ek_meta_description(): string {
	if ( is_singular() ) {
		$post = get_queried_object();
		$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		$text = wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $text ) ) );
	} elseif ( is_category() || is_tag() ) {
		$text = wp_strip_all_tags( term_description() ) ?: sprintf( 'Články v kategorii %s na webu %s.', single_term_title( '', false ), get_bloginfo( 'name' ) );
	} else {
		$text = (string) ek_opt( 'ek_meta_desc' );
	}
	$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
	return mb_strlen( $text ) > 160 ? rtrim( mb_substr( $text, 0, 157 ) ) . '…' : $text;
}

add_action( 'wp_head', function () {
	$desc  = ek_meta_description();
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
	$image = '';

	if ( is_singular() && has_post_thumbnail() ) {
		$image = get_the_post_thumbnail_url( null, 'ek-hero' );
	} elseif ( $hero = (int) ek_opt( 'ek_hero_image' ) ) {
		$image = wp_get_attachment_image_url( $hero, 'ek-hero' );
	}

	if ( $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	}

	$og = array(
		'og:locale'      => 'cs_CZ',
		'og:site_name'   => get_bloginfo( 'name' ),
		'og:type'        => is_single() ? 'article' : 'website',
		'og:title'       => is_singular() ? ek_plain_title() : html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ),
		'og:description' => $desc,
		'og:url'         => $url,
		'og:image'       => $image,
	);
	foreach ( array_filter( $og ) as $prop => $content ) {
		printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $prop ), esc_attr( $content ) );
	}
	if ( is_single() ) {
		printf( '<meta property="article:published_time" content="%s">' . "\n", esc_attr( get_the_date( 'c' ) ) );
		printf( '<meta property="article:modified_time" content="%s">' . "\n", esc_attr( get_the_modified_date( 'c' ) ) );
	}
	printf( '<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary' );

	// JSON-LD
	$schema = array(
		'@context' => 'https://schema.org',
		'@type'    => 'WebSite',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		),
	);
	if ( is_single() ) {
		$schema = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'BlogPosting',
			'headline'         => ek_plain_title(),
			'description'      => $desc,
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'mainEntityOfPage' => get_permalink(),
			'author'           => array( '@type' => 'Person', 'name' => 'ElvisEK', 'url' => home_url( '/' ) ),
			'publisher'        => array( '@type' => 'Person', 'name' => 'ElvisEK' ),
			'inLanguage'       => 'cs-CZ',
		);
		if ( $image ) {
			$schema['image'] = $image;
		}
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 5 );

// Kratší <title> oddělovač.
add_filter( 'document_title_separator', fn() => '–' );
