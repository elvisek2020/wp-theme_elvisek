<?php
/**
 * EKT module: updated
 * Version: 1.0.0
 * Requires: common
 * Replaces: wp-last-modified-info
 * Assets: –
 * Summary: Štítek „Aktualizováno“ podle ručně zadaného data revize v Nastavení článku.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Datum se zadává ručně (post meta ekt_updated, Y-m-d). Automatické post_modified se nepoužívá:
 * mění se i při hromadných úpravách (WebP, oprava odkazů) a štítek by svítil u všech článků.
 * Štítek se ukáže, jen když je datum pozdější než datum vydání. Modul seo bere stejné datum
 * pro dateModified.
 *
 * Veřejné funkce: ekt_updated_date( $post ) (Y-m-d nebo ''), ekt_updated_badge( $post ),
 * ekt_updated_chip( $post ) (malý štítek na kartu).
 * Filtry: ekt_updated_texts( ['label'=>'Aktualizováno','format'=>'j. n. Y'] ),
 * ekt_updated_html( $html, $args ) (override template-parts/ekt/updated.php; $args: date, label,
 * text, variant badge|chip).
 * Jiné typy obsahu: v inc/site-*.php zaregistruj pole znovu se stejným klíčem a jinými post_types.
 *
 * Markup: <span class="ekt-updated"><svg class="ekt-icon ekt-icon--refresh">
 *           <span class="ekt-updated__label">Aktualizováno <time datetime="Y-m-d">1. 10. 2026</time></span></span>
 *         karta: <span class="ekt-updated ekt-updated--chip" title="Aktualizováno 1. 10. 2026">…Aktualizováno</span>
 */

ekt_post_setting_register(
	'updated',
	array(
		'type'     => 'date',
		'label'    => 'Aktualizace návodu',
		'help'     => 'Datum revize – u článku se zobrazí štítek „Aktualizováno“. Prázdné = bez štítku.',
		'priority' => 30,
		'sanitize' => 'ekt_updated_sanitize',
	)
);

/**
 * Očištění data aktualizace (Y-m-d, jinak '').
 *
 * @param mixed $value Hodnota.
 * @return string
 */
function ekt_updated_sanitize( $value ): string {
	return ekt_sanitize_date( $value );
}

/**
 * Datum aktualizace (Y-m-d), jen když je pozdější než datum vydání.
 *
 * @param int|WP_Post|null $post Článek.
 * @return string '' = bez aktualizace.
 */
function ekt_updated_date( $post = null ): string {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$value = ekt_sanitize_date( ekt_post_setting( 'updated', $post ) );
	return ( '' !== $value && $value > (string) get_the_date( 'Y-m-d', $post ) ) ? $value : '';
}

/**
 * Společný výstup štítku.
 *
 * @param int|WP_Post|null $post    Článek.
 * @param string           $variant badge|chip.
 * @return string
 */
function ekt_updated_render( $post, string $variant ): string {
	$date = ekt_updated_date( $post );
	if ( '' === $date ) {
		return '';
	}
	$texts = array_merge(
		array(
			'label'  => 'Aktualizováno',
			'format' => 'j. n. Y',
		),
		(array) apply_filters( 'ekt_updated_texts', array() )
	);
	$args  = array(
		'date'    => $date,
		'label'   => (string) $texts['label'],
		'text'    => (string) wp_date( (string) $texts['format'], (int) strtotime( $date . ' 12:00:00' ) ),
		'variant' => $variant,
		'post'    => get_post( $post ),
	);
	return ekt_render(
		'updated',
		static function ( array $args ): string {
			if ( 'chip' === $args['variant'] ) {
				return sprintf(
					'<span class="ekt-updated ekt-updated--chip" title="%1$s">%2$s%3$s</span>',
					esc_attr( $args['label'] . ' ' . $args['text'] ),
					ekt_icon( 'refresh', 13 ),
					esc_html( $args['label'] )
				);
			}
			return sprintf(
				'<span class="ekt-updated">%1$s<span class="ekt-updated__label">%2$s <time datetime="%3$s">%4$s</time></span></span>',
				ekt_icon( 'refresh', 15 ),
				esc_html( $args['label'] ),
				esc_attr( $args['date'] ),
				esc_html( $args['text'] )
			);
		},
		$args
	);
}

/**
 * Štítek „Aktualizováno 1. 10. 2026“ pro detail článku (volá kostra v single.php).
 *
 * @param int|WP_Post|null $post Článek.
 * @return string HTML ('' = bez aktualizace).
 */
function ekt_updated_badge( $post = null ): string {
	return ekt_updated_render( $post, 'badge' );
}

/**
 * Malý štítek „Aktualizováno“ na kartu článku.
 *
 * @param int|WP_Post|null $post Článek.
 * @return string HTML.
 */
function ekt_updated_chip( $post = null ): string {
	return ekt_updated_render( $post, 'chip' );
}

/*
 * Migrace: post meta ek_updated → ekt_updated (A elvisek).
 */
add_filter(
	'ekt_migrations',
	static function ( array $steps ): array {
		$steps['updated/post-meta'] = 'ekt_updated_migrate';
		return $steps;
	}
);

/**
 * Krok migrace: {p}_updated → ekt_updated.
 *
 * @return bool
 */
function ekt_updated_migrate(): bool {
	$ok = true;
	foreach ( ekt_legacy_prefixes() as $p ) {
		$ok = ekt_migrate_meta_key( 'post', $p . '_updated', 'ekt_updated' ) && $ok;
	}
	return $ok;
}
