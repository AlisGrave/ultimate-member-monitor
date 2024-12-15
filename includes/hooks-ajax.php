<?php
defined("ABSPATH") || die('die');

/**
 * Hooks for Ajax actions, scripts and styles
 */

final class UMM_hooks_ajax {

	static private $_ran = false;

	static public function run() {
		if ( self::$_ran ) return;

		self::$_ran = true; 

		self::_init();
	}

	# Prefix for scripts and hooks and various stuff
	static $PREFIX = 'umon_';

	static function getAjaxAction() {
		return self::$PREFIX.'ajax';
	}
	static function getLibName( $library ) {
		return self::$PREFIX.sanitize_key($library);
	}

	private static function _init() {

		# Register scripts and styles
		add_action( 'wp_default_scripts', [__CLASS__, 'wp_default_scripts'], 99 );
		add_action( 'wp_default_styles', [__CLASS__, 'wp_default_styles'], 10 );

		#
		add_action( 'wp_enqueue_scripts', [__CLASS__, 'wp_enqueue_scripts'] );

		# Register ajax action - for logged in and anon users
		add_action( 'wp_ajax_'.self::getAjaxAction(), [__CLASS__, 'ajax_action'] );
		add_action( 'wp_ajax_nopriv_'.self::getAjaxAction(), [__CLASS__, 'ajax_action'] );

		add_action( self::$PREFIX.'addlib', [__CLASS__, 'include_lib'], 10, 2 );

		# Custom parse_request for the service worker
		add_action('parse_request', [__CLASS__, 'init_parse_request_sw'], 3 );
	}

	# Virtual file
	private static $service_worker_file_abspath = 'sw_umon.js';
	# Real file
	private static $serivce_worker_file_plugin  = 'assets/sw.js';

	/**
	 * Parse each request and check for specific file in ABSPATH
	 * i.e. www.domain.com/{specific-file.js}
	 * otherwise service worker wont work
	 */
	static function init_parse_request_sw( $wp ) {
		if ( 	$wp->request == self::$service_worker_file_abspath 
			 && file_exists( UMM_DIR.self::$serivce_worker_file_plugin )
			) {
			nocache_headers();
			header('Content-Type: application/javascript');
			header('Service-Worker-Allowed: /');
			header('Service-Worker: script');
			readfile( UMM_DIR.'assets/sw.js' );
			die();
		}
	}

	static function wp_default_scripts( WP_Scripts $wp_scripts ) {

		# Register Charts
		$wp_scripts->add( self::getLibName('apexcharts'), UMM_URL.'assets/apexcharts/apexcharts.min.js', ['jquery'], '3.50.0', true );

		# Register DT
		$wp_scripts->add( self::getLibName('dt'), UMM_URL.'assets/DataTables/datatables.min.js', ['jquery'], '2.0.5', true );

		# Register FUI
		$wp_scripts->add( self::getLibName('fui'), UMM_URL.'assets/fomantic/semantic.min.js', ['jquery'], '2.9.3', true );
		//$wp_scripts->add( self::getLibName('fui'), 'https://cdn.jsdelivr.net/npm/fomantic-ui@2.9.3/dist/semantic.min.js', ['jquery'], '2.9.3', true );

		# Register DRP
		//$wp_scripts->add( self::getLibName('drp'), UMM_URL.'assets/daterangepicker/daterangepicker.min.js', ['jquery','moment'], '3.1.0', true );

		# Register Swal
		$wp_scripts->add( self::getLibName('swal'), UMM_URL.'assets/SweetAlert/sweetalert2.all.min.js', ['jquery'], '11.14.5', true );

		# Register js ping
		$wp_scripts->add( self::getLibName('ping'), UMM_URL.'assets/ping.js', [], '0.21.15', true );

		# Register single-asset
		// $jsver = filemtime(UMM_DIR.'assets/single-asset.js');
		// $wp_scripts->add( self::getLibName('sinass'), UMM_URL.'assets/single-asset.js', [], $jsver, true );

		# Register DT-Hightlighter - 2 files, depends on DT
		// $wp_scripts->add(
		// 	self::getLibName('dt-hglt-jq'),
		// 	UMM_URL.'assets/highlight/jquery.highlight.js',
		// 	['jquery', self::getLibName('dt') ],
		// 	'2009',
		// 	true
		// );
		// $wp_scripts->add(
		// 	self::getLibName('dt-hglt'),
		// 	UMM_URL.'assets/highlight/highlight.js',
		// 	[ self::getLibName('dt-hglt-jq') ],
		// 	'2009',
		// 	true
		// );
	}

