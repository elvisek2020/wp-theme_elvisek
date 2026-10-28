<?php
/**
 * EKC module: hardening
 * Version: 1.1.0
 * Requires: common
 * Replaces: disable-xml-rpc, disable-xml-rpc-api, stop-user-enumeration, disable-file-editor, wp-security-txt
 * Summary: Vypnuté XML-RPC, zakázaný editor souborů, skrytá verze WordPressu a uživatelská jména, batch API jen pro přihlášené, blok souborů jádra v .htaccess, security.txt.
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
ekc_settings_register(
	'htaccess_protect',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Zablokovat readme.html a license.txt',
		'help'    => 'Soubory jádra, které prozrazují verzi WordPressu (i wp-config-sample.php), vrátí 403. Pravidlo se zapíše do .htaccess ve vlastním bloku vedle bloku WordPressu (jen Apache a LiteSpeed; na Nginx nic nedělá), při deaktivaci pluginu se odebere.',
		'section' => 'Zabezpečení',
	)
);
ekc_settings_register(
	'rest_batch_private',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'REST batch API jen pro přihlášené',
		'help'    => 'Hromadné požadavky /wp-json/batch/v1 používá jen editor bloků. Nepřihlášenému vrátí 401 – anonymně slouží jen jako útočná plocha.',
		'section' => 'Zabezpečení',
	)
);
ekc_settings_register(
	'security_contact',
	array(
		'type'     => 'text',
		'default'  => '',
		'label'    => 'Kontakt pro hlášení zranitelností',
		'help'     => 'E-mail nebo adresa https://… Vyplněním se zapne /.well-known/security.txt (RFC 9116). Prázdné = vypnuto.',
		'section'  => 'Zabezpečení',
		'sanitize' => 'ekc_hardening_sanitize_contact',
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

/* -------------------------------------------------------------------------
 * REST batch API jen pro přihlášené
 * ---------------------------------------------------------------------- */

/**
 * /wp-json/batch/v1 (i ?rest_route=/batch/v1) pro nepřihlášené: 401. Používá ho jen editor
 * bloků; anonymně slouží jako útočná plocha (dávka obchází limity jednotlivých požadavků).
 *
 * @param mixed           $result  Dosavadní výsledek.
 * @param WP_REST_Server  $server  Server.
 * @param WP_REST_Request $request Požadavek.
 * @return mixed
 */
function ekc_hardening_rest_batch( $result, $server, $request ) {
	if ( null === $result && $request instanceof WP_REST_Request && str_starts_with( (string) $request->get_route(), '/batch/v1' ) && ! is_user_logged_in() ) {
		return new WP_Error( 'rest_forbidden', 'Batch API je jen pro přihlášené.', array( 'status' => 401 ) );
	}
	return $result;
}

if ( ekc_opt( 'rest_batch_private' ) ) {
	add_filter( 'rest_pre_dispatch', 'ekc_hardening_rest_batch', 10, 3 );
}

/* -------------------------------------------------------------------------
 * .htaccess: soubory jádra, které prozrazují verzi WordPressu
 * Statické soubory obsluhuje server mimo PHP a aktualizace WordPressu je vrací, proto pravidlo
 * v .htaccess (vlastní blok „EKC_NAME Core“ vedle bloku WordPressu). Zapíše ho krok migrace
 * (hardening/htaccess-N; při změně pravidel zvyš N), přepíše ho změna volby htaccess_protect,
 * deaktivace pluginu blok vyprázdní a krok zapomene (po nové aktivaci se zapíše znovu).
 * ---------------------------------------------------------------------- */

const EKC_HARDENING_HTACCESS_STEP = 'hardening/htaccess-1';

/**
 * Pravidla bloku (filtr ekc_htaccess_rules; prázdné pole = blok prázdný).
 *
 * @return string[]
 */
function ekc_hardening_htaccess_rules(): array {
	$rules = array(
		'<FilesMatch "^(readme\.html|license\.txt|wp-config-sample\.php)$">',
		'	<IfModule mod_authz_core.c>',
		'		Require all denied',
		'	</IfModule>',
		'	<IfModule !mod_authz_core.c>',
		'		Order allow,deny',
		'		Deny from all',
		'	</IfModule>',
		'</FilesMatch>',
	);
	return array_values( array_map( 'strval', (array) ekc_filter( 'ekc_htaccess_rules', $rules ) ) );
}

/**
 * Zapíše (nebo vyprázdní) vlastní blok v .htaccess v kořeni webu.
 *
 * @param bool $on Zapsat pravidla (false = prázdný blok).
 * @return bool Hotovo; true i tam, kde .htaccess není (Nginx), false = soubor nejde zapsat.
 */
