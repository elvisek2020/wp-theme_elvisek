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
 * 7) Údržba ElvisEK (dříve Přechod) — Nástroje → Údržba ElvisEK
 *    a) migrace [php], [su_spoiler] a bloků SyntaxHighlighter na nativní HTML/bloky
 *       (náhled → provést → případně vrátit; záloha obsahu v post meta _ek_premigration)
 *    b) vypnutí pluginů, které šablona / elvisek-core nahrazují
 * ====================================================================== */

function ek_mig_replaced_plugins(): array {
	return array(
		'gutenberg/gutenberg.php'                                                  => 'Gutenberg',
		'shortcodes-ultimate/shortcodes-ultimate.php'                              => 'Shortcodes Ultimate',
		'syntaxhighlighter/syntaxhighlighter.php'                                  => 'SyntaxHighlighter Evolved',
		'wp-jquery-lightbox/wp-jquery-lightbox.php'                                => 'LightPress Lightbox',
		'open-external-links-in-a-new-window/open-external-links-in-a-new-window.php' => 'Open External Links in a New Window',
		'google-analytics-for-wordpress/googleanalytics.php'                       => 'MonsterInsights',
		'cookie-law-info/cookie-law-info.php'                                      => 'CookieYes',
		'advanced-nocaptcha-recaptcha/advanced-nocaptcha-recaptcha.php'            => 'CAPTCHA 4WP',
		'simple-login-log/simple-login-log.php'                                    => 'Simple Login Log',
		'wp-last-login/wp-last-login.php'                                          => 'WP Last Login',
		'display-mysql-version/mysql-version-display.php'                          => 'Display MySQL Version',
		'server-ip-memory-usage/server-ip-memory-usage.php'                        => 'Server IP & Memory Usage',
		'wp-extra-file-types/wp-extra-file-types.php'                              => 'WP Extra File Types',
		'stops-core-theme-and-plugin-updates/main.php'                             => 'Easy Updates Manager',
		'wpbenchmark/wp-benchmark-io.php'                                          => 'WP Benchmark',
	);
}

function ek_mig_code_lang( string $code ): string {
	if ( preg_match( '/^\s*(import |from \S+ import |def |print\()/m', $code ) ) {
		return 'python';
	}
	if ( preg_match( '/\b(pwsh|Import-Module|Connect-AzAccount|Install-Module|Get-[A-Z]\w+)\b/', $code ) ) {
		return 'powershell';
	}
	return 'bash';
}

function ek_mig_code_html( string $code, ?string $lang = null ): string {
	$code = trim( str_replace( "\r\n", "\n", preg_replace( '#<br\s*/?>#i', "\n", $code ) ), "\n" );
	$lang = $lang ?: ek_mig_code_lang( html_entity_decode( $code ) );
	return '<pre class="wp-block-code language-' . esc_attr( $lang ) . '"><code>' . $code . '</code></pre>';
}

function ek_mig_transform( string $c ): string {
	$c = preg_replace_callback(
		'#(?:<pre[^>]*>\s*)?\[php[^\]]*\](.*?)\[/php\](?:\s*</pre>)?#s',
		fn( $m ) => "\n" . ek_mig_code_html( $m[1] ) . "\n",
		$c
	);
	$c = preg_replace_callback(
		'#\[su_spoiler([^\]]*)\](.*?)\[/su_spoiler\]#s',
		function ( $m ) {
			$atts  = shortcode_parse_atts( $m[1] );
			$title = is_array( $atts ) && isset( $atts['title'] ) ? $atts['title'] : 'Podrobnosti';
			$body  = preg_match( '#<[a-z][^>]*>#i', $m[2] ) ? trim( $m[2] ) : ek_mig_code_html( $m[2] );
			return '<details class="wp-block-details"><summary>' . esc_html( $title ) . "</summary>\n" . $body . "\n</details>";
		},
		$c
	);
	$c = preg_replace_callback(
		'#<!-- wp:syntaxhighlighter/code(\s+(\{.*?\}))?\s*-->\s*<pre class="wp-block-syntaxhighlighter-code">(.*?)</pre>\s*<!-- /wp:syntaxhighlighter/code -->#s',
		function ( $m ) {
			$attrs = ! empty( $m[2] ) ? json_decode( $m[2], true ) : array();
			$code  = trim( str_replace( "\r\n", "\n", $m[3] ), "\n" );
			$lang  = sanitize_key( $attrs['language'] ?? '' ) ?: ek_mig_code_lang( html_entity_decode( $code ) );
			return '<!-- wp:code {"className":"language-' . $lang . '"} -->' . "\n"
				. '<pre class="wp-block-code language-' . $lang . '"><code>' . $code . '</code></pre>' . "\n"
				. '<!-- /wp:code -->';
		},
		$c
	);
	return $c;
}

