<?php
/**
 * EKC module: updater
 * Version: 1.0.1
 * Requires: common
 * Replaces: –
 * Summary: Aktualizace pluginu z GitHub Releases (Update URI) včetně okna Zobrazit podrobnosti.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Vydání musí obsahovat soubor SLUG-core.zip (sestavuje release.yml). Repo bere z hlavičky
 * Update URI (EKC_REPO). Soukromé repo: define( 'EKC_GITHUB_TOKEN', '…' ) ve wp-config.php (přijme i EKT_/EK_/MND_/IV_GITHUB_TOKEN)
 * (token jen pro čtení obsahu repa). Na lokálním vývoji vypnuto, pro test
 * define( 'EKC_UPDATER_ON_LOCAL', true ).
 */

/**
 * Slug pluginu (název složky).
 *
 * @return string
 */
function ekc_updater_slug(): string {
	return dirname( EKC_BASENAME );
}

/**
 * Hlavička pluginu (Requires at least, Requires PHP, Description, Author).
 *
 * @return array
 */
function ekc_updater_header(): array {
	static $header = null;
	if ( null === $header ) {
		$header = get_file_data(
			EKC_FILE,
			array(
				'name'         => 'Plugin Name',
				'requires'     => 'Requires at least',
				'requires_php' => 'Requires PHP',
				'description'  => 'Description',
				'author'       => 'Author',
			)
		);
	}
	return $header;
}

/**
 * Token pro soukromé repo: EKC_GITHUB_TOKEN, jinak token šablony (EKT_) nebo starších
 * pluginů a šablon (EK_, MND_, IV_), aby převedený web nemusel měnit wp-config.php.
 */
function ekc_updater_token(): string {
	foreach ( array( 'EKC_GITHUB_TOKEN', 'EKT_GITHUB_TOKEN', 'EK_GITHUB_TOKEN', 'MND_GITHUB_TOKEN', 'IV_GITHUB_TOKEN' ) as $name ) {
		if ( defined( $name ) && is_string( constant( $name ) ) && '' !== constant( $name ) ) {
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
function ekc_updater_headers( string $accept = 'application/vnd.github+json' ): array {
	$headers = array(
		'Accept'     => $accept,
		'User-Agent' => ekc_updater_slug() . '-updater',
	);
	if ( '' !== ekc_updater_token() ) {
		$headers['Authorization'] = 'Bearer ' . ekc_updater_token();
	}
	return $headers;
}

/**
 * Poslední vydání s balíčkem SLUG-core.zip (cache site transient ekc_release: úspěch 6 h, chyba 1 h).
 *
 * @return array{version:string,url:string,package:string,notes:string,date:string}|array{} Prázdné = nic.
 */
function ekc_release(): array {
	$cached = get_site_transient( 'ekc_release' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$release  = array();
	$response = wp_remote_get(
		'https://api.github.com/repos/' . EKC_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => ekc_updater_headers(),
		)
	);
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( ekc_updater_slug() . '.zip' === ( $asset['name'] ?? '' ) ) {
				$private = '' !== ekc_updater_token();
				$release = array(
					'version' => ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' ),
					'url'     => (string) ( $data['html_url'] ?? '' ),
					// Soukromé repo: stahuje se přes API (s tokenem, viz ekc_updater_http_args).
					'package' => (string) ( $private ? ( $asset['url'] ?? '' ) : ( $asset['browser_download_url'] ?? '' ) ),
					'notes'   => (string) ( $data['body'] ?? '' ),
					'date'    => (string) ( $data['published_at'] ?? '' ),
				);
				break;
			}
		}
	}
	set_site_transient( 'ekc_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release;
}

/**
 * Odpověď pro kontrolu aktualizací (Update URI → filtr update_plugins_github.com).
 *
 * @param array|false $update      Data aktualizace.
 * @param array       $plugin_data Hlavičky pluginu.
 * @param string      $plugin_file Soubor pluginu.
 * @return array|false
 */
function ekc_updater_check( $update, $plugin_data, $plugin_file ) {
	if ( EKC_BASENAME !== $plugin_file ) {
		return $update;
	}
	$release = ekc_release();
	if ( ! $release || '' === $release['version'] || '' === $release['package'] ) {
		return $update;
	}
	$header = ekc_updater_header();
	return array(
		'id'           => 'https://github.com/' . EKC_REPO,
		'slug'         => ekc_updater_slug(),
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'tested'       => EKC_TESTED,
		'requires'     => (string) $header['requires'],
		'requires_php' => (string) $header['requires_php'],
	);
}

/**
 * Okno „Zobrazit podrobnosti“ (jinak by WordPress hledal plugin na wordpress.org).
 *
 * @param false|object|array $result Výsledek.
 * @param string             $action Typ dotazu.
 * @param object             $args   Parametry.
 * @return false|object|array
 */
function ekc_updater_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ekc_updater_slug() !== ( $args->slug ?? '' ) ) {
		return $result;
	}
	$release = ekc_release();
	$header  = ekc_updater_header();
	$notes   = '' !== ( $release['notes'] ?? '' ) ? $release['notes'] : 'Viz CHANGELOG na GitHubu.';
	return (object) array(
		'name'          => (string) $header['name'],
		'slug'          => ekc_updater_slug(),
		'version'       => $release['version'] ?? EKC_VERSION,
		'author'        => esc_html( (string) $header['author'] ),
		'homepage'      => 'https://github.com/' . EKC_REPO,
		'tested'        => EKC_TESTED,
		'requires'      => (string) $header['requires'],
		'requires_php'  => (string) $header['requires_php'],
		'last_updated'  => $release['date'] ?? '',
		'download_link' => $release['package'] ?? '',
		'sections'      => array(
			'description' => esc_html( (string) $header['description'] ),
			'changelog'   => '<pre style="white-space:pre-wrap">' . esc_html( $notes ) . '</pre><p><a href="' . esc_url( 'https://github.com/' . EKC_REPO . '/blob/main/CHANGELOG.md' ) . '" target="_blank" rel="noopener">Celý CHANGELOG</a></p>',
		),
	);
}

