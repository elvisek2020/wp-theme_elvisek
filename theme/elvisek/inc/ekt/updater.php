<?php
/**
 * EKT module: updater
 * Version: 1.0.1
 * Requires: common
 * Replaces: –
 * Assets: –
 * Summary: Aktualizace šablony z GitHub Releases (repo z hlavičky Update URI) bez nabídek z wordpress.org.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Vydání musí obsahovat soubor SLUG.zip (sestavuje release.yml), SLUG = složka šablony.
 * Repo bere z hlavičky Update URI ve style.css (EKT_REPO). Soukromé repo:
 * define( 'EKT_GITHUB_TOKEN', '…' ) ve wp-config.php (token jen pro čtení obsahu repa; starší
 * EKC_/EK_/MND_/IV_GITHUB_TOKEN platí dál). Na lokálním vývoji vypnuto, pro test
 * define( 'EKT_UPDATER_ON_LOCAL', true ).
 */

/**
 * Token pro GitHub API (nový, nebo ze starší šablony).
 *
 * @return string
 */
function ekt_updater_token(): string {
	foreach ( array( 'EKT_GITHUB_TOKEN', 'EKC_GITHUB_TOKEN', 'EK_GITHUB_TOKEN', 'MND_GITHUB_TOKEN', 'IV_GITHUB_TOKEN' ) as $name ) {
		if ( defined( $name ) && constant( $name ) ) {
			return (string) constant( $name );
		}
	}
	return '';
}

/**
 * HTTP hlavičky pro GitHub API.
 *
 * @param string $accept Accept.
 * @return array
 */
function ekt_updater_headers( string $accept = 'application/vnd.github+json' ): array {
	$headers = array(
		'Accept'     => $accept,
		'User-Agent' => EKT_SLUG . '-theme-updater',
	);
	$token = ekt_updater_token();
	if ( '' !== $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}
	return $headers;
}

/**
 * Poslední vydání s balíčkem SLUG.zip (cache site transient ekt_theme_release: úspěch 6 h, chyba 1 h).
 *
 * @return array{version:string,url:string,package:string}|array{} Prázdné = nic.
 */
function ekt_theme_release(): array {
	$cached = get_site_transient( 'ekt_theme_release' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$release  = array();
	$response = wp_remote_get(
		'https://api.github.com/repos/' . EKT_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => ekt_updater_headers(),
		)
	);
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( EKT_SLUG . '.zip' === ( $asset['name'] ?? '' ) ) {
				$release = array(
					'version' => ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' ),
					'url'     => (string) ( $data['html_url'] ?? '' ),
					// Soukromé repo: stahuje se přes API (s tokenem, viz ekt_updater_http_args).
					'package' => (string) ( '' !== ekt_updater_token() ? ( $asset['url'] ?? '' ) : ( $asset['browser_download_url'] ?? '' ) ),
				);
				break;
			}
		}
	}
	set_site_transient( 'ekt_theme_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release;
}

/**
 * Nabídka aktualizace šablony. Nabídku z wordpress.org pro stejný slug zahodí
 * (šablona stejného jména tam patří někomu jinému).
 *
 * @param mixed $transient Data aktualizací šablon.
 * @return mixed
 */
function ekt_updater_offer( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}
	$slug    = EKT_SLUG;
	$theme   = wp_get_theme( $slug );
	$release = $theme->exists() ? ekt_theme_release() : array();

	if ( isset( $transient->response[ $slug ] ) ) {
		unset( $transient->response[ $slug ] ); // nikdy nenabízet „aktualizaci“ z wordpress.org
	}
	if ( ! $release || '' === $release['version'] || '' === $release['package'] ) {
		return $transient;
	}
	$item = array(
		'theme'        => $slug,
		'new_version'  => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => (string) $theme->get( 'RequiresWP' ),
		'requires_php' => (string) $theme->get( 'RequiresPHP' ),
	);
	if ( version_compare( $release['version'], (string) $theme->get( 'Version' ), '>' ) ) {
		$transient->response[ $slug ] = $item;
		unset( $transient->no_update[ $slug ] );
	} else {
		$transient->no_update[ $slug ] = $item;
	}
	return $transient;
}

/**
 * Soukromé repo: token a Accept octet-stream pro stažení balíčku z API.
 *
 * @param array  $args HTTP argumenty.
 * @param string $url  Adresa.
 * @return array
 */
function ekt_updater_http_args( $args, $url ) {
	if ( is_array( $args ) && str_starts_with( (string) $url, 'https://api.github.com/repos/' . EKT_REPO . '/releases/assets/' ) ) {
		$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), ekt_updater_headers( 'application/octet-stream' ) );
	}
	return $args;
}

/**
 * Tlačítko „Zkontrolovat znovu“ v Aktualizacích smaže i naši cache.
 */
function ekt_updater_force_check(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['force-check'] ) ) {
		delete_site_transient( 'ekt_theme_release' );
	}
}

/**
 * Po aktualizaci šablon zapomenout uložené vydání.
 *
 * @param WP_Upgrader $upgrader Upgrader.
 * @param array       $extra    Typ a seznam aktualizovaných položek.
 */
function ekt_updater_clear( $upgrader, $extra = array() ): void {
	if ( 'theme' === ( $extra['type'] ?? '' ) ) {
		delete_site_transient( 'ekt_theme_release' );
	}
}

if ( '' !== EKT_REPO && ( 'local' !== wp_get_environment_type() || ( defined( 'EKT_UPDATER_ON_LOCAL' ) && EKT_UPDATER_ON_LOCAL ) ) ) {
	add_filter( 'pre_set_site_transient_update_themes', 'ekt_updater_offer' );
	add_action( 'load-update-core.php', 'ekt_updater_force_check' );
	add_action( 'upgrader_process_complete', 'ekt_updater_clear', 10, 2 );
	if ( '' !== ekt_updater_token() ) {
		add_filter( 'http_request_args', 'ekt_updater_http_args', 10, 2 );
	}
}
