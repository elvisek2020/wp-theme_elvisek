<?php
/**
 * Stránka nenalezena: hláška, hledání, nejnovější články a témata.
 */
defined( 'ABSPATH' ) || exit;

get_header();

$ek_recent = new WP_Query( array(
	'posts_per_page'      => 6,
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
) );
?>
<main id="obsah" class="ek-main">
	<div class="ek-wrap ek-stack">
		<section class="ek-404">
			<p class="ek-404__code"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="Zpět na úvod" aria-label="404 – zpět na úvod">404</a></p>
			<h1 class="ek-pagehead__title">Tahle stránka neexistuje</h1>
			<p class="ek-404__shell" aria-hidden="true"><span>$</span> sudo make it work<br><span class="ek-404__err">make: *** No rule to make target „tahle-stránka“. Stop.</span></p>
			<p class="ek-muted">Ani <code>sudo</code> tady nepomohl. Možná se článek přesunul, nebo je v adrese překlep. Zkuste něco z nejnovějšího:</p>
		</section>

		<?php if ( $ek_recent->have_posts() ) : ?>
			<section class="ek-section" aria-labelledby="ek-404-recent">
				<div class="ek-section__head"><h2 id="ek-404-recent" class="ek-section__title">Nejnovější články</h2></div>
				<div class="ek-grid">
					<?php
					while ( $ek_recent->have_posts() ) {
						$ek_recent->the_post();
						get_template_part( 'template-parts/card' );
					}
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/topics' ); ?>
	</div>
</main>
<?php
get_footer();
