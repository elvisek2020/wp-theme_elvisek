<?php
/**
 * Nastavení šablony v Přizpůsobení (Vzhled → Přizpůsobit → ElvisEK).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Výchozí hodnoty nastavení.
 */
function ek_defaults(): array {
	return array(
		'ek_motto'       => get_bloginfo( 'description' ),
		'ek_hero_image'  => '',
		'ek_hero_image_light' => '',
		'ek_about'       => 'Memo blog – poznámky co, jak, kde a proč ze světa jedniček a nul.',
		'ek_since'       => '2016',
		'ek_meta_desc'   => 'ElvisEK – Linux, Apple, macOS, Synology, NAS, Raspberry, LibreELEC – instalace, konfigurace, debugging. Loxone, Zigbee, weby.',
		'ek_ga_id'       => '',
		'ek_topics_count'=> 8,
		'ek_show_masthead' => true,
		'ek_sidebar_mode'=> 'wide',
		'ek_sidebar_toc' => true,
		'ek_sidebar_recent' => true,
		'ek_code_lines'  => true,
		'ek_postnav'     => false,
		'ek_cat_image_mode' => 'always',
	);
}

function ek_opt( string $key ) {
	$defaults = ek_defaults();
	return get_theme_mod( $key, $defaults[ $key ] ?? '' );
}

add_action( 'customize_register', function ( WP_Customize_Manager $wpc ) {
	$d = ek_defaults();

	// Sekce jako samostatné položky menu v Přizpůsobit.
	$sections = array(
		'ek_home'    => array( 'Úvodní stránka', 24 ),
		'ek_article' => array( 'Články', 25 ),
		'ek_footer'  => array( 'Patička', 26 ),
		'ek_seo'     => array( 'SEO a analytika', 27 ),
	);
	foreach ( $sections as $id => [ $title, $prio ] ) {
		$wpc->add_section( $id, array( 'title' => $title, 'priority' => $prio ) );
	}

	$add = function ( string $id, string $section, string $label, string $type, $sanitize, array $extra = array() ) use ( $wpc, $d ) {
		$wpc->add_setting( $id, array( 'default' => $d[ $id ] ?? '', 'sanitize_callback' => $sanitize ) );
		$wpc->add_control( $id, array_merge( array( 'label' => $label, 'section' => $section, 'type' => $type ), $extra ) );
	};

	// Úvodní stránka
	$add( 'ek_show_masthead', 'ek_home', 'Zobrazit nadpis webu s mottem', 'checkbox', 'rest_sanitize_boolean' );
	$add( 'ek_motto', 'ek_home', 'Motto pod nadpisem', 'textarea', 'sanitize_textarea_field' );
	$wpc->add_setting( 'ek_hero_image', array( 'default' => '', 'sanitize_callback' => 'absint' ) );
	$wpc->add_control( new WP_Customize_Media_Control( $wpc, 'ek_hero_image', array(
		'label'       => 'Obrázek na pozadí nadpisu (volitelné)',
		'description' => 'Tmavý obrázek, bílý text. Použije se v tmavém režimu a ve světlém, pokud níže není světlá varianta. Ideálně 2400 × 1000 px, motiv ve středovém pruhu.',
		'section'     => 'ek_home',
		'mime_type'   => 'image',
	) ) );
	$wpc->add_setting( 'ek_hero_image_light', array( 'default' => '', 'sanitize_callback' => 'absint' ) );
	$wpc->add_control( new WP_Customize_Media_Control( $wpc, 'ek_hero_image_light', array(
		'label'       => 'Světlá varianta obrázku (volitelné)',
		'description' => 'Světlý obrázek pro světlý režim – text bude tmavý. Stejný rozměr a kompozice jako tmavý.',
		'section'     => 'ek_home',
		'mime_type'   => 'image',
	) ) );
	// Výběr a pořadí témat v hlavičce (zaškrtnout + přetáhnout). Prázdné = automaticky podle počtu článků.
	$wpc->add_setting( 'ek_topics', array(
		'default'           => '',
		'sanitize_callback' => fn( $v ) => implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $v ) ) ) ),
	) );
	$wpc->add_control( new EK_Topics_Control( $wpc, 'ek_topics', array(
		'label'       => 'Témata v hlavičce',
		'description' => 'Zaškrtni témata a přetáhni je do požadovaného pořadí. Když nic nezaškrtneš, zobrazí se automaticky nejpočetnější (podle „Počtu témat“ níže). Prázdná témata se nezobrazují.',
		'section'     => 'ek_home',
	) ) );
	$add( 'ek_topics_count', 'ek_home', 'Počet témat', 'number', 'absint', array( 'input_attrs' => array( 'min' => 0, 'max' => 12 ) ) );

	// Články
	$add( 'ek_sidebar_mode', 'ek_article', 'Boční panel', 'select',
		fn( $v ) => in_array( $v, array( 'wide', 'always', 'never' ), true ) ? $v : 'wide',
		array( 'choices' => array(
			'wide'   => 'Jen na širokých obrazovkách (od 1400 px)',
			'always' => 'Vždy (od 1080 px)',
			'never'  => 'Nikdy — jen článek',
		) )
	);
	$add( 'ek_sidebar_toc', 'ek_article', 'Boční panel: obsah článku', 'checkbox', 'rest_sanitize_boolean' );
	$add( 'ek_sidebar_recent', 'ek_article', 'Boční panel: novinky', 'checkbox', 'rest_sanitize_boolean' );
	$add( 'ek_code_lines', 'ek_article', 'Čísla řádků u kódu', 'checkbox', 'rest_sanitize_boolean' );
	$add( 'ek_postnav', 'ek_article', 'Pod článkem: odkazy Starší / Novější', 'checkbox', 'rest_sanitize_boolean' );
	$add( 'ek_cat_image_mode', 'ek_article', 'Obrázek rubriky (Příspěvky → Rubriky)', 'select',
		fn( $v ) => in_array( $v, array( 'always', 'fallback', 'off' ), true ) ? $v : 'always',
		array( 'choices' => array(
			'always'   => 'Vždy – místo náhledového obrázku článku',
			'fallback' => 'Jen u článků bez náhledového obrázku',
			'off'      => 'Nepoužívat',
		) )
	);

	// Patička
	$add( 'ek_about', 'ek_footer', 'Krátký text o webu', 'textarea', 'sanitize_textarea_field' );
	$add( 'ek_since', 'ek_footer', 'Rok založení (copyright)', 'number', 'absint' );

	// SEO a analytika
	$add( 'ek_meta_desc', 'ek_seo', 'Meta description titulky', 'textarea', 'sanitize_textarea_field' );
	$add( 'ek_ga_id', 'ek_seo', 'Google Analytics 4 ID (G-…)', 'text', 'ek_sanitize_ga_id',
		array( 'description' => 'Prázdné = žádná analytika ani cookie lišta. Přihlášeným se GA nenačítá.' ) );
} );

