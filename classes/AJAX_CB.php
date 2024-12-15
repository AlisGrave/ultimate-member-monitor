<?php
namespace umm;

use UMM as PluginMain;
use WP_Error;
use Exception;

defined('ABSPATH') || die();

/**
 * Class for AJAX_CB tasks
 */

class AJAX_CB {

	/**
	 * This method does NOT check ajax nonces, must be checked from calling function
	 * i.e. from hooks-ajax.php
	 *
	 * @return NONE - directly calls wp_send_json[success|error] and in rare cases maybe plain text
	 * @note   if nothing is returned or an exception is raised, the calling function will output an error
	 */
	static function task( $task, $POST=null ) {

		$POST = (array)( is_null($POST) ? $_POST : $POST );

		# Task is admin (i.e. starts with admin-)
		$prefix = explode('-', $task, 2);
		if ( 	$prefix[0] == 'admin'
			 && !(
					   current_user_can('manage_options')
					|| current_user_can('cap_sgam_admin')
				)
			) {
			wp_send_json_error( __('Forbidden', UMM_DOMAIN) );
		}

		switch ($task) {

		# Return single user's assets
		// case 'get-assets': {
		// 	$uid = get_current_user_id();
		// 	# List is keyed by id, remove so JS wont interpret as object
		// 	$list = (new UserAssets( $uid ))->getList( ['as-array'=>TRUE] );
		// 	wp_send_json_success( $list );
		// }

		# Saves new asset
		case 'asset-add':
			$uid = get_current_user_id();
			$data = !empty($_POST['data']) ? (array)$_POST['data'] : [];

			$UASS = new UserAssets($uid);
			$res = $UASS->addAsset( $data );
			if ( is_wp_error($res) ) {
				wp_send_json_error( $res->get_error_messages() );
			}
			//wp_send_json_error( var_export($res, true) );

			# Save before return - addAsset will save
			// $UASS->save();

			wp_send_json_success('New asset is added');

			break;

		case 'asset-delete':
			$args = !empty($_POST['args']) ? (array)$_POST['args'] : [];
			$UASS = new UserAssets();
			$asset_id = !empty($args['id']) ? (int)$args['id'] : 0;
			$res  = $UASS->deleteAsset($asset_id);
			if ( is_wp_error($res) ) {
				wp_send_json_error( $res->get_error_messages() );
			}
			wp_send_json_success('Asset removed');
			break;

		case 'get-history':
			$assetid = !empty($_POST['assetid']) ? (int)$_POST['assetid'] : 0;

			//$uid = get_current_user_id();
			$UASS = new UserAssets();

			$history = $UASS->getAssetHistory( $assetid );
			if ( is_wp_error($history) ) {
				wp_send_json_error( $history->get_error_messages() );
			}

			wp_send_json_success( [
				'asset'   => $UASS->getID($assetid),
				'history' => $history,
			]);
			break;

		case 'get-data':
			$ZAPI = apply_filters( 'zapideb_class', null );
			if ( empty($ZAPI) || !class_exists($ZAPI) ) {
				wp_send_json_error('API is on lunch break. Come back later.');
			}

			//$ZAPI->
			break;

		#######################################################################
		case 'test':
			$res = !empty($_POST['res']) ? (int)$_POST['res'] : 0;

			if ( $res == 0 ) {
				$RES = new WP_Error('err', 'Error');
			}
			elseif ( $res > 0 ) {
				$RES = 'Success';
			}
			if ( is_wp_error( $RES ) ) {
				wp_send_json_error( $RES->get_error_messages() );
			}
			wp_send_json_success( $RES );
			break;
		case 'die':
			header("HTTP/1.0 500 Internal Server Error");
			die('Die, die, die, my darling!');
			break;
		}

		# If nothing was return so far, send this as a marker that AJAX_CB was called
		wp_send_json_error('501 Not Implemented', 501);
	}

	/**
	 * No private task - i.e. for anonymous users
	 */
	static function nopriv_task( $task, $POST=null ) {
		$POST = (array)( is_null($POST) ? $_POST : $POST );

		switch ($task) {
		case 'user-login-link':
			# Throttle requests
			sleep(1);
			usleep( rand(250,750)*1000 );
			// wp_send_json_error('kor');
			// wp_send_json_success('hoi');

			$email = !empty($POST['email']) ? sanitize_email($POST['email']) : '';
			if ( empty($email) || !is_email($email) ) {
				wp_send_json_error( __('Invalid email', UMM_DOMAIN) );
			}

			# Redirect to
			$redirect_to = ( !empty($POST['redirect']) ? esc_url($POST['redirect']) : '' );

			// $RES = apply_filters(
			// 	'sgam-login-link',
			// 	new WP_Error('err', __('System error', UMM_DOMAIN) ),
			// 	$email,
			// 	$redirect_to
			// );
			if ( is_wp_error($RES) ) {
				wp_send_json_error( $RES->get_error_messages() );
			}

			wp_send_json_success( $RES );

			break;
		}

		# If nothing was return so far, send this as a marker that AJAX_CB was called
		wp_send_json_error('No!', 501);
	}

	###########################################################################
	###########################################################################

}
