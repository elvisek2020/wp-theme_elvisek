<?php
/**
 * EKT module: term-image
 * Version: 1.0.0
 * Requires: common
 * Replaces: categories-images, wp-term-images
 * Assets: –
 * Summary: Obrázek rubriky (term meta ekt_image) jako náhledový obrázek článků podle volby vždy / jen bez vlastního / vypnuto.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Obrázek se vybírá u rubriky (Příspěvky → Rubriky). Na webu ho filtr post_thumbnail_id podstrčí
 * článkům podle volby Přizpůsobit → Články → term_image_mode (always = vždy, fallback = jen
 * článkům bez vlastního obrázku, off = nepoužívat), takže funguje v kartách, detailu i Open Graph.
 * V administraci a REST API zůstává skutečný náhledový obrázek článku (data se nemění).
 * Plugin SLUG-core (modul maint-media) dostane ID obrázků rubrik přes filtr ekc_media_used_ids,
 * aby je nepovažoval za nepoužité.
 *
 * Veřejné funkce: ekt_term_image_id( $term ), ekt_term_image_taxonomies().
 * Přizpůsobit: ekt_posts → term_image_mode (výchozí always).
 * Filtry: ekt_term_image_taxonomies( ['category'] ), ekt_term_image_post_types( ['post'] ),
 * ekt_term_image_term( $term, $post ) (který term dává obrázek; výchozí ekt_primary_category()).
 */

ekt_setting_register(
	'term_image_mode',
	array(
		'type'        => 'select',
		'default'     => 'always',
		'label'       => 'Obrázek rubriky jako náhled článku',
		'description' => 'Obrázek se nastavuje u rubriky (Příspěvky → Rubriky).',
		'section'     => 'posts',
		'priority'    => 40,
		'choices'     => array(
			'always'   => 'Vždy (i místo vlastního obrázku článku)',
			'fallback' => 'Jen u článků bez vlastního obrázku',
			'off'      => 'Nepoužívat',
		),
	)
);

/**
 * Taxonomie s obrázkem.
 *
 * @return string[]
 */
function ekt_term_image_taxonomies(): array {
	return array_values( array_filter( (array) apply_filters( 'ekt_term_image_taxonomies', array( 'category' ) ), 'taxonomy_exists' ) );
}

/**
 * ID obrázku termu (0 = žádný nebo smazaný).
 *
 * @param WP_Term|int|null $term Term.
 * @return int
 */
