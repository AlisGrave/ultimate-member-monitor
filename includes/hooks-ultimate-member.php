<?php
defined("ABSPATH") || die('die');

/**
 * Hooks for Ultimate Member plugin
 */

final class UMM_hooks_um {

	static private $_ran = false;

	static public function run() {
		if ( self::$_ran ) return;

		self::$_ran = true;

		# Filter main options for UM plugin to include virutal tabs as options
		# Problem: this hook runs BEFORE user is known, and w/o the user tabs are unknown
		# Solution: fix in Plugin UM -> /includes/core/class-options.php : 45
		//add_filter( 'option_um_options', [__CLASS__, 'option_um_options'], 20, 2);

		# Call on init after user is authenticated
		add_action('init', [__CLASS__,'init'], 1);
	}

	public static function init() {

		# Set tabs for the user
		# user must be authenticated
		self::_initTabs();

		# Modify tabs for UM
		add_filter( 'um_profile_tabs', [__CLASS__, 'um_profile_tabs'], 1000 );

		# Check an ability to view tab - not used
		//add_filter( 'um_user_profile_tabs', [__CLASS__, 'um_user_profile_tabs'], 2000, 1 );		

		foreach( self::$UM_TABS as $tab => $tdata ) {
			# Add magic static callback @see __callStatic
			add_action( "um_profile_content_{$tab}", [__CLASS__, 'um_profile_content_'.$tab], 2000 );
		}
	}

	/**
	 * Filters the value of an existing option.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 1.5.0 As 'option_' . $setting
	 * @since 3.0.0
	 * @since 4.4.0 The `$option` parameter was added.
	 *
	 * @param mixed  $value  Value of the option. If stored serialized, it will be
	 *                       unserialized prior to being returned.
	 * @param string $option Option name.
	 */
	static function option_um_options( $value, $option ) {
		return $value;
	}

	private static $UM_TABS = [];
	private static function _initTabs(){
		# Create one tab for each added asset
		self::$UM_TABS = [];

		/*
		* There are values for 'default_privacy' atribute
		* 0 - Anyone,
		* 1 - Guests only,
		* 2 - Members only,
		* 3 - Only the owner
		*/

		# Add Main Tab
		self::$UM_TABS['umm_main'] = [
			'name' 				=> 'Dashboard',
			'icon' 				=> 'um-faicon-desktop',
			'role'				=> 'um_member',
			'template'			=> 'tab-main.php',
			'default_privacy'	=> 2,
		];

		self::$UM_TABS['umm_addasset'] = [
			'name' 				=> 'Add assets',
			'icon' 				=> 'um-faicon-plus-square', 
			'role'				=> 'um_member',
			'template'			=> 'tab-addasset.php',
			'default_privacy'	=> 2,
		];
		$tab_id = 'umm_addasset';
		add_filter( "um_get_option_filter__profile_tab_{$tab_id}", function(){
			return true;
		}, 20);

		# Get Current User's assets
		$UserAss = new \umm\UserAssets();
		//d( $UserAss->getList() );
		foreach( $UserAss->getList() as $id => $ass ) {

			# Add tab
			$tab_id = 'umm_asset_'.$id;
			self::$UM_TABS[ $tab_id ] = [
				'name' 				=> $ass['host'],
				//'icon' 				=> 'um-faicon-eye',
				'icon' 				=> 'um-faicon-area-chart',
				'role'				=> 'um_member', 
				'template'			=> 'tab-single-asset.php',
				'asset_id'			=> $id,
				'default_privacy'	=> 2,
			];

			# Add filter to allow tab to be displayed
			# Does not work, since these tabs are not in options and will always return no
			add_filter( "um_get_option_filter__profile_tab_{$tab_id}", function(){
				return true;
			}, 20);
		}

		# Add final Debug tab (Admin only)
		// self::$UM_TABS['umm_debug'] = [
		// 	'name' 				=> 'Debug',
		// 	'icon' 				=> 'um-faicon-bug',
		// 	'role'				=> 'administrator',
		// 	'template'			=> 'tab-debug.php',
		// 	'default_privacy'	=> 2,
		// ];

		//d( self::$UM_TABS );
	}

	static public function um_profile_tabs( $tabs ) {
		foreach( self::$UM_TABS as $tab => $tdata ) {
			$tabs[$tab] = $tdata;
		}
		//d( $tabs );
		return $tabs;
	}

	/**
	 * Check user's ability to view tab
	 *
	 * @param $tabs
	 *
	 * @return mixed
	 */
	static function um_user_profile_tabs( $tabs ) {
		// if ( empty( $tabs['mycustomtab'] ) ) {
		// 	return $tabs;
		// }

		$user_id = um_profile_id();
		$user = wp_get_current_user();

		// if ( ! user_can( $user_id, 'um_member' ) ) {
		// 	unset( $tabs['mycustomtab'] );
		// }

		// if ( !in_array( 'author', (array) $user->roles ) ) {
		//     # Unset tabs
		// }

		//d( __FUNCTION__, $tabs );

		return $tabs;
	}


	/**
	 * Magic call static for tab contents
	 */
	public static function __callStatic($name, $arguments) {
		# Check called static method, if starts with 'um_profile_content_', extract rest as tab name
		// $_starts = 'um_profile_content_';
		// $c = strlen($_starts);
		// $starts = substr( $name, 0, $c);
		// $tab = ( strlen($name) > ($c+1) ? substr($name, $c) : '' );
		// if ( $starts != $_starts || empty($tab) || empty(self::$UM_TABS[$tab]) ) return; # disregard silently
		
		//d( get_defined_vars() );
		


		//d(  UM()->profile(), UM()->profile()->tabs(), UM()->profile()->active_tab() );
		$tabs 	 = UM()->profile()->tabs();
		$active_tab = UM()->profile()->active_tab();
		
		$the_tab = isset($tabs[$active_tab]) ? $tabs[$active_tab] : false;
		//$the_tab = isset(self::$UM_TABS[$active_tab]) ? self::$UM_TABS[$active_tab] : false;
		
		if ( !$the_tab ) {
			echo 'Unknown tab';
			return;
		}

		$templ = $tabs[$active_tab]['template'];
		$path = UMM_DIR . 'templates/'.$templ;

		if ( !file_exists($path) ) {
			echo 'Missing template.';
			return;
		}

		include $path;
	}
	
}


UMM_hooks_um::run();