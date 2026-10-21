<?php
/**
 * EKT module: common
 * Version: 1.0.1
 * Requires: –
 * Replaces: –
 * Assets: –
 * Summary: Společné API šablony: assets, registr Přizpůsobit a Nastavení článku, ikony, helpery, setup, převzetí ze staré šablony a migrace dat.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Obsah:
 *  1) Assets a výstup (ekt_asset_ver, ekt_enqueue_script, ekt_enqueue_style, ekt_render)
 *  2) Registr Přizpůsobit (ekt_setting_register, ekt_opt, filtr ekt_defaults)
 *  3) Registr polí „Nastavení článku“ (ekt_post_setting_register, ekt_post_setting)
 *  4) Očištění textu (ekt_sanitize_text, ekt_sanitize_date)
 *  5) Původní pluginy (ekt_legacy_active)
 *  6) Ikony (ekt_icon, filtr ekt_icons)
 *  7) Helpery (rubrika, titulek, tvary, copyright, patička, písma, termy)
 *  8) Setup a úklid <head>
 *  9) Upozornění na chybějící plugin SLUG-core
 * 10) Přepnutí šablony (after_switch_theme)
 * 11) Migrace dat (ekt_schema, filtr ekt_migrations)
 *
 * Identita šablony (EKT_SLUG, EKT_NAME, EKT_VERSION, EKT_DIR, EKT_URI, EKT_REPO, EKT_LEGACY)
 * je ve functions.php. Tento soubor je na všech webech bajtově shodný.
 */

const EKT_SCHEMA = 1; // verze migrace v common; zvýšit při novém kroku v ekt_migration_steps()

/* =========================================================================
 * 1) Assets a výstup
 * Soubory kanonických modulů leží v assets/ekt/ (js/<modul>.js, css/<modul>.css).
 * Verze podle filemtime, skripty s defer v patičce. Chybějící soubor = modul bez assetu,
 * nic se nenačte (vypnutí modulu je smazání jeho souborů).
 * ====================================================================== */

/**
 * Absolutní cesta k souboru v assets/ekt/.
 *
 * @param string $rel Cesta relativně k assets/ekt/ (např. js/lightbox.js).
 * @return string
 */
function ekt_asset_path( string $rel ): string {
	return EKT_DIR . '/assets/ekt/' . ltrim( $rel, '/' );
}

/**
 * Adresa souboru v assets/ekt/.
 *
 * @param string $rel Cesta relativně k assets/ekt/.
 * @return string
 */
function ekt_asset_url( string $rel ): string {
	return EKT_URI . '/assets/ekt/' . ltrim( $rel, '/' );
}

/**
 * Verze souboru podle času změny (cache prohlížeče i hostingu se po nahrání obnoví sama).
 *
 * @param string $rel Cesta relativně k assets/ekt/.
 * @return string
 */
function ekt_asset_ver( string $rel ): string {
	$path = ekt_asset_path( $rel );
	return is_file( $path ) ? (string) filemtime( $path ) : EKT_VERSION;
}

/**
 * Načte skript z assets/ekt/ (výchozí defer, v patičce).
 *
 * @param string $handle Handle (ekt-<modul>).
 * @param string $rel    Cesta relativně k assets/ekt/.
 * @param array  $deps   Závislosti.
 * @param array  $args   strategy ('defer'|'async'|''), in_footer (bool),
 *                       data (array) + object (název globální proměnné) = konfigurace před skriptem.
 * @return bool Načteno (false = soubor chybí).
 */
function ekt_enqueue_script( string $handle, string $rel, array $deps = array(), array $args = array() ): bool {
	if ( ! is_file( ekt_asset_path( $rel ) ) ) {
		return false;
	}
	$args = array_merge(
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
			'data'      => null,
			'object'    => '',
		),
		$args
	);
	$load = array( 'in_footer' => (bool) $args['in_footer'] );
	if ( '' !== $args['strategy'] ) {
		$load['strategy'] = $args['strategy'];
	}
	wp_enqueue_script( $handle, ekt_asset_url( $rel ), $deps, ekt_asset_ver( $rel ), $load );
	if ( is_array( $args['data'] ) && preg_match( '/^[A-Za-z_$][A-Za-z0-9_$]*$/', (string) $args['object'] ) ) {
		$json = wp_json_encode( $args['data'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE );
		wp_add_inline_script( $handle, 'window.' . $args['object'] . '=' . $json . ';', 'before' );
	}
	return true;
}

/**
 * Načte styl z assets/ekt/.
 *
 * @param string $handle Handle (ekt-<modul>).
 * @param string $rel    Cesta relativně k assets/ekt/.
 * @param array  $deps   Závislosti.
 * @return bool Načteno (false = soubor chybí).
 */
function ekt_enqueue_style( string $handle, string $rel, array $deps = array() ): bool {
	if ( ! is_file( ekt_asset_path( $rel ) ) ) {
		return false;
	}
	wp_enqueue_style( $handle, ekt_asset_url( $rel ), $deps, ekt_asset_ver( $rel ) );
	return true;
}

/**
 * Výstup modulu s možností přepsání šablonou: když existuje template-parts/ekt/<modul>.php,
 * použije se (dostane $args), jinak výchozí markup. Výsledek jde přes filtr ekt_<modul>_html
 * (pomlčky v názvu modulu → podtržítka).
 *
 * @param string          $module  Název modulu (lightbox, site-info…).
 * @param string|callable $default Výchozí HTML, nebo callable( array $args ): string (zavolá se jen bez override).
 * @param array           $args    Data pro šablonu a filtr.
 * @return string HTML (escapované autorem markupu).
 */
function ekt_render( string $module, $default, array $args = array() ): string {
	$part = 'template-parts/ekt/' . sanitize_file_name( $module );
	if ( locate_template( $part . '.php' ) ) {
		ob_start();
		get_template_part( $part, null, $args );
		$html = (string) ob_get_clean();
	} else {
		$html = is_callable( $default ) ? (string) call_user_func( $default, $args ) : (string) $default;
	}
	return (string) apply_filters( 'ekt_' . str_replace( '-', '_', $module ) . '_html', $html, $args );
}

/* =========================================================================
 * 2) Registr Přizpůsobit
 * Moduly registrují pole hned při načtení souboru. Hodnota je v theme_mod ekt_<key>.
 * Výchozí hodnoty jdou přes filtr ekt_defaults (i „výchozí ze starého webu“ v inc/site-*.php),
 * proto se čte vždy přes ekt_opt() – get_theme_mod() bez druhého parametru.
 * ====================================================================== */

/**
 * Úložiště polí (interní).
 *
 * @param string|null $key   Klíč k přidání.
 * @param array|null  $field Popis pole.
 * @return array<string,array>
 */
function ekt_setting_fields( ?string $key = null, ?array $field = null ): array {
	static $fields = array();
	if ( null !== $key && null !== $field ) {
		$fields[ $key ] = $field;
	}
	return $fields;
}

/**
 * Úložiště sekcí Přizpůsobit (interní). Výchozí: ekt_general, ekt_footer, ekt_posts, ekt_seo.
 *
 * @param string|null $id    ID sekce (ekt_…).
 * @param array|null  $args  title, priority, description.
 * @return array<string,array>
 */
function ekt_customizer_sections( ?string $id = null, ?array $args = null ): array {
	static $sections = array(
		'ekt_general' => array(
			'title'       => 'Obecné',
			'priority'    => 10,
			'description' => '',
		),
		'ekt_footer'  => array(
			'title'       => 'Patička',
			'priority'    => 20,
			'description' => '',
		),
		'ekt_posts'   => array(
			'title'       => 'Články',
			'priority'    => 30,
			'description' => '',
		),
		'ekt_seo'     => array(
			'title'       => 'SEO a analytika',
			'priority'    => 40,
			'description' => '',
		),
	);
	if ( null !== $id && null !== $args ) {
		$sections[ $id ] = array_merge(
			array(
				'title'       => $id,
				'priority'    => 50,
				'description' => '',
			),
			$args
		);
	}
	return $sections;
}

/**
 * Modul přidá vlastní sekci do panelu šablony v Přizpůsobit.
 *
 * @param string $id       ID sekce (ekt_<oblast>).
 * @param string $title    Název.
 * @param int    $priority Pořadí (obsazené 10, 20, 30, 40).
 */
function ekt_customizer_section( string $id, string $title, int $priority = 50 ): void {
	ekt_customizer_sections(
		ekt_section_id( $id ),
		array(
			'title'    => $title,
			'priority' => $priority,
		)
	);
}

