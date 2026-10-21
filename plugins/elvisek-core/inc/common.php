<?php
/**
 * EKC module: common
 * Version: 1.0.1
 * Requires: –
 * Replaces: –
 * Summary: Společné API pro moduly: registr nastavení, helpery, karanténa, kostra Údržby webu a migrace dat.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Obsah:
 *  1) Stránky administrace a filtry (ekc_page_url, ekc_filter)
 *  2) Registr nastavení (ekc_settings_register, ekc_opt)
 *  3) Helpery: IP, formátování, stav
 *  4) Databáze a velikosti
 *  5) Přílohy a vyloučené složky
 *  6) Karanténa
 *  7) Údržba webu (Nástroje → Údržba webu, ekc_maintenance_section)
 *  8) Migrace dat ze starých pluginů (ekc_migrate)
 */

const EKC_SCHEMA            = 1;       // verze migrace v common; zvýšit při novém kroku v ekc_migration_steps()
const EKC_QUARANTINE_TABLE  = 'ekq_';  // prefix tabulek v karanténě: {prefix}ekq_*
const EKC_QUARANTINE_LEGACY = array( 'mndq_' );
const EKC_LOGIN_LOG_MAX     = 200;     // nejvýš záznamů v ekc_login_log
const EKC_LOGIN_LOG_DAYS    = 90;      // a nejvýš dní

/* =========================================================================
 * 1) Stránky administrace a filtry
 * ====================================================================== */

/**
 * Nadřazená stránka (soubor v wp-admin) pro stránky pluginu. Modul může stránku přesunout
 * filtrem ekc_page_parents (např. log přihlášení pod Uživatele: 'users.php').
 *
 * @return array<string,string> slug => soubor
 */
function ekc_page_parents(): array {
	return (array) apply_filters(
		'ekc_page_parents',
		array(
			'ekc-nastaveni' => 'options-general.php',
			'ekc-udrzba'    => 'tools.php',
			'ekc-login-log' => 'tools.php',
		)
	);
}

/**
 * Adresa stránky pluginu v administraci.
 *
 * @param string $page Slug stránky (ekc-nastaveni, ekc-udrzba, ekc-login-log).
 * @param array  $args Další parametry dotazu.
 * @return string
 */
function ekc_page_url( string $page, array $args = array() ): string {
	$parents = ekc_page_parents();
	$parent  = $parents[ $page ] ?? 'admin.php';
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( $parent ) );
}

/**
 * apply_filters() se zpětnou kompatibilitou: když na webu někdo používá starý filtr
 * mnd_core_*, zavolá se přes apply_filters_deprecated() a pak nový.
 *
 * @param string $hook  Nový název filtru (ekc_*).
 * @param mixed  $value Hodnota.
 * @param mixed  ...$args Další argumenty.
 * @return mixed
 */
function ekc_filter( string $hook, $value, ...$args ) {
	$legacy = array(
		'ekc_client_ip'           => 'mnd_core_client_ip',
		'ekc_security_headers'    => 'mnd_core_security_headers',
		'ekc_llms_lines'          => 'mnd_core_llms_lines',
		'ekc_media_excluded_dirs' => 'mnd_core_media_excluded_dirs',
		'ekc_order_types'         => 'mnd_core_order_types',
		'ekc_allow_xmlrpc'        => 'mnd_core_allow_xmlrpc',
		'ekc_webp_batch_seconds'  => 'mnd_core_webp_batch_seconds',
	);
	if ( isset( $legacy[ $hook ] ) && has_filter( $legacy[ $hook ] ) ) {
		$value = apply_filters_deprecated( $legacy[ $hook ], array_merge( array( $value ), $args ), '1.0.0', $hook );
	}
	return apply_filters( $hook, $value, ...$args );
}

/* =========================================================================
 * 2) Registr nastavení
 * Moduly registrují pole hned při načtení souboru. Hodnoty jsou v jedné option ekc_settings
 * (autoload), sloučené s výchozími. Stránku vykresluje inc/settings.php.
 * ====================================================================== */

/**
 * Úložiště registru (interní).
 *
 * @param string|null $key   Klíč k přidání.
 * @param array|null  $field Popis pole.
 * @return array<string,array>
 */
function ekc_settings_fields( ?string $key = null, ?array $field = null ): array {
	static $fields = array();
	if ( null !== $key && null !== $field ) {
		$fields[ $key ] = $field;
	}
	return $fields;
}

/**
 * Modul přidá pole do Nastavení → NAZEV Core.
 *
 * @param string $key   Klíč v ekc_settings (malá písmena, podtržítka).
 * @param array  $field type (bool|int|select|text|textarea|list), default, label, help, section,
 *                      options (select: hodnota => popisek), min, max, unit (int), sanitize (callable).
 */
function ekc_settings_register( string $key, array $field ): void {
	$type     = in_array( $field['type'] ?? '', array( 'bool', 'int', 'select', 'text', 'textarea', 'list' ), true ) ? $field['type'] : 'bool';
	$defaults = array(
		'bool'     => false,
		'int'      => 0,
		'select'   => '',
		'text'     => '',
		'textarea' => '',
		'list'     => array(),
	);
	$field = array_merge(
		array(
			'type'     => $type,
			'default'  => $defaults[ $type ],
			'label'    => $key,
			'help'     => '',
			'section'  => 'Obecné',
			'options'  => array(),
			'min'      => null,
			'max'      => null,
			'unit'     => '',
			'sanitize' => null,
		),
		$field
	);
	$field['type'] = $type;
	if ( 'select' === $type && '' === $field['default'] && $field['options'] ) {
		$field['default'] = (string) array_key_first( $field['options'] );
	}
	ekc_settings_fields( sanitize_key( $key ), $field );
}

/**
 * Očištění jedné hodnoty podle typu pole.
 *
 * @param array $field Popis pole (z registru).
 * @param mixed $value Hodnota z formuláře nebo ze staré option.
 * @return mixed
 */
