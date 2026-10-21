<?php
/**
 * EKT module: analytics
 * Version: 1.0.1
 * Requires: common
 * Replaces: google-analytics-for-wordpress, google-site-kit, cookie-law-info, complianz-gdpr, cookie-notice
 * Assets: js/consent.js, css/consent.css
 * Summary: Google Analytics 4 s cookie lištou a Consent Mode v2 (výchozí stav denied, gtag.js až po souhlasu); bez ID měření nic.
 */

defined( 'ABSPATH' ) || exit;

/*
 * - Bez ID měření (Přizpůsobit → SEO a analytika) se nenačte nic: žádný skript, lišta ani odkaz.
 * - Dokud běží MonsterInsights, Site Kit, CookieYes, Complianz nebo Cookie Notice, modul nedělá
 *   nic (ekt_legacy_active( 'consent' ) – analytika a lišta jsou jeden celek, žádné dvojí GA).
 * - Neměří přihlášené a náhled Přizpůsobit.
 * - Výchozí stav souhlasu „denied“ inline v <head> (priorita 2), konfigurace GA a gtag.js až po
 *   souhlasu (assets/ekt/js/consent.js). Volba platí 12 měsíců (filtr ekt_consent_days), pak se
 *   lišta zeptá znovu. Po odmítnutí se smažou cookies _ga* na všech úrovních domény.
 * - localStorage ekt-consent = {"v":"granted|denied","t":ms}; JS převezme ek-consent (grant/deny),
 *   iv-consent (yes/no) a mndConsent ({v,t}) a staré klíče smaže.
 * - ID z MonsterInsights (option monsterinsights_site_profile) převezme jednorázově migrace
 *   'analytics/monsterinsights-id'; staré theme_mods (ek_ga_id, mnd_ga_id, iv_ga4_id) převádí common.
 *
 * Markup lišty (override template-parts/ekt/analytics.php, filtr ekt_analytics_html):
 *   div.ekt-consent[data-ekt-consent-bar][data-ekt-ga][data-ekt-consent-days][hidden]
 *     p.ekt-consent__text (+ a.ekt-consent__privacy)
 *     div.ekt-consent__actions > button.ekt-consent__btn[data-ekt-consent="denied"]
 *                                button.ekt-consent__btn.ekt-consent__btn--accept[data-ekt-consent="granted"]
 * Odkaz do patičky: ekt_consent_link() → button.ekt-consent-link[data-ekt-consent-open]
 * (ekt_site_info_parts() ho přidá sám). Jakýkoli prvek s data-ekt-consent-open lištu otevře.
 *
 * Veřejné funkce: ekt_analytics_id(), ekt_analytics_active(), ekt_consent_texts(), ekt_consent_link().
 * Filtry: ekt_ga_id( $id ), ekt_analytics_active( $active ), ekt_consent_texts( $texts ),
 * ekt_consent_days (365), ekt_analytics_html( $html, $args ).
 */

ekt_setting_register(
	'ga_id',
	array(
		'type'        => 'text',
		'label'       => 'Google Analytics 4 ID (G-…)',
		'description' => 'Prázdné = žádná analytika ani cookie lišta.',
		'section'     => 'seo',
		'priority'    => 10,
		'sanitize'    => 'ekt_analytics_sanitize_id',
	)
);

/**
 * Očištění ID měření: G-XXXXXXXX velkými písmeny, jinak ''.
 *
 * @param mixed $value Hodnota.
 * @return string
 */
function ekt_analytics_sanitize_id( $value ): string {
	$id = strtoupper( trim( is_scalar( $value ) ? (string) $value : '' ) );
	return preg_match( '/^G-[A-Z0-9]{4,20}$/', $id ) ? $id : '';
}

/**
 * ID měření GA4, jinak ''.
 *
 * @return string
 */
function ekt_analytics_id(): string {
	return ekt_analytics_sanitize_id( apply_filters( 'ekt_ga_id', (string) ekt_opt( 'ga_id' ) ) );
}

/**
 * Měří se na této stránce? Ne bez ID, dokud běží původní plugin analytiky nebo cookie lišty,
 * pro přihlášené, v náhledu Přizpůsobit, v administraci a ve feedech.
 *
 * @return bool
 */
function ekt_analytics_active(): bool {
	$active = '' !== ekt_analytics_id()
		&& ! ekt_legacy_active( 'consent' )
		&& ! is_admin()
		&& ! is_feed()
		&& ! is_user_logged_in()
		&& ! is_customize_preview();
	return (bool) apply_filters( 'ekt_analytics_active', $active );
}

/**
 * Texty lišty (neutrálně, ne v 1. osobě majitele). Změna filtrem ekt_consent_texts.
 *
 * @return array{label:string,text:string,privacy:string,decline:string,accept:string,settings:string}
 */
function ekt_consent_texts(): array {
	$texts = array(
		'label'    => 'Souhlas s cookies',
		'text'     => 'Web měří návštěvnost pomocí Google Analytics. Analytické cookies se uloží jen s vaším souhlasem.',
		'privacy'  => 'Zásady ochrany osobních údajů',
		'decline'  => 'Odmítnout',
		'accept'   => 'Přijmout',
		'settings' => 'Nastavení cookies',
	);
	return array_map( 'strval', array_merge( $texts, (array) apply_filters( 'ekt_consent_texts', $texts ) ) );
}

