<?php
/**
 * ElvisEK Core — veškerá funkcionalita (načítá elvisek-core.php).
 * Oddělené od hlavního souboru, aby šla bezpečně přeskočit, když je na serveru ještě starý mu-plugin.
 */

defined( 'ABSPATH' ) || exit;


/* =========================================================================
 * 1) Omezení pokusů o přihlášení (nahrazuje reCAPTCHA na loginu)
 *    5 chybných pokusů za 15 min z jedné IP → blokace na 30 min.
 * ====================================================================== */

const EK_LOGIN_MAX_FAILS = 5;
const EK_LOGIN_WINDOW    = 15 * MINUTE_IN_SECONDS;
const EK_LOGIN_LOCKOUT   = 30 * MINUTE_IN_SECONDS;

function ek_client_ip(): string {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

add_filter( 'authenticate', function ( $user, $username ) {
	$key = 'ek_lock_' . md5( ek_client_ip() );
	if ( get_transient( $key ) ) {
		return new WP_Error( 'ek_locked', 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za 30 minut.' );
	}
	return $user;
}, 30, 2 );

add_action( 'wp_login_failed', function ( $username ) {
	$ip        = ek_client_ip();
	$fails_key = 'ek_fails_' . md5( $ip );
	$fails     = (int) get_transient( $fails_key ) + 1;
	set_transient( $fails_key, $fails, EK_LOGIN_WINDOW );
	if ( $fails >= EK_LOGIN_MAX_FAILS ) {
		set_transient( 'ek_lock_' . md5( $ip ), 1, EK_LOGIN_LOCKOUT );
		delete_transient( $fails_key );
	}
	ek_login_log_add( (string) $username, false );
} );

// Chybová hláška neprozradí, jestli existuje uživatel.
add_filter( 'login_errors', function ( $error ) {
	return str_contains( (string) $error, 'Příliš mnoho' ) ? $error : '<strong>Chyba:</strong> nesprávné přihlašovací údaje.';
} );

/* =========================================================================
 * 2) Log přihlášení (nahrazuje simple-login-log) + poslední přihlášení (wp-last-login)
 * ====================================================================== */

const EK_LOGIN_LOG_OPTION = 'ek_login_log';
const EK_LOGIN_LOG_MAX    = 200;

function ek_login_log_add( string $username, bool $success ): void {
	$log   = get_option( EK_LOGIN_LOG_OPTION, array() );
	$log   = is_array( $log ) ? $log : array();
	array_unshift( $log, array(
		't'  => time(),
		'u'  => mb_substr( sanitize_user( $username ), 0, 60 ),
		'ok' => $success,
		'ip' => ek_client_ip(),
		'ua' => mb_substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 160 ),
	) );
	update_option( EK_LOGIN_LOG_OPTION, array_slice( $log, 0, EK_LOGIN_LOG_MAX ), false );
}

add_action( 'wp_login', function ( $login, $user ) {
	update_user_meta( $user->ID, 'ek_last_login', time() );
	delete_transient( 'ek_fails_' . md5( ek_client_ip() ) );
	ek_login_log_add( (string) $login, true );
}, 10, 2 );

add_filter( 'manage_users_columns', function ( $cols ) {
	$cols['ek_last_login'] = 'Poslední přihlášení';
	return $cols;
} );
add_filter( 'manage_users_custom_column', function ( $out, $col, $user_id ) {
	if ( 'ek_last_login' !== $col ) {
		return $out;
	}
	$t = (int) get_user_meta( $user_id, 'ek_last_login', true );
	return $t ? esc_html( wp_date( 'j. n. Y H:i', $t ) ) : '—';
}, 10, 3 );

add_action( 'admin_menu', function () {
	add_management_page( 'Log přihlášení', 'Log přihlášení', 'manage_options', 'ek-login-log', function () {
		$log = get_option( EK_LOGIN_LOG_OPTION, array() );
		echo '<div class="wrap"><h1>Log přihlášení</h1><p>Posledních ' . (int) EK_LOGIN_LOG_MAX . ' pokusů o přihlášení.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Čas</th><th>Uživatel</th><th>Výsledek</th><th>IP</th><th>Prohlížeč</th></tr></thead><tbody>';
		if ( ! $log ) {
			echo '<tr><td colspan="5">Zatím prázdné.</td></tr>';
		}
		foreach ( (array) $log as $row ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><small>%s</small></td></tr>',
				esc_html( wp_date( 'j. n. Y H:i:s', (int) $row['t'] ) ),
				esc_html( $row['u'] ),
				$row['ok'] ? '✅ úspěch' : '❌ chyba',
				esc_html( $row['ip'] ),
				esc_html( $row['ua'] )
			);
		}
		echo '</tbody></table></div>';
	} );
} );