function ekc_settings_sanitize_value( array $field, $value ) {
	if ( is_callable( $field['sanitize'] ?? null ) ) {
		return call_user_func( $field['sanitize'], $value, $field );
	}
	switch ( $field['type'] ) {
		case 'bool':
			return empty( $value ) ? 0 : 1;
		case 'int':
			if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
				return (int) $field['default'];
			}
			$value = (int) $value;
			if ( null !== $field['min'] ) {
				$value = max( (int) $field['min'], $value );
			}
			if ( null !== $field['max'] ) {
				$value = min( (int) $field['max'], $value );
			}
			return $value;
		case 'select':
			return array_key_exists( (string) $value, (array) $field['options'] ) ? (string) $value : (string) $field['default'];
		case 'text':
			return sanitize_text_field( (string) $value );
		case 'textarea':
			return sanitize_textarea_field( (string) $value );
		case 'list':
			$items = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
			$items = array_map( static fn( $item ) => sanitize_text_field( (string) $item ), (array) $items );
			return array_values( array_unique( array_filter( $items, static fn( $item ) => '' !== $item ) ) );
	}
	return $value;
}

/**
 * Výchozí hodnoty všech registrovaných polí.
 *
 * @return array
 */
function ekc_settings_defaults(): array {
	$defaults = array();
	foreach ( ekc_settings_fields() as $key => $field ) {
		$defaults[ $key ] = $field['default'];
	}
	return $defaults;
}

/**
 * Uložené hodnoty sloučené s výchozími.
 *
 * @return array
 */
function ekc_settings(): array {
	$saved = get_option( 'ekc_settings', array() );
	return array_merge( ekc_settings_defaults(), is_array( $saved ) ? $saved : array() );
}

/**
 * Hodnota nastavení (typ podle registru: bool, int, string, array). Neznámý klíč = null.
 *
 * @param string $key Klíč.
 * @return mixed
 */
function ekc_opt( string $key ) {
	$fields   = ekc_settings_fields();
	$settings = ekc_settings();
	if ( ! array_key_exists( $key, $settings ) ) {
		return null;
	}
	$value = $settings[ $key ];
	switch ( $fields[ $key ]['type'] ?? '' ) {
		case 'bool':
			return ! empty( $value );
		case 'int':
			return (int) $value;
		case 'list':
			return is_array( $value ) ? $value : ekc_settings_sanitize_value( $fields[ $key ], $value );
		case 'select':
		case 'text':
		case 'textarea':
			return (string) $value;
	}
	return $value;
}

/**
 * Uloží jednu hodnotu nastavení (očištěnou podle registru).
 *
 * @param string $key   Klíč.
 * @param mixed  $value Hodnota.
 * @return bool
 */
function ekc_opt_set( string $key, $value ): bool {
	$fields = ekc_settings_fields();
	$saved  = get_option( 'ekc_settings', array() );
	$saved  = is_array( $saved ) ? $saved : array();
	$saved[ $key ] = isset( $fields[ $key ] ) ? ekc_settings_sanitize_value( $fields[ $key ], $value ) : $value;
	return update_option( 'ekc_settings', $saved, true );
}

/* =========================================================================
 * 3) Helpery: IP, formátování, stav
 * ====================================================================== */

/**
 * IP adresa návštěvníka z REMOTE_ADDR. Za reverzní proxy upravit filtrem ekc_client_ip.
 *
 * @return string Platná IP adresa, jinak '0.0.0.0'.
 */
function ekc_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip = (string) ekc_filter( 'ekc_client_ip', $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Velikost v B, kB, MB nebo GB s českým formátem čísel.
 *
 * @param int $bytes Bajty.
 * @return string
 */
function ekc_size( int $bytes ): string {
	if ( $bytes >= GB_IN_BYTES ) {
		return number_format_i18n( $bytes / GB_IN_BYTES, 2 ) . ' GB';
	}
	if ( $bytes >= MB_IN_BYTES ) {
		return number_format_i18n( $bytes / MB_IN_BYTES, 1 ) . ' MB';
	}
	if ( $bytes >= KB_IN_BYTES ) {
		return number_format_i18n( $bytes / KB_IN_BYTES, 0 ) . ' kB';
	}
	return number_format_i18n( max( 0, $bytes ) ) . ' B';
}

/**
 * Datum a čas v časovém pásmu webu (j. n. Y H:i).
 *
 * @param int $ts Unix čas.
 * @return string
 */
function ekc_datetime( int $ts ): string {
	return (string) wp_date( 'j. n. Y H:i', $ts );
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
function ekc_plural( int $n, string $one, string $few, string $many ): string {
	$abs  = abs( $n );
	$form = 1 === $abs ? $one : ( ( $abs >= 2 && $abs <= 4 ) ? $few : $many );
	return str_contains( $form, '%d' ) ? sprintf( $form, $n ) : $form;
}

/**
 * Barevná tečka stavu (do widgetu Zdraví webu a přehledů).
 *
 * @param string $state ok|warn|bad.
 * @return string HTML.
 */
function ekc_status_dot( string $state ): string {
	$colors = array(
		'ok'   => '#1a7f37',
		'warn' => '#bf8700',
		'bad'  => '#cf222e',
	);
	return '<span style="color:' . ( $colors[ $state ] ?? $colors['ok'] ) . '" aria-hidden="true">●</span>';
}

/* =========================================================================
 * 4) Databáze a velikosti
 * ====================================================================== */

/**
 * Tabulky webu (podle prefixu) seřazené od největší.
 *
 * @return object[] {t: název, r: řádky, d: data, i: indexy, f: volné místo}
 */
function ekc_db_tables(): array {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT table_name AS t, table_rows AS r, data_length AS d, index_length AS i, data_free AS f
			 FROM information_schema.tables WHERE table_schema = %s AND table_name LIKE %s ORDER BY (data_length + index_length) DESC',
			DB_NAME,
			$wpdb->esc_like( $wpdb->prefix ) . '%'
		)
	);
	return $rows ? $rows : array();
}

/**
 * Velikost tabulek webu v bajtech.
 *
 * @return int
 */
function ekc_db_size(): int {
	$bytes = 0;
	foreach ( ekc_db_tables() as $table ) {
		$bytes += (int) $table->d + (int) $table->i;
	}
	return $bytes;
}

/**
 * Volby načítané na každé stránce (autoload).
 *
 * @return array{total:int,count:int,top:object[]} top = 10 největších {n: název, b: bajty}
 */
function ekc_db_autoload(): array {
	global $wpdb;
	$where = "autoload IN ('yes','on','auto','auto-on')";
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- pevný výraz
	return array(
		'total' => (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE $where" ),
		'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE $where" ),
		'top'   => (array) $wpdb->get_results( "SELECT option_name AS n, LENGTH(option_value) AS b FROM {$wpdb->options} WHERE $where ORDER BY b DESC LIMIT 10" ),
	);
	// phpcs:enable
}

/**
 * Velikost souboru nebo složky (rekurzivně).
 *
 * @param string $path Cesta.
 * @return int Bajty.
 */
function ekc_path_size( string $path ): int {
	if ( is_file( $path ) ) {
		return (int) filesize( $path );
	}
	$bytes = 0;
	if ( is_dir( $path ) ) {
		try {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( $file->isFile() ) {
					$bytes += (int) $file->getSize();
				}
			}
		} catch ( Throwable $e ) {
			return $bytes; // nečitelná podsložka
		}
	}
	return $bytes;
}

