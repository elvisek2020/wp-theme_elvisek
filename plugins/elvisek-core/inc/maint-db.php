<?php
/**
 * EKC module: maint-db
 * Version: 1.0.0
 * Requires: common
 * Replaces: wp-optimize, wp-sweep, advanced-database-cleaner, optimize-database
 * Summary: Údržba databáze: tabulky a autoload, OPTIMIZE a úklid revizí, konceptů, koše, transientů a osiřelých metadat po dávkách.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Sekce Databáze v Nástroje → Údržba webu. Počty se před mazáním spočítají znovu na serveru
 * (formulář mohl být otevřený dlouho), mazání běží po dávkách s časovým limitem – co nestihne,
 * dokončí další kliknutí. Koš maže natrvalo jen obsah, ne přílohy (ty by vzaly i soubory).
 */

const EKC_MAINT_DB_BATCH = 500;

/**
 * Úklidové úlohy: klíč => [název, popis].
 *
 * @return array<string,array{0:string,1:string}>
 */
function ekc_maint_db_tasks(): array {
	return array(
		'revisions'   => array( 'Revize', 'Starší uložené verze stránek a příspěvků. Aktuální obsah zůstane.' ),
		'autodrafts'  => array( 'Automatické koncepty', 'Nedokončené koncepty, které WordPress vytvoří při otevření editoru.' ),
		'trash'       => array( 'Koš', 'Stránky a příspěvky v koši – smažou se natrvalo (přílohy v koši zůstanou).' ),
		'transients'  => array( 'Prošlé transienty', 'Expirovaná mezipaměť v tabulce voleb.' ),
		'orphan_meta' => array( 'Osiřelá metadata', 'Metadata obsahu, který už neexistuje.' ),
	);
}

/**
 * Počet položek úlohy.
 *
 * @param string $task Klíč úlohy.
 * @return int
 */
function ekc_maint_db_count( string $task ): int {
	global $wpdb;
	switch ( $task ) {
		case 'revisions':
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
		case 'autodrafts':
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
		case 'trash':
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_type <> 'attachment'" );
		case 'transients':
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options} WHERE ( option_name LIKE %s OR option_name LIKE %s ) AND option_value < %d",
					$wpdb->esc_like( '_transient_timeout_' ) . '%',
					$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
					time()
				)
			);
		case 'orphan_meta':
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
	}
	return 0;
}

/**
 * Jedna dávka úklidu.
 *
 * @param string $task Klíč úlohy.
 * @return int Odstraněno položek (0 = není co dělat).
 */
function ekc_maint_db_batch( string $task ): int {
	global $wpdb;
	$limit = EKC_MAINT_DB_BATCH;
	$done  = 0;
	switch ( $task ) {
		case 'revisions':
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT %d", $limit ) ) as $id ) {
				$done += wp_delete_post_revision( (int) $id ) ? 1 : 0;
			}
			break;
		case 'autodrafts':
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' LIMIT %d", $limit ) ) as $id ) {
				$done += wp_delete_post( (int) $id, true ) ? 1 : 0;
			}
			break;
		case 'trash':
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_type <> 'attachment' LIMIT %d", $limit ) ) as $id ) {
				$done += wp_delete_post( (int) $id, true ) ? 1 : 0;
			}
			break;
		case 'transients':
			$done = ekc_maint_db_count( 'transients' );
			delete_expired_transients( true );
			break;
		case 'orphan_meta':
			$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT pm.meta_id FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL LIMIT %d", $limit * 10 ) ) );
			if ( $ids ) {
				$done = (int) $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- čísla
				wp_cache_flush_group( 'post_meta' );
			}
			break;
	}
	return $done;
}

/**
 * Úloha Údržby: úklid (item = klíč úlohy). Počet se ověří znovu na serveru.
 *
 * @param string $item Klíč úlohy.
 * @return array
 */
function ekc_maint_db_task_cleanup( string $item ): array {
	$tasks = ekc_maint_db_tasks();
	if ( ! isset( $tasks[ $item ] ) ) {
		return array(
			'message' => 'Neznámá úloha úklidu.',
			'type'    => 'error',
		);
	}
	if ( ! ekc_maint_db_count( $item ) ) {
		return array(
			'message' => $tasks[ $item ][0] . ': není co mazat.',
			'type'    => 'info',
			'mark'    => false,
		);
	}
	$start  = time();
	$budget = (int) apply_filters( 'ekc_maint_db_seconds', 20 );
	$total  = 0;
	do {
		$done   = ekc_maint_db_batch( $item );
		$total += $done;
	} while ( $done > 0 && 'transients' !== $item && time() - $start < $budget );

	$left = ekc_maint_db_count( $item );
	$msg  = sprintf( '%s – smazáno: %s.', $tasks[ $item ][0], ekc_plural( $total, '%d položka', '%d položky', '%d položek' ) );
	if ( $left && 'transients' !== $item ) {
		$msg .= sprintf( ' Zbývá %d – klikněte znovu.', $left );
	}
	return array(
		'message' => $msg,
		'type'    => $left && 'transients' !== $item ? 'warning' : 'success',
	);
}

