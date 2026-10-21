<?php
/**
 * Výsledky hledání.
 */
defined( 'ABSPATH' ) || exit;

get_header();
global $wp_query;
?>
<main id="obsah" class="ek-main">
	<header class="ek-wrap ek-pagehead ek-pagehead--search">
		<div>
			<h1 class="ek-pagehead__title">Hledání: „<?php echo esc_html( get_search_query() ); ?>“</h1>
			<p class="ek-pagehead__desc"><?php echo esc_html( 'Nalezeno: ' . ekt_plural( (int) $wp_query->found_posts, '%d výsledek', '%d výsledky', '%d výsledků' ) ); ?></p>
		</div>
		<div class="ek-pagehead__form"><?php get_search_form(); ?></div>
	</header>

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
			<div class="ek-empty">
				<p>Nic jsem nenašla. Zkuste jiné slovo, nebo projděte témata:</p>
				<?php get_template_part( 'template-parts/topics' ); ?>
			</div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
