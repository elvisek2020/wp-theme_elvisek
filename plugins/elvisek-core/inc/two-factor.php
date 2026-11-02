<?php
/**
 * EKC module: two-factor
 * Version: 1.1.0
 * Requires: common
 * Replaces: two-factor, wp-2fa, wordfence-login-security, google-authenticator
 * Summary: Dvoufázové ověření kódem z aplikace (TOTP) se záložními kódy, volitelně povinné pro správce.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Uživatel si ověření zapne v profilu: naskenuje QR kód do aplikace (Google Authenticator,
 * Microsoft Authenticator, 1Password, Bitwarden…) a potvrdí ho prvním kódem. Dostane 10 záložních
 * kódů na jedno použití. Po správném hesle na wp-login.php se pak místo přihlášení zobrazí formulář
 * na šestimístný kód (TOTP podle RFC 6238: SHA-1, 30 s, tolerance ±1 krok, kód jde použít jen
 * jednou). QR kód kreslí modul sám (SVG), nic se neposílá ven.
 *
 * Aplikační hesla (REST, wp.py, mobilní aplikace) dvoufázovým ověřením neprochází. Ostatní cesty,
 * kde se ověřuje heslo (XML-RPC, formuláře jiných pluginů přes wp_signon), účet s ověřením odmítnou.
 * Chybné kódy se počítají do blokace (modul login-guard, akce wp_login_failed s chybou
 * ekc_2fa_invalid) a do logu jako událost twofactor (modul login-log).
 *
 * User meta (mažou se s pluginem, prefix ekc_):
 * - ekc_2fa_secret  – tajný klíč v base32; vyplněný = ověření zapnuté,
 * - ekc_2fa_setup   – klíč při zapínání (do potvrzení prvním kódem),
 * - ekc_2fa_backup  – hashe nepoužitých záložních kódů,
 * - ekc_2fa_fresh   – nové záložní kódy k jednomu zobrazení v profilu,
 * - ekc_2fa_last    – poslední použitý časový krok (kód nejde použít dvakrát),
 * - ekc_2fa_since   – kdy bylo ověření zapnuto,
 * - ekc_2fa_pending – rozpracované přihlášení {h: hash tokenu, x: platnost, r: zapamatovat,
 *                     t: adresa po přihlášení, i: přihlášení v okně administrace, n: chybné kódy}.
 *
 * Ztracený telefon i záložní kódy: jiný správce vypne ověření v profilu uživatele, jinak
 * WP-CLI `wp user meta delete <uživatel> ekc_2fa_secret`, případně dočasně vypnout nastavení
 * two_factor (nebo smazat soubor modulu přes FTP).
 */

const EKC_2FA_PERIOD  = 30;                    // délka kroku TOTP v sekundách
const EKC_2FA_DIGITS  = 6;                     // délka kódu
const EKC_2FA_WINDOW  = 1;                     // tolerance ± kroků (rozdíl hodin)
const EKC_2FA_PENDING = 10 * MINUTE_IN_SECONDS; // jak dlouho platí formulář na kód
const EKC_2FA_TRIES   = 5;                     // chybných kódů na jedno přihlášení
const EKC_2FA_BACKUP  = 10;                    // počet záložních kódů

ekc_settings_register(
	'two_factor',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Povolit uživatelům',
		'help'    => 'Uživatelé si v profilu můžou zapnout ověření kódem z aplikace v telefonu (Google Authenticator, 1Password, Bitwarden…). Po heslu se pak zadává šestimístný kód. Vypnutím se ověření vypne všem.',
		'section' => 'Přihlášení',
		'group'   => 'Dvoufázové ověření',
	)
);
ekc_settings_register(
	'two_factor_required',
	array(
		'type'    => 'bool',
		'default' => false,
		'label'   => 'Vyžadovat u správců',
		'help'    => 'Správce bez dvoufázového ověření se po přihlášení dostane jen do svého profilu, dokud si ho nezapne.',
		'section' => 'Přihlášení',
		'group'   => 'Dvoufázové ověření',
		'parent'  => 'two_factor',
	)
);

/* -------------------------------------------------------------------------
 * TOTP, base32 a záložní kódy
 * ---------------------------------------------------------------------- */

/**
 * Base32 podle RFC 4648 bez zarovnání.
 *
 * @param string $bin Binární data.
 * @return string
 */
function ekc_2fa_base32_encode( string $bin ): string {
	$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$bits     = '';
	foreach ( str_split( $bin ) as $char ) {
		$bits .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
	}
	$out = '';
	foreach ( str_split( $bits, 5 ) as $chunk ) {
		$out .= $alphabet[ bindec( str_pad( $chunk, 5, '0' ) ) ];
	}
	return $out;
}

/**
 * Dekódování base32 (mezery, pomlčky a velikost písmen nevadí).
 *
 * @param string $text Base32.
 * @return string Binární data, při chybě ''.
 */
function ekc_2fa_base32_decode( string $text ): string {
	$text = strtoupper( (string) preg_replace( '/[\s=-]+/', '', $text ) );
	if ( '' === $text || preg_match( '/[^A-Z2-7]/', $text ) ) {
		return '';
	}
	$bits = '';
	foreach ( str_split( $text ) as $char ) {
		$bits .= str_pad( decbin( (int) strpos( 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char ) ), 5, '0', STR_PAD_LEFT );
	}
	$out = '';
	foreach ( str_split( $bits, 8 ) as $byte ) {
		if ( 8 === strlen( $byte ) ) {
			$out .= chr( (int) bindec( $byte ) );
		}
	}
	return $out;
}

/**
 * Nový tajný klíč (160 bitů).
 *
 * @return string Base32.
 */
function ekc_2fa_new_secret(): string {
	return ekc_2fa_base32_encode( random_bytes( 20 ) );
}

