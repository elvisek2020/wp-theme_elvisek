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
const EK_TOC_META     = 'ek_toc';    // '' = podle nastavení šablony, 'show', 'hide'
const EK_SUMMARY_META = 'ek_summary'; // shrnutí „Ve zkratce“, jeden bod na řádek

add_action( 'init', function () {
	register_post_meta( 'post', EK_UPDATED_META, array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'ek_updated_sanitize',
		'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
	) );
	register_post_meta( 'post', EK_TOC_META, array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => fn( $v ) => in_array( $v, array( 'show', 'hide' ), true ) ? $v : '',
		'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
	) );
	register_post_meta( 'post', EK_SUMMARY_META, array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'ek_summary_sanitize',
		'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
	) );
} );

/**
 * Shrnutí: řádky bez prázdných, bez odrážek na začátku, max. 8 bodů.
 */
function ek_summary_sanitize( $value ): string {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
	// Bez sanitize_text_field: ten by smazal i text jako `<16>` v kódu. Ukládá se prostý text, při výpisu se escapuje.
	$lines = array_map( fn( $l ) => trim( preg_replace( '/^\s*(?:[-*•–]|\d+[.)])\s+/u', '', preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', wp_check_invalid_utf8( (string) $l ) ) ) ), $lines );
	return implode( "\n", array_slice( array_values( array_filter( $lines, 'strlen' ) ), 0, 8 ) );
}

/** Body shrnutí článku (pole řetězců). */
function ek_summary_items( $post = null ): array {
	$post = get_post( $post );
	$raw  = $post ? (string) get_post_meta( $post->ID, EK_SUMMARY_META, true ) : '';
	return '' === $raw ? array() : explode( "\n", $raw );
}

/** Box „Ve zkratce“ nad obsahem článku. Ve shrnutí jde použít `kód`. */
function ek_summary_box( $post = null ): string {
	$items = ek_summary_items( $post );
	if ( ! $items ) {
		return '';
	}
	$li = '';
	foreach ( $items as $item ) {
		$li .= '<li>' . preg_replace( '/`([^`]+)`/', '<code>$1</code>', esc_html( $item ) ) . '</li>';
	}
	return '<aside class="ek-summary" aria-label="Ve zkratce"><p class="ek-summary__title">' . ek_icon( 'check', 16 ) . 'Ve zkratce</p><ul>' . $li . '</ul></aside>';
}

/**
 * Zobrazit u aktuálního článku obsah (TOC)? Nastavení článku má přednost před šablonou.
 */
function ek_show_toc( $post = null ): bool {
	$post = get_post( $post );
	$own  = $post ? (string) get_post_meta( $post->ID, EK_TOC_META, true ) : '';
	if ( 'show' === $own || 'hide' === $own ) {
		return 'show' === $own;
	}
	return (bool) ek_opt( 'ek_sidebar_toc' );
}

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
	add_meta_box( 'ek-updated', 'Nastavení článku', 'ek_updated_metabox', 'post', 'side' );
} );

function ek_updated_metabox( WP_Post $post ): void {
	$value = ek_updated_sanitize( get_post_meta( $post->ID, EK_UPDATED_META, true ) );
	wp_nonce_field( 'ek_updated_save', 'ek_updated_nonce' );
	?>
	<?php $toc = (string) get_post_meta( $post->ID, EK_TOC_META, true ); ?>
	<p style="margin:0 0 4px"><label for="ek-toc-select"><strong>Obsah článku</strong></label></p>
	<select name="ek_toc" id="ek-toc-select" style="width:100%">
		<option value="" <?php selected( $toc, '' ); ?>>Podle nastavení šablony (<?php echo ek_opt( 'ek_sidebar_toc' ) ? 'zapnuto' : 'vypnuto'; ?>)</option>
		<option value="show" <?php selected( $toc, 'show' ); ?>>Zobrazit</option>
		<option value="hide" <?php selected( $toc, 'hide' ); ?>>Skrýt</option>
	</select>
	<p style="margin:14px 0 4px"><label for="ek-summary-input"><strong>Ve zkratce</strong></label></p>
	<textarea name="ek_summary" id="ek-summary-input" rows="5" style="width:100%" placeholder="Jeden bod na řádek"><?php echo esc_textarea( (string) get_post_meta( $post->ID, EK_SUMMARY_META, true ) ); ?></textarea>
	<p class="description" style="margin-top:2px">Zobrazí se jako box nad textem článku. Jeden bod na řádek (max. 8), `kód` v obrácených apostrofech.</p>
	<p style="margin:14px 0 4px"><strong>Aktualizace návodu</strong></p>
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
	$toc = sanitize_key( wp_unslash( $_POST['ek_toc'] ?? '' ) );
	if ( in_array( $toc, array( 'show', 'hide' ), true ) ) {
		update_post_meta( $post_id, EK_TOC_META, $toc );
	} else {
		delete_post_meta( $post_id, EK_TOC_META );
	}
	$summary = ek_summary_sanitize( wp_unslash( $_POST['ek_summary'] ?? '' ) );
	if ( '' !== $summary ) {
		update_post_meta( $post_id, EK_SUMMARY_META, $summary );
	} else {
		delete_post_meta( $post_id, EK_SUMMARY_META );
	}
	$value = ek_updated_sanitize( wp_unslash( $_POST['ek_updated'] ?? '' ) );
	if ( $value ) {
		update_post_meta( $post_id, EK_UPDATED_META, $value );
	} else {
		delete_post_meta( $post_id, EK_UPDATED_META );
	}
} );
