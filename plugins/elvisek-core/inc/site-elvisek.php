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
	// Cizí okno otevřené z webu (nebo web otevřený z cizího okna) nesdílí window.opener.
	$headers['Cross-Origin-Opener-Policy'] = 'same-origin';
	return $headers;
} );

/* =========================================================================
 * REST batch API (/wp-json/batch/v1) jen pro přihlášené. Používá ho editor;
 * anonymně slouží jen jako útočná plocha (např. díra wp2shell, opravená ve WP 7.0.2).
 * ====================================================================== */

add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( null === $result && str_starts_with( (string) $request->get_route(), '/batch/v1' ) && ! is_user_logged_in() ) {
		return new WP_Error( 'rest_forbidden', 'Batch API je jen pro přihlášené.', array( 'status' => 401 ) );
	}
	return $result;
}, 10, 3 );

/* =========================================================================
 * .htaccess: soubory jádra, které prozrazují verzi WordPressu (readme.html, license.txt).
 * Statické soubory obsluhuje server mimo PHP a aktualizace WordPressu je vrací, proto pravidlo
 * v .htaccess (vlastní blok „ElvisEK Core“ vedle bloku WordPressu). Zapíše ho krok migrace,
 * při deaktivaci pluginu se blok odebere.
 * ====================================================================== */

/**
 * Zapíše (prázdné pole = odebere) blok pravidel v .htaccess v kořeni webu.
 *
 * @param string[] $rules Řádky pravidel.
 * @return bool
 */
function ek_site_htaccess( array $rules ): bool {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	$file = get_home_path() . '.htaccess';
	if ( ! is_file( $file ) || ! wp_is_writable( $file ) ) {
		return false;
	}
	return (bool) insert_with_markers( $file, 'ElvisEK Core', $rules );
}

add_filter( 'ekc_migrations', function ( array $steps ): array {
	$steps['site-elvisek/htaccess-1'] = static fn(): bool => ek_site_htaccess( array(
		'<FilesMatch "^(readme\.html|license\.txt|wp-config-sample\.php)$">',
		'	<IfModule mod_authz_core.c>',
		'		Require all denied',
		'	</IfModule>',
		'	<IfModule !mod_authz_core.c>',
		'		Order allow,deny',
		'		Deny from all',
		'	</IfModule>',
		'</FilesMatch>',
	) );
	return $steps;
} );

register_deactivation_hook( EKC_FILE, static function () {
	ek_site_htaccess( array() );
	$done = (array) get_option( 'ekc_migrations_done', array() );
	update_option( 'ekc_migrations_done', array_values( array_diff( $done, array( 'site-elvisek/htaccess-1' ) ) ), false );
} );

/* =========================================================================
 * „Tested up to: 7.1“ platí pro celou řadu 7.1.x (jako na wordpress.org). WordPress jinak
 * porovná 7.1 s 7.1.3 a v Aktualizacích hlásí „Kompatibilita s WordPressem: Nebylo otestováno“.
 * Kanonický updater posílá hodnotu z hlavičky beze změny, proto úprava nad uloženou aktualizací.
 * ====================================================================== */

/**
 * Nejvyšší známá verze WordPressu ve stejné řadě (major.minor) jako $tested, jinak $tested.
 *
 * @param string $tested Hodnota „Tested up to“.
 * @return string
 */
function ek_site_tested_full( string $tested ): string {
	if ( ! preg_match( '/^\d+\.\d+$/', $tested ) ) {
		return $tested;
	}
	$versions = array( (string) preg_replace( '/-.*$/', '', (string) get_bloginfo( 'version' ) ) );
	$core     = get_site_transient( 'update_core' );
	foreach ( (array) ( is_object( $core ) ? ( $core->updates ?? array() ) : array() ) as $offer ) {
		if ( ! empty( $offer->current ) ) {
			$versions[] = (string) $offer->current;
		}
	}
	$best = $tested;
	foreach ( $versions as $v ) {
		if ( ( $v === $tested || str_starts_with( $v, $tested . '.' ) ) && version_compare( $v, $best, '>' ) ) {
			$best = $v;
		}
	}
	return $best;
}

add_filter( 'site_transient_update_plugins', function ( $transient ) {
	if ( ! is_object( $transient ) || ! defined( 'EKC_BASENAME' ) ) {
		return $transient;
	}
	foreach ( array( 'response', 'no_update' ) as $list ) {
		if ( isset( $transient->{$list}[ EKC_BASENAME ] ) ) {
			$item = $transient->{$list}[ EKC_BASENAME ];
			if ( is_object( $item ) && isset( $item->tested ) ) {
				$item->tested = ek_site_tested_full( (string) $item->tested );
			} elseif ( is_array( $item ) && isset( $item['tested'] ) ) {
				$item['tested'] = ek_site_tested_full( (string) $item['tested'] );
			}
			$transient->{$list}[ EKC_BASENAME ] = $item;
		}
	}
	return $transient;
} );

// Okno „Zobrazit podrobnosti“ (kanon odpovídá na plugins_api s prioritou 10).
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' === $action && is_object( $result ) && isset( $result->tested ) && dirname( (string) EKC_BASENAME ) === ( $args->slug ?? '' ) ) {
		$result->tested = ek_site_tested_full( (string) $result->tested );
	}
	return $result;
}, 20, 3 );
