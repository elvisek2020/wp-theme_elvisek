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
 * /.well-known/security.txt (RFC 9116): kontakt pro hlášení zranitelností.
 * ====================================================================== */

const EK_SITE_SECURITY_CONTACT = 'mailto:elvisek@elvisek.cz';

add_action( 'parse_request', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( 'security.txt' === $path ) {
		wp_safe_redirect( home_url( '/.well-known/security.txt' ), 301 );
		exit;
	}
	if ( '.well-known/security.txt' !== $path ) {
		return;
	}
	$lines = array(
		'Contact: ' . EK_SITE_SECURITY_CONTACT,
		// Platnost se posouvá sama (RFC doporučuje méně než rok dopředu).
		'Expires: ' . gmdate( 'Y-m-d\T00:00:00\Z', time() + 180 * DAY_IN_SECONDS ),
		'Preferred-Languages: cs, en',
		'Canonical: ' . home_url( '/.well-known/security.txt' ),
	);
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=86400' );
	echo implode( "\n", $lines ) . "\n";
	exit;
} );

/* =========================================================================
 * Content-Security-Policy pro web (hlavičky posílá modul security-headers jen na webu,
 * ne v administraci). Skripty inline ('unsafe-inline') kvůli inline skriptům šablony
 * a cache hostingu (nonce by se uložil do cache se stránkou); cizí skripty jen GA po souhlasu.
 * ====================================================================== */

add_filter( 'ekc_security_headers', function ( array $headers ): array {
	$ga = 'https://www.googletagmanager.com https://*.google-analytics.com https://*.analytics.google.com';
	$headers['Content-Security-Policy'] = implode( '; ', array(
		"default-src 'self'",
		"script-src 'self' 'unsafe-inline' https://www.googletagmanager.com",
		"style-src 'self' 'unsafe-inline'",
		"img-src 'self' data: https:",
		"font-src 'self' data:",
		"connect-src 'self' " . $ga,
		"frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com",
		"media-src 'self' https:",
		"worker-src 'self'",
		"manifest-src 'self'",
		"object-src 'none'",
		"base-uri 'self'",
		"form-action 'self'",
		"frame-ancestors 'self'",
	) ) . ( is_ssl() ? '; upgrade-insecure-requests' : '' );
	return $headers;
} );