/**
 * ID sekce s prefixem (general → ekt_general).
 *
 * @param string $id ID.
 * @return string
 */
function ekt_section_id( string $id ): string {
	$id = sanitize_key( $id );
	return str_starts_with( $id, 'ekt_' ) ? $id : 'ekt_' . $id;
}

/**
 * Modul zaregistruje pole Přizpůsobit (theme_mod ekt_<key>).
 *
 * @param string $key  Klíč bez prefixu (malá písmena, podtržítka), např. ga_id.
 * @param array  $args type (text|textarea|url|email|checkbox|select|number|color|image|sortable_terms),
 *                     default, label, description, section (general|footer|posts|seo|vlastní),
 *                     choices (select: hodnota => popisek), sanitize (callable( $value, $field )),
 *                     priority, input_attrs (number: min, max, step), taxonomy (sortable_terms, výchozí category).
 */
function ekt_setting_register( string $key, array $args ): void {
	$types = array( 'text', 'textarea', 'url', 'email', 'checkbox', 'select', 'number', 'color', 'image', 'sortable_terms' );
	$type  = in_array( $args['type'] ?? '', $types, true ) ? $args['type'] : 'text';
	$empty = array(
		'checkbox' => 0,
		'number'   => 0,
		'image'    => 0,
	);
	$field = array_merge(
		array(
			'type'        => $type,
			'default'     => $empty[ $type ] ?? '',
			'label'       => $key,
			'description' => '',
			'section'     => 'general',
			'choices'     => array(),
			'sanitize'    => null,
			'priority'    => 10,
			'input_attrs' => array(),
			'taxonomy'    => 'category',
		),
		$args
	);
	$field['type']    = $type;
	$field['section'] = ekt_section_id( (string) $field['section'] );
	if ( 'select' === $type && '' === $field['default'] && $field['choices'] ) {
		$field['default'] = (string) array_key_first( $field['choices'] );
	}
	if ( 'checkbox' === $type ) {
		$field['default'] = empty( $field['default'] ) ? 0 : 1;
	}
	ekt_setting_fields( sanitize_key( $key ), $field );
}

/**
 * Očištění hodnoty pole Přizpůsobit podle typu. Checkbox se ukládá jako 1/0 (ne true/false:
 * false je pro get_theme_mod() „nenastaveno“ a vrátila by se výchozí hodnota).
 *
 * @param array $field Popis pole.
 * @param mixed $value Hodnota.
 * @return mixed
 */
function ekt_setting_sanitize( array $field, $value ) {
	if ( is_callable( $field['sanitize'] ?? null ) ) {
		return call_user_func( $field['sanitize'], $value, $field );
	}
	switch ( $field['type'] ) {
		case 'checkbox':
			return ( empty( $value ) || 'false' === $value ) ? 0 : 1;
		case 'number':
			$value = is_numeric( $value ) ? (int) $value : (int) $field['default'];
			if ( isset( $field['input_attrs']['min'] ) ) {
				$value = max( (int) $field['input_attrs']['min'], $value );
			}
			if ( isset( $field['input_attrs']['max'] ) ) {
				$value = min( (int) $field['input_attrs']['max'], $value );
			}
			return $value;
		case 'image':
			return absint( $value );
		case 'color':
			return (string) sanitize_hex_color( (string) $value );
		case 'url':
			return esc_url_raw( trim( (string) $value ) );
		case 'email':
			return sanitize_email( (string) $value );
		case 'select':
			return array_key_exists( (string) $value, (array) $field['choices'] ) ? (string) $value : (string) $field['default'];
		case 'sortable_terms':
			return implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ) );
		case 'textarea':
			return ekt_sanitize_text( $value, true );
	}
	return ekt_sanitize_text( $value );
}

/**
 * Výchozí hodnoty registrovaných polí (klíč bez prefixu => hodnota) po filtru ekt_defaults.
 * Filtr přidává i výchozí hodnoty ze starého webu (GA ID, sítě), které nejsou v registru.
 *
 * @return array
 */
function ekt_defaults(): array {
	$defaults = array();
	foreach ( ekt_setting_fields() as $key => $field ) {
		$defaults[ $key ] = $field['default'];
	}
	return (array) apply_filters( 'ekt_defaults', $defaults );
}

/**
 * Hodnota nastavení šablony. Čte theme_mod ekt_<key> bez druhého parametru; nenastavené
 * (false) = výchozí z ekt_defaults. Prázdný řetězec je platná volba (např. vymazané GA ID).
 *
 * @param string $key Klíč bez prefixu.
 * @return mixed checkbox → bool, number a image → int, ostatní → string; neznámý klíč bez výchozí → null.
 */
function ekt_opt( string $key ) {
	$value = get_theme_mod( 'ekt_' . $key );
	if ( false === $value ) {
		$defaults = ekt_defaults();
		$value    = $defaults[ $key ] ?? null;
	}
	$fields = ekt_setting_fields();
	switch ( $fields[ $key ]['type'] ?? '' ) {
		case 'checkbox':
			return ! empty( $value );
		case 'number':
		case 'image':
			return (int) $value;
		case '':
			return $value;
	}
	return null === $value ? '' : (string) $value;
}

/**
 * Uloží hodnotu nastavení šablony (očištěnou podle registru).
 *
 * @param string $key   Klíč bez prefixu.
 * @param mixed  $value Hodnota.
 */
function ekt_opt_set( string $key, $value ): void {
	$fields = ekt_setting_fields();
	set_theme_mod( 'ekt_' . $key, isset( $fields[ $key ] ) ? ekt_setting_sanitize( $fields[ $key ], $value ) : $value );
}

/**
 * Vybrané termy z pole sortable_terms v uloženém pořadí. Nic nevybráno = nejpočetnější.
 *
 * @param string $key   Klíč pole.
 * @param int    $limit Kolik termů vrátit, když nic není vybráno (0 = žádné).
 * @return WP_Term[]
 */
function ekt_opt_terms( string $key, int $limit = 0 ): array {
	$fields   = ekt_setting_fields();
	$taxonomy = (string) ( $fields[ $key ]['taxonomy'] ?? 'category' );
	$ids      = array_filter( array_map( 'absint', explode( ',', (string) ekt_opt( $key ) ) ) );
	if ( $ids ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'include'    => $ids,
				'orderby'    => 'include',
				'hide_empty' => true,
			)
		);
		return is_array( $terms ) ? array_values( $terms ) : array();
	}
	if ( $limit <= 0 ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => $limit,
			'hide_empty' => true,
			'exclude'    => 'category' === $taxonomy ? array( (int) get_option( 'default_category' ) ) : array(),
		)
	);
	return is_array( $terms ) ? array_values( $terms ) : array();
}

/**
 * Ovládací prvek sortable_terms (zaškrtnout a přetáhnout). Třída vzniká až v Přizpůsobit,
 * kde existuje WP_Customize_Control.
 */
