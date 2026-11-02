<?php
/**
 * EKC module: login-guard
 * Version: 1.1.0
 * Requires: common
 * Replaces: limit-login-attempts-reloaded, loginizer, advanced-nocaptcha-recaptcha, all-in-one-wp-security-and-firewall
 * Summary: Limit pokusů o přihlášení s eskalací blokace, zbývajícími pokusy a zrušením blokací.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Po nastaveném počtu chybných pokusů během nastaveného okna (výchozí 15 minut) se IP adresa
 * zablokuje na nastavenou dobu, každá další blokace během 24 hodin je dvakrát delší (nejvýš 24 h).
 * Volitelně se pokusy počítají a blokují za celou síť (/24 u IPv4, /64 u IPv6) – proti útokům,
 * které střídají adresy v jednom rozsahu a z jedné adresy zkusí jen jednou dvakrát.
 * Platí pro wp-login.php i další místa, kde se ověřuje heslo (aplikační hesla, XML-RPC),
 * a pro chybné kódy dvoufázového ověření (modul two-factor). Během blokace se pokusy
 * nepočítají ani neprodlužují blokaci.
 *
 * Počítadla i blokace jsou v jedné option ekc_lockouts (bez autoloadu), ne v transientech:
 * s perzistentní object cache se transienty nedají vypsat ani hromadně smazat.
 * Formát: [ klíč => { f: chybné pokusy v okně, s: začátek okna, l: blokace do, n: počet blokací
 * za 24 h, w: čas poslední blokace, u: poslední zadané jméno, i: poslední IP } ]. Klíč je IP adresa,
 * při blokaci sítí rozsah (203.0.113.0/24, 2001:db8:1:2::/64).
 *
 * Chybové hlášky přepisuje jen pro chybné jméno, e-mail nebo heslo (vlastní kód ekc_failed)
 * a blokaci (ekc_locked); ostatní chyby (prázdné heslo, 2FA, jiné pluginy) nechává být.
 */

const EKC_LOGIN_WINDOW      = 15 * MINUTE_IN_SECONDS; // výchozí okno pro počítání chybných pokusů
const EKC_LOGIN_LOCKOUT_MAX = DAY_IN_SECONDS;         // nejdelší blokace
const EKC_LOCKOUTS_MAX      = 500;                    // nejvýš sledovaných IP adres

ekc_settings_register(
	'login_protection',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Omezení pokusů o přihlášení',
		'help'    => 'Po opakovaných chybných pokusech z jedné IP adresy (nebo sítě, viz níže) se přihlášení dočasně zablokuje. Chybové hlášky neprozradí, jestli uživatel existuje.',
		'section' => 'Přihlášení',
	)
);
ekc_settings_register(
	'login_attempts',
	array(
		'type'    => 'int',
		'default' => 5,
		'label'   => 'Počet pokusů',
		'help'    => 'Kolik chybných pokusů během okna pro počítání je povoleno.',
		'section' => 'Přihlášení',
		'min'     => 3,
		'max'     => 20,
		'unit'    => 'pokusů',
	)
);
ekc_settings_register(
	'login_window',
	array(
		'type'    => 'int',
		'default' => 15,
		'label'   => 'Okno pro počítání pokusů',
		'help'    => 'Za jak dlouhou dobu se chybné pokusy sčítají. Delší okno zachytí i pomalé útoky (pokus každých 10 minut), např. 60–240 minut.',
		'section' => 'Přihlášení',
		'min'     => 5,
		'max'     => 1440,
		'unit'    => 'minut',
	)
);
ekc_settings_register(
	'login_subnet',
	array(
		'type'    => 'bool',
		'default' => false,
		'label'   => 'Blokovat celou síť',
		'help'    => 'Pokusy se počítají a blokují za celý rozsah /24 (IPv4) nebo /64 (IPv6), ne za jednu adresu. Proti útokům, které střídají adresy v jedné síti. Může zablokovat i sousedy ve stejné síti (mobilní operátor, firma).',
		'section' => 'Přihlášení',
	)
);
ekc_settings_register(
	'login_lockout',
	array(
		'type'    => 'int',
		'default' => 30,
		'label'   => 'Délka blokace',
		'help'    => 'Každá další blokace během 24 hodin je dvakrát delší (nejvýš 24 h). Zrušit ji jde v Nástroje → Údržba webu nebo v logu přihlášení.',
		'section' => 'Přihlášení',
		'min'     => 5,
		'max'     => 1440,
		'unit'    => 'minut',
	)
);

