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
			<p class="ek-pagehead__desc"><?php echo esc_html( 'Nalezeno: ' . ek_plural( (int) $wp_query->found_posts, 'výsledek', 'výsledky', 'výsledků' ) ); ?></p>
		</div>
		<div class="ek-pagehead__form"><?php get_search_form(); ?></div>
	</header>

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
			<?php ek_load_more( 'Další výsledky' ); ?>
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
