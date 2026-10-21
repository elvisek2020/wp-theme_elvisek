<?php
/**
 * EKC module: security-headers
 * Version: 1.0.0
 * Requires: common
 * Replaces: http-headers, headers-security-advanced-hsts-wp
 * Summary: Bezpečnostní HTTP hlavičky na webu (nosniff, rámce, referrer, oprávnění, volitelně HSTS).
 */

defined( 'ABSPATH' ) || exit;

ekc_settings_register(
	'security_headers',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Bezpečnostní HTTP hlavičky',
		'help'    => 'X-Content-Type-Options, X-Frame-Options, Referrer-Policy a Permissions-Policy. Úprava filtrem ekc_security_headers.',
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