/* -------------------------------------------------------------------------
 * Data blokací
 * ---------------------------------------------------------------------- */

/**
 * Okno pro počítání chybných pokusů v sekundách.
 *
 * @return int
 */
function ekc_login_window(): int {
	$minutes = (int) ekc_opt( 'login_window' );
	return $minutes > 0 ? $minutes * MINUTE_IN_SECONDS : EKC_LOGIN_WINDOW;
}

/**
 * Síť, do které IP adresa patří: /24 u IPv4, /64 u IPv6.
 *
 * @param string $ip IP adresa.
 * @return string Např. 203.0.113.0/24, při neplatné adrese ''.
 */
function ekc_ip_subnet( string $ip ): string {
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		return (string) preg_replace( '/\.\d+$/', '.0', $ip ) . '/24';
	}
	$bin = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? inet_pton( $ip ) : false;
	if ( false === $bin || 16 !== strlen( $bin ) ) {
		return '';
	}
	return (string) inet_ntop( substr( $bin, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
}

/**
 * Klíč, pod kterým se počítají pokusy a blokace: IP adresa, nebo její síť (nastavení login_subnet).
 *
 * @param string $ip IP adresa, '' = aktuální návštěvník.
 * @return string
 */
function ekc_login_guard_key( string $ip = '' ): string {
	$ip = '' !== $ip ? $ip : ekc_client_ip();
	if ( ekc_opt( 'login_subnet' ) ) {
		$net = ekc_ip_subnet( $ip );
		if ( '' !== $net ) {
			return $net;
		}
	}
	return $ip;
}

/**
 * Platný klíč blokace (IP adresa nebo síť ve tvaru adresa/prefix)?
 *
 * @param string $key Klíč.
 * @return bool
 */
function ekc_login_guard_valid_key( string $key ): bool {
	if ( filter_var( $key, FILTER_VALIDATE_IP ) ) {
		return true;
	}
	return (bool) preg_match( '#^([0-9a-f:.]+)/(24|64)$#i', $key, $m ) && filter_var( $m[1], FILTER_VALIDATE_IP );
}

/**
 * Sledované IP adresy (bez prošlých záznamů).
 *
 * @return array<string,array>
 */
function ekc_lockouts(): array {
	$data = get_option( 'ekc_lockouts', array() );
	$data   = is_array( $data ) ? $data : array();
	$now    = time();
	$window = max( ekc_login_window(), EKC_LOGIN_WINDOW );
	foreach ( $data as $ip => $row ) {
		if ( ! is_array( $row ) ) {
			unset( $data[ $ip ] );
			continue;
		}
		$fails_alive = (int) ( $row['s'] ?? 0 ) + $window > $now && (int) ( $row['f'] ?? 0 ) > 0;
		$lock_alive  = (int) ( $row['l'] ?? 0 ) > $now;
		$count_alive = (int) ( $row['w'] ?? 0 ) + DAY_IN_SECONDS > $now;
		if ( ! $fails_alive && ! $lock_alive && ! $count_alive ) {
			unset( $data[ $ip ] );
		}
	}
	return $data;
}

/**
 * Uloží sledované IP adresy (bez autoloadu; prázdné = smazat option).
 *
 * @param array $data Data.
 */
function ekc_lockouts_save( array $data ): void {
	if ( ! $data ) {
		delete_option( 'ekc_lockouts' );
		return;
	}
	if ( count( $data ) > EKC_LOCKOUTS_MAX ) {
		// Při útoku z mnoha adres nechat ty s nejnovější aktivitou.
		uasort( $data, static fn( $a, $b ) => max( (int) ( $b['s'] ?? 0 ), (int) ( $b['w'] ?? 0 ) ) <=> max( (int) ( $a['s'] ?? 0 ), (int) ( $a['w'] ?? 0 ) ) );
		$data = array_slice( $data, 0, EKC_LOCKOUTS_MAX, true );
	}
	update_option( 'ekc_lockouts', $data, false );
}

/**
 * Do kdy je IP zablokovaná (unix čas), 0 = není. Kontroluje adresu i její síť.
 *
 * @param string $ip IP adresa.
 * @return int
 */
function ekc_login_locked_until( string $ip ): int {
	$data  = ekc_lockouts();
	$net   = ekc_ip_subnet( $ip );
	$until = max( (int) ( $data[ $ip ]['l'] ?? 0 ), '' !== $net ? (int) ( $data[ $net ]['l'] ?? 0 ) : 0 );
	return $until > time() ? $until : 0;
}

/**
 * Počet aktuálně zablokovaných IP adres (čte ho widget Zdraví webu).
 *
 * @return int
 */
function ekc_lockouts_active(): int {
	$now = time();
	return count( array_filter( ekc_lockouts(), static fn( $row ) => (int) ( $row['l'] ?? 0 ) > $now ) );
}

/**
 * Zruší blokaci jedné IP adresy nebo sítě, nebo všech (i s počítadly).
 *
 * @param string $ip IP adresa nebo síť (klíč), '' = všechny.
 * @return int Počet zrušených blokací.
 */
function ekc_lockouts_clear( string $ip = '' ): int {
	$data = ekc_lockouts();
	$now  = time();
	if ( '' === $ip ) {
		$count = count( array_filter( $data, static fn( $row ) => (int) ( $row['l'] ?? 0 ) > $now ) );
		ekc_lockouts_save( array() );
		return $count;
	}
	if ( ! isset( $data[ $ip ] ) ) {
		return 0;
	}
	$count = (int) ( $data[ $ip ]['l'] ?? 0 ) > $now ? 1 : 0;
	unset( $data[ $ip ] );
	ekc_lockouts_save( $data );
	return $count;
}

/**
 * Délka blokace v sekundách pro n-tou blokaci za 24 h.
 *
 * @param int $n Pořadí blokace (1 = první).
 * @return int
 */
function ekc_login_lockout_duration( int $n ): int {
	$base = max( 5, (int) ekc_opt( 'login_lockout' ) ) * MINUTE_IN_SECONDS;
	return (int) min( $base * ( 2 ** max( 0, $n - 1 ) ), EKC_LOGIN_LOCKOUT_MAX );
}

/**
 * Délka čitelně (min nebo h).
 *
 * @param int $seconds Sekundy.
 * @return string
 */
function ekc_login_duration_label( int $seconds ): string {
	$minutes = max( 1, (int) ceil( $seconds / MINUTE_IN_SECONDS ) );
	if ( $minutes < 120 ) {
		return $minutes . ' min';
	}
	return number_format_i18n( $minutes / 60, 0 === $minutes % 60 ? 0 : 1 ) . ' h';
}

/* -------------------------------------------------------------------------
 * Přihlášení
 * ---------------------------------------------------------------------- */

/**
 * Zablokovaná IP se nepřihlásí ani správným heslem. Chybné jméno, e-mail i heslo mají stejnou
 * hlášku (neprozradí, jestli uživatel existuje), u posledních dvou pokusů se zbývajícím počtem.
 *
 * @param WP_User|WP_Error|null $user     Výsledek ověření.
 * @param string                $username Uživatelské jméno.
 * @return WP_User|WP_Error|null
 */
function ekc_login_guard_authenticate( $user, $username ) {
	if ( ! ekc_opt( 'login_protection' ) ) {
		return $user;
	}
	$key   = ekc_login_guard_key();
	$data  = ekc_lockouts();
	$row   = $data[ $key ] ?? array();
	$until = max( (int) ( $row['l'] ?? 0 ), ekc_login_locked_until( ekc_client_ip() ) );

	if ( $until > time() ) {
		return new WP_Error(
			'ekc_locked',
			'<strong>Chyba:</strong> Příliš mnoho neúspěšných pokusů o přihlášení. Zkuste to znovu za ' . esc_html( ekc_login_duration_label( $until - time() ) ) . '.'
		);
	}

	if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
		$fails = ( (int) ( $row['s'] ?? 0 ) + ekc_login_window() > time() ? (int) ( $row['f'] ?? 0 ) : 0 ) + 1;
		$left  = (int) ekc_opt( 'login_attempts' ) - $fails;
		$text  = '<strong>Chyba:</strong> Nesprávné uživatelské jméno, e-mail nebo heslo.';
		if ( $left <= 0 ) {
			$recent = (int) ( $row['w'] ?? 0 ) + DAY_IN_SECONDS > time() ? (int) ( $row['n'] ?? 0 ) : 0;
			$text   = '<strong>Chyba:</strong> Příliš mnoho neúspěšných pokusů o přihlášení. Přihlášení je zablokované na ' . esc_html( ekc_login_duration_label( ekc_login_lockout_duration( $recent + 1 ) ) ) . '.';
		} elseif ( $left <= 2 ) {
			$text .= ' ' . ekc_plural( $left, 'Zbývá %d pokus.', 'Zbývají %d pokusy.', 'Zbývá %d pokusů.' );
		}
		return new WP_Error( 'ekc_failed', $text );
	}

	return $user;
}
add_filter( 'authenticate', 'ekc_login_guard_authenticate', 99, 2 );

