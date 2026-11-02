<?php
/**
 * EKC module: login-log
 * Version: 1.2.0
 * Requires: common
 * Replaces: simple-login-log, wp-last-login
 * Summary: Log přihlášení, neúspěšných pokusů a blokací a sloupec Poslední přihlášení u uživatelů.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Záznamy jsou v option ekc_login_log (bez autoloadu) ve formátu {t, e, u, i, d, a}:
 * čas, událost success|failed|twofactor|lockout, zadané jméno, IP, délka blokace v sekundách,
 * prohlížeč. Událost twofactor = správné heslo, ale chybný kód dvoufázového ověření (modul
 * two-factor) – heslo pak nejspíš zná někdo cizí.
 * Nejnovější první; kolik záznamů a dní se drží, je v nastavení (login_log_max, login_log_days),
 * výchozí hodnoty jsou konstanty EKC_LOGIN_LOG_MAX a EKC_LOGIN_LOG_DAYS v common.
 * Blokace zapisuje modul login-guard přes ekc_login_log_add(). Stránka logu (ekc-login-log)
 * se zobrazuje i při vypnutém logu (starší záznamy). Čas posledního přihlášení: user meta
 * ekc_last_login a sloupec v přehledu uživatelů.
 */

ekc_settings_register(
	'login_log',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Zapisovat přihlášení',
		'help'    => 'Záznam přihlášení, neúspěšných pokusů a blokací a sloupec Poslední přihlášení v přehledu uživatelů.',
		'section' => 'Přihlášení',
		'group'   => 'Log přihlášení',
	)
);
ekc_settings_register(
	'login_log_max',
	array(
		'type'    => 'int',
		'default' => EKC_LOGIN_LOG_MAX,
		'label'   => 'Délka logu',
		'help'    => 'Kolik posledních událostí se v logu přihlášení drží. Při útocích stovky denně, pro historii několika týdnů 2000 a víc.',
		'section' => 'Přihlášení',
		'group'   => 'Log přihlášení',
		'parent'  => 'login_log',
		'min'     => 50,
		'max'     => 5000,
		'unit'    => 'událostí',
	)
);
ekc_settings_register(
	'login_log_days',
	array(
		'type'    => 'int',
		'default' => EKC_LOGIN_LOG_DAYS,
		'label'   => 'Stáří záznamů v logu',
		'help'    => 'Starší záznamy se mažou, i když se do délky logu vejdou.',
		'section' => 'Přihlášení',
		'group'   => 'Log přihlášení',
		'parent'  => 'login_log',
		'min'     => 7,
		'max'     => 365,
		'unit'    => 'dní',
	)
);

/**
 * Kolik událostí se v logu drží.
 *
 * @return int
 */
function ekc_login_log_max(): int {
	$max = (int) ekc_opt( 'login_log_max' );
	return $max > 0 ? $max : EKC_LOGIN_LOG_MAX;
}

/**
 * Kolik dní se záznamy drží.
 *
 * @return int
 */
function ekc_login_log_days(): int {
	$days = (int) ekc_opt( 'login_log_days' );
	return $days > 0 ? $days : EKC_LOGIN_LOG_DAYS;
}

/**
 * Záznamy logu, nejnovější první.
 *
 * @param int $limit Nejvýš záznamů.
 * @return array<int,array{t:int,e:string,u:string,i:string,d:int,a:string}>
 */
