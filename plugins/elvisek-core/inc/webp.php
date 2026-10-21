<?php
/**
 * EKC module: webp
 * Version: 1.0.0
 * Requires: common
 * Replaces: webp-express, webp-converter-for-media, converter-for-media, ewww-image-optimizer, imagify
 * Summary: Nahrané JPG/PNG rovnou jako WebP, hromadný převod starších obrázků v Údržbě a 301 ze starých adres.
 */

defined( 'ABSPATH' ) || exit;

/*
 * 1) Nahrání: JPG/PNG se převede na WebP (originál se neukládá), fotka se otočí podle EXIF,
 *    zmenšeniny vznikají jako WebP. GIF, SVG a soubory ve vyloučených složkách
 *    (ekc_media_excluded_dirs – přílohy z formulářů) beze změny. Filtr wp_handle_upload pokrývá
 *    i stažení z URL, REST API a WP-CLI (wp_handle_sideload volá stejný filtr).
 * 2) Údržba → Převod na WebP: starší JPG/PNG natrvalo na WebP – všechny velikosti, metadata,
 *    odkazy v obsahu. Fotky nad big_image_size_threshold se zmenší, nezmenšený originál
 *    (original_image) se nepřevádí. Původní soubory jdou do karantény. Dávky podle času
 *    (filtr ekc_webp_batch_seconds) pokračují samy; obrázek, na kterém PHP spadne, dostane
 *    značku _ekc_webp_fail a příště se přeskočí (tlačítko Zkusit znovu ji smaže).
 * 3) Staré adresy .jpg/.png, ke kterým existuje .webp, přesměruje 301 (i když je převod vypnutý).
 */

ekc_settings_register(
	'webp',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Nahrané obrázky jako WebP',
		'help'    => 'JPG a PNG se při nahrání převedou na WebP (menší soubory). Starší obrázky převede Nástroje → Údržba webu.',
		'section' => 'Média',
	)
);

/**
 * Umí server ukládat WebP (GD nebo Imagick)?
 *
 * @return bool
 */
function ekc_webp_supported(): bool {
	static $ok = null;
	if ( null === $ok ) {
		$ok = (bool) wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}
	return $ok;
}

/**
 * Kvalita WebP (filtr ekc_webp_quality).
 *
 * @return int
 */
function ekc_webp_quality(): int {
	return max( 1, min( 100, (int) apply_filters( 'ekc_webp_quality', 82 ) ) );
}

/**
 * Leží soubor ve vyloučené složce uploads?
 *
 * @param string $path Absolutní cesta.
 * @return bool
 */
function ekc_webp_path_excluded( string $path ): bool {
	$base = trailingslashit( wp_normalize_path( (string) wp_get_upload_dir()['basedir'] ) );
	$path = wp_normalize_path( $path );
	if ( ! str_starts_with( $path, $base ) ) {
		return false;
	}
	$rel = substr( $path, strlen( $base ) );
	foreach ( ekc_media_excluded_dirs() as $dir ) {
		if ( str_starts_with( $rel, $dir ) ) {
			return true;
		}
	}
	return false;
}

/* =========================================================================
 * 1) Nahrání
 * ====================================================================== */

/**
 * Převod nahraného JPG/PNG na WebP.
 *
 * @param array  $upload  Výsledek nahrání (file, url, type).
 * @param string $context upload|sideload.
 * @return array
 */
