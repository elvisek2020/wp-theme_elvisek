<?php
/**
 * EKC module: maint-media
 * Version: 1.0.0
 * Requires: common
 * Replaces: media-cleaner, dnui-delete-not-used-image-wordpress, broken-link-checker
 * Summary: Údržba médií: největší soubory, nepoužité obrázky do karantény a rozbité interní odkazy.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Sekce Média a odkazy v Nástroje → Údržba webu. Všechno se počítá až na kliknutí.
 *
 * Nepoužité obrázky: nejdřív se sestaví rejstřík použití (ID a cesty souborů) z obsahu všech
 * typů kromě revizí, z metadat (_thumbnail_id, file), z voleb widget_* a theme_mods_*,
 * ikony a loga webu a ze souborů aktivní šablony. Pak se po dávkách projdou VŠECHNY obrázky
 * v knihovně (pokračuje samo). Obrázek je použitý, když se najde jeho ID (wp-image-N, "id":N
 * v blocích, [gallery ids], data-id, attachment_id) nebo cesta kteréhokoli jeho souboru
 * (i zmenšeniny a originálu). Vyloučené složky (ekc_media_excluded_dirs) se nenabízejí.
 * Vybrané obrázky jdou do karantény: soubory se přesunou, záznam v databázi se uloží do JSON
 * vedle nich a pak odebere. Před přesunem se použití ověří znovu na serveru.
 *
 * Rozšíření pro projektové funkce (inc/site-*.php):
 *  - ekc_media_used_ids( int[] $ids ) – další použitá ID (např. vlastní pole),
 *  - ekc_media_used_meta_keys( string[] $keys ) – meta klíče, jejichž hodnota je ID přílohy,
 *  - ekc_media_used_options( string[] $patterns ) – volby (LIKE), ve kterých hledat ID a cesty,
 *  - ekc_media_used_text( string[] $texts ) – další texty k prohledání.
 */

const EKC_MEDIA_SCAN_TTL = DAY_IN_SECONDS;

/* =========================================================================
 * Rejstřík použití
 * ====================================================================== */

/**
 * Cesta k uploads v adrese relativně ke kořeni webu (např. wp-content/uploads/).
 *
 * @return string
 */
function ekc_media_uploads_rel(): string {
	$base = (string) wp_parse_url( (string) wp_get_upload_dir()['baseurl'], PHP_URL_PATH );
	return trim( $base, '/' ) . '/';
}

/**
 * Klíč souboru pro porovnání: cesta v uploads bez přípony, rozměrů zmenšeniny a „-scaled“.
 *
 * @param string $rel Cesta relativně k uploads (např. 2019/05/fotka-300x200.jpg).
 * @return string
 */
function ekc_media_stem( string $rel ): string {
	$rel = rawurldecode( (string) preg_replace( '/[?#].*$/', '', $rel ) );
	$rel = (string) preg_replace( '/\.[a-z0-9]{2,5}$/i', '', ltrim( $rel, '/' ) );
	$rel = (string) preg_replace( '/-\d+x\d+$/', '', $rel );
	$rel = (string) preg_replace( '/-scaled$/', '', $rel );
	return strtolower( $rel );
}

/**
 * Prázdný rejstřík.
 *
 * @return array{ids:array<int,bool>,stems:array<string,bool>}
 */
function ekc_media_index_new(): array {
	return array(
		'ids'   => array(),
		'stems' => array(),
	);
}

/**
 * Najde v textu ID příloh a cesty souborů a přidá je do rejstříku.
 *
 * @param array  $index Rejstřík (upraví se).
 * @param string $text  Text (HTML, JSON bloků, serializovaná volba, CSS…).
 */
