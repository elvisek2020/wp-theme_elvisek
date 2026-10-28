<?php
/**
 * Projektové úpravy webu elvisek.cz nad kanonickými moduly (jen přes jejich filtry).
 */

defined( 'ABSPATH' ) || exit;

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

/* =========================================================================
 * Nastavení pro kanon (hardening 1.1, security-headers 1.1): CSP, COOP, security.txt, batch API
 * a blok v .htaccess dřív dělal tento soubor. Kanon má CSP a security.txt ve výchozím stavu
 * vypnuté, proto je jednorázově zapne (pak jdou měnit v Nastavení → ElvisEK Core).
 * ====================================================================== */

add_filter( 'ekc_migrations', function ( array $steps ): array {
	$steps['site-elvisek/kanon-zabezpeceni'] = 'ek_site_migrate_security';
	return $steps;
} );

function ek_site_migrate_security(): bool {
	ekc_opt_set( 'csp', true );
	if ( '' === (string) ekc_opt( 'security_contact' ) ) {
		ekc_opt_set( 'security_contact', 'elvisek@elvisek.cz' );
	}
	return true;
}