	static function wp_default_styles( WP_Styles $wp_styles ) {
		
		$wp_styles->add( self::getLibName('apexcharts'), UMM_URL.'assets/apexcharts/apexcharts.css', [], '3.50.0', 'all' );
		
		$wp_styles->add( self::getLibName('dt'), UMM_URL.'assets/DataTables/datatables.min.css', [], '2.0.5', 'all' );
		
		$wp_styles->add( self::getLibName('fui'), UMM_URL.'assets/fomantic/semantic.min.css', [], '2.9.3', 'all' );
		//$wp_styles->add( self::getLibName('fui'), 'https://cdn.jsdelivr.net/npm/fomantic-ui@2.9.3/dist/semantic.min.css', [], '2.9.3', 'all' );
		
		$wp_styles->add( self::getLibName('swal'), UMM_URL.'assets/SweetAlert/sweetalert2.min.css', [], '11.14.5' );
		
		// $wp_styles->add( self::getLibName('drp'), UMM_URL.'lib/daterangepicker/daterangepicker.css', [], '3.1.0', 'all' );

		// $wp_styles->add(
		// 	self::getLibName('dt-hglt'),
		// 	UMM_URL.'lib/highlight/highlight.css',
		// 	[],
		// 	'2009'
		// );
	}

	/**
	 * @param array $libs  - libs to include
	 * @param array $VARS  - extra vars for some libs
	 */
	static function include_lib( $libs=[], $VARS=[] ) {
		$libs = (array)$libs;

		foreach( $libs as $library ) {
			switch ($library) {

				case 'dt':
				case 'fui':
				case 'drp':
				case 'swal':
				case 'dt-hglt':
				case 'apexcharts':
					wp_enqueue_style( self::getLibName($library) );
					wp_enqueue_script( self::getLibName($library) );
					break;

				case 'ping':
					wp_enqueue_script( self::getLibName($library) );
					break;

				// case 'sinass':
				// 	# @see self::init_parse_request_sw()
				// 	$ver_s_worker = filemtime( UMM_DIR.self::$serivce_worker_file_plugin );
				// 	$ver_w_worker = filemtime( UMM_DIR.'assets/ww.js' );

				// 	wp_localize_script( 
				// 		self::getLibName($library),  
				// 		'SAVARS',
				// 		[
				// 			'servworker' => trailingslashit( site_url() ).self::$service_worker_file_abspath.'?v='.$ver_s_worker,
				// 			'webworker'  => UMM_URL.'assets/ww.js?v='.$ver_w_worker,
				// 		]
				// 	);
				// 	wp_enqueue_script( self::getLibName($library) );
				// 	break;

				case 'ajax':
					$JS = 'window.wp = window.wp || {};
					window.wp.ajax.umm = function( options ){
						options.data.nonce = "'.wp_create_nonce( self::getAjaxAction() ).'";
						return wp.ajax.send( "'.self::getAjaxAction().'", options );
					};
					';
					wp_add_inline_script( 'wp-util', $JS, 'after' );
					# Localize wp-util with additional vars
					wp_localize_script(
						'wp-util',
						'_umm',
						self::ajax_vars( $VARS )
					);
					wp_enqueue_script( 'wp-util');

					break;

			}
		}

	}

