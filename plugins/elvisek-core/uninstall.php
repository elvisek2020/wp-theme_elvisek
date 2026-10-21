<?php
/**
 * Odinstalace pluginu: smaže nastavení a data pluginu (options ekc_*, transienty, user meta ekc_*,
 * postmeta _ekc_*).
 *
 * Karanténa se NEMAŽE: složka SLUG-karantena (mimo web nebo ve wp-content) a tabulky {prefix}ekq_*
 * zůstávají, aby o nic nepřišel ten, kdo plugin odinstaluje omylem. Smazat je jde předtím
 * v Nástroje → Údržba webu → Karanténa, nebo ručně.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$ekc_like = static fn( string $prefix ): string => $wpdb->esc_like( $prefix ) . '%';

// Options včetně transientů (_transient_ekc_*, _transient_timeout_ekc_*) a site transientů.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$ekc_like( 'ekc_' ),
		$ekc_like( '_transient_ekc_' ),
		$ekc_like( '_transient_timeout_ekc_' ),
		$ekc_like( '_site_transient_ekc_' ),
		$ekc_like( '_site_transient_timeout_ekc_' )
	)
);
if ( is_multisite() ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			$ekc_like( '_site_transient_ekc_' ),
			$ekc_like( '_site_transient_timeout_ekc_' )
		)
	);
}

// User meta (ekc_last_login, …) a postmeta (_ekc_webp_fail, …).
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $ekc_like( 'ekc_' ) ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $ekc_like( '_ekc_' ) ) );

wp_cache_flush();
