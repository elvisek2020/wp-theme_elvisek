<?php
/**
 * EKC module: llms-txt
 * Version: 1.0.0
 * Requires: common
 * Replaces: website-llms-txt, llms-txt-generator
 * Summary: Adresa /llms.txt se stručným přehledem webu pro AI (llmstxt.org) – blog podle rubrik, nebo strom stránek po jazycích.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Obsah se skládá při každém požadavku (Cache-Control 1 h), bez přepisovacích pravidel.
 * Režim blog: rubriky s počtem článků a nejnovější články. Režim stránky: strom stránek
 * (s Polylangem po jazycích). Úvodní text z nastavení, řádky jde doplnit nebo upravit filtrem
 * ekc_llms_lines( array $lines, string $mode ).
 */

ekc_settings_register(
	'llms_txt',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Soubor /llms.txt',
		'help'    => 'Stručný přehled webu pro AI asistenty (llmstxt.org).',
		'section' => 'SEO',
	)
);
ekc_settings_register(
	'llms_mode',
	array(
		'type'    => 'select',
		'default' => 'auto',
		'label'   => 'Obsah /llms.txt',
		'options' => array(
			'auto'  => 'Automaticky (statická titulka = stránky, jinak blog)',
			'blog'  => 'Blog: rubriky a nejnovější články',
			'pages' => 'Stránky: strom stránek (po jazycích)',
		),
		'section' => 'SEO',
	)
);
ekc_settings_register(
	'llms_intro',
	array(
		'type'    => 'textarea',
		'default' => '',
		'label'   => 'Úvod do /llms.txt',
		'help'    => 'Pár vět o tom, co web je a pro koho. Prázdné = text titulní stránky (u stránek) nebo nic (u blogu).',
		'section' => 'SEO',
	)
);

/**
 * Text bez HTML, entit a shortcodů, zkrácený na celá slova.
 *
 * @param string $text  Text.
 * @param int    $words Počet slov (0 = nezkracovat).
 * @return string
 */
function ekc_llms_text( string $text, int $words = 60 ): string {
	$text = wp_strip_all_tags( str_replace( '<', ' <', strip_shortcodes( excerpt_remove_blocks( $text ) ) ) ); // mezera místo značky, ať se slova neslepí
	$text = trim( (string) preg_replace( '/\s+/u', ' ', ekc_llms_decode( $text ) ) );
	return $words ? wp_trim_words( $text, $words, '…' ) : $text;
}

/**
 * HTML entity → znaky (titulky, názvy rubrik, popis webu).
 *
 * @param string $text Text.
 * @return string
 */
function ekc_llms_decode( string $text ): string {
	return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/**
 * Režim obsahu: blog nebo pages.
 *
 * @return string
 */
function ekc_llms_mode(): string {
	$mode = ekc_opt( 'llms_mode' );
	if ( 'blog' === $mode || 'pages' === $mode ) {
		return $mode;
	}
	return 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ? 'pages' : 'blog';
}

/**
 * Jazyky Polylangu (objekty se slug, name), nebo prázdné pole.
 *
 * @return array
 */
function ekc_llms_languages(): array {
	if ( ! function_exists( 'pll_languages_list' ) ) {
		return array();
	}
	return array_values( array_filter( (array) pll_languages_list( array( 'fields' => '' ) ), 'is_object' ) );
}

/**
 * Řádek odkazu v Markdownu.
 *
 * @param string $title   Název.
 * @param string $url     Adresa.
 * @param string $summary Popis.
 * @param int    $depth   Odsazení.
 * @return string
 */
function ekc_llms_link( string $title, string $url, string $summary = '', int $depth = 0 ): string {
	$title = str_replace( array( '[', ']' ), array( '(', ')' ), ekc_llms_decode( $title ) );
	return sprintf( '%s- [%s](%s)%s', str_repeat( '  ', $depth ), $title, $url, '' !== $summary ? ': ' . $summary : '' );
}

/**
 * Část režimu blog: rubriky a nejnovější články.
 *
 * @return string[]
 */
function ekc_llms_blog_lines(): array {
	$out  = array();
	$cats = get_categories(
		array(
			'orderby'    => 'count',
			'order'      => 'DESC',
			'hide_empty' => true,
			'lang'       => '',
		)
	);
	if ( $cats && ! is_wp_error( $cats ) ) {
		$out[] = '## Témata';
		$out[] = '';
		foreach ( $cats as $cat ) {
			$n     = (int) $cat->count;
			$out[] = ekc_llms_link( $cat->name, (string) get_category_link( $cat ), ekc_plural( $n, '%d článek', '%d články', '%d článků' ) );
		}
		$out[] = '';
	}

	$posts = get_posts(
		array(
			'numberposts'   => (int) apply_filters( 'ekc_llms_posts', 30 ),
			'post_status'   => 'publish',
			'has_password'  => false,
			'no_found_rows' => true,
			'lang'          => '',
		)
	);
	if ( $posts ) {
		$out[] = '## Nejnovější články';
		$out[] = '';
		foreach ( $posts as $post ) {
			$out[] = ekc_llms_link( get_the_title( $post ), (string) get_permalink( $post ), ekc_llms_text( has_excerpt( $post ) ? $post->post_excerpt : $post->post_content, 22 ) );
		}
		$out[] = '';
	}
	return $out;
}

/**
 * Část režimu stránky: strom stránek, s Polylangem po jazycích.
 *
 * @return string[]
 */
function ekc_llms_pages_lines(): array {
	$out   = array();
	$langs = ekc_llms_languages();
	if ( ! $langs ) {
		$langs = array(
			(object) array(
				'slug' => '',
				'name' => 'Stránky',
			),
		);
	}
	$multi = count( $langs ) > 1;
	foreach ( $langs as $lang ) {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => (int) apply_filters( 'ekc_llms_pages', 300 ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'lang'           => (string) $lang->slug,
			)
		);
		if ( ! $pages ) {
			continue;
		}
		$home  = function_exists( 'pll_home_url' ) && $lang->slug ? (string) pll_home_url( $lang->slug ) : home_url( '/' );
		$out[] = '## ' . ekc_llms_decode( (string) $lang->name ) . ( $multi || $lang->slug ? ' (' . $home . ')' : '' );
		$out[] = '';

		// Hlavní stránky bez popisu, podstránky s krátkým popisem. Stránka, jejíž rodič není ve výběru, jde nahoru.
		$ids      = wp_list_pluck( $pages, 'ID' );
		$children = array();
		foreach ( $pages as $page ) {
			$parent                = in_array( (int) $page->post_parent, $ids, true ) ? (int) $page->post_parent : 0;
			$children[ $parent ][] = $page;
		}
		$walk = static function ( int $parent, int $depth ) use ( &$walk, &$out, $children ): void {
			foreach ( $children[ $parent ] ?? array() as $page ) {
				$summary = $depth ? ekc_llms_text( has_excerpt( $page ) ? $page->post_excerpt : $page->post_content, 18 ) : '';
				$out[]   = ekc_llms_link( get_the_title( $page ), (string) get_permalink( $page ), $summary, $depth );
				if ( $depth < 5 ) {
					$walk( (int) $page->ID, $depth + 1 );
				}
			}
		};
		$walk( 0, 0 );
		$out[] = '';
	}
	return $out;
}

