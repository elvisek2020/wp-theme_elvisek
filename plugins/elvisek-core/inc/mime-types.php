<?php
/**
 * EKC module: mime-types
 * Version: 1.0.0
 * Requires: common
 * Replaces: wp-extra-file-types, file-upload-types, mime-types-plus
 * Summary: Další typy souborů v Médiích podle seznamu v nastavení; rizikové typy jen pro administrátory.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Volitelný modul. Seznam typů je v Nastavení → Core → Média (klíč media_types), jeden na řádek:
 * „přípona“ nebo „přípona mime/typ“ (více přípon: „tgz|gz application/x-gzip“).
 *
 * - Běžné typy smí nahrát každý s upload_files.
 * - Rizikové typy (skripty, spustitelné soubory, obrazy disků, HTML a SVG) jen uživatel
 *   s manage_options. U nich se kontrola obsahu souboru neobchází: projdou, jen když
 *   skutečný MIME (fileinfo) odpovídá očekávaným hodnotám pro danou příponu.
 * - Typy, které by server mohl spustit (php, phtml, phar, cgi, asp…), nejdou povolit vůbec.
 */

const EKC_MIME_RISKY = array( 'sh', 'bash', 'zsh', 'py', 'pl', 'rb', 'js', 'mjs', 'exe', 'msi', 'bat', 'cmd', 'com', 'ps1', 'vbs', 'jar', 'apk', 'app', 'dmg', 'pkg', 'bin', 'gbl', 'iso', 'img', 'html', 'htm', 'xhtml', 'svg', 'svgz', 'swf', 'xml', 'mobileconfig' );
const EKC_MIME_NEVER = array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'cgi', 'asp', 'aspx', 'jsp', 'shtml', 'htaccess', 'htpasswd', 'ini' );

/**
 * Známé typy: přípona => [ MIME pro WordPress, přijatelné skutečné MIME podle fileinfo ].
 *
 * @return array<string,array{0:string,1:string[]}>
 */
function ekc_mime_known(): array {
	$binary = array( 'application/octet-stream' );
	return array(
		'7z'           => array( 'application/x-7z-compressed', array( 'application/x-7z-compressed' ) ),
		'bz2'          => array( 'application/x-bzip2', array( 'application/x-bzip2' ) ),
		'tgz'          => array( 'application/x-gzip', array( 'application/gzip', 'application/x-gzip' ) ),
		'xz'           => array( 'application/x-xz', array( 'application/x-xz' ) ),
		'txz'          => array( 'application/x-xz', array( 'application/x-xz' ) ),
		'dmg'          => array( 'application/x-apple-diskimage', array_merge( $binary, array( 'application/x-apple-diskimage', 'application/zlib', 'application/x-bzip2' ) ) ),
		'sh'           => array( 'application/x-sh', array( 'text/x-shellscript', 'text/x-sh', 'application/x-sh', 'text/plain' ) ),
		'py'           => array( 'text/x-python', array( 'text/x-python', 'text/x-script.python', 'text/x-python3', 'text/plain' ) ),
		'bin'          => array( 'application/octet-stream', $binary ),
		'gbl'          => array( 'application/octet-stream', $binary ),
		'mobileconfig' => array( 'application/x-apple-aspen-config', array( 'application/x-apple-aspen-config', 'application/xml', 'text/xml', 'text/plain', 'application/octet-stream' ) ),
		'svg'          => array( 'image/svg+xml', array( 'image/svg+xml' ) ),
		'json'         => array( 'application/json', array( 'application/json', 'text/plain' ) ),
		'md'           => array( 'text/markdown', array( 'text/markdown', 'text/plain', 'text/x-markdown' ) ),
		'epub'         => array( 'application/epub+zip', array( 'application/epub+zip', 'application/zip' ) ),
		'woff2'        => array( 'font/woff2', array( 'font/woff2', 'application/octet-stream' ) ),
		'gpx'          => array( 'application/gpx+xml', array( 'application/gpx+xml', 'application/xml', 'text/xml', 'text/plain' ) ),
	);
}

ekc_settings_register(
	'media_types',
	array(
		'type'     => 'list',
		'default'  => array( '7z', 'bz2', 'tgz', 'xz', 'txz', 'dmg', 'sh', 'py', 'bin', 'gbl', 'mobileconfig' ),
		'label'    => 'Další typy souborů',
		'help'     => 'Přípona, případně přípona a MIME typ (např. „gpx application/gpx+xml“). Rizikové typy (' . implode( ', ', array_slice( EKC_MIME_RISKY, 0, 12 ) ) . '…) smí nahrát jen administrátor. Typy spustitelné serverem (php, phtml, phar, cgi…) povolit nejde.',
		'section'  => 'Média',
		'sanitize' => 'ekc_mime_sanitize',
	)
);

/**
 * Očištění seznamu z nastavení: malá písmena, platné přípony a MIME, bez zakázaných typů.
 *
 * @param mixed $value Hodnota (řádky nebo pole).
 * @return string[]
 */
