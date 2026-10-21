<?php
/**
 * EKC module: health
 * Version: 1.0.0
 * Requires: common
 * Replaces: –
 * Summary: Widget Zdraví webu na Nástěnce: verze a aktualizace, data, obsah, přihlášení, poslední údržba.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Verze a aktualizace se berou z mezipaměti WordPressu (nic se nestahuje). Dražší čísla
 * (velikost databáze, autoload, uploads) drží transient ekc_health na 12 h, tlačítko Obnovit
 * je přepočítá. Údaje volitelných modulů jen přes function_exists(); moduly přidávají řádky
 * filtrem ekc_health_rows: [ 'Skupina' => [ 'Popisek' => 'HTML hodnota (escapovaná)' ] ].
 */

const EKC_HEALTH_TTL = 12 * HOUR_IN_SECONDS;

/**
 * Velikost databáze, autoload a uploads (cache 12 h).
 *
 * @param bool $refresh Přepočítat.
 * @return array{db:int,autoload:int,uploads:int,at:int}
 */
function ekc_health_heavy( bool $refresh = false ): array {
	$data = $refresh ? false : get_transient( 'ekc_health' );
	if ( is_array( $data ) && isset( $data['at'] ) ) {
		return $data;
	}
	@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$uploads  = wp_get_upload_dir();
	$autoload = ekc_db_autoload();
	$data     = array(
		'db'       => ekc_db_size(),
		'autoload' => (int) $autoload['total'],
		'uploads'  => ! empty( $uploads['basedir'] ) ? ekc_path_size( (string) $uploads['basedir'] ) : 0,
		'at'       => time(),
	);
	set_transient( 'ekc_health', $data, EKC_HEALTH_TTL );
	return $data;
}

/**
 * Po úloze v Údržbě se velikosti přepočítají při dalším zobrazení.
 */
function ekc_health_flush(): void {
	delete_transient( 'ekc_health' );
}
add_action( 'ekc_maintenance_done', 'ekc_health_flush' );

/**
 * Tlačítko Obnovit (index.php?ekc_health_refresh=1 s nonce) → přepočítat a přesměrovat zpět (PRG).
 */
function ekc_health_refresh(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ověřeno níže
	if ( empty( $_GET['ekc_health_refresh'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'ekc_health_refresh' ) ) {
		ekc_health_heavy( true );
	}
	wp_safe_redirect( admin_url( 'index.php' ) );
	exit;
}
add_action( 'load-index.php', 'ekc_health_refresh' );

/**
 * Registrace widgetu (jen administrátorům).
 */
function ekc_health_register(): void {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'ekc_health', 'Zdraví webu', 'ekc_health_widget', null, null, 'normal', 'high' );
	}
}
add_action( 'wp_dashboard_setup', 'ekc_health_register' );

/**
 * Řádky widgetu.
 *
 * @return array<string,array<string,string>> skupina => popisek => HTML
 */
