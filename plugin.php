<?php
/*
 * Plugin Name:       Monitor extension to UM plugin
 * Plugin URI:        https://localhost
 * Description:       Adds custom extras to Ultimate Member plugin for Monitor instance
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            SKG
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       um-mon
 * Domain Path:       /languages
 * Requires Plugins:  ultimate-member
 */ 

 
# Exit if accessed directly
defined( 'ABSPATH' ) || die('nani?');

define( 'UMM_VERSION', '1.0.0' );
define( 'UMM_DOMAIN', 'umm' );
//define( 'UMM_REST_NS', 'umm' );

define( 'UMM_DIR', plugin_dir_path( __FILE__ ) );
define( 'UMM_URL', plugin_dir_url( __FILE__ ) );
define( 'UMM_FILE', __FILE__ );
define( 'UMM_SLUG', plugin_basename( __FILE__ ) );


final class UMM {
	private static $_ran = false;

	public static function run(){
		if ( self::$_ran ) return;

		self::$_ran = true;

		self::_init();
	}

	private static function _init(){
		self::_autoloaders();
		self::_includes();
	}

	###########################################################################

	private static function _autoloaders() {
		# Custom autoload
		spl_autoload_register( array(__CLASS__, 'autoload') );

		# Composer autoloader
		// include_once UMM_DIR . 'vendor/autoload.php';
	}

	CONST BASE_NS = 'umm';

	public static function autoload( $className ) {
		# Autoload files with specific prefix and suffix in specific directories
		$ns_prefix   = self::BASE_NS."\\";
		$file_prefix = '';
		$file_suffix = ['.php'];

		$dirs = [
			UMM_DIR . 'classes/',
			UMM_DIR . 'crons/',
		];

		# Remove left backslash from className
		$className = ltrim($className, "\\");

		# Target Filename to search for
		$targetFilename = '';

		# Condition for ns prefix is the prefix
		if ( substr($className, 0, strlen($ns_prefix)) == $ns_prefix ) {
			$targetFilename = substr( $className, strlen($ns_prefix) );
			$targetFilename = str_replace('\\', '/', $targetFilename);
		}
		# Condition for filename prefix is name of the class to start with specific string
		elseif ( substr($className, 0, strlen($file_prefix)) == $file_prefix ) {
			$targetFilename = $className;
		}
		else {
			# Not interested
			return;
		}

		foreach( $dirs as $dir ) {

			foreach( $file_suffix as $fs ) {
				if ( file_exists( $dir . $targetFilename. $fs ) ) {
					include_once $dir.$targetFilename.$fs;
					return;
				}
			}
		}
	}

	private static function _includes(){
		# Include required files

		# Hooks for Ultimate Member plugin
		include_once UMM_DIR . 'includes/hooks-ultimate-member.php';

		# Hooks for ajax (scripts and styles also)
		include_once UMM_DIR . 'includes/hooks-ajax.php';
	}
}

UMM::run();