function ekc_mime_sanitize( $value ): array {
	$lines = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
	$clean = array();
	foreach ( (array) $lines as $line ) {
		$parts = preg_split( '/[\s=]+/', strtolower( trim( (string) $line ) ), 2 );
		$exts  = array_filter( explode( '|', ltrim( (string) ( $parts[0] ?? '' ), '.' ) ), static fn( $ext ) => (bool) preg_match( '/^[a-z0-9]{1,15}$/', $ext ) && ! in_array( $ext, EKC_MIME_NEVER, true ) );
		if ( ! $exts ) {
			continue;
		}
		$mime = trim( (string) ( $parts[1] ?? '' ) );
		$mime = preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $mime ) ? $mime : '';
		$clean[] = implode( '|', $exts ) . ( '' !== $mime ? ' ' . $mime : '' );
	}
	return array_values( array_unique( $clean ) );
}

/**
 * Typy ze seznamu v nastavení.
 *
 * @return array<string,array{mime:string,real:string[],risky:bool}> přípona => údaje
 */
function ekc_mime_types(): array {
	static $cache = null;
	$list = (array) ekc_opt( 'media_types' );
	$key  = md5( (string) wp_json_encode( $list ) );
	if ( null !== $cache && $cache[0] === $key ) {
		return $cache[1];
	}
	$known = ekc_mime_known();
	$wp    = wp_get_mime_types();
	$types = array();
	foreach ( ekc_mime_sanitize( $list ) as $line ) {
		$parts = explode( ' ', $line, 2 );
		foreach ( explode( '|', $parts[0] ) as $ext ) {
			$mime = $parts[1] ?? '';
			if ( '' === $mime ) {
				$mime = $known[ $ext ][0] ?? '';
			}
			if ( '' === $mime ) {
				foreach ( $wp as $exts => $wp_mime ) {
					if ( in_array( $ext, explode( '|', $exts ), true ) ) {
						$mime = $wp_mime;
						break;
					}
				}
			}
			$mime          = '' !== $mime ? $mime : 'application/octet-stream';
			$types[ $ext ] = array(
				'mime'  => $mime,
				'real'  => array_values( array_unique( array_merge( array( $mime ), $known[ $ext ][1] ?? array() ) ) ),
				'risky' => in_array( $ext, EKC_MIME_RISKY, true ),
			);
		}
	}
	$cache = array( $key, $types );
	return $types;
}

/**
 * Smí uživatel nahrát typ?
 *
 * @param array             $type Údaje typu.
 * @param int|WP_User|null  $user Uživatel (null = aktuální).
 * @return bool
 */
function ekc_mime_user_can( array $type, $user = null ): bool {
	$cap = $type['risky'] ? 'manage_options' : 'upload_files';
	return null === $user ? current_user_can( $cap ) : user_can( $user, $cap );
}

/**
 * Povolené typy pro nahrání podle oprávnění uživatele.
 *
 * @param array            $mimes Přípony => MIME.
 * @param int|WP_User|null $user  Uživatel.
 * @return array
 */
function ekc_mime_upload_mimes( $mimes, $user = null ) {
	$mimes = (array) $mimes;
	foreach ( ekc_mime_types() as $ext => $type ) {
		if ( ekc_mime_user_can( $type, $user ) ) {
			$mimes[ $ext ] = $type['mime'];
		}
	}
	foreach ( array_keys( $mimes ) as $exts ) {
		if ( array_intersect( explode( '|', (string) $exts ), EKC_MIME_NEVER ) ) {
			unset( $mimes[ $exts ] );
		}
	}
	return $mimes;
}
add_filter( 'upload_mimes', 'ekc_mime_upload_mimes', 20, 2 );

/**
 * Kontrola obsahu: WordPress odmítne soubor, když se jeho MIME podle fileinfo liší od očekávaného
 * (skripty a binárky mívají „nečekaný“ MIME). Běžné typy ze seznamu se přijmou podle přípony,
 * rizikové jen se skutečným MIME z očekávaných hodnot.
 *
 * @param array       $data      ext, type, proper_filename.
 * @param string      $file      Cesta k dočasnému souboru.
 * @param string      $filename  Název souboru.
 * @param array|null  $mimes     Povolené typy.
 * @param string|bool $real_mime Skutečný MIME (fileinfo), WP 5.1+.
 * @return array
 */
function ekc_mime_check_filetype( $data, $file, $filename, $mimes = null, $real_mime = false ) {
	if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
		return $data;
	}
	$ext   = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
	$types = ekc_mime_types();
	if ( ! isset( $types[ $ext ] ) || ! ekc_mime_user_can( $types[ $ext ] ) ) {
		return $data;
	}
	$type = $types[ $ext ];
	if ( $type['risky'] ) {
		if ( ! $real_mime && is_file( (string) $file ) && extension_loaded( 'fileinfo' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$real_mime = $finfo ? finfo_file( $finfo, (string) $file ) : false;
		}
		if ( ! $real_mime || ! in_array( (string) $real_mime, $type['real'], true ) ) {
			return $data;
		}
	}
	return array(
		'ext'             => $ext,
		'type'            => $type['mime'],
		'proper_filename' => $data['proper_filename'] ?? false,
	);
}
add_filter( 'wp_check_filetype_and_ext', 'ekc_mime_check_filetype', 10, 5 );
