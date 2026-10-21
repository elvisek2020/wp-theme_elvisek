<?php
/**
 * EKT module: term-color
 * Version: 1.0.0
 * Requires: common
 * Replaces: category-colors
 * Assets: –
 * Summary: Barva rubriky (term meta ekt_color) s polem a sloupcem v administraci a CSS proměnnou --ekt-cat pro šablonu.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Barva se nastavuje u rubriky (Příspěvky → Rubriky), bez nastavení se použije výchozí:
 * odstín odvozený ze slugu (střední sytost i jas), nebo hodnota z filtru ekt_term_color_default
 * (mapa slugů na barvy je projektová → inc/site-*.php). Šablona barvu použije jen přes CSS
 * proměnné: --ekt-cat u štítku rubriky, --ekt-stripe (jedna barva, nebo úseky pro víc rubrik)
 * u karty článku. Doporučení: jemný podtón nebo proužek, ne velké barevné plochy.
 *
 * Veřejné funkce: ekt_term_color( $term ), ekt_term_color_style( $term ),
 * ekt_term_stripe_style( $post ), ekt_term_color_taxonomies().
 * Filtry: ekt_term_color_taxonomies( ['category'] ), ekt_term_color_default( $color, $term ).
 * Výstup: ekt_term_color_style() → ' style="--ekt-cat:#rrggbb"',
 *         ekt_term_stripe_style() → ' style="--ekt-stripe:#rrggbb|linear-gradient(…)"'.
 */

/**
 * Taxonomie s barvou.
 *
 * @return string[]
 */
function ekt_term_color_taxonomies(): array {
	return array_values( array_filter( (array) apply_filters( 'ekt_term_color_taxonomies', array( 'category' ) ), 'taxonomy_exists' ) );
}

/**
 * Barva #rrggbb, jinak ''.
 *
 * @param mixed $value Hodnota.
 * @return string
 */
function ekt_term_color_sanitize( $value ): string {
	$value = strtolower( trim( is_scalar( $value ) ? (string) $value : '' ) );
	return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
}

/**
 * HSL → #rrggbb.
 *
 * @param int $h Odstín 0–359.
 * @param int $s Sytost 0–100.
 * @param int $l Jas 0–100.
 * @return string
 */
function ekt_term_color_hsl( int $h, int $s, int $l ): string {
	$s  /= 100;
	$l  /= 100;
	$c   = ( 1 - abs( 2 * $l - 1 ) ) * $s;
	$x   = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
	$m   = $l - $c / 2;
	$rgb = match ( intdiv( $h % 360, 60 ) ) {
		0 => array( $c, $x, 0 ),
		1 => array( $x, $c, 0 ),
		2 => array( 0, $c, $x ),
		3 => array( 0, $x, $c ),
		4 => array( $x, 0, $c ),
		default => array( $c, 0, $x ),
	};
	return sprintf( '#%02x%02x%02x', ...array_map( static fn( $v ) => (int) round( ( $v + $m ) * 255 ), $rgb ) );
}

/**
 * Výchozí barva termu (odstín ze slugu, filtr ekt_term_color_default).
 *
 * @param WP_Term $term Term.
 * @return string
 */
function ekt_term_color_default( WP_Term $term ): string {
	$hue = hexdec( substr( md5( $term->slug ), 0, 4 ) ) % 360;
	return ekt_term_color_sanitize( apply_filters( 'ekt_term_color_default', ekt_term_color_hsl( (int) $hue, 55, 48 ), $term ) );
}

/**
 * Barva termu (vlastní, jinak výchozí).
 *
 * @param WP_Term|int|null $term Term.
 * @return string #rrggbb nebo ''.
 */
function ekt_term_color( $term ): string {
	$term = is_numeric( $term ) ? get_term( (int) $term ) : $term;
	if ( ! $term instanceof WP_Term ) {
		return '';
	}
	$own = ekt_term_color_sanitize( get_term_meta( $term->term_id, 'ekt_color', true ) );
	return '' !== $own ? $own : ekt_term_color_default( $term );
}

/**
 * Atribut style s --ekt-cat (štítky rubrik, dlaždice, položky menu).
 *
 * @param WP_Term|int|null $term Term.
 * @return string ' style="--ekt-cat:#…"' nebo ''.
 */
function ekt_term_color_style( $term ): string {
	$color = ekt_term_color( $term );
	return '' !== $color ? ' style="--ekt-cat:' . esc_attr( $color ) . '"' : '';
}

/**
 * Atribut style s --ekt-stripe pro kartu článku: jedna barva, nebo stejné úseky (max. 4 rubriky).
 *
 * @param int|WP_Post|null $post Článek.
 * @return string
 */
function ekt_term_stripe_style( $post = null ): string {
	$post = get_post( $post );
	$tax  = ekt_term_color_taxonomies();
	if ( ! $post || ! $tax ) {
		return '';
	}
	$terms = get_the_terms( $post, $tax[0] );
	$terms = is_array( $terms ) ? array_values( array_filter( $terms, static fn( $t ) => 'category' !== $t->taxonomy || (int) $t->term_id !== (int) get_option( 'default_category' ) ) ) : array();
	if ( ! $terms ) {
		return '';
	}
	$colors = array_values( array_filter( array_map( 'ekt_term_color', array_slice( $terms, 0, 4 ) ) ) );
	if ( ! $colors ) {
		return '';
	}
	if ( 1 === count( $colors ) ) {
		$bg = $colors[0];
	} else {
		$n     = count( $colors );
		$stops = array();
		foreach ( $colors as $i => $color ) {
			$stops[] = sprintf( '%s %s%% %s%%', $color, round( $i * 100 / $n, 2 ), round( ( $i + 1 ) * 100 / $n, 2 ) );
		}
		$bg = 'linear-gradient(90deg,' . implode( ',', $stops ) . ')';
	}
	return ' style="--ekt-stripe:' . esc_attr( $bg ) . '"';
}