function ekc_media_index_text( array &$index, string $text ): void {
	if ( '' === $text ) {
		return;
	}
	$text = str_replace( '\\/', '/', $text ); // JSON
	$ids  = array();
	$id_patterns = array(
		'/wp-image-(\d+)/',
		'/"(?:id|mediaId|imageId|attachmentId|attachment_id)"\s*:\s*"?(\d+)/i',
		'/data-id=["\']?(\d+)/i',
		'/attachment_id=(\d+)/i',
	);
	foreach ( $id_patterns as $pattern ) {
		if ( preg_match_all( $pattern, $text, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
	}
	// [gallery ids="1,2,3"] a starší blok galerie "ids":[1,2,3].
	foreach ( array( '/\bids\s*=\s*["\']?([\d,\s]+)/i', '/"ids"\s*:\s*\[([\d,\s"]+)\]/' ) as $pattern ) {
		if ( preg_match_all( $pattern, $text, $m ) ) {
			foreach ( $m[1] as $list ) {
				$ids = array_merge( $ids, preg_split( '/[^\d]+/', $list ) );
			}
		}
	}
	foreach ( $ids as $id ) {
		if ( (int) $id > 0 ) {
			$index['ids'][ (int) $id ] = true;
		}
	}

	// Cesty: …/wp-content/uploads/2019/05/soubor.jpg a samotné 2019/05/soubor.jpg (volby, metadata).
	$rel   = preg_quote( ekc_media_uploads_rel(), '#' );
	$chars = '[^"\'\s<>()\[\]\\\\]+';
	foreach ( array( '#' . $rel . '(' . $chars . ')#i', '#(?<![\w/.-])(\d{4}/\d{2}/' . $chars . '\.[a-z0-9]{2,5})#i' ) as $pattern ) {
		if ( preg_match_all( $pattern, $text, $m ) ) {
			foreach ( $m[1] as $path ) {
				$stem = ekc_media_stem( $path );
				if ( '' !== $stem ) {
					$index['stems'][ $stem ] = true;
				}
			}
		}
	}
}

/**
 * Projde hodnotu volby (pole z unserialize) a posbírá čísla v klíčích typu id, logo, image, icon.
 *
 * @param array $index Rejstřík (upraví se).
 * @param mixed $value Hodnota.
 * @param bool  $top   Nejvyšší úroveň (číselné hodnoty theme_mods jsou ID).
 */
function ekc_media_index_value( array &$index, $value, bool $top = true ): void {
	if ( ! is_array( $value ) && ! is_object( $value ) ) {
		return;
	}
	foreach ( (array) $value as $key => $item ) {
		if ( is_array( $item ) || is_object( $item ) ) {
			ekc_media_index_value( $index, $item, false );
			continue;
		}
		$id_key = is_string( $key ) && preg_match( '/(^|_)(id|ids|logo|image|icon|media|attachment_id)$/i', $key );
		if ( is_numeric( $item ) && (int) $item > 0 && ( $top || $id_key ) ) {
			$index['ids'][ (int) $item ] = true;
		} elseif ( $id_key && is_string( $item ) && preg_match( '/^[\d,\s]+$/', $item ) ) {
			foreach ( preg_split( '/[^\d]+/', $item ) as $id ) {
				if ( (int) $id > 0 ) {
					$index['ids'][ (int) $id ] = true;
				}
			}
		}
	}
}

/**
 * Soubory aktivní šablony (a nadřazené), ve kterých může být obrázek zadaný napevno.
 *
 * @return string[]
 */
function ekc_media_theme_files(): array {
	$files = array();
	foreach ( array_unique( array( get_template_directory(), get_stylesheet_directory() ) ) as $dir ) {
		if ( ! is_dir( $dir ) ) {
			continue;
		}
		try {
			$it = new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator(
					new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
					static fn( $file ) => ! ( $file->isDir() && in_array( $file->getFilename(), array( 'node_modules', 'vendor', '.git' ), true ) )
				)
			);
			$it->setMaxDepth( 3 );
			foreach ( $it as $file ) {
				if ( $file->isFile() && $file->getSize() < 2 * MB_IN_BYTES && preg_match( '/\.(php|css|js|json|html|svg)$/i', $file->getFilename() ) ) {
					$files[] = $file->getPathname();
				}
			}
		} catch ( Throwable $e ) {
			continue;
		}
	}
	return $files;
}

/**
 * Sestaví rejstřík použití z celého webu (po dávkách, kvůli paměti).
 *
 * @return array{ids:array<int,bool>,stems:array<string,bool>}
 */
function ekc_media_used_index(): array {
	global $wpdb;
	$index = ekc_media_index_new();

	// 1) Obsah všech typů kromě příloh a revizí (i šablony bloků, vlastní CSS, opakovaně použitelné bloky).
	$last = 0;
	do {
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID > %d AND post_type NOT IN ('attachment','revision') AND post_status NOT IN ('trash','auto-draft') ORDER BY ID LIMIT 300",
				$last
			)
		);
		foreach ( $rows as $row ) {
			$last = (int) $row->ID;
			ekc_media_index_text( $index, $row->post_content . "\n" . $row->post_excerpt );
		}
	} while ( count( $rows ) === 300 );

	// 2) Metadata, jejichž hodnota je ID přílohy (náhled, soubor dokumentu…).
	$keys = array_values( array_filter( array_map( 'strval', (array) apply_filters( 'ekc_media_used_meta_keys', array( '_thumbnail_id', 'file' ) ) ) ) );
	if ( $keys ) {
		$in = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- jen zástupné %s
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($in)", $keys ) ) as $value ) {
			foreach ( preg_split( '/[^\d]+/', (string) $value ) as $id ) {
				if ( (int) $id > 0 ) {
					$index['ids'][ (int) $id ] = true;
				}
			}
		}
	}

	// 3) Volby: widgety, nastavení šablon (i neaktivních), ikona a logo webu.
	foreach ( array( 'site_icon', 'site_logo' ) as $option ) {
		$id = (int) get_option( $option );
		if ( $id ) {
			$index['ids'][ $id ] = true;
		}
	}
	$patterns = (array) apply_filters( 'ekc_media_used_options', array( 'widget\_%', 'theme\_mods\_%' ) );
	foreach ( $patterns as $pattern ) {
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s", (string) $pattern ) ) as $row ) {
			ekc_media_index_text( $index, (string) $row->option_value );
			ekc_media_index_value( $index, maybe_unserialize( $row->option_value ) );
		}
	}

	// 4) Soubory aktivní šablony (logo nebo pozadí zadané napevno).
	foreach ( ekc_media_theme_files() as $file ) {
		ekc_media_index_text( $index, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	// 5) Projektová rozšíření.
	foreach ( (array) apply_filters( 'ekc_media_used_text', array() ) as $text ) {
		ekc_media_index_text( $index, (string) $text );
	}
	foreach ( (array) apply_filters( 'ekc_media_used_ids', array_keys( $index['ids'] ) ) as $id ) {
		if ( (int) $id > 0 ) {
			$index['ids'][ (int) $id ] = true;
		}
	}
	return $index;
}

/**
 * Klíče všech souborů přílohy (soubor, originál, zmenšeniny, zálohy po úpravě v editoru).
 *
 * @param int $id ID přílohy.
 * @return string[]
 */
function ekc_media_attachment_stems( int $id ): array {
	$rel   = (string) get_post_meta( $id, '_wp_attached_file', true );
	$dir   = dirname( $rel );
	$dir   = '.' === $dir ? '' : $dir . '/';
	$meta  = wp_get_attachment_metadata( $id );
	$meta  = is_array( $meta ) ? $meta : array();
	$names = array( basename( $rel ) );
	if ( ! empty( $meta['original_image'] ) ) {
		$names[] = (string) $meta['original_image'];
	}
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		if ( is_array( $size ) && ! empty( $size['file'] ) ) {
			$names[] = (string) $size['file'];
		}
	}
	foreach ( (array) get_post_meta( $id, '_wp_attachment_backup_sizes', true ) as $size ) {
		if ( is_array( $size ) && ! empty( $size['file'] ) ) {
			$names[] = (string) $size['file'];
		}
	}
	$stems = array();
	foreach ( $names as $name ) {
		if ( '' !== $name ) {
			$stems[] = ekc_media_stem( $dir . $name );
		}
	}
	return array_values( array_unique( array_filter( $stems ) ) );
}

