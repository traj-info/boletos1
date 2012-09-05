<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the id=main div and all content
 * after.  Calls sidebar-footer.php for bottom widgets.
 *
 * @package Cryout Creations
 * @subpackage mantra
 * @since mantra 0.5
 */
?>	<div style="clear:both;"></div>
	</div> <!-- #forbottom -->
	</div><!-- #main -->


	<footer id="footer" role="contentinfo">
		<div id="colophon">

<?php
	/* A sidebar in the footer? Yep. You can can customize
	 * your footer with four columns of widgets.
	 */
	get_sidebar( 'footer' );
?><?php
$mantra_options= mantra_get_theme_options();
foreach ($mantra_options as $key => $value) {	
     ${"$key"} = $value ;
}
        $mantra_theme_data = get_transient( 'cryout_theme_info'); 
?>


		</div><!-- #colophon -->

		<div id="footer2">
<?php if ( has_nav_menu( 'footer' ) ) wp_nav_menu( array( 'container' => 'nav', 'container_class' => 'footermenu', 'theme_location' => 'footer' ) ); ?>
			<div id="site-info" >
				<a href="<?php echo home_url( '/' ) ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">&copy; <?php echo date('Y'); ?> <?php bloginfo( 'name' ); ?></a>. Todos os direitos reservados.
			</div>

			<!-- #site-info -->
	<?php if ($mantra_copyright != '') { ?><div id="site-copyright"><?php echo $mantra_copyright; ?> </div> <?php } ?>

			<div class="socials" id="sfooter">
<?php if($mantra_socialsdisplay3) mantra_set_social_icons(); ?>
</div>

</div>

	</footer><!-- #footer -->

</div><!-- #wrapper -->

<?php
	/* Always have wp_footer() just before the closing </body>
	 * tag of your theme, or you will break many plugins, which
	 * generally use this hook to reference JavaScript files.
	 */

	wp_footer();
?>

</body>
</html>