/**
 * Soukromé repo: token a Accept octet-stream pro stažení balíčku z API.
 *
 * @param array  $args HTTP argumenty.
 * @param string $url  Adresa.
 * @return array
 */
function ekc_updater_http_args( array $args, string $url ): array {
	if ( str_starts_with( $url, 'https://api.github.com/repos/' . EKC_REPO . '/releases/assets/' ) ) {
		$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), ekc_updater_headers( 'application/octet-stream' ) );
	}
	return $args;
}

/**
 * Tlačítko „Zkontrolovat znovu“ v Aktualizacích smaže i naši cache.
 */
function ekc_updater_force_check(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['force-check'] ) ) {
		delete_site_transient( 'ekc_release' );
	}
}

/**
 * Po aktualizaci pluginů zapomenout uložené vydání.
 *
 * @param WP_Upgrader $upgrader Upgrader.
 * @param array       $extra    Typ a seznam aktualizovaných položek.
 */
function ekc_updater_clear( $upgrader, $extra = array() ): void {
	if ( 'plugin' === ( $extra['type'] ?? '' ) ) {
		delete_site_transient( 'ekc_release' );
	}
}

if ( 'local' !== wp_get_environment_type() || ( defined( 'EKC_UPDATER_ON_LOCAL' ) && EKC_UPDATER_ON_LOCAL ) ) {
	add_filter( 'update_plugins_github.com', 'ekc_updater_check', 10, 3 );
	add_filter( 'plugins_api', 'ekc_updater_info', 10, 3 );
	add_action( 'load-update-core.php', 'ekc_updater_force_check' );
	add_action( 'upgrader_process_complete', 'ekc_updater_clear', 10, 2 );
	if ( '' !== ekc_updater_token() ) {
		add_filter( 'http_request_args', 'ekc_updater_http_args', 10, 2 );
	}
}