function ekt_term_image_id( $term ): int {
	$term = is_numeric( $term ) ? get_term( (int) $term ) : $term;
	if ( ! $term instanceof WP_Term ) {
		return 0;
	}
	$id = (int) get_term_meta( $term->term_id, 'ekt_image', true );
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/**
 * Náhledový obrázek článku na webu podle volby term_image_mode.
 *
 * @param int|false        $thumbnail_id ID obrázku článku.
 * @param int|WP_Post|null $post         Článek.
 * @return int|false
 */
function ekt_term_image_thumbnail( $thumbnail_id, $post ) {
	if ( is_admin() || ( function_exists( 'wp_is_serving_rest_request' ) ? wp_is_serving_rest_request() : ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) ) {
		return $thumbnail_id;
	}
	$post = get_post( $post );
	if ( ! $post || ! in_array( $post->post_type, (array) apply_filters( 'ekt_term_image_post_types', array( 'post' ) ), true ) ) {
		return $thumbnail_id;
	}
	$mode = (string) ekt_opt( 'term_image_mode' );
	if ( 'off' === $mode || ( 'fallback' === $mode && $thumbnail_id ) ) {
		return $thumbnail_id;
	}
	$term = apply_filters( 'ekt_term_image_term', ekt_primary_category( $post ), $post );
	$img  = ekt_term_image_id( $term );
	return $img ? $img : $thumbnail_id;
}
add_filter( 'post_thumbnail_id', 'ekt_term_image_thumbnail', 10, 2 );

/**
 * Plugin maint-media: obrázky rubrik jsou použité (nekarantenovat).
 *
 * @param array $ids Použitá ID.
 * @return array
 */
function ekt_term_image_used_ids( $ids ): array {
	global $wpdb;
	$keys = array_merge( array( 'ekt_image' ), array_map( static fn( $p ) => $p . '_image', ekt_legacy_prefixes() ) );
	$in   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholdery v $in
	$found = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key IN ($in)", $keys ) );
	return array_values( array_unique( array_merge( array_map( 'intval', (array) $ids ), array_filter( array_map( 'intval', $found ) ) ) ) );
}
add_filter( 'ekc_media_used_ids', 'ekt_term_image_used_ids' );

/**
 * Term meta, pole a sloupce v administraci.
 */
function ekt_term_image_init(): void {
	foreach ( ekt_term_image_taxonomies() as $tax ) {
		register_term_meta(
			$tax,
			'ekt_image',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
			)
		);
		add_action( $tax . '_add_form_fields', 'ekt_term_image_add_field' );
		add_action( $tax . '_edit_form_fields', 'ekt_term_image_edit_field' );
		add_action( 'created_' . $tax, 'ekt_term_image_save' );
		add_action( 'edited_' . $tax, 'ekt_term_image_save' );
		add_filter( 'manage_edit-' . $tax . '_columns', 'ekt_term_image_columns' );
		add_filter( 'manage_' . $tax . '_custom_column', 'ekt_term_image_column', 10, 3 );
	}
}
add_action( 'init', 'ekt_term_image_init', 20 );

/**
 * Pole obrázku.
 *
 * @param int $value ID obrázku.
 */
function ekt_term_image_field( int $value = 0 ): void {
	wp_nonce_field( 'ekt_term_image', '_ekt_term_image_nonce' );
	$img = $value ? wp_get_attachment_image( $value, 'medium', false, array( 'style' => 'max-width:320px;height:auto;border-radius:8px;display:block' ) ) : '';
	?>
	<div class="ekt-term-image" data-ekt-term-image>
		<input type="hidden" name="ekt_term_image" value="<?php echo esc_attr( $value ? (string) $value : '' ); ?>">
		<div class="ekt-term-image__preview" style="margin-bottom:8px"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image ?></div>
		<button type="button" class="button" data-ekt-pick>Vybrat obrázek</button>
		<button type="button" class="button-link" data-ekt-clear <?php echo $value ? '' : 'hidden'; ?>>Odebrat</button>
		<p class="description">Ilustrace rubriky, ideálně 1600 × 900 px (16 : 9) s motivem uprostřed. Jako náhled článků podle Přizpůsobit → Články.</p>
	</div>
	<?php
}

/**
 * Pole ve formuláři přidání termu.
 */
function ekt_term_image_add_field(): void {
	echo '<div class="form-field"><label>Obrázek</label>';
	ekt_term_image_field();
	echo '</div>';
}

/**
 * Pole ve formuláři úpravy termu.
 *
 * @param WP_Term $term Term.
 */
function ekt_term_image_edit_field( $term ): void {
	echo '<tr class="form-field"><th scope="row"><label>Obrázek</label></th><td>';
	ekt_term_image_field( $term instanceof WP_Term ? (int) get_term_meta( $term->term_id, 'ekt_image', true ) : 0 );
	echo '</td></tr>';
}

/**
 * Uložení obrázku.
 *
 * @param int $term_id Term.
 */
function ekt_term_image_save( $term_id ): void {
	if ( ! isset( $_POST['ekt_term_image'], $_POST['_ekt_term_image_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_ekt_term_image_nonce'] ) ), 'ekt_term_image' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_term', (int) $term_id ) ) {
		return;
	}
	$id = absint( wp_unslash( $_POST['ekt_term_image'] ) );
	if ( $id && wp_attachment_is_image( $id ) ) {
		update_term_meta( (int) $term_id, 'ekt_image', $id );
	} else {
		delete_term_meta( (int) $term_id, 'ekt_image' );
	}
}

