<?php
/**
 * EKC module: login-log
 * Version: 1.0.0
 * Requires: common
 * Replaces: simple-login-log, wp-last-login
 * Summary: Log přihlášení, neúspěšných pokusů a blokací a sloupec Poslední přihlášení u uživatelů.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Záznamy jsou v option ekc_login_log (bez autoloadu) ve formátu {t, e, u, i, d, a}:
 * čas, událost success|failed|lockout, zadané jméno, IP, délka blokace v sekundách, prohlížeč.
 * Nejnovější první, nejvýš EKC_LOGIN_LOG_MAX záznamů a EKC_LOGIN_LOG_DAYS dní (konstanty v common).
 * Blokace zapisuje modul login-guard přes ekc_login_log_add(). Stránka logu (ekc-login-log)
 * se zobrazuje i při vypnutém logu (starší záznamy). Čas posledního přihlášení: user meta
 * ekc_last_login a sloupec v přehledu uživatelů.
 */

ekc_settings_register(
	'login_log',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Log přihlášení',
		'help'    => 'Záznam přihlášení, neúspěšných pokusů a blokací (posledních ' . EKC_LOGIN_LOG_MAX . ' událostí, nejvýš ' . EKC_LOGIN_LOG_DAYS . ' dní) a sloupec Poslední přihlášení v přehledu uživatelů.',
		'section' => 'Přihlášení',
	)
);

/**
 * Záznamy logu, nejnovější první.
 *
 * @param int $limit Nejvýš záznamů.
 * @return array<int,array{t:int,e:string,u:string,i:string,d:int,a:string}>
 */
function ekc_login_log( int $limit = 50 ): array {
	$since = time() - EKC_LOGIN_LOG_DAYS * DAY_IN_SECONDS;
	$rows  = array();
	foreach ( (array) get_option( 'ekc_login_log', array() ) as $row ) {
		$row = ekc_login_log_row( $row );
		if ( $row && $row['t'] >= $since ) {
			$rows[] = $row;
			if ( count( $rows ) >= $limit ) {
				break;
			}
		}
	}
	return $rows;
}

/**
 * Zapíše událost do logu (při vypnutém logu nic).
 *
 * @param string $event    success|failed|lockout.
 * @param string $user     Zadané uživatelské jméno nebo e-mail.
 * @param int    $duration Délka blokace v sekundách (lockout).
 */
function ekc_login_log_add( string $event, string $user, int $duration = 0 ): void {
	if ( ! ekc_opt( 'login_log' ) || ! in_array( $event, array( 'success', 'failed', 'lockout' ), true ) ) {
		return;
	}
	$log = get_option( 'ekc_login_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift(
		$log,
		array(
			't' => time(),
			'e' => $event,
			'u' => mb_substr( sanitize_user( $user, false ), 0, 60 ),
			'i' => ekc_client_ip(),
			'd' => max( 0, $duration ),
			'a' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 160 ) : '',
		)
	);
	$since = time() - EKC_LOGIN_LOG_DAYS * DAY_IN_SECONDS;
	$log   = array_filter( array_slice( $log, 0, EKC_LOGIN_LOG_MAX ), static fn( $row ) => is_array( $row ) && (int) ( $row['t'] ?? 0 ) >= $since );
	update_option( 'ekc_login_log', array_values( $log ), false );
}

/**
 * Neúspěšný pokus (během blokace z login-guard se nezapisuje).
 *
 * @param string        $username Zadané jméno.
 * @param WP_Error|null $error    Chyba.
 */
function ekc_login_log_failed( $username, $error = null ): void {
	if ( $error instanceof WP_Error && in_array( 'ekc_locked', $error->get_error_codes(), true ) ) {
		return;
	}
	ekc_login_log_add( 'failed', (string) $username );
}
add_action( 'wp_login_failed', 'ekc_login_log_failed', 5, 2 );

/**
 * Úspěšné přihlášení do logu a čas posledního přihlášení.
 *
 * @param string       $user_login Uživatelské jméno.
 * @param WP_User|null $user       Uživatel.
 */
function ekc_login_log_success( $user_login, $user = null ): void {
	if ( ! ekc_opt( 'login_log' ) ) {
		return;
	}
	ekc_login_log_add( 'success', (string) $user_login );
	if ( $user instanceof WP_User ) {
		update_user_meta( $user->ID, 'ekc_last_login', time() );
	}
}
add_action( 'wp_login', 'ekc_login_log_success', 10, 2 );