	static function wp_enqueue_scripts() {
		# Do no enqeue, leave it on demand
	}

	###########################################################################

	static function ajax_action() {

		//header( 'X-ST:'.wp_get_session_token() );
		# Force show dump, even for non-admins
		$show_dump = TRUE;

		$nonce = ( !empty($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '' );
		$task  = ( !empty($_POST['task']) ? sanitize_key($_POST['task']) : '' );

		if ( empty($nonce) || !wp_verify_nonce( $nonce, self::getAjaxAction() ) ) {
			wp_send_json_error( __('Your session has expired', UMM_DOMAIN), 419, JSON_UNESCAPED_UNICODE );
		}
		# Wrap in try-catch to hide system errors
		try {
			if ( is_user_logged_in() ) {
				\umm\AJAX_CB::task($task);
			}
			else {
				\umm\AJAX_CB::nopriv_task($task);
			}
		}
		catch( \Exception $e ) {
			# Only if user has admin capabilities
			$err = __('Възникна грешка! Моля опитайте пак.', UMM_DOMAIN);
			if ( !empty($show_dump) || current_user_can( 'manage_options') ) {
				$err = '<pre>'.(string)$e.'</pre>';
			}
			wp_send_json_error( $err, 500 );
		}
		catch( \Throwable $t ) {
			# Only if user has admin capabilities
			$err = __('Възникна грешка! Моля опитайте пак.', UMM_DOMAIN);
			if ( !empty($show_dump) || current_user_can( 'manage_options') ) {
				$err = '<pre>'.(string)$t.'</pre>';
			}
			wp_send_json_error( $err, 500 );
		}

		# Default error if no task was executed or an exception thrown
		wp_send_json_error( __('Your actions are BAD and you should feel BAD!', UMM_DOMAIN), 400, JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Returns default ajax vars merged with given $VARS
	 */
	static function ajax_vars( $VARS=[] ) {
		$VARS = (array)$VARS;

		global $wp_locale;
		$locale_dr = [
			'format' 			=> 'DD.MM.YYYY',
			'separator' 		=> ' - ',
			'applyLabel' 		=> __('Избери', UMM_DOMAIN),
			'cancelLabel' 		=> __('Затвори', UMM_DOMAIN),
			'fromLabel' 		=> __('От', UMM_DOMAIN),
			'toLabel' 			=> __('До', UMM_DOMAIN),
			'customRangeLabel' 	=> __('Друго', UMM_DOMAIN),
			'weekLabel' 		=> 'С',
			'daysOfWeek' 		=> array_values( $wp_locale->weekday_initial ),
			'monthNames' 		=> array_values( $wp_locale->month ),
			'firstDay'			=> 1,
		];
		$locale_dt = [
			'sEmptyTable' 		=> __('Няма данни', UMM_DOMAIN),
			'search'			=> '',
			'searchPlaceholder' => __('Търси', UMM_DOMAIN),
			'info' 				=> sprintf( __("%s - %s от %s", UMM_DOMAIN), '_START_', '_END_', '_TOTAL_' ),
			'infoEmpty' 		=> "",
			'infoFiltered' 		=> sprintf( __("(филтрирани от %s записа)", UMM_DOMAIN), '_MAX_'),
			'lengthMenu' 		=> sprintf( __("Покажи %s", UMM_DOMAIN), '_MENU_', ),
			'paginate' 			=> array(
				'first'		=> __("Първа", UMM_DOMAIN),
				'last'		=> __("Последна", UMM_DOMAIN),
				'next'		=> __("Следваща", UMM_DOMAIN),
				'previous'	=> __("Предишна", UMM_DOMAIN),
			)
		];
		return wp_parse_args( $VARS, [
			'locale_dt' => $locale_dt,
			'locale_dr'	=> $locale_dr,
		]);
	}
}

UMM_hooks_ajax::run();