/**
 * Kód TOTP pro daný časový krok.
 *
 * @param string $secret Tajný klíč v base32.
 * @param int    $step   Časový krok (unix čas / 30).
 * @return string
 */
function ekc_2fa_totp( string $secret, int $step ): string {
	$hash   = hash_hmac( 'sha1', pack( 'J', $step ), ekc_2fa_base32_decode( $secret ), true );
	$offset = ord( $hash[19] ) & 0x0f;
	$number = ( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 ) | ( ord( $hash[ $offset + 1 ] ) << 16 ) | ( ord( $hash[ $offset + 2 ] ) << 8 ) | ord( $hash[ $offset + 3 ] );
	return str_pad( (string) ( $number % ( 10 ** EKC_2FA_DIGITS ) ), EKC_2FA_DIGITS, '0', STR_PAD_LEFT );
}

/**
 * Ověří kód TOTP v toleranci ± EKC_2FA_WINDOW kroků; kroky do $last (už použité) neplatí.
 *
 * @param string $secret Tajný klíč.
 * @param string $code   Zadaný kód.
 * @param int    $last   Poslední použitý krok.
 * @return int Krok, kterému kód odpovídá, 0 = neplatný.
 */
function ekc_2fa_check_totp( string $secret, string $code, int $last = 0 ): int {
	$code = (string) preg_replace( '/\D+/', '', $code );
	if ( '' === $secret || EKC_2FA_DIGITS !== strlen( $code ) ) {
		return 0;
	}
	$now = intdiv( time(), EKC_2FA_PERIOD );
	for ( $delta = -EKC_2FA_WINDOW; $delta <= EKC_2FA_WINDOW; $delta++ ) {
		$step = $now + $delta;
		if ( $step > $last && hash_equals( ekc_2fa_totp( $secret, $step ), $code ) ) {
			return $step;
		}
	}
	return 0;
}

/**
 * Normalizovaný záložní kód (malá písmena a číslice bez oddělovačů).
 *
 * @param string $code Kód.
 * @return string
 */
function ekc_2fa_backup_normalize( string $code ): string {
	return (string) preg_replace( '/[^a-z0-9]+/', '', strtolower( $code ) );
}

/**
 * Vygeneruje nové záložní kódy, uloží jejich hashe a vrátí je (zobrazí se jednou).
 *
 * @param int $user_id ID uživatele.
 * @return string[] Kódy ve tvaru abcd-efgh.
 */
function ekc_2fa_backup_generate( int $user_id ): array {
	$alphabet = 'abcdefghjkmnpqrstuvwxyz23456789'; // bez 0/o, 1/l/i
	$codes    = array();
	$hashes   = array();
	for ( $i = 0; $i < EKC_2FA_BACKUP; $i++ ) {
		$code = '';
		for ( $j = 0; $j < 8; $j++ ) {
			$code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}
		$codes[]  = substr( $code, 0, 4 ) . '-' . substr( $code, 4 );
		$hashes[] = wp_hash_password( $code );
	}
	update_user_meta( $user_id, 'ekc_2fa_backup', $hashes );
	update_user_meta( $user_id, 'ekc_2fa_fresh', $codes );
	return $codes;
}

/**
 * Počet nepoužitých záložních kódů.
 *
 * @param int $user_id ID uživatele.
 * @return int
 */
function ekc_2fa_backup_left( int $user_id ): int {
	return count( (array) get_user_meta( $user_id, 'ekc_2fa_backup', true ) );
}

/**
 * Ověří a spotřebuje záložní kód.
 *
 * @param int    $user_id ID uživatele.
 * @param string $code    Zadaný kód.
 * @return bool
 */
function ekc_2fa_backup_use( int $user_id, string $code ): bool {
	$code = ekc_2fa_backup_normalize( $code );
	if ( 8 !== strlen( $code ) ) {
		return false;
	}
	$hashes = array_values( array_filter( (array) get_user_meta( $user_id, 'ekc_2fa_backup', true ), 'is_string' ) );
	foreach ( $hashes as $i => $hash ) {
		if ( wp_check_password( $code, $hash ) ) {
			unset( $hashes[ $i ] );
			update_user_meta( $user_id, 'ekc_2fa_backup', array_values( $hashes ) );
			return true;
		}
	}
	return false;
}

/* -------------------------------------------------------------------------
 * Stav uživatele
 * ---------------------------------------------------------------------- */

/**
 * Má uživatel zapnuté dvoufázové ověření (a je modul zapnutý)?
 *
 * @param int $user_id ID uživatele.
 * @return bool
 */
function ekc_2fa_enabled( int $user_id ): bool {
	return ekc_opt( 'two_factor' ) && '' !== (string) get_user_meta( $user_id, 'ekc_2fa_secret', true );
}

/**
 * Ověří kód z aplikace nebo záložní kód a zapíše jeho použití.
 *
 * @param int    $user_id ID uživatele.
 * @param string $code    Zadaný kód.
 * @return bool
 */
function ekc_2fa_verify_code( int $user_id, string $code ): bool {
	$secret = (string) get_user_meta( $user_id, 'ekc_2fa_secret', true );
	$step   = ekc_2fa_check_totp( $secret, $code, (int) get_user_meta( $user_id, 'ekc_2fa_last', true ) );
	if ( $step ) {
		update_user_meta( $user_id, 'ekc_2fa_last', $step );
		return true;
	}
	return ekc_2fa_backup_use( $user_id, $code );
}

/**
 * Zapne ověření s potvrzeným klíčem a vygeneruje záložní kódy.
 *
 * @param int    $user_id ID uživatele.
 * @param string $secret  Tajný klíč.
 * @param int    $step    Krok, kterým byl klíč potvrzen.
 */
