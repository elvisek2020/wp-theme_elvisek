<?php
/**
 * Plugin Name: ElvisEK Dev Tools (jen lokálně)
 * Description: Pomocné akce pro lokální vývoj přes URL. Na produkci se nenačte (WP_ENVIRONMENT_TYPE musí být „local“). NENASAZOVAT.
 * Version: 0.1.0
 *
 * Použití: http://localhost:8080/?ek-dev=<akce>&key=<EK_DEV_KEY>
 *   status            – přehled (šablona, pluginy, počty)
 *   activate          – přepne na šablonu elvisek a vypne pluginy, které šablona nahrazuje
 *   restore           – vrátí původní šablonu a pluginy
 *   migrate           – náhled migrace shortcodů (nic nemění)
 *   migrate&apply=1   – provede migraci (záloha původního obsahu do post meta)
 *   migrate-undo      – vrátí obsah ze zálohy
 */

defined( 'ABSPATH' ) || exit;

if ( 'local' !== wp_get_environment_type() ) {
	return;
}

const EK_DEV_KEY = 'ek-local-7f3c9a21';

// V DB jsou odkazy na http://localhost:8080 — při přístupu z jiné adresy (IP v síti) je přepíšeme ve výstupu.
if ( defined( 'EK_DEV_HOST' ) && 'localhost:8080' !== EK_DEV_HOST && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	ob_start( static function ( string $buffer ): string {
		return str_replace(
			array( 'http://localhost:8080', 'http:\\/\\/localhost:8080', 'localhost%3A8080' ),
			array( 'http://' . EK_DEV_HOST, 'http:\\/\\/' . EK_DEV_HOST, rawurlencode( EK_DEV_HOST ) ),
			$buffer
		);
	} );
}

/** Pluginy, které nová šablona / elvisek-core nahrazují. */
function ek_dev_replaced_plugins(): array {
	return array(
		'gutenberg/gutenberg.php',
		'shortcodes-ultimate/shortcodes-ultimate.php',
		'syntaxhighlighter/syntaxhighlighter.php',
		'wp-jquery-lightbox/wp-jquery-lightbox.php',
		'open-external-links-in-a-new-window/open-external-links-in-a-new-window.php',
		'google-analytics-for-wordpress/googleanalytics.php',
		'cookie-law-info/cookie-law-info.php',
		'advanced-nocaptcha-recaptcha/advanced-nocaptcha-recaptcha.php',
		'simple-login-log/simple-login-log.php',
		'wp-last-login/wp-last-login.php',
		'display-mysql-version/mysql-version-display.php',
		'server-ip-memory-usage/server-ip-memory-usage.php',
		'wp-extra-file-types/wp-extra-file-types.php',
		'stops-core-theme-and-plugin-updates/main.php',
		'wpbenchmark/wp-benchmark-io.php',
	);
}

add_action( 'init', function () {
	if ( empty( $_GET['ek-dev'] ) ) {
		return;
	}
	if ( ! hash_equals( EK_DEV_KEY, (string) ( $_GET['key'] ?? '' ) ) ) {
		wp_die( 'Neplatný klíč.', 403 );
	}
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	$action = sanitize_key( $_GET['ek-dev'] );
	$out    = match ( $action ) {
		'status'       => ek_dev_status(),
		'activate'     => ek_dev_activate(),
		'restore'      => ek_dev_restore(),
		'migrate'      => ek_dev_migrate( ! empty( $_GET['apply'] ) ),
		'migrate-undo' => ek_dev_migrate_undo(),
		default        => array( 'error' => 'Neznámá akce' ),
	};
	echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}, 1 );