function ekc_webp_upload( $upload, $context = 'upload' ) {
	if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) || ! ekc_opt( 'webp' ) ) {
		return $upload;
	}
	if ( ! in_array( $upload['type'] ?? '', array( 'image/jpeg', 'image/png' ), true ) || ! ekc_webp_supported() || ekc_webp_path_excluded( (string) $upload['file'] ) ) {
		return $upload;
	}
	$file   = (string) $upload['file'];
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return $upload;
	}
	$dir  = dirname( $file );
	$name = (string) preg_replace( '/\.(png|jpe?g)$/i', '.webp', basename( $file ) );
	if ( $name === basename( $file ) ) {
		return $upload;
	}
	// Stejný název s jinou příponou má jen právě nahraný soubor (ten se smaže) → „-1“ jen při skutečné kolizi.
	$stem = substr( $name, 0, -5 );
	$same = array_diff( (array) glob( $dir . '/' . ekc_webp_glob_escape( $stem ) . '.*' ), array( $file ) );
	if ( $same ) {
		$name = wp_unique_filename( $dir, $name );
	}
	$target = $dir . '/' . $name;
	$editor->set_quality( ekc_webp_quality() );
	$editor->maybe_exif_rotate(); // WebP nenese EXIF – otočit hned
	$saved = $editor->save( $target, 'image/webp' );
	// Když převod selže (GD u PNG s paletou zapíše i prázdný soubor), zůstane původní soubor.
	if ( is_wp_error( $saved ) || ! is_file( $target ) || ! filesize( $target ) ) {
		if ( is_file( $target ) ) {
			wp_delete_file( $target );
		}
		return $upload;
	}
	wp_delete_file( $file );
	$upload['file'] = $target;
	$upload['url']  = trailingslashit( dirname( (string) $upload['url'] ) ) . rawurlencode( $name );
	$upload['type'] = 'image/webp';
	return $upload;
}
add_filter( 'wp_handle_upload', 'ekc_webp_upload', 10, 2 );

/**
 * Escapování znaků globu v názvu souboru.
 *
 * @param string $text Text.
 * @return string
 */
function ekc_webp_glob_escape( string $text ): string {
	return (string) preg_replace( '/([*?\[\]])/', '[$1]', $text );
}

/**
 * Zmenšeniny JPG/PNG ve formátu WebP (mimo vyloučené složky).
 *
 * @param array  $formats  Mapování formátů.
 * @param string $filename Soubor.
 * @return array
 */
function ekc_webp_output_format( $formats, $filename = '' ) {
	if ( ekc_opt( 'webp' ) && ekc_webp_supported() && ! ( $filename && ekc_webp_path_excluded( (string) $filename ) ) ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
	}
	return $formats;
}
add_filter( 'image_editor_output_format', 'ekc_webp_output_format', 10, 2 );

/* =========================================================================
 * 2) Hromadný převod (Údržba webu)
 * ====================================================================== */

/**
 * Podmínka SQL pro JPG/PNG přílohy mimo vyloučené složky a bez značky selhání.
 *
 * @return string FROM … WHERE … (aliasy p = posts).
 */
function ekc_webp_pending_sql(): string {
	global $wpdb;
	$where = '';
	foreach ( ekc_media_excluded_dirs() as $dir ) {
		$where .= $wpdb->prepare( ' AND COALESCE(a.meta_value, %s) NOT LIKE %s', '', $wpdb->esc_like( $dir ) . '%' );
	}
	return "FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = '_ekc_webp_fail'
		LEFT JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_wp_attached_file'
		WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/png','image/jpeg') AND f.meta_id IS NULL" . $where;
}

/**
 * ID příloh čekajících na převod (nejnovější první).
 *
 * @param int $limit 0 = všechny.
 * @return int[]
 */
function ekc_webp_pending( int $limit = 0 ): array {
	global $wpdb;
	$sql = 'SELECT p.ID ' . ekc_webp_pending_sql() . ' ORDER BY p.ID DESC' . ( $limit > 0 ? ' LIMIT ' . $limit : '' );
	return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- připraveno v ekc_webp_pending_sql()
}

/**
 * Počet příloh čekajících na převod.
 *
 * @return int
 */
function ekc_webp_pending_count(): int {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) ' . ekc_webp_pending_sql() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Přílohy, jejichž převod selhal: ID => důvod.
 *
 * @return array<int,string>
 */
function ekc_webp_failed(): array {
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_ekc_webp_fail' ORDER BY post_id DESC" ) as $row ) {
		$out[ (int) $row->post_id ] = (string) $row->meta_value;
	}
	return $out;
}

/**
 * Největší rozměr obrázku (jako u nových nahrání – big_image_size_threshold, výchozí 2560 px).
 *
 * @return int 0 = bez zmenšení.
 */
function ekc_webp_max_size(): int {
	return (int) apply_filters( 'big_image_size_threshold', 2560, array( 0, 0 ), '', 0 );
}

/**
 * Soubor s diakritikou v jiném tvaru Unicode (NFD z Macu) přejmenuje na tvar z databáze.
 * Využije modul maint-filenames, když na webu je; jinak jen přes rozšíření intl.
 *
 * @param string $path Očekávaná cesta.
 * @return bool Soubor existuje pod očekávaným názvem.
 */