/**
 * Je příloha použitá?
 *
 * @param int   $id    ID přílohy.
 * @param array $index Rejstřík z ekc_media_used_index().
 * @return bool
 */
function ekc_media_is_used( int $id, array $index ): bool {
	if ( isset( $index['ids'][ $id ] ) ) {
		return true;
	}
	foreach ( ekc_media_attachment_stems( $id ) as $stem ) {
		if ( isset( $index['stems'][ $stem ] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Velikost všech souborů přílohy.
 *
 * @param int $id ID přílohy.
 * @return int
 */
function ekc_media_bytes( int $id ): int {
	$bytes = 0;
	foreach ( ekc_attachment_files( $id ) as $file ) {
		$bytes += (int) filesize( $file );
	}
	return $bytes;
}

/* =========================================================================
 * Nepoužité obrázky: průchod po dávkách a karanténa
 * ====================================================================== */

/**
 * Stav hledání (transient ekc_media_scan).
 *
 * @return array|null
 */
function ekc_media_scan_state(): ?array {
	$state = get_transient( 'ekc_media_scan' );
	return is_array( $state ) && isset( $state['index'], $state['unused'] ) ? $state : null;
}

/**
 * Úloha Údržby: dávka hledání nepoužitých obrázků (item start = začít znovu).
 *
 * @param string $item start|continue.
 * @return array
 */
function ekc_media_task_scan( string $item ): array {
	global $wpdb;
	$start = time();
	$state = ekc_media_scan_state();
	if ( 'start' === $item || ! $state || ! empty( $state['done'] ) ) {
		$state = array(
			'index'   => ekc_media_used_index(),
			'last'    => 0,
			'unused'  => array(),
			'checked' => 0,
			'total'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" ),
			'done'    => false,
			'at'      => time(),
		);
	}
	$budget = (int) apply_filters( 'ekc_media_scan_seconds', 20 );
	do {
		$ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE %s AND ID > %d ORDER BY ID LIMIT 200",
					'image/%',
					(int) $state['last']
				)
			)
		);
		foreach ( $ids as $id ) {
			$state['last'] = $id;
			++$state['checked'];
			if ( ! ekc_media_excluded( $id ) && ! ekc_media_is_used( $id, $state['index'] ) ) {
				$state['unused'][] = $id;
			}
		}
	} while ( $ids && time() - $start < $budget );

	$state['done'] = ! $ids;
	set_transient( 'ekc_media_scan', $state, EKC_MEDIA_SCAN_TTL );

	$msg = $state['done']
		? sprintf( 'Hledání dokončeno: z %1$d obrázků %2$s.', (int) $state['checked'], ekc_plural( count( $state['unused'] ), 'je %d nepoužitý', 'jsou %d nepoužité', 'je %d nepoužitých' ) )
		: sprintf( 'Prohledáno %1$d z %2$d obrázků, pokračuje se automaticky.', (int) $state['checked'], (int) $state['total'] );
	return array(
		'message' => $msg,
		'type'    => 'info',
		'mark'    => false,
		'args'    => array( 'ekc_media_auto' => $state['done'] ? false : 1 ),
	);
}

/**
 * Přesune přílohu do karantény: soubory do {karanténa}/{datum}/media/…, záznam do JSON, pak smaže řádek z databáze.
 *
 * @param int   $id     ID přílohy.
 * @param array $backup Záloha záznamů (doplní se).
 * @return int|WP_Error Uvolněné bajty.
 */
function ekc_media_quarantine_attachment( int $id, array &$backup ) {
	global $wpdb;
	$files = ekc_attachment_files( $id );
	$main  = (string) get_attached_file( $id );
	foreach ( (array) get_post_meta( $id, '_wp_attachment_backup_sizes', true ) as $size ) {
		if ( is_array( $size ) && ! empty( $size['file'] ) && is_file( dirname( $main ) . '/' . $size['file'] ) ) {
			$files[] = dirname( $main ) . '/' . $size['file'];
		}
	}
	$files = array_values( array_unique( $files ) );

	$backup[ $id ] = array(
		'post'  => $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A ),
		'meta'  => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $id ), ARRAY_A ),
		'files' => array(),
	);
	$bytes = 0;
	foreach ( $files as $file ) {
		$size   = (int) filesize( $file );
		$target = ekc_quarantine_move( $file, 'media' );
		if ( false === $target ) {
			// Vrátit už přesunuté soubory, příloha zůstane beze změny.
			foreach ( $backup[ $id ]['files'] as $from => $to ) {
				@rename( $to, $from ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			}
			unset( $backup[ $id ] );
			return new WP_Error( 'move', 'Soubor se nepodařilo přesunout: ' . wp_basename( $file ) );
		}
		$backup[ $id ]['files'][ $file ] = $target;
		$bytes                          += $size;
	}
	// Soubory už jsou v karanténě, wp_delete_attachment odebere jen záznamy z databáze.
	if ( ! wp_delete_attachment( $id, true ) ) {
		return new WP_Error( 'delete', 'Záznam přílohy se nepodařilo odebrat (soubory jsou v karanténě).' );
	}
	return $bytes;
}

