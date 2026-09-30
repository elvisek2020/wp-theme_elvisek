<?php
/**
 * Ochrana komentářů bez reCAPTCHA a bez externích služeb:
 * skryté pole (honeypot) + minimální čas vyplnění + podepsané časové razítko.
 */

defined( 'ABSPATH' ) || exit;

const EK_COMMENT_MIN_SECONDS = 4;

add_action( 'comment_form_after_fields', 'ek_comment_trap' );
add_action( 'comment_form_logged_in_after', 'ek_comment_trap' );

function ek_comment_trap(): void {
	$ts  = time();
	$sig = wp_hash( 'ek-comment|' . $ts );
	echo '<p class="ek-hp" aria-hidden="true"><label for="ek_website">Nevyplňujte</label><input type="text" id="ek_website" name="ek_website" value="" tabindex="-1" autocomplete="off"></p>';
	printf( '<input type="hidden" name="ek_ts" value="%d"><input type="hidden" name="ek_sig" value="%s">', $ts, esc_attr( $sig ) );
}

add_filter( 'preprocess_comment', function ( array $data ): array {
	// Trackbacky/pingbacky a přihlášené uživatele nekontrolujeme.
	if ( ! empty( $data['comment_type'] ) && 'comment' !== $data['comment_type'] ) {
		return $data;
	}
	if ( is_user_logged_in() ) {
		return $data;
	}

	$hp  = isset( $_POST['ek_website'] ) ? (string) wp_unslash( $_POST['ek_website'] ) : '';
	$ts  = isset( $_POST['ek_ts'] ) ? (int) $_POST['ek_ts'] : 0;
	$sig = isset( $_POST['ek_sig'] ) ? (string) wp_unslash( $_POST['ek_sig'] ) : '';

	$valid_sig = $ts && hash_equals( wp_hash( 'ek-comment|' . $ts ), $sig );
	$too_fast  = ( time() - $ts ) < EK_COMMENT_MIN_SECONDS;
	$too_old   = ( time() - $ts ) > DAY_IN_SECONDS;

	if ( '' !== $hp || ! $valid_sig || $too_fast || $too_old ) {
		wp_die(
			esc_html__( 'Komentář se nepodařilo odeslat. Zkuste to prosím znovu za chvilku.', 'elvisek' ),
			esc_html__( 'Komentář neodeslán', 'elvisek' ),
			array( 'response' => 400, 'back_link' => true )
		);
	}
	return $data;
} );

// Pole „Web“ ve formuláři je pro spam magnet — skryjeme ho.
add_filter( 'comment_form_default_fields', function ( array $fields ): array {
	unset( $fields['url'] );
	return $fields;
} );
