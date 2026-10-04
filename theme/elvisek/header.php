<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="ek-skip" href="#obsah">Přeskočit na obsah</a>
<?php
$ek_is_hero = is_home() && ! is_paged();
$ek_topics  = ek_nav_topics();
?>

<?php if ( $ek_is_hero ) : ?>
	<header class="ek-hero-head" data-ek-hero-head>
		<div class="ek-wrap">
			<div class="ek-hero-head__top">
				<?php ek_logo(); ?>
				<?php ek_header_tools( 'ek-search-hero' ); ?>
			</div>
			<nav class="ek-tiles" aria-label="Témata">
				<ul style="<?php echo esc_attr( ek_tiles_style( count( $ek_topics ) ) ); ?>">
					<?php foreach ( $ek_topics as $cat ) : ?>
						<li>
							<a class="ek-tile" href="<?php echo esc_url( get_category_link( $cat ) ); ?>"<?php echo ek_cat_style( $cat ); ?>>
								<span class="ek-tile__icon"><?php echo ek_icon( ek_category_icon_name( $cat->slug ), 19 ); ?></span>
								<span class="ek-tile__name"><?php echo esc_html( $cat->name ); ?></span>
								<span class="ek-tile__count"><?php echo esc_html( ek_plural( (int) $cat->count, 'článek', 'články', 'článků' ) ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		</div>
	</header>
<?php endif; ?>

<<?php echo $ek_is_hero ? 'div' : 'header'; ?> class="ek-bar<?php echo $ek_is_hero ? ' ek-bar--floating' : ''; ?>" <?php echo $ek_is_hero ? 'aria-hidden="true" inert' : ''; ?> data-ek-bar>
	<div class="ek-wrap ek-bar__inner">
		<?php ek_logo( true ); ?>
		<<?php echo $ek_is_hero ? 'div' : 'nav'; ?> class="ek-chipsnav<?php echo count( $ek_topics ) > 8 ? ' ek-chipsnav--many' : ''; ?>" aria-label="Témata" data-ek-chipsnav>
			<ul>
				<?php foreach ( $ek_topics as $cat ) : ?>
					<li class="<?php echo ek_is_current_topic( $cat ) ? 'is-current' : ''; ?>">
						<a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"<?php echo is_category( $cat->term_id ) ? ' aria-current="page"' : ''; ?>>
							<?php echo ek_icon( ek_category_icon_name( $cat->slug ), 16 ); ?><span><?php echo esc_html( $cat->name ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</<?php echo $ek_is_hero ? 'div' : 'nav'; ?>>
		<?php ek_header_tools( 'ek-search' ); ?>
		<?php if ( $ek_topics ) : ?>
			<button type="button" class="ek-iconbtn ek-menubtn" data-ek-menu-toggle aria-expanded="false" aria-controls="ek-menu" aria-label="Témata">
				<span class="ek-menubtn__open"><?php echo ek_icon( 'menu' ); ?></span><span class="ek-menubtn__close"><?php echo ek_icon( 'close' ); ?></span>
			</button>
		<?php endif; ?>
	</div>
	<?php if ( $ek_topics ) : ?>
		<div class="ek-menu" id="ek-menu" data-ek-menu hidden>
			<div class="ek-wrap">
				<ul class="ek-menu__list">
					<?php foreach ( $ek_topics as $cat ) : ?>
						<li>
							<a class="ek-menu__item<?php echo ek_is_current_topic( $cat ) ? ' is-current' : ''; ?>" href="<?php echo esc_url( get_category_link( $cat ) ); ?>"<?php echo is_category( $cat->term_id ) ? ' aria-current="page"' : ''; ?>>
								<span class="ek-menu__icon"><?php echo ek_icon( ek_category_icon_name( $cat->slug ), 18 ); ?></span>
								<span class="ek-menu__text">
									<span class="ek-menu__name"><?php echo esc_html( $cat->name ); ?></span>
									<span class="ek-menu__count"><?php echo esc_html( ek_plural( (int) $cat->count, 'článek', 'články', 'článků' ) ); ?></span>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>
</<?php echo $ek_is_hero ? 'div' : 'header'; ?>>