function ekt_customizer_define_controls(): void {
	if ( class_exists( 'EKT_Sortable_Terms_Control' ) || ! class_exists( 'WP_Customize_Control' ) ) {
		return;
	}

	/**
	 * Seznam termů se zaškrtávátky a řazením přetažením (jQuery UI sortable z Přizpůsobit).
	 */
	class EKT_Sortable_Terms_Control extends WP_Customize_Control {
		/**
		 * Typ prvku.
		 *
		 * @var string
		 */
		public $type = 'ekt_sortable_terms';

		/**
		 * Taxonomie.
		 *
		 * @var string
		 */
		public $taxonomy = 'category';

		/**
		 * Skripty prvku.
		 */
		public function enqueue() {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}

		/**
		 * Výpis prvku.
		 */
		public function render_content() {
			$selected = array_values( array_filter( array_map( 'absint', explode( ',', (string) $this->value() ) ) ) );
			$terms    = get_terms(
				array(
					'taxonomy'   => $this->taxonomy,
					'hide_empty' => false,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);
			$by_id    = array();
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$by_id[ $term->term_id ] = $term;
			}
			$ordered = array();
			foreach ( $selected as $id ) {
				if ( isset( $by_id[ $id ] ) ) {
					$ordered[] = $by_id[ $id ];
					unset( $by_id[ $id ] );
				}
			}
			$ordered = array_merge( $ordered, array_values( $by_id ) );
			?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php if ( $this->description ) : ?>
				<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>
			<ul class="ekt-terms-sort" style="margin:8px 0 0">
				<?php foreach ( $ordered as $term ) : ?>
					<li data-id="<?php echo (int) $term->term_id; ?>" style="display:flex;align-items:center;gap:8px;padding:6px 8px;margin:0 0 4px;background:#fff;border:1px solid #dcdcde;border-radius:4px;cursor:move">
						<span class="dashicons dashicons-menu" style="color:#a7aaad" aria-hidden="true"></span>
						<label style="flex:1;cursor:pointer"><input type="checkbox" <?php checked( in_array( (int) $term->term_id, $selected, true ) ); ?>> <?php echo esc_html( $term->name ); ?></label>
						<small style="color:#646970"><?php echo (int) $term->count; ?></small>
					</li>
				<?php endforeach; ?>
			</ul>
			<input type="hidden" class="ekt-terms-value" <?php $this->link(); ?> value="<?php echo esc_attr( implode( ',', $selected ) ); ?>">
			<script>
			( function ( $ ) {
				var $wrap = $( '#customize-control-<?php echo esc_js( $this->id ); ?>' );
				var $list = $wrap.find( '.ekt-terms-sort' );
				function save() {
					var ids = $list.children( 'li' ).filter( function () { return $( this ).find( 'input' ).prop( 'checked' ); } ).map( function () { return $( this ).data( 'id' ); } ).get();
					$wrap.find( '.ekt-terms-value' ).val( ids.join( ',' ) ).trigger( 'change' );
				}
				$list.sortable( { axis: 'y', update: save } );
				$list.on( 'change', 'input', save );
			} )( jQuery );
			</script>
			<?php
		}
	}
}
add_action( 'customize_register', 'ekt_customizer_define_controls', 1 );

/**
 * Panel šablony, sekce a pole v Přizpůsobit.
 *
 * @param WP_Customize_Manager $wpc Přizpůsobit.
 */
function ekt_customizer_register( $wpc ): void {
	$fields = ekt_setting_fields();
	if ( ! $fields ) {
		return;
	}
	$defaults = ekt_defaults();
	$wpc->add_panel(
		'ekt',
		array(
			'title'    => EKT_NAME,
			'priority' => 25,
		)
	);
	$used = array_unique( array_column( $fields, 'section' ) );
	foreach ( ekt_customizer_sections() as $id => $section ) {
		if ( in_array( $id, $used, true ) ) {
			$wpc->add_section(
				$id,
				array(
					'title'       => (string) $section['title'],
					'priority'    => (int) $section['priority'],
					'description' => (string) $section['description'],
					'panel'       => 'ekt',
				)
			);
		}
	}
	foreach ( $fields as $key => $field ) {
		$id = 'ekt_' . $key;
		if ( ! $wpc->get_section( $field['section'] ) ) {
			$wpc->add_section(
				$field['section'],
				array(
					'title' => $field['section'],
					'panel' => 'ekt',
				)
			);
		}
		$wpc->add_setting(
			$id,
			array(
				'default'           => $defaults[ $key ] ?? $field['default'],
				'sanitize_callback' => static fn( $value ) => ekt_setting_sanitize( $field, $value ),
			)
		);
		$control = array(
			'label'       => (string) $field['label'],
			'description' => (string) $field['description'],
			'section'     => $field['section'],
			'priority'    => (int) $field['priority'],
		);
		switch ( $field['type'] ) {
			case 'image':
				$wpc->add_control( new WP_Customize_Media_Control( $wpc, $id, array_merge( $control, array( 'mime_type' => 'image' ) ) ) );
				break;
			case 'color':
				$wpc->add_control( new WP_Customize_Color_Control( $wpc, $id, $control ) );
				break;
			case 'sortable_terms':
				if ( class_exists( 'EKT_Sortable_Terms_Control' ) ) {
					$wpc->add_control( new EKT_Sortable_Terms_Control( $wpc, $id, array_merge( $control, array( 'taxonomy' => (string) $field['taxonomy'] ) ) ) );
				}
				break;
			default:
				$control['type'] = $field['type'];
				if ( 'select' === $field['type'] ) {
					$control['choices'] = (array) $field['choices'];
				}
				if ( $field['input_attrs'] ) {
					$control['input_attrs'] = (array) $field['input_attrs'];
				}
				$wpc->add_control( $id, $control );
		}
	}
}
add_action( 'customize_register', 'ekt_customizer_register', 20 );

// Patička: copyright, kredit autora webu, odkaz Administrace (ekt_site_info_parts()).
ekt_setting_register(
	'copyright_name',
	array(
		'label'       => 'Copyright: jméno',
		'description' => 'Prázdné = název webu.',
		'section'     => 'footer',
		'priority'    => 10,
	)
);
ekt_setting_register(
	'copyright_since',
	array(
		'type'        => 'number',
		'label'       => 'Copyright: od roku',
		'description' => '0 = rok prvního článku.',
		'section'     => 'footer',
		'priority'    => 11,
		'input_attrs' => array(
			'min' => 0,
			'max' => 2100,
		),
	)
);
ekt_setting_register(
	'footer_text',
	array(
		'type'        => 'textarea',
		'label'       => 'Vlastní text místo copyrightu',
		'description' => 'Prázdné = „Copyright © roky jméno“.',
		'section'     => 'footer',
		'priority'    => 12,
	)
);
ekt_setting_register(
	'credit_name',
	array(
		'label'       => 'Autor webu (Design & code by)',
		'description' => 'Prázdné = bez kreditu.',
		'section'     => 'footer',
		'priority'    => 13,
	)
);
ekt_setting_register(
	'credit_url',
	array(
		'type'     => 'url',
		'label'    => 'Autor webu: odkaz',
		'section'  => 'footer',
		'priority' => 14,
	)
);
ekt_setting_register(
	'admin_link',
	array(
		'type'        => 'checkbox',
		'default'     => 1,
		'label'       => 'Odkaz Administrace v patičce',
		'description' => 'Jen pro nepřihlášené.',
		'section'     => 'footer',
		'priority'    => 15,
	)
);

/* =========================================================================
 * 3) Registr polí „Nastavení článku“
 * Jeden box v editoru (boční panel), každý modul přidá pole. Hodnota je v post meta ekt_<key>
 * (register_post_meta se show_in_rest). Prázdná hodnota = meta se smaže.
 * ====================================================================== */

/**
 * Úložiště polí (interní).
 *
 * @param string|null $key   Klíč k přidání.
 * @param array|null  $field Popis pole.
 * @return array<string,array>
 */
function ekt_post_setting_fields( ?string $key = null, ?array $field = null ): array {
	static $fields = array();
	if ( null !== $key && null !== $field ) {
		$fields[ $key ] = $field;
	}
	return $fields;
}

/**
 * Modul přidá pole do boxu „Nastavení článku“.
 *
 * @param string $key  Klíč bez prefixu (post meta ekt_<key>), např. updated.
 * @param array  $args type (date|select|textarea|checkbox|text), label, help, post_types (výchozí ['post']),
 *                     choices (select: hodnota => popisek; '' = výchozí volba), rows (textarea),
 *                     placeholder, priority, sanitize (callable( $value, $field ): string|bool).
 */
function ekt_post_setting_register( string $key, array $args ): void {
	$type  = in_array( $args['type'] ?? '', array( 'date', 'select', 'textarea', 'checkbox', 'text' ), true ) ? $args['type'] : 'text';
	$field = array_merge(
		array(
			'type'        => $type,
			'label'       => $key,
			'help'        => '',
			'post_types'  => array( 'post' ),
			'choices'     => array(),
			'rows'        => 4,
			'placeholder' => '',
			'priority'    => 10,
			'sanitize'    => null,
		),
		$args
	);
	$field['type']       = $type;
	$field['post_types'] = array_values( array_filter( array_map( 'sanitize_key', (array) $field['post_types'] ) ) );
	ekt_post_setting_fields( sanitize_key( $key ), $field );
}

/**
 * Očištění hodnoty pole Nastavení článku.
 *
 * @param array $field Popis pole.
 * @param mixed $value Hodnota.
 * @return string|bool
 */
function ekt_post_setting_sanitize( array $field, $value ) {
	if ( is_callable( $field['sanitize'] ?? null ) ) {
		return call_user_func( $field['sanitize'], $value, $field );
	}
	switch ( $field['type'] ) {
		case 'checkbox':
			return ! empty( $value ) && 'false' !== $value;
		case 'date':
			return ekt_sanitize_date( $value );
		case 'select':
			$value = (string) $value;
			return array_key_exists( $value, (array) $field['choices'] ) ? $value : '';
		case 'textarea':
			return ekt_sanitize_text( $value, true );
	}
	return ekt_sanitize_text( $value );
}

