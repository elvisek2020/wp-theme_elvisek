<?php
/**
 * EKC module: admin-info
 * Version: 1.0.0
 * Requires: common
 * Replaces: display-mysql-version, server-ip-memory-usage
 * Summary: Verze PHP a databáze, IP serveru a využití paměti v patičce administrace.
 */

defined( 'ABSPATH' ) || exit;

ekc_settings_register(
	'admin_footer',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Info o serveru v patičce',
		'help'    => 'Verze PHP a databáze, IP serveru a využití paměti v patičce administrace (jen pro administrátory).',
		'section' => 'Administrace',
	)
);

/**
 * IP adresa serveru (SERVER_ADDR, na IIS LOCAL_ADDR), jen platná adresa.
 *
 * @return string IP, nebo '' když ji server neposílá.
 */
function ekc_admin_info_server_ip(): string {
	foreach ( array( 'SERVER_ADDR', 'LOCAL_ADDR' ) as $key ) {
		$ip = isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : '';
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
	}
	return '';
}

/**
 * Údaje o serveru za původním textem patičky (jen administrátorům).
 *
 * @param string $text Text patičky.
 * @return string
 */
function ekc_admin_info_footer( $text ) {
	if ( ! ekc_opt( 'admin_footer' ) || ! current_user_can( 'manage_options' ) ) {
		return $text;
	}
	global $wpdb;
	$parts = array(
		'PHP ' . PHP_VERSION,
		'DB ' . $wpdb->db_server_info(),
	);
	$ip = ekc_admin_info_server_ip();
	if ( '' !== $ip ) {
		$parts[] = 'IP ' . $ip;
	}
	$parts[] = 'paměť ' . size_format( memory_get_peak_usage( true ) ) . ' / ' . ini_get( 'memory_limit' );

	$info = '<span class="ekc-admin-info">' . esc_html( implode( ' · ', $parts ) ) . '</span>';
	return '' !== trim( wp_strip_all_tags( (string) $text ) ) ? $text . ' · ' . $info : $info;
}
add_filter( 'admin_footer_text', 'ekc_admin_info_footer', 20 );
