<?php
/**
 * Záložní šablona.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="obsah" class="ek-main">
	<div class="ek-wrap ek-stack">
		<?php if ( have_posts() ) : ?>
			<div class="ek-grid" data-ek-grid>
				<?php
				while ( have_posts() ) {
					the_post();
					get_template_part( 'template-parts/card' );
				}
				?>
			</div>
			<?php ek_load_more(); ?>
		<?php else : ?>
			<p>Zatím tu nic není.</p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
