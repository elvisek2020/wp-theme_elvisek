<?php
/**
 * EKT module: comments-antispam
 * Version: 1.0.0
 * Requires: common
 * Replaces: antispam-bee, advanced-nocaptcha-recaptcha
 * Assets: –
 * Summary: Ochrana komentářů bez reCAPTCHA a externích služeb: skryté pole, minimální čas vyplnění a podepsané časové razítko.
 */

defined( 'ABSPATH' ) || exit;

/*
 * - Do formuláře přidá skryté pole ekt_hp (honeypot, skryté inline stylem – nezávisí na CSS
 *   šablony) a razítko ekt_ts + podpis ekt_sig (wp_hash, porovnání hash_equals).
 * - Zamítne (wp_die, HTTP 400) komentář s vyplněným honeypotem, bez platného podpisu, odeslaný
 *   dřív než za 4 s (filtr ekt_antispam_min_seconds) nebo s razítkem starším než 14 dní
 *   (filtr ekt_antispam_max_age; dlouhá platnost kvůli full-page cache hostingu – stránka
 *   z cache nese staré razítko).
 * - Kontroluje jen typ comment (pingbacky a trackbacky ne) a jen nepřihlášené.
 * - Skryje pole „Web“ (magnet na spam), filtr ekt_antispam_hide_url (true).
 * - Formuláře ze stránek v cache ze starší šablony (pole ek_* / iv_*) platí dál, dokud je
 *   prefix v EKT_LEGACY (stejná podpisová pravidla jako dřív).
 * - Dokud běží Antispam Bee, nedělá nic (ekt_legacy_active( 'antispam' )).
 *
 * Markup: p.ekt-hp[aria-hidden] (inline skrytý) + input[type=hidden] ekt_ts, ekt_sig.
 * Veřejné funkce: ekt_antispam_fields(), ekt_antispam_check( $data ), ekt_antispam_sign( $ts ).
 * Filtry: ekt_antispam_min_seconds (4), ekt_antispam_max_age (14 dní), ekt_antispam_hide_url (true),
 * ekt_antispam_texts( $texts ), ekt_antispam_reject( $reject, $reason ).
 */

/**
 * Dělá modul něco? Ne, dokud běží Antispam Bee.
 *
 * @return bool
 */
function ekt_antispam_enabled(): bool {
	return ! ekt_legacy_active( 'antispam' );
}

/**
 * Podpis razítka.
 *
 * @param int $ts Unixový čas.
 * @return string
 */
function ekt_antispam_sign( int $ts ): string {
	return wp_hash( 'ekt-comment|' . $ts );
}

/**
 * Skrytá pole ve formuláři komentáře.
 */
function ekt_antispam_fields(): void {
	if ( ! ekt_antispam_enabled() ) {
		return;
	}
	$ts = time();
	echo '<p class="ekt-hp" aria-hidden="true" style="position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden"><label for="ekt_hp">Nevyplňujte</label><input type="text" id="ekt_hp" name="ekt_hp" value="" tabindex="-1" autocomplete="off"></p>';
	printf( '<input type="hidden" name="ekt_ts" value="%d"><input type="hidden" name="ekt_sig" value="%s">', (int) $ts, esc_attr( ekt_antispam_sign( $ts ) ) );
}
add_action( 'comment_form_after_fields', 'ekt_antispam_fields' );
add_action( 'comment_form_logged_in_after', 'ekt_antispam_fields' );

/**
 * Pole formuláře z POST: nová ekt_*, jinak ze starší šablony (cache).
 *
 * @return array{hp:string,ts:int,sig:string,valid:bool}
 */