/** Příspěvky ke konverzi: [id => [title, status, old, new]]. */
function ek_mig_candidates(): array {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT ID, post_title, post_status, post_content FROM {$wpdb->posts}
		 WHERE post_type IN ('post','page') AND post_status IN ('publish','draft','private','future','pending')
		 AND ( post_content LIKE '%[php%' OR post_content LIKE '%[su_spoiler%' OR post_content LIKE '%wp:syntaxhighlighter/code%' )"
	);
	$out = array();
	foreach ( $rows as $r ) {
		$new = ek_mig_transform( $r->post_content );
		if ( $new !== $r->post_content ) {
			$out[ (int) $r->ID ] = array( $r->post_title, $r->post_status, $r->post_content, $new );
		}
	}
	return $out;
}

function ek_mig_apply(): int {
	global $wpdb;
	$n = 0;
	foreach ( ek_mig_candidates() as $id => [ , , $old, $new ] ) {
		if ( ! metadata_exists( 'post', $id, '_ek_premigration' ) ) {
			add_post_meta( $id, '_ek_premigration', wp_slash( $old ), true );
		}
		// Přímý update — nemění datum úpravy a nevytváří revizi.
		$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$n++;
	}
	return $n;
}

function ek_mig_undo(): int {
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ek_premigration'" );
	foreach ( $ids as $id ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => get_post_meta( $id, '_ek_premigration', true ) ), array( 'ID' => $id ) );
		delete_post_meta( $id, '_ek_premigration' );
		clean_post_cache( $id );
	}
	return count( $ids );
}

add_action( 'admin_menu', function () {
	add_management_page( 'Údržba ElvisEK', 'Údržba ElvisEK', 'manage_options', 'ek-migrace', 'ek_mig_page' );
} );