/**
 * Hodnota pole Nastavení článku.
 *
 * @param string           $key  Klíč bez prefixu.
 * @param int|WP_Post|null $post Článek (výchozí aktuální).
 * @return string|bool checkbox → bool, ostatní → string ('' = nevyplněno).
 */
function ekt_post_setting( string $key, $post = null ) {
	$post   = get_post( $post );
	$fields = ekt_post_setting_fields();
	$value  = $post ? get_post_meta( $post->ID, 'ekt_' . $key, true ) : '';
	if ( 'checkbox' === ( $fields[ $key ]['type'] ?? '' ) ) {
		return ! empty( $value );
	}
	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Pole seřazená podle priority pro daný typ obsahu.
 *
 * @param string $post_type Typ obsahu.
 * @return array<string,array>
 */
function ekt_post_settings_for( string $post_type ): array {
	$fields = array_filter( ekt_post_setting_fields(), static fn( $field ) => in_array( $post_type, $field['post_types'], true ) );
	uasort( $fields, static fn( $a, $b ) => (int) $a['priority'] <=> (int) $b['priority'] );
	return $fields;
}

/**
 * Registrace post meta (show_in_rest) pro všechna pole.
 */
function ekt_post_settings_register_meta(): void {
	foreach ( ekt_post_setting_fields() as $key => $field ) {
		foreach ( $field['post_types'] as $post_type ) {
			register_post_meta(
				$post_type,
				'ekt_' . $key,
				array(
					'type'              => 'checkbox' === $field['type'] ? 'boolean' : 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => static fn( $value ) => ekt_post_setting_sanitize( $field, $value ),
					'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id ),
				)
			);
		}
	}
}
add_action( 'init', 'ekt_post_settings_register_meta', 20 );

/**
 * Box v editoru pro typy obsahu, které mají aspoň jedno pole.
 *
 * @param string $post_type Typ obsahu.
 */
function ekt_post_settings_metabox( $post_type ): void {
	if ( is_string( $post_type ) && ekt_post_settings_for( $post_type ) ) {
		add_meta_box( 'ekt-post-settings', 'Nastavení článku', 'ekt_post_settings_render', $post_type, 'side' );
	}
}
add_action( 'add_meta_boxes', 'ekt_post_settings_metabox' );

/**
 * Výpis boxu Nastavení článku.
 *
 * @param WP_Post $post Článek.
 */
function ekt_post_settings_render( $post ): void {
	wp_nonce_field( 'ekt_post_settings', '_ekt_ps_nonce' );
	$first = true;
	foreach ( ekt_post_settings_for( $post->post_type ) as $key => $field ) {
		$id    = 'ekt-ps-' . $key;
		$name  = 'ekt_ps[' . $key . ']';
		$value = ekt_post_setting( $key, $post );
		$top   = $first ? '0' : '14px';
		$first = false;
		if ( 'checkbox' === $field['type'] ) {
			printf(
				'<p style="margin:%1$s 0 4px"><label><input type="checkbox" name="%2$s" id="%3$s" value="1"%4$s> <strong>%5$s</strong></label></p>',
				esc_attr( $top ),
				esc_attr( $name ),
				esc_attr( $id ),
				checked( (bool) $value, true, false ),
				esc_html( (string) $field['label'] )
			);
		} else {
			printf( '<p style="margin:%1$s 0 4px"><label for="%2$s"><strong>%3$s</strong></label></p>', esc_attr( $top ), esc_attr( $id ), esc_html( (string) $field['label'] ) );
			switch ( $field['type'] ) {
				case 'select':
					printf( '<select name="%1$s" id="%2$s" style="width:100%%">', esc_attr( $name ), esc_attr( $id ) );
					foreach ( (array) $field['choices'] as $option => $label ) {
						printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $option ), selected( (string) $value, (string) $option, false ), esc_html( (string) $label ) );
					}
					echo '</select>';
					break;
				case 'textarea':
					printf(
						'<textarea name="%1$s" id="%2$s" rows="%3$d" style="width:100%%" placeholder="%4$s">%5$s</textarea>',
						esc_attr( $name ),
						esc_attr( $id ),
						(int) $field['rows'],
						esc_attr( (string) $field['placeholder'] ),
						esc_textarea( (string) $value )
					);
					break;
				case 'date':
					printf(
						'<span style="display:flex;gap:6px;align-items:center"><input type="date" name="%1$s" id="%2$s" value="%3$s" style="flex:1"><button type="button" class="button" onclick="document.getElementById(%4$s).value=new Date().toLocaleDateString(\'sv-SE\')">Dnes</button></span>',
						esc_attr( $name ),
						esc_attr( $id ),
						esc_attr( (string) $value ),
						esc_attr( (string) wp_json_encode( $id ) )
					);
					break;
				default:
					printf(
						'<input type="text" name="%1$s" id="%2$s" value="%3$s" style="width:100%%" placeholder="%4$s">',
						esc_attr( $name ),
						esc_attr( $id ),
						esc_attr( (string) $value ),
						esc_attr( (string) $field['placeholder'] )
					);
			}
		}
		if ( '' !== (string) $field['help'] ) {
			echo '<p class="description" style="margin-top:2px">' . esc_html( (string) $field['help'] ) . '</p>';
		}
	}
}

/**
 * Uložení boxu Nastavení článku.
 *
 * @param int     $post_id ID.
 * @param WP_Post $post    Článek.
 */