/* =========================================================================
 * 3) Info o serveru v patičce adminu (nahrazuje display-mysql-version, server-ip-memory-usage)
 * ====================================================================== */

add_filter( 'admin_footer_text', function ( $text ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return $text;
	}
	global $wpdb;
	return sprintf(
		'PHP %s · DB %s · IP %s · paměť %s / %s',
		esc_html( PHP_VERSION ),
		esc_html( $wpdb->db_server_info() ),
		esc_html( $_SERVER['SERVER_ADDR'] ?? '?' ),
		esc_html( size_format( memory_get_peak_usage( true ) ) ),
		esc_html( ini_get( 'memory_limit' ) )
	);
} );

/* =========================================================================
 * 4) Další typy souborů v Médiích (nahrazuje wp-extra-file-types)
 * ====================================================================== */

function ek_extra_mimes(): array {
	return array(
		'7z'           => 'application/x-7z-compressed',
		'dmg'          => 'application/x-apple-diskimage',
		'sh'           => 'application/x-sh',
		'bz2'          => 'application/x-bzip2',
		'tgz'          => 'application/x-gzip',
		'txz'          => 'application/x-xz',
		'xz'           => 'application/x-xz',
		'mobileconfig' => 'application/x-apple-aspen-config',
		'py'           => 'text/plain',
		'bin'          => 'application/octet-stream',
		'gbl'          => 'application/octet-stream',
	);
}

add_filter( 'upload_mimes', function ( $mimes ) {
	if ( current_user_can( 'upload_files' ) ) {
		$mimes = array_merge( $mimes, ek_extra_mimes() );
	}
	return $mimes;
} );

// Tolerantní kontrola přípony (binárky/skripty mají často „nečekaný“ MIME).
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
	if ( ! empty( $data['ext'] ) ) {
		return $data;
	}
	$ext   = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$extra = ek_extra_mimes();
	if ( isset( $extra[ $ext ] ) && current_user_can( 'upload_files' ) ) {
		return array( 'ext' => $ext, 'type' => $extra[ $ext ], 'proper_filename' => false );
	}
	return $data;
}, 10, 3 );

/* =========================================================================
 * 5) Automatické aktualizace (nahrazuje Easy Updates Manager — vše automaticky)
 * ====================================================================== */

add_filter( 'allow_major_auto_core_updates', '__return_true' );
add_filter( 'allow_minor_auto_core_updates', '__return_true' );
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	// ElvisEK Core se aktualizuje ručně z GitHub Releases (Nástěnka → Aktualizace).
	return ( isset( $item->plugin ) && EK_CORE_BASENAME === $item->plugin ) ? false : true;
}, 10, 2 );
add_filter( 'auto_update_theme', function ( $update, $item ) {
	// Vlastní šablonu „elvisek“ nikdy neaktualizovat z wordpress.org.
	return ( isset( $item->theme ) && 'elvisek' === $item->theme ) ? false : true;
}, 10, 2 );
add_filter( 'auto_update_translation', '__return_true' );

// Lokální vývoj: žádné aktualizace.
if ( 'local' === wp_get_environment_type() ) {
	add_filter( 'automatic_updater_disabled', '__return_true' );
}

/* =========================================================================
 * 5b) Nové zmenšeniny obrázků jako WebP (originál zůstává)
 * ====================================================================== */