/* =========================================================================
 * 5) Přílohy a vyloučené složky
 * ====================================================================== */

/**
 * Všechny soubory přílohy na disku (soubor, nezmenšený originál, zmenšeniny).
 *
 * @param int $id ID přílohy.
 * @return string[] Absolutní cesty existujících souborů.
 */
function ekc_attachment_files( int $id ): array {
	$main = get_attached_file( $id );
	if ( ! $main ) {
		return array();
	}
	$dir   = dirname( $main );
	$meta  = wp_get_attachment_metadata( $id );
	$meta  = is_array( $meta ) ? $meta : array();
	$files = array( $main );
	if ( ! empty( $meta['original_image'] ) ) {
		$files[] = $dir . '/' . $meta['original_image'];
	}
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		if ( is_array( $size ) && ! empty( $size['file'] ) ) {
			$files[] = $dir . '/' . $size['file'];
		}
	}
	return array_values( array_unique( array_filter( $files, 'is_file' ) ) );
}

/**
 * Složky v uploads, jejichž soubory se nepřevádějí ani nenabízejí jako nepoužité
 * (přílohy z formulářů – osobní údaje). Filtr ekc_media_excluded_dirs.
 *
 * @return string[] Cesty relativně k uploads, s lomítkem na konci.
 */
function ekc_media_excluded_dirs(): array {
	$dirs = (array) ekc_filter( 'ekc_media_excluded_dirs', array( 'formidable/', 'wpforms/', 'gravity_forms/', 'wpcf7_uploads/' ) );
	return array_values( array_filter( array_map( static fn( $dir ) => trailingslashit( ltrim( (string) $dir, '/' ) ), $dirs ), static fn( $dir ) => '/' !== $dir ) );
}

/**
 * Leží příloha ve vyloučené složce?
 *
 * @param int $id ID přílohy.
 * @return bool
 */
function ekc_media_excluded( int $id ): bool {
	$rel = (string) get_post_meta( $id, '_wp_attached_file', true );
	foreach ( ekc_media_excluded_dirs() as $dir ) {
		if ( str_starts_with( $rel, $dir ) ) {
			return true;
		}
	}
	return false;
}

/* =========================================================================
 * 6) Karanténa
 * Co se má smazat, přesune se napřed sem; trvalé smazání je na správci (sekce Karanténa v Údržbě).
 * Mimo webový kořen, když to hosting dovolí, jinak wp-content/EKC_SLUG-karantena zamčená
 * .htaccess (na Nginx .htaccess neplatí – Údržba na to upozorní). Přednost má konstanta
 * EKC_QUARANTINE_DIR ve wp-config.php.
 * ====================================================================== */

/**
 * Cesta ke karanténě (nic nevytváří). Zvolené umístění se pamatuje v option ekc_quarantine_base.
 *
 * @return string Bez lomítka na konci.
 */
function ekc_quarantine_base(): string {
	if ( defined( 'EKC_QUARANTINE_DIR' ) && EKC_QUARANTINE_DIR ) {
		return untrailingslashit( (string) EKC_QUARANTINE_DIR );
	}
	$saved = (string) get_option( 'ekc_quarantine_base', '' );
	if ( $saved && is_dir( $saved ) ) {
		return $saved;
	}
	$name   = EKC_SLUG . '-karantena';
	$root   = untrailingslashit( ABSPATH );
	$docroot = isset( $_SERVER['DOCUMENT_ROOT'] ) ? untrailingslashit( (string) realpath( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ) ) : '';
	if ( $docroot && str_starts_with( (string) realpath( $root ), $docroot ) ) {
		$root = $docroot; // WordPress v podsložce webu: „mimo“ znamená nad document rootem
	}
	$outside = dirname( $root ) . '/' . $name;
	$inside  = WP_CONTENT_DIR . '/' . $name;
	if ( is_dir( $outside ) ) {
		return $outside;
	}
	if ( is_dir( $inside ) ) {
		return $inside;
	}
	return @wp_is_writable( dirname( $outside ) ) ? $outside : $inside; // phpcs:ignore WordPress.PHP.NoSilencedErrors -- open_basedir
}

/**
 * Leží karanténa mimo webový kořen?
 *
 * @return bool
 */
function ekc_quarantine_outside(): bool {
	$base = wp_normalize_path( ekc_quarantine_base() ) . '/';
	return ! str_starts_with( $base, wp_normalize_path( untrailingslashit( ABSPATH ) ) . '/' )
		&& ! str_starts_with( $base, wp_normalize_path( WP_CONTENT_DIR ) . '/' );
}

/**
 * Složka karantény (vytvoří ji, uvnitř webu i s .htaccess a index.php).
 *
 * @param string $sub Podsložka.
 * @return string|WP_Error Cesta s lomítkem na konci.
 */