function ekt_post_settings_save( $post_id, $post ): void {
	if ( ! isset( $_POST['_ekt_ps_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_ekt_ps_nonce'] ) ), 'ekt_post_settings' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( (int) $post_id ) || ! current_user_can( 'edit_post', (int) $post_id ) ) {
		return;
	}
	$input = isset( $_POST['ekt_ps'] ) && is_array( $_POST['ekt_ps'] ) ? wp_unslash( $_POST['ekt_ps'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- čistí ekt_post_setting_sanitize()
	foreach ( ekt_post_settings_for( $post->post_type ) as $key => $field ) {
		$value = ekt_post_setting_sanitize( $field, $input[ $key ] ?? '' );
		if ( false === $value || '' === $value ) {
			delete_post_meta( (int) $post_id, 'ekt_' . $key );
		} else {
			update_post_meta( (int) $post_id, 'ekt_' . $key, true === $value ? '1' : $value );
		}
	}
}
add_action( 'save_post', 'ekt_post_settings_save', 10, 2 );

/* =========================================================================
 * 4) Očištění textu
 * ====================================================================== */

/**
 * Prostý text bez sanitize_text_field (ten maže i text jako `<16>` v kódu). Neplatné UTF-8 pryč,
 * řídicí znaky → mezera, okraje oříznuté. Ukládá se prostý text, při výpisu se vždy escapuje.
 *
 * @param mixed $value     Hodnota.
 * @param bool  $multiline Zachovat řádky (\n).
 * @return string
 */
function ekt_sanitize_text( $value, bool $multiline = false ): string {
	$value = wp_check_invalid_utf8( is_scalar( $value ) ? (string) $value : '' );
	if ( ! $multiline ) {
		return trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value ) );
	}
	$lines = preg_split( '/\r\n|\r|\n/', $value );
	$lines = array_map( static fn( $line ) => rtrim( (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', (string) $line ) ), (array) $lines );
	return trim( implode( "\n", $lines ), "\n" );
}

/**
 * Datum Y-m-d, jinak ''.
 *
 * @param mixed $value Hodnota.
 * @return string
 */
function ekt_sanitize_date( $value ): string {
	$value = trim( is_scalar( $value ) ? (string) $value : '' );
	$date  = DateTime::createFromFormat( '!Y-m-d', $value );
	return ( $date && $date->format( 'Y-m-d' ) === $value ) ? $value : '';
}

/* =========================================================================
 * 5) Původní pluginy
 * Šablona vypíná svou náhradu, dokud běží původní plugin (žádný dvojí výstup při přechodu).
 * Šablona se načítá až po pluginech, takže konstanty a funkce pluginů jsou už známé.
 * ====================================================================== */

/**
 * Běží ještě původní plugin, který tuhle věc dělá?
 *
 * @param string $what lightbox|cookies|analytics|consent|ext_links|seo|typography|sitemap|toc|pwa|antispam|share
 * @return bool
 */
function ekt_legacy_active( string $what ): bool {
	switch ( $what ) {
		case 'lightbox':
			$active = defined( 'LIGHTPRESS_VERSION' )              // wp-jquery-lightbox
				|| class_exists( 'SLB_Lightbox' )                  // simple-lightbox
				|| class_exists( 'Responsive_Lightbox' );          // responsive-lightbox
			break;
		case 'cookies':
			$active = defined( 'CLI_VERSION' )                     // cookie-law-info (CookieYes)
				|| defined( 'CMPLZ_VERSION' )                      // complianz-gdpr
				|| class_exists( 'Cookie_Notice' );                // cookie-notice
			break;
		case 'analytics':
			$active = function_exists( 'MonsterInsights_Lite' )    // google-analytics-for-wordpress
				|| function_exists( 'MonsterInsights' )            // MonsterInsights Pro
				|| defined( 'MONSTERINSIGHTS_VERSION' )
				|| defined( 'GOOGLESITEKIT_VERSION' );             // google-site-kit
			break;
		case 'consent': // analytika a cookie lišta jsou jeden celek
			$active = ekt_legacy_active( 'analytics' ) || ekt_legacy_active( 'cookies' );
			break;
		case 'ext_links':
			$active = function_exists( 'external_links_in_new_windows_client' ) // open-external-links-in-a-new-window
				|| class_exists( 'WPEL_Plugin' );                  // wp-external-links
			break;
		case 'seo':
			$active = defined( 'WPSEO_VERSION' )                   // wordpress-seo (Yoast)
				|| defined( 'RANK_MATH_VERSION' )                  // seo-by-rank-math
				|| defined( 'SEOPRESS_VERSION' )                   // wp-seopress
				|| defined( 'AIOSEO_VERSION' )                     // all-in-one-seo-pack
				|| defined( 'THE_SEO_FRAMEWORK_VERSION' );         // autodescription
			break;
		case 'typography':
			$active = class_exists( 'Zalomeni' );                  // zalomeni
			break;
		case 'sitemap':
			$active = class_exists( 'GoogleSitemapGeneratorLoader' ); // google-sitemap-generator
			break;
		case 'toc':
			$active = class_exists( 'ezTOC' )                      // easy-table-of-contents
				|| defined( 'LUCKY_WP_TOC_VERSION' )               // luckywp-table-of-contents
				|| class_exists( 'toc' );                          // table-of-contents-plus
			break;
		case 'pwa':
			$active = defined( 'SUPERPWA_VERSION' )                // super-progressive-web-apps
				|| defined( 'PWA_VERSION' );                       // pwa
			break;
		case 'antispam':
			$active = class_exists( 'Antispam_Bee' );              // antispam-bee
			break;
		case 'share':
			$active = function_exists( 'A2A_SHARE_SAVE_init' )     // add-to-any
				|| class_exists( 'Shared_Counts' );                // shared-counts
			break;
		default:
			$active = false;
	}
	return (bool) apply_filters( 'ekt_legacy_active', $active, $what );
}

/* =========================================================================
 * 6) Ikony
 * Stroke sada (viewBox 24, currentColor). Filtr ekt_icons přidá nebo přepíše ikonu:
 * hodnota je vnitřek <svg> (cesty), nebo celé <svg…> (vlastní sada šablony, fill ikony).
 * ====================================================================== */

/**
 * Sada ikon po filtru ekt_icons (filtr registruj při načtení souboru, ne až při výpisu).
 *
 * @return array<string,string>
 */
function ekt_icons(): array {
	static $icons = null;
	if ( null !== $icons ) {
		return $icons;
	}
	$icons = (array) apply_filters(
		'ekt_icons',
		array(
			'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
			'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
			'monitor'  => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
			'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
			'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
			'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
			'chevron'  => '<path d="m6 9 6 6 6-6"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'arrow-l'  => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
			'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
			'copy'     => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
			'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
			'share'    => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
			'check'    => '<path d="M20 6 9 17l-5-5"/>',
			'refresh'  => '<path d="M20 11a8 8 0 0 0-14.6-4.5L4 8M4 4v4h4M4 13a8 8 0 0 0 14.6 4.5L20 16M20 20v-4h-4"/>',
			'print'    => '<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7"/>',
			'expand'   => '<path d="M4 9V5a1 1 0 0 1 1-1h4M15 4h4a1 1 0 0 1 1 1v4M20 15v4a1 1 0 0 1-1 1h-4M9 20H5a1 1 0 0 1-1-1v-4"/>',
			'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
			'rss'      => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>',
			'offline'  => '<path d="M2 2l20 20M8.5 16.5a5 5 0 0 1 7 0M5 13a10 10 0 0 1 5.2-2.8M19 13a10 10 0 0 0-2.4-1.7M2 9.5a15 15 0 0 1 4.4-2.9M22 9.5A15 15 0 0 0 11 5.1M12 20h.01"/>',
			'star'     => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/>',
			'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
			'folder'   => '<path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/>',
			'code'     => '<path d="m8 7-5 5 5 5M16 7l5 5-5 5"/>',
			'scale'    => '<path d="M12 3v18M7 21h10M5 7h14M5 7l-3 7a3.5 3.5 0 0 0 6 0zM19 7l-3 7a3.5 3.5 0 0 0 6 0z"/>',
			'sparkle'  => '<path d="M12 3.5l1.9 5.1 5.1 1.9-5.1 1.9L12 17.5l-1.9-5.1L5 10.5l5.1-1.9z"/><path d="M18.5 15.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/>',
			'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
			'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
			// Sítě.
			'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
			'github'    => '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/>',
			'linkedin'  => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
			'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
			'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/>',
			'youtube'   => '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="M10 9.5v5l4.5-2.5z" fill="currentColor"/>',
			'x'         => '<path d="M4 4h4.5L20 20h-4.5z"/><path d="M20 4l-6.6 7.4M4 20l6.6-7.4"/>',
			'mastodon'  => '<path d="M20.5 9.4c0-3.9-2.6-5-2.6-5C16.6 3.8 14.4 3.5 12 3.5s-4.6.3-5.9.9c0 0-2.6 1.1-2.6 5 0 3.6-.3 8 4.2 9.2 1.7.5 3.4.6 4.8.5 2.1-.1 3.3-.8 3.3-.8l-.1-1.5s-1.5.5-3.2.4c-1.7-.1-3.4-.2-3.7-2.2a4 4 0 0 1 0-.6s1.7.4 3.8.5c1.3.1 2.5-.1 3.7-.2 2.3-.3 4.3-1.7 4.6-3 .4-2.1.3-5.2.3-5.2z"/><path d="M8.5 12.5V9.2c0-1 .8-1.7 1.7-1.7s1.8.8 1.8 1.7v2M15.5 12.5V9.2c0-1-.8-1.7-1.7-1.7S12 8.3 12 9.2"/>',
		)
	);
	return $icons;
}

/**
 * Inline SVG ikona (aria-hidden; popisek dej prvku kolem).
 *
 * @param string $name  Název ikony.
 * @param int    $size  Velikost v px.
 * @param string $class Třídy navíc.
 * @return string HTML, neznámá ikona = ''.
 */
function ekt_icon( string $name, int $size = 20, string $class = '' ): string {
	$icons = ekt_icons();
	if ( ! isset( $icons[ $name ] ) || '' === $icons[ $name ] ) {
		return '';
	}
	$classes = trim( 'ekt-icon ekt-icon--' . sanitize_html_class( $name ) . ' ' . $class );
	$inner   = (string) $icons[ $name ];
	if ( str_starts_with( ltrim( $inner ), '<svg' ) ) {
		// Celé SVG ze sady šablony: doplnit třídu, velikost a aria-hidden.
		return (string) preg_replace( '/^\s*<svg\b/', '<svg class="' . esc_attr( $classes ) . '" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"', $inner, 1 );
	}
	return sprintf(
		'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $classes ),
		$size,
		$inner
	);
}

/* =========================================================================
 * 7) Helpery
 * ====================================================================== */

/**
 * Hlavní rubrika článku: primární rubrika Yoast/Rank Math (když je u článku), jinak první mimo
 * výchozí „Nezařazené“, jinak první.
 *
 * @param int|WP_Post|null $post Článek.
 * @return WP_Term|null
 */
function ekt_primary_category( $post = null ): ?WP_Term {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}
	$cats = get_the_category( $post->ID );
	if ( ! $cats ) {
		return null;
	}
	foreach ( array( '_yoast_wpseo_primary_category', 'rank_math_primary_category' ) as $meta ) {
		$primary = (int) get_post_meta( $post->ID, $meta, true );
		foreach ( $primary ? $cats : array() as $cat ) {
			if ( (int) $cat->term_id === $primary ) {
				return $cat;
			}
		}
	}
	foreach ( $cats as $cat ) {
		if ( (int) $cat->term_id !== (int) get_option( 'default_category' ) ) {
			return $cat;
		}
	}
	return $cats[0];
}

/**
 * Titulek jako prostý text (bez HTML a entit) – pro meta, JSON-LD, Markdown, sdílení.
 *
 * @param int|WP_Post|null $post Článek.
 * @return string
 */
function ekt_plain_title( $post = null ): string {
	$post = get_post( $post );
	return $post ? trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '';
}

/**
 * Český tvar podle počtu: 1 / 2–4 / 0 a 5+. Tvar může obsahovat %d (doplní se počet).
 *
 * @param int    $n    Počet.
 * @param string $one  Tvar pro 1.
 * @param string $few  Tvar pro 2–4.
 * @param string $many Tvar pro 0 a 5+.
 * @return string
 */
function ekt_plural( int $n, string $one, string $few, string $many ): string {
	$abs  = abs( $n );
	$form = 1 === $abs ? $one : ( ( $abs >= 2 && $abs <= 4 ) ? $few : $many );
	return str_contains( $form, '%d' ) ? sprintf( $form, $n ) : $form;
}

/**
 * Rok prvního publikovaného článku (cache v transientu ekt_first_year na den).
 *
 * @return int 0 = web nemá články.
 */
function ekt_first_year(): int {
	$year = get_transient( 'ekt_first_year' );
	if ( false !== $year ) {
		return (int) $year;
	}
	$first = get_posts(
		array(
			'numberposts'      => 1,
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'orderby'          => 'date',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	$year = $first ? (int) get_the_date( 'Y', (int) $first[0] ) : 0;
	set_transient( 'ekt_first_year', $year, DAY_IN_SECONDS );
	return $year;
}

/**
 * Rozsah let pro copyright („2016–2026“, nebo jen letošní rok).
 *
 * @param int $since Rok od (0 = Přizpůsobit → Patička → od roku, jinak rok prvního článku).
 * @return string
 */
function ekt_copyright_years( int $since = 0 ): string {
	if ( $since <= 0 ) {
		$since = (int) ekt_opt( 'copyright_since' );
	}
	if ( $since <= 0 ) {
		$since = ekt_first_year();
	}
	$now = (int) wp_date( 'Y' );
	return ( $since && $since < $now ) ? $since . '–' . $now : (string) $now;
}

/**
 * Části řádku patičky (HTML, escapované): copyright, kredit autora webu, Nastavení cookies
 * (modul analytics), Administrace. Šablona je spojí po svém, nebo použije ekt_site_info().
 *
 * @return array<string,string> klíč => HTML (prázdné části vynechané). Filtr ekt_site_info_parts.
 */
function ekt_site_info_parts(): array {
	$parts = array();
	$text  = trim( (string) ekt_opt( 'footer_text' ) );
	if ( '' !== $text ) {
		$parts['copyright'] = nl2br( esc_html( $text ) );
	} else {
		$name               = trim( (string) ekt_opt( 'copyright_name' ) );
		$parts['copyright'] = sprintf(
			'Copyright ©&nbsp;%1$s %2$s',
			esc_html( ekt_copyright_years() ),
			esc_html( '' !== $name ? $name : get_bloginfo( 'name' ) )
		);
	}
	$credit = trim( (string) ekt_opt( 'credit_name' ) );
	if ( '' !== $credit ) {
		$url             = trim( (string) ekt_opt( 'credit_url' ) );
		$parts['credit'] = 'Design &amp; code by ' . ( '' !== $url
			? sprintf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $url ), esc_html( $credit ) )
			: esc_html( $credit ) );
	}
	if ( function_exists( 'ekt_consent_link' ) ) {
		$parts['cookies'] = (string) ekt_consent_link();
	}
	if ( ekt_opt( 'admin_link' ) && ! is_user_logged_in() ) {
		$parts['admin'] = '<a href="' . esc_url( admin_url() ) . '" rel="nofollow">Administrace</a>';
	}
	return array_filter( (array) apply_filters( 'ekt_site_info_parts', $parts ), static fn( $part ) => '' !== (string) $part );
}

/**
 * Řádek patičky: <p class="ekt-site-info"><span>…</span> · <span>…</span></p>
 * (span s white-space:nowrap dá šablona). Override template-parts/ekt/site-info.php, filtr ekt_site_info_html.
 *
 * @param string $class Třída odstavce.
 * @return string HTML.
 */
function ekt_site_info( string $class = 'ekt-site-info' ): string {
	$parts = ekt_site_info_parts();
	return ekt_render(
		'site-info',
		static fn() => $parts ? '<p class="' . esc_attr( $class ) . '"><span>' . implode( '</span> · <span>', $parts ) . '</span></p>' : '',
		array(
			'parts' => $parts,
			'class' => $class,
		)
	);
}

/**
 * Preload písem (wp_head, priorita 1). Soubory dá šablona filtrem ekt_preload_fonts
 * jako cesty relativně ke složce šablony: array( 'assets/fonts/inter-latin-wght-normal.woff2' ).
 */
function ekt_preload_fonts(): void {
	foreach ( (array) apply_filters( 'ekt_preload_fonts', array() ) as $font ) {
		$font = ltrim( (string) $font, '/' );
		if ( '' !== $font && is_file( EKT_DIR . '/' . $font ) ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( EKT_URI . '/' . $font ) );
		}
	}
}
add_action( 'wp_head', 'ekt_preload_fonts', 1 );

