<?php
/**
 * Barva rubriky: jemný podtón u štítků rubrik a proužek nahoře na kartě článku.
 * Barva se nastavuje u rubriky (Příspěvky → Rubriky), bez nastavení se použije výchozí z palety.
 * Článek může mít víc rubrik: každý štítek má svou barvu, proužek na kartě se rozdělí na úseky.
 */

defined( 'ABSPATH' ) || exit;

const EK_CAT_COLOR_META = 'ek_color';

add_action( 'init', function () {
	register_term_meta( 'category', EK_CAT_COLOR_META, array(
		'type'              => 'string',
		'single'            => true,
		'sanitize_callback' => 'ek_cat_color_sanitize',
		'show_in_rest'      => true,
	) );
} );

function ek_cat_color_sanitize( $value ): string {
	$value = strtolower( trim( (string) $value ) );
	return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
}

/**
 * Výchozí barvy pro známé rubriky (podle slugu), ostatní dostanou barvu odvozenou ze slugu.
 */
function ek_cat_color_default( WP_Term $cat ): string {
	$map = array(
		'raspberry-pi'  => '#c51a4a',
		'osx'           => '#64748b',
		'debian'        => '#a80030',
		'ios'           => '#2f7fe0',
		'loxone'        => '#5fae3e',
		'synology'      => '#2a5fa8',
		'webportfolio'  => '#7c4dce',
		'zigbee'        => '#d39a00',
		'car'           => '#2f7d6d',
		'aliexpress'    => '#e4572e',
		'homeassistant' => '#18a6dd',
		'ai-tools'      => '#d4704f',
		'nezarazene'    => '#8a94a3',
	);
	if ( isset( $map[ $cat->slug ] ) ) {
		return $map[ $cat->slug ];
	}
	// Odstín ze slugu, střední sytost i jas – ať je podtón v obou režimech jemný.
	$h = hexdec( substr( md5( $cat->slug ), 0, 4 ) ) % 360;
	return ek_hsl_hex( $h, 55, 48 );
}

function ek_hsl_hex( int $h, int $s, int $l ): string {
	$s /= 100;
	$l /= 100;
	$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
	$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
	$m = $l - $c / 2;
	$rgb = match ( intdiv( $h, 60 ) ) {
		0 => array( $c, $x, 0 ),
		1 => array( $x, $c, 0 ),
		2 => array( 0, $c, $x ),
		3 => array( 0, $x, $c ),
		4 => array( $x, 0, $c ),
		default => array( $c, 0, $x ),
	};
	return sprintf( '#%02x%02x%02x', ...array_map( fn( $v ) => (int) round( ( $v + $m ) * 255 ), $rgb ) );
}

/**
 * Barva rubriky (#rrggbb).
 */
function ek_cat_color( ?WP_Term $cat ): string {
	if ( ! $cat ) {
		return '';
	}
	$own = ek_cat_color_sanitize( get_term_meta( $cat->term_id, EK_CAT_COLOR_META, true ) );
	return $own ?: ek_cat_color_default( $cat );
}

/**
 * Atribut style s CSS proměnnou --ek-cat (pro štítky, dlaždice, položky menu).
 */
function ek_cat_style( ?WP_Term $cat ): string {
	$color = ek_cat_color( $cat );
	return $color ? ' style="--ek-cat:' . esc_attr( $color ) . '"' : '';
}

/**
 * Proužek na kartě článku: jedna barva, nebo stejně dlouhé úseky pro víc rubrik.
 */
