<?php
/**
 * Stránka nenalezena.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="obsah" class="ek-main">
	<div class="ek-wrap ek-404">
		<p class="ek-404__code">404</p>
		<h1 class="ek-pagehead__title">Tahle stránka neexistuje</h1>
		<p class="ek-muted">Možná se přesunula, nebo je v adrese překlep. Zkuste hledání:</p>
		<?php get_search_form(); ?>
		<p><a class="ek-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Zpět na úvod</a></p>
	</div>
</main>
<?php
get_footer();
