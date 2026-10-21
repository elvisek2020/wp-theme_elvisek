<?php
/**
 * EKT module: content-links
 * Version: 1.0.0
 * Requires: common
 * Replaces: open-external-links-in-a-new-window, wp-external-links
 * Assets: –
 * Summary: Externí odkazy v obsahu do nového okna s rel noopener sloučeným s existujícím rel (nofollow, sponsored zůstanou).
 */

defined( 'ABSPATH' ) || exit;

/*
 * Úprava výstupu přes WP_HTML_Tag_Processor (uložený obsah se nemění), filtr the_content
 * s prioritou 20 (po blocích, odstavcích a shortcodech).
 *  - Externí = http(s) nebo // adresa s jiným hostitelem než web; „www.“ se nebere v úvahu
 *    (example.com = www.example.com). Další vlastní domény (jazykové verze): filtr
 *    ekt_content_links_hosts( $hosts ).
 *  - target="_blank" jen když odkaz target nemá; rel: k existujícím hodnotám se přidá noopener
 *    (nofollow, sponsored, ugc zůstanou), bez duplicit.
 *  - Odkazy na obrázky pro lightbox (data-ekt-lightbox) a odkazy s data-ekt-same-window se nemění.
 *  - Dokud běží původní plugin, nedělá nic (ekt_legacy_active( 'ext_links' )).
 *
 * Veřejné funkce: ekt_content_links_is_external( $href ), ekt_content_links_process( $html ).
 * Filtry: ekt_content_links_hosts( $hosts ), ekt_content_links_filters( $filters ) (výchozí
 * the_content), ekt_content_links_rel( $tokens, $href ).
 */

/**
 * Vlastní hostitelé webu bez „www.“ (malými písmeny).
 *
 * @return string[]
 */
function ekt_content_links_hosts(): array {
	static $hosts = null;
	if ( null === $hosts ) {
		$own   = array( (string) wp_parse_url( home_url(), PHP_URL_HOST ), (string) wp_parse_url( site_url(), PHP_URL_HOST ) );
		$own   = (array) apply_filters( 'ekt_content_links_hosts', $own );
		$hosts = array_values( array_unique( array_filter( array_map( static fn( $h ) => (string) preg_replace( '/^www\./', '', strtolower( trim( (string) $h ) ) ), $own ) ) ) );
	}
	return $hosts;
}

/**
 * Je adresa externí (jiný hostitel než web)?
 *
 * @param string $href Adresa.
 * @return bool
 */
function ekt_content_links_is_external( string $href ): bool {
	$href = trim( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( str_starts_with( $href, '//' ) ) {
		$href = 'https:' . $href;
	}
	if ( ! preg_match( '#^https?://#i', $href ) ) {
		return false; // relativní, #kotva, mailto:, tel:
	}
	$host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );
	$host = (string) preg_replace( '/^www\./', '', $host );
	return '' !== $host && ! in_array( $host, ekt_content_links_hosts(), true );
}

/**
 * Úprava externích odkazů v HTML.
 *
 * @param string $html HTML.
 * @return string
 */
function ekt_content_links_process( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '<a' ) || ekt_legacy_active( 'ext_links' ) ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( 'A' ) ) {
		$href = $p->get_attribute( 'href' );
		if ( ! is_string( $href ) || null !== $p->get_attribute( 'data-ekt-lightbox' ) || null !== $p->get_attribute( 'data-ekt-same-window' ) ) {
			continue;
		}
		if ( ! ekt_content_links_is_external( $href ) ) {
			continue;
		}
		$target = $p->get_attribute( 'target' );
		if ( ! is_string( $target ) || '' === trim( $target ) ) {
			$p->set_attribute( 'target', '_blank' );
		}
		$rel    = $p->get_attribute( 'rel' );
		$tokens = preg_split( '/\s+/', strtolower( trim( is_string( $rel ) ? $rel : '' ) ), -1, PREG_SPLIT_NO_EMPTY );
		$tokens = array_values( array_unique( array_merge( (array) $tokens, array( 'noopener' ) ) ) );
		$tokens = (array) apply_filters( 'ekt_content_links_rel', $tokens, $href );
		$p->set_attribute( 'rel', implode( ' ', array_map( 'strval', $tokens ) ) );
	}
	return $p->get_updated_html();
}

/**
 * Napojení na výstup obsahu.
 */
function ekt_content_links_hooks(): void {
	foreach ( (array) apply_filters( 'ekt_content_links_filters', array( 'the_content' ) ) as $filter ) {
		add_filter( (string) $filter, 'ekt_content_links_process', 20 );
	}
}
add_action( 'init', 'ekt_content_links_hooks' );