function ekc_webp_fix_name( string $path ): bool {
	if ( is_file( $path ) ) {
		return true;
	}
	if ( function_exists( 'ekc_filenames_fix_form' ) ) {
		return (bool) ekc_filenames_fix_form( $path );
	}
	if ( ! class_exists( 'Normalizer' ) || ! preg_match( '/[^\x20-\x7e]/', basename( $path ) ) ) {
		return false;
	}
	foreach ( array( Normalizer::FORM_D, Normalizer::FORM_C ) as $form ) {
		$variant = dirname( $path ) . '/' . (string) Normalizer::normalize( basename( $path ), $form );
		if ( $variant !== $path && is_file( $variant ) ) {
			return @rename( $variant, $path ) && is_file( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		}
	}
	return false;
}

/**
 * Převede jednu přílohu natrvalo na WebP: všechny velikosti, metadata, odkazy v obsahu.
 * Původní soubory přesune do karantény. Při chybě nechá přílohu beze změny.
 *
 * @param int $id ID přílohy.
 * @return int|WP_Error Ušetřené bajty.
 */
function ekc_webp_convert( int $id ) {
	global $wpdb;
	$main = (string) get_attached_file( $id );
	$meta = wp_get_attachment_metadata( $id );
	$meta = is_array( $meta ) ? $meta : array();

	// Názvy s diakritikou v jiném tvaru Unicode nejdřív sjednotit s databází.
	if ( '' !== $main ) {
		ekc_webp_fix_name( $main );
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
			if ( is_array( $size ) && ! empty( $size['file'] ) ) {
				ekc_webp_fix_name( dirname( $main ) . '/' . $size['file'] );
			}
		}
		if ( ! empty( $meta['original_image'] ) ) {
			ekc_webp_fix_name( dirname( $main ) . '/' . $meta['original_image'] );
		}
	}

	$rel = (string) get_post_meta( $id, '_wp_attached_file', true );
	if ( '' === $main || ! is_file( $main ) || '' === $rel ) {
		return new WP_Error( 'missing', 'Soubor chybí.' );
	}
	$dir    = dirname( $main );
	$subdir = trim( dirname( $rel ), './' );
	$names  = array( basename( $main ) );
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		if ( is_array( $size ) && ! empty( $size['file'] ) ) {
			$names[] = (string) $size['file'];
		}
	}
	// Nezmenšený originál (vedle „-scaled“) web nepoužívá a jeho načtení může vyčerpat paměť:
	// nepřevádí se, jen se přesune do karantény.
	$original = ! empty( $meta['original_image'] ) && is_file( $dir . '/' . $meta['original_image'] ) ? (string) $meta['original_image'] : '';
	$max_size = ekc_webp_max_size();

	$map     = array(); // starý název => nový
	$created = array();
	$before  = 0;
	$after   = 0;
	foreach ( array_unique( $names ) as $name ) {
		if ( ! preg_match( '/\.(png|jpe?g)$/i', $name ) ) {
			continue; // zmenšenina už je WebP
		}
		$old = $dir . '/' . $name;
		if ( ! is_file( $old ) ) {
			continue;
		}
		$new_name = (string) preg_replace( '/\.(png|jpe?g)$/i', '.webp', $name );
		if ( is_file( $dir . '/' . $new_name ) || in_array( $dir . '/' . $new_name, $created, true ) ) {
			$new_name = wp_unique_filename( $dir, $new_name );
		}
		$new    = $dir . '/' . $new_name;
		$editor = wp_get_image_editor( $old );
		$saved  = is_wp_error( $editor ) ? $editor : null;
		if ( ! $saved ) {
			$editor->set_quality( ekc_webp_quality() );
			$editor->maybe_exif_rotate();
			$dims = $editor->get_size();
			if ( $name === basename( $main ) && $max_size && max( (int) $dims['width'], (int) $dims['height'] ) > $max_size ) {
				$editor->resize( $max_size, $max_size, false ); // jako WordPress u nových nahrání
			}
			$saved = $editor->save( $new, 'image/webp' );
		}
		if ( is_wp_error( $saved ) || ! is_file( $new ) || ! filesize( $new ) ) {
			if ( is_file( $new ) ) {
				wp_delete_file( $new );
			}
			array_map( 'wp_delete_file', $created );
			return new WP_Error( 'convert', 'Převod se nepovedl: ' . $name . ( is_wp_error( $saved ) ? ' (' . $saved->get_error_message() . ')' : '' ) );
		}
		$created[]    = $new;
		$map[ $name ] = $new_name;
		$before      += (int) filesize( $old );
		$after       += (int) filesize( $new );
	}
	if ( ! $map ) {
		if ( preg_match( '/\.webp$/i', $main ) ) { // soubor už je WebP, jen typ v databázi zůstal starý
			$wpdb->update( $wpdb->posts, array( 'post_mime_type' => 'image/webp' ), array( 'ID' => $id ) );
			clean_post_cache( $id );
			return 0;
		}
		return new WP_Error( 'nothing', 'Příloha nemá žádný soubor JPG/PNG k převodu.' );
	}

	// Metadata přílohy.
	$main_new = $dir . '/' . ( $map[ basename( $main ) ] ?? basename( $main ) );
	$meta['file'] = ( '' !== $subdir ? $subdir . '/' : '' ) . basename( $main_new );
	$dims         = wp_getimagesize( $main_new );
	if ( $dims ) {
		$meta['width']  = (int) $dims[0];
		$meta['height'] = (int) $dims[1];
	}
	if ( $original ) {
		$before += (int) filesize( $dir . '/' . $original );
		unset( $meta['original_image'] );
	}
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $key => $size ) {
		if ( is_array( $size ) && ! empty( $size['file'] ) && isset( $map[ $size['file'] ] ) ) {
			$meta['sizes'][ $key ]['file']      = $map[ $size['file'] ];
			$meta['sizes'][ $key ]['mime-type'] = 'image/webp';
			$meta['sizes'][ $key ]['filesize']  = (int) filesize( $dir . '/' . $map[ $size['file'] ] );
		}
	}
	$meta['filesize'] = (int) filesize( $main_new );
	update_attached_file( $id, $main_new );
	wp_update_attachment_metadata( $id, $meta );
	$wpdb->update(
		$wpdb->posts,
		array(
			'post_mime_type' => 'image/webp',
			'guid'           => str_replace( array_keys( $map ), array_values( $map ), (string) get_post_field( 'guid', $id ) ),
		),
		array( 'ID' => $id )
	);

	// Odkazy v obsahu (bez revizí – staré adresy stejně přesměruje 301).
	$base = untrailingslashit( (string) wp_parse_url( (string) wp_get_upload_dir()['baseurl'], PHP_URL_PATH ) ) . '/' . ( '' !== $subdir ? $subdir . '/' : '' );
	foreach ( $map as $from => $to ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_type NOT IN ('revision','attachment') AND post_content LIKE %s",
				$base . $from,
				$base . $to,
				'%' . $wpdb->esc_like( $base . $from ) . '%'
			)
		);
	}

	// Původní soubory do karantény.
	foreach ( array_keys( $map ) as $from ) {
		ekc_quarantine_move( $dir . '/' . $from, 'webp' );
	}
	if ( $original ) {
		ekc_quarantine_move( $dir . '/' . $original, 'webp' );
	}
	clean_post_cache( $id );
	return max( 0, $before - $after );
}

