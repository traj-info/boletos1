<?php
/*
Plugin Name: Trajettoria Boletos
Plugin URI: http://www.trajettoria.com
Description: Plugin para emissao e gerenciamento de boletos bancarios
Author: Trajettoria
Version: 1.0
Author URI: http://www.trajettoria.com
*/

// Constantes:
define('STATUS_BOLETO_EM_ABERTO', 0);

// Includes:
require_once('/includes/util.php');
require_once('/includes/class.phpmailer.php');
require_once('/includes/clas.smtp.php');

class TrajettoriaBoletos {
	
	/*
	 * 
	 */
	public static function install() {}
	
	/*
	 * 
	 */
	public static function initialize() {}
	
	/*
	 * 
	 */


}

register_activation_hook( __FILE__, array( 'TrajettoriaBoletos', 'install' ) );
add_filter( 'init', array( 'TrajettoriaBoletos', 'initialize' ) );
add_shortcode( 'exemplo', array( 'TrajettoriaBoletos', 'nomeDaFuncao' ) );

?>