/**
 * Sloupec Obrázek v seznamu termů.
 *
 * @param array $cols Sloupce.
 * @return array
 */
function ekt_term_image_columns( $cols ): array {
	$cols = (array) $cols;
	return array_slice( $cols, 0, 1, true ) + array( 'ekt_image' => 'Obrázek' ) + array_slice( $cols, 1, null, true );
}

/**
 * Obsah sloupce Obrázek.
 *
 * @param string $out     Výstup.
 * @param string $col     Sloupec.
 * @param int    $term_id Term.
 * @return string
 */
function ekt_term_image_column( $out, $col, $term_id ) {
	if ( 'ekt_image' !== $col ) {
		return $out;
	}
	$id = ekt_term_image_id( (int) $term_id );
	return $id ? wp_get_attachment_image( $id, array( 96, 54 ), false, array( 'style' => 'width:96px;height:54px;object-fit:cover;border-radius:4px' ) ) : '—';
}

/**
 * Výběr obrázku z knihovny médií na stránce termů.
 *
 * @param string $hook Stránka administrace.
 */
function ekt_term_image_admin_assets( $hook ): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) || ! $screen || ! in_array( (string) $screen->taxonomy, ekt_term_image_taxonomies(), true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_add_inline_script(
		'media-editor',
		<<<'JS'
document.addEventListener('click', (e) => {
	const box = e.target.closest('[data-ekt-term-image]');
	if (!box) return;
	const input = box.querySelector('input[name="ekt_term_image"]');
	const preview = box.querySelector('.ekt-term-image__preview');
	const clear = box.querySelector('[data-ekt-clear]');
	if (e.target.closest('[data-ekt-pick]')) {
		e.preventDefault();
		const frame = wp.media({ title: 'Obrázek', library: { type: 'image' }, multiple: false, button: { text: 'Použít' } });
		frame.on('select', () => {
			const a = frame.state().get('selection').first().toJSON();
			input.value = a.id;
			const size = a.sizes && (a.sizes.medium || a.sizes.full);
			const img = document.createElement('img');
			img.src = size ? size.url : a.url;
			img.alt = '';
			img.style.cssText = 'max-width:320px;height:auto;border-radius:8px;display:block';
			preview.replaceChildren(img);
			clear.hidden = false;
		});
		frame.open();
	}
	if (e.target.closest('[data-ekt-clear]')) {
		e.preventDefault();
		input.value = '';
		preview.replaceChildren();
		clear.hidden = true;
	}
});
if (window.jQuery) jQuery(document).ajaxComplete((ev, xhr, s) => {
	if (s && s.data && String(s.data).includes('action=add-tag')) {
		document.querySelectorAll('#addtag [data-ekt-term-image] input[name="ekt_term_image"]').forEach((i) => { i.value = ''; });
		document.querySelectorAll('#addtag .ekt-term-image__preview').forEach((p) => p.replaceChildren());
	}
});
JS
	);
}
add_action( 'admin_enqueue_scripts', 'ekt_term_image_admin_assets' );

/*
 * Migrace: term meta ek_image → ekt_image (A elvisek). Theme_mod ek_cat_image_mode →
 * ekt_term_image_mode převádí common.
 */
add_filter(
	'ekt_migrations',
	static function ( array $steps ): array {
		$steps['term-image/term-meta'] = 'ekt_term_image_migrate';
		return $steps;
	}
);

/**
 * Krok migrace: {p}_image → ekt_image.
 *
 * @return bool
 */
function ekt_term_image_migrate(): bool {
	$ok = true;
	foreach ( ekt_legacy_prefixes() as $p ) {
		$ok = ekt_migrate_meta_key( 'term', $p . '_image', 'ekt_image' ) && $ok;
	}
	return $ok;
}