function ek_mig_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$notice = '';

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'ek_mig' ) ) {
		$do = sanitize_key( $_POST['ek_do'] ?? '' );
		if ( 'migrate' === $do ) {
			$notice = sprintf( 'Převedeno článků: %d. Původní obsah je zálohovaný.', ek_mig_apply() );
		} elseif ( 'mig-forget' === $do ) {
			$notice = sprintf( 'Smazány zálohy původního obsahu u %d článků.', count( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_ek_premigration', 'fields' => 'ids', 'posts_per_page' => -1 ) ) ) );
			delete_post_meta_by_key( '_ek_premigration' );
		} elseif ( 'undo' === $do ) {
			$notice = sprintf( 'Vráceno článků: %d.', ek_mig_undo() );
		} elseif ( 'plugins' === $do ) {
			$active = array_values( array_filter( array_keys( ek_mig_replaced_plugins() ), 'is_plugin_active' ) );
			update_option( 'ek_mig_deactivated', $active, false );
			deactivate_plugins( $active );
			$notice = sprintf( 'Vypnuto pluginů: %d.', count( $active ) );
		} elseif ( str_starts_with( $do, 'maint-' ) ) {
			$notice = ek_maint_handle( $do );
		} elseif ( 'plugins-undo' === $do ) {
			$list = (array) get_option( 'ek_mig_deactivated', array() );
			activate_plugins( $list );
			delete_option( 'ek_mig_deactivated' );
			$notice = sprintf( 'Znovu zapnuto pluginů: %d.', count( $list ) );
		}
	}

	$cands    = ek_mig_candidates();
	$migrated = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_ek_premigration'" );
	$plugins  = ek_mig_replaced_plugins();
	$active   = array_filter( array_keys( $plugins ), 'is_plugin_active' );
	$present  = array_filter( array_keys( $plugins ), fn( $f ) => file_exists( WP_PLUGIN_DIR . '/' . $f ) );
	if ( ! $present && get_option( 'ek_mig_deactivated' ) ) {
		delete_option( 'ek_mig_deactivated' ); // pluginy jsou smazané, není co zapínat
	}
	$form     = function ( string $do, string $label, string $class = 'button' ) {
		echo '<form method="post" style="display:inline-block;margin-right:8px">';
		wp_nonce_field( 'ek_mig' );
		printf( '<input type="hidden" name="ek_do" value="%s"><button class="%s">%s</button></form>', esc_attr( $do ), esc_attr( $class ), esc_html( $label ) );
	};

	echo '<div class="wrap"><h1>Údržba ElvisEK</h1>';
	if ( $notice ) {
		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $notice ) );
	}

	if ( $cands || $migrated ) {
		echo '<h2>Migrace obsahu</h2>';
		echo '<p>Převede <code>[php]</code> a <code>[su_spoiler]</code> a bloky SyntaxHighlighter na nativní bloky Kód a Rozbalovací sekce. Nemění datum úpravy, nevytváří revize, původní obsah si uloží.</p>';
		if ( $cands ) {
			echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>ID</th><th>Článek</th><th>Stav</th><th>Náhled</th></tr></thead><tbody>';
			foreach ( $cands as $id => [ $title, $status, $old, $new ] ) {
				$pos = strpos( $new, '<details' );
				$pos = false === $pos ? strpos( $new, '<pre class="wp-block-code' ) : $pos;
				printf(
					'<tr><td>%d</td><td><a href="%s" target="_blank">%s</a></td><td>%s</td><td><code style="white-space:pre-wrap;font-size:11px">%s</code></td></tr>',
					$id, esc_url( get_permalink( $id ) ), esc_html( $title ), esc_html( $status ),
					esc_html( mb_substr( $new, (int) $pos, 220 ) )
				);
			}
			echo '</tbody></table><p>';
			$form( 'migrate', sprintf( 'Převést %d článků', count( $cands ) ), 'button button-primary' );
			echo '</p>';
		}
		if ( $migrated ) {
			printf( '<p>Převedených se zálohou: %d. ', $migrated );
			$form( 'undo', 'Vrátit původní obsah' );
			echo '<form method="post" style="display:inline-block" onsubmit="return confirm(\'Smazat zálohy? Pak už nepůjde vrátit původní obsah.\');">';
			wp_nonce_field( 'ek_mig' );
			echo '<input type="hidden" name="ek_do" value="mig-forget"><button class="button">Vše v pořádku – smazat zálohy</button></form>';
			echo '</p><p class="description">Po smazání záloh tahle sekce zmizí.</p>';
		}
	}

	if ( $present ) {
		echo '<h2>Pluginy nahrazené šablonou</h2><table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $plugins as $file => $name ) {
			$state = ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ? 'není nainstalován' : ( is_plugin_active( $file ) ? '🟢 aktivní' : '⚪ vypnutý' );
			printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $name ), esc_html( $state ) );
		}
		echo '</tbody></table><p>';
		if ( $active ) {
			if ( $cands ) {
				echo '<em>Nejdřív proveďte migraci obsahu, jinak se v článcích objeví holé shortcody.</em><br>';
			}
			$form( 'plugins', sprintf( 'Vypnout %d aktivních pluginů', count( $active ) ), $cands ? 'button' : 'button button-primary' );
		}
		if ( get_option( 'ek_mig_deactivated' ) ) {
			$form( 'plugins-undo', 'Znovu zapnout vypnuté pluginy' );
		}
		echo '</p><p class="description">Pluginy se jen vypnou, nesmažou. ManageWP (worker) zůstává.</p>';
	}

	ek_maint_section_perf( $form );
	ek_maint_section_cleanup( $form );
	echo '</div>';
}

/* =========================================================================
 * 8) Výkon a úklid — sekce na stránce Nástroje → Údržba ElvisEK
 * ====================================================================== */

