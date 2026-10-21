<?php
/**
 * EKC module: sitemap
 * Version: 1.0.0
 * Requires: common
 * Replaces: google-sitemap-generator, google-xml-sitemaps-v3-for-qtranslate, xml-sitemap-feed, simple-wp-sitemap
 * Summary: Vestavěná sitemapa WordPressu bez uživatelů, s datem změny a přesměrováním starých adres sitemapy.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Používá jádro WordPressu (/wp-sitemap.xml). Modul jen odebere seznam uživatelů (prozrazuje
 * přihlašovací jména), doplní lastmod a staré adresy /sitemap.xml, /sitemap.xml.gz
 * a /sitemap_index.xml přesměruje 301 – jen když na webu neběží jiný generátor sitemapy
 * (ten si adresy obsluhuje sám). Statický soubor sitemap.xml v kořeni webu má přednost (server
 * ho pošle dřív, než se WordPress spustí) – po přechodu ho smažte.
 */

ekc_settings_register(
	'sitemap_no_users',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Sitemap bez uživatelů',
		'help'    => 'Seznam autorů v sitemapě prozrazuje přihlašovací jména.',
		'section' => 'SEO',
	)
);
ekc_settings_register(
	'sitemap_lastmod',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Datum změny v sitemapě',
		'help'    => 'Vyhledávače podle něj poznají, co mají projít znovu.',
		'section' => 'SEO',
	)
);
ekc_settings_register(
	'sitemap_redirect',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Přesměrovat staré adresy sitemapy',
		'help'    => '/sitemap.xml, /sitemap.xml.gz a /sitemap_index.xml → /wp-sitemap.xml (301). Jen když neběží jiný plugin se sitemapou.',
		'section' => 'SEO',
	)
);

/**
 * Sitemap bez uživatelů.
 *
 * @param WP_Sitemaps_Provider|false $provider Poskytovatel.
 * @param string                     $name     Název.
 * @return WP_Sitemaps_Provider|false
 */
function ekc_sitemap_providers( $provider, $name ) {
	return ( 'users' === $name && ekc_opt( 'sitemap_no_users' ) ) ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'ekc_sitemap_providers', 10, 2 );

/**
 * Datum změny u položek typu obsah.
 *
 * @param array   $entry Položka sitemapy.
 * @param WP_Post $post  Obsah.
 * @return array
 */
function ekc_sitemap_entry_lastmod( $entry, $post ) {
	if ( ekc_opt( 'sitemap_lastmod' ) && $post instanceof WP_Post && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
		$entry['lastmod'] = wp_date( DATE_W3C, (int) strtotime( $post->post_modified_gmt . ' UTC' ) );
	}
	return $entry;
}
add_filter( 'wp_sitemaps_posts_entry', 'ekc_sitemap_entry_lastmod', 10, 2 );

/**
 * Datum poslední změny v indexu sitemapy (podle typu obsahu).
 *
 * @param array  $entry          Položka indexu.
 * @param string $object_type    Typ (post, term…).
 * @param string $object_subtype Podtyp (page, post…).
 * @return array
 */
function ekc_sitemap_index_lastmod( $entry, $object_type, $object_subtype ) {
	global $wpdb;
	if ( ! ekc_opt( 'sitemap_lastmod' ) || 'post' !== $object_type || ! $object_subtype ) {
		return $entry;
	}
	static $cache = array();
	if ( ! array_key_exists( $object_subtype, $cache ) ) {
		$cache[ $object_subtype ] = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $object_subtype ) );
	}
	if ( $cache[ $object_subtype ] ) {
		$entry['lastmod'] = wp_date( DATE_W3C, (int) strtotime( $cache[ $object_subtype ] . ' UTC' ) );
	}
	return $entry;
}
add_filter( 'wp_sitemaps_index_entry', 'ekc_sitemap_index_lastmod', 10, 3 );

/**
 * Běží jiný generátor sitemapy (starý plugin nebo SEO plugin se sitemapou)?
 *
 * @return bool
 */
function ekc_sitemap_foreign(): bool {
	$foreign = class_exists( 'GoogleSitemapGeneratorLoader' )      // google-sitemap-generator (+ varianta pro qTranslate)
		|| class_exists( 'XMLSF\XMLSitemapFeed' ) || defined( 'XMLSF_VERSION' ) // xml-sitemap-feed
		|| defined( 'WPSEO_VERSION' )                               // Yoast SEO
		|| class_exists( 'RankMath' )                               // Rank Math
		|| defined( 'AIOSEO_VERSION' )                              // All in One SEO
		|| defined( 'SEOPRESS_VERSION' );                           // SEOPress
	return (bool) apply_filters( 'ekc_sitemap_foreign', $foreign );
}

/**
 * Staré adresy sitemapy → index vestavěné sitemapy (301). Podporuje WordPress v podsložce.
 */
function ekc_sitemap_redirect(): void {
	if ( ! ekc_opt( 'sitemap_redirect' ) || ! isset( $_SERVER['REQUEST_URI'] ) || ekc_sitemap_foreign() ) {
		return;
	}
	if ( ! function_exists( 'wp_sitemaps_get_server' ) || ! wp_sitemaps_get_server()->sitemaps_enabled() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ), '/' );
	$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $home ) {
		if ( ! str_starts_with( $path . '/', $home . '/' ) ) {
			return;
		}
		$path = ltrim( substr( $path, strlen( $home ) ), '/' );
	}
	if ( in_array( $path, array( 'sitemap.xml', 'sitemap.xml.gz', 'sitemap_index.xml' ), true ) ) {
		wp_safe_redirect( (string) get_sitemap_url( 'index' ), 301, 'EKC' );
		exit;
	}
}
add_action( 'parse_request', 'ekc_sitemap_redirect', 1 );
