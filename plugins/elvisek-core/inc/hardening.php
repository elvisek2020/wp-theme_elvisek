<?php
/**
 * EKC module: hardening
 * Version: 1.0.0
 * Requires: common
 * Replaces: disable-xml-rpc, disable-xml-rpc-api, stop-user-enumeration, disable-file-editor
 * Summary: Vypnuté XML-RPC, zakázaný editor souborů, skrytá verze WordPressu a uživatelská jména.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Zapnutí se čte při načtení souboru (ekc_opt), změna nastavení platí od dalšího požadavku.
 * XML-RPC: weby ho nepoužívají (ManageWP má vlastní rozhraní; Jetpack ho potřebuje – pak výjimka filtrem) a je častým cílem útoků
 * hrubou silou. Požadavek na xmlrpc.php skončí 403 hned po načtení pluginů (plugins_loaded,
 * aby se stihl zaregistrovat filtr ekc_allow_xmlrpc z inc/site-*.php nebo jiného pluginu).
 */

ekc_settings_register(
	'xmlrpc_off',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Vypnout XML-RPC a pingbacky',
		'help'    => 'Staré rozhraní pro vzdálené publikování, častý cíl útoků. xmlrpc.php vrací 403, z hlavičky stránky zmizí odkaz RSD a X-Pingback. Výjimku jde povolit filtrem ekc_allow_xmlrpc.',
		'section' => 'Zabezpečení',
	)
);
ekc_settings_register(
	'file_edit_off',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Zakázat editor souborů',
		'help'    => 'Skryje úpravy kódu šablon a pluginů v administraci (konstanta DISALLOW_FILE_EDIT). Když je konstanta ve wp-config.php, má přednost.',
		'section' => 'Zabezpečení',
	)
);
ekc_settings_register(
	'hide_users',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Skrýt uživatelská jména',
		'help'    => 'Pro nepřihlášené: adresy ?author=… a archivy autorů přesměrují na úvodní stránku, seznam uživatelů v REST API není, oEmbed bez jména autora.',
		'section' => 'Zabezpečení',
	)
);
ekc_settings_register(
	'hide_version',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Skrýt verzi WordPressu',
		'help'    => 'Bez meta značky generator a verze v kanálech RSS.',
		'section' => 'Zabezpečení',
	)
);

/* -------------------------------------------------------------------------
 * XML-RPC
 * ---------------------------------------------------------------------- */

/**
 * Požadavek na xmlrpc.php: 403, pokud ho filtr ekc_allow_xmlrpc nepovolí.
 */
function ekc_hardening_xmlrpc_block(): void {
	if ( ekc_filter( 'ekc_allow_xmlrpc', false ) ) {
		return;
	}
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( 'XML-RPC is disabled.' );
}

/**
 * Bez hlavičky X-Pingback.
 *
 * @param array $headers HTTP hlavičky.
 * @return array
 */
function ekc_hardening_pingback_header( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
}

if ( ekc_opt( 'xmlrpc_off' ) ) {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		add_action( 'plugins_loaded', 'ekc_hardening_xmlrpc_block', 0 );
	}
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter( 'xmlrpc_methods', '__return_empty_array' ); // ani system.listMethods, kdyby výjimka
	add_filter( 'pings_open', '__return_false', 20 );
	add_filter( 'wp_headers', 'ekc_hardening_pingback_header' );
	remove_action( 'wp_head', 'rsd_link' );
}

/* -------------------------------------------------------------------------
 * Editor souborů a verze WordPressu
 * ---------------------------------------------------------------------- */

if ( ekc_opt( 'file_edit_off' ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

if ( ekc_opt( 'hide_version' ) ) {
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );
}

/* -------------------------------------------------------------------------
 * Uživatelská jména pro nepřihlášené
 * ---------------------------------------------------------------------- */

/**
 * ?author=N a archivy autorů přesměrují na úvodní stránku (prozrazovaly by přihlašovací jména).
 * Běží před redirect_canonical, který by ?author=1 přesměroval na /author/<login>/.
 */
function ekc_hardening_author_redirect(): void {
	if ( is_user_logged_in() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen přesměrování
	if ( isset( $_GET['author'] ) || is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}

/**
 * REST API: uživatelé jen pro přihlášené (editor je potřebuje pro výběr autora).
 *
 * @param array $endpoints Registrované cesty.
 * @return array
 */
function ekc_hardening_rest_users( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( '/wp/v2/users' === $route || str_starts_with( $route, '/wp/v2/users/' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
	}
	return $endpoints;
}

/**
 * oEmbed bez jména a odkazu na autora.
 *
 * @param array $data Data odpovědi.
 * @return array
 */
function ekc_hardening_oembed_author( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
}

if ( ekc_opt( 'hide_users' ) ) {
	add_action( 'template_redirect', 'ekc_hardening_author_redirect', 1 );
	add_filter( 'rest_endpoints', 'ekc_hardening_rest_users' );
	add_filter( 'oembed_response_data', 'ekc_hardening_oembed_author' );
}
