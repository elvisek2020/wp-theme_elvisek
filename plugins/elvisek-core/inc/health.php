<?php
/**
 * Widget „Zdraví webu“ na Nástěnce: verze, databáze, obsah, uploads, poslední údržba.
 * Dražší čísla (velikost databáze a uploads) se drží 12 h v transientu, tlačítko Obnovit je přepočítá.
 */

defined( 'ABSPATH' ) || exit;

const EK_HEALTH_TTL = 12 * HOUR_IN_SECONDS;

add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'ek_health', 'Zdraví webu', 'ek_health_widget', null, null, 'normal', 'high' );
} );

/** Údržba webu zaznamená čas posledního spuštění akce. */
function ek_health_mark_maintenance(): void {
	update_option( 'ek_maint_last', time(), false );
}

function ek_health_heavy( bool $refresh = false ): array {
	$data = $refresh ? false : get_transient( 'ek_health_heavy' );
	if ( is_array( $data ) ) {
		return $data;
	}
	global $wpdb;
	$like = $wpdb->esc_like( $wpdb->prefix ) . '%';
	$db   = (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s AND table_name LIKE %s',
		DB_NAME,
		$like
	) );
	$autoload = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')" );
	$uploads  = 0;
	$dir      = wp_get_upload_dir()['basedir'] ?? '';
	if ( $dir && is_dir( $dir ) ) {
		@set_time_limit( 60 );
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( $f->isFile() ) {
				$uploads += $f->getSize();
			}
		}
	}
	$data = array( 'db' => $db, 'autoload' => $autoload, 'uploads' => $uploads, 'at' => time() );
	set_transient( 'ek_health_heavy', $data, EK_HEALTH_TTL );
	return $data;
}

function ek_health_size( int $bytes ): string {
	if ( $bytes >= 1073741824 ) {
		return number_format_i18n( $bytes / 1073741824, 2 ) . ' GB';
	}
	return $bytes >= 1048576 ? number_format_i18n( $bytes / 1048576, 1 ) . ' MB' : number_format_i18n( $bytes / 1024, 0 ) . ' kB';
}

function ek_health_widget(): void {
	global $wpdb;
	$refresh = isset( $_GET['ek_health_refresh'] ) && check_admin_referer( 'ek_health_refresh' );
	$heavy   = ek_health_heavy( $refresh );

	// Verze a dostupné aktualizace (z cache WordPressu, nic se tu nestahuje).
	$theme       = wp_get_theme( 'elvisek' );
	$theme_ver   = $theme->exists() ? (string) $theme->get( 'Version' ) : '–';
	$upd_themes  = get_site_transient( 'update_themes' );
	$upd_plugins = get_site_transient( 'update_plugins' );
	$theme_new   = is_object( $upd_themes ) ? (string) ( $upd_themes->response['elvisek']['new_version'] ?? '' ) : '';
	$plugin_obj  = is_object( $upd_plugins ) ? ( $upd_plugins->response[ EK_CORE_BASENAME ] ?? null ) : null;
	$plugin_new  = is_object( $plugin_obj ) ? (string) ( $plugin_obj->new_version ?? '' ) : '';
	$wp_upd      = get_core_updates();
	$wp_new      = ( is_array( $wp_upd ) && isset( $wp_upd[0] ) && 'upgrade' === ( $wp_upd[0]->response ?? '' ) ) ? $wp_upd[0]->current : '';

	// Obsah.
	$drafts   = (int) wp_count_posts( 'post' )->draft;
	$pending  = (int) wp_count_comments()->moderated;
	$no_thumb = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_thumbnail_id'
		 WHERE p.post_type = 'post' AND p.post_status = 'publish' AND ( m.meta_value IS NULL OR m.meta_value = '' OR m.meta_value = '0' )"
	);
	$maint    = (int) get_option( 'ek_maint_last' );

	$ok   = '<span style="color:#1a7f37">●</span>';
	$warn = '<span style="color:#bf8700">●</span>';
	$new  = fn( string $v ) => $v ? ' ' . $warn . ' <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">k dispozici ' . esc_html( $v ) . '</a>' : ' ' . $ok;

	$rows = array(
		'Verze'   => array(
			'Šablona ElvisEK' => esc_html( $theme_ver ) . $new( (string) $theme_new ),
			'ElvisEK Core'    => esc_html( EK_CORE_VERSION ) . $new( (string) $plugin_new ),
			'WordPress'       => esc_html( get_bloginfo( 'version' ) ) . $new( (string) $wp_new ),
			'PHP'             => esc_html( PHP_VERSION ),
		),
		'Data'    => array(
			'Databáze'  => esc_html( ek_health_size( $heavy['db'] ) ),
			'Autoload'  => esc_html( ek_health_size( $heavy['autoload'] ) ) . ( $heavy['autoload'] > 1048576 ? ' ' . $warn . ' víc než 1 MB' : ' ' . $ok ),
			'Uploads'   => esc_html( ek_health_size( $heavy['uploads'] ) ),
		),
		'Obsah'   => array(
			'Koncepty'                  => sprintf( '<a href="%s">%d</a>', esc_url( admin_url( 'edit.php?post_status=draft' ) ), $drafts ),
			'Komentáře ke schválení'    => $pending ? sprintf( '%s <a href="%s">%d</a>', $warn, esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ), $pending ) : '0 ' . $ok,
			'Články bez vlastního náhledu' => (string) $no_thumb . ' <span style="color:#646970">(zobrazí se obrázek rubriky)</span>',
		),
		'Údržba'  => array(
			'Naposledy' => $maint ? esc_html( wp_date( 'j. n. Y H:i', $maint ) ) . ' (' . esc_html( human_time_diff( $maint ) ) . ')' : 'zatím nezaznamenáno',
		),
	);

	echo '<table class="widefat striped" style="border:0;box-shadow:none"><tbody>';
	foreach ( $rows as $group => $items ) {
		printf( '<tr><th colspan="2" style="padding-top:10px;font-weight:600">%s</th></tr>', esc_html( $group ) );
		foreach ( $items as $label => $value ) {
			printf( '<tr><td style="width:55%%">%s</td><td>%s</td></tr>', esc_html( $label ), $value ); // phpcs:ignore WordPress.Security.EscapeOutput -- hodnoty escapované výše
		}
	}
	echo '</tbody></table>';
	printf(
		'<p style="display:flex;justify-content:space-between;align-items:center;margin:12px 0 0"><a class="button" href="%s">Údržba webu</a><span style="color:#646970">Velikosti k %s · <a href="%s">Obnovit</a></span></p>',
		esc_url( admin_url( 'tools.php?page=' . EK_MAINT_SLUG ) ),
		esc_html( wp_date( 'j. n. H:i', $heavy['at'] ) ),
		esc_url( wp_nonce_url( admin_url( 'index.php?ek_health_refresh=1' ), 'ek_health_refresh' ) )
	);
}