function ekc_quarantine_dir( string $sub = '' ) {
	$base = ekc_quarantine_base();
	$dir  = trailingslashit( $base ) . ( '' !== $sub ? trim( $sub, '/' ) . '/' : '' );
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'ekc_quarantine', 'Nepodařilo se vytvořit složku karantény ' . $base . '.' );
	}
	if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! ( defined( 'EKC_QUARANTINE_DIR' ) && EKC_QUARANTINE_DIR ) && get_option( 'ekc_quarantine_base' ) !== $base ) {
		update_option( 'ekc_quarantine_base', $base, false );
	}
	if ( ! ekc_quarantine_outside() ) {
		if ( ! is_file( $base . '/.htaccess' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $base . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		if ( ! is_file( $base . '/index.php' ) ) {
			file_put_contents( $base . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
	return $dir;
}

/**
 * Přesune soubor nebo složku do karantény: {karanténa}/{RRRR-MM-DD}/{subdir}/{cesta relativně k webu}.
 *
 * @param string $path   Absolutní cesta.
 * @param string $subdir Podsložka (např. webp, media, slug pluginu).
 * @return string|false Nová cesta, nebo false.
 */
function ekc_quarantine_move( string $path, string $subdir ) {
	if ( '' === $path || ! file_exists( $path ) ) {
		return false;
	}
	$norm = wp_normalize_path( $path );
	$abs  = wp_normalize_path( untrailingslashit( ABSPATH ) ) . '/';
	$cont = wp_normalize_path( dirname( WP_CONTENT_DIR ) ) . '/';
	if ( str_starts_with( $norm, $abs ) ) {
		$rel = substr( $norm, strlen( $abs ) );
	} elseif ( str_starts_with( $norm, $cont ) ) {
		$rel = substr( $norm, strlen( $cont ) );
	} else {
		$rel = basename( $norm );
	}
	$group = trim( preg_replace( '/[^A-Za-z0-9._-]+/', '-', $subdir ), '-.' );
	$rdir  = dirname( $rel );
	$dir   = ekc_quarantine_dir( wp_date( 'Y-m-d' ) . '/' . ( '' !== $group ? $group : 'ostatni' ) . ( '.' !== $rdir ? '/' . $rdir : '' ) );
	if ( is_wp_error( $dir ) ) {
		return false;
	}
	$target = $dir . basename( $norm );
	if ( file_exists( $target ) ) {
		$target .= '-' . time();
	}
	// phpcs:disable WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	if ( @rename( $path, $target ) ) {
		return $target;
	}
	// Jiný disk: soubor zkopírovat a smazat (složky jen přejmenováním).
	if ( is_file( $path ) && @copy( $path, $target ) ) {
		@unlink( $path );
		return $target;
	}
	// phpcs:enable
	return false;
}

/**
 * Obsah karantény: složka se soubory a tabulky {prefix}ekq_* (i starší {prefix}mndq_*).
 *
 * @return array{dir:?string,bytes:int,tables:array<string,int>} tables = název => bajty
 */
function ekc_quarantine_contents(): array {
	global $wpdb;
	$base   = ekc_quarantine_base();
	$dir    = is_dir( $base ) ? $base : null;
	$tables = array();
	foreach ( array_merge( array( EKC_QUARANTINE_TABLE ), EKC_QUARANTINE_LEGACY ) as $prefix ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT table_name AS t, data_length + index_length AS b FROM information_schema.tables WHERE table_schema = %s AND table_name LIKE %s',
				DB_NAME,
				$wpdb->esc_like( $wpdb->prefix . $prefix ) . '%'
			)
		);
		foreach ( (array) $rows as $row ) {
			$tables[ $row->t ] = (int) $row->b;
		}
	}
	return array(
		'dir'    => $dir,
		'bytes'  => $dir ? ekc_path_size( $dir ) : 0,
		'tables' => $tables,
	);
}

/**
 * Trvalé smazání karantény (soubory i tabulky). Volá se jen z Údržby po potvrzení.
 *
 * @return string Zpráva.
 */
function ekc_quarantine_purge(): string {
	global $wpdb;
	$q        = ekc_quarantine_contents();
	$prefixes = array_merge( array( EKC_QUARANTINE_TABLE ), EKC_QUARANTINE_LEGACY );
	$dropped  = 0;
	foreach ( array_keys( $q['tables'] ) as $table ) {
		foreach ( $prefixes as $prefix ) {
			if ( str_starts_with( $table, $wpdb->prefix . $prefix ) ) {
				$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' ); // phpcs:ignore WordPress.DB
				++$dropped;
				break;
			}
		}
	}
	if ( $q['dir'] && str_ends_with( $q['dir'], '-karantena' ) ) {
		$keep = ekc_quarantine_outside() ? array() : array( '.htaccess', 'index.php' );
		$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $q['dir'], FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $file ) {
			if ( dirname( $file->getPathname() ) === $q['dir'] && in_array( $file->getFilename(), $keep, true ) ) {
				continue;
			}
			$file->isDir() ? @rmdir( $file->getPathname() ) : @unlink( $file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
	return sprintf( 'Karanténa smazána: %s souborů a %d %s.', ekc_size( $q['bytes'] ), $dropped, ekc_plural( $dropped, 'tabulka', 'tabulky', 'tabulek' ) );
}

/* =========================================================================
 * 7) Údržba webu — Nástroje → Údržba webu (slug ekc-udrzba)
 * Moduly registrují sekce: ekc_maintenance_section( id, [title, order, render, tasks] ).
 * Formuláře míří na admin-post.php?action=ekc_maintenance, nonce na úlohu, po akci
 * přesměrování zpět (PRG) a jednorázová zpráva v transientu ekc_maint_msg_<uid>.
 * ====================================================================== */

/**
 * Úložiště sekcí (interní).
 *
 * @param string|null $id   ID sekce k přidání.
 * @param array|null  $args Popis sekce.
 * @return array<string,array>
 */
function ekc_maintenance_sections( ?string $id = null, ?array $args = null ): array {
	static $sections = array();
	if ( null !== $id && null !== $args ) {
		$sections[ $id ] = $args;
	}
	return $sections;
}

/**
 * Modul zaregistruje sekci Údržby webu.
 *
 * @param string $id   ID sekce (a-z, 0-9, -), zároveň kotva #ekc-<id>.
 * @param array  $args title (string), order (int, menší = výš), render (callable( string $id )),
 *                     tasks (array task_id => callable( string $item, array $items ): string|array).
 */
function ekc_maintenance_section( string $id, array $args ): void {
	$id = sanitize_key( $id );
	ekc_maintenance_sections(
		$id,
		array_merge(
			array(
				'title'  => $id,
				'order'  => 50,
				'render' => null,
				'tasks'  => array(),
			),
			$args
		)
	);
}

/**
 * Adresa stránky Údržba webu.
 *
 * @param array  $args    Parametry dotazu.
 * @param string $section Kotva sekce.
 * @return string
 */
function ekc_maintenance_url( array $args = array(), string $section = '' ): string {
	return ekc_page_url( 'ekc-udrzba', $args ) . ( '' !== $section ? '#ekc-' . sanitize_key( $section ) : '' );
}

/**
 * Skrytá pole formuláře úlohy (action, section, task, nonce). Modul píše vlastní
 * <form method="post" action="admin_url( 'admin-post.php' )">, když potřebuje víc polí (např. výběr items[]).
 *
 * @param string $section ID sekce.
 * @param string $task    ID úlohy.
 */
function ekc_maintenance_fields( string $section, string $task ): void {
	printf(
		'<input type="hidden" name="action" value="ekc_maintenance"><input type="hidden" name="section" value="%1$s"><input type="hidden" name="task" value="%2$s">',
		esc_attr( $section ),
		esc_attr( $task )
	);
	wp_nonce_field( 'ekc_maint_' . $section . '_' . $task, '_ekc_nonce' );
}

/**
 * Formulář s jedním tlačítkem (volitelně s potvrzením).
 *
 * @param string $section ID sekce.
 * @param string $task    ID úlohy.
 * @param string $label   Text tlačítka.
 * @param string $confirm Potvrzovací otázka ('' = bez potvrzení).
 * @param bool   $primary Zvýrazněné tlačítko.
 * @param string $item    Upřesnění úlohy (předá se callbacku jako $item).
 */
function ekc_maintenance_button( string $section, string $task, string $label, string $confirm = '', bool $primary = false, string $item = '' ): void {
	printf(
		'<form method="post" action="%1$s" style="display:inline-block;margin:0 4px 4px 0"%2$s>',
		esc_url( admin_url( 'admin-post.php' ) ),
		'' !== $confirm ? ' onsubmit="return window.confirm(' . esc_attr( (string) wp_json_encode( $confirm ) ) . ');"' : ''
	);
	ekc_maintenance_fields( $section, $task );
	printf(
		'<input type="hidden" name="item" value="%1$s"><button type="submit" class="button%2$s">%3$s</button></form>',
		esc_attr( $item ),
		$primary ? ' button-primary' : '',
		esc_html( $label )
	);
}

/**
 * Odkaz pro drahý výpočet „až na kliknutí“ (GET, jen čte). V render callbacku pak
 * if ( ekc_maintenance_showing( 'unused' ) ) { … spočítat a vypsat … }.
 *
 * @param string $section ID sekce.
 * @param string $key     Co zobrazit.
 * @param string $label   Text tlačítka.
 */
function ekc_maintenance_show_link( string $section, string $key, string $label ): void {
	printf( '<a class="button" href="%1$s">%2$s</a>', esc_url( ekc_maintenance_url( array( 'ekc_show' => $key ), $section ) ), esc_html( $label ) );
}

/**
 * Chce správce zobrazit výsledek drahého výpočtu?
 *
 * @param string $key Klíč z ekc_maintenance_show_link().
 * @return bool
 */
function ekc_maintenance_showing( string $key ): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen zobrazení
	return isset( $_GET['ekc_show'] ) && sanitize_key( wp_unslash( $_GET['ekc_show'] ) ) === $key;
}

/**
 * Zpracování úlohy (admin-post.php?action=ekc_maintenance).
 */
function ekc_maintenance_handle(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce se ověřuje hned níže podle úlohy
	$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
	$task    = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
	// phpcs:enable
	check_admin_referer( 'ekc_maint_' . $section . '_' . $task, '_ekc_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Na tuto akci nemáte oprávnění.', 403 );
	}

	$sections = ekc_maintenance_sections();
	$callback = $sections[ $section ]['tasks'][ $task ] ?? null;
	if ( ! is_callable( $callback ) ) {
		wp_die( 'Neznámá úloha.', 400 );
	}

	$item  = isset( $_POST['item'] ) ? sanitize_text_field( wp_unslash( $_POST['item'] ) ) : '';
	$items = isset( $_POST['items'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['items'] ) ) : array();

	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	try {
		$result = call_user_func( $callback, $item, $items );
	} catch ( Throwable $e ) {
		$result = array(
			'message' => 'Úloha selhala: ' . $e->getMessage(),
			'type'    => 'error',
		);
	}
	$result = is_array( $result ) ? $result : array( 'message' => (string) $result );
	$result = array_merge(
		array(
			'message' => '',
			'type'    => 'success', // success|warning|error|info
			'args'    => array(),   // parametry do adresy po přesměrování (např. pokračování dávky)
			'mark'    => true,      // zapsat čas poslední údržby
		),
		$result
	);

	if ( $result['mark'] && 'error' !== $result['type'] ) {
		update_option( 'ekc_maint_last', time(), false );
	}
	do_action( 'ekc_maintenance_done', $section, $task, $result );
	if ( '' !== $result['message'] ) {
		set_transient(
			'ekc_maint_msg_' . get_current_user_id(),
			array(
				'message' => (string) $result['message'],
				'type'    => (string) $result['type'],
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	$back = wp_get_referer();
	$back = $back ? remove_query_arg( array( 'ekc_done', 'ekc_show' ), $back ) : ekc_maintenance_url();
	$back = (string) preg_replace( '/#.*$/', '', $back );
	wp_safe_redirect( add_query_arg( array_merge( array( 'ekc_done' => 1 ), (array) $result['args'] ), $back ) . '#ekc-' . $section );
	exit;
}
add_action( 'admin_post_ekc_maintenance', 'ekc_maintenance_handle' );

/**
 * Jednorázová zpráva po úloze (Údržba, log přihlášení, …).
 */
function ekc_maintenance_notice(): void {
	$key = 'ekc_maint_msg_' . get_current_user_id();
	$msg = get_transient( $key );
	if ( ! $msg ) {
		return;
	}
	delete_transient( $key );
	$msg  = is_array( $msg ) ? $msg : array( 'message' => (string) $msg );
	$type = in_array( $msg['type'] ?? '', array( 'success', 'warning', 'error', 'info' ), true ) ? $msg['type'] : 'success';
	printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( (string) $msg['message'] ) );
}

/**
 * Stránka v menu Nástroje.
 */
function ekc_maintenance_menu(): void {
	$parents = ekc_page_parents();
	add_submenu_page( $parents['ekc-udrzba'] ?? 'tools.php', 'Údržba webu', 'Údržba webu', 'manage_options', 'ekc-udrzba', 'ekc_maintenance_page' );
}
add_action( 'admin_menu', 'ekc_maintenance_menu' );

/**
 * Stránka Nástroje → Údržba webu: sekce podle order.
 */
function ekc_maintenance_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$sections = ekc_maintenance_sections();
	uasort( $sections, static fn( $a, $b ) => ( (int) $a['order'] <=> (int) $b['order'] ) ?: strcmp( (string) $a['title'], (string) $b['title'] ) );

	echo '<div class="wrap"><h1>Údržba webu</h1>';
	ekc_maintenance_notice();
	$last = (int) get_option( 'ekc_maint_last', 0 );
	echo '<p>Nástroje pro kontrolu a úklid webu. Co se maže, přesune se nejdřív do karantény. ';
	echo $last ? esc_html( 'Poslední údržba: ' . ekc_datetime( $last ) . '.' ) : 'Údržba zatím neproběhla.';
	echo ' <a href="' . esc_url( ekc_page_url( 'ekc-nastaveni' ) ) . '">Nastavení</a></p>';

	if ( count( $sections ) > 2 ) {
		$links = array();
		foreach ( $sections as $id => $section ) {
			$links[] = '<a href="#ekc-' . esc_attr( $id ) . '">' . esc_html( (string) $section['title'] ) . '</a>';
		}
		echo '<p>' . implode( ' · ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno výše
	}

	foreach ( $sections as $id => $section ) {
		printf( '<div class="card" id="ekc-%1$s" style="max-width:none;margin-top:16px"><h2 class="title">%2$s</h2>', esc_attr( $id ), esc_html( (string) $section['title'] ) );
		if ( is_callable( $section['render'] ) ) {
			try {
				call_user_func( $section['render'], $id );
			} catch ( Throwable $e ) {
				echo '<p>' . ekc_status_dot( 'bad' ) . ' ' . esc_html( 'Sekci se nepodařilo zobrazit: ' . $e->getMessage() ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo '</div>';
	}
	echo '</div>';
}

/**
 * Sekce Systém: verze a umístění karantény.
 *
 * @param string $id ID sekce.
 */
function ekc_maintenance_render_system( string $id ): void {
	global $wpdb;
	$rows = array(
		'WordPress'           => get_bloginfo( 'version' ),
		'PHP'                 => PHP_VERSION . ( version_compare( PHP_VERSION, '8.1', '<' ) ? ' (doporučeno 8.1+)' : '' ),
		'Databáze'            => $wpdb->db_server_info(),
		'Paměť PHP'           => (string) ini_get( 'memory_limit' ),
		'Max. velikost nahrání' => ekc_size( (int) wp_max_upload_size() ),
		'Prostředí'           => wp_get_environment_type(),
		EKC_NAME . ' Core'    => EKC_VERSION,
		'Karanténa'           => ekc_quarantine_base(),
	);
	echo '<table class="widefat striped" style="max-width:720px"><tbody>';
	foreach ( $rows as $label => $value ) {
		printf( '<tr><td style="width:40%%">%1$s</td><td><code>%2$s</code></td></tr>', esc_html( $label ), esc_html( (string) $value ) );
	}
	echo '</tbody></table>';
	if ( ! ekc_quarantine_outside() ) {
		echo '<p>' . ekc_status_dot( 'warn' ) . ' Karanténa je uvnitř webu, chrání ji jen <code>.htaccess</code> (Apache). Na Nginx ji zamkněte v konfiguraci serveru, nebo nastavte <code>EKC_QUARANTINE_DIR</code> ve wp-config.php na složku mimo web.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Sekce Karanténa: obsah a trvalé smazání.
 *
 * @param string $id ID sekce.
 */
function ekc_maintenance_render_quarantine( string $id ): void {
	$q = ekc_quarantine_contents();
	if ( ! $q['dir'] && ! $q['tables'] ) {
		echo '<p>Karanténa je prázdná.</p>';
		return;
	}
	echo '<p>Soubory: <strong>' . esc_html( ekc_size( $q['bytes'] ) ) . '</strong>';
	if ( $q['dir'] ) {
		echo ' v <code>' . esc_html( $q['dir'] ) . '</code>';
	}
	echo '. Tabulky: <strong>' . count( $q['tables'] ) . '</strong>.</p>';
	if ( $q['tables'] ) {
		echo '<ul style="list-style:disc;margin-left:20px">';
		foreach ( $q['tables'] as $table => $bytes ) {
			echo '<li><code>' . esc_html( $table ) . '</code> ' . esc_html( ekc_size( $bytes ) ) . '</li>';
		}
		echo '</ul>';
	}
	echo '<p class="description">Než karanténu smažete, ověřte, že web funguje. Smazání je nevratné.</p>';
	ekc_maintenance_button( $id, 'purge', 'Smazat karanténu natrvalo', 'Smazat obsah karantény natrvalo? Tuto akci nejde vrátit.' );
}

ekc_maintenance_section(
	'system',
	array(
		'title'  => 'Systém',
		'order'  => 5,
		'render' => 'ekc_maintenance_render_system',
	)
);
ekc_maintenance_section(
	'quarantine',
	array(
		'title'  => 'Karanténa',
		'order'  => 95,
		'render' => 'ekc_maintenance_render_quarantine',
		'tasks'  => array(
			'purge' => static fn() => ekc_quarantine_purge(),
		),
	)
);

/* =========================================================================
 * 8) Migrace dat ze starých pluginů (EKC_LEGACY) a z předchozích verzí
 * Běží na init v administraci, cronu a WP-CLI (na frontendu ne – tam by stála dotaz navíc).
 * Kroky common jsou číslované (option ekc_schema), kroky modulů mají textové ID
 * (filtr ekc_migrations, hotové v option ekc_migrations_done). Každý krok je idempotentní,
 * vrací true = hotovo (false = zkusit příště) a staré klíče maže až po úspěšném převodu.
 * ====================================================================== */

/**
 * Kroky migrace v common: číslo schématu => callable.
 *
 * @return array<int,callable>
 */
function ekc_migration_steps(): array {
	return array(
		1 => 'ekc_migrate_legacy',
	);
}

/**
 * Spustí čekající migrace (zámek transientem ekc_migrate_lock).
 */
function ekc_migrate(): void {
	if ( ! ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) ) {
		return;
	}
	$schema  = (int) get_option( 'ekc_schema', 0 );
	$modules = (array) apply_filters( 'ekc_migrations', array() );
	$done    = get_option( 'ekc_migrations_done', array() );
	$done    = is_array( $done ) ? $done : array();
	$pending = array_diff( array_keys( $modules ), $done );
	if ( $schema >= EKC_SCHEMA && ! $pending ) {
		return;
	}
	if ( get_transient( 'ekc_migrate_lock' ) ) {
		return;
	}
	set_transient( 'ekc_migrate_lock', time(), 5 * MINUTE_IN_SECONDS );
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	foreach ( ekc_migration_steps() as $version => $step ) {
		if ( $version <= $schema ) {
			continue;
		}
		if ( ! ekc_migration_run( $step, 'common ' . $version ) ) {
			break; // další kroky čekají na tento
		}
		$schema = $version;
		update_option( 'ekc_schema', $schema, false );
	}

	foreach ( $pending as $id ) {
		if ( ekc_migration_run( $modules[ $id ], (string) $id ) ) {
			$done[] = (string) $id;
			update_option( 'ekc_migrations_done', array_values( array_unique( $done ) ), false );
		}
	}
	delete_transient( 'ekc_migrate_lock' );
}
add_action( 'init', 'ekc_migrate', 20 );

/**
 * Jeden krok migrace s ošetřením chyb.
 *
 * @param mixed  $step  Callable.
 * @param string $label Označení do logu.
 * @return bool Hotovo.
 */
function ekc_migration_run( $step, string $label ): bool {
	if ( ! is_callable( $step ) ) {
		return true; // neplatný krok neblokuje ostatní
	}
	try {
		return false !== call_user_func( $step );
	} catch ( Throwable $e ) {
		error_log( 'EKC migrace ' . $label . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return false;
	}
}

/**
 * Staré prefixy dat (z EKC_LEGACY).
 *
 * @return string[]
 */
function ekc_legacy_prefixes(): array {
	$legacy = defined( 'EKC_LEGACY' ) ? (array) EKC_LEGACY : array();
	return array_values( array_filter( array_map( 'sanitize_key', $legacy ) ) );
}

/**
 * Převod dat ze starých pluginů (schéma 1).
 *
 * @return bool
 */
function ekc_migrate_legacy(): bool {
	$ok = true;
	foreach ( ekc_legacy_prefixes() as $p ) {
		$ok = ekc_migrate_settings( $p ) && $ok;
		$ok = ekc_migrate_login_log( $p ) && $ok;
		$ok = ekc_migrate_last_login( $p ) && $ok;
		$ok = ekc_migrate_maint_last( $p ) && $ok;
		$ok = ekc_migrate_webp_fail( $p ) && $ok;
		$ok = ekc_migrate_dashboard( $p ) && $ok;
		ekc_migrate_cleanup( $p );
	}
	return $ok;
}

/**
 * Nastavení: {p}_settings (mnd_core) → ekc_settings (jen klíče, které některý modul registruje,
 * a jen pokud je nový web ještě nemá). Samostatné staré volby podle mapy.
 *
 * @param string $p Starý prefix.
 * @return bool
 */
function ekc_migrate_settings( string $p ): bool {
	$fields = ekc_settings_fields();
	$saved  = get_option( 'ekc_settings', array() );
	$saved  = is_array( $saved ) ? $saved : array();
	$before = $saved;

	$old = get_option( $p . '_settings', null );
	if ( is_array( $old ) ) {
		foreach ( $old as $key => $value ) {
			if ( isset( $fields[ $key ] ) && ! array_key_exists( $key, $saved ) ) {
				$saved[ $key ] = ekc_settings_sanitize_value( $fields[ $key ], $value );
			}
		}
	}

	// Samostatné volby: stará option => klíč v ekc_settings (převede se, jen když modul klíč registruje).
	$map     = array( $p . '_protect_content' => 'content_protection' );
	$convert = array();
	foreach ( $map as $option => $key ) {
		$value = get_option( $option, null );
		if ( null !== $value && isset( $fields[ $key ] ) ) {
			if ( ! array_key_exists( $key, $saved ) ) {
				$saved[ $key ] = ekc_settings_sanitize_value( $fields[ $key ], '1' === (string) $value ? 1 : 0 );
			}
			$convert[] = $option;
		}
	}

	if ( $saved !== $before && ! update_option( 'ekc_settings', $saved, true ) ) {
		return false;
	}
	if ( is_array( $old ) ) {
		delete_option( $p . '_settings' );
	}
	foreach ( $convert as $option ) {
		delete_option( $option );
	}
	return true;
}

/**
 * Převod řádku logu: formát A/D {t, u, ok, ip, ua} → {t, e, u, i, d, a}; mnd_core beze změny.
 *
 * @param mixed $row Řádek.
 * @return array|null
 */
function ekc_login_log_row( $row ): ?array {
	if ( ! is_array( $row ) || empty( $row['t'] ) ) {
		return null;
	}
	if ( array_key_exists( 'ok', $row ) ) {
		$row = array(
			't' => (int) $row['t'],
			'e' => $row['ok'] ? 'success' : 'failed',
			'u' => (string) ( $row['u'] ?? '' ),
			'i' => (string) ( $row['ip'] ?? '' ),
			'd' => 0,
			'a' => (string) ( $row['ua'] ?? '' ),
		);
	}
	return array(
		't' => (int) $row['t'],
		'e' => in_array( $row['e'] ?? '', array( 'success', 'failed', 'lockout' ), true ) ? $row['e'] : 'failed',
		'u' => (string) ( $row['u'] ?? '' ),
		'i' => (string) ( $row['i'] ?? '' ),
		'd' => (int) ( $row['d'] ?? 0 ),
		'a' => (string) ( $row['a'] ?? '' ),
	);
}

/**
 * Log přihlášení: {p}_login_log → ekc_login_log (sloučit s novými záznamy, bez duplicit).
 *
 * @param string $p Starý prefix.
 * @return bool
 */
function ekc_migrate_login_log( string $p ): bool {
	$old = get_option( $p . '_login_log', null );
	if ( null === $old ) {
		return true;
	}
	// Seskupit podle klíče zvlášť pro nový a starý log. Ze skupiny se vezme ta delší: stejné
	// záznamy v obou logech (opakovaný běh) se nezdvojí a víc chyb v téže sekundě se neztratí.
	$groups = array();
	foreach ( array( 'new' => (array) get_option( 'ekc_login_log', array() ), 'old' => (array) $old ) as $src => $list ) {
		foreach ( $list as $row ) {
			$row = ekc_login_log_row( $row );
			if ( $row ) {
				$groups[ $row['t'] . '|' . $row['e'] . '|' . $row['u'] . '|' . $row['i'] ][ $src ][] = $row;
			}
		}
	}
	$rows = array();
	foreach ( $groups as $group ) {
		$new = $group['new'] ?? array();
		$was = $group['old'] ?? array();
		array_push( $rows, ...( count( $was ) > count( $new ) ? $was : $new ) );
	}
	$limit = time() - EKC_LOGIN_LOG_DAYS * DAY_IN_SECONDS;
	$rows  = array_values( array_filter( $rows, static fn( $row ) => $row['t'] >= $limit ) );
	usort( $rows, static fn( $a, $b ) => $b['t'] <=> $a['t'] );
	$rows = array_slice( $rows, 0, EKC_LOGIN_LOG_MAX );

	update_option( 'ekc_login_log', $rows, false );
	if ( (array) get_option( 'ekc_login_log', array() ) !== $rows ) {
		return false;
	}
	delete_option( $p . '_login_log' );
	return true;
}

/**
 * Poslední přihlášení: user meta {p}_last_login → ekc_last_login (ponechá vyšší hodnotu).
 *
 * @param string $p Starý prefix.
 * @return bool
 */
function ekc_migrate_last_login( string $p ): bool {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $p . '_last_login' ) );
	foreach ( (array) $rows as $row ) {
		$old = (int) $row->meta_value;
		$cur = (int) get_user_meta( (int) $row->user_id, 'ekc_last_login', true );
		if ( $old > $cur ) {
			update_user_meta( (int) $row->user_id, 'ekc_last_login', $old );
		}
		if ( (int) get_user_meta( (int) $row->user_id, 'ekc_last_login', true ) >= $old ) {
			delete_user_meta( (int) $row->user_id, $p . '_last_login' );
		}
	}
	return true;
}

/**
 * Čas poslední údržby: {p}_maint_last / {p}_last_maintenance → ekc_maint_last (vyšší).
 *
 * @param string $p Starý prefix.
 * @return bool
 */
function ekc_migrate_maint_last( string $p ): bool {
	$latest = (int) get_option( 'ekc_maint_last', 0 );
	$found  = array();
	foreach ( array( $p . '_maint_last', $p . '_last_maintenance' ) as $option ) {
		$value = get_option( $option, null );
		if ( null !== $value ) {
			$latest  = max( $latest, (int) $value );
			$found[] = $option;
		}
	}
	if ( ! $found ) {
		return true;
	}
	if ( $latest > (int) get_option( 'ekc_maint_last', 0 ) ) {
		update_option( 'ekc_maint_last', $latest, false );
	}
	foreach ( $found as $option ) {
		delete_option( $option );
	}
	return true;
}

/**
 * Značka selhání převodu na WebP: postmeta _{p}_webp_fail → _ekc_webp_fail.
 *
 * @param string $p Starý prefix.
 * @return bool
 */
function ekc_migrate_webp_fail( string $p ): bool {
	global $wpdb;
	$old = '_' . $p . '_webp_fail';
	// Kde už nová značka je, starou jen smazat; zbytek přejmenovat.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE o FROM {$wpdb->postmeta} o JOIN {$wpdb->postmeta} n ON n.post_id = o.post_id AND n.meta_key = '_ekc_webp_fail' WHERE o.meta_key = %s",
			$old
		)
	);
	$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_ekc_webp_fail' WHERE meta_key = %s", $old ) );
	if ( $result && function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
		wp_cache_flush_group( 'post_meta' );
	}
	return false !== $result;
}

/**
 * Widget Zdraví webu: staré ID v uspořádání a skrytí Nástěnky ({p}_health, {p}_status) → ekc_health.
 *
 * @param string $p Starý prefix.
 * @return bool
 */
function ekc_migrate_dashboard( string $p ): bool {
	global $wpdb;
	$old_ids = array( $p . '_health', $p . '_status' );
	$keys    = array( 'meta-box-order_dashboard', 'metaboxhidden_dashboard', 'closedpostboxes_dashboard' );
	$rows    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN (%s, %s, %s) AND meta_value LIKE %s",
			$keys[0],
			$keys[1],
			$keys[2],
			'%' . $wpdb->esc_like( $p . '_' ) . '%'
		)
	);
	$swap = static function ( array $ids ) use ( $old_ids ): array {
		$ids = array_map( static fn( $id ) => in_array( $id, $old_ids, true ) ? 'ekc_health' : $id, $ids );
		return array_values( array_unique( array_filter( $ids, static fn( $id ) => '' !== $id ) ) );
	};
	foreach ( (array) $rows as $row ) {
		$value = maybe_unserialize( $row->meta_value );
		if ( ! is_array( $value ) ) {
			continue;
		}
		if ( 'meta-box-order_dashboard' === $row->meta_key ) {
			foreach ( $value as $column => $list ) {
				$value[ $column ] = implode( ',', $swap( explode( ',', (string) $list ) ) );
			}
		} else {
			$value = $swap( $value );
		}
		update_user_meta( (int) $row->user_id, $row->meta_key, $value );
	}
	return true;
}

/**
 * Smazat stará data, která se nepřevádějí: limity přihlášení, cache zdraví a vydání, verze úklidu.
 *
 * @param string $p Starý prefix.
 */
function ekc_migrate_cleanup( string $p ): void {
	global $wpdb;
	foreach ( array( 'lock_', 'fails_', 'fail_', 'count_', 'health', 'maint_msg_', 'webp_auto_' ) as $part ) {
		foreach ( array( '_transient_', '_transient_timeout_' ) as $kind ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $kind . $p . '_' . $part ) . '%' ) );
		}
	}
	foreach ( array( $p . '_release', $p . '_core_release' ) as $name ) {
		delete_site_transient( $name );
	}
	foreach ( array( $p . '_maint_v', $p . '_health_db' ) as $name ) {
		delete_option( $name );
		delete_transient( $name );
	}
	delete_metadata( 'user', 0, $p . '_full_menu', '', true );
	wp_cache_delete( 'alloptions', 'options' );
}

/**
 * Staré záložky a odkazy na stránky starých pluginů → nové stránky (jen když web má EKC_LEGACY
 * a stará stránka už neexistuje).
 */
function ekc_legacy_redirects(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen přesměrování
	if ( ! ekc_legacy_prefixes() || empty( $_GET['page'] ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		return;
	}
	$map = array(
		'ek-udrzba'                         => 'ekc-udrzba',
		'iv-udrzba'                         => 'ekc-udrzba',
		'ek-migrace'                        => 'ekc-udrzba',
		EKC_SLUG . '-core-maintenance'      => 'ekc-udrzba',
		'ek-login-log'                      => 'ekc-login-log',
		'iv-login-log'                      => 'ekc-login-log',
		EKC_SLUG . '-core-login-log'        => 'ekc-login-log',
		EKC_SLUG . '-core'                  => 'ekc-nastaveni',
	);
	$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $map[ $page ] ) ) {
		wp_safe_redirect( ekc_page_url( $map[ $page ] ) );
		exit;
	}
}
// Neexistující stránka: WordPress by vrátil 403 (menu.php běží dřív než admin_init).
add_action( 'admin_page_access_denied', 'ekc_legacy_redirects' );