function ekc_2fa_activate( int $user_id, string $secret, int $step ): void {
	update_user_meta( $user_id, 'ekc_2fa_secret', $secret );
	update_user_meta( $user_id, 'ekc_2fa_last', $step );
	update_user_meta( $user_id, 'ekc_2fa_since', time() );
	delete_user_meta( $user_id, 'ekc_2fa_setup' );
	ekc_2fa_backup_generate( $user_id );
}

/**
 * Vypne ověření a smaže jeho data.
 *
 * @param int $user_id ID uživatele.
 */
function ekc_2fa_deactivate( int $user_id ): void {
	foreach ( array( 'secret', 'setup', 'backup', 'fresh', 'last', 'since', 'pending' ) as $key ) {
		delete_user_meta( $user_id, 'ekc_2fa_' . $key );
	}
}

/* -------------------------------------------------------------------------
 * Přihlášení: po správném hesle formulář na kód
 * ---------------------------------------------------------------------- */

/**
 * Jde o odeslaný přihlašovací formulář wp-login.php?
 *
 * @return bool
 */
function ekc_2fa_is_login_form(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- jen zjišťuje, odkud přihlášení přišlo
	return 'wp-login.php' === ( $GLOBALS['pagenow'] ?? '' ) && isset( $_POST['log'] ) && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );
}

/**
 * Po správném hesle místo přihlášení formulář na kód. Aplikační hesla projdou, ostatní cesty
 * mimo přihlašovací stránku účet s ověřením odmítnou.
 *
 * @param WP_User|WP_Error|null $user Výsledek ověření.
 * @return WP_User|WP_Error|null
 */
function ekc_2fa_authenticate( $user ) {
	if ( ! $user instanceof WP_User || ! ekc_2fa_enabled( $user->ID ) || did_action( 'application_password_did_authenticate' ) ) {
		return $user;
	}
	if ( ! ekc_2fa_is_login_form() || ! function_exists( 'login_header' ) ) {
		return new WP_Error( 'ekc_2fa_required', '<strong>Chyba:</strong> Účet má zapnuté dvoufázové ověření. Přihlaste se na přihlašovací stránce webu, nebo použijte aplikační heslo.' );
	}
	// phpcs:disable WordPress.Security.NonceVerification -- přihlašovací formulář WordPressu nonce nemá
	$token   = wp_generate_password( 40, false );
	$pending = array(
		'h' => wp_hash( $token ),
		'x' => time() + EKC_2FA_PENDING,
		'r' => ! empty( $_POST['rememberme'] ) ? 1 : 0,
		't' => isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '',
		'i' => ! empty( $_REQUEST['interim-login'] ) ? 1 : 0,
		'n' => 0,
	);
	// phpcs:enable
	update_user_meta( $user->ID, 'ekc_2fa_pending', $pending );
	ekc_2fa_form( $user, $token, $pending );
	exit;
}
add_filter( 'authenticate', 'ekc_2fa_authenticate', 100 );

/**
 * Rozpracované přihlášení pro token (platné, jinak null).
 *
 * @param int    $user_id ID uživatele.
 * @param string $token   Token z formuláře.
 * @return array|null
 */
function ekc_2fa_pending( int $user_id, string $token ): ?array {
	$pending = get_user_meta( $user_id, 'ekc_2fa_pending', true );
	if ( ! is_array( $pending ) || '' === $token || ! hash_equals( (string) ( $pending['h'] ?? '' ), wp_hash( $token ) ) ) {
		return null;
	}
	if ( (int) ( $pending['x'] ?? 0 ) < time() ) {
		delete_user_meta( $user_id, 'ekc_2fa_pending' );
		return null;
	}
	return $pending;
}

/**
 * Formulář na kód (vykresluje se uvnitř wp-login.php).
 *
 * @param WP_User $user    Uživatel.
 * @param string  $token   Token rozpracovaného přihlášení.
 * @param array   $pending Rozpracované přihlášení.
 * @param string  $error   Chybová hláška (HTML).
 */