/**
 * Neúspěšný pokus: započítat a po limitu zablokovat. Během blokace se nic nepočítá.
 *
 * @param string        $username Zadané jméno.
 * @param WP_Error|null $error    Chyba (WP 5.4+).
 */
function ekc_login_guard_failed( $username, $error = null ): void {
	if ( ! ekc_opt( 'login_protection' ) ) {
		return;
	}
	if ( $error instanceof WP_Error && in_array( 'ekc_locked', $error->get_error_codes(), true ) ) {
		return;
	}
	$ip   = ekc_client_ip();
	$key  = ekc_login_guard_key( $ip );
	$now  = time();
	$data = ekc_lockouts();
	$row  = array_merge(
		array(
			'f' => 0,
			's' => 0,
			'l' => 0,
			'n' => 0,
			'w' => 0,
			'u' => '',
			'i' => '',
		),
		(array) ( $data[ $key ] ?? array() )
	);
	if ( (int) $row['l'] > $now || ekc_login_locked_until( $ip ) ) {
		return; // blokace běží – nepočítat ani neprodlužovat
	}
	if ( (int) $row['s'] + ekc_login_window() <= $now ) {
		$row['f'] = 0;
		$row['s'] = $now;
	}
	$row['f'] = (int) $row['f'] + 1;
	$row['u'] = mb_substr( sanitize_user( (string) $username, false ), 0, 60 );
	$row['i'] = $ip;

	$duration = 0;
	if ( $row['f'] >= (int) ekc_opt( 'login_attempts' ) ) {
		$row['n']  = (int) $row['w'] + DAY_IN_SECONDS > $now ? (int) $row['n'] + 1 : 1;
		$duration  = ekc_login_lockout_duration( (int) $row['n'] );
		$row['l']  = $now + $duration;
		$row['w']  = $now;
		$row['f']  = 0;
		$row['s']  = 0;
	}
	$data[ $key ] = $row;
	ekc_lockouts_save( $data );

	if ( $duration && function_exists( 'ekc_login_log_add' ) ) {
		ekc_login_log_add( 'lockout', (string) $username, $duration );
	}
}
add_action( 'wp_login_failed', 'ekc_login_guard_failed', 10, 2 );