function ek_dev_status(): array {
	return array(
		'theme'          => get_stylesheet(),
		'active_plugins' => get_option( 'active_plugins' ),
		'menu_locations' => get_theme_mod( 'nav_menu_locations' ),
		'posts'          => wp_count_posts()->publish,
		'migrated'       => (int) ( new WP_Query( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_ek_premigration', 'fields' => 'ids', 'posts_per_page' => -1 ) ) )->found_posts,
		'php'            => PHP_VERSION,
	);
}

function ek_dev_activate(): array {
	if ( ! get_option( 'ek_dev_backup' ) ) {
		update_option( 'ek_dev_backup', array(
			'stylesheet' => get_stylesheet(),
			'template'   => get_template(),
			'plugins'    => get_option( 'active_plugins' ),
		), false );
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$deactivate = array_values( array_filter( ek_dev_replaced_plugins(), 'is_plugin_active' ) );
	deactivate_plugins( $deactivate, true );
	if ( file_exists( WP_PLUGIN_DIR . '/elvisek-core/elvisek-core.php' ) ) {
		activate_plugin( 'elvisek-core/elvisek-core.php' );
	}
	switch_theme( 'elvisek' );
	do_action( 'after_switch_theme', 'elvisek', wp_get_theme( 'elvisek' ) );
	return array( 'ok' => true, 'theme' => get_stylesheet(), 'deactivated' => $deactivate, 'still_active' => get_option( 'active_plugins' ) );
}

function ek_dev_restore(): array {
	$b = get_option( 'ek_dev_backup' );
	if ( ! $b ) {
		return array( 'error' => 'Žádná záloha stavu.' );
	}
	switch_theme( $b['stylesheet'] );
	update_option( 'active_plugins', $b['plugins'] );
	delete_option( 'ek_dev_backup' );
	return array( 'ok' => true, 'theme' => get_stylesheet() );
}

/* ---------------------------------------------------------------------
 * Migrace obsahu: [php], [su_spoiler], bloky syntaxhighlighter → nativní HTML/bloky
 * ------------------------------------------------------------------ */

function ek_dev_code_lang( string $code ): string {
	if ( preg_match( '/^\s*(import |from \S+ import |def |print\()/m', $code ) ) {
		return 'python';
	}
	if ( preg_match( '/\b(pwsh|Import-Module|Connect-AzAccount|Install-Module|Get-[A-Z]\w+)\b/', $code ) ) {
		return 'powershell';
	}
	return 'bash';
}

function ek_dev_clean_code( string $code ): string {
	$code = preg_replace( '#<br\s*/?>#i', "\n", $code );
	$code = str_replace( "\r\n", "\n", $code );
	return trim( $code, "\n" );
}

function ek_dev_code_html( string $code, ?string $lang = null ): string {
	$code = ek_dev_clean_code( $code );
	$lang = $lang ?: ek_dev_code_lang( html_entity_decode( $code ) );
	return '<pre class="wp-block-code language-' . esc_attr( $lang ) . '"><code>' . $code . '</code></pre>';
}

function ek_dev_transform( string $c ): string {
	// 1) [php]…[/php] (případně obalené <pre>) → blok kódu
	$c = preg_replace_callback(
		'#(?:<pre[^>]*>\s*)?\[php[^\]]*\](.*?)\[/php\](?:\s*</pre>)?#s',
		fn( $m ) => "\n" . ek_dev_code_html( $m[1] ) . "\n",
		$c
	);

	// 2) [su_spoiler title="…"]…[/su_spoiler] → <details>
	$c = preg_replace_callback(
		'#\[su_spoiler([^\]]*)\](.*?)\[/su_spoiler\]#s',
		function ( $m ) {
			$atts  = shortcode_parse_atts( $m[1] );
			$title = is_array( $atts ) && isset( $atts['title'] ) ? $atts['title'] : 'Podrobnosti';
			$body  = $m[2];
			$body  = preg_match( '#<[a-z][^>]*>#i', $body ) ? trim( $body ) : ek_dev_code_html( $body );
			return '<details class="wp-block-details"><summary>' . esc_html( $title ) . "</summary>\n" . $body . "\n</details>";
		},
		$c
	);

	// 3) Blok syntaxhighlighter/code → core/code
	$c = preg_replace_callback(
		'#<!-- wp:syntaxhighlighter/code(\s+(\{.*?\}))?\s*-->\s*<pre class="wp-block-syntaxhighlighter-code">(.*?)</pre>\s*<!-- /wp:syntaxhighlighter/code -->#s',
		function ( $m ) {
			$attrs = ! empty( $m[2] ) ? json_decode( $m[2], true ) : array();
			$code  = ek_dev_clean_code( $m[3] );
			$lang  = sanitize_key( $attrs['language'] ?? '' ) ?: ek_dev_code_lang( html_entity_decode( $code ) );
			return '<!-- wp:code {"className":"language-' . $lang . '"} -->' . "\n"
				. '<pre class="wp-block-code language-' . $lang . '"><code>' . $code . '</code></pre>' . "\n"
				. '<!-- /wp:code -->';
		},
		$c
	);

	return $c;
}

function ek_dev_migrate( bool $apply ): array {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT ID, post_title, post_status, post_content FROM {$wpdb->posts}
		 WHERE post_type IN ('post','page') AND post_status IN ('publish','draft','private','future')
		 AND ( post_content LIKE '%[php%' OR post_content LIKE '%[su_spoiler%' OR post_content LIKE '%wp:syntaxhighlighter/code%' )"
	);
	$report = array();
	foreach ( $rows as $row ) {
		$new = ek_dev_transform( $row->post_content );
		if ( $new === $row->post_content ) {
			continue;
		}
		$item = array(
			'id'     => (int) $row->ID,
			'title'  => $row->post_title,
			'status' => $row->post_status,
			'left'   => array(
				'php'        => substr_count( $new, '[php' ),
				'su_spoiler' => substr_count( $new, '[su_spoiler' ),
				'shl_block'  => substr_count( $new, 'wp:syntaxhighlighter' ),
			),
		);
		if ( $apply ) {
			if ( ! metadata_exists( 'post', $row->ID, '_ek_premigration' ) ) {
				add_post_meta( $row->ID, '_ek_premigration', wp_slash( $row->post_content ), true );
			}
			// Přímý update: nemění datum úpravy ani nevytváří revizi.
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $row->ID ) );
			clean_post_cache( $row->ID );
			$item['applied'] = true;
		} else {
			$pos = mb_strpos( $new, '<details' );
			if ( false === $pos ) {
				$pos = mb_strpos( $new, '<pre class="wp-block-code' );
			}
			$item['sample'] = mb_substr( $new, (int) $pos, 400 );
		}
		$report[] = $item;
	}
	return array( 'apply' => $apply, 'count' => count( $report ), 'posts' => $report );
}

function ek_dev_migrate_undo(): array {
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ek_premigration'" );
	foreach ( $ids as $id ) {
		$orig = get_post_meta( $id, '_ek_premigration', true );
		$wpdb->update( $wpdb->posts, array( 'post_content' => $orig ), array( 'ID' => $id ) );
		delete_post_meta( $id, '_ek_premigration' );
		clean_post_cache( $id );
	}
	return array( 'restored' => array_map( 'intval', $ids ) );
}
