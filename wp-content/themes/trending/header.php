<?php
/**
 * Header Template
 *
 * The header template is generally used on every page of your site. Nearly all other templates call it 
 * somewhere near the top of the file. It is used mostly as an opening wrapper, which is closed with the 
 * footer.php file. It also executes key functions needed by the theme, child themes, and plugins. 
 *
 * @package Trending
 * @subpackage Template
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>" />
<title><?php hybrid_document_title(); ?></title>

<link rel="stylesheet" href="<?php echo get_stylesheet_uri(); ?>" type="text/css" media="all" />
<link rel="profile" href="http://gmpg.org/xfn/11" />
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />

<?php wp_head(); // wp_head ?>

</head>

<body class="<?php hybrid_body_class(); ?>">

	<?php do_atomic( 'open_body' ); // trending_open_body ?>

	<div id="container">

		<?php do_atomic( 'before_header' ); // trending_before_header ?>

		<div id="header">

			<?php do_atomic( 'open_header' ); // trending_open_header ?>

			<div class="wrap">

				<div id="branding">
					<?php hybrid_site_title(); ?>
					<?php hybrid_site_description(); ?>
				</div><!-- #branding -->

				<?php get_sidebar( 'header' ); // Loads the sidebar-header.php template. ?>

				<?php do_atomic( 'header' ); // trending_header ?>

			</div><!-- .wrap -->

			<?php do_atomic( 'close_header' ); // trending_close_header ?>

		</div><!-- #header -->

		<?php do_atomic( 'after_header' ); // trending_after_header ?>

		<?php get_template_part( 'menu', 'primary' ); // Loads the menu-primary.php template. ?>

		<?php do_atomic( 'before_main' ); // trending_before_main ?>

		<div id="main">

			<div class="wrap">

			<?php do_atomic( 'open_main' ); // trending_open_main ?>

			<?php if ( current_theme_supports( 'breadcrumb-trail' ) ) breadcrumb_trail( array( 'before' => __( 'You are here:', hybrid_get_textdomain() ) ) ); ?>

			<?php get_sidebar( 'before-content' ); // Loads the sidebar-before-content.php template. ?>