function ekc_2fa_form( WP_User $user, string $token, array $pending, string $error = '' ): void {
	global $interim_login;
	$interim_login = ! empty( $pending['i'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- tak to dělá wp-login.php
	nocache_headers();
	login_header( 'Dvoufázové ověření', '', '' !== $error ? new WP_Error( 'ekc_2fa', $error ) : null );
	?>
	<form name="ekc2faform" id="loginform" action="<?php echo esc_url( add_query_arg( 'action', 'ekc_2fa', wp_login_url() ) ); ?>" method="post" autocomplete="off">
		<p><?php echo esc_html( 'Zadejte šestimístný kód z ověřovací aplikace. Když nemáte telefon po ruce, použijte jeden ze záložních kódů.' ); ?></p>
		<p>
			<label for="ekc_2fa_code">Kód</label>
			<input type="text" name="ekc_2fa_code" id="ekc_2fa_code" class="input" value="" size="20" inputmode="numeric" autocomplete="one-time-code" spellcheck="false" autocapitalize="off" required>
		</p>
		<input type="hidden" name="ekc_2fa_user" value="<?php echo (int) $user->ID; ?>">
		<input type="hidden" name="ekc_2fa_token" value="<?php echo esc_attr( $token ); ?>">
		<?php if ( ! empty( $pending['i'] ) ) : ?>
			<input type="hidden" name="interim-login" value="1">
		<?php endif; ?>
		<p class="submit">
			<input type="submit" class="button button-primary button-large" value="Ověřit">
		</p>
	</form>
	<?php
	login_footer( 'ekc_2fa_code' );
}

/**
 * Přesměrování zpět na přihlášení s hláškou (expired|locked).
 *
 * @param string $reason Důvod.
 */
function ekc_2fa_back_to_login( string $reason ): void {
	wp_safe_redirect( add_query_arg( 'ekc_2fa', $reason, wp_login_url() ) );
	exit;
}

/**
 * Odeslaný kód (wp-login.php?action=ekc_2fa).
 */
function ekc_2fa_verify(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- místo nonce jednorázový token rozpracovaného přihlášení
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! ekc_opt( 'two_factor' ) ) {
		ekc_2fa_back_to_login( 'expired' );
	}
	$user_id = isset( $_POST['ekc_2fa_user'] ) ? absint( $_POST['ekc_2fa_user'] ) : 0;
	$token   = isset( $_POST['ekc_2fa_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ekc_2fa_token'] ) ) : '';
	$code    = isset( $_POST['ekc_2fa_code'] ) ? sanitize_text_field( wp_unslash( $_POST['ekc_2fa_code'] ) ) : '';
	// phpcs:enable
	$user    = $user_id ? get_userdata( $user_id ) : false;
	$pending = $user ? ekc_2fa_pending( $user_id, $token ) : null;
	if ( ! $user || ! $pending ) {
		ekc_2fa_back_to_login( 'expired' );
	}
	$locked = static fn(): bool => function_exists( 'ekc_login_locked_until' ) && ekc_opt( 'login_protection' ) && ekc_login_locked_until( ekc_client_ip() ) > 0;
	if ( $locked() ) {
		delete_user_meta( $user_id, 'ekc_2fa_pending' );
		ekc_2fa_back_to_login( 'locked' );
	}

	if ( ! ekc_2fa_verify_code( $user_id, $code ) ) {
		$pending['n'] = (int) $pending['n'] + 1;
		update_user_meta( $user_id, 'ekc_2fa_pending', $pending );
		do_action( 'wp_login_failed', $user->user_login, new WP_Error( 'ekc_2fa_invalid', 'Neplatný kód dvoufázového ověření.' ) );
		if ( $pending['n'] >= EKC_2FA_TRIES || $locked() ) {
			delete_user_meta( $user_id, 'ekc_2fa_pending' );
			ekc_2fa_back_to_login( $locked() ? 'locked' : 'expired' );
		}
		ekc_2fa_form( $user, $token, $pending, '<strong>Chyba:</strong> Neplatný kód. Zkuste to znovu.' );
		exit;
	}

	delete_user_meta( $user_id, 'ekc_2fa_pending' );
	wp_set_auth_cookie( $user_id, ! empty( $pending['r'] ), is_ssl() );
	do_action( 'wp_login', $user->user_login, $user );

	if ( ! empty( $pending['i'] ) ) {
		global $interim_login;
		$interim_login = 'success'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- okno administrace pozná úspěch podle třídy body
		login_header( '', '<p class="message">Přihlášení proběhlo úspěšně.</p>' );
		login_footer();
		exit;
	}

	$requested = (string) $pending['t'];
	$redirect  = (string) apply_filters( 'login_redirect', '' !== $requested ? $requested : admin_url(), $requested, $user );
	if ( ( '' === $redirect || admin_url() === $redirect ) && ! $user->has_cap( 'edit_posts' ) ) {
		$redirect = admin_url( 'profile.php' );
	}
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'login_form_ekc_2fa', 'ekc_2fa_verify' );

/**
 * Hláška na přihlašovací stránce po vypršení nebo blokaci.
 *
 * @param WP_Error $errors Chyby a zprávy.
 * @return WP_Error
 */
function ekc_2fa_login_messages( $errors ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen výběr hlášky
	$reason = isset( $_GET['ekc_2fa'] ) ? sanitize_key( wp_unslash( $_GET['ekc_2fa'] ) ) : '';
	if ( $errors instanceof WP_Error && 'expired' === $reason ) {
		$errors->add( 'ekc_2fa_expired', '<strong>Chyba:</strong> Ověření kódem vypršelo nebo bylo zadáno příliš mnoho chybných kódů. Přihlaste se znovu.' );
	} elseif ( $errors instanceof WP_Error && 'locked' === $reason ) {
		$errors->add( 'ekc_2fa_locked', '<strong>Chyba:</strong> Příliš mnoho neúspěšných pokusů o přihlášení. Zkuste to znovu později.' );
	}
	return $errors;
}
add_filter( 'wp_login_errors', 'ekc_2fa_login_messages' );

/* -------------------------------------------------------------------------
 * Profil: zapnutí, záložní kódy, vypnutí
 * ---------------------------------------------------------------------- */

/**
 * Adresa otpauth:// pro aplikaci.
 *
 * @param WP_User $user   Uživatel.
 * @param string  $secret Tajný klíč.
 * @return string
 */
function ekc_2fa_uri( WP_User $user, string $secret ): string {
	$issuer = (string) EKC_NAME;
	return 'otpauth://totp/' . rawurlencode( $issuer ) . ':' . rawurlencode( $user->user_login )
		. '?secret=' . $secret . '&issuer=' . rawurlencode( $issuer )
		. '&algorithm=SHA1&digits=' . EKC_2FA_DIGITS . '&period=' . EKC_2FA_PERIOD;
}

/**
 * Sekce Dvoufázové ověření v profilu.
 *
 * @param WP_User $user Upravovaný uživatel.
 */