function ekc_health_rows(): array {
	$ok    = ekc_status_dot( 'ok' );
	$warn  = ekc_status_dot( 'warn' );
	$heavy = ekc_health_heavy();
	$upd   = admin_url( 'update-core.php' );
	$new   = static fn( string $version ): string => '' !== $version
		? ' ' . $warn . ' <a href="' . esc_url( $upd ) . '">k dispozici ' . esc_html( $version ) . '</a>'
		: ' ' . $ok;

	// Verze a dostupné aktualizace (z mezipaměti WordPressu).
	$theme       = wp_get_theme();
	$upd_themes  = get_site_transient( 'update_themes' );
	$upd_plugins = get_site_transient( 'update_plugins' );
	$theme_new   = is_object( $upd_themes ) ? (string) ( $upd_themes->response[ $theme->get_stylesheet() ]['new_version'] ?? '' ) : '';
	$plugin_obj  = is_object( $upd_plugins ) ? ( $upd_plugins->response[ EKC_BASENAME ] ?? null ) : null;
	$plugin_new  = is_object( $plugin_obj ) ? (string) ( $plugin_obj->new_version ?? '' ) : '';
	if ( ! function_exists( 'get_core_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}
	$wp_upd  = get_core_updates();
	$wp_new  = ( is_array( $wp_upd ) && isset( $wp_upd[0]->response ) && 'upgrade' === $wp_upd[0]->response ) ? (string) $wp_upd[0]->current : '';
	$counts  = wp_get_update_data();
	$total   = (int) ( $counts['counts']['total'] ?? 0 );
	$theme_l = 'Šablona ' . $theme->get( 'Name' ) . ( $theme->parent() ? ' (podřízená)' : '' );

	$rows = array(
		'Verze'  => array(
			$theme_l          => esc_html( (string) $theme->get( 'Version' ) ) . $new( $theme_new ),
			EKC_NAME . ' Core' => esc_html( EKC_VERSION ) . $new( $plugin_new ),
			'WordPress'       => esc_html( (string) get_bloginfo( 'version' ) ) . $new( $wp_new ),
			'PHP'             => esc_html( PHP_VERSION ) . ( version_compare( PHP_VERSION, '8.1', '<' ) ? ' ' . $warn . ' doporučeno 8.1+' : ' ' . $ok ),
			'Aktualizace'     => $total
				? $warn . ' <a href="' . esc_url( $upd ) . '">' . esc_html( ekc_plural( $total, 'čeká %d', 'čekají %d', 'čeká %d' ) ) . '</a>'
				: '0 ' . $ok,
		),
		'Data'   => array(
			'Databáze' => esc_html( ekc_size( (int) $heavy['db'] ) ),
			'Autoload' => esc_html( ekc_size( (int) $heavy['autoload'] ) ) . ( $heavy['autoload'] > MB_IN_BYTES ? ' ' . $warn . ' víc než 1 MB' : ' ' . $ok ),
			'Uploads'  => esc_html( ekc_size( (int) $heavy['uploads'] ) ),
		),
		'Obsah'  => array(
			'Koncepty' => sprintf( '<a href="%1$s">%2$d</a>', esc_url( admin_url( 'edit.php?post_status=draft&post_type=any' ) ), (int) wp_count_posts( 'post' )->draft + (int) wp_count_posts( 'page' )->draft ),
		),
	);

	$pending = (int) wp_count_comments()->moderated;
	if ( $pending || post_type_supports( 'post', 'comments' ) ) {
		$rows['Obsah']['Komentáře ke schválení'] = $pending
			? $warn . ' <a href="' . esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ) . '">' . $pending . '</a>'
			: '0 ' . $ok;
	}

	// Přihlášení – jen když běží moduly login-log / login-guard.
	if ( function_exists( 'ekc_login_log' ) ) {
		$failed = 0;
		$last   = null;
		$since  = time() - DAY_IN_SECONDS;
		foreach ( (array) ekc_login_log( 200 ) as $row ) {
			if ( 'failed' === ( $row['e'] ?? '' ) && (int) $row['t'] >= $since ) {
				++$failed;
			}
			if ( ! $last && 'success' === ( $row['e'] ?? '' ) ) {
				$last = $row;
			}
		}
		$rows['Přihlášení']['Neúspěšné za 24 h']   = sprintf( '<a href="%1$s">%2$d</a>', esc_url( ekc_page_url( 'ekc-login-log' ) ), $failed ) . ( $failed > 20 ? ' ' . $warn : '' );
		$rows['Přihlášení']['Poslední přihlášení'] = $last ? esc_html( ekc_datetime( (int) $last['t'] ) . ' · ' . $last['u'] ) : '—';
	}
	if ( function_exists( 'ekc_lockouts_active' ) ) {
		$locked = (int) ekc_lockouts_active();
		$rows['Přihlášení']['Zablokované IP'] = $locked . ( $locked ? ' ' . $warn : ' ' . $ok );
	}

	$maint = (int) get_option( 'ekc_maint_last', 0 );
	$rows['Údržba']['Naposledy'] = $maint
		? esc_html( ekc_datetime( $maint ) . ' (' . human_time_diff( $maint ) . ')' )
		: 'zatím nezaznamenáno';

	return (array) apply_filters( 'ekc_health_rows', $rows );
}

/**
 * Obsah widgetu.
 */
function ekc_health_widget(): void {
	$heavy = ekc_health_heavy();
	echo '<table class="widefat striped" style="border:0;box-shadow:none"><tbody>';
	foreach ( ekc_health_rows() as $group => $items ) {
		if ( ! $items ) {
			continue;
		}
		printf( '<tr><th colspan="2" style="padding-top:10px;font-weight:600">%s</th></tr>', esc_html( (string) $group ) );
		foreach ( (array) $items as $label => $value ) {
			printf( '<tr><td style="width:55%%">%1$s</td><td>%2$s</td></tr>', esc_html( (string) $label ), wp_kses_post( (string) $value ) );
		}
	}
	echo '</tbody></table>';
	printf(
		'<p style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin:12px 0 0"><span><a class="button" href="%1$s">Údržba webu</a> <a href="%2$s">Nastavení</a></span><span style="color:#646970">Velikosti k %3$s · <a href="%4$s">Obnovit</a></span></p>',
		esc_url( ekc_page_url( 'ekc-udrzba' ) ),
		esc_url( ekc_page_url( 'ekc-nastaveni' ) ),
		esc_html( (string) wp_date( 'j. n. H:i', (int) $heavy['at'] ) ),
		esc_url( wp_nonce_url( admin_url( 'index.php?ekc_health_refresh=1' ), 'ekc_health_refresh' ) )
	);
}
