<?php
/**
 * EKT module: load-more
 * Version: 1.0.0
 * Requires: common
 * Replaces: ajax-load-more, catch-infinite-scroll
 * Assets: js/load-more.js
 * Summary: Výpis článků po dávkách: odkaz na další stránku, který JS při dorolování třikrát načte sám, pak jako tlačítko.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Šablona označí kontejner karet [data-ekt-grid] a místo stránkování vypíše ekt_load_more().
 * Bez JS je to obyčejný odkaz na další stránku. JS (js/load-more.js) stáhne další stránku,
 * přidá její [data-ekt-grid] > * a odkaz přepíše na následující; prvních N dávek (výchozí 3)
 * načte sám při dorolování, pak čeká na kliknutí, ať jde dojet k patičce.
 *
 * Počty (hlavní dotaz titulky, archivů a hledání):
 *  - ekt_load_more_per_page( 0, $query ) – článků v dávce (0 = Nastavení → Čtení),
 *  - ekt_load_more_home_offset( 0 ) – titulka: kolik článků navíc má první stránka (např. 1 hlavní
 *    článek + dávka); další stránky se posunou a počet stránek se přepočítá.
 *
 * Veřejné funkce: ekt_load_more( $label = '' ).
 * Filtry: viz výše, ekt_load_more_auto( 3 ), ekt_load_more_texts( ['more'=>…] ),
 * ekt_load_more_html( $html, $args ) (override template-parts/ekt/load-more.php; $args: url, label).
 *
 * Markup: <div class="ekt-more" data-ekt-more-box><a class="ekt-more__btn" data-ekt-more href="…/page/2/">Načíst další</a></div>
 * Během načítání .ekt-more.is-loading a aria-busy="true".
 */

/**
 * Počet článků v dávce pro hlavní dotaz (0 = výchozí WordPressu).
 *
 * @param WP_Query $query Dotaz.
 * @return int
 */
function ekt_load_more_per_page( WP_Query $query ): int {
	return max( 0, (int) apply_filters( 'ekt_load_more_per_page', 0, $query ) );
}

/**
 * Počty v hlavním dotazu (dávka, posun titulky).
 *
 * @param WP_Query $query Dotaz.
 */
function ekt_load_more_pre_get_posts( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || $query->is_singular() || $query->is_feed() ) {
		return;
	}
	if ( ! $query->is_home() && ! $query->is_archive() && ! $query->is_search() ) {
		return;
	}
	$per = ekt_load_more_per_page( $query );
	if ( $per <= 0 ) {
		$per = (int) get_option( 'posts_per_page', 10 );
	}
	$offset = $query->is_home() ? max( 0, (int) apply_filters( 'ekt_load_more_home_offset', 0 ) ) : 0;
	if ( ekt_load_more_per_page( $query ) > 0 ) {
		$query->set( 'posts_per_page', $per );
	}
	if ( $offset > 0 ) {
		$paged = max( 1, (int) $query->get( 'paged' ) );
		if ( 1 === $paged ) {
			$query->set( 'posts_per_page', $per + $offset );
		} else {
			$query->set( 'posts_per_page', $per );
			$query->set( 'offset', $offset + ( $paged - 1 ) * $per );
		}
		$query->set( 'ekt_load_more_per', $per );
	}
}
add_action( 'pre_get_posts', 'ekt_load_more_pre_get_posts' );

/**
 * Titulka s posunem: počet stránek = 1 + zbytek po dávkách (offset ruší výpočet WordPressu).
 *
 * @param int      $found Počet nalezených.
 * @param WP_Query $query Dotaz.
 * @return int
 */
function ekt_load_more_found_posts( $found, WP_Query $query ) {
	$per = (int) $query->get( 'ekt_load_more_per' );
	if ( $per <= 0 || ! $query->is_main_query() ) {
		return $found;
	}
	$offset = max( 0, (int) apply_filters( 'ekt_load_more_home_offset', 0 ) );
	$pages  = max( 1, 1 + (int) ceil( max( 0, (int) $found - $per - $offset ) / $per ) );
	return $pages * (int) $query->get( 'posts_per_page' ); // WP dělí posts_per_page aktuální stránky
}
add_filter( 'found_posts', 'ekt_load_more_found_posts', 10, 2 );

/**
 * Odkaz „Načíst další“ ('' = poslední stránka). Volá kostra v index.php místo stránkování.
 *
 * @param string $label Text odkazu ('' = z filtru ekt_load_more_texts).
 * @return string HTML.
 */
function ekt_load_more( string $label = '' ): string {
	global $wp_query;
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( ! $wp_query instanceof WP_Query || $paged >= (int) $wp_query->max_num_pages ) {
		return '';
	}
	$texts = array_merge( array( 'more' => 'Načíst další' ), (array) apply_filters( 'ekt_load_more_texts', array() ) );
	ekt_enqueue_script(
		'ekt-load-more',
		'js/load-more.js',
		array(),
		array(
			'object' => 'EKT_LOAD_MORE',
			'data'   => array( 'auto' => max( 0, (int) apply_filters( 'ekt_load_more_auto', 3 ) ) ),
		)
	);
	return ekt_render(
		'load-more',
		static fn( array $args ): string => sprintf(
			'<div class="ekt-more" data-ekt-more-box><a class="ekt-more__btn" data-ekt-more href="%1$s">%2$s</a></div>',
			esc_url( $args['url'] ),
			esc_html( $args['label'] )
		),
		array(
			'url'   => get_pagenum_link( $paged + 1 ),
			'label' => '' !== $label ? $label : (string) $texts['more'],
		)
	);
}
