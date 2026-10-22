<?php
/**
 * EKT module: typography
 * Version: 1.0.0
 * Requires: common
 * Replaces: zalomeni
 * Assets: –
 * Summary: Česká typografie – pevné mezery za jednopísmennými předložkami, mezi číslem a jednotkou a v číslech.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Pevná mezera (&nbsp;), aby se řádek nezalomil na nevhodném místě:
 *  - za jednopísmennými předložkami a spojkami k, s, v, z, o, u, a, i („v Praze“),
 *  - mezi číslem a jednotkou („500 mil.“, „10 %“), v číslech s mezerou („1 000“),
 *    v měřítku („1 : 1000“) a za řadovou číslovkou („5. května“).
 * Jen na webu (ne administrace, REST, AJAX, feed) a jen v češtině (podle Polylangu nebo locale),
 * nikdy uvnitř značek ani v pre, code, kbd, samp, script, style, textarea. Uložený obsah se nemění.
 * Dokud běží Zalomení, nedělá nic (ekt_legacy_active( 'typography' )).
 *
 * Veřejné funkce: ekt_typography( $text ), ekt_typography_enabled().
 * Filtry: ekt_typography_enabled( $enabled ), ekt_typography_words( $words ),
 * ekt_typography_units( $units ), ekt_typography_filters( $filters ) (the_content, the_excerpt,
 * the_title, widget_title, widget_text_content, widget_block_content, term_description).
 */

/**
 * Upravovat text na této stránce?
 *
 * @return bool
 */
function ekt_typography_enabled(): bool {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ekt_legacy_active( 'typography' ) ) {
		return false;
	}
	$locale = function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'locale' ) : '';
	if ( '' === $locale ) {
		$locale = determine_locale();
	}
	return (bool) apply_filters( 'ekt_typography_enabled', str_starts_with( $locale, 'cs' ) );
}

/**
 * Vzory a náhrady (sestavené jednou).
 *
 * @return array<string,string> vzor => náhrada
 */
function ekt_typography_rules(): array {
	static $rules = null;
	if ( null !== $rules ) {
		return $rules;
	}
	$words = array_filter( array_map( 'strval', (array) apply_filters( 'ekt_typography_words', array( 'k', 's', 'v', 'z', 'o', 'u', 'a', 'i' ) ) ) );
	$units = array_filter( array_map( 'strval', (array) apply_filters( 'ekt_typography_units', array( 'm', 'm²', 'm³', 'cm', 'mm', 'km', 'l', 'ml', 'g', 'kg', 't', 'h', 'min', 's', '°C', '%', 'Kč', '€', '$', 'tis.', 'mil.', 'mld.', 'W', 'kW', 'kWh', 'kWp', 'MW', 'MWh', 'GWh', 'TWh', 'V', 'A', 'mAh', 'GB', 'TB', 'MB', 'let', 'lidí', 'dní', 'ks' ) ) ) );
	usort( $units, static fn( $a, $b ) => mb_strlen( $b ) <=> mb_strlen( $a ) ); // m² před m
	$quote  = static fn( $item ) => preg_quote( $item, '/' );
	// Před slovem: začátek, mezera, závorka, uvozovka nebo pevná mezera (i jako entita); ne za apostrofem.
	$before = '(?<=^|[\s(\[„“"\x{00A0}]|&nbsp;|&#160;|&#8222;|&#8220;|&bdquo;|&ldquo;)';
	$rules  = array();
	if ( $words ) {
		$rules[ '/' . $before . '(' . implode( '|', array_map( $quote, $words ) ) . ') +/iu' ] = '$1&nbsp;';
	}
	if ( $units ) {
		$rules[ '/(\d) +(' . implode( '|', array_map( $quote, $units ) ) . ')(?=$|[\s.,;:!?)<&\x{00A0}])/u' ] = '$1&nbsp;$2';
	}
	$rules['/(\d) : (?=\d)/u']                         = '$1&nbsp;:&nbsp;';
	$rules['/(?<![\d,.])(\d{1,3}) (?=\d{3}(?!\d))/u'] = '$1&nbsp;';
	$rules['/(\d\.) +(?=[0-9a-záčďéěíňóřšťúůýž])/u']  = '$1&nbsp;';
	return $rules;
}

/**
 * Doplní pevné mezery do textu mimo HTML značky a mimo pre/code/kbd/samp/script/style/textarea.
 *
 * @param mixed $text HTML nebo prostý text.
 * @return mixed
 */
function ekt_typography( $text ) {
	if ( ! is_string( $text ) || ! str_contains( $text, ' ' ) || ! ekt_typography_enabled() ) {
		return $text;
	}
	$rules = ekt_typography_rules();
	$parts = wp_html_split( $text );
	$skip  = 0;
	foreach ( $parts as $i => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' === $part[0] ) {
			if ( preg_match( '#^<(/?)(pre|code|kbd|samp|script|style|textarea)\b#i', $part, $tag ) ) {
				$skip = max( 0, $skip + ( $tag[1] ? -1 : 1 ) );
			}
			continue;
		}
		if ( ! $skip ) {
			$fixed       = preg_replace( array_keys( $rules ), array_values( $rules ), $part );
			$parts[ $i ] = null === $fixed ? $part : $fixed; // chyba výrazu (neplatné UTF-8) = text beze změny
		}
	}
	return implode( '', $parts );
}

/**
 * Napojení na výstup. Priorita 20 = po blocích, odstavcích a shortcodech.
 */
function ekt_typography_hooks(): void {
	$filters = (array) apply_filters( 'ekt_typography_filters', array( 'the_content', 'the_excerpt', 'the_title', 'widget_title', 'widget_text_content', 'widget_block_content', 'term_description' ) );
	foreach ( $filters as $filter ) {
		add_filter( (string) $filter, 'ekt_typography', 20 );
	}
}
add_action( 'init', 'ekt_typography_hooks' );