function ekc_2fa_profile( $user ): void {
	if ( ! $user instanceof WP_User || ! ekc_opt( 'two_factor' ) ) {
		return;
	}
	$self    = get_current_user_id() === $user->ID;
	$enabled = ekc_2fa_enabled( $user->ID );

	echo '<h2 id="ekc-2fa">Dvoufázové ověření</h2><table class="form-table" role="presentation"><tr><th scope="row">Stav</th><td>';

	if ( $enabled ) {
		$fresh = $self ? get_user_meta( $user->ID, 'ekc_2fa_fresh', true ) : array();
		if ( $fresh && is_array( $fresh ) ) {
			delete_user_meta( $user->ID, 'ekc_2fa_fresh' );
			echo '<div class="notice notice-warning inline" style="margin:0 0 1em"><p><strong>Záložní kódy – uložte si je teď, znovu se nezobrazí.</strong> Každý jde použít jednou místo kódu z aplikace, třeba když ztratíte telefon.</p>';
			echo '<p style="font:15px/1.8 ui-monospace,Menlo,Consolas,monospace;column-count:2;max-width:320px">' . implode( '<br>', array_map( 'esc_html', $fresh ) ) . '</p></div>';
		}
		$since = (int) get_user_meta( $user->ID, 'ekc_2fa_since', true );
		$left  = ekc_2fa_backup_left( $user->ID );
		echo '<p>' . ekc_status_dot( 'ok' ) . ' ' . esc_html( 'Zapnuto' . ( $since ? ' od ' . ekc_datetime( $since ) : '' ) . '. ' . ekc_plural( $left, 'Zbývá %d záložní kód.', 'Zbývají %d záložní kódy.', 'Zbývá %d záložních kódů.' ) ) . ( $left <= 2 ? ' ' . ekc_status_dot( 'warn' ) : '' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $self ) {
			echo '<p><label><input type="checkbox" name="ekc_2fa_regen" value="1"> Vygenerovat nové záložní kódy (staré přestanou platit)</label><br>';
			echo '<label><input type="checkbox" name="ekc_2fa_disable" value="1"> Vypnout dvoufázové ověření</label></p>';
			echo '<p><label for="ekc_2fa_confirm">Kód z aplikace</label><br><input type="text" name="ekc_2fa_confirm" id="ekc_2fa_confirm" class="regular-text" style="max-width:12em" inputmode="numeric" autocomplete="one-time-code"></p>';
			echo '<p class="description">Pro obě akce zadejte aktuální kód z aplikace (nebo záložní kód) a uložte profil.</p>';
		} else {
			echo '<p><label><input type="checkbox" name="ekc_2fa_disable" value="1"> Vypnout dvoufázové ověření tomuto uživateli</label></p>';
			echo '<p class="description">Třeba když ztratil telefon i záložní kódy. Znovu si ho pak zapne sám ve svém profilu.</p>';
		}
	} elseif ( $self ) {
		$secret = (string) get_user_meta( $user->ID, 'ekc_2fa_setup', true );
		if ( '' === ekc_2fa_base32_decode( $secret ) ) {
			$secret = ekc_2fa_new_secret();
			update_user_meta( $user->ID, 'ekc_2fa_setup', $secret );
		}
		echo '<p>' . ekc_status_dot( 'warn' ) . ' Vypnuto. Po zapnutí bude přihlášení chtít kromě hesla i kód z aplikace v telefonu.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<ol style="max-width:640px"><li>V aplikaci (Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden…) přidejte účet a naskenujte QR kód.</li><li>Zadejte šestimístný kód, který aplikace ukáže, a uložte profil.</li></ol>';
		echo '<div style="display:inline-block;padding:8px;background:#fff;border:1px solid #dcdcde;line-height:0">' . ekc_2fa_qr_svg( ekc_2fa_uri( $user, $secret ), 4 ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG skládá modul z čísel
		echo '<p>Ruční zadání klíče: <code style="user-select:all">' . esc_html( $secret ) . '</code></p>';
		echo '<p><label for="ekc_2fa_enable">Kód z aplikace</label><br><input type="text" name="ekc_2fa_enable" id="ekc_2fa_enable" class="regular-text" style="max-width:12em" inputmode="numeric" autocomplete="one-time-code"></p>';
	} else {
		echo '<p>Vypnuto. Zapnout si ho musí uživatel sám ve svém profilu.</p>';
	}
	echo '</td></tr></table>';
}
add_action( 'show_user_profile', 'ekc_2fa_profile' );
add_action( 'edit_user_profile', 'ekc_2fa_profile' );

/**
 * Uložení profilu: zapnutí, nové záložní kódy, vypnutí. Chybný kód zastaví uložení profilu.
 * Nonce profilu už ověřil WordPress.
 *
 * @param WP_Error $errors Chyby.
 * @param bool     $update Úprava existujícího uživatele.
 * @param object   $user   Data uživatele (ID).
 */
function ekc_2fa_profile_save( $errors, $update, $user ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce update-user_{ID} ověřil user-edit.php
	if ( ! $update || ! ekc_opt( 'two_factor' ) || empty( $user->ID ) || ! current_user_can( 'edit_user', (int) $user->ID ) ) {
		return;
	}
	$user_id = (int) $user->ID;
	$self    = get_current_user_id() === $user_id;
	$field   = static fn( string $key ): string => isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

	if ( ! ekc_2fa_enabled( $user_id ) ) {
		$code = $field( 'ekc_2fa_enable' );
		if ( ! $self || '' === $code ) {
			return;
		}
		$secret = (string) get_user_meta( $user_id, 'ekc_2fa_setup', true );
		$step   = ekc_2fa_check_totp( $secret, $code );
		if ( ! $step ) {
			$errors->add( 'ekc_2fa_enable', '<strong>Dvoufázové ověření:</strong> kód nesouhlasí. Zkontrolujte, že je v telefonu správný čas, a zadejte aktuální kód.' );
			return;
		}
		ekc_2fa_activate( $user_id, $secret, $step );
		return;
	}

	$disable = ! empty( $_POST['ekc_2fa_disable'] );
	$regen   = ! empty( $_POST['ekc_2fa_regen'] );
	// phpcs:enable
	if ( ! $disable && ! $regen ) {
		return;
	}
	if ( $self && ! ekc_2fa_verify_code( $user_id, $field( 'ekc_2fa_confirm' ) ) ) {
		$errors->add( 'ekc_2fa_confirm', '<strong>Dvoufázové ověření:</strong> pro změnu zadejte platný kód z aplikace.' );
		return;
	}
	if ( $disable ) {
		ekc_2fa_deactivate( $user_id );
	} else {
		ekc_2fa_backup_generate( $user_id );
	}
}
add_action( 'user_profile_update_errors', 'ekc_2fa_profile_save', 10, 3 );

/* -------------------------------------------------------------------------
 * Povinné ověření pro správce, sloupec u uživatelů, Zdraví webu
 * ---------------------------------------------------------------------- */

/**
 * Musí si uživatel ověření zapnout (správce, nastavení two_factor_required)?
 *
 * @param WP_User $user Uživatel.
 * @return bool
 */
function ekc_2fa_must_setup( WP_User $user ): bool {
	return ekc_opt( 'two_factor' ) && ekc_opt( 'two_factor_required' ) && $user->has_cap( 'manage_options' ) && ! ekc_2fa_enabled( $user->ID );
}

/**
 * Správce bez ověření se dostane jen do profilu.
 */
function ekc_2fa_enforce(): void {
	if ( wp_doing_ajax() || wp_doing_cron() || ! is_user_logged_in() ) {
		return;
	}
	$user = wp_get_current_user();
	if ( ! ekc_2fa_must_setup( $user ) || in_array( $GLOBALS['pagenow'] ?? '', array( 'profile.php', 'admin-post.php' ), true ) ) {
		return;
	}
	wp_safe_redirect( admin_url( 'profile.php#ekc-2fa' ) );
	exit;
}
add_action( 'admin_init', 'ekc_2fa_enforce' );

/**
 * Upozornění v profilu, když je ověření povinné.
 */
function ekc_2fa_enforce_notice(): void {
	if ( 'profile.php' === ( $GLOBALS['pagenow'] ?? '' ) && ekc_2fa_must_setup( wp_get_current_user() ) ) {
		echo '<div class="notice notice-error"><p><strong>Zapněte si dvoufázové ověření.</strong> Na tomto webu je pro správce povinné, do ostatních částí administrace se dostanete až po jeho zapnutí. <a href="#ekc-2fa">Přejít na nastavení</a></p></div>';
	}
}
add_action( 'admin_notices', 'ekc_2fa_enforce_notice' );

/**
 * Sloupec 2FA v přehledu uživatelů.
 *
 * @param array $columns Sloupce.
 * @return array
 */
function ekc_2fa_users_column( $columns ) {
	if ( ekc_opt( 'two_factor' ) ) {
		$columns['ekc_2fa'] = '2FA';
	}
	return $columns;
}
add_filter( 'manage_users_columns', 'ekc_2fa_users_column' );

/**
 * Obsah sloupce 2FA.
 *
 * @param string $output  Výstup.
 * @param string $column  Sloupec.
 * @param int    $user_id ID uživatele.
 * @return string
 */
function ekc_2fa_users_column_content( $output, $column, $user_id ) {
	if ( 'ekc_2fa' !== $column ) {
		return $output;
	}
	return ekc_2fa_enabled( (int) $user_id ) ? ekc_status_dot( 'ok' ) . ' zapnuto' : '—';
}
add_filter( 'manage_users_custom_column', 'ekc_2fa_users_column_content', 10, 3 );

/**
 * Řádek do widgetu Zdraví webu: správci bez dvoufázového ověření.
 *
 * @param array $rows Řádky.
 * @return array
 */
function ekc_2fa_health_rows( array $rows ): array {
	if ( ! ekc_opt( 'two_factor' ) ) {
		return $rows;
	}
	$admins  = get_users(
		array(
			'role__in' => array( 'administrator' ),
			'fields'   => array( 'ID' ),
		)
	);
	$without = count( array_filter( $admins, static fn( $u ) => ! ekc_2fa_enabled( (int) $u->ID ) ) );
	$rows['Přihlášení']['Správci bez 2FA'] = $without . ' ' . ekc_status_dot( $without ? 'warn' : 'ok' );
	return $rows;
}
add_filter( 'ekc_health_rows', 'ekc_2fa_health_rows' );

/* -------------------------------------------------------------------------
 * QR kód (SVG, bajtový režim, úroveň opravy M) – podle referenční implementace Project Nayuki
 * ---------------------------------------------------------------------- */

/**
 * Násobení v GF(2^8) s polynomem 0x11D.
 *
 * @param int $x Činitel.
 * @param int $y Činitel.
 * @return int
 */
function ekc_2fa_gf_mul( int $x, int $y ): int {
	$z = 0;
	for ( $i = 7; $i >= 0; $i-- ) {
		$z  = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11d );
		$z ^= ( ( $y >> $i ) & 1 ) * $x;
	}
	return $z;
}

