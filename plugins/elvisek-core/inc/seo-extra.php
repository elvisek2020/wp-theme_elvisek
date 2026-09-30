<?php
/**
 * Bezpečnostní hlavičky, úpravy sitemap a /llms.txt.
 */

defined( 'ABSPATH' ) || exit;

/* Bezpečnostní hlavičky pro stránky generované WordPressem (web i admin). */
add_action( 'send_headers', function () {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()' );
	if ( is_ssl() && 'local' !== wp_get_environment_type() ) {
		header( 'Strict-Transport-Security: max-age=31536000' ); // bez includeSubDomains – subdomény můžou jet i bez HTTPS
	}
} );

/* Sitemapa: bez seznamu uživatelů (prozrazuje přihlašovací jméno) + datum poslední změny. */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );

add_filter( 'wp_sitemaps_posts_entry', function ( $entry, $post ) {
	$entry['lastmod'] = wp_date( DATE_W3C, strtotime( $post->post_modified_gmt . ' UTC' ) );
	return $entry;
}, 10, 2 );

add_filter( 'wp_sitemaps_index_entry', function ( $entry, $object_type, $object_subtype ) {
	global $wpdb;
	if ( 'post' === $object_type && $object_subtype ) {
		$last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $object_subtype ) );
		if ( $last ) {
			$entry['lastmod'] = wp_date( DATE_W3C, strtotime( $last . ' UTC' ) );
		}
	}
	return $entry;
}, 10, 3 );

/* Autorské archivy nepoužíváme (prozrazují login) → na titulku. */
add_action( 'template_redirect', function () {
	if ( is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

/* /llms.txt – stručný přehled webu pro AI (https://llmstxt.org). */
add_action( 'parse_request', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( 'llms.txt' !== $path ) {
		return;
	}
	$out   = array();
	$out[] = '# ' . get_bloginfo( 'name' );
	$out[] = '';
	$out[] = '> ' . ( get_bloginfo( 'description' ) ?: 'Technický blog' );
	$out[] = '';
	$out[] = 'Osobní technický blog (česky): návody a poznámky z homelabu – Linux, macOS, Raspberry Pi, Synology, Home Assistant, Loxone, Zigbee.';
	$out[] = '';
	$out[] = '## Témata';
	$out[] = '';
	foreach ( get_categories( array( 'orderby' => 'count', 'order' => 'DESC', 'hide_empty' => true ) ) as $cat ) {
		$out[] = sprintf( '- [%s](%s): %d článků', $cat->name, get_category_link( $cat ), $cat->count );
	}
	$out[] = '';
	$out[] = '## Nejnovější články';
	$out[] = '';
	foreach ( get_posts( array( 'numberposts' => 30, 'post_status' => 'publish' ) ) as $p ) {
		$excerpt = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $p ) ), 22, '…' );
		$out[]   = sprintf( '- [%s](%s): %s', html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ), get_permalink( $p ), $excerpt );
	}
	$out[] = '';
	$out[] = '## Další';
	$out[] = '';
	$out[] = '- [Sitemap](' . home_url( '/wp-sitemap.xml' ) . ')';
	$out[] = '- [RSS](' . get_feed_link() . ')';
	$kontakt = get_page_by_path( 'kontakt' );
	if ( $kontakt ) {
		$out[] = '- [Kontakt](' . get_permalink( $kontakt ) . ')';
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo implode( "\n", $out ) . "\n";
	exit;
} );
