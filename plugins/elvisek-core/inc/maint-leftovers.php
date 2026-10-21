<?php
/**
 * EKC module: maint-leftovers
 * Version: 1.1.0
 * Requires: common
 * Replaces: plugins-garbage-collector, advanced-database-cleaner
 * Summary: Pozůstatky odebraných pluginů (tabulky, volby, metadata, role, složky) přesune do karantény se zálohou.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Sekce Pozůstatky pluginů v Nástroje → Údržba webu. Ukáže jen to, co na webu opravdu zůstalo
 * a patří pluginu (nebo šabloně), který není aktivní. „Do karantény“ nic nemaže:
 *  - volby, metadata a role uloží do JSON v karanténě a pak je odebere,
 *  - tabulky přejmenuje na {prefix}ekq_… (data zůstanou v databázi, starší mndq_ se rozpozná),
 *  - složky ve wp-content přesune do karantény (složku cache jen tehdy, když ji nepoužívá
 *    jiný aktivní plugin pro cache).
 * Trvalé smazání je v sekci Karanténa (common).
 *
 * Definice: filtr ekc_leftover_definitions( array $defs ). Projektové pozůstatky (stará šablona,
 * pole, role) si web přidá v inc/site-*.php:
 *
 *   add_filter( 'ekc_leftover_definitions', function ( array $defs ): array {
 *       $defs['old-theme'] = array(
 *           'name'    => 'Původní šablona',
 *           'theme'   => 'stara-sablona',
 *           'options' => array( 'theme_mods_stara-sablona' ),
 *           'paths'   => array( 'themes/stara-sablona' ),
 *       );
 *       return $defs;
 *   } );
 *
 * Klíče definice: name, plugin (soubor nebo pole souborů), theme (slug nebo pole), tables
 * (bez prefixu), options, postmeta, usermeta (vzory, % = cokoli), paths (glob relativně
 * k wp-content), cache_paths (jako paths, ale jen když neběží jiná cache), roles (jen bez
 * uživatelů), requires (callable – dokud nevrátí true, pozůstatek se nenabízí, data se převádějí).
 */

/**
 * Obecné definice pozůstatků známých pluginů z wordpress.org.
 *
 * @return array<string,array>
 */