function ekc_login_log( int $limit = 50 ): array {
	$since = time() - ekc_login_log_days() * DAY_IN_SECONDS;
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
 * @param string $event    success|failed|twofactor|lockout.
 * @param string $user     Zadané uživatelské jméno nebo e-mail.
 * @param int    $duration Délka blokace v sekundách (lockout).
 */
function ekc_login_log_add( string $event, string $user, int $duration = 0 ): void {
	if ( ! ekc_opt( 'login_log' ) || ! in_array( $event, array( 'success', 'failed', 'twofactor', 'lockout' ), true ) ) {
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
	$since = time() - ekc_login_log_days() * DAY_IN_SECONDS;
	$log   = array_filter( array_slice( $log, 0, ekc_login_log_max() ), static fn( $row ) => is_array( $row ) && (int) ( $row['t'] ?? 0 ) >= $since );
	update_option( 'ekc_login_log', array_values( $log ), false );
}

/**
 * Neúspěšný pokus (během blokace z login-guard se nezapisuje). Chybný kód dvoufázového
 * ověření (chyba ekc_2fa_invalid z modulu two-factor) je samostatná událost twofactor.
 *
 * @param string        $username Zadané jméno.
 * @param WP_Error|null $error    Chyba.
 */
function ekc_login_log_failed( $username, $error = null ): void {
	if ( $error instanceof WP_Error && in_array( 'ekc_locked', $error->get_error_codes(), true ) ) {
		return;
	}
	$event = $error instanceof WP_Error && in_array( 'ekc_2fa_invalid', $error->get_error_codes(), true ) ? 'twofactor' : 'failed';
	ekc_login_log_add( $event, (string) $username );
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
		case 'twofactor':
			return ekc_status_dot( 'bad' ) . ' správné heslo, chybný kód 2FA';
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
	$all      = ekc_login_log( ekc_login_log_max() );
	$per_page = (int) ekc_filter( 'ekc_login_log_per_page', 50 );
	$per_page = max( 10, $per_page );
	$pages    = max( 1, (int) ceil( count( $all ) / $per_page ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen stránkování
	$paged = isset( $_GET['paged'] ) ? min( $pages, max( 1, absint( $_GET['paged'] ) ) ) : 1;
	$log   = array_slice( $all, ( $paged - 1 ) * $per_page, $per_page );
	echo '<div class="wrap"><h1>Log přihlášení</h1>';
	ekc_maintenance_notice();
	if ( ! ekc_opt( 'login_log' ) ) {
		echo '<div class="notice notice-warning inline"><p>Log přihlášení je vypnutý, níže jsou jen starší záznamy. <a href="' . esc_url( ekc_page_url( 'ekc-nastaveni' ) ) . '">Nastavení</a></p></div>';
	}

	/*
	 * Modul login-guard sem vypíše stav blokací a tlačítko pro jejich zrušení.
	 */
	do_action( 'ekc_login_log_before' );

	$nav = static function () use ( $all, $log, $paged, $pages, $per_page ): void {
		if ( ! $all ) {
			return;
		}
		$from = ( $paged - 1 ) * $per_page + 1;
		echo '<div class="tablenav"><div class="tablenav-pages"><span class="displaying-num">' . esc_html( sprintf( '%d–%d z %d', $from, $from + count( $log ) - 1, count( $all ) ) ) . '</span>';
		if ( $pages > 1 ) {
			echo '<span class="pagination-links ekc-pages">' . wp_kses_post(
				(string) paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', ekc_page_url( 'ekc-login-log' ) ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $pages,
						'prev_text' => '‹',
						'next_text' => '›',
						'mid_size'  => 2,
					)
				)
			) . '</span>';
		}
		echo '</div><br class="clear"></div>';
	};
	echo '<style>.ekc-pages .page-numbers{display:inline-block;min-width:30px;margin-left:3px;padding:0 6px;border:1px solid #2271b1;border-radius:3px;background:#f6f7f7;line-height:28px;text-align:center;text-decoration:none}.ekc-pages .page-numbers.current{border-color:#dcdcde;background:#fff;color:#50575e;font-weight:600}.ekc-pages .page-numbers.dots{border-color:transparent;background:none}</style>';
	$nav();
	echo '<table class="widefat striped"><thead><tr><th>Čas</th><th>Uživatel</th><th>Výsledek</th><th>IP adresa</th><th>Prohlížeč</th></tr></thead><tbody>';
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
	$nav();
	echo '<p class="description">' . esc_html( sprintf( 'Uchovává se posledních %d událostí, nejvýše %d dní.', ekc_login_log_max(), ekc_login_log_days() ) ) . ' <a href="' . esc_url( ekc_page_url( 'ekc-nastaveni' ) ) . '">Nastavení</a></p>';
	echo '</div>';
}
