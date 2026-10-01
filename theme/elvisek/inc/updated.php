<?php
/**
 * Štítek „Aktualizováno“ u starších návodů.
 *
 * Datum aktualizace se zadává ručně v editoru článku (box „Aktualizace návodu“).
 * Automatické datum změny z WordPressu se nepoužívá – mění se i při hromadných úpravách
 * a štítek by svítil skoro u všech článků.
 */

defined( 'ABSPATH' ) || exit;

const EK_UPDATED_META = 'ek_updated';

add_action( 'init', function () {
	register_post_meta( 'post', EK_UPDATED_META, array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'ek_updated_sanitize',
		'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
	) );
} );

/**
 * Datum ve formátu Y-m-d, jinak prázdný řetězec.
 */
function ek_updated_sanitize( $value ): string {
	$value = trim( (string) $value );
	$d     = DateTime::createFromFormat( '!Y-m-d', $value );
	return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : '';
}

/**
 * Datum aktualizace článku (Y-m-d) nebo prázdný řetězec.
 * Zobrazí se jen když je pozdější než datum vydání.
 */
function ek_updated_date( $post = null ): string {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$value = ek_updated_sanitize( get_post_meta( $post->ID, EK_UPDATED_META, true ) );
	return ( $value && $value > get_the_date( 'Y-m-d', $post ) ) ? $value : '';
}

/**
 * Štítek pro detail článku: „Aktualizováno 1. 10. 2026“.
 */
function ek_updated_badge(): string {
	$value = ek_updated_date();
	if ( ! $value ) {
		return '';
	}
	return sprintf(
		'<span class="ek-updated">%s<span>Aktualizováno <time datetime="%s">%s</time></span></span>',
		ek_icon( 'refresh', 15 ),
		esc_attr( $value ),
		esc_html( wp_date( 'j. n. Y', strtotime( $value . ' 12:00:00' ) ) )
	);
}

/**
 * Malý štítek na kartu článku.
 */
function ek_updated_chip(): string {
	$value = ek_updated_date();
	if ( ! $value ) {
		return '';
	}
	return sprintf(
		'<span class="ek-chip ek-chip--updated" title="Aktualizováno %s">%sAktualizováno</span>',
		esc_attr( wp_date( 'j. n. Y', strtotime( $value . ' 12:00:00' ) ) ),
		ek_icon( 'refresh', 13 )
	);
}

/* ---------- Box v editoru ---------- */

add_action( 'add_meta_boxes_post', function () {
	add_meta_box( 'ek-updated', 'Aktualizace návodu', 'ek_updated_metabox', 'post', 'side' );
} );

function ek_updated_metabox( WP_Post $post ): void {
	$value = ek_updated_sanitize( get_post_meta( $post->ID, EK_UPDATED_META, true ) );
	wp_nonce_field( 'ek_updated_save', 'ek_updated_nonce' );
	?>
	<p style="margin-top:0">Když návod zrevidujete, zadejte datum – u článku se zobrazí štítek „Aktualizováno“. Prázdné = bez štítku.</p>
	<p style="display:flex;gap:6px;align-items:center">
		<input type="date" name="ek_updated" id="ek-updated-input" value="<?php echo esc_attr( $value ); ?>" style="flex:1">
		<button type="button" class="button" onclick="document.getElementById('ek-updated-input').value=new Date().toLocaleDateString('sv-SE')">Dnes</button>
	</p>
	<?php
}

add_action( 'save_post_post', function ( int $post_id ) {
	if ( ! isset( $_POST['ek_updated_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ek_updated_nonce'] ), 'ek_updated_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$value = ek_updated_sanitize( wp_unslash( $_POST['ek_updated'] ?? '' ) );
	if ( $value ) {
		update_post_meta( $post_id, EK_UPDATED_META, $value );
	} else {
		delete_post_meta( $post_id, EK_UPDATED_META );
	}
} );