/**
 * Obsah /llms.txt.
 *
 * @return string
 */
function ekc_llms_content(): string {
	$mode  = ekc_llms_mode();
	$out   = array();
	$out[] = '# ' . ekc_llms_decode( (string) get_bloginfo( 'name' ) );
	$out[] = '';

	$intro = trim( ekc_llms_decode( (string) ekc_opt( 'llms_intro' ) ) );
	$front = (int) get_option( 'page_on_front' );
	$about = '';
	if ( '' === $intro && 'pages' === $mode && $front ) {
		$about = ekc_llms_text( (string) get_post_field( 'post_content', $front ) );
	}
	$desc = ekc_llms_decode( (string) get_bloginfo( 'description' ) );
	$lead = '' !== $desc ? $desc : ( '' !== $intro ? $intro : wp_trim_words( $about, 25, '…' ) );
	if ( '' !== $lead ) {
		$out[] = '> ' . str_replace( "\n", "\n> ", $lead );
		$out[] = '';
	}
	foreach ( array( $intro, $about ) as $text ) {
		if ( '' !== $text && $text !== $lead ) {
			$out[] = $text;
			$out[] = '';
		}
	}

	// Jazykové verze (Polylang) – v režimu stránky jsou v nadpisech sekcí.
	$langs = ekc_llms_languages();
	if ( 'blog' === $mode && count( $langs ) > 1 && function_exists( 'pll_home_url' ) ) {
		$out[] = '## Jazykové verze';
		$out[] = '';
		foreach ( $langs as $lang ) {
			$out[] = ekc_llms_link( (string) $lang->name, (string) pll_home_url( $lang->slug ) );
		}
		$out[] = '';
	}

	$out = array_merge( $out, 'pages' === $mode ? ekc_llms_pages_lines() : ekc_llms_blog_lines() );

	$more = array();
	if ( function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled() ) {
		$more[] = ekc_llms_link( 'Sitemap', (string) get_sitemap_url( 'index' ) );
	}
	if ( 'blog' === $mode ) {
		$more[] = ekc_llms_link( 'RSS', (string) get_feed_link() );
	}
	if ( $more ) {
		$out[] = '## Další';
		$out[] = '';
		$out   = array_merge( $out, $more );
	}

	$out = (array) ekc_filter( 'ekc_llms_lines', $out, $mode );
	return rtrim( implode( "\n", array_map( 'strval', $out ) ) ) . "\n";
}

/**
 * Obsluha adresy /llms.txt (i při WordPressu v podsložce).
 */
function ekc_llms_serve(): void {
	if ( ! ekc_opt( 'llms_txt' ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return;
	}
	$path = trim( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ), '/' );
	$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( ( '' !== $home ? $home . '/' : '' ) . 'llms.txt' !== $path ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	if ( 'HEAD' !== $method ) {
		echo ekc_llms_content(); // phpcs:ignore WordPress.Security.EscapeOutput -- prostý text
	}
	exit;
}
add_action( 'parse_request', 'ekc_llms_serve', 1 );