/**
 * Bajty opravy chyb (Reed–Solomon) pro jeden blok.
 *
 * @param int[] $data   Datové bajty.
 * @param int   $degree Počet bajtů opravy.
 * @return int[]
 */
function ekc_2fa_rs( array $data, int $degree ): array {
	$divisor                = array_fill( 0, $degree, 0 );
	$divisor[ $degree - 1 ] = 1;
	$root                   = 1;
	for ( $i = 0; $i < $degree; $i++ ) {
		for ( $j = 0; $j < $degree; $j++ ) {
			$divisor[ $j ] = ekc_2fa_gf_mul( $divisor[ $j ], $root );
			if ( $j + 1 < $degree ) {
				$divisor[ $j ] ^= $divisor[ $j + 1 ];
			}
		}
		$root = ekc_2fa_gf_mul( $root, 0x02 );
	}
	$result = array_fill( 0, $degree, 0 );
	foreach ( $data as $byte ) {
		$factor   = $byte ^ array_shift( $result );
		$result[] = 0;
		foreach ( $divisor as $i => $coef ) {
			$result[ $i ] ^= ekc_2fa_gf_mul( $coef, $factor );
		}
	}
	return $result;
}

/**
 * Matice QR kódu pro text (bajtový režim, úroveň M, verze 1–15).
 *
 * @param string $text Text.
 * @return array<int,array<int,bool>> [y][x] = tmavý modul.
 */
