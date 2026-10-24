<?php
/**
 * Plugin Name:       ElvisEK Core
 * Description:       Funkce webu ElvisEK nezávislé na šabloně. Nahrazuje 7 pluginů.
 * Version:           0.14.1
 * Requires at least: 6.4
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Author:            Zdeněk Král (ElvisEK)
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/elvisek2020/wp-theme_elvisek
 * Text Domain:       elvisek-core
 *
 * Soubor plugins/elvisek-core/elvisek-core.php. Jediný soubor pluginu, který se liší web od webu:
 * zástupné hodnoty psané velkými písmeny nahraď (postup v references/plugin.md skillu),
 * moduly v inc/ se kopírují beze změny.
 * „Tested up to“ zvedni po ověření nové verze WordPressu.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Pojistka proti dvojímu načtení (dvě kopie pluginu, nebo plugin + jeho starý mu-plugin).
 * EKC_FILE se definuje až níže, takže při prvním načtení neexistuje.
 */
if ( defined( 'EKC_FILE' ) ) {
	return;
}

/*
 * Volitelně: web dřív běžel se starým mu-pluginem. Vyplň název funkce, kterou definoval jen ten
 * mu-plugin (NE funkci z tohoto souboru nebo z inc/), např. 'ek_login_log_add'. Když existuje,
 * plugin se nenačte a správci napíše, ať starý soubor smaže. Prázdné = kontrola vypnutá.
 */
$ekc_mu_guard = '';
if ( '' !== $ekc_mu_guard && function_exists( $ekc_mu_guard ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-warning"><p><strong>ElvisEK Core:</strong> na serveru je ještě starý mu-plugin ve složce <code>wp-content/mu-plugins/</code>. Smažte ho, pak začne fungovat tento plugin včetně aktualizací.</p></div>';
			}
		}
	);
	unset( $ekc_mu_guard );
	return;
}
unset( $ekc_mu_guard );

/* -------------------------------------------------------------------------
 * Identita webu
 * ---------------------------------------------------------------------- */

define( 'EKC_SLUG', 'elvisek' );   // krátký název webu, např. ivavaskova
define( 'EKC_NAME', 'ElvisEK' );  // zobrazovaný název, např. Iva Vašková
define( 'EKC_THEME', 'elvisek' );  // slug vlastní šablony, '' = web vlastní šablonu nemá
define( 'EKC_LEGACY', array( 'ek' ) ); // staré prefixy dat k migraci: 'ek', 'iv', 'mnd_core'; nový web array()

define( 'EKC_FILE', __FILE__ );
define( 'EKC_BASENAME', plugin_basename( __FILE__ ) );

$ekc_header = get_file_data(
	__FILE__,
	array(
		'version' => 'Version',
		'tested'  => 'Tested up to',
		'uri'     => 'Update URI',
	)
);
define( 'EKC_VERSION', (string) $ekc_header['version'] );
define( 'EKC_TESTED', (string) $ekc_header['tested'] );
define( 'EKC_REPO', trim( (string) preg_replace( '#^https?://github\.com/#i', '', (string) $ekc_header['uri'] ), '/' ) ); // owner/repo
unset( $ekc_header );

/* -------------------------------------------------------------------------
 * Zpětná kompatibilita se staršími šablonami (kontrolují, jestli plugin běží)
 * ---------------------------------------------------------------------- */

if ( in_array( 'mnd_core', EKC_LEGACY, true ) && ! defined( 'MND_CORE_VERSION' ) ) {
	define( 'MND_CORE_VERSION', EKC_VERSION );
}
if ( in_array( 'iv', EKC_LEGACY, true ) && ! defined( 'IV_CORE_VERSION' ) ) {
	define( 'IV_CORE_VERSION', EKC_VERSION );
}
if ( in_array( 'ek', EKC_LEGACY, true ) && ! function_exists( 'ek_client_ip' ) ) {
	/**
	 * Starý název funkce, podle kterého šablona pozná, že plugin běží.
	 *
	 * @return string
	 */
	function ek_client_ip(): string {
		return function_exists( 'ekc_client_ip' ) ? ekc_client_ip() : '0.0.0.0';
	}
}

/* -------------------------------------------------------------------------
 * Moduly z inc/: common, settings, ostatní (projektové site-*.php nakonec), health, updater.
 * Vypnutí modulu = smazání souboru.
 * ---------------------------------------------------------------------- */

if ( ! is_file( __DIR__ . '/inc/common.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p><strong>ElvisEK Core:</strong> chybí soubor <code>inc/common.php</code>, plugin se nenačetl. Nahrajte plugin znovu celý.</p></div>';
			}
		}
	);
	return;
}

$ekc_modules = array();
foreach ( (array) glob( __DIR__ . '/inc/*.php' ) as $ekc_path ) {
	$ekc_modules[ basename( (string) $ekc_path, '.php' ) ] = (string) $ekc_path;
}
ksort( $ekc_modules );

$ekc_first = array( 'common', 'settings' );
$ekc_last  = array( 'health', 'updater' );
$ekc_order = array();
foreach ( $ekc_first as $ekc_name ) {
	$ekc_order[] = $ekc_name;
}
foreach ( array_keys( $ekc_modules ) as $ekc_name ) {
	if ( ! in_array( $ekc_name, $ekc_first, true ) && ! in_array( $ekc_name, $ekc_last, true ) && 0 !== strpos( $ekc_name, 'site-' ) ) {
		$ekc_order[] = $ekc_name;
	}
}
foreach ( array_keys( $ekc_modules ) as $ekc_name ) {
	if ( 0 === strpos( $ekc_name, 'site-' ) ) {
		$ekc_order[] = $ekc_name;
	}
}
foreach ( $ekc_last as $ekc_name ) {
	$ekc_order[] = $ekc_name;
}

foreach ( $ekc_order as $ekc_name ) {
	if ( isset( $ekc_modules[ $ekc_name ] ) ) {
		require_once $ekc_modules[ $ekc_name ];
	}
}
unset( $ekc_modules, $ekc_path, $ekc_first, $ekc_last, $ekc_order, $ekc_name );
