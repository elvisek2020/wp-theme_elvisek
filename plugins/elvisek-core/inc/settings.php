<?php
/**
 * EKC module: settings
 * Version: 1.1.0
 * Requires: common
 * Replaces: –
 * Summary: Stránka Nastavení → NAZEV Core vykreslená z registru nastavení (ekc_settings_register).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pořadí známých sekcí (ostatní za nimi v pořadí registrace).
 *
 * @return string[]
 */
function ekc_settings_section_order(): array {
	return array( 'Přihlášení', 'Zabezpečení', 'SEO', 'Obsah', 'Média', 'Komentáře', 'Aktualizace', 'Administrace', 'Obecné' );
}

/**
 * Registrovaná pole seskupená do sekcí.
 *
 * @return array<string,array<string,array>> sekce => klíč => pole
 */
function ekc_settings_grouped(): array {
	$groups = array();
	foreach ( ekc_settings_fields() as $key => $field ) {
		$groups[ (string) $field['section'] ][ $key ] = $field;
	}
	$order = array_flip( ekc_settings_section_order() );
	$names = array_keys( $groups );
	usort(
		$names,
		static function ( $a, $b ) use ( $order, $names ) {
			$ia = $order[ $a ] ?? 100 + array_search( $a, $names, true );
			$ib = $order[ $b ] ?? 100 + array_search( $b, $names, true );
			return $ia <=> $ib;
		}
	);
	$sorted = array();
	foreach ( $names as $name ) {
		$sorted[ $name ] = $groups[ $name ];
	}
	return $sorted;
}

/**
 * Očištění formuláře: registrovaná pole podle typu, neregistrované klíče zůstanou
 * (modul může být dočasně odebraný). Nezaškrtnutý checkbox = 0.
 *
 * @param mixed $input Odeslané hodnoty.
 * @return array
 */
function ekc_settings_sanitize( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$saved = get_option( 'ekc_settings', array() );
	$clean = array_merge( is_array( $saved ) ? $saved : array(), $input );
	// Formulář posílá jen zaškrtnuté checkboxy; programové update_option() (ekc_opt_set) posílá celé pole.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ověřil options.php
	$is_form = isset( $_POST['option_page'] ) && 'ekc_settings_group' === $_POST['option_page'];
	foreach ( ekc_settings_fields() as $key => $field ) {
		if ( array_key_exists( $key, $input ) || $is_form ) {
			$clean[ $key ] = ekc_settings_sanitize_value( $field, $input[ $key ] ?? null );
		}
	}
	return $clean;
}

/**
 * Registrace option (autoload) a sanitizace.
 */