const EK_HTACCESS_MARKER = 'ElvisEK cache';

function ek_maint_htaccess_rules(): array {
	return array(
		'<IfModule mod_expires.c>',
		'ExpiresActive On',
		'ExpiresByType text/css "access plus 1 year"',
		'ExpiresByType application/javascript "access plus 1 year"',
		'ExpiresByType text/javascript "access plus 1 year"',
		'ExpiresByType font/woff2 "access plus 1 year"',
		'ExpiresByType image/webp "access plus 1 year"',
		'ExpiresByType image/avif "access plus 1 year"',
		'ExpiresByType image/jpeg "access plus 1 year"',
		'ExpiresByType image/png "access plus 1 year"',
		'ExpiresByType image/gif "access plus 1 year"',
		'ExpiresByType image/svg+xml "access plus 1 year"',
		'ExpiresByType image/x-icon "access plus 1 year"',
		'</IfModule>',
		'<IfModule mod_headers.c>',
		'<FilesMatch "\.(css|js|woff2|webp|avif|jpe?g|png|gif|svg|ico)$">',
		'Header set Cache-Control "public, max-age=31536000"',
		'</FilesMatch>',
		'</IfModule>',
		'<IfModule mod_deflate.c>',
		'AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml application/javascript application/json application/xml image/svg+xml',
		'</IfModule>',
	);
}

/** Tabulky po odinstalovaných/nepoužívaných pluginech. */
function ek_maint_orphan_tables(): array {
	global $wpdb;
	$p        = $wpdb->prefix;
	$patterns = array( 'cerber\_%', $p . 'cerber\_%', $p . 'itsec\_%', $p . 'yoast\_%', $p . 'mwai\_%', $p . 'tm\_%', $p . 'monsterinsights\_%', $p . 'eum\_%', $p . 'wp\_phpmyadmin\_extension%', $p . 'xsg\_%', $p . 'simple\_login\_log' );
	$where    = implode( ' OR ', array_fill( 0, count( $patterns ), 'table_name LIKE %s' ) );
	$rows     = $wpdb->get_results( $wpdb->prepare(
		"SELECT table_name AS t, ROUND((data_length+index_length)/1048576,2) AS mb, table_rows AS r FROM information_schema.tables WHERE table_schema = DATABASE() AND ( $where ) ORDER BY (data_length+index_length) DESC",
		$patterns
	) );
	return $rows ?: array();
}

/**
 * Volby (wp_options) po odinstalovaných pluginech a smazaných šablonách.
 * ManageWP (mwp_, mmb_, worker_) a vše aktivní se nikdy nenabízí.
 */
