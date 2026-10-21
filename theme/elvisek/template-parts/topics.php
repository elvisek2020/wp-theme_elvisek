<?php
/**
 * Dlaždice témat (kategorie s nejvíce články).
 */
defined( 'ABSPATH' ) || exit;

$ek_topics = ek_topics( (int) ekt_opt( 'site_topics_count' ) ?: 8 );
if ( ! $ek_topics ) {
	return;
}
?>
<section class="ek-section" aria-labelledby="ek-topics-title">
	<div class="ek-section__head">
		<h2 id="ek-topics-title" class="ek-section__title">Témata</h2>
	</div>
	<ul class="ek-topics">
		<?php foreach ( $ek_topics as $cat ) : ?>
			<li>
				<a class="ek-topic" href="<?php echo esc_url( get_category_link( $cat ) ); ?>"<?php echo ekt_term_color_style( $cat ); ?>>
					<span class="ek-topic__icon"><?php echo ekt_icon( ek_category_icon_name( $cat->slug ), 20 ); ?></span>
					<span class="ek-topic__name"><?php echo esc_html( $cat->name ); ?></span>
					<span class="ek-topic__count"><?php echo esc_html( ekt_plural( (int) $cat->count, '%d článek', '%d články', '%d článků' ) ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