function ekc_hardening_htaccess( bool $on ): bool {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	$file = get_home_path() . '.htaccess';
	if ( ! is_file( $file ) ) {
		return true; // server bez .htaccess, nebo ho WordPress ještě nevytvořil (Nastavení → Trvalé odkazy)
	}
	if ( ! wp_is_writable( $file ) ) {
		return false;
	}
	return (bool) insert_with_markers( $file, EKC_NAME . ' Core', $on ? ekc_hardening_htaccess_rules() : array() );
}

/**
 * Krok migrace: blok podle volby htaccess_protect.
 *
 * @return bool
 */
function ekc_hardening_htaccess_migrate(): bool {
	return ekc_hardening_htaccess( (bool) ekc_opt( 'htaccess_protect' ) );
}

add_filter(
	'ekc_migrations',
	static function ( array $steps ): array {
		$steps[ EKC_HARDENING_HTACCESS_STEP ] = 'ekc_hardening_htaccess_migrate';
		return $steps;
	}
);

/**
 * Změna volby htaccess_protect v nastavení: blok hned zapsat, nebo vyprázdnit.
 *
 * @param mixed $old Původní nastavení.
 * @param mixed $new Nové nastavení.
 */
function ekc_hardening_htaccess_option( $old, $new ): void {
	$was = is_array( $old ) && array_key_exists( 'htaccess_protect', $old ) ? (bool) $old['htaccess_protect'] : true;
	$now = is_array( $new ) && array_key_exists( 'htaccess_protect', $new ) ? (bool) $new['htaccess_protect'] : true;
	if ( $was !== $now ) {
		ekc_hardening_htaccess( $now );
	}
}
add_action( 'update_option_ekc_settings', 'ekc_hardening_htaccess_option', 10, 2 );
add_action( 'add_option_ekc_settings', static fn( $option, $value ) => ekc_hardening_htaccess_option( array(), $value ), 10, 2 );

/**
 * Deaktivace pluginu: blok vyprázdnit a krok zapomenout (nová aktivace ho zapíše znovu).
 */
function ekc_hardening_deactivate(): void {
	ekc_hardening_htaccess( false );
	$done = (array) get_option( 'ekc_migrations_done', array() );
	update_option( 'ekc_migrations_done', array_values( array_diff( $done, array( EKC_HARDENING_HTACCESS_STEP ) ) ), false );
}
register_deactivation_hook( EKC_FILE, 'ekc_hardening_deactivate' );

/* -------------------------------------------------------------------------
 * /.well-known/security.txt (RFC 9116)
 * ---------------------------------------------------------------------- */

/**
 * Kontakt: e-mail → mailto:…, jinak jen adresa https:// nebo mailto:; ostatní = ''.
 *
 * @param mixed $value Hodnota z formuláře.
 * @return string
 */
function ekc_hardening_sanitize_contact( $value ): string {
	$value = trim( sanitize_text_field( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}
	if ( str_starts_with( strtolower( $value ), 'mailto:' ) ) {
		$value = substr( $value, 7 );
	}
	if ( is_email( $value ) ) {
		return 'mailto:' . $value;
	}
	return str_starts_with( strtolower( $value ), 'https://' ) ? esc_url_raw( $value, array( 'https' ) ) : '';
}

/**
 * Obsluha /.well-known/security.txt (a 301 ze /security.txt), jen s vyplněným kontaktem.
 * Platnost (Expires) se posouvá sama, RFC doporučuje méně než rok dopředu.
 */
function ekc_hardening_security_txt(): void {
	$contact = (string) ekc_opt( 'security_contact' );
	if ( '' === $contact ) {
		return;
	}
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- jen porovnání
	$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $base && '/' !== $base && str_starts_with( $path, $base ) ) {
		$path = substr( $path, strlen( rtrim( $base, '/' ) ) );
	}
	$path = trim( $path, '/' );
	if ( 'security.txt' === $path ) {
		wp_safe_redirect( home_url( '/.well-known/security.txt' ), 301 );
		exit;
	}
	if ( '.well-known/security.txt' !== $path ) {
		return;
	}
	$lines = array(
		'Contact: ' . $contact,
		'Expires: ' . gmdate( 'Y-m-d\T00:00:00\Z', time() + 180 * DAY_IN_SECONDS ),
		'Preferred-Languages: cs, en',
		'Canonical: ' . home_url( '/.well-known/security.txt' ),
	);
	$lines = array_map( static fn( $l ) => str_replace( array( "\r", "\n" ), '', (string) $l ), (array) ekc_filter( 'ekc_security_txt', $lines ) );
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=86400' );
	echo implode( "\n", $lines ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- prostý text
	exit;
}
add_action( 'parse_request', 'ekc_hardening_security_txt', 0 );