function ekc_settings_init(): void {
	register_setting(
		'ekc_settings_group',
		'ekc_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ekc_settings_sanitize',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'admin_init', 'ekc_settings_init' );

/**
 * Stránka Nastavení → NAZEV Core.
 */
function ekc_settings_menu(): void {
	$parents = ekc_page_parents();
	add_submenu_page( $parents['ekc-nastaveni'] ?? 'options-general.php', EKC_NAME . ' Core', EKC_NAME . ' Core', 'manage_options', 'ekc-nastaveni', 'ekc_settings_page' );
}
add_action( 'admin_menu', 'ekc_settings_menu' );

/**
 * Odkaz Nastavení v řádku pluginu.
 *
 * @param array $links Odkazy.
 * @return array
 */
function ekc_settings_action_link( array $links ): array {
	array_unshift( $links, '<a href="' . esc_url( ekc_page_url( 'ekc-nastaveni' ) ) . '">Nastavení</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . EKC_BASENAME, 'ekc_settings_action_link' );

/**
 * Jedno pole formuláře.
 *
 * @param string $key   Klíč.
 * @param array  $field Pole z registru.
 */
function ekc_settings_field( string $key, array $field ): void {
	$name  = 'ekc_settings[' . $key . ']';
	$id    = 'ekc-' . str_replace( '_', '-', $key );
	$value = ekc_opt( $key );

	switch ( $field['type'] ) {
		case 'bool':
			printf(
				'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> Zapnuto</label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( (bool) $value, true, false )
			);
			break;
		case 'int':
			printf(
				'<input type="number" id="%1$s" name="%2$s" value="%3$d" step="1" class="small-text"%4$s%5$s> %6$s',
				esc_attr( $id ),
				esc_attr( $name ),
				(int) $value,
				null !== $field['min'] ? ' min="' . (int) $field['min'] . '"' : '',
				null !== $field['max'] ? ' max="' . (int) $field['max'] . '"' : '',
				esc_html( (string) $field['unit'] )
			);
			break;
		case 'select':
			printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( (array) $field['options'] as $option => $label ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( (string) $option ), selected( (string) $value, (string) $option, false ), esc_html( (string) $label ) );
			}
			echo '</select>';
			break;
		case 'text':
			printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
			break;
		case 'textarea':
			printf( '<textarea id="%1$s" name="%2$s" rows="5" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
			break;
		case 'list':
			printf( '<textarea id="%1$s" name="%2$s" rows="5" class="large-text code">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( implode( "\n", (array) $value ) ) );
			echo '<p class="description">Jedna položka na řádek.</p>';
			break;
	}
	if ( '' !== (string) $field['help'] ) {
		echo '<p class="description">' . esc_html( (string) $field['help'] ) . '</p>';
	}
}

/**
 * Obsah stránky.
 */
function ekc_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$groups = ekc_settings_grouped();
	echo '<div class="wrap"><h1>' . esc_html( EKC_NAME . ' Core' ) . '</h1>';
	echo '<p>Funkce webu nezávislé na šabloně. Výchozí nastavení je doporučené a stejné na všech webech. ';
	echo '<a href="' . esc_url( ekc_page_url( 'ekc-udrzba' ) ) . '">Údržba webu</a>';
	if ( function_exists( 'ekc_login_log' ) ) {
		echo ' · <a href="' . esc_url( ekc_page_url( 'ekc-login-log' ) ) . '">Log přihlášení</a>';
	}
	echo '</p>';
	if ( 'local' === wp_get_environment_type() ) {
		echo '<div class="notice notice-info inline"><p>Lokální vývoj: automatické aktualizace a kontrola nové verze pluginu jsou vypnuté.</p></div>';
	}
	if ( ! $groups ) {
		echo '<p>Žádný modul nemá nastavení.</p></div>';
		return;
	}
	$slugs = array();
	foreach ( array_keys( $groups ) as $section ) {
		$slugs[ $section ] = sanitize_title( remove_accents( (string) $section ) );
	}
	echo '<nav class="nav-tab-wrapper ekc-tabs">';
	foreach ( $slugs as $section => $slug ) {
		printf( '<a href="#ekc-sec-%1$s" class="nav-tab" data-tab="%1$s">%2$s</a>', esc_attr( $slug ), esc_html( (string) $section ) );
	}
	echo '</nav>';

	echo '<form method="post" action="options.php">';
	settings_fields( 'ekc_settings_group' );
	foreach ( $groups as $section => $fields ) {
		$slug = $slugs[ $section ];
		printf( '<div class="card ekc-sec" id="ekc-sec-%1$s"><h2 class="title">%2$s</h2>', esc_attr( $slug ), esc_html( (string) $section ) );
		$subgroups = array();
		foreach ( $fields as $key => $field ) {
			$subgroups[ (string) $field['group'] ][ $key ] = $field;
		}
		foreach ( $subgroups as $group => $items ) {
			if ( '' !== $group ) {
				echo '<h3 class="ekc-group">' . esc_html( $group ) . '</h3>';
			}
			echo '<table class="form-table" role="presentation">';
			foreach ( $items as $key => $field ) {
				$parent = (string) $field['parent'];
				printf(
					'<tr%1$s><th scope="row"><label for="%2$s">%3$s</label></th><td>',
					'' !== $parent ? ' class="ekc-child" data-parent="' . esc_attr( 'ekc-' . str_replace( '_', '-', $parent ) ) . '"' : '',
					esc_attr( 'ekc-' . str_replace( '_', '-', $key ) ),
					esc_html( (string) $field['label'] )
				);
				ekc_settings_field( $key, $field );
				echo '</td></tr>';
			}
			echo '</table>';
		}
		submit_button( 'Uložit změny', 'primary', 'submit-' . $slug );
		echo '</div>';
	}
	echo '</form></div>';
	?>
	<style>
		.ekc-tabs { margin-top: 16px; }
		.ekc-sec.card { max-width: 980px; margin-top: 16px; padding: 4px 24px 8px; }
		.ekc-sec h2.title { margin-bottom: 0; }
		.ekc-sec h3.ekc-group { margin: 20px 0 0; padding-top: 16px; border-top: 1px solid #dcdcde; font-size: 14px; }
		.ekc-sec h2 + h3.ekc-group { border-top: 0; padding-top: 4px; }
		.ekc-sec .form-table { margin-top: 4px; }
		.ekc-sec .form-table th { width: 230px; }
		.ekc-sec tr.ekc-child th { padding-left: 22px; font-weight: 400; }
		.ekc-sec tr.ekc-child th label::before { content: "↳ "; color: #8c8f94; }
		.ekc-sec tr.ekc-off { opacity: .5; }
		.ekc-sec .description { max-width: 640px; }
		.js .ekc-sec { display: none; }
		.js .ekc-sec.is-active { display: block; }
	</style>
	<script>
	( function () {
		var tabs = [].slice.call( document.querySelectorAll( '.ekc-tabs .nav-tab' ) );
		var ref = document.querySelector( 'input[name="_wp_http_referer"]' );
		function show( slug, remember ) {
			var current = tabs.filter( function ( t ) { return t.dataset.tab === slug; } )[ 0 ] || tabs[ 0 ];
			if ( ! current ) { return; }
			slug = current.dataset.tab;
			tabs.forEach( function ( t ) { t.classList.toggle( 'nav-tab-active', t === current ); } );
			document.querySelectorAll( '.ekc-sec' ).forEach( function ( c ) { c.classList.toggle( 'is-active', c.id === 'ekc-sec-' + slug ); } );
			if ( remember ) { history.replaceState( null, '', '#ekc-sec-' + slug ); }
			if ( ref ) { ref.value = ref.value.replace( /#.*$/, '' ) + '#ekc-sec-' + slug; }
		}
		tabs.forEach( function ( t ) {
			t.addEventListener( 'click', function ( e ) { e.preventDefault(); show( t.dataset.tab, true ); } );
		} );
		show( location.hash.replace( '#ekc-sec-', '' ), false );
		document.querySelectorAll( 'tr[data-parent]' ).forEach( function ( row ) {
			var box = document.getElementById( row.dataset.parent );
			if ( ! box ) { return; }
			var sync = function () { row.classList.toggle( 'ekc-off', ! box.checked ); };
			box.addEventListener( 'change', sync );
			sync();
		} );
	} )();
	</script>
	<?php
}
