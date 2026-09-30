<?php
/**
 * Statická stránka.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="obsah" class="ek-main">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'ek-wrap ek-page' ); ?>>
			<header class="ek-article__head">
				<h1 class="ek-article__title"><?php the_title(); ?></h1>
			</header>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="ek-article__hero ek-thumb<?php echo ek_thumb_is_logo( get_post_thumbnail_id() ) ? ' ek-article__hero--logo' : ''; ?>"><?php the_post_thumbnail( 'ek-hero', array( 'class' => 'ek-thumb__img' ) ); ?></figure>
			<?php endif; ?>
			<div class="ek-content entry-content">
				<?php the_content(); ?>
			</div>
			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</article>
	<?php endwhile; ?>
</main>
<?php
get_footer();
