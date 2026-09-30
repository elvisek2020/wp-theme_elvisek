<?php
/**
 * Google Analytics 4 s cookie lištou (Consent Mode v2).
 *
 * Bez vyplněného GA4 ID (Přizpůsobit → ElvisEK) se nenačte nic:
 * žádný skript Googlu, žádná lišta. Nahrazuje MonsterInsights + CookieYes.
 */

defined( 'ABSPATH' ) || exit;

function ek_ga_id(): string {
	return (string) ek_opt( 'ek_ga_id' );
}

add_action( 'wp_head', function () {
	$id = ek_ga_id();
	if ( ! $id || is_user_logged_in() ) {
		return;
	}
	// Výchozí stav: vše zamítnuto, dokud návštěvník nepotvrdí. gtag.js se stáhne až po souhlasu (consent.js).
	?>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'});
gtag('js',new Date());gtag('config','<?php echo esc_js( $id ); ?>',{anonymize_ip:true});
window.EK_GA_ID='<?php echo esc_js( $id ); ?>';
</script>
	<?php
}, 2 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! ek_ga_id() || is_user_logged_in() ) {
		return;
	}
	wp_enqueue_script( 'ek-consent', EK_URI . '/assets/js/consent.js', array(), ek_asset_ver( 'assets/js/consent.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
} );

add_action( 'wp_footer', function () {
	if ( ! ek_ga_id() || is_user_logged_in() ) {
		return;
	}
	$privacy = get_privacy_policy_url();
	?>
<div class="ek-consent" data-ek-consent hidden role="region" aria-label="Souhlas s cookies">
	<p>Web používá Google Analytics, abych věděl, které návody vás zajímají. Cookies se uloží jen s vaším souhlasem.<?php if ( $privacy ) : ?> <a href="<?php echo esc_url( $privacy ); ?>">Více informací</a>.<?php endif; ?></p>
	<div class="ek-consent__actions">
		<button type="button" class="ek-btn ek-btn--ghost" data-ek-consent-choice="deny">Odmítnout</button>
		<button type="button" class="ek-btn" data-ek-consent-choice="grant">Souhlasím</button>
	</div>
</div>
	<?php
} );

// Odkaz v patičce pro změnu volby.
function ek_consent_link(): void {
	if ( ek_ga_id() ) {
		echo '<button type="button" class="ek-linkbtn" data-ek-consent-open>Nastavení cookies</button>';
	}
}
