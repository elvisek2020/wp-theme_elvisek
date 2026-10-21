<?php
/**
 * EKT module: summary
 * Version: 1.0.0
 * Requires: common
 * Replaces: –
 * Assets: –
 * Summary: Box „Ve zkratce“ nad textem článku z bodů v Nastavení článku (max. 8, `kód` v obrácených apostrofech).
 */

defined( 'ABSPATH' ) || exit;

/*
 * Data: post meta ekt_summary (Nastavení článku → Ve zkratce), jeden bod na řádek. Odrážky
 * a čísla na začátku řádku se při uložení odstraní, prázdné řádky vynechají, max. 8 bodů
 * (filtr ekt_summary_max). Ukládá se prostý text (bez sanitize_text_field, ten maže i `<16>`),
 * při výpisu se escapuje; text v `obrácených apostrofech` se vypíše jako <code>.
 *
 * Veřejné funkce: ekt_summary_items( $post ), ekt_summary_box( $post ), ekt_summary_sanitize( $value ).
 * Filtry: ekt_summary_max( 8 ), ekt_summary_texts( ['title'=>'Ve zkratce'] ),
 * ekt_summary_html( $html, $args ) (override template-parts/ekt/summary.php, $args['items'] = HTML bodů).
 * Jiné typy obsahu: v inc/site-*.php zaregistruj pole znovu se stejným klíčem a jinými post_types.
 *
 * Markup: <aside class="ekt-summary" aria-label="Ve zkratce">
 *           <p class="ekt-summary__title"><svg class="ekt-icon ekt-icon--check">Ve zkratce</p>
 *           <ul class="ekt-summary__list"><li class="ekt-summary__item">…</li></ul></aside>
 */

ekt_post_setting_register(
	'summary',
	array(
		'type'        => 'textarea',
		'label'       => 'Ve zkratce',
		'help'        => 'Box nad textem článku. Jeden bod na řádek (max. 8), `kód` v obrácených apostrofech.',
		'rows'        => 5,
		'placeholder' => 'Jeden bod na řádek',
		'priority'    => 20,
		'sanitize'    => 'ekt_summary_sanitize',
	)
);

/**
 * Body shrnutí: řádky bez odrážek a prázdných řádků, nejvýš ekt_summary_max.
 *
 * @param mixed $value Text.
 * @return string Body oddělené \n.
 */
function ekt_summary_sanitize( $value ): string {
	$lines = explode( "\n", ekt_sanitize_text( $value, true ) );
	$lines = array_map( static fn( $line ) => trim( (string) preg_replace( '/^\s*(?:[-*•–]|\d+[.)])\s+/u', '', $line ) ), $lines );
	$lines = array_values( array_filter( $lines, static fn( $line ) => '' !== $line ) );
	return implode( "\n", array_slice( $lines, 0, max( 1, (int) apply_filters( 'ekt_summary_max', 8 ) ) ) );
}

/**
 * Body shrnutí článku (prostý text).
 *
 * @param int|WP_Post|null $post Článek.
 * @return string[]
 */
function ekt_summary_items( $post = null ): array {
	$raw = (string) ekt_post_setting( 'summary', $post );
	return '' === $raw ? array() : array_values( array_filter( explode( "\n", $raw ), static fn( $line ) => '' !== trim( $line ) ) );
}

/**
 * Box „Ve zkratce“ ('' = článek shrnutí nemá).
 *
 * @param int|WP_Post|null $post Článek.
 * @return string HTML.
 */
function ekt_summary_box( $post = null ): string {
	$post = get_post( $post );
	if ( ! $post || post_password_required( $post ) ) {
		return '';
	}
	$items = ekt_summary_items( $post );
	if ( ! $items ) {
		return '';
	}
	$html  = array_map( static fn( $item ) => (string) preg_replace( '/`([^`]+)`/', '<code>$1</code>', esc_html( $item ) ), $items );
	$texts = (array) apply_filters( 'ekt_summary_texts', array( 'title' => 'Ve zkratce' ) );
	return ekt_render(
		'summary',
		static function ( array $args ): string {
			return sprintf(
				'<aside class="ekt-summary" aria-label="%1$s"><p class="ekt-summary__title">%2$s%3$s</p><ul class="ekt-summary__list"><li class="ekt-summary__item">%4$s</li></ul></aside>',
				esc_attr( $args['title'] ),
				ekt_icon( 'check', 16 ),
				esc_html( $args['title'] ),
				implode( '</li><li class="ekt-summary__item">', $args['items'] )
			);
		},
		array(
			'items' => $html,
			'title' => (string) ( $texts['title'] ?? 'Ve zkratce' ),
			'post'  => $post,
		)
	);
}

/*
 * Migrace: post meta ek_summary → ekt_summary (A elvisek).
 */
add_filter(
	'ekt_migrations',
	static function ( array $steps ): array {
		$steps['summary/post-meta'] = 'ekt_summary_migrate';
		return $steps;
	}
);

/**
 * Krok migrace: {p}_summary → ekt_summary.
 *
 * @return bool
 */
function ekt_summary_migrate(): bool {
	$ok = true;
	foreach ( ekt_legacy_prefixes() as $p ) {
		$ok = ekt_migrate_meta_key( 'post', $p . '_summary', 'ekt_summary' ) && $ok;
	}
	return $ok;
}
