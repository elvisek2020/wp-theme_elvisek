<?php
/**
 * EKC module: security-headers
 * Version: 1.1.0
 * Requires: common
 * Replaces: http-headers, headers-security-advanced-hsts-wp
 * Summary: Bezpečnostní HTTP hlavičky na webu (nosniff, rámce, referrer, oprávnění, COOP, volitelně HSTS a CSP).
 */

defined( 'ABSPATH' ) || exit;

ekc_settings_register(
	'security_headers',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Bezpečnostní HTTP hlavičky',
		'help'    => 'X-Content-Type-Options, X-Frame-Options, Referrer-Policy a Permissions-Policy, podle voleb níže i COOP, HSTS a CSP. Úprava filtrem ekc_security_headers.',
		'section' => 'Zabezpečení',
	)
);
ekc_settings_register(
	'hsts',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'HSTS (vynutit HTTPS)',
		'help'    => 'Prohlížeč bude web rok otevírat jen přes HTTPS (bez subdomén, jen na HTTPS a nikdy na lokálním vývoji). Zapínejte jen, když HTTPS funguje spolehlivě.',
		'section' => 'Zabezpečení',
	)
);
ekc_settings_register(
	'coop',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Oddělit okna (Cross-Origin-Opener-Policy)',
		'help'    => 'Cizí okno otevřené z webu (nebo web otevřený z cizího okna) nesdílí window.opener. Vypněte jen kvůli přihlášení nebo platbě ve vyskakovacím okně.',
		'section' => 'Zabezpečení',
	)
);
ekc_settings_register(
	'csp',
	array(
		'type'    => 'bool',
		'default' => false,
		'label'   => 'Content-Security-Policy',
		'help'    => 'Skripty, rámce a další zdroje jen z webu, Google Analytics a videí YouTube a Vimeo. Před zapnutím ověřte, že web nic dalšího nenačítá (konzole prohlížeče). Zdroje se doplňují filtrem ekc_csp_sources.',
		'section' => 'Zabezpečení',
	)
);

/*
 * CSP: 'unsafe-inline' ve script-src je záměr. Šablona i WordPress vkládají inline skripty
 * (data modulů, souhlas s analytikou) a nonce nejde: cache hostingu uloží stránku i s nonce
 * a další návštěvník by dostal starý. Hlavní přínos CSP tu je frame-ancestors, object-src,
 * base-uri, form-action a seznam povolených cizích domén.
 */

/**
 * Zdroje CSP: direktiva => seznam zdrojů (filtr ekc_csp_sources; prázdný seznam = direktiva se vynechá).
 *
 * @return array<string,string[]>
 */
function ekc_csp_sources(): array {
	$ga      = array( 'https://www.googletagmanager.com', 'https://*.google-analytics.com', 'https://*.analytics.google.com' );
	$sources = array(
		'default-src'     => array( "'self'" ),
		'script-src'      => array( "'self'", "'unsafe-inline'", 'https://www.googletagmanager.com' ),
		'style-src'       => array( "'self'", "'unsafe-inline'" ),
		'img-src'         => array( "'self'", 'data:', 'https:' ),
		'font-src'        => array( "'self'", 'data:' ),
		'connect-src'     => array_merge( array( "'self'" ), $ga ),
		'frame-src'       => array( "'self'", 'https://www.youtube-nocookie.com', 'https://www.youtube.com', 'https://player.vimeo.com' ),
		'media-src'       => array( "'self'", 'https:' ),
		'worker-src'      => array( "'self'" ),
		'manifest-src'    => array( "'self'" ),
		'object-src'      => array( "'none'" ),
		'base-uri'        => array( "'self'" ),
		'form-action'     => array( "'self'" ),
		'frame-ancestors' => array( "'self'" ),
	);
	return (array) ekc_filter( 'ekc_csp_sources', $sources );
}

/**
 * Hodnota hlavičky Content-Security-Policy (filtr ekc_csp; '' = neposílat).
 *
 * @return string
 */
function ekc_csp(): string {
	$parts = array();
	foreach ( ekc_csp_sources() as $directive => $list ) {
		$directive = strtolower( (string) preg_replace( '/[^A-Za-z-]/', '', (string) $directive ) );
		$list      = array_filter( array_map( static fn( $src ) => trim( str_replace( array( ';', ',' ), '', (string) $src ) ), (array) $list ) );
		if ( '' !== $directive && $list ) {
			$parts[] = $directive . ' ' . implode( ' ', array_unique( $list ) );
		}
	}
	if ( $parts && is_ssl() ) {
		$parts[] = 'upgrade-insecure-requests';
	}
	return (string) ekc_filter( 'ekc_csp', implode( '; ', $parts ) );
}

/**
 * Hlavičky, které se pošlou (po úpravě filtrem ekc_security_headers; prázdná hodnota = neposílat).
 *
 * @return array<string,string>
 */
function ekc_security_headers_list(): array {
	$headers = array(
		'X-Content-Type-Options' => 'nosniff',
		'X-Frame-Options'        => 'SAMEORIGIN',
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
		'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()',
	);
	if ( ekc_opt( 'hsts' ) && is_ssl() && 'local' !== wp_get_environment_type() ) {
		$headers['Strict-Transport-Security'] = 'max-age=31536000';
	}
	if ( ekc_opt( 'coop' ) ) {
		$headers['Cross-Origin-Opener-Policy'] = 'same-origin';
	}
	if ( ekc_opt( 'csp' ) ) {
		$headers['Content-Security-Policy'] = ekc_csp();
	}
	return (array) ekc_filter( 'ekc_security_headers', $headers );
}

/**
 * Pošle hlavičky na webu. Administrace a přihlášení posílají X-Frame-Options samy.
 */
function ekc_security_headers_send(): void {
	if ( ! ekc_opt( 'security_headers' ) || headers_sent() ) {
		return;
	}
	foreach ( ekc_security_headers_list() as $name => $value ) {
		$name  = preg_replace( '/[^A-Za-z0-9-]/', '', (string) $name );
		$value = str_replace( array( "\r", "\n" ), '', (string) $value );
		if ( '' !== $name && '' !== $value ) {
			header( $name . ': ' . $value );
		}
	}
}
add_action( 'send_headers', 'ekc_security_headers_send' );