/**
 * Úloha Údržby: dávka převodů omezená časem. Pokračování zařídí stránka (parametr ekc_webp_auto).
 *
 * @return array
 */
function ekc_webp_task_convert(): array {
	if ( ! ekc_webp_supported() ) {
		return array(
			'message' => 'Server neumí ukládat WebP (chybí podpora v GD nebo Imagick).',
			'type'    => 'error',
		);
	}
	$start  = time();
	$budget = max( 5, (int) ekc_filter( 'ekc_webp_batch_seconds', 25 ) );
	$done   = 0;
	$saved  = 0;
	$fail   = array();
	$seen   = array();
	do {
		$ids = array_diff( ekc_webp_pending( 20 ), $seen );
		foreach ( $ids as $id ) {
			$seen[] = $id;
			// Značka předem: když PHP na obrázku spadne (paměť, čas), další dávka ho přeskočí.
			update_post_meta( $id, '_ekc_webp_fail', 'Převod nedoběhl (nejspíš došla paměť) – obrázek zůstal JPG/PNG.' );
			$result = ekc_webp_convert( $id );
			if ( is_wp_error( $result ) ) {
				$fail[] = $id;
				update_post_meta( $id, '_ekc_webp_fail', $result->get_error_message() );
			} else {
				delete_post_meta( $id, '_ekc_webp_fail' );
				++$done;
				$saved += $result;
			}
			if ( time() - $start >= $budget ) {
				break 2;
			}
		}
	} while ( $ids );

	$left   = ekc_webp_pending_count();
	$failed = count( ekc_webp_failed() );
	$msg    = sprintf( 'Převedeno: %s, ušetřeno %s.', ekc_plural( $done, '%d obrázek', '%d obrázky', '%d obrázků' ), ekc_size( $saved ) );
	if ( $left ) {
		$msg .= ' ' . sprintf( 'Zbývá %d – další dávka se spustí sama.', $left );
	} elseif ( $failed ) {
		$msg .= ' ' . sprintf( 'Hotovo. Původní soubory jsou v karanténě, %s zůstává JPG/PNG (seznam v Údržbě).', ekc_plural( $failed, '%d obrázek', '%d obrázky', '%d obrázků' ) );
	} else {
		$msg .= ' Hotovo, všechny obrázky jsou WebP. Původní soubory jsou v karanténě.';
	}
	if ( $fail ) {
		$msg .= ' Nepovedlo se: ID ' . implode( ', ', $fail ) . '.';
	}
	return array(
		'message' => $msg,
		'type'    => $fail ? 'warning' : 'success',
		'args'    => array( 'ekc_webp_auto' => $left ? 1 : false ), // false = parametr z adresy odebrat
	);
}