/**
 * Úspěšné přihlášení vynuluje chybné pokusy (počet blokací za 24 h zůstává).
 */
function ekc_login_guard_success(): void {
	$key  = ekc_login_guard_key();
	$data = ekc_lockouts();
	if ( empty( $data[ $key ]['f'] ) ) {
		return;
	}
	$data[ $key ]['f'] = 0;
	$data[ $key ]['s'] = 0;
	ekc_lockouts_save( ekc_lockouts_prune_row( $data, $key ) );
}
add_action( 'wp_login', 'ekc_login_guard_success' );

/**
 * Odebere záznam IP, když už nenese žádnou informaci.
 *
 * @param array  $data Data.
 * @param string $ip   IP adresa.
 * @return array
 */
function ekc_lockouts_prune_row( array $data, string $ip ): array {
	$row = $data[ $ip ] ?? array();
	if ( (int) ( $row['l'] ?? 0 ) <= time() && (int) ( $row['w'] ?? 0 ) + DAY_IN_SECONDS <= time() && empty( $row['f'] ) ) {
		unset( $data[ $ip ] );
	}
	return $data;
}

/* -------------------------------------------------------------------------
 * Zrušení blokací: sekce v Údržbě webu a stav nad logem přihlášení
 * ---------------------------------------------------------------------- */

/**
 * Úloha Údržby: zrušit blokaci jedné IP ($item) nebo všech.
 *
 * @param string $item IP adresa, nebo ''.
 * @return array
 */
function ekc_login_guard_unlock( string $item = '' ): array {
	$ip    = ekc_login_guard_valid_key( $item ) ? $item : '';
	$count = ekc_lockouts_clear( $ip );
	return array(
		'message' => '' !== $ip
			? ( $count ? 'Blokace ' . $ip . ' byla zrušena.' : $ip . ' už zablokovaná nebyla.' )
			: ( $count ? 'Zrušeno blokací přihlášení: ' . $count . '.' : 'Žádná IP adresa nebyla zablokovaná, počítadla jsou vynulovaná.' ),
		'mark'    => false,
	);
}