function ek_maint_orphan_options(): array {
	global $wpdb;
	$prefixes = array(
		'jqlb_', 'coming_soon_page_', 'su_option_', 'su_presets_', 'exactmetrics_', '_amn_exact-metrics', 'monsterinsights_',
		'wp-mail-bank', 'mail_bank_', 'mb_tech_banker', 'wp-optimize-', 'wpo_', 'updraft_', 'recaptcha_', 'c4wp_', 'itsec_', 'fs_',
		'lightpress_', 'social_facbook_', 'social_twiter_', 'social_google_', 'social_youtobe_', 'social_', 'gadwp_', 'jetpack_', 'jpsq_',
		'advgb_', 'ossdl_', 'wpeft_', 'wt_cli_', 'cli_heading', 'cli_pg_', 'CookieLawInfo', 'cookielawinfo_', 'wpseo', 'yoast_', 'mwai_',
		'siteorigin_', 'megamenu_', 'wpsupercache_', 'supercache_', 'wpsc_', 'gutenberg_', 'hmbkp_', 'slb_', 'amp-options', 'amp_',
		'wpXSG_', 'xmsg_', 'syntaxhighlighter_', 'eum_', '_lab_opt_in_',
	);
	$exact = array( 'sunrise_defaults_su', 'sm_options', 'sm_status', 'user_hit_count', 'disabled_hit_count', 'show_from_name_in_email', 'show_from_email_in_email', 'update_email_configuration', 'do_activate', 'customize_stashed_theme_mods' );

	// Šablony, které už nejsou nainstalované.
	$like_mods = $wpdb->esc_like( 'theme_mods_' ) . '%';
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like_mods ) ) as $name ) {
		$slug = substr( $name, strlen( 'theme_mods_' ) );
		if ( $slug !== get_stylesheet() && ! wp_get_theme( $slug )->exists() ) {
			$exact[] = $name;
		}
	}
	if ( ! wp_get_theme( 'graphene-plus' )->exists() && ! wp_get_theme( 'graphene' )->exists() ) {
		array_push( $prefixes, 'graphene', '_graphene' );
	}

	$keep  = array( 'mwp_', 'mmb_', 'worker_' );
	$where = array();
	$args  = array();
	foreach ( $prefixes as $px ) {
		$where[] = 'option_name LIKE %s';
		$args[]  = $wpdb->esc_like( $px ) . '%';
	}
	$where[] = 'option_name IN (' . implode( ',', array_fill( 0, count( $exact ), '%s' ) ) . ')';
	$args    = array_merge( $args, $exact );
	$rows    = $wpdb->get_results( $wpdb->prepare(
		"SELECT option_name AS n, autoload AS a, LENGTH(option_value) AS b FROM {$wpdb->options} WHERE ( " . implode( ' OR ', $where ) . ' ) ORDER BY option_name',
		$args
	) );
	return array_values( array_filter( (array) $rows, function ( $r ) use ( $keep ) {
		foreach ( $keep as $k ) {
			if ( str_starts_with( $r->n, $k ) ) {
				return false;
			}
		}
		return ! str_starts_with( $r->n, 'ek_' );
	} ) );
}

function ek_maint_thumb_ids(): array {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_thumbnail_id' AND p.post_status = 'publish' AND pm.meta_value > 0"
	) );
}

function ek_maint_missing_sizes( int $id ): array {
	$meta  = wp_get_attachment_metadata( $id );
	$sizes = array_keys( (array) ( $meta['sizes'] ?? array() ) );
	$want  = array( 'ek-card', 'ek-featured', 'ek-hero' );
	$w     = (int) ( $meta['width'] ?? 0 );
	$h     = (int) ( $meta['height'] ?? 0 );
	// Velikost vznikne jen tehdy, když je originál větší.
	$dims  = array( 'ek-card' => array( 640, 360 ), 'ek-featured' => array( 1100, 720 ), 'ek-hero' => array( 1520, 640 ) );
	return array_values( array_filter( $want, fn( $s ) => ! in_array( $s, $sizes, true ) && ( $w > $dims[ $s ][0] || $h > $dims[ $s ][1] ) ) );
}

function ek_maint_handle( string $do ): string {
	global $wpdb;
	switch ( $do ) {
		case 'maint-htaccess-on':
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			return insert_with_markers( ABSPATH . '.htaccess', EK_HTACCESS_MARKER, ek_maint_htaccess_rules() )
				? 'Cache hlavičky přidány do .htaccess.' : 'Zápis do .htaccess se nepovedl (práva souboru).';
		case 'maint-htaccess-off':
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			insert_with_markers( ABSPATH . '.htaccess', EK_HTACCESS_MARKER, array() );
			return 'Cache hlavičky z .htaccess odebrány.';
		case 'maint-regen':
			@set_time_limit( 120 );
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$done = 0;
			$start = time();
			foreach ( ek_maint_thumb_ids() as $id ) {
				if ( ! ek_maint_missing_sizes( $id ) ) {
					continue;
				}
				wp_update_image_subsizes( $id );
				$done++;
				if ( time() - $start > 40 ) {
					break; // zbytek při dalším kliknutí
				}
			}
			return sprintf( 'Náhledy vytvořeny u %d obrázků. Pokud nějaké zbývají, klikněte znovu.', $done );
		case 'maint-tables':
			$names = array_map( 'sanitize_key', (array) ( $_POST['ek_tables'] ?? array() ) );
			$valid = wp_list_pluck( ek_maint_orphan_tables(), 't' );
			$drop  = array_intersect( $names, array_map( 'strtolower', $valid ) );
			foreach ( $drop as $t ) {
				$wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $t ) . "`" );
			}
			return sprintf( 'Smazáno tabulek: %d.', count( $drop ) );
		case 'maint-options':
			$names = array_map( 'wp_unslash', (array) ( $_POST['ek_options'] ?? array() ) );
			$valid = wp_list_pluck( ek_maint_orphan_options(), 'n' );
			$drop  = array_intersect( $names, $valid );
			foreach ( $drop as $n ) {
				delete_option( $n );
			}
			return sprintf( 'Smazáno voleb: %d.', count( $drop ) );
		case 'maint-revisions':
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" );
			foreach ( $ids as $id ) {
				wp_delete_post_revision( (int) $id );
			}
			return sprintf( 'Smazáno revizí: %d.', count( $ids ) );
		case 'maint-drafts':
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('auto-draft','trash')" );
			foreach ( $ids as $id ) {
				wp_delete_post( (int) $id, true );
			}
			return sprintf( 'Smazáno automatických konceptů a položek z koše: %d.', count( $ids ) );
		case 'maint-transients':
			delete_expired_transients( true );
			return 'Expirované transienty smazány.';
	}
	return '';
}

