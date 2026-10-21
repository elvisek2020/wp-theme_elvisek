<?php
/**
 * EKT module: totop
 * Version: 1.0.0
 * Requires: common
 * Replaces: wpfront-scroll-top
 * Assets: js/totop.js
 * Summary: Tlačítko Nahoru, které se ukáže po odrolování zhruba jedné obrazovky (bez animace při omezeném pohybu).
 */

defined( 'ABSPATH' ) || exit;

/*
 * Výstup ekt_totop_button() (footer.php kostry; override template-parts/ekt/totop.php,
 * filtr ekt_totop_html):
 *   <button type="button" class="ekt-totop" data-ekt-totop aria-label="Nahoru" hidden>svg arrow-up</button>
 * JS (assets/ekt/js/totop.js) tlačítko ukáže po odrolování max( 600 px, výška okna ): odebere
 * hidden a přidá třídu is-shown; při návratu nahoru vrátí hidden. Kliknutí roluje nahoru,
 * plynule jen bez prefers-reduced-motion. Umístění (position: fixed…) a vzhled jsou v CSS šablony.
 *
 * Filtry: ekt_totop_label (Nahoru), ekt_totop_html( $html, $args ).
 */

/**
 * Tlačítko Nahoru.
 *
 * @return string HTML.
 */
function ekt_totop_button(): string {
	ekt_enqueue_script( 'ekt-totop', 'js/totop.js' );
	return ekt_render(
		'totop',
		static fn( array $args ): string => sprintf(
			'<button type="button" class="ekt-totop" data-ekt-totop aria-label="%1$s" title="%1$s" hidden>%2$s</button>',
			esc_attr( $args['label'] ),
			ekt_icon( 'arrow-up', 20 )
		),
		array( 'label' => (string) apply_filters( 'ekt_totop_label', 'Nahoru' ) )
	);
}
