<?php
/**
 * Plugin Name: ElvisEK Core
 * Plugin URI: https://github.com/elvisek2020/wp-theme_elvisek
 * Description: Funkce webu nezávislé na šabloně — ochrana přihlášení, log přihlášení, poslední přihlášení, info v adminu, další typy souborů, automatické aktualizace, hardening, nástroje pro přechod a údržbu. Nahrazuje 7 pluginů.
 * Version: 0.3.1
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: Zdeněk Král (ElvisEK)
 * Update URI: https://github.com/elvisek2020/wp-theme_elvisek
 * Text Domain: elvisek-core
 */

defined( 'ABSPATH' ) || exit;

// Starý mu-plugin (verze do 0.2.0) je ještě na serveru → nenačítat podruhé.
if ( function_exists( 'ek_client_ip' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-warning"><p><strong>ElvisEK Core:</strong> na serveru je ještě starý <code>wp-content/mu-plugins/elvisek-core.php</code>. Smažte ho přes FTP — pak začne fungovat tento plugin (včetně aktualizací).</p></div>';
	} );
	return;
}

define( 'EK_CORE_FILE', __FILE__ );
define( 'EK_CORE_BASENAME', plugin_basename( __FILE__ ) );

require __DIR__ . '/inc/core.php';