/**
 * Úloha Údržby: OPTIMIZE TABLE nad tabulkami webu.
 *
 * @return string
 */
function ekc_maint_db_task_optimize(): string {
	global $wpdb;
	$count = 0;
	$start = time();
	foreach ( ekc_db_tables() as $table ) {
		$wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $table->t ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		++$count;
		if ( time() - $start > 240 ) {
			break;
		}
	}
	delete_transient( 'ekc_health' );
	return sprintf( 'Optimalizováno: %s.', ekc_plural( $count, '%d tabulka', '%d tabulky', '%d tabulek' ) );
}

/**
 * Sekce Údržby: Databáze.
 *
 * @param string $id ID sekce.
 */
function ekc_maint_db_render( string $id ): void {
	$tables   = ekc_db_tables();
	$autoload = ekc_db_autoload();
	$size     = 0;
	$free     = 0;
	foreach ( $tables as $table ) {
		$size += (int) $table->d + (int) $table->i;
		$free += (int) $table->f;
	}

	printf(
		'<p>%1$s, celkem <strong>%2$s</strong>%3$s. Autoload (načítá se při každém požadavku): <strong>%4$s</strong> v %5$s%6$s</p>',
		esc_html( ekc_plural( count( $tables ), '%d tabulka', '%d tabulky', '%d tabulek' ) ),
		esc_html( ekc_size( $size ) ),
		$free > MB_IN_BYTES ? esc_html( ', nevyužité místo ' . ekc_size( $free ) ) : '',
		esc_html( ekc_size( (int) $autoload['total'] ) ),
		esc_html( ekc_plural( (int) $autoload['count'], '%d volbě', '%d volbách', '%d volbách' ) ),
		$autoload['total'] > MB_IN_BYTES ? ' ' . ekc_status_dot( 'warn' ) . ' víc než 1 MB zpomaluje web.' : '.' // phpcs:ignore WordPress.Security.EscapeOutput
	);

	echo '<details style="margin-bottom:1em"><summary style="cursor:pointer">Tabulky a největší volby v autoloadu</summary><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-top:8px">';
	echo '<table class="widefat striped"><thead><tr><th>Tabulka</th><th style="text-align:right">Řádků</th><th style="text-align:right">Velikost</th><th style="text-align:right">Nevyužito</th></tr></thead><tbody>';
	foreach ( $tables as $table ) {
		printf(
			'<tr><td><code>%1$s</code></td><td style="text-align:right">%2$s</td><td style="text-align:right">%3$s</td><td style="text-align:right">%4$s</td></tr>',
			esc_html( (string) $table->t ),
			esc_html( number_format_i18n( (int) $table->r ) ),
			esc_html( ekc_size( (int) $table->d + (int) $table->i ) ),
			esc_html( (int) $table->f ? ekc_size( (int) $table->f ) : '–' )
		);
	}
	echo '</tbody></table><table class="widefat striped"><thead><tr><th>Volba (autoload)</th><th style="text-align:right">Velikost</th></tr></thead><tbody>';
	foreach ( (array) $autoload['top'] as $row ) {
		printf( '<tr><td><code>%1$s</code></td><td style="text-align:right">%2$s</td></tr>', esc_html( (string) $row->n ), esc_html( ekc_size( (int) $row->b ) ) );
	}
	echo '</tbody></table></div></details>';
	ekc_maintenance_button( $id, 'optimize', 'Optimalizovat tabulky', 'Spustit optimalizaci všech tabulek webu? Může to chvíli trvat.' );

	echo '<h3>Úklid databáze</h3><table class="widefat striped" style="max-width:860px"><tbody>';
	foreach ( ekc_maint_db_tasks() as $key => $task ) {
		$count = ekc_maint_db_count( $key );
		printf(
			'<tr><td><strong>%1$s</strong><br><span class="description">%2$s</span></td><td style="width:90px;text-align:right">%3$s</td><td style="width:120px">',
			esc_html( $task[0] ),
			esc_html( $task[1] ),
			esc_html( number_format_i18n( $count ) )
		);
		if ( $count ) {
			ekc_maintenance_button( $id, 'cleanup', 'Smazat', sprintf( 'Opravdu smazat %1$d položek (%2$s)? Akce je nevratná.', $count, $task[0] ), false, $key );
		} else {
			echo '<span class="description">Není co mazat</span>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

ekc_maintenance_section(
	'db',
	array(
		'title'  => 'Databáze',
		'order'  => 20,
		'render' => 'ekc_maint_db_render',
		'tasks'  => array(
			'optimize' => 'ekc_maint_db_task_optimize',
			'cleanup'  => 'ekc_maint_db_task_cleanup',
		),
	)
);