/**
 * Sloupec Poslední přihlášení v přehledu uživatelů.
 *
 * @param array $columns Sloupce.
 * @return array
 */
function ekc_login_log_users_column( $columns ) {
	if ( ekc_opt( 'login_log' ) ) {
		$columns['ekc_last_login'] = 'Poslední přihlášení';
	}
	return $columns;
}
add_filter( 'manage_users_columns', 'ekc_login_log_users_column' );

/**
 * Obsah sloupce.
 *
 * @param string $output  Výstup.
 * @param string $column  Sloupec.
 * @param int    $user_id ID uživatele.
 * @return string
 */
function ekc_login_log_users_column_content( $output, $column, $user_id ) {
	if ( 'ekc_last_login' !== $column ) {
		return $output;
	}
	$time = (int) get_user_meta( (int) $user_id, 'ekc_last_login', true );
	return $time ? esc_html( ekc_datetime( $time ) ) : '—';
}
add_filter( 'manage_users_custom_column', 'ekc_login_log_users_column_content', 10, 3 );

/**
 * Stránka Log přihlášení (Nástroje, nadřazenou stránku mění filtr ekc_page_parents).
 */
function ekc_login_log_menu(): void {
	$parents = ekc_page_parents();
	add_submenu_page( $parents['ekc-login-log'] ?? 'tools.php', 'Log přihlášení', 'Log přihlášení', 'manage_options', 'ekc-login-log', 'ekc_login_log_page' );
}
add_action( 'admin_menu', 'ekc_login_log_menu' );

/**
 * Popis události.
 *
 * @param array $row Záznam.
 * @return string HTML.
 */
function ekc_login_log_event( array $row ): string {
	switch ( $row['e'] ) {
		case 'success':
			return ekc_status_dot( 'ok' ) . ' přihlášení';
		case 'lockout':
			$minutes = max( 1, (int) round( (int) $row['d'] / MINUTE_IN_SECONDS ) );
			$label   = $minutes < 120 ? $minutes . ' min' : number_format_i18n( $minutes / 60, 0 === $minutes % 60 ? 0 : 1 ) . ' h';
			return ekc_status_dot( 'bad' ) . ' zablokováno na ' . esc_html( $label );
	}
	return ekc_status_dot( 'warn' ) . ' neúspěšný pokus';
}

/**
 * Obsah stránky logu.
 */
function ekc_login_log_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$log = ekc_login_log( EKC_LOGIN_LOG_MAX );
	echo '<div class="wrap"><h1>Log přihlášení</h1>';
	ekc_maintenance_notice();
	if ( ! ekc_opt( 'login_log' ) ) {
		echo '<div class="notice notice-warning inline"><p>Log přihlášení je vypnutý, níže jsou jen starší záznamy. <a href="' . esc_url( ekc_page_url( 'ekc-nastaveni' ) ) . '">Nastavení</a></p></div>';
	}

	/*
	 * Modul login-guard sem vypíše stav blokací a tlačítko pro jejich zrušení.
	 */
	do_action( 'ekc_login_log_before' );

	echo '<table class="widefat striped" style="margin-top:1em"><thead><tr><th>Čas</th><th>Uživatel</th><th>Výsledek</th><th>IP adresa</th><th>Prohlížeč</th></tr></thead><tbody>';
	if ( ! $log ) {
		echo '<tr><td colspan="5">Zatím prázdné.</td></tr>';
	}
	foreach ( $log as $row ) {
		printf(
			'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td><code>%4$s</code></td><td><small>%5$s</small></td></tr>',
			esc_html( (string) wp_date( 'j. n. Y H:i:s', $row['t'] ) ),
			esc_html( $row['u'] ),
			ekc_login_log_event( $row ), // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno ve funkci
			esc_html( $row['i'] ),
			esc_html( $row['a'] )
		);
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html( sprintf( 'Uchovává se posledních %d událostí, nejvýše %d dní.', EKC_LOGIN_LOG_MAX, EKC_LOGIN_LOG_DAYS ) ) . ' <a href="' . esc_url( ekc_page_url( 'ekc-nastaveni' ) ) . '">Nastavení</a></p>';
	echo '</div>';
}
