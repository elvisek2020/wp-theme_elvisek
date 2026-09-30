<?php
/**
 * Aktualizace šablony z GitHub Releases (bez pluginu).
 *
 * Jak to funguje: WordPress se při běžné kontrole aktualizací (2× denně / v adminu)
 * zeptá GitHubu na poslední Release repozitáře. Když je jeho tag vyšší než Version
 * ve style.css a Release obsahuje soubor elvisek.zip, nabídne se aktualizace
 * v Nástěnka → Aktualizace jako u každé jiné šablony.
 *
 * Soukromé repo: do wp-config.php přidat define( 'EK_GITHUB_TOKEN', 'github_pat_…' ); (jen čtení obsahu).
 * Na lokálním vývoji je vypnuto.
 */

defined( 'ABSPATH' ) || exit;

const EK_UPDATE_REPO  = 'elvisek2020/wp-theme_elvisek';
const EK_UPDATE_ASSET = 'elvisek.zip';
const EK_UPDATE_CACHE = 'ek_theme_release';

if ( 'local' === wp_get_environment_type() ) {
	return;
}

/**
 * Poslední release z GitHubu (cache 6 h).
 */
function ek_update_latest_release(): ?array {
	$cached = get_site_transient( EK_UPDATE_CACHE );
	if ( is_array( $cached ) ) {
		return $cached ?: null;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . EK_UPDATE_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => ek_update_headers( 'application/vnd.github+json' ),
		)
	);

	$release = array();
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( EK_UPDATE_ASSET === ( $asset['name'] ?? '' ) ) {
				$release = array(
					'version' => ltrim( (string) $data['tag_name'], 'vV' ),
					'url'     => (string) $data['html_url'],
					// Soukromé repo stahuje přes API URL assetu, veřejné přes přímý odkaz.
					'package' => defined( 'EK_GITHUB_TOKEN' ) ? (string) $asset['url'] : (string) $asset['browser_download_url'],
				);
				break;
			}
		}
	}

	set_site_transient( EK_UPDATE_CACHE, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release ?: null;
}

function ek_update_headers( string $accept ): array {
	$headers = array(
		'Accept'     => $accept,
		'User-Agent' => 'elvisek-theme-updater',
	);
	if ( defined( 'EK_GITHUB_TOKEN' ) && EK_GITHUB_TOKEN ) {
		$headers['Authorization'] = 'Bearer ' . EK_GITHUB_TOKEN;
	}
	return $headers;
}

// Nabídnout aktualizaci.
add_filter( 'pre_set_site_transient_update_themes', function ( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}
	$theme   = wp_get_theme( 'elvisek' );
	$release = ek_update_latest_release();
	if ( ! $release || ! $theme->exists() ) {
		return $transient;
	}
	$item = array(
		'theme'        => 'elvisek',
		'new_version'  => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => $theme->get( 'RequiresWP' ),
		'requires_php' => $theme->get( 'RequiresPHP' ),
	);
	if ( version_compare( $release['version'], $theme->get( 'Version' ), '>' ) ) {
		$transient->response['elvisek'] = $item;
	} else {
		$transient->no_update['elvisek'] = $item;
	}
	return $transient;
} );

// Soukromé repo: stažení assetu přes API potřebuje token a Accept: octet-stream.
add_filter( 'http_request_args', function ( $args, $url ) {
	if ( defined( 'EK_GITHUB_TOKEN' ) && str_starts_with( $url, 'https://api.github.com/repos/' . EK_UPDATE_REPO . '/releases/assets/' ) ) {
		$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), ek_update_headers( 'application/octet-stream' ) );
	}
	return $args;
}, 10, 2 );

// Tlačítko „Zkontrolovat znovu“ v Aktualizacích smaže i naši cache.
add_action( 'load-update-core.php', function () {
	if ( isset( $_GET['force-check'] ) ) {
		delete_site_transient( EK_UPDATE_CACHE );
	}
} );
