<?php
/**
 * EKT module: color-scheme
 * Version: 1.0.0
 * Requires: common
 * Replaces: wp-dark-mode
 * Assets: js/color-scheme.js
 * Summary: Světlý a tmavý režim bez bliknutí: volba v localStorage, přepínač Systém/Světlý/Tmavý nebo dvoustavový, theme-color, volitelně široká stránka.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Stav je na <html>: data-theme="light|dark" (bez atributu = podle systému) a volitelně
 * data-wide="0" (užší stránka). Šablona má tmavé tokeny dvakrát: :root[data-theme="dark"] a
 * @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) } (kostra main.css).
 *
 * - wp_head priorita 0: <meta name="color-scheme" content="light dark">, theme-color (filtr
 *   ekt_color_scheme_theme_color → array( 'light' => '#…', 'dark' => '#…' ), výchozí žádná) a
 *   inline skript, který nastaví data-theme/data-wide před vykreslením (bez bliknutí).
 * - Ovladače (JS assets/ekt/js/color-scheme.js, načte se ve výstupní funkci, nebo vždy filtrem
 *   ekt_color_scheme_enqueue): [data-ekt-theme-set="system|light|dark"] (aria-pressed),
 *   [data-ekt-theme-toggle] (světlý ↔ tmavý), [data-ekt-wide-toggle] (aria-pressed = široká).
 * - Široká stránka jen se zapnutým filtrem ekt_color_scheme_wide (výchozí false).
 * - localStorage ekt-theme (light|dark), ekt-wide ("0"); převezme ek-theme, iv-theme, ek-wide.
 *
 * Výstup ekt_color_scheme_switch() (override template-parts/ekt/color-scheme.php s $args,
 * filtr ekt_color_scheme_html). Varianta filtrem ekt_color_scheme_variant: 'set' (výchozí) =
 *   <div class="ekt-scheme" role="group" aria-label="Vzhled">
 *     <button class="ekt-scheme__btn ekt-scheme__btn--system" data-ekt-theme-set="system" aria-pressed>
 *       <svg class="ekt-icon …"/><span class="ekt-scheme__label">Systém</span></button> … light, dark
 *   </div>
 * 'toggle' = <button class="ekt-scheme ekt-scheme--toggle" data-ekt-theme-toggle aria-label="Přepnout…">
 *   <span class="ekt-scheme__icon ekt-scheme__icon--light">sun</span><span class="ekt-scheme__icon ekt-scheme__icon--dark">moon</span></button>
 *   (kterou ikonu ukázat, řeší CSS šablony podle data-theme).
 *
 * Veřejné funkce: ekt_color_scheme_switch(), ekt_color_scheme_head().
 * Filtry: ekt_color_scheme_variant, ekt_color_scheme_wide, ekt_color_scheme_theme_color,
 * ekt_color_scheme_texts( $texts ), ekt_color_scheme_enqueue.
 */

/**
 * Texty přepínače.
 *
 * @return array<string,string>
 */
function ekt_color_scheme_texts(): array {
	$texts = array(
		'group'  => 'Vzhled',
		'system' => 'Systém',
		'light'  => 'Světlý',
		'dark'   => 'Tmavý',
		'toggle' => 'Přepnout světlý a tmavý režim',
	);
	return array_map( 'strval', array_merge( $texts, (array) apply_filters( 'ekt_color_scheme_texts', $texts ) ) );
}

/**
 * Hlavička: color-scheme, theme-color a nastavení režimu před vykreslením.
 */
