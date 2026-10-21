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
			<div class="ek-grid" data-ekt-grid>
				<?php
				while ( have_posts() ) {
					the_post();
					get_template_part( 'template-parts/card' );
				}
				?>
			</div>
			<?php echo ekt_load_more(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapuje modul ?>
		<?php else : ?>
			<p>Zatím tu nic není.</p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
