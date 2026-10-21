<?php
/**
 * EKT module: quick-search
 * Version: 1.0.2
 * Requires: common
 * Replaces: searchwp-live-ajax-search, ajax-search-lite
 * Assets: js/quick-search.js, css/quick-search.css
 * Summary: Rychlé hledání během psaní: REST ekt/v1/search a našeptávač pod libovolným formulářem form[data-ekt-qs].
 */

defined( 'ABSPATH' ) || exit;

/*
 * REST: GET /wp-json/ekt/v1/search?q=… → až 6 výsledků [ { t: titulek, u: adresa, c: rubrika
 * nebo „Stránka“, d: datum } ], Cache-Control public 5 min. Při EKT_LEGACY s 'ek' i alias
 * ek/v1/search (stránky a skripty staré šablony v cache hostingu).
 *
 * JS (js/quick-search.js) se napojí na každý form[data-ekt-qs] s input[type=search] (atribut
 * může nést adresu API, jinak window.EKT_QS.api). Výsledky ovládá šipkami, Enterem a Esc.
 * Standardní formulář get_search_form() dostane data-ekt-qs automaticky (filtr
 * ekt_quick_search_searchform), vlastní formulář šablony stačí označit atributem.
 * Otevírání lupy v hlavičce je vzhled šablony.
 *
 * Veřejné funkce: ekt_quick_search_results( $q ), ekt_quick_search_api_url().
 * Filtry: ekt_quick_search_args( $query_args, $q ), ekt_quick_search_item( $item, $post ),
 * ekt_quick_search_texts( $texts ), ekt_quick_search_searchform( true ),
 * ekt_quick_search_enqueue( true ) (false = šablona načte skript sama přes ekt_enqueue_script).
 *
 * Markup (vytváří JS uvnitř formuláře): <div class="ekt-qs" hidden>
 *   <ul class="ekt-qs__list" role="listbox"><li class="ekt-qs__item" role="option" [aria-selected]>
 *     <a class="ekt-qs__link"><span class="ekt-qs__title">…<mark></span><span class="ekt-qs__meta">…</span></a></li></ul>
 *   <a class="ekt-qs__all">Všechny výsledky…</a> | <p class="ekt-qs__empty">…</p></div>
 * Aktivní položka: .is-active. Formulář dostane .is-qs-open.
 */

/**
 * Výsledky rychlého hledání.
 *
 * @param string $q Hledaný text.
 * @return array<int,array{t:string,u:string,c:string,d:string}>
 */
function ekt_quick_search_results( string $q ): array {
	if ( mb_strlen( $q ) < 2 ) {
		return array();
	}
	$query = new WP_Query(
		(array) apply_filters(
			'ekt_quick_search_args',
			array(
				's'                   => $q,
				'post_type'           => array( 'post', 'page' ),
				'post_status'         => 'publish',
				'posts_per_page'      => 6,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'has_password'        => false,
			),
			$q
		)
	);
	$out = array();
	foreach ( $query->posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$cat   = 'post' === $post->post_type ? ekt_primary_category( $post ) : null;
		$type  = get_post_type_object( $post->post_type );
		$item  = array(
			't' => ekt_plain_title( $post ),
			'u' => (string) get_permalink( $post ),
			'c' => $cat ? html_entity_decode( $cat->name, ENT_QUOTES, 'UTF-8' ) : ( 'page' === $post->post_type ? 'Stránka' : (string) ( $type->labels->singular_name ?? '' ) ),
			'd' => 'post' === $post->post_type ? (string) get_the_date( 'j. n. Y', $post ) : '',
		);
		$out[] = (array) apply_filters( 'ekt_quick_search_item', $item, $post );
	}
	return $out;
}

/**
 * REST trasy ekt/v1/search (a alias ek/v1/search pro starou šablonu A).
 */
function ekt_quick_search_routes(): void {
	$args       = array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'q' => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => static fn( $value ) => mb_substr( trim( sanitize_text_field( (string) $value ) ), 0, 80 ),
			),
		),
		'callback'            => static function ( WP_REST_Request $request ) {
			$response = rest_ensure_response( ekt_quick_search_results( (string) $request['q'] ) );
			$response->header( 'Cache-Control', 'public, max-age=300' );
			return $response;
		},
	);
	register_rest_route( 'ekt/v1', '/search', $args );
	if ( in_array( 'ek', ekt_legacy_prefixes(), true ) ) {
		register_rest_route( 'ek/v1', '/search', $args );
	}
}
add_action( 'rest_api_init', 'ekt_quick_search_routes' );

/**
 * Adresa REST endpointu.
 *
 * @return string
 */
function ekt_quick_search_api_url(): string {
	return rest_url( 'ekt/v1/search' );
}

/**
 * Skript a funkční styl na webu (malý, odložený; formulář hledání bývá v hlavičce všude).
 */
function ekt_quick_search_enqueue(): void {
	if ( ! apply_filters( 'ekt_quick_search_enqueue', true ) ) {
		return;
	}
	$texts = array_merge(
		array(
			'all'   => 'Všechny výsledky pro „%s“',
			'empty' => 'Nic jsme nenašli. Zkuste jiné slovo.',
		),
		(array) apply_filters( 'ekt_quick_search_texts', array() )
	);
	ekt_enqueue_style( 'ekt-quick-search', 'css/quick-search.css' );
	ekt_enqueue_script(
		'ekt-quick-search',
		'js/quick-search.js',
		array(),
		array(
			'object' => 'EKT_QS',
			'data'   => array(
				'api'   => ekt_quick_search_api_url(),
				'texts' => $texts,
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'ekt_quick_search_enqueue' );

/**
 * Standardní formulář hledání → data-ekt-qs.
 *
 * @param string $form HTML formuláře.
 * @return string
 */
function ekt_quick_search_searchform( $form ): string {
	$form = (string) $form;
	if ( is_admin() || ! apply_filters( 'ekt_quick_search_searchform', true ) ) {
		return $form;
	}
	$p = new WP_HTML_Tag_Processor( $form );
	if ( $p->next_tag( 'form' ) && null === $p->get_attribute( 'data-ekt-qs' ) ) {
		$p->set_attribute( 'data-ekt-qs', true );
		return $p->get_updated_html();
	}
	return $form;
}
add_filter( 'get_search_form', 'ekt_quick_search_searchform', 20 );