function ek_maint_section_perf( callable $form ): void {
	$ids     = ek_maint_thumb_ids();
	$missing = count( array_filter( $ids, fn( $id ) => (bool) ek_maint_missing_sizes( $id ) ) );
	$ht      = file_exists( ABSPATH . '.htaccess' ) ? (string) file_get_contents( ABSPATH . '.htaccess' ) : '';
	$has_ht  = str_contains( $ht, '# BEGIN ' . EK_HTACCESS_MARKER );
	$webp    = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );

	echo '<h2>Výkon</h2><table class="widefat striped" style="max-width:900px"><tbody>';
	printf( '<tr><td>Náhledy pro šablonu (karty, hlavní článek, úvodní obrázek)</td><td>%s</td></tr>', $missing ? sprintf( '⚠️ chybí u %d z %d obrázků', $missing, count( $ids ) ) : '✅ hotovo' );
	printf( '<tr><td>Nové zmenšeniny ve formátu WebP</td><td>%s</td></tr>', $webp ? '✅ server podporuje' : '⚪ server WebP neumí, zůstávají JPG/PNG' );
	printf( '<tr><td>Cache hlavičky v .htaccess</td><td>%s</td></tr>', $has_ht ? '✅ nastaveno' : '⚪ nenastaveno' );
	echo '</tbody></table><p>';
	if ( $missing ) {
		$form( 'maint-regen', 'Vytvořit chybějící náhledy', 'button button-primary' );
	}
	$has_ht ? $form( 'maint-htaccess-off', 'Odebrat cache hlavičky' ) : $form( 'maint-htaccess-on', 'Přidat cache hlavičky do .htaccess', 'button button-primary' );
	echo '</p>';
}