/* =========================================================================
 * 8) Setup a úklid <head>
 * Projektové věci (menu, velikosti obrázků, custom-logo, content_width, editor.css, počet
 * článků) patří do inc/setup.php šablony (priorita 10, takže common jde přepsat).
 * ====================================================================== */

/**
 * Společná podpora šablony.
 */
function ekt_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
}
add_action( 'after_setup_theme', 'ekt_setup', 5 );

/**
 * Emoji skript a styly – moderní prohlížeče emoji zobrazí samy.
 */
function ekt_disable_emoji(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'ekt_disable_emoji' );

// Odkazy v hlavičce, které web nepotřebuje (Windows Live Writer, RSD, verze WP, krátký odkaz).
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );

// Styly bloků jen pro bloky, které jsou na stránce opravdu použité.
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * Styly, které návštěvník nepotřebuje (classic-theme-styles; dashicons bez lišty administrace).
 * Seznam jde změnit filtrem ekt_dequeue_styles.
 */
function ekt_dequeue_styles(): void {
	$styles = array( 'classic-theme-styles' );
	if ( ! is_admin_bar_showing() ) {
		$styles[] = 'dashicons';
	}
	foreach ( (array) apply_filters( 'ekt_dequeue_styles', $styles ) as $handle ) {
		wp_dequeue_style( (string) $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'ekt_dequeue_styles', 20 );

/* =========================================================================
 * 9) Upozornění na chybějící plugin SLUG-core
 * ====================================================================== */

/**
 * Běží doprovodný plugin (kanonický, nebo starší s vlastní značkou)?
 *
 * @return bool
 */
function ekt_core_active(): bool {
	return defined( 'EKC_VERSION' ) || defined( 'MND_CORE_VERSION' ) || defined( 'IV_CORE_VERSION' ) || function_exists( 'ek_client_ip' );
}

/**
 * Upozornění pro správce: plugin chybí, nebo je nainstalovaný a neaktivní.
 */
function ekt_core_notice(): void {
	if ( ekt_core_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$slug      = (string) apply_filters( 'ekt_core_plugin', EKT_SLUG . '-core' ); // složka pluginu
	$installed = is_file( WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php' );
	$name      = EKT_NAME . ' Core';
	if ( $installed ) {
		$text = 'plugin <em>' . esc_html( $name ) . '</em> je nainstalovaný, ale není aktivní.';
		$link = '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Aktivovat v Pluginech</a>';
	} else {
		$text = 'chybí plugin <em>' . esc_html( $name ) . '</em>.';
		$link = EKT_REPO ? '<a href="' . esc_url( 'https://github.com/' . EKT_REPO . '/releases/latest' ) . '" target="_blank" rel="noopener">Stáhnout ' . esc_html( $slug ) . '.zip</a>' : '';
	}
	printf(
		'<div class="notice notice-warning"><p><strong>Šablona %1$s:</strong> %2$s Zajišťuje ochranu přihlášení, zabezpečení, sitemap a údržbu webu. %3$s</p></div>',
		esc_html( EKT_NAME ),
		$text, // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno výše
		$link  // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno výše
	);
}
add_action( 'admin_notices', 'ekt_core_notice' );

/* =========================================================================
 * 10) Přepnutí šablony (after_switch_theme, priorita 20 = po automatickém mapování WordPressu)
 * Převezme menu a logo ze staré šablony, zruší duplicitní menu, uklidí výchozí widgety,
 * doplní menu Polylangu po jazycích a převede staré theme_mods podle EKT_LEGACY.
 * ====================================================================== */

/**
 * Po aktivaci šablony.
 *
 * @param string        $old_name  Název předchozí šablony.
 * @param WP_Theme|null $old_theme Předchozí šablona.
 */
function ekt_after_switch_theme( $old_name, $old_theme = null ): void {
	$old_slug = $old_theme instanceof WP_Theme ? $old_theme->get_stylesheet() : sanitize_title( (string) $old_name );
	if ( '' === $old_slug || get_stylesheet() === $old_slug ) {
		return;
	}
	$old = get_option( 'theme_mods_' . $old_slug, array() );
	$old = is_array( $old ) ? $old : array();

	ekt_switch_menus( $old, $old_slug );
	ekt_switch_logo( $old );
	ekt_switch_widgets();
	ekt_switch_polylang_menus( $old_slug );

	// Staré theme_mods (ek_*, iv_*, mnd_*) ze šablony s jiným slugem → ekt_* (jen chybějící).
	if ( ekt_legacy_prefixes() && $old ) {
		$mods = get_theme_mods();
		$mods = is_array( $mods ) ? $mods : array();
		list( $new ) = ekt_convert_legacy_mods( $old );
		foreach ( $new as $key => $value ) {
			if ( ! array_key_exists( $key, $mods ) ) {
				set_theme_mod( $key, $value );
			}
		}
	}
	do_action( 'ekt_after_switch_theme', $old_slug, $old );
}
add_action( 'after_switch_theme', 'ekt_after_switch_theme', 20, 2 );

/**
 * Menu: umístění podle mapy (filtr ekt_menu_locations_map: staré umístění => nové), stejné názvy,
 * jinak první obsazené umístění staré šablony do primary. Stejné menu jako v primary, které
 * stará šablona v daném umístění neměla, je duplicita po automatickém přiřazení WordPressu.
 *
 * @param array  $old      theme_mods staré šablony.
 * @param string $old_slug Slug staré šablony.
 */
function ekt_switch_menus( array $old, string $old_slug ): void {
	$registered = get_registered_nav_menus();
	$loc        = (array) get_theme_mod( 'nav_menu_locations', array() );
	$old_loc    = array_filter( array_map( 'intval', (array) ( $old['nav_menu_locations'] ?? array() ) ) );
	$map        = (array) apply_filters( 'ekt_menu_locations_map', array(), $old_slug, $old_loc );

	foreach ( $old_loc as $from => $menu_id ) {
		$to = $map[ $from ] ?? ( isset( $registered[ $from ] ) ? $from : '' );
		if ( '' !== $to && isset( $registered[ $to ] ) && empty( $loc[ $to ] ) && is_nav_menu( $menu_id ) ) {
			$loc[ $to ] = $menu_id;
		}
	}
	if ( isset( $registered['primary'] ) && empty( $loc['primary'] ) && $old_loc ) {
		foreach ( $old_loc as $menu_id ) {
			if ( is_nav_menu( $menu_id ) ) {
				$loc['primary'] = $menu_id;
				break;
			}
		}
	}
	if ( ! empty( $loc['primary'] ) ) {
		foreach ( $loc as $location => $menu_id ) {
			if ( 'primary' !== $location && (int) $menu_id === (int) $loc['primary'] && (int) ( $old_loc[ $location ] ?? 0 ) !== (int) $menu_id ) {
				unset( $loc[ $location ] );
			}
		}
	}
	set_theme_mod( 'nav_menu_locations', array_map( 'intval', array_filter( $loc ) ) );
}

/**
 * Logo: ID, nebo URL (starší šablony: logo_image, logo) → ID přílohy.
 *
 * @param array $old theme_mods staré šablony.
 */
function ekt_switch_logo( array $old ): void {
	if ( get_theme_mod( 'custom_logo' ) ) {
		return;
	}
	$logo = $old['custom_logo'] ?? ( $old['logo_image'] ?? ( $old['logo'] ?? 0 ) );
	$id   = is_numeric( $logo ) ? (int) $logo : attachment_url_to_postid( (string) $logo );
	if ( $id && wp_attachment_is_image( $id ) ) {
		set_theme_mod( 'custom_logo', $id );
	}
}

/**
 * Výchozí widgety (Hledat, Archivy, Rubriky…), které WordPress při přepnutí přesune do
 * oblastí nové šablony, vrátí mezi neaktivní. Seznam: filtr ekt_default_widgets ([] = vypnuto).
 */
function ekt_switch_widgets(): void {
	$defaults = (array) apply_filters( 'ekt_default_widgets', array( 'search', 'recent-posts', 'recent-comments', 'archives', 'categories', 'meta', 'calendar', 'pages', 'tag_cloud', 'rss' ) );
	if ( ! $defaults ) {
		return;
	}
	$sidebars = wp_get_sidebars_widgets();
	$changed  = false;
	foreach ( $sidebars as $sidebar => $widgets ) {
		if ( 'wp_inactive_widgets' === $sidebar || 'array_version' === $sidebar || ! is_array( $widgets ) ) {
			continue;
		}
		$keep = array();
		foreach ( $widgets as $widget_id ) {
			if ( in_array( preg_replace( '/-\d+$/', '', (string) $widget_id ), $defaults, true ) ) {
				$sidebars['wp_inactive_widgets'][] = $widget_id;
				$changed                           = true;
			} else {
				$keep[] = $widget_id;
			}
		}
		$sidebars[ $sidebar ] = $keep;
	}
	if ( $changed ) {
		wp_set_sidebars_widgets( $sidebars );
	}
}

/**
 * Polylang ukládá menu po jazycích zvlášť pro každou šablonu: doplnit chybějící ze staré.
 *
 * @param string $old_slug Slug staré šablony.
 */
function ekt_switch_polylang_menus( string $old_slug ): void {
	if ( ! function_exists( 'PLL' ) ) {
		return;
	}
	$new_slug = get_stylesheet();
	$options  = PLL()->options;
	$menus    = isset( $options['nav_menus'] ) ? (array) $options['nav_menus'] : array();
	if ( empty( $menus[ $old_slug ] ) || ! is_array( $menus[ $old_slug ] ) ) {
		return;
	}
	$map     = (array) apply_filters( 'ekt_menu_locations_map', array(), $old_slug, array() );
	$new     = isset( $menus[ $new_slug ] ) && is_array( $menus[ $new_slug ] ) ? $menus[ $new_slug ] : array();
	$changed = false;
	foreach ( $menus[ $old_slug ] as $location => $languages ) {
		$to = (string) ( $map[ $location ] ?? $location );
		foreach ( (array) $languages as $lang => $menu_id ) {
			if ( $menu_id && empty( $new[ $to ][ $lang ] ) ) {
				$new[ $to ][ $lang ] = (int) $menu_id;
				$changed             = true;
			}
		}
	}
	if ( ! $changed ) {
		return;
	}
	$menus[ $new_slug ] = $new;
	if ( is_object( $options ) && method_exists( $options, 'set' ) ) {
		$options->set( 'nav_menus', $menus ); // Polylang 3.7+
	} else {
		$raw              = (array) get_option( 'polylang', array() );
		$raw['nav_menus'] = $menus;
		update_option( 'polylang', $raw );
	}
}

/* =========================================================================
 * 11) Migrace dat
 * Běží na admin_init a ve WP-CLI (na frontendu ne). Kroky common jsou číslované (option
 * ekt_schema), kroky modulů mají ID 'modul/krok' (filtr ekt_migrations, hotové v option
 * ekt_migrations_done). Každý krok je idempotentní, vrací true = hotovo (false = zkusit příště)
 * a staré klíče maže až po ověřeném zápisu nových. Obsah článků se nepřepisuje nikdy.
 * ====================================================================== */

/**
 * Staré prefixy dat (z EKT_LEGACY).
 *
 * @return string[]
 */
function ekt_legacy_prefixes(): array {
	$legacy = defined( 'EKT_LEGACY' ) ? (array) EKT_LEGACY : array();
	return array_values( array_filter( array_map( 'sanitize_key', $legacy ) ) );
}

/**
 * Kroky migrace v common: číslo schématu => callable.
 *
 * @return array<int,callable>
 */
function ekt_migration_steps(): array {
	return array(
		1 => 'ekt_migrate_theme_mods',
	);
}

/**
 * Spustí čekající migrace (zámek transientem ekt_migrate_lock).
 */
function ekt_migrate(): void {
	$schema  = (int) get_option( 'ekt_schema', 0 );
	$modules = (array) apply_filters( 'ekt_migrations', array() );
	$done    = get_option( 'ekt_migrations_done', array() );
	$done    = is_array( $done ) ? $done : array();
	$pending = array_diff( array_keys( $modules ), $done );
	if ( $schema >= EKT_SCHEMA && ! $pending ) {
		return;
	}
	if ( get_transient( 'ekt_migrate_lock' ) ) {
		return;
	}
	set_transient( 'ekt_migrate_lock', time(), 5 * MINUTE_IN_SECONDS );
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	foreach ( ekt_migration_steps() as $version => $step ) {
		if ( $version <= $schema ) {
			continue;
		}
		if ( ! ekt_migration_run( $step, 'common ' . $version ) ) {
			break; // další kroky čekají na tento
		}
		$schema = $version;
		update_option( 'ekt_schema', $schema, false );
	}

	foreach ( $pending as $id ) {
		if ( ekt_migration_run( $modules[ $id ], (string) $id ) ) {
			$done[] = (string) $id;
			update_option( 'ekt_migrations_done', array_values( array_unique( $done ) ), false );
		}
	}
	delete_transient( 'ekt_migrate_lock' );
}
add_action( 'admin_init', 'ekt_migrate' );
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	add_action( 'init', 'ekt_migrate', 99 );
}

/**
 * Jeden krok migrace s ošetřením chyb.
 *
 * @param mixed  $step  Callable.
 * @param string $label Označení do logu.
 * @return bool Hotovo.
 */
function ekt_migration_run( $step, string $label ): bool {
	if ( ! is_callable( $step ) ) {
		return true; // neplatný krok neblokuje ostatní
	}
	try {
		return false !== call_user_func( $step );
	} catch ( Throwable $e ) {
		error_log( 'EKT migrace ' . $label . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return false;
	}
}

/**
 * Přejmenuje klíč post meta nebo term meta (krok migrace modulu). Idempotentní: kde nová
 * meta už je, stará se jen smaže; zbytek se přejmenuje jedním UPDATE (zápis nového klíče
 * a zánik starého jsou tentýž řádek). Cache meta dotčených objektů se vyčistí.
 *
 * @param string $meta_type post|term.
 * @param string $old_key   Starý klíč (ek_updated).
 * @param string $new_key   Nový klíč (ekt_updated).
 * @return bool Hotovo (false = starý klíč po přejmenování pořád existuje, zkusit příště).
 */
function ekt_migrate_meta_key( string $meta_type, string $old_key, string $new_key ): bool {
	global $wpdb;
	$table = 'term' === $meta_type ? $wpdb->termmeta : $wpdb->postmeta;
	$id    = 'term' === $meta_type ? 'term_id' : 'post_id';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabulka a sloupec z $wpdb
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT {$id} FROM {$table} WHERE meta_key = %s", $old_key ) );
	if ( ! $ids ) {
		return true;
	}
	$wpdb->query( $wpdb->prepare( "DELETE o FROM {$table} o INNER JOIN {$table} n ON n.{$id} = o.{$id} AND n.meta_key = %s WHERE o.meta_key = %s", $new_key, $old_key ) );
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET meta_key = %s WHERE meta_key = %s", $new_key, $old_key ) );
	$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE meta_key = %s", $old_key ) );
	// phpcs:enable
	foreach ( $ids as $object_id ) {
		wp_cache_delete( (int) $object_id, $meta_type . '_meta' );
	}
	return 0 === $left;
}

/**
 * Mapa starých theme_mods na nové pro jeden prefix: starý klíč => nový klíč (ekt_…).
 * Nemapované klíče zůstávají. Doplnit jde filtrem ekt_legacy_mod_map( $map, $prefix ).
 *
 * @param string $p Starý prefix (ek, iv, mnd).
 * @return array<string,string>
 */
function ekt_legacy_mod_map( string $p ): array {
	$map = array(
		// Analytika (GA ID má na starých webech tři různé klíče).
		$p . '_ga_id'          => 'ekt_ga_id',
		$p . '_ga4_id'         => 'ekt_ga_id',
		// Patička.
		$p . '_footer_text'    => 'ekt_footer_text',
		$p . '_copyright_name' => 'ekt_copyright_name',
		$p . '_credit_name'    => 'ekt_credit_name',
		$p . '_credit_url'     => 'ekt_credit_url',
		$p . '_since'          => 'ekt_copyright_since',
		// SEO a články (klíče modulů seo, share, code, toc, term-image).
		$p . '_meta_desc'      => 'ekt_meta_desc',
		$p . '_share'          => 'ekt_share',
		$p . '_code_lines'     => 'ekt_code_lines',
		$p . '_sidebar_toc'    => 'ekt_toc',
		$p . '_cat_image_mode' => 'ekt_term_image_mode',
	);
	// Sítě: {p}_social_<síť> i kratší {p}_<síť>.
	foreach ( array( 'email', 'github', 'linkedin', 'facebook', 'instagram', 'youtube', 'x', 'mastodon', 'rss' ) as $net ) {
		$map[ $p . '_social_' . $net ] = 'ekt_social_' . $net;
		if ( 'x' !== $net && 'rss' !== $net && 'email' !== $net ) {
			$map[ $p . '_' . $net ] = 'ekt_social_' . $net;
		}
	}
	return (array) apply_filters( 'ekt_legacy_mod_map', $map, $p );
}

/**
 * Převede staré theme_mods podle EKT_LEGACY.
 *
 * @param array $mods theme_mods (starý → nový se bere jen z tohoto pole).
 * @return array{0:array<string,mixed>,1:string[]} [nové klíče => hodnota, převedené staré klíče]
 */
function ekt_convert_legacy_mods( array $mods ): array {
	$new = array();
	$old = array();
	foreach ( ekt_legacy_prefixes() as $p ) {
		foreach ( ekt_legacy_mod_map( $p ) as $from => $to ) {
			if ( ! array_key_exists( $from, $mods ) ) {
				continue;
			}
			$value = $mods[ $from ];
			if ( is_bool( $value ) ) {
				$value = (int) $value; // checkbox: false by bylo „nenastaveno“
			}
			if ( ! array_key_exists( $to, $new ) ) {
				$new[ $to ] = $value;
			}
			$old[] = $from;
		}
	}
	return array( $new, $old );
}

/**
 * Schéma 1: theme_mods aktivní šablony {p}_* → ekt_* (jen chybějící), pak smazat převedené
 * staré klíče a staré cache vydání ({p}_theme_release).
 *
 * @return bool
 */
function ekt_migrate_theme_mods(): bool {
	$prefixes = ekt_legacy_prefixes();
	if ( ! $prefixes ) {
		return true;
	}
	$option = 'theme_mods_' . get_option( 'stylesheet' );
	$mods   = get_option( $option, array() );
	$mods   = is_array( $mods ) ? $mods : array();
	list( $new, $old ) = ekt_convert_legacy_mods( $mods );

	$changed = false;
	foreach ( $new as $key => $value ) {
		if ( ! array_key_exists( $key, $mods ) ) {
			$mods[ $key ] = $value;
			$changed      = true;
		}
	}
	if ( $changed ) {
		update_option( $option, $mods );
		$check = get_option( $option, array() );
		foreach ( $new as $key => $value ) {
			if ( ! is_array( $check ) || ! array_key_exists( $key, $check ) ) {
				return false; // zápis se nepovedl, staré klíče zůstávají
			}
		}
		$mods = $check;
	}
	if ( $old ) {
		foreach ( array_unique( $old ) as $key ) {
			unset( $mods[ $key ] );
		}
		update_option( $option, $mods );
	}
	foreach ( $prefixes as $p ) {
		delete_site_transient( $p . '_theme_release' );
	}
	return true;
}