function ekt_antispam_posted(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- formulář komentáře nemá nonce, kontrolou je podpis
	$sets = array(
		array( 'ekt_hp', 'ekt_ts', 'ekt_sig', static fn( int $ts ): string => ekt_antispam_sign( $ts ) ),
	);
	$legacy = array(
		'ek' => static fn( int $ts ): string => wp_hash( 'ek-comment|' . $ts ),
		'iv' => static fn( int $ts ): string => wp_hash( 'iv_comment_' . $ts ),
	);
	foreach ( ekt_legacy_prefixes() as $p ) {
		if ( isset( $legacy[ $p ] ) ) {
			$sets[] = array( $p . '_website', $p . '_ts', $p . '_sig', $legacy[ $p ] );
		}
	}
	foreach ( $sets as $set ) {
		list( $hp_name, $ts_name, $sig_name, $sign ) = $set;
		if ( ! isset( $_POST[ $ts_name ] ) ) {
			continue;
		}
		$ts  = (int) $_POST[ $ts_name ];
		$sig = isset( $_POST[ $sig_name ] ) && is_string( $_POST[ $sig_name ] ) ? (string) wp_unslash( $_POST[ $sig_name ] ) : '';
		$hp  = isset( $_POST[ $hp_name ] ) && is_string( $_POST[ $hp_name ] ) ? (string) wp_unslash( $_POST[ $hp_name ] ) : '';
		return array(
			'hp'    => $hp,
			'ts'    => $ts,
			'sig'   => $sig,
			'valid' => $ts > 0 && '' !== $sig && hash_equals( $sign( $ts ), $sig ),
		);
	}
	// Honeypot bez razítka (robot, který razítko zahodil).
	$hp = isset( $_POST['ekt_hp'] ) && is_string( $_POST['ekt_hp'] ) ? (string) wp_unslash( $_POST['ekt_hp'] ) : '';
	// phpcs:enable
	return array(
		'hp'    => $hp,
		'ts'    => 0,
		'sig'   => '',
		'valid' => false,
	);
}

/**
 * Kontrola komentáře před uložením.
 *
 * @param array $data Data komentáře.
 * @return array
 */
function ekt_antispam_check( $data ) {
	if ( ! is_array( $data ) || ! ekt_antispam_enabled() || is_user_logged_in() ) {
		return $data;
	}
	$type = (string) ( $data['comment_type'] ?? '' );
	if ( '' !== $type && 'comment' !== $type ) {
		return $data; // pingback, trackback a jiné typy se nekontrolují
	}
	$f       = ekt_antispam_posted();
	$age     = time() - $f['ts'];
	$min     = max( 0, (int) apply_filters( 'ekt_antispam_min_seconds', 4 ) );
	$max     = max( HOUR_IN_SECONDS, (int) apply_filters( 'ekt_antispam_max_age', 14 * DAY_IN_SECONDS ) );
	$reason  = '';
	if ( '' !== trim( $f['hp'] ) ) {
		$reason = 'honeypot';
	} elseif ( ! $f['valid'] ) {
		$reason = 'signature';
	} elseif ( $age < $min ) {
		$reason = 'too_fast';
	} elseif ( $age > $max ) {
		$reason = 'expired';
	}
	if ( ! (bool) apply_filters( 'ekt_antispam_reject', '' !== $reason, $reason ) ) {
		return $data;
	}
	$texts = array_merge(
		array(
			'title'   => 'Komentář neodeslán',
			'message' => 'Komentář se nepodařilo odeslat. Obnovte stránku a zkuste to prosím znovu.',
		),
		(array) apply_filters( 'ekt_antispam_texts', array() )
	);
	wp_die(
		esc_html( (string) $texts['message'] ),
		esc_html( (string) $texts['title'] ),
		array(
			'response'  => 400,
			'back_link' => true,
		)
	);
}
add_filter( 'preprocess_comment', 'ekt_antispam_check', 1 );

/**
 * Pole „Web“ ve formuláři je magnet na spam – skrýt.
 *
 * @param array $fields Pole formuláře.
 * @return array
 */
function ekt_antispam_hide_url( $fields ) {
	if ( is_array( $fields ) && ekt_antispam_enabled() && apply_filters( 'ekt_antispam_hide_url', true ) ) {
		unset( $fields['url'] );
	}
	return $fields;
}
add_filter( 'comment_form_default_fields', 'ekt_antispam_hide_url' );