function ekc_leftovers_builtin(): array {
	return array(
		'aios'                 => array(
			'name'    => 'All-In-One Security (AIOS)',
			'plugin'  => 'all-in-one-wp-security-and-firewall/wp-security.php',
			'tables'  => array( 'aiowps_%' ),
			'options' => array( 'aio_wp_security_configs', 'aiowpsec_%', 'aios_%', 'updraft_lock_aios_%' ),
			'paths'   => array( 'aiowps_backups', 'uploads/aios' ),
		),
		'wordfence'            => array(
			'name'     => 'Wordfence',
			'plugin'   => 'wordfence/wordfence.php',
			// Wordfence pojmenovává tabulky velkými písmeny (wfConfig, wfHits…).
			'tables'   => array( 'wfls_%', 'wfBlock%', 'wfConfig', 'wfCrawlers', 'wfFileChanges', 'wfFileMods', 'wfHits', 'wfHoover', 'wfIssues', 'wfKnownFileList', 'wfLeechers', 'wfLiveTrafficHuman', 'wfLocs', 'wfLogins', 'wfNet404s', 'wfNotifications', 'wfPendingIssues', 'wfReverseCache', 'wfScanners', 'wfSecurityEvents', 'wfSNIPCache', 'wfStatus', 'wfThrottleLog', 'wfTrafficRates', 'wfVulnScanners', 'wfAuditEvents' ),
			'options'  => array( 'wordfence%', 'wf_plugin_act_error' ),
			'paths'    => array( 'wflogs' ),
		),
		'cerber'               => array(
			'name'    => 'WP Cerber Security',
			'plugin'  => 'wp-cerber/wp-cerber.php',
			'tables'  => array( 'cerber_%' ),
			'options' => array( 'cerber_%', 'cerber-%', '_cerber_%' ),
		),
		'limit-login-attempts' => array(
			'name'    => 'Limit Login Attempts',
			'plugin'  => array( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php', 'limit-login-attempts/limit-login-attempts.php' ),
			'options' => array( 'limit_login_%', 'llar_%' ),
		),
		'simple-login-log'     => array(
			'name'    => 'Simple Login Log',
			'plugin'  => 'simple-login-log/simple-login-log.php',
			'tables'  => array( 'simple_login_log' ),
			'options' => array( 'sll_%', 'simple_login_log%' ),
		),
		'backupwordpress'      => array(
			'name'    => 'BackUpWordPress',
			'plugin'  => 'backupwordpress/backupwordpress.php',
			'options' => array( 'hmbkp_%', '_transient_hmbkp_%', '_transient_timeout_hmbkp_%' ),
			'paths'   => array( 'backupwordpress-*-backups' ),
		),
		'updraftplus'          => array(
			'name'    => 'UpdraftPlus',
			'plugin'  => 'updraftplus/updraftplus.php',
			'options' => array( 'updraft_%', 'updraftplus_%', 'updraftplus-%' ),
			'paths'   => array( 'updraft' ),
		),
		'backwpup'             => array(
			'name'    => 'BackWPup',
			'plugin'  => 'backwpup/backwpup.php',
			'options' => array( 'backwpup%' ),
			'paths'   => array( 'uploads/backwpup-*' ),
		),
		'duplicator'           => array(
			'name'    => 'Duplicator',
			'plugin'  => array( 'duplicator/duplicator.php', 'duplicator-pro/duplicator-pro.php' ),
			'tables'  => array( 'duplicator_%' ),
			'options' => array( 'duplicator_%' ),
			'paths'   => array( 'backups-dup-lite', 'backups-dup-pro' ),
		),
		'ai1wm'                => array(
			'name'    => 'All-in-One WP Migration',
			'plugin'  => 'all-in-one-wp-migration/all-in-one-wp-migration.php',
			'options' => array( 'ai1wm_%' ),
			'paths'   => array( 'ai1wm-backups' ),
		),
		'ithemes-security'     => array(
			'name'    => 'Solid Security (iThemes Security)',
			'plugin'  => array( 'better-wp-security/better-wp-security.php', 'ithemes-security-pro/ithemes-security-pro.php' ),
			'tables'  => array( 'itsec_%' ),
			'options' => array( 'itsec%' ),
			'paths'   => array( 'uploads/ithemes-security' ),
		),
		'ai-engine'            => array(
			'name'    => 'AI Engine',
			'plugin'  => array( 'ai-engine/ai-engine.php', 'ai-engine-pro/ai-engine-pro.php' ),
			'tables'  => array( 'mwai_%' ),
			'options' => array( 'mwai%' ),
		),
		'wpbenchmark'          => array(
			'name'    => 'WPBenchmark',
			'plugin'  => 'wpbenchmark/wpbenchmark.php',
			'options' => array( 'wpbenchmark%' ),
		),
		'eum'                  => array(
			'name'    => 'Easy Updates Manager',
			'plugin'  => 'stops-core-theme-and-plugin-updates/main.php',
			'tables'  => array( 'eum_%' ),
			'options' => array( 'MPSUM', 'eum_%' ),
		),
		'yoast'                => array(
			'name'     => 'Yoast SEO',
			'plugin'   => array( 'wordpress-seo/wp-seo.php', 'wordpress-seo-premium/wp-seo-premium.php' ),
			'tables'   => array( 'yoast_%' ),
			'options'  => array( 'wpseo', 'wpseo_%', 'yoast_%' ),
			'postmeta' => array( '_yoast_wpseo_%' ),
			'usermeta' => array( 'wpseo_%', '_yoast_wpseo_%' ),
		),
		'monsterinsights'      => array(
			'name'    => 'MonsterInsights',
			'plugin'  => 'google-analytics-for-wordpress/googleanalytics.php',
			'tables'  => array( 'monsterinsights_%' ),
			'options' => array( 'monsterinsights_%', 'widget_monsterinsights%', '_transient_monsterinsights_%', '_transient_timeout_monsterinsights_%', '_transient__monsterinsights_%', '_transient_timeout__monsterinsights_%', '_site_transient_monsterinsights_%', '_site_transient_timeout_monsterinsights_%' ),
		),
		'cookie-law-info'      => array(
			'name'    => 'CookieYes (Cookie Law Info)',
			'plugin'  => 'cookie-law-info/cookie-law-info.php',
			'tables'  => array( 'cli_%', 'cky_%' ),
			'options' => array( 'cookielawinfo%', 'CookieLawInfo-%', 'cli_%', 'cky_%', 'wt_cli_%' ),
		),
		'supsystic-social'     => array(
			'name'   => 'Social Share Buttons by Supsystic',
			'plugin' => array( 'social-share-buttons-by-supsystic/index.php', 'xxx-social-share-buttons-by-supsystic/index.php' ),
			'tables' => array( 'supsystic_ss_%' ),
		),
		'photo-gallery'        => array(
			'name'    => 'Photo Gallery (10Web)',
			'plugin'  => 'photo-gallery/photo-gallery.php',
			'tables'  => array( 'bwg_%' ),
			'options' => array( 'wd_bwg_%', 'bwg_%' ),
		),
		'wp-ulike'             => array(
			'name'    => 'WP ULike',
			'plugin'  => 'wp-ulike/wp-ulike.php',
			'tables'  => array( 'ulike', 'ulike_%' ),
			'options' => array( 'wp_ulike_%', 'widget_wp_ulike%' ),
		),
		'lightbox'             => array(
			'name'    => 'Huge IT Lightbox',
			'plugin'  => 'lightbox/lightbox.php',
			'options' => array( 'hugeit_lightbox_%', 'lightbox_open_close_effect' ),
		),
		'responsive-lightbox'  => array(
			'name'    => 'Responsive Lightbox',
			'plugin'  => 'responsive-lightbox/responsive-lightbox.php',
			'options' => array( 'responsive_lightbox_%' ),
		),
		'admin-menu-editor'    => array(
			'name'    => 'Admin Menu Editor',
			'plugin'  => 'admin-menu-editor/menu-editor.php',
			'options' => array( 'ws_menu_editor', 'ws_menu_editor_%', 'ws_ame_%' ),
		),
		'admin-columns'        => array(
			'name'    => 'Admin Columns',
			'plugin'  => 'codepress-admin-columns/codepress-admin-columns.php',
			'tables'  => array( 'admin_columns' ),
			'options' => array( 'cpac_%', 'ac_version', 'ac_capabilities_set' ),
		),
		'scpo'                 => array(
			'name'    => 'Simple Custom Post Order',
			'plugin'  => 'simple-custom-post-order/simple-custom-post-order.php',
			'options' => array( 'scporder_%' ),
		),
		'zalomeni'             => array(
			'name'    => 'Zalomení',
			'plugin'  => 'zalomeni/zalomeni.php',
			'options' => array( 'zalomeni_%' ),
		),
		'w3tc'                 => array(
			'name'        => 'W3 Total Cache',
			'plugin'      => 'w3-total-cache/w3-total-cache.php',
			'options'     => array( 'w3tc_%' ),
			'paths'       => array( 'w3tc-config' ),
			'cache_paths' => array( 'cache' ),
		),
	);
}

/**
 * Chráněné vzory: tabulky, volby a metadata ponechaných pluginů a samotného pluginu se nikdy
 * nenabízejí, ani když na ně omylem ukáže příliš široká definice. Výchozí: ManageWP (worker),
 * data tohoto pluginu a karanténní tabulky. Web přidá vlastní filtrem ekc_leftovers_protected.
 *
 * @return string[] Vzory LIKE (% = cokoli).
 */
function ekc_leftovers_protected(): array {
	return array_values(
		array_unique(
			(array) apply_filters(
				'ekc_leftovers_protected',
				array( 'mwp_%', 'mwp-%', 'worker_%', '_worker_%', 'managewp%', 'ekc_%', '_ekc_%', 'ekq_%', 'mndq_%' )
			)
		)
	);
}

/**
 * Je název chráněný?
 *
 * @param string $name Název tabulky (bez prefixu), volby nebo meta klíče.
 * @return bool
 */
function ekc_leftovers_is_protected( string $name ): bool {
	foreach ( ekc_leftovers_protected() as $pattern ) {
		if ( preg_match( ekc_leftovers_regex( (string) $pattern ), $name ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Všechny definice (obecné + projektové z filtru), doplněné o výchozí klíče.
 *
 * @return array<string,array>
 */
function ekc_leftovers_definitions(): array {
	$defs = (array) apply_filters( 'ekc_leftover_definitions', ekc_leftovers_builtin() );
	$out  = array();
	foreach ( $defs as $slug => $def ) {
		if ( ! is_array( $def ) || '' === (string) $slug ) {
			continue;
		}
		$out[ (string) $slug ] = array_merge(
			array(
				'name'        => (string) $slug,
				'plugin'      => array(),
				'theme'       => array(),
				'tables'      => array(),
				'options'     => array(),
				'postmeta'    => array(),
				'usermeta'    => array(),
				'paths'       => array(),
				'cache_paths' => array(),
				'roles'       => array(),
				'requires'    => null,
			),
			$def
		);
	}
	return $out;
}

/**
 * Vzor LIKE (jen % jako zástupný znak) → regulární výraz bez ohledu na velikost písmen.
 *
 * @param string $pattern Vzor.
 * @return string
 */
function ekc_leftovers_regex( string $pattern ): string {
	return '/^' . str_replace( '%', '.*', preg_quote( $pattern, '/' ) ) . '$/i';
}

/**
 * Vzor LIKE pro SQL (podtržítko doslova, % jako zástupný znak).
 *
 * @param string $pattern Vzor.
 * @return string
 */
function ekc_leftovers_like( string $pattern ): string {
	global $wpdb;
	return str_replace( '\\%', '%', $wpdb->esc_like( $pattern ) );
}

/**
 * Běží na webu cache, která používá wp-content/cache?
 *
 * @return bool
 */
function ekc_leftovers_cache_active(): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$active = ( defined( 'WP_CACHE' ) && WP_CACHE ) || is_file( WP_CONTENT_DIR . '/advanced-cache.php' );
	foreach ( array( 'wp-super-cache/wp-cache.php', 'wp-fastest-cache/wpFastestCache.php', 'litespeed-cache/litespeed-cache.php', 'cache-enabler/cache-enabler.php', 'breeze/breeze.php', 'wp-rocket/wp-rocket.php', 'sg-cachepress/sg-cachepress.php', 'wp-optimize/wp-optimize.php', 'comet-cache/comet-cache.php', 'hummingbird-performance/wp-hummingbird.php', 'autoptimize/autoptimize.php' ) as $plugin ) {
		$active = $active || is_plugin_active( $plugin );
	}
	return (bool) apply_filters( 'ekc_leftovers_cache_active', $active );
}

/**
 * Je plugin nebo šablona pozůstatku v provozu (nebo se jeho data teprve převádějí)?
 *
 * @param array $def Definice.
 * @return bool
 */
function ekc_leftovers_in_use( array $def ): bool {
	if ( null !== $def['requires'] && ( ! is_callable( $def['requires'] ) || ! call_user_func( $def['requires'] ) ) ) {
		return true;
	}
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( (array) $def['plugin'] as $plugin ) {
		if ( is_plugin_active( (string) $plugin ) ) {
			return true;
		}
	}
	return (bool) array_intersect( (array) $def['theme'], array( get_template(), get_stylesheet() ) );
}

/**
 * Stav pluginu pozůstatku pro popisek: vypnutý (soubory jsou na disku), nebo odebraný.
 *
 * @param array $def Definice.
 * @return string
 */
function ekc_leftovers_state( array $def ): string {
	foreach ( (array) $def['plugin'] as $plugin ) {
		if ( is_file( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			return 'plugin je vypnutý, ale nesmazaný';
		}
	}
	foreach ( (array) $def['theme'] as $theme ) {
		if ( is_dir( get_theme_root() . '/' . $theme ) ) {
			return 'šablona je na disku';
		}
	}
	return '';
}

/**
 * Seznam tabulek a voleb webu (načte se jednou za požadavek).
 *
 * @return array{tables:array<string,int>,options:array<string,int>}
 */
function ekc_leftovers_catalog(): array {
	static $catalog = null;
	global $wpdb;
	if ( null === $catalog ) {
		$catalog = array(
			'tables'  => array(),
			'options' => array(),
		);
		$skip = array_merge( array( EKC_QUARANTINE_TABLE ), EKC_QUARANTINE_LEGACY );
		foreach ( ekc_db_tables() as $table ) {
			$rest = substr( (string) $table->t, strlen( $wpdb->prefix ) );
			foreach ( $skip as $q ) {
				if ( 0 === stripos( $rest, $q ) ) {
					continue 2; // už v karanténě
				}
			}
			$catalog['tables'][ $rest ] = (int) $table->d + (int) $table->i;
		}
		foreach ( (array) $wpdb->get_results( "SELECT option_name AS n, LENGTH(option_value) AS b FROM {$wpdb->options}" ) as $row ) {
			$catalog['options'][ (string) $row->n ] = (int) $row->b;
		}
	}
	return $catalog;
}

/**
 * Co z pozůstatku na webu skutečně je.
 *
 * @param array $def Definice.
 * @return array{tables:array<string,int>,options:array<string,int>,postmeta:array<string,int>,usermeta:array<string,int>,paths:array<string,int>,roles:array<string,int>,kept:string[]}
 */
function ekc_leftovers_scan( array $def ): array {
	global $wpdb;
	$catalog = ekc_leftovers_catalog();
	$found   = array(
		'tables'   => array(),
		'options'  => array(),
		'postmeta' => array(),
		'usermeta' => array(),
		'paths'    => array(),
		'roles'    => array(),
		'kept'     => array(),
	);

	foreach ( (array) $def['tables'] as $pattern ) {
		$re = ekc_leftovers_regex( (string) $pattern );
		foreach ( $catalog['tables'] as $rest => $bytes ) {
			if ( preg_match( $re, (string) $rest ) && ! ekc_leftovers_is_protected( (string) $rest ) ) {
				$found['tables'][ $wpdb->prefix . $rest ] = $bytes;
			}
		}
	}
	foreach ( (array) $def['options'] as $pattern ) {
		$re = ekc_leftovers_regex( (string) $pattern );
		foreach ( $catalog['options'] as $name => $bytes ) {
			if ( preg_match( $re, (string) $name ) && ! ekc_leftovers_is_protected( (string) $name ) ) {
				$found['options'][ $name ] = $bytes;
			}
		}
	}
	foreach ( array( 'postmeta' => $wpdb->postmeta, 'usermeta' => $wpdb->usermeta ) as $kind => $table ) {
		foreach ( (array) $def[ $kind ] as $pattern ) {
			if ( ekc_leftovers_is_protected( (string) $pattern ) ) {
				continue; // vzor sám míří do chráněných klíčů
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z $wpdb
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(SUM(LENGTH(meta_value)), 0) AS b FROM {$table} WHERE meta_key LIKE %s", ekc_leftovers_like( (string) $pattern ) ) );
			if ( $row && (int) $row->n ) {
				$found[ $kind ][ (string) $pattern ] = (int) $row->n;
			}
		}
	}
	$cache_active = null;
	foreach ( array( 'paths', 'cache_paths' ) as $key ) {
		foreach ( (array) $def[ $key ] as $pattern ) {
			foreach ( (array) glob( WP_CONTENT_DIR . '/' . ltrim( (string) $pattern, '/' ), GLOB_ONLYDIR ) as $path ) {
				if ( ! $path || ! is_dir( $path ) ) {
					continue;
				}
				if ( 'cache_paths' === $key ) {
					$cache_active = $cache_active ?? ekc_leftovers_cache_active();
					if ( $cache_active ) {
						$found['kept'][] = $path; // složku používá jiná cache
						continue;
					}
				}
				$found['paths'][ $path ] = ekc_path_size( $path );
			}
		}
	}
	foreach ( (array) $def['roles'] as $role ) {
		if ( get_role( (string) $role ) && ! get_users(
			array(
				'role'   => (string) $role,
				'fields' => 'ID',
				'number' => 1,
			)
		) ) {
			$found['roles'][ (string) $role ] = 0;
		}
	}
	return $found;
}

/**
 * Je co přesouvat?
 *
 * @param array $found Výsledek ekc_leftovers_scan().
 * @return bool
 */
function ekc_leftovers_has( array $found ): bool {
	return (bool) ( $found['tables'] || $found['options'] || $found['postmeta'] || $found['usermeta'] || $found['paths'] || $found['roles'] );
}

/**
 * Cílový název tabulky v karanténě: {prefix}ekq_{zbytek}; když je delší než 64 znaků nebo už
 * existuje, zkrátí se a doplní krátkým hashem (žádné kolize zkrácených názvů).
 *
 * @param string $table Původní název.
 * @return string
 */
function ekc_leftovers_table_target( string $table ): string {
	global $wpdb;
	$target = $wpdb->prefix . EKC_QUARANTINE_TABLE . substr( $table, strlen( $wpdb->prefix ) );
	$exists = static fn( string $name ): bool => (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s', DB_NAME, $name ) );
	if ( strlen( $target ) <= 64 && ! $exists( $target ) ) {
		return $target;
	}
	for ( $i = 0; $i < 20; $i++ ) {
		$candidate = substr( $target, 0, 56 ) . '_' . substr( md5( $table . '|' . $i ), 0, 7 );
		if ( ! $exists( $candidate ) ) {
			return $candidate;
		}
	}
	return substr( $target, 0, 52 ) . '_' . substr( md5( uniqid( $table, true ) ), 0, 11 );
}

/**
 * Uloží zálohu do JSON v karanténě.
 *
 * @param string $dir  Složka (s lomítkem).
 * @param string $name Název bez přípony.
 * @param array  $data Data.
 * @return bool
 */
function ekc_leftovers_backup( string $dir, string $name, array $data ): bool {
	$file = $dir . $name . '-' . gmdate( 'His' ) . '.json';
	// phpcs:ignore WordPress.WP.AlternativeFunctions
	return false !== file_put_contents( $file, (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
}

/**
 * Úloha Údržby: pozůstatek do karantény (item = klíč definice). Vše se zjišťuje znovu na serveru.
 *
 * @param string $item Klíč definice.
 * @return array
 */
function ekc_leftovers_task_quarantine( string $item ): array {
	global $wpdb;
	$defs = ekc_leftovers_definitions();
	if ( ! isset( $defs[ $item ] ) ) {
		return array(
			'message' => 'Neznámá položka.',
			'type'    => 'error',
		);
	}
	$def = $defs[ $item ];
	if ( ekc_leftovers_in_use( $def ) ) {
		return array(
			'message' => $def['name'] . ': plugin nebo šablona je pořád aktivní (nebo se data teprve převádějí) – nejdřív ji vypněte.',
			'type'    => 'warning',
			'mark'    => false,
		);
	}
	$found = ekc_leftovers_scan( $def );
	if ( ! ekc_leftovers_has( $found ) ) {
		return array(
			'message' => $def['name'] . ': nic k přesunu.',
			'type'    => 'info',
			'mark'    => false,
		);
	}
	$group = trim( (string) preg_replace( '/[^A-Za-z0-9._-]+/', '-', $item ), '-.' );
	$dir   = ekc_quarantine_dir( wp_date( 'Y-m-d' ) . '/' . ( '' !== $group ? $group : 'pozustatky' ) );
	if ( is_wp_error( $dir ) ) {
		return array(
			'message' => $dir->get_error_message(),
			'type'    => 'error',
		);
	}
	$done = array();

	// Role a metadata: záloha, pak odebrat.
	if ( $found['roles'] || $found['postmeta'] || $found['usermeta'] ) {
		$backup = array(
			'roles'    => array(),
			'postmeta' => array(),
			'usermeta' => array(),
		);
		foreach ( array_keys( $found['roles'] ) as $role ) {
			$backup['roles'][ $role ] = get_role( $role ) ? get_role( $role )->capabilities : array();
		}
		foreach ( array( 'postmeta' => array( $wpdb->postmeta, 'post_id' ), 'usermeta' => array( $wpdb->usermeta, 'user_id' ) ) as $kind => $t ) {
			foreach ( array_keys( $found[ $kind ] ) as $pattern ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- názvy z $wpdb
				$backup[ $kind ][ $pattern ] = $wpdb->get_results( $wpdb->prepare( "SELECT {$t[1]} AS id, meta_key, meta_value FROM {$t[0]} WHERE meta_key LIKE %s", ekc_leftovers_like( $pattern ) ), ARRAY_A );
			}
		}
		if ( ! ekc_leftovers_backup( $dir, 'role-metadata', $backup ) ) {
			return array(
				'message' => 'Zálohu rolí a metadat se nepodařilo uložit – nic se nezměnilo.',
				'type'    => 'error',
			);
		}
		foreach ( array_keys( $found['roles'] ) as $role ) {
			remove_role( $role );
		}
		foreach ( array( 'postmeta' => $wpdb->postmeta, 'usermeta' => $wpdb->usermeta ) as $kind => $table ) {
			foreach ( array_keys( $found[ $kind ] ) as $pattern ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE meta_key LIKE %s", ekc_leftovers_like( $pattern ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		if ( $found['postmeta'] || $found['usermeta'] ) {
			wp_cache_flush_group( 'post_meta' );
			wp_cache_flush_group( 'user_meta' );
		}
		if ( $found['roles'] ) {
			$done[] = 'role: ' . implode( ', ', array_keys( $found['roles'] ) );
		}
		$meta = array_sum( $found['postmeta'] ) + array_sum( $found['usermeta'] );
		if ( $meta ) {
			$done[] = ekc_plural( $meta, '%d metadata', '%d metadata', '%d metadat' );
		}
	}

	// Volby: záloha (s autoloadem), pak odebrat.
	if ( $found['options'] ) {
		$backup = array();
		foreach ( array_keys( $found['options'] ) as $name ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ), ARRAY_A );
			if ( $row ) {
				$backup[ $name ] = $row;
			}
		}
		if ( ! ekc_leftovers_backup( $dir, 'volby', $backup ) ) {
			return array(
				'message' => 'Zálohu voleb se nepodařilo uložit – nic se nezměnilo.',
				'type'    => 'error',
			);
		}
		foreach ( array_keys( $backup ) as $name ) {
			delete_option( $name );
		}
		$done[] = ekc_plural( count( $backup ), '%d volba', '%d volby', '%d voleb' );
	}

	// Tabulky: přejmenovat do karantény (názvy přesně tak, jak jsou v databázi, včetně velkých písmen).
	$renamed = 0;
	$failed  = array();
	foreach ( array_keys( $found['tables'] ) as $table ) {
		$target = ekc_leftovers_table_target( $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- názvy tabulek ze serveru
		if ( false !== $wpdb->query( 'RENAME TABLE `' . str_replace( '`', '', $table ) . '` TO `' . str_replace( '`', '', $target ) . '`' ) ) {
			++$renamed;
		} else {
			$failed[] = $table;
		}
	}
	if ( $renamed ) {
		$done[] = ekc_plural( $renamed, '%d tabulka', '%d tabulky', '%d tabulek' );
	}

	// Složky: přesunout.
	$moved = 0;
	foreach ( array_keys( $found['paths'] ) as $path ) {
		if ( ekc_quarantine_move( $path, '' !== $group ? $group : 'pozustatky' ) ) {
			++$moved;
		} else {
			$failed[] = str_replace( WP_CONTENT_DIR, 'wp-content', $path );
		}
	}
	if ( $moved ) {
		$done[] = ekc_plural( $moved, '%d složka', '%d složky', '%d složek' );
	}

	$msg = sprintf( '%1$s – do karantény přesunuto: %2$s.', $def['name'], $done ? implode( ', ', $done ) : 'nic' );
	if ( $found['kept'] ) {
		$msg .= ' Ponecháno (používá jiná cache): ' . implode( ', ', array_map( static fn( $p ) => str_replace( WP_CONTENT_DIR, 'wp-content', $p ), $found['kept'] ) ) . '.';
	}
	if ( $failed ) {
		$msg .= ' Nepodařilo se: ' . implode( ', ', $failed ) . '.';
	}
	return array(
		'message' => $msg,
		'type'    => $failed ? 'warning' : 'success',
	);
}

/**
 * Sekce Údržby: Pozůstatky pluginů.
 *
 * @param string $id ID sekce.
 */
function ekc_leftovers_render( string $id ): void {
	$rows = array();
	foreach ( ekc_leftovers_definitions() as $slug => $def ) {
		if ( ekc_leftovers_in_use( $def ) ) {
			continue;
		}
		$found = ekc_leftovers_scan( $def );
		if ( ekc_leftovers_has( $found ) ) {
			$rows[ $slug ] = array( $def, $found );
		}
	}
	if ( ! $rows ) {
		echo '<p>' . ekc_status_dot( 'ok' ) . ' Po odebraných pluginech nic nezůstalo.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	echo '<p>Tabulky, volby, metadata, role a složky pluginů (a starých šablon), které na webu nejsou aktivní. „Do karantény“ nic nemaže: volby, metadata a role uloží do JSON v karanténě, tabulky přejmenuje a složky přesune. Samotný plugin smažte v Pluginy.</p>';
	echo '<table class="widefat striped" style="max-width:960px"><tbody>';
	foreach ( $rows as $slug => $row ) {
		list( $def, $found ) = $row;
		$parts = array();
		if ( $found['tables'] ) {
			$parts[] = sprintf( 'tabulky: %1$s (%2$s)', implode( ', ', array_keys( $found['tables'] ) ), ekc_size( array_sum( $found['tables'] ) ) );
		}
		if ( $found['options'] ) {
			$parts[] = sprintf( 'volby: %1$d (%2$s)', count( $found['options'] ), ekc_size( array_sum( $found['options'] ) ) );
		}
		if ( $found['postmeta'] || $found['usermeta'] ) {
			$parts[] = sprintf( 'metadata: %d', array_sum( $found['postmeta'] ) + array_sum( $found['usermeta'] ) );
		}
		if ( $found['paths'] ) {
			$parts[] = sprintf( 'složky: %1$s (%2$s)', implode( ', ', array_map( static fn( $p ) => str_replace( WP_CONTENT_DIR, 'wp-content', $p ), array_keys( $found['paths'] ) ) ), ekc_size( array_sum( $found['paths'] ) ) );
		}
		if ( $found['roles'] ) {
			$parts[] = 'role bez uživatelů: ' . implode( ', ', array_keys( $found['roles'] ) );
		}
		if ( $found['kept'] ) {
			$parts[] = 'ponechá: ' . implode( ', ', array_map( static fn( $p ) => str_replace( WP_CONTENT_DIR, 'wp-content', $p ), $found['kept'] ) ) . ' (používá jiná cache)';
		}
		$state = ekc_leftovers_state( $def );
		printf(
			'<tr><td><strong>%1$s</strong>%2$s<br><span class="description">%3$s</span></td><td style="width:150px">',
			esc_html( (string) $def['name'] ),
			'' !== $state ? ' <span class="description">(' . esc_html( $state ) . ')</span>' : '',
			esc_html( implode( ' · ', $parts ) )
		);
		ekc_maintenance_button( $id, 'quarantine', 'Do karantény', sprintf( 'Přesunout pozůstatky „%s“ do karantény?', $def['name'] ), false, (string) $slug );
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

ekc_maintenance_section(
	'leftovers',
	array(
		'title'  => 'Pozůstatky pluginů',
		'order'  => 40,
		'render' => 'ekc_leftovers_render',
		'tasks'  => array(
			'quarantine' => 'ekc_leftovers_task_quarantine',
		),
	)
);