add_filter( 'image_editor_output_format', function ( $formats ) {
	if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
	}
	return $formats;
} );

/* =========================================================================
 * 6) Hardening
 * ====================================================================== */

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' ); // ani system.listMethods
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );

// Nepřihlášeným neukazovat seznam uživatelů přes REST API.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );

// ?author=N neprozradí login.
add_action( 'template_redirect', function () {
	if ( ! is_user_logged_in() && isset( $_GET['author'] ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

/* =========================================================================
 * 7) Údržba webu — Nástroje → Údržba webu (inc/maintenance.php)
 * ====================================================================== */

require __DIR__ . '/maintenance.php';

/* =========================================================================
 * 9) Aktualizace pluginu z GitHub Releases (Update URI → filtr update_plugins_github.com)
 *    Release musí obsahovat soubor elvisek-core.zip (sestavuje GitHub Action).
 * ====================================================================== */

if ( 'local' !== wp_get_environment_type() ) {
	add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
		if ( EK_CORE_BASENAME !== $plugin_file ) {
			return $update;
		}
		$release = get_site_transient( 'ek_core_release' );
		if ( ! is_array( $release ) ) {
			$release  = array();
			$response = wp_remote_get( 'https://api.github.com/repos/elvisek2020/wp-theme_elvisek/releases/latest', array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'elvisek-core-updater' ),
			) );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
					if ( 'elvisek-core.zip' === ( $asset['name'] ?? '' ) ) {
						$release = array(
							'version' => ltrim( (string) $data['tag_name'], 'vV' ),
							'url'     => (string) $data['html_url'],
							'package' => (string) $asset['browser_download_url'],
							'notes'   => (string) ( $data['body'] ?? '' ),
							'date'    => (string) ( $data['published_at'] ?? '' ),
						);
						break;
					}
				}
			}
			set_site_transient( 'ek_core_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		}
		if ( ! $release ) {
			return $update;
		}
		return array(
			'id'           => 'https://github.com/elvisek2020/wp-theme_elvisek',
			'slug'         => 'elvisek-core',
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'tested'       => EK_CORE_TESTED_WP,
			'requires'     => '6.6',
			'requires_php' => '8.1',
		);
	}, 10, 3 );

	// Okno „Zobrazit podrobnosti“ (jinak by WP hledal plugin na wordpress.org).
	add_filter( 'plugins_api', function ( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || 'elvisek-core' !== ( $args->slug ?? '' ) ) {
			return $res;
		}
		$release = get_site_transient( 'ek_core_release' );
		$notes   = is_array( $release ) && ! empty( $release['notes'] ) ? $release['notes'] : 'Viz CHANGELOG na GitHubu.';
		return (object) array(
			'name'          => 'ElvisEK Core',
			'slug'          => 'elvisek-core',
			'version'       => is_array( $release ) ? ( $release['version'] ?? EK_CORE_VERSION ) : EK_CORE_VERSION,
			'author'        => 'Zdeněk Král (ElvisEK)',
			'homepage'      => 'https://github.com/elvisek2020/wp-theme_elvisek',
			'tested'        => EK_CORE_TESTED_WP,
			'requires'      => '6.6',
			'requires_php'  => '8.1',
			'last_updated'  => is_array( $release ) ? ( $release['date'] ?? '' ) : '',
			'download_link' => is_array( $release ) ? ( $release['package'] ?? '' ) : '',
			'sections'      => array(
				'description' => 'Funkce webu elvisek.cz nezávislé na šabloně: ochrana a log přihlášení, hardening, automatické aktualizace, WebP, údržba databáze.',
				'changelog'   => '<pre style="white-space:pre-wrap">' . esc_html( $notes ) . '</pre><p><a href="https://github.com/elvisek2020/wp-theme_elvisek/blob/main/CHANGELOG.md" target="_blank">Celý CHANGELOG</a></p>',
			),
		);
	}, 10, 3 );

	add_action( 'load-update-core.php', function () {
		if ( isset( $_GET['force-check'] ) ) {
			delete_site_transient( 'ek_core_release' );
		}
	} );
}
