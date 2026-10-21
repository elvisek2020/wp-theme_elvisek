<?php
/**
 * Projektové úpravy webu elvisek.cz nad kanonickými moduly (jen přes jejich filtry).
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Média: obrázek rubriky ze šablony (term meta ek_image) se počítá jako použitý.
 * ====================================================================== */

add_filter( 'ekc_media_used_ids', function ( array $ids ): array {
	global $wpdb;
	$terms = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'ek_image'" );
	return array_merge( $ids, array_filter( array_map( 'intval', $terms ) ) );
} );

/* =========================================================================
 * /llms.txt: úvodní věta ze starého pluginu (jednorázově do nastavení) a odkaz na Kontakt.
 * ====================================================================== */

const EK_SITE_LLMS_INTRO = 'Osobní technický blog (česky): návody a poznámky z homelabu – Linux, macOS, Raspberry Pi, Synology, Home Assistant, Loxone, Zigbee.';

add_filter( 'ekc_migrations', function ( array $steps ): array {
	$steps['site-elvisek/llms-intro'] = 'ek_site_migrate_llms_intro';
	return $steps;
} );

function ek_site_migrate_llms_intro(): bool {
	if ( '' === trim( (string) ekc_opt( 'llms_intro' ) ) ) {
		ekc_opt_set( 'llms_intro', EK_SITE_LLMS_INTRO );
	}
	return true;
}

add_filter( 'ekc_llms_lines', function ( array $lines ): array {
	$kontakt = get_page_by_path( 'kontakt' );
	if ( $kontakt && function_exists( 'ekc_llms_link' ) ) {
		$lines[] = ekc_llms_link( 'Kontakt', (string) get_permalink( $kontakt ) );
	}
	return $lines;
} );
