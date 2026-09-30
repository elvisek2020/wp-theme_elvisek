<?php
/**
 * Obrázek rubriky: pole u rubriky (Příspěvky → Rubriky) a jeho použití jako náhledového obrázku článků.
 * Režim v Přizpůsobit → Články: vždy / jen bez vlastního obrázku / nepoužívat.
 * Na webu se podstrčí přes filtr post_thumbnail_id, takže funguje v kartách, detailu i Open Graph.
 * V administraci a REST API zůstává skutečný náhledový obrázek článku (data se nemění).
 */

defined( 'ABSPATH' ) || exit;

const EK_CAT_IMAGE_META = 'ek_image';

add_action( 'init', function () {
	register_term_meta( 'category', EK_CAT_IMAGE_META, array(
		'type'              => 'integer',
		'single'            => true,
		'sanitize_callback' => 'absint',
		'show_in_rest'      => true,
	) );
} );

function ek_category_image_id( ?WP_Term $cat ): int {
	if ( ! $cat ) {
		return 0;
	}
	$id = (int) get_term_meta( $cat->term_id, EK_CAT_IMAGE_META, true );
	return $id && wp_attachment_is_image( $id ) ? $id : 0;
}

add_filter( 'post_thumbnail_id', function ( $thumbnail_id, $post ) {
	if ( is_admin() || ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) ) {
		return $thumbnail_id;
	}
	$post = get_post( $post );
	if ( ! $post || 'post' !== $post->post_type ) {
		return $thumbnail_id;
	}
	$mode = ek_opt( 'ek_cat_image_mode' );
	if ( 'off' === $mode || ( 'fallback' === $mode && $thumbnail_id ) ) {
		return $thumbnail_id;
	}
	$cat_img = ek_category_image_id( ek_primary_category( $post ) );
	return $cat_img ?: $thumbnail_id;
}, 10, 2 );

/* ---------- Administrace: pole u rubriky ---------- */

function ek_cat_image_field( int $value = 0 ): void {
	$img = $value ? wp_get_attachment_image( $value, 'ek-card', false, array( 'style' => 'max-width:320px;height:auto;border-radius:8px;display:block' ) ) : '';
	?>
	<div class="ek-cat-image" data-ek-cat-image>
		<input type="hidden" name="ek_cat_image" value="<?php echo esc_attr( $value ?: '' ); ?>">
		<div class="ek-cat-image__preview" style="margin-bottom:8px"><?php echo $img; ?></div>
		<button type="button" class="button" data-ek-pick>Vybrat obrázek</button>
		<button type="button" class="button-link" data-ek-clear <?php echo $value ? '' : 'hidden'; ?>>Odebrat</button>
		<p class="description">Ilustrace rubriky, 1600 × 900 px (16 : 9), motiv uprostřed. Použije se jako náhled článků podle nastavení v Přizpůsobit → Články.</p>
	</div>
	<?php
}

add_action( 'category_add_form_fields', function () {
	echo '<div class="form-field"><label>Obrázek rubriky</label>';
	ek_cat_image_field();
	echo '</div>';
} );

add_action( 'category_edit_form_fields', function ( WP_Term $term ) {
	echo '<tr class="form-field"><th scope="row"><label>Obrázek rubriky</label></th><td>';
	ek_cat_image_field( (int) get_term_meta( $term->term_id, EK_CAT_IMAGE_META, true ) );
	echo '</td></tr>';
} );

$ek_cat_image_save = function ( int $term_id ) {
	if ( ! isset( $_POST['ek_cat_image'] ) || ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$id = absint( $_POST['ek_cat_image'] );
	$id ? update_term_meta( $term_id, EK_CAT_IMAGE_META, $id ) : delete_term_meta( $term_id, EK_CAT_IMAGE_META );
};
add_action( 'created_category', $ek_cat_image_save );
add_action( 'edited_category', $ek_cat_image_save );

// Sloupec s náhledem v seznamu rubrik.
add_filter( 'manage_edit-category_columns', function ( $cols ) {
	return array_slice( $cols, 0, 1, true ) + array( 'ek_image' => 'Obrázek' ) + array_slice( $cols, 1, null, true );
} );
add_filter( 'manage_category_custom_column', function ( $out, $col, $term_id ) {
	if ( 'ek_image' !== $col ) {
		return $out;
	}
	$id = (int) get_term_meta( $term_id, EK_CAT_IMAGE_META, true );
	return $id ? wp_get_attachment_image( $id, array( 96, 54 ), false, array( 'style' => 'width:96px;height:54px;object-fit:cover;border-radius:4px' ) ) : '—';
}, 10, 3 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) || 'category' !== ( $_GET['taxonomy'] ?? '' ) ) {
		return;
	}
	wp_enqueue_media();
	wp_add_inline_script( 'media-editor', <<<'JS'
document.addEventListener('click', (e) => {
	const box = e.target.closest('[data-ek-cat-image]');
	if (!box) return;
	const input = box.querySelector('input[name="ek_cat_image"]');
	const preview = box.querySelector('.ek-cat-image__preview');
	const clear = box.querySelector('[data-ek-clear]');
	if (e.target.closest('[data-ek-pick]')) {
		e.preventDefault();
		const frame = wp.media({ title: 'Obrázek rubriky', library: { type: 'image' }, multiple: false, button: { text: 'Použít' } });
		frame.on('select', () => {
			const a = frame.state().get('selection').first().toJSON();
			input.value = a.id;
			const src = (a.sizes && (a.sizes['ek-card'] || a.sizes.medium || a.sizes.full)).url || a.url;
			preview.innerHTML = '<img src="' + src + '" style="max-width:320px;height:auto;border-radius:8px;display:block" alt="">';
			clear.hidden = false;
		});
		frame.open();
	}
	if (e.target.closest('[data-ek-clear]')) {
		e.preventDefault();
		input.value = '';
		preview.innerHTML = '';
		clear.hidden = true;
	}
});
// Po přidání rubriky přes AJAX vyčistit pole.
if (window.jQuery) jQuery(document).ajaxComplete((ev, xhr, s) => {
	if (s.data && String(s.data).includes('action=add-tag')) {
		document.querySelectorAll('#addtag [data-ek-cat-image] input').forEach((i) => { i.value = ''; });
		document.querySelectorAll('#addtag .ek-cat-image__preview').forEach((p) => { p.innerHTML = ''; });
	}
});
JS
	);
} );