function ek_maint_section_cleanup( callable $form ): void {
	global $wpdb;
	$tables     = ek_maint_orphan_tables();
	$revisions  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
	$drafts     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('auto-draft','trash')" );
	$confirm    = "return confirm('Opravdu? Tohle nejde vrátit bez zálohy databáze.');";

	echo '<h2>Úklid</h2><div class="notice notice-warning inline"><p><strong>Před úklidem si udělejte zálohu databáze.</strong> Mazání nejde vrátit.</p></div>';

	echo '<h3>Tabulky po starých pluginech</h3>';
	if ( $tables ) {
		echo '<form method="post" onsubmit="' . esc_attr( $confirm ) . '">';
		wp_nonce_field( 'ek_mig' );
		echo '<input type="hidden" name="ek_do" value="maint-tables"><table class="widefat striped" style="max-width:900px"><thead><tr><th></th><th>Tabulka</th><th>MB</th><th>Řádků</th></tr></thead><tbody>';
		$sum = 0;
		foreach ( $tables as $t ) {
			$sum += (float) $t->mb;
			printf( '<tr><td><input type="checkbox" name="ek_tables[]" value="%1$s" checked></td><td><code>%1$s</code></td><td>%2$s</td><td>%3$s</td></tr>', esc_attr( $t->t ), esc_html( $t->mb ), esc_html( $t->r ) );
		}
		printf( '</tbody></table><p><button class="button">Smazat vybrané tabulky (%s MB)</button></p></form>', esc_html( number_format_i18n( $sum, 1 ) ) );
	} else {
		echo '<p>✅ Žádné nepoužívané tabulky.</p>';
	}

	$options = ek_maint_orphan_options();
	echo '<h3>Nastavení po starých pluginech a šablonách</h3>';
	if ( $options ) {
		$kb    = array_sum( wp_list_pluck( $options, 'b' ) ) / 1024;
		$auto  = array_filter( $options, fn( $o ) => in_array( $o->a, array( 'yes', 'on', 'auto', 'auto-on' ), true ) );
		$kb_ld = array_sum( wp_list_pluck( $auto, 'b' ) ) / 1024;
		printf( '<p>%d položek, %s kB celkem, z toho %d se načítá při každém požadavku (%s kB).</p>', count( $options ), esc_html( number_format_i18n( $kb, 1 ) ), count( $auto ), esc_html( number_format_i18n( $kb_ld, 1 ) ) );
		echo '<form method="post" onsubmit="' . esc_attr( $confirm ) . '">';
		wp_nonce_field( 'ek_mig' );
		echo '<input type="hidden" name="ek_do" value="maint-options"><details><summary style="cursor:pointer">Zobrazit seznam</summary><table class="widefat striped" style="max-width:900px;margin-top:8px"><thead><tr><th></th><th>Volba</th><th>kB</th><th>Autoload</th></tr></thead><tbody>';
		foreach ( $options as $o ) {
			printf( '<tr><td><input type="checkbox" name="ek_options[]" value="%1$s" checked></td><td><code>%1$s</code></td><td>%2$s</td><td>%3$s</td></tr>', esc_attr( $o->n ), esc_html( number_format_i18n( $o->b / 1024, 1 ) ), esc_html( $o->a ) );
		}
		echo '</tbody></table></details><p><button class="button">Smazat vybrané volby</button></p></form>';
	} else {
		echo '<p>✅ Žádné zbytky nastavení.</p>';
	}

	echo '<h3>Obsah databáze</h3><table class="widefat striped" style="max-width:900px"><tbody>';
	printf( '<tr><td>Revize článků</td><td>%d</td></tr>', $revisions );
	printf( '<tr><td>Automatické koncepty a koš</td><td>%d</td></tr>', $drafts );
	echo '</tbody></table><p>';
	foreach ( array( 'maint-revisions' => array( $revisions, 'Smazat revize' ), 'maint-drafts' => array( $drafts, 'Vysypat koncepty a koš' ) ) as $do => [ $n, $label ] ) {
		if ( $n ) {
			echo '<form method="post" style="display:inline-block;margin-right:8px" onsubmit="' . esc_attr( $confirm ) . '">';
			wp_nonce_field( 'ek_mig' );
			printf( '<input type="hidden" name="ek_do" value="%s"><button class="button">%s</button></form>', esc_attr( $do ), esc_html( $label ) );
		}
	}
	$form( 'maint-transients', 'Smazat expirované transienty' );
	echo '</p>';

	echo '<h3>Soubory</h3><p>Nepoužívané pluginy a šablony smažete standardně: ';
	printf( '<a href="%s">Pluginy → Neaktivní</a> (hromadná akce Smazat) a <a href="%s">Vzhled → Motivy</a>. ', esc_url( admin_url( 'plugins.php?plugin_status=inactive' ) ), esc_url( admin_url( 'themes.php' ) ) );
	echo 'Staré zálohy ve složce <code>wp-content/backupwordpress-*</code> je potřeba smazat přes FTP.</p>';
}

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
			'requires_php' => '8.1',
		);
	}, 10, 3 );

	add_action( 'load-update-core.php', function () {
		if ( isset( $_GET['force-check'] ) ) {
			delete_site_transient( 'ek_core_release' );
		}
	} );
}