/**
 * Úloha Údržby: vybrané nepoužité obrázky do karantény (použití se ověří znovu).
 *
 * @param string $item  Nepoužito.
 * @param array  $items ID příloh.
 * @return array
 */
function ekc_media_task_quarantine( string $item, array $items ): array {
	$state = ekc_media_scan_state();
	$want  = array_values( array_unique( array_filter( array_map( 'intval', $items ) ) ) );
	if ( ! $want || ! $state ) {
		return array(
			'message' => $state ? 'Nic nebylo vybráno.' : 'Výsledek hledání vypršel, spusťte hledání znovu.',
			'type'    => 'warning',
			'mark'    => false,
		);
	}
	$want  = array_intersect( $want, array_map( 'intval', $state['unused'] ) );
	$index = ekc_media_used_index(); // čerstvé ověření
	$dir   = ekc_quarantine_dir( wp_date( 'Y-m-d' ) . '/media' );
	if ( is_wp_error( $dir ) ) {
		return array(
			'message' => $dir->get_error_message(),
			'type'    => 'error',
		);
	}

	$json    = $dir . 'prilohy-' . gmdate( 'His' ) . '-' . wp_generate_password( 4, false ) . '.json';
	$backup  = array();
	$moved   = array();
	$skipped = 0;
	$errors  = array();
	$bytes   = 0;
	$start   = time();
	foreach ( $want as $id ) {
		if ( time() - $start > 200 ) {
			break;
		}
		$is_image = 'attachment' === get_post_type( $id ) && str_starts_with( (string) get_post_mime_type( $id ), 'image/' );
		if ( ! $is_image || ekc_media_excluded( $id ) || ekc_media_is_used( $id, $index ) || ! current_user_can( 'delete_post', $id ) ) {
			++$skipped;
			continue;
		}
		$result = ekc_media_quarantine_attachment( $id, $backup );
		if ( is_wp_error( $result ) ) {
			$errors[] = 'ID ' . $id . ': ' . $result->get_error_message();
			continue;
		}
		$moved[] = $id;
		$bytes  += $result;
		// Záloha záznamů průběžně (kdyby PHP spadlo uprostřed dávky).
		file_put_contents( $json, (string) wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	$state['unused'] = array_values( array_diff( array_map( 'intval', $state['unused'] ), $moved ) );
	set_transient( 'ekc_media_scan', $state, EKC_MEDIA_SCAN_TTL );

	$msg = sprintf( 'Do karantény přesunuto: %1$s (%2$s). Záznamy z databáze jsou zálohované v JSON vedle souborů.', ekc_plural( count( $moved ), '%d obrázek', '%d obrázky', '%d obrázků' ), ekc_size( $bytes ) );
	if ( $skipped ) {
		$msg .= ' ' . sprintf( 'Vynecháno %d (mezitím použité, vyloučené nebo bez oprávnění).', $skipped );
	}
	if ( $errors ) {
		$msg .= ' Chyby: ' . implode( '; ', array_slice( $errors, 0, 5 ) ) . '.';
	}
	return array(
		'message' => $msg,
		'type'    => $errors ? 'warning' : 'success',
	);
}

/* =========================================================================
 * Největší soubory a rozbité odkazy
 * ====================================================================== */

/**
 * Největší přílohy (součet všech jejich souborů).
 *
 * @param int $limit Počet.
 * @return array<int,array{0:int,1:int,2:string}> [ID, bajty, název]
 */
function ekc_media_largest( int $limit = 20 ): array {
	global $wpdb;
	$rows = array();
	$last = 0;
	do {
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID > %d ORDER BY ID LIMIT 500", $last ) ) );
		foreach ( $ids as $id ) {
			$last   = $id;
			$file   = (string) get_attached_file( $id );
			$rows[] = array( $id, ekc_media_bytes( $id ), '' !== $file ? wp_basename( $file ) : '?' );
		}
		usort( $rows, static fn( $a, $b ) => $b[1] <=> $a[1] );
		$rows = array_slice( $rows, 0, $limit );
	} while ( count( $ids ) === 500 );
	return $rows;
}

/**
 * Cesty v adrese, které nejsou obsah, ale platí (archivy rubrik, štítků a typů obsahu, jazyky).
 *
 * @return array<string,bool>
 */
function ekc_media_known_paths(): array {
	$paths = array();
	$add   = static function ( $url ) use ( &$paths ): void {
		if ( is_string( $url ) && '' !== $url ) {
			$paths[ untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ] = true;
		}
	};
	$taxes = get_taxonomies( array( 'public' => true ) );
	$terms = get_terms(
		array(
			'taxonomy'   => array_values( $taxes ),
			'hide_empty' => false,
			'lang'       => '',
		)
	);
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$link = get_term_link( $term );
		$add( is_wp_error( $link ) ? '' : $link );
	}
	foreach ( get_post_types( array( 'has_archive' => true ) ) as $type ) {
		$add( get_post_type_archive_link( $type ) );
	}
	if ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_home_url' ) ) {
		foreach ( (array) pll_languages_list() as $slug ) {
			$add( pll_home_url( (string) $slug ) );
		}
	}
	return $paths;
}