function ekt_color_scheme_head(): void {
	echo '<meta name="color-scheme" content="light dark">' . "\n";
	$colors = (array) apply_filters( 'ekt_color_scheme_theme_color', array() );
	foreach ( array( 'light', 'dark' ) as $mode ) {
		$color = sanitize_hex_color( (string) ( $colors[ $mode ] ?? '' ) );
		if ( $color ) {
			printf(
				'<meta name="theme-color" content="%1$s" media="(prefers-color-scheme: %2$s)" data-ekt-theme-color="%2$s" data-ekt-color="%1$s">' . "\n",
				esc_attr( $color ),
				esc_attr( $mode )
			);
		}
	}
	$wide = (bool) apply_filters( 'ekt_color_scheme_wide', false );
	// Volba uživatele přebije systém; theme-color se srovná s ručně zvoleným režimem.
	$js = "(function(){var d=document.documentElement,s;try{s=window.localStorage;}catch(e){return;}if(!s)return;try{" .
		"var t=s.getItem('ekt-theme');if(t===null){t=s.getItem('ek-theme')||s.getItem('iv-theme');if(t==='light'||t==='dark'){s.setItem('ekt-theme',t);}s.removeItem('ek-theme');s.removeItem('iv-theme');}" .
		"if(t==='light'||t==='dark'){d.setAttribute('data-theme',t);var m=document.querySelectorAll('meta[data-ekt-theme-color]'),c=null;m.forEach(function(x){if(x.getAttribute('data-ekt-theme-color')===t){c=x.getAttribute('data-ekt-color');}});if(c){m.forEach(function(x){x.content=c;});}}";
	if ( $wide ) {
		$js .= "var w=s.getItem('ekt-wide');if(w===null&&s.getItem('ek-wide')!==null){w=s.getItem('ek-wide');if(w==='0'){s.setItem('ekt-wide','0');}s.removeItem('ek-wide');}if(w==='0'){d.setAttribute('data-wide','0');}";
	}
	$js .= '}catch(e){}})();';
	wp_print_inline_script_tag( $js, array( 'id' => 'ekt-color-scheme-init' ) );
}
add_action( 'wp_head', 'ekt_color_scheme_head', 0 );

/**
 * Načte skript ovladačů.
 */
function ekt_color_scheme_enqueue(): void {
	ekt_enqueue_script( 'ekt-color-scheme', 'js/color-scheme.js' );
}

/**
 * Ovladače mimo výstupní funkci (vlastní markup šablony, široká stránka): skript vždy.
 */
function ekt_color_scheme_maybe_enqueue(): void {
	if ( apply_filters( 'ekt_color_scheme_enqueue', false ) ) {
		ekt_color_scheme_enqueue();
	}
}
add_action( 'wp_enqueue_scripts', 'ekt_color_scheme_maybe_enqueue' );

/**
 * Přepínač světlého a tmavého režimu (header.php kostry).
 *
 * @return string HTML.
 */
function ekt_color_scheme_switch(): string {
	ekt_color_scheme_enqueue();
	$variant = 'toggle' === apply_filters( 'ekt_color_scheme_variant', 'set' ) ? 'toggle' : 'set';
	return ekt_render(
		'color-scheme',
		static function ( array $args ): string {
			$t = $args['texts'];
			if ( 'toggle' === $args['variant'] ) {
				return sprintf(
					'<button type="button" class="ekt-scheme ekt-scheme--toggle" data-ekt-theme-toggle aria-label="%1$s" title="%1$s"><span class="ekt-scheme__icon ekt-scheme__icon--light">%2$s</span><span class="ekt-scheme__icon ekt-scheme__icon--dark">%3$s</span></button>',
					esc_attr( $t['toggle'] ),
					ekt_icon( 'sun', 18 ),
					ekt_icon( 'moon', 18 )
				);
			}
			$html = '<div class="ekt-scheme" role="group" aria-label="' . esc_attr( $t['group'] ) . '">';
			foreach ( array(
				'system' => 'monitor',
				'light'  => 'sun',
				'dark'   => 'moon',
			) as $mode => $icon ) {
				$html .= sprintf(
					'<button type="button" class="ekt-scheme__btn ekt-scheme__btn--%1$s" data-ekt-theme-set="%1$s" aria-pressed="false">%2$s<span class="ekt-scheme__label">%3$s</span></button>',
					esc_attr( $mode ),
					ekt_icon( $icon, 16 ),
					esc_html( $t[ $mode ] )
				);
			}
			return $html . '</div>';
		},
		array(
			'variant' => $variant,
			'texts'   => ekt_color_scheme_texts(),
		)
	);
}