/**
 * Term meta, pole a sloupce v administraci.
 */
function ekt_term_color_init(): void {
	foreach ( ekt_term_color_taxonomies() as $tax ) {
		register_term_meta(
			$tax,
			'ekt_color',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'ekt_term_color_sanitize',
			)
		);
		add_action( $tax . '_add_form_fields', 'ekt_term_color_add_field' );
		add_action( $tax . '_edit_form_fields', 'ekt_term_color_edit_field' );
		add_action( 'created_' . $tax, 'ekt_term_color_save' );
		add_action( 'edited_' . $tax, 'ekt_term_color_save' );
		add_filter( 'manage_edit-' . $tax . '_columns', 'ekt_term_color_columns', 11 );
		add_filter( 'manage_' . $tax . '_custom_column', 'ekt_term_color_column', 10, 3 );
	}
}
add_action( 'init', 'ekt_term_color_init', 20 );

/**
 * Pole barvy (formulář přidání i úpravy).
 *
 * @param WP_Term|null $term Term.
 */
function ekt_term_color_field( ?WP_Term $term = null ): void {
	$own = $term ? ekt_term_color_sanitize( get_term_meta( $term->term_id, 'ekt_color', true ) ) : '';
	$def = $term ? ekt_term_color_default( $term ) : '#64748b';
	wp_nonce_field( 'ekt_term_color', '_ekt_term_color_nonce' );
	printf(
		'<input type="color" name="ekt_term_color" value="%1$s" oninput="var c=this.form&&this.form.querySelector(\'[name=ekt_term_color_default]\');if(c)c.checked=false"> <label style="margin-left:10px"><input type="checkbox" name="ekt_term_color_default" value="1"%2$s> výchozí barva</label><p class="description">Jemný podtón štítku a proužek na kartě článku; na webu se použije jen slabě.</p>',
		esc_attr( '' !== $own ? $own : $def ),
		checked( '' === $own, true, false )
	);
}

/**
 * Pole ve formuláři přidání termu.
 */
function ekt_term_color_add_field(): void {
	echo '<div class="form-field"><label>Barva</label>';
	ekt_term_color_field();
	echo '</div>';
}

/**
 * Pole ve formuláři úpravy termu.
 *
 * @param WP_Term $term Term.
 */
function ekt_term_color_edit_field( $term ): void {
	echo '<tr class="form-field"><th scope="row"><label>Barva</label></th><td>';
	ekt_term_color_field( $term instanceof WP_Term ? $term : null );
	echo '</td></tr>';
}

/**
 * Uložení barvy.
 *
 * @param int $term_id Term.
 */
function ekt_term_color_save( $term_id ): void {
	if ( ! isset( $_POST['ekt_term_color'], $_POST['_ekt_term_color_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_ekt_term_color_nonce'] ) ), 'ekt_term_color' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_term', (int) $term_id ) ) {
		return;
	}
	$color = ekt_term_color_sanitize( wp_unslash( $_POST['ekt_term_color'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- čistí ekt_term_color_sanitize()
	if ( ! empty( $_POST['ekt_term_color_default'] ) || '' === $color ) {
		delete_term_meta( (int) $term_id, 'ekt_color' );
	} else {
		update_term_meta( (int) $term_id, 'ekt_color', $color );
	}
}

/**
 * Sloupec Barva v seznamu termů.
 *
 * @param array $cols Sloupce.
 * @return array
 */
function ekt_term_color_columns( $cols ): array {
	$cols = (array) $cols;
	return array_slice( $cols, 0, 2, true ) + array( 'ekt_color' => 'Barva' ) + array_slice( $cols, 2, null, true );
}

/**
 * Obsah sloupce Barva.
 *
 * @param string $out     Výstup.
 * @param string $col     Sloupec.
 * @param int    $term_id Term.
 * @return string
 */
function ekt_term_color_column( $out, $col, $term_id ) {
	if ( 'ekt_color' !== $col ) {
		return $out;
	}
	$color = ekt_term_color( (int) $term_id );
	return '' !== $color ? '<span style="display:inline-block;width:22px;height:22px;border-radius:6px;background:' . esc_attr( $color ) . '"></span>' : '—';
}

/*
 * Migrace: term meta ek_color → ekt_color (A elvisek).
 */
add_filter(
	'ekt_migrations',
	static function ( array $steps ): array {
		$steps['term-color/term-meta'] = 'ekt_term_color_migrate';
		return $steps;
	}
);

/**
 * Krok migrace: {p}_color → ekt_color.
 *
 * @return bool
 */
function ekt_term_color_migrate(): bool {
	$ok = true;
	foreach ( ekt_legacy_prefixes() as $p ) {
		$ok = ekt_migrate_meta_key( 'term', $p . '_color', 'ekt_color' ) && $ok;
	}
	return $ok;
}
