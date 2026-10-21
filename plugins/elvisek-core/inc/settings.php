<?php
/**
 * EKC module: settings
 * Version: 1.0.0
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
	echo '<form method="post" action="options.php">';
	settings_fields( 'ekc_settings_group' );
	foreach ( $groups as $section => $fields ) {
		echo '<h2 class="title">' . esc_html( $section ) . '</h2><table class="form-table" role="presentation">';
		foreach ( $fields as $key => $field ) {
			printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( 'ekc-' . str_replace( '_', '-', $key ) ), esc_html( (string) $field['label'] ) );
			ekc_settings_field( $key, $field );
			echo '</td></tr>';
		}
		echo '</table>';
	}
	submit_button();
	echo '</form></div>';
}