/**
 * Sekce Údržby: zablokované IP adresy.
 *
 * @param string $id ID sekce.
 */
function ekc_login_guard_render( string $id ): void {
	$now    = time();
	$locked = array_filter( ekc_lockouts(), static fn( $row ) => (int) ( $row['l'] ?? 0 ) > $now );
	uasort( $locked, static fn( $a, $b ) => (int) $b['l'] <=> (int) $a['l'] );

	echo '<p>';
	if ( ekc_opt( 'login_protection' ) ) {
		echo esc_html( ekc_login_guard_summary() . sprintf( ' na %d min, každá další blokace během 24 h je dvakrát delší.', (int) ekc_opt( 'login_lockout' ) ) );
	} else {
		echo ekc_status_dot( 'warn' ) . ' Omezení pokusů o přihlášení je vypnuté.'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( function_exists( 'ekc_login_log' ) ) {
		echo ' <a href="' . esc_url( ekc_page_url( 'ekc-login-log' ) ) . '">Log přihlášení</a>';
	}
	echo '</p>';

	if ( ! $locked ) {
		echo '<p>' . ekc_status_dot( 'ok' ) . ' Žádná IP adresa ani síť není zablokovaná.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th>IP adresa / síť</th><th>Zablokováno do</th><th>Blokací za 24 h</th><th>Poslední jméno</th><th>Poslední IP</th><th></th></tr></thead><tbody>';
	foreach ( $locked as $ip => $row ) {
		echo '<tr><td><code>' . esc_html( (string) $ip ) . '</code></td><td>' . esc_html( ekc_datetime( (int) $row['l'] ) ) . '</td><td>' . (int) ( $row['n'] ?? 0 ) . '</td><td>' . esc_html( (string) ( $row['u'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row['i'] ?? '' ) ) . '</td><td>';
		ekc_maintenance_button( $id, 'unlock', 'Zrušit', '', false, (string) $ip );
		echo '</td></tr>';
	}
	echo '</tbody></table><p>';
	ekc_maintenance_button( $id, 'unlock', 'Zrušit všechny blokace', 'Zrušit všechny blokace přihlášení?' );
	echo '</p>';
}

ekc_maintenance_section(
	'login',
	array(
		'title'  => 'Přihlášení',
		'order'  => 85,
		'render' => 'ekc_login_guard_render',
		'tasks'  => array(
			'unlock' => 'ekc_login_guard_unlock',
		),
	)
);

/**
 * Popis pravidla: „Po 5 neúspěšných pokusech během 60 minut se zablokuje celá síť (/24)“.
 *
 * @return string
 */
function ekc_login_guard_summary(): string {
	$minutes = (int) ( ekc_login_window() / MINUTE_IN_SECONDS );
	$window  = $minutes < 120 ? $minutes . ' minut' : ekc_login_duration_label( $minutes * MINUTE_IN_SECONDS );
	return sprintf(
		'Po %1$d neúspěšných pokusech během %2$s se zablokuje %3$s',
		(int) ekc_opt( 'login_attempts' ),
		$window,
		ekc_opt( 'login_subnet' ) ? 'celá síť (/24, u IPv6 /64)' : 'IP adresa'
	);
}

/**
 * Stav blokací a tlačítko nad tabulkou logu přihlášení (akce modulu login-log).
 */
function ekc_login_guard_log_status(): void {
	$active = ekc_lockouts_active();
	echo '<p>';
	if ( ekc_opt( 'login_protection' ) ) {
		echo esc_html( ekc_login_guard_summary() . sprintf( ' dočasně. Aktuálně zablokováno: %d.', $active ) );
	} else {
		echo 'Omezení pokusů o přihlášení je vypnuté.';
	}
	echo '</p>';
	if ( $active ) {
		ekc_maintenance_button( 'login', 'unlock', 'Zrušit všechny blokace', 'Zrušit všechny blokace přihlášení?' );
		echo ' <a href="' . esc_url( ekc_maintenance_url( array(), 'login' ) ) . '">Zablokované adresy</a>';
	}
}
add_action( 'ekc_login_log_before', 'ekc_login_guard_log_status' );