/**
 * Interní odkazy a obrázky v publikovaném obsahu, které nikam nevedou.
 *
 * @return array<int,array{0:int,1:string,2:string}> [ID, titulek, adresa]
 */
function ekc_media_links_broken(): array {
	global $wpdb;
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$host      = preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$home_path = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
	$cont_path = untrailingslashit( (string) wp_parse_url( content_url(), PHP_URL_PATH ) );
	$root      = untrailingslashit( get_home_path() );
	$known     = ekc_media_known_paths();
	$types     = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
	$in        = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$out       = array();
	$seen      = array();
	$last      = 0;
	do {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- jen zástupné %s
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ($in) AND post_status = 'publish' ORDER BY ID LIMIT 200", array_merge( array( $last ), $types ) ) );
		foreach ( $rows as $row ) {
			$last = (int) $row->ID;
			if ( ! preg_match_all( '#(?:href|src)=["\']([^"\']+)["\']#i', (string) $row->post_content, $m ) ) {
				continue;
			}
			foreach ( array_unique( $m[1] ) as $url ) {
				$parts = wp_parse_url( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
				if ( ! is_array( $parts ) ) {
					continue;
				}
				$link = preg_replace( '#^www\.#', '', (string) ( $parts['host'] ?? '' ) );
				$path = rawurldecode( (string) ( $parts['path'] ?? '' ) );
				if ( '' === $link && str_starts_with( $path, '/' ) ) {
					$link = $host; // relativní odkaz /kontakt/
				}
				if ( $link !== $host || '' === $path || '/' === $path ) {
					continue;
				}
				$path = untrailingslashit( $path );
				$key  = $row->ID . '|' . $path;
				if ( isset( $seen[ $key ] ) || count( $out ) >= 500 ) {
					continue;
				}
				$seen[ $key ] = true;
				$rel = '' !== $home_path && str_starts_with( $path . '/', $home_path . '/' ) ? substr( $path, strlen( $home_path ) ) : $path;
				if ( '' === $rel ) {
					continue; // titulka
				}
				if ( str_starts_with( $path, $cont_path . '/' ) ) {
					$ok = file_exists( WP_CONTENT_DIR . substr( $path, strlen( $cont_path ) ) );
				} elseif ( preg_match( '#\.(?!html?$|php$)[a-z0-9]{2,5}$#i', $path ) ) {
					$ok = file_exists( $root . $rel );
				} elseif ( isset( $known[ $path ] ) || preg_match( '#^/(feed|wp-admin|wp-login\.php|wp-json|comments/feed)(/|$)|/(feed|page/\d+)$#', $rel ) || preg_match( '#^/\d{4}(/\d{2}(/\d{2})?)?$#', $rel ) ) {
					$ok = true;
				} else {
					$ok = url_to_postid( home_url( $rel . '/' ) ) > 0 || url_to_postid( home_url( $rel ) ) > 0 || get_page_by_path( ltrim( $rel, '/' ) );
				}
				if ( ! $ok ) {
					$out[] = array( (int) $row->ID, (string) $row->post_title, (string) $url );
				}
			}
		}
	} while ( count( $rows ) === 200 );
	return $out;
}

/* =========================================================================
 * Sekce Údržby
 * ====================================================================== */

/**
 * Výpis nepoužitých obrázků s výběrem.
 *
 * @param string $id    ID sekce.
 * @param array  $state Stav hledání.
 */
function ekc_media_render_unused( string $id, array $state ): void {
	$list = array_values( array_filter( array_map( 'intval', $state['unused'] ), static fn( $aid ) => 'attachment' === get_post_type( $aid ) ) );
	if ( ! $list ) {
		echo '<p>' . ekc_status_dot( 'ok' ) . ' ' . esc_html( sprintf( 'Všechny obrázky jsou použité (prohledáno %d, %s).', (int) $state['checked'], ekc_datetime( (int) $state['at'] ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	$shown = array_slice( $list, 0, 500 );
	$sizes = array();
	foreach ( $shown as $aid ) {
		$sizes[ $aid ] = ekc_media_bytes( $aid );
	}
	printf(
		'<p><strong>%1$s</strong> (%2$s%3$s), které nejsou použité v obsahu, galerii, náhledu, widgetu, nastavení ani souborech šablony (hledání %4$s). Mohou být odkazované zvenku – zkontrolujte je. Vybrané se přesunou do karantény, nic se nemaže natrvalo.</p>',
		esc_html( ekc_plural( count( $list ), 'Nalezen %d obrázek', 'Nalezeny %d obrázky', 'Nalezeno %d obrázků' ) ),
		esc_html( ekc_size( array_sum( $sizes ) ) ),
		count( $list ) > count( $shown ) ? esc_html( ' u prvních ' . count( $shown ) ) : '',
		esc_html( ekc_datetime( (int) $state['at'] ) )
	);
	printf(
		'<form method="post" action="%1$s" id="ekc-media-unused" onsubmit="var n=this.querySelectorAll(\'input[name=&quot;items[]&quot;]:checked\').length;return n>0&&window.confirm(\'Přesunout \'+n+\' obrázků do karantény?\');">',
		esc_url( admin_url( 'admin-post.php' ) )
	);
	ekc_maintenance_fields( $id, 'quarantine' );
	echo '<p style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><button type="button" class="button" data-ekc-all="1">Vybrat vše</button><button type="button" class="button" data-ekc-all="0">Zrušit výběr</button><span data-ekc-count style="color:#646970"></span><button type="submit" class="button button-primary">Přesunout vybrané do karantény</button></p>';
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px">';
	foreach ( $shown as $aid ) {
		printf(
			'<label style="display:block;font-size:11px;word-break:break-all;border:1px solid #dcdcde;border-radius:6px;padding:6px;background:#fff;cursor:pointer"><span style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><input type="checkbox" name="items[]" value="%1$d"><span style="color:#646970">%2$s</span></span>%3$s<br>%4$s <a href="%5$s" target="_blank" rel="noopener">upravit</a></label>',
			(int) $aid,
			esc_html( ekc_size( $sizes[ $aid ] ) ),
			wp_get_attachment_image( $aid, 'thumbnail', false, array( 'style' => 'width:100%;height:90px;object-fit:cover;border-radius:4px', 'loading' => 'lazy' ) ),
			esc_html( (string) get_post_meta( $aid, '_wp_attached_file', true ) ),
			esc_url( (string) get_edit_post_link( $aid ) )
		);
	}
	echo '</div></form>';
	echo "<script>(function(){var f=document.getElementById('ekc-media-unused');if(!f)return;var b=[].slice.call(f.querySelectorAll('input[name=\"items[]\"]'));var c=f.querySelector('[data-ekc-count]');var s=function(){c.textContent='Vybráno '+b.filter(function(x){return x.checked;}).length+' z '+b.length;};f.addEventListener('change',s);[].forEach.call(f.querySelectorAll('[data-ekc-all]'),function(t){t.addEventListener('click',function(){b.forEach(function(x){x.checked=t.getAttribute('data-ekc-all')==='1';});s();});});s();})();</script>";
}

/**
 * Sekce Údržby: Média a odkazy.
 *
 * @param string $id ID sekce.
 */
function ekc_media_render( string $id ): void {
	echo '<p class="description">Kontroly projdou celý web, spouštějí se až tlačítkem a mohou chvíli trvat.</p><p>';
	ekc_maintenance_show_link( $id, 'largest', 'Největší soubory' );
	echo ' ';
	ekc_maintenance_show_link( $id, 'links', 'Rozbité interní odkazy' );
	echo '</p>';

	if ( ekc_maintenance_showing( 'largest' ) ) {
		$rows = ekc_media_largest();
		if ( ! $rows ) {
			echo '<p>Knihovna médií je prázdná.</p>';
		} else {
			echo '<h3>Největší soubory</h3><table class="widefat striped" style="max-width:860px"><tbody>';
			foreach ( $rows as $row ) {
				printf( '<tr><td><a href="%1$s">%2$s</a></td><td style="text-align:right">%3$s</td></tr>', esc_url( (string) get_edit_post_link( $row[0] ) ), esc_html( $row[2] ), esc_html( ekc_size( $row[1] ) ) );
			}
			echo '</tbody></table>';
		}
	}
	if ( ekc_maintenance_showing( 'links' ) ) {
		$rows = ekc_media_links_broken();
		echo '<h3>Rozbité interní odkazy</h3>';
		if ( ! $rows ) {
			echo '<p>' . ekc_status_dot( 'ok' ) . ' V publikovaném obsahu nejsou rozbité interní odkazy ani obrázky.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Obsah</th><th>Odkaz</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				printf( '<tr><td><a href="%1$s">%2$s</a></td><td><code style="word-break:break-all">%3$s</code></td></tr>', esc_url( (string) get_edit_post_link( $row[0] ) ), esc_html( '' !== $row[1] ? $row[1] : '#' . $row[0] ), esc_html( $row[2] ) );
			}
			echo '</tbody></table>';
		}
	}

	echo '<h3>Nepoužité obrázky</h3>';
	$state = ekc_media_scan_state();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen zobrazení
	$auto = $state && empty( $state['done'] ) && ! empty( $_GET['ekc_media_auto'] );
	if ( $auto ) {
		echo '<div class="notice notice-info inline"><p><span class="spinner is-active" style="float:none;margin:0 6px 0 0"></span>' . esc_html( sprintf( 'Hledání pokračuje: prohledáno %1$d z %2$d obrázků – nechte stránku otevřenou.', (int) $state['checked'], (int) $state['total'] ) ) . ' <a href="' . esc_url( ekc_maintenance_url( array(), $id ) ) . '">Zastavit</a></p></div>';
		echo '<form id="ekc-media-continue" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		ekc_maintenance_fields( $id, 'scan' );
		echo '<input type="hidden" name="item" value="continue"></form><script>window.setTimeout(function(){document.getElementById("ekc-media-continue").submit();},1000);</script>';
		return;
	}
	ekc_maintenance_button( $id, 'scan', $state ? 'Hledat znovu' : 'Najít nepoužité obrázky', '', ! $state, 'start' );
	if ( $state && empty( $state['done'] ) ) {
		ekc_maintenance_button( $id, 'scan', 'Pokračovat v hledání', '', true, 'continue' );
		echo '<p>' . esc_html( sprintf( 'Hledání je přerušené: prohledáno %1$d z %2$d obrázků.', (int) $state['checked'], (int) $state['total'] ) ) . '</p>';
		return;
	}
	if ( $state ) {
		ekc_media_render_unused( $id, $state );
	}
}

ekc_maintenance_section(
	'media',
	array(
		'title'  => 'Média a odkazy',
		'order'  => 30,
		'render' => 'ekc_media_render',
		'tasks'  => array(
			'scan'       => 'ekc_media_task_scan',
			'quarantine' => 'ekc_media_task_quarantine',
		),
	)
);
