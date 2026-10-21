<?php
/**
 * Šablona ElvisEK — identita a načtení modulů.
 *
 * Soubor theme/elvisek/functions.php. Kanonické moduly v inc/ekt/ (a jejich assets/ekt/) jsou na všech
 * webech bajtově shodné a mění se jen ve skillu (scripts/modules.py). Web se liší tímto souborem,
 * šablonovými soubory (header.php, single.php…), assets/css/ a projektovými moduly
 * inc/<oblast>.php a inc/site-*.php. Vypnutí kanonického modulu = smazání souboru i jeho assets.
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Identita šablony (hodnoty z hlavičky style.css)
 * ---------------------------------------------------------------------- */

define( 'EKT_LEGACY', array( 'ek' ) ); // staré prefixy dat šablony k migraci: 'ek', 'iv', 'mnd'; nová šablona array()

define( 'EKT_FILE', __FILE__ );
define( 'EKT_DIR', get_template_directory() );
define( 'EKT_URI', get_template_directory_uri() );
define( 'EKT_SLUG', get_template() ); // složka šablony = název ZIPu ve vydání

$ekt_header = get_file_data(
	EKT_DIR . '/style.css',
	array(
		'name'    => 'Theme Name',
		'version' => 'Version',
		'uri'     => 'Update URI',
	)
);
define( 'EKT_NAME', (string) $ekt_header['name'] );
define( 'EKT_VERSION', (string) $ekt_header['version'] );
define( 'EKT_REPO', trim( (string) preg_replace( '#^https?://github\.com/#i', '', (string) $ekt_header['uri'] ), '/' ) ); // owner/repo
unset( $ekt_header );

/* -------------------------------------------------------------------------
 * Projektové moduly šablony v inc/ (pořadí = pořadí načtení, komentář = co dělají).
 * Kanonické moduly sem nepatří, načítají se samy z inc/ekt/.
 * ---------------------------------------------------------------------- */

$ekt_site_modules = array(
	'setup',         // menu, logo, velikosti obrázků, content_width, výpis článků
	'assets',        // styly a skript šablony, preload písem, editor
	'template-tags', // karty, štítky, logo, témata, drobečky, související články
);

/* -------------------------------------------------------------------------
 * Načtení: inc/ekt/common.php, ostatní inc/ekt/*.php abecedně (bez updater), projektové
 * moduly ze seznamu výše, inc/site-*.php, nakonec inc/ekt/updater.php.
 * ---------------------------------------------------------------------- */

if ( ! is_file( EKT_DIR . '/inc/ekt/common.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'switch_themes' ) ) {
				echo '<div class="notice notice-error"><p><strong>Šablona ' . esc_html( EKT_NAME ) . ':</strong> chybí soubor <code>inc/ekt/common.php</code>, funkce šablony se nenačetly. Nahrajte šablonu znovu celou.</p></div>';
			}
		}
	);
	return;
}

require_once EKT_DIR . '/inc/ekt/common.php';

$ekt_paths = glob( EKT_DIR . '/inc/ekt/*.php' );
$ekt_paths = is_array( $ekt_paths ) ? $ekt_paths : array();
sort( $ekt_paths );
foreach ( $ekt_paths as $ekt_path ) {
	if ( ! in_array( basename( $ekt_path, '.php' ), array( 'common', 'updater' ), true ) ) {
		require_once $ekt_path;
	}
}

foreach ( $ekt_site_modules as $ekt_name ) {
	if ( is_file( EKT_DIR . '/inc/' . $ekt_name . '.php' ) ) {
		require_once EKT_DIR . '/inc/' . $ekt_name . '.php';
	}
}

$ekt_paths = glob( EKT_DIR . '/inc/site-*.php' );
$ekt_paths = is_array( $ekt_paths ) ? $ekt_paths : array();
sort( $ekt_paths );
foreach ( $ekt_paths as $ekt_path ) {
	require_once $ekt_path;
}

if ( is_file( EKT_DIR . '/inc/ekt/updater.php' ) ) {
	require_once EKT_DIR . '/inc/ekt/updater.php';
}
unset( $ekt_site_modules, $ekt_paths, $ekt_path, $ekt_name );