function ek_cat_stripe_style( $post = null ): string {
	$default = (int) get_option( 'default_category' );
	$cats    = array_values( array_filter( get_the_category( $post ? get_post( $post )->ID : 0 ), fn( $c ) => (int) $c->term_id !== $default ) );
	if ( ! $cats ) {
		return '';
	}
	$colors = array_map( 'ek_cat_color', array_slice( $cats, 0, 4 ) );
	if ( 1 === count( $colors ) ) {
		$bg = $colors[0];
	} else {
		$n     = count( $colors );
		$stops = array();
		foreach ( $colors as $i => $c ) {
			$stops[] = sprintf( '%s %s%% %s%%', $c, round( $i * 100 / $n, 2 ), round( ( $i + 1 ) * 100 / $n, 2 ) );
		}
		$bg = 'linear-gradient(90deg,' . implode( ',', $stops ) . ')';
	}
	return ' style="--ek-stripe:' . esc_attr( $bg ) . '"';
}

/* ---------- Administrace: pole u rubriky ---------- */

function ek_cat_color_field( ?WP_Term $term = null ): void {
	$own = $term ? ek_cat_color_sanitize( get_term_meta( $term->term_id, EK_CAT_COLOR_META, true ) ) : '';
	$def = $term ? ek_cat_color_default( $term ) : '#64748b';
	?>
	<input type="color" name="ek_cat_color" value="<?php echo esc_attr( $own ?: $def ); ?>" data-default="<?php echo esc_attr( $def ); ?>">
	<label style="margin-left:10px"><input type="checkbox" name="ek_cat_color_default" value="1" <?php checked( '' === $own ); ?>> výchozí barva</label>
	<p class="description">Jemný podtón štítku rubriky a proužku na kartě článku. Text zůstává neutrální, takže stačí výrazná barva – na webu se použije jen slabě.</p>
	<?php
}

add_action( 'category_add_form_fields', function () {
	echo '<div class="form-field"><label>Barva rubriky</label>';
	ek_cat_color_field();
	echo '</div>';
} );

add_action( 'category_edit_form_fields', function ( WP_Term $term ) {
	echo '<tr class="form-field"><th scope="row"><label>Barva rubriky</label></th><td>';
	ek_cat_color_field( $term );
	echo '</td></tr>';
} );

$ek_cat_color_save = function ( int $term_id ) {
	if ( ! isset( $_POST['ek_cat_color'] ) || ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$color = ek_cat_color_sanitize( wp_unslash( $_POST['ek_cat_color'] ) );
	if ( ! empty( $_POST['ek_cat_color_default'] ) || ! $color ) {
		delete_term_meta( $term_id, EK_CAT_COLOR_META );
	} else {
		update_term_meta( $term_id, EK_CAT_COLOR_META, $color );
	}
};
add_action( 'created_category', $ek_cat_color_save );
add_action( 'edited_category', $ek_cat_color_save );

// Změna barvy v poli odškrtne „výchozí barva“.
add_action( 'admin_footer-term.php', 'ek_cat_color_admin_js' );
add_action( 'admin_footer-edit-tags.php', 'ek_cat_color_admin_js' );
function ek_cat_color_admin_js(): void {
	if ( 'category' !== ( $_GET['taxonomy'] ?? '' ) ) {
		return;
	}
	echo "<script>document.addEventListener('input',function(e){if(e.target.name==='ek_cat_color'){var c=e.target.form&&e.target.form.querySelector('[name=ek_cat_color_default]');if(c)c.checked=false;}});</script>";
}

// Sloupec s barvou v seznamu rubrik.
add_filter( 'manage_edit-category_columns', function ( $cols ) {
	return array_slice( $cols, 0, 2, true ) + array( 'ek_color' => 'Barva' ) + array_slice( $cols, 2, null, true );
}, 11 );
add_filter( 'manage_category_custom_column', function ( $out, $col, $term_id ) {
	if ( 'ek_color' !== $col ) {
		return $out;
	}
	$term = get_term( $term_id, 'category' );
	$c    = $term instanceof WP_Term ? ek_cat_color( $term ) : '';
	return $c ? '<span style="display:inline-block;width:22px;height:22px;border-radius:6px;background:' . esc_attr( $c ) . '"></span>' : '—';
}, 10, 3 );