function ekc_2fa_qr_matrix( string $text ): array {
	// Úroveň M, index = verze: bajty opravy na blok a počet bloků.
	$ecc_len = array( 0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24 );
	$blocks  = array( 0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10 );
	$bytes   = array_values( unpack( 'C*', $text ) ?: array() );
	$len     = count( $bytes );

	$raw_modules = static function ( int $ver ): int {
		$result = ( 16 * $ver + 128 ) * $ver + 64;
		if ( $ver >= 2 ) {
			$align   = intdiv( $ver, 7 ) + 2;
			$result -= ( 25 * $align - 10 ) * $align - 55;
			if ( $ver >= 7 ) {
				$result -= 36;
			}
		}
		return $result;
	};

	// Nejmenší verze, do které se data vejdou.
	$ver = 0;
	for ( $v = 1; $v <= 15; $v++ ) {
		$capacity = intdiv( $raw_modules( $v ), 8 ) - $ecc_len[ $v ] * $blocks[ $v ];
		if ( 4 + ( $v < 10 ? 8 : 16 ) + 8 * $len <= $capacity * 8 ) {
			$ver = $v;
			break;
		}
	}
	if ( ! $ver ) {
		return array();
	}
	$capacity = intdiv( $raw_modules( $ver ), 8 ) - $ecc_len[ $ver ] * $blocks[ $ver ];

	// Datové bity: režim 0100, délka, bajty, ukončení a výplň.
	$bits = '0100' . str_pad( decbin( $len ), $ver < 10 ? 8 : 16, '0', STR_PAD_LEFT );
	foreach ( $bytes as $byte ) {
		$bits .= str_pad( decbin( $byte ), 8, '0', STR_PAD_LEFT );
	}
	$bits .= str_repeat( '0', min( 4, $capacity * 8 - strlen( $bits ) ) );
	$bits .= str_repeat( '0', ( 8 - strlen( $bits ) % 8 ) % 8 );
	$data  = array_map( 'bindec', str_split( $bits, 8 ) );
	for ( $pad = 0xec; count( $data ) < $capacity; $pad ^= 0xec ^ 0x11 ) {
		$data[] = $pad;
	}

	// Rozdělení do bloků, oprava chyb a proložení.
	$num_blocks = $blocks[ $ver ];
	$block_ecc  = $ecc_len[ $ver ];
	$raw_words  = intdiv( $raw_modules( $ver ), 8 );
	$num_short  = $num_blocks - $raw_words % $num_blocks;
	$short_len  = intdiv( $raw_words, $num_blocks );
	$parts      = array();
	for ( $i = 0, $k = 0; $i < $num_blocks; $i++ ) {
		$dat = array_slice( $data, $k, $short_len - $block_ecc + ( $i < $num_short ? 0 : 1 ) );
		$k  += count( $dat );
		$ecc = ekc_2fa_rs( $dat, $block_ecc );
		if ( $i < $num_short ) {
			$dat[] = 0;
		}
		$parts[] = array_merge( $dat, $ecc );
	}
	$words = array();
	$width = count( $parts[0] );
	for ( $i = 0; $i < $width; $i++ ) {
		foreach ( $parts as $j => $part ) {
			if ( $i !== $short_len - $block_ecc || $j >= $num_short ) {
				$words[] = $part[ $i ];
			}
		}
	}

	// Funkční vzory.
	$size = $ver * 4 + 17;
	$mod  = array_fill( 0, $size, array_fill( 0, $size, false ) );
	$func = $mod;
	$set  = static function ( int $x, int $y, bool $dark ) use ( &$mod, &$func ): void {
		$mod[ $y ][ $x ]  = $dark;
		$func[ $y ][ $x ] = true;
	};
	for ( $i = 0; $i < $size; $i++ ) {
		$set( 6, $i, 0 === $i % 2 );
		$set( $i, 6, 0 === $i % 2 );
	}
	foreach ( array( array( 3, 3 ), array( $size - 4, 3 ), array( 3, $size - 4 ) ) as list( $cx, $cy ) ) {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$dist = max( abs( $dx ), abs( $dy ) );
				$xx   = $cx + $dx;
				$yy   = $cy + $dy;
				if ( $xx >= 0 && $xx < $size && $yy >= 0 && $yy < $size ) {
					$set( $xx, $yy, 2 !== $dist && 4 !== $dist );
				}
			}
		}
	}
	if ( $ver > 1 ) {
		$count = intdiv( $ver, 7 ) + 2;
		$step  = intdiv( $ver * 8 + $count * 3 + 5, $count * 4 - 4 ) * 2;
		$pos   = array( 6 );
		for ( $p = $size - 7; count( $pos ) < $count; $p -= $step ) {
			array_splice( $pos, 1, 0, array( $p ) );
		}
		foreach ( $pos as $i => $ax ) {
			foreach ( $pos as $j => $ay ) {
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $count - 1 === $j ) || ( $count - 1 === $i && 0 === $j ) ) {
					continue;
				}
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$set( $ax + $dx, $ay + $dy, 1 !== max( abs( $dx ), abs( $dy ) ) );
					}
				}
			}
		}
	}
	$format = static function ( int $mask ) use ( $set, $size ): void {
		$data = $mask; // úroveň M = 00
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		$bit  = static fn( int $i ): bool => 1 === ( ( $bits >> $i ) & 1 );
		for ( $i = 0; $i <= 5; $i++ ) {
			$set( 8, $i, $bit( $i ) );
		}
		$set( 8, 7, $bit( 6 ) );
		$set( 8, 8, $bit( 7 ) );
		$set( 7, 8, $bit( 8 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			$set( 14 - $i, 8, $bit( $i ) );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$set( $size - 1 - $i, 8, $bit( $i ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$set( 8, $size - 15 + $i, $bit( $i ) );
		}
		$set( 8, $size - 8, true );
	};
	$format( 0 );
	if ( $ver >= 7 ) {
		$rem = $ver;
		for ( $i = 0; $i < 12; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1f25 );
		}
		$vbits = ( $ver << 12 ) | $rem;
		for ( $i = 0; $i < 18; $i++ ) {
			$dark = 1 === ( ( $vbits >> $i ) & 1 );
			$a    = $size - 11 + $i % 3;
			$b    = intdiv( $i, 3 );
			$set( $a, $b, $dark );
			$set( $b, $a, $dark );
		}
	}

	// Data do matice (cik-cak po dvou sloupcích zprava).
	$total = count( $words ) * 8;
	$i     = 0;
	for ( $right = $size - 1; $right >= 1; $right -= 2 ) {
		if ( 6 === $right ) {
			$right = 5;
		}
		for ( $vert = 0; $vert < $size; $vert++ ) {
			for ( $j = 0; $j < 2; $j++ ) {
				$x      = $right - $j;
				$upward = 0 === ( ( $right + 1 ) & 2 );
				$y      = $upward ? $size - 1 - $vert : $vert;
				if ( ! $func[ $y ][ $x ] && $i < $total ) {
					$mod[ $y ][ $x ] = 1 === ( ( $words[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1 );
					++$i;
				}
			}
		}
	}

	// Maska s nejnižší penalizací (pravidla běhů, bloků 2×2 a poměru tmavých modulů).
	$apply   = static function ( int $mask ) use ( &$mod, $func, $size ): void {
		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x < $size; $x++ ) {
				if ( $func[ $y ][ $x ] ) {
					continue;
				}
				switch ( $mask ) {
					case 0:
						$flip = 0 === ( $x + $y ) % 2;
						break;
					case 1:
						$flip = 0 === $y % 2;
						break;
					case 2:
						$flip = 0 === $x % 3;
						break;
					case 3:
						$flip = 0 === ( $x + $y ) % 3;
						break;
					case 4:
						$flip = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2;
						break;
					case 5:
						$flip = 0 === $x * $y % 2 + $x * $y % 3;
						break;
					case 6:
						$flip = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2;
						break;
					default:
						$flip = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}
				if ( $flip ) {
					$mod[ $y ][ $x ] = ! $mod[ $y ][ $x ];
				}
			}
		}
	};
	$penalty = static function () use ( &$mod, $size ): int {
		$score = 0;
		$dark  = 0;
		for ( $a = 0; $a < $size; $a++ ) {
			$run_r = 1;
			$run_c = 1;
			for ( $b = 1; $b < $size; $b++ ) {
				$run_r   = $mod[ $a ][ $b ] === $mod[ $a ][ $b - 1 ] ? $run_r + 1 : 1;
				$run_c   = $mod[ $b ][ $a ] === $mod[ $b - 1 ][ $a ] ? $run_c + 1 : 1;
				$score  += ( 5 === $run_r ? 3 : ( $run_r > 5 ? 1 : 0 ) ) + ( 5 === $run_c ? 3 : ( $run_c > 5 ? 1 : 0 ) );
			}
			for ( $b = 0; $b < $size; $b++ ) {
				$dark += $mod[ $a ][ $b ] ? 1 : 0;
				if ( $a + 1 < $size && $b + 1 < $size && $mod[ $a ][ $b ] === $mod[ $a ][ $b + 1 ] && $mod[ $a ][ $b ] === $mod[ $a + 1 ][ $b ] && $mod[ $a ][ $b ] === $mod[ $a + 1 ][ $b + 1 ] ) {
					$score += 3;
				}
			}
		}
		$total = $size * $size;
		return $score + ( intdiv( abs( $dark * 20 - $total * 10 ) + $total - 1, $total ) - 1 ) * 10;
	};
	$best      = 0;
	$best_cost = PHP_INT_MAX;
	for ( $mask = 0; $mask < 8; $mask++ ) {
		$apply( $mask );
		$format( $mask );
		$cost = $penalty();
		if ( $cost < $best_cost ) {
			$best      = $mask;
			$best_cost = $cost;
		}
		$apply( $mask );
	}
	$apply( $best );
	$format( $best );
	return $mod;
}

/**
 * QR kód jako SVG (s okrajem 4 moduly).
 *
 * @param string $text  Text.
 * @param int    $scale Pixelů na modul.
 * @return string SVG, při příliš dlouhém textu ''.
 */
function ekc_2fa_qr_svg( string $text, int $scale = 4 ): string {
	$matrix = ekc_2fa_qr_matrix( $text );
	if ( ! $matrix ) {
		return '';
	}
	$size = count( $matrix ) + 8;
	$path = '';
	foreach ( $matrix as $y => $row ) {
		foreach ( $row as $x => $dark ) {
			if ( $dark ) {
				$path .= 'M' . ( $x + 4 ) . ' ' . ( $y + 4 ) . 'h1v1h-1z';
			}
		}
	}
	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" shape-rendering="crispEdges" role="img" aria-label="QR kód pro ověřovací aplikaci"><rect width="%1$d" height="%1$d" fill="#fff"/><path d="%3$s" fill="#000"/></svg>',
		$size,
		$size * $scale,
		$path
	);
}