function ek_sanitize_ga_id( $value ): string {
	$value = strtoupper( trim( (string) $value ) );
	return preg_match( '/^G-[A-Z0-9]{4,}$/', $value ) ? $value : '';
}

/**
 * Ovládací prvek: seznam rubrik se zaškrtávátky a řazením přetažením (jQuery UI sortable je v Přizpůsobit k dispozici).
 */
add_action( 'customize_register', function () {
	if ( class_exists( 'EK_Topics_Control' ) ) {
		return;
	}
	class EK_Topics_Control extends WP_Customize_Control {
		public $type = 'ek_topics';

		public function enqueue() {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}

		public function render_content() {
			$selected = array_filter( array_map( 'absint', explode( ',', (string) $this->value() ) ) );
			$cats     = get_categories( array( 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC' ) );
			$by_id    = array();
			foreach ( $cats as $c ) {
				$by_id[ $c->term_id ] = $c;
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
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<ul class="ek-topics-sort" style="margin:8px 0 0">
				<?php foreach ( $ordered as $c ) : ?>
					<li data-id="<?php echo (int) $c->term_id; ?>" style="display:flex;align-items:center;gap:8px;padding:6px 8px;margin:0 0 4px;background:#fff;border:1px solid #dcdcde;border-radius:4px;cursor:move">
						<span class="dashicons dashicons-menu" style="color:#a7aaad"></span>
						<label style="flex:1;cursor:pointer"><input type="checkbox" <?php checked( in_array( (int) $c->term_id, $selected, true ) ); ?>> <?php echo esc_html( $c->name ); ?></label>
						<small style="color:#646970"><?php echo (int) $c->count; ?></small>
					</li>
				<?php endforeach; ?>
			</ul>
			<input type="hidden" class="ek-topics-value" <?php $this->link(); ?> value="<?php echo esc_attr( implode( ',', $selected ) ); ?>">
			<script>
			( function ( $ ) {
				var $wrap = $( '#customize-control-<?php echo esc_js( $this->id ); ?>' );
				var $list = $wrap.find( '.ek-topics-sort' );
				function save() {
					var ids = $list.children( 'li' ).filter( function () { return $( this ).find( 'input' ).prop( 'checked' ); } ).map( function () { return $( this ).data( 'id' ); } ).get();
					$wrap.find( '.ek-topics-value' ).val( ids.join( ',' ) ).trigger( 'change' );
				}
				$list.sortable( { axis: 'y', update: save } );
				$list.on( 'change', 'input', save );
			} )( jQuery );
			</script>
			<?php
		}
	}
}, 1 );