/**
 * Výchozí stav souhlasu – co nejdřív v <head>, před dalšími skripty.
 */
function ekt_analytics_head(): void {
	if ( ! ekt_analytics_active() ) {
		return;
	}
	wp_print_inline_script_tag(
		"window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}\n" .
		"gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'});",
		array( 'id' => 'ekt-consent-default' )
	);
}
add_action( 'wp_head', 'ekt_analytics_head', 2 );

/**
 * Skript a funkční styl lišty.
 */
function ekt_analytics_enqueue(): void {
	if ( ! ekt_analytics_active() ) {
		return;
	}
	ekt_enqueue_script( 'ekt-consent', 'js/consent.js' );
	ekt_enqueue_style( 'ekt-consent', 'css/consent.css' );
}
add_action( 'wp_enqueue_scripts', 'ekt_analytics_enqueue' );

/**
 * Cookie lišta (skrytá; zobrazí ji skript, dokud návštěvník nerozhodne).
 */
function ekt_analytics_bar(): void {
	if ( ! ekt_analytics_active() ) {
		return;
	}
	$args = array(
		'id'      => ekt_analytics_id(),
		'days'    => max( 1, (int) apply_filters( 'ekt_consent_days', 365 ) ),
		'texts'   => ekt_consent_texts(),
		'privacy' => (string) get_privacy_policy_url(),
	);
	echo ekt_render( // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno v ekt_analytics_bar_html()
		'analytics',
		'ekt_analytics_bar_html',
		$args
	);
}
add_action( 'wp_footer', 'ekt_analytics_bar', 5 );

/**
 * Výchozí markup lišty.
 *
 * @param array $args id, days, texts, privacy.
 * @return string
 */
function ekt_analytics_bar_html( array $args ): string {
	$t       = $args['texts'];
	$privacy = '' !== $args['privacy']
		? ' <a class="ekt-consent__privacy" href="' . esc_url( $args['privacy'] ) . '">' . esc_html( $t['privacy'] ) . '</a>'
		: '';
	return sprintf(
		'<div class="ekt-consent" role="region" aria-label="%1$s" data-ekt-consent-bar data-ekt-ga="%2$s" data-ekt-consent-days="%3$d" hidden>' .
		'<p class="ekt-consent__text">%4$s%5$s</p>' .
		'<div class="ekt-consent__actions">' .
		'<button type="button" class="ekt-consent__btn" data-ekt-consent="denied">%6$s</button>' .
		'<button type="button" class="ekt-consent__btn ekt-consent__btn--accept" data-ekt-consent="granted">%7$s</button>' .
		'</div></div>' . "\n",
		esc_attr( $t['label'] ),
		esc_attr( (string) $args['id'] ),
		(int) $args['days'],
		esc_html( $t['text'] ),
		$privacy,
		esc_html( $t['decline'] ),
		esc_html( $t['accept'] )
	);
}

/**
 * Odkaz „Nastavení cookies“ – znovu otevře lištu. Bez aktivní analytiky ''.
 *
 * @return string HTML.
 */
function ekt_consent_link(): string {
	if ( ! ekt_analytics_active() ) {
		return '';
	}
	return '<button type="button" class="ekt-consent-link" data-ekt-consent-open>' . esc_html( ekt_consent_texts()['settings'] ) . '</button>';
}

/**
 * Migrace: jednorázově převezme ID měření z MonsterInsights, když šablona žádné nemá.
 * Starý příznak {p}_ga_migrated (B/C) znamená, že převzetí už proběhlo – nic nedoplňovat.
 *
 * @param array $steps Kroky.
 * @return array
 */
function ekt_analytics_migrations( array $steps ): array {
	$steps['analytics/monsterinsights-id'] = 'ekt_analytics_migrate_mi';
	return $steps;
}
add_filter( 'ekt_migrations', 'ekt_analytics_migrations' );

/**
 * Krok migrace 'analytics/monsterinsights-id' (idempotentní).
 *
 * @return bool
 */
function ekt_analytics_migrate_mi(): bool {
	$done = false;
	foreach ( ekt_legacy_prefixes() as $p ) {
		if ( false !== get_theme_mod( $p . '_ga_migrated' ) ) {
			$done = true;
			remove_theme_mod( $p . '_ga_migrated' );
		}
	}
	if ( $done || false !== get_theme_mod( 'ekt_ga_id' ) ) {
		return true; // ID už je (i prázdné = vědomě vymazané)
	}
	$profile = get_option( 'monsterinsights_site_profile' );
	$id      = is_array( $profile ) ? ekt_analytics_sanitize_id( $profile['v4'] ?? '' ) : '';
	if ( '' !== $id ) {
		set_theme_mod( 'ekt_ga_id', $id );
		return get_theme_mod( 'ekt_ga_id' ) === $id;
	}
	return true;
}
