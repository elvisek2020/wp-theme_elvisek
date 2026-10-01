<?php
/**
 * Komentáře.
 */
defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
$ek_count = (int) get_comments_number();
?>
<section id="komentare" class="ek-comments">
	<?php if ( $ek_count ) : ?>
		<details class="ek-comments__box" data-ek-comments>
		<summary class="ek-comments__summary">
			<h2 class="ek-compose__title"><?php echo esc_html( 'Komentáře (' . $ek_count . ')' ); ?></h2>
			<span class="ek-comments__toggle"><span class="ek-comments__show">Zobrazit</span><span class="ek-comments__hide">Skrýt</span><?php echo ek_icon( 'chevron', 18 ); ?></span>
		</summary>
		<ol class="ek-comments__list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 0,
				'reply_text'  => 'Odpovědět',
			) );
			?>
		</ol>
		<?php the_comments_navigation( array( 'prev_text' => '← Starší komentáře', 'next_text' => 'Novější komentáře →' ) ); ?>
		</details>
	<?php endif; ?>

	<?php if ( comments_open() ) : ?>
		<div class="ek-comments__form" data-ek-compose>
			<?php
			comment_form( array(
				'title_reply'          => 'Napsat komentář',
				'title_reply_before'   => '<h2 id="reply-title" class="ek-compose__title">',
				'title_reply_after'    => '</h2>',
				'label_submit'         => 'Odeslat komentář',
				'class_submit'         => 'ek-btn',
				'comment_notes_before' => '<p class="comment-notes">E-mail nebude zveřejněn.</p>',
				'comment_field'        => '<p class="comment-form-comment"><label for="comment" class="screen-reader-text">Komentář</label><textarea id="comment" name="comment" rows="4" placeholder="Napište komentář…" required></textarea></p>',
			) );
			?>
		</div>
	<?php elseif ( $ek_count ) : ?>
		<p class="ek-muted">Komentáře jsou u tohoto článku uzavřené.</p>
	<?php endif; ?>
</section>
