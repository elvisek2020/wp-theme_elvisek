<?php
/**
 * EKT module: social
 * Version: 1.0.0
 * Requires: common
 * Replaces: sticky-side-buttons, simple-social-icons
 * Assets: –
 * Summary: Odkazy na profily na sociálních sítích z Přizpůsobit (ikony bez skriptů třetích stran).
 */

defined( 'ABSPATH' ) || exit;

/*
 * Sítě (registr, filtr ekt_social_networks): email, github, linkedin, facebook, instagram,
 * youtube, x, mastodon, rss. Hodnoty v Přizpůsobit → Sociální sítě (theme_mods ekt_social_<síť>;
 * staré ek_social_*, iv_social_* převádí common). E-mail jako mailto: zakódovaný antispambot(),
 * profily target="_blank" rel="me noopener" (rel="me" ověřuje profil např. na Mastodonu), RSS bez nového okna.
 *
 * Výstup ekt_social_links( $class ) (override template-parts/ekt/social.php s $args['items'],
 * $args['class']; filtr ekt_social_html):
 *   <div class="ekt-social $class">
 *     <a class="ekt-social__link ekt-social__link--github" href aria-label title>
 *       <svg class="ekt-icon ekt-icon--github">…</svg><span class="ekt-social__label">GitHub</span>
 *     </a>…
 *   </div>
 * Popisek .ekt-social__label je v markupu vždy; šablona ho skryje vizuálně, nebo zobrazí.
 *
 * Veřejné funkce: ekt_social_networks(), ekt_social_items(), ekt_social_links( $class ).
 * Filtry: ekt_social_networks( $networks ), ekt_social_items( $items ), ekt_social_icon_size (20).
 */

/**
 * Registr sítí: klíč => [label, icon, type (email|url)].
 *
 * @return array<string,array{label:string,icon:string,type:string}>
 */
function ekt_social_networks(): array {
	return (array) apply_filters(
		'ekt_social_networks',
		array(
			'email'     => array(
				'label' => 'E-mail',
				'icon'  => 'mail',
				'type'  => 'email',
			),
			'github'    => array(
				'label' => 'GitHub',
				'icon'  => 'github',
				'type'  => 'url',
			),
			'linkedin'  => array(
				'label' => 'LinkedIn',
				'icon'  => 'linkedin',
				'type'  => 'url',
			),
			'facebook'  => array(
				'label' => 'Facebook',
				'icon'  => 'facebook',
				'type'  => 'url',
			),
			'instagram' => array(
				'label' => 'Instagram',
				'icon'  => 'instagram',
				'type'  => 'url',
			),
			'youtube'   => array(
				'label' => 'YouTube',
				'icon'  => 'youtube',
				'type'  => 'url',
			),
			'x'         => array(
				'label' => 'X',
				'icon'  => 'x',
				'type'  => 'url',
			),
			'mastodon'  => array(
				'label' => 'Mastodon',
				'icon'  => 'mastodon',
				'type'  => 'url',
			),
			'rss'       => array(
				'label' => 'RSS',
				'icon'  => 'rss',
				'type'  => 'url',
			),
		)
	);
}

ekt_customizer_section( 'social', 'Sociální sítě', 50 );
$ekt_social_priority = 10;
foreach ( ekt_social_networks() as $ekt_social_key => $ekt_social_net ) {
	$ekt_social_email = 'email' === ( $ekt_social_net['type'] ?? 'url' );
	ekt_setting_register(
		'social_' . sanitize_key( (string) $ekt_social_key ),
		array(
			'type'        => $ekt_social_email ? 'email' : 'url',
			'label'       => (string) ( $ekt_social_net['label'] ?? $ekt_social_key ),
			'description' => $ekt_social_email ? 'Adresa se na webu zakóduje proti robotům.' : ( 'rss' === $ekt_social_key ? 'Adresa feedu webu (…/feed/).' : 'Celá adresa profilu (https://…).' ),
			'section'     => 'social',
			'priority'    => $ekt_social_priority++,
		)
	);
}
unset( $ekt_social_priority, $ekt_social_key, $ekt_social_net, $ekt_social_email );

/**
 * Vyplněné sítě v pořadí registru.
 *
 * @return array<int,array{network:string,label:string,icon:string,url:string,external:bool}> url je escapovaná adresa.
 */
function ekt_social_items(): array {
	$items = array();
	foreach ( ekt_social_networks() as $key => $net ) {
		$value = trim( (string) ekt_opt( 'social_' . $key ) );
		if ( '' === $value ) {
			continue;
		}
		if ( 'email' === ( $net['type'] ?? 'url' ) ) {
			$email = sanitize_email( $value );
			if ( '' === $email ) {
				continue;
			}
			$url      = 'mailto:' . antispambot( $email ); // entity, esc_url by je zničil
			$external = false;
		} else {
			$url = esc_url( $value );
			if ( '' === $url ) {
				continue;
			}
			$external = 'rss' !== $key; // vlastní feed bez nového okna
		}
		$items[] = array(
			'network'  => (string) $key,
			'label'    => (string) ( $net['label'] ?? $key ),
			'icon'     => (string) ( $net['icon'] ?? $key ),
			'url'      => $url,
			'external' => $external,
		);
	}
	return (array) apply_filters( 'ekt_social_items', $items );
}

/**
 * Ikony profilů (patička, hlavička…). Bez vyplněných sítí ''.
 *
 * @param string $class Třídy navíc pro obal.
 * @return string HTML.
 */
function ekt_social_links( string $class = '' ): string {
	$items = ekt_social_items();
	return ekt_render(
		'social',
		static function ( array $args ): string {
			if ( ! $args['items'] ) {
				return '';
			}
			$size = (int) apply_filters( 'ekt_social_icon_size', 20 );
			$html = '<div class="' . esc_attr( trim( 'ekt-social ' . $args['class'] ) ) . '">';
			foreach ( $args['items'] as $item ) {
				$html .= sprintf(
					'<a class="ekt-social__link ekt-social__link--%1$s" href="%2$s" aria-label="%3$s" title="%3$s"%4$s>%5$s<span class="ekt-social__label">%6$s</span></a>',
					esc_attr( sanitize_html_class( $item['network'] ) ),
					$item['url'], // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno v ekt_social_items()
					esc_attr( $item['label'] ),
					$item['external'] ? ' target="_blank" rel="me noopener"' : '',
					ekt_icon( $item['icon'], $size, 'ekt-social__icon' ),
					esc_html( $item['label'] )
				);
			}
			return $html . '</div>';
		},
		array(
			'items' => $items,
			'class' => $class,
		)
	);
}
