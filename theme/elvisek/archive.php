<?php
/**
 * Archiv: kategorie, štítek, datum.
 */
defined( 'ABSPATH' ) || exit;

get_header();
$ek_term = get_queried_object();
?>
<main id="obsah" class="ek-main">
	<header class="ek-wrap ek-pagehead">
		<?php if ( is_category() && $ek_term instanceof WP_Term ) : ?>
			<span class="ek-pagehead__icon"><?php echo ekt_icon( ek_category_icon_name( $ek_term->slug ), 26 ); ?></span>
		<?php endif; ?>
		<div>
			<h1 class="ek-pagehead__title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
			<?php if ( $ek_desc = get_the_archive_description() ) : ?>
				<div class="ek-pagehead__desc"><?php echo wp_kses_post( $ek_desc ); ?></div>
			<?php elseif ( $ek_term instanceof WP_Term ) : ?>
				<p class="ek-pagehead__desc"><?php echo esc_html( ekt_plural( (int) $ek_term->count, '%d článek', '%d články', '%d článků' ) ); ?></p>
			<?php endif; ?>
		</div>
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
			<p>V této sekci zatím nejsou žádné články.</p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