/**
 * Úloha Údržby: nepřevedené obrázky znovu do fronty.
 *
 * @return array
 */
function ekc_webp_task_retry(): array {
	delete_metadata( 'post', 0, '_ekc_webp_fail', '', true );
	return array(
		'message' => 'Nepřevedené obrázky jsou znovu zařazené do převodu.',
		'mark'    => false,
	);
}

/**
 * Sekce Údržby: Převod na WebP.
 *
 * @param string $id ID sekce.
 */
function ekc_webp_render( string $id ): void {
	$pending = ekc_webp_pending_count();
	$failed  = ekc_webp_failed();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen zobrazení
	$auto = $pending && ! empty( $_GET['ekc_webp_auto'] );

	if ( ! $pending && ! $failed ) {
		echo '<p>' . ekc_status_dot( 'ok' ) . ' Všechny obrázky jsou ve formátu WebP (kromě vyloučených složek).' . ( ekc_opt( 'webp' ) ? ' Nově nahrané se převádějí automaticky.' : ' Převod při nahrání je vypnutý v nastavení.' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	if ( $pending ) {
		printf(
			'<p>Obrázků ve formátu JPG/PNG: <strong>%1$d</strong>. Převod je natrvalo: vznikne WebP verze všech velikostí, fotky větší než %2$d px se zmenší (jako u nových nahrání), odkazy v obsahu se přepíšou a staré adresy přesměrují (301). Původní soubory se přesunou do karantény. Vyloučené složky (%3$s) se nepřevádějí.</p>',
			(int) $pending,
			(int) ekc_webp_max_size(),
			esc_html( implode( ', ', ekc_media_excluded_dirs() ) )
		);
		if ( ! ekc_webp_supported() ) {
			echo '<p>' . ekc_status_dot( 'bad' ) . ' <strong>Server neumí ukládat WebP (chybí podpora v GD nebo Imagick).</strong></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( $auto ) {
			echo '<div class="notice notice-info inline"><p><span class="spinner is-active" style="float:none;margin:0 6px 0 0"></span>Převod pokračuje automaticky po dávkách – nechte stránku otevřenou. ';
			echo '<a href="' . esc_url( ekc_maintenance_url( array(), $id ) ) . '">Zastavit</a></p></div>';
			echo '<form id="ekc-webp-continue" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			ekc_maintenance_fields( $id, 'convert' );
			echo '</form><script>window.setTimeout(function(){document.getElementById("ekc-webp-continue").submit();},1500);</script>';
		} else {
			ekc_maintenance_button( $id, 'convert', 'Převést na WebP', sprintf( 'Převést %d obrázků natrvalo na WebP? Před převodem mějte čerstvou zálohu.', $pending ), true );
		}
	}
	if ( $failed ) {
		echo '<details style="margin:1em 0"><summary>' . esc_html( sprintf( 'Nepřevedené obrázky: %d (zůstávají JPG/PNG, web je zobrazuje dál)', count( $failed ) ) ) . '</summary><ul style="list-style:disc;margin-left:20px">';
		foreach ( array_slice( $failed, 0, 100, true ) as $fail_id => $reason ) {
			printf(
				'<li><a href="%1$s">%2$s</a> – %3$s</li>',
				esc_url( (string) get_edit_post_link( $fail_id ) ),
				esc_html( wp_basename( (string) get_attached_file( $fail_id ) ) ),
				esc_html( $reason )
			);
		}
		echo '</ul>';
		ekc_maintenance_button( $id, 'retry', 'Zkusit znovu', 'Znovu zařadit nepřevedené obrázky do převodu?' );
		echo '</details>';
	}
}

ekc_maintenance_section(
	'webp',
	array(
		'title'  => 'Převod na WebP',
		'order'  => 10,
		'render' => 'ekc_webp_render',
		'tasks'  => array(
			'convert' => 'ekc_webp_task_convert',
			'retry'   => 'ekc_webp_task_retry',
		),
	)
);

/**
 * Řádek do widgetu Zdraví webu.
 *
 * @param array $rows Řádky.
 * @return array
 */
function ekc_webp_health_rows( array $rows ): array {
	$count = ekc_webp_pending_count();
	$link  = '<a href="' . esc_url( ekc_maintenance_url( array(), 'webp' ) ) . '">' . (int) $count . '</a>';
	$rows['Média']['Nepřevedené na WebP'] = ( $count ? $link : '0' ) . ' ' . ekc_status_dot( $count ? 'warn' : 'ok' );
	return $rows;
}
add_filter( 'ekc_health_rows', 'ekc_webp_health_rows' );

/* =========================================================================
 * 3) Staré adresy JPG/PNG → 301 na WebP
 * ====================================================================== */

/**
 * Odkazy zvenku a vyhledávače obrázků na původní .jpg/.png → převedená .webp verze.
 */
function ekc_webp_redirect(): void {
	if ( ! is_404() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	$up   = wp_get_upload_dir();
	$base = untrailingslashit( (string) wp_parse_url( (string) $up['baseurl'], PHP_URL_PATH ) );
	if ( ! str_starts_with( $path, $base . '/' ) || ! preg_match( '/^(.+)\.(png|jpe?g)$/i', rawurldecode( substr( $path, strlen( $base ) ) ), $m ) || str_contains( $m[1], '..' ) ) {
		return;
	}
	if ( is_file( $up['basedir'] . $m[1] . '.webp' ) ) {
		wp_redirect( $up['baseurl'] . implode( '/', array_map( 'rawurlencode', explode( '/', $m[1] ) ) ) . '.webp', 301, 'EKC' ); // phpcs:ignore WordPress.Security.SafeRedirect -- vlastní uploads
		exit;
	}
}
add_action( 'template_redirect', 'ekc_webp_redirect' );

/* =========================================================================
 * Migrace: zbytky starších verzí převodu (WebP kopie vedle originálu)
 * Značku selhání _{p}_webp_fail převádí common.
 * ====================================================================== */

/**
 * Migrační krok.
 *
 * @param array $steps Kroky.
 * @return array
 */
function ekc_webp_migrations( array $steps ): array {
	$steps['webp/legacy'] = 'ekc_webp_migrate_legacy';
	return $steps;
}
add_filter( 'ekc_migrations', 'ekc_webp_migrations' );

/**
 * Smaže nastavení a značky starého „servírování WebP kopií“ ({p}_webp_serve, _{p}_webp).
 *
 * @return bool
 */
function ekc_webp_migrate_legacy(): bool {
	foreach ( ekc_legacy_prefixes() as $p ) {
		delete_option( $p . '_webp_serve' );
		delete_metadata( 'post', 0, '_' . $p . '_webp', '', true );
	}
	return true;
}
