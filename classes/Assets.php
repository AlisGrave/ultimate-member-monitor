<?php
namespace umm;

use UMM as PluginMain;
use WP_Error;
use Exception;

defined('ABSPATH') || die();

/**
 * Class for User Assets 
 */
 
 class Assets {

 	CONST META_USER_ASSET = '_monitor_asset';

 	/**
 	 * Return user meta with assets data (serialized)
 	 * Meta contains all data for single user assets
 	 */
 	static function getAssets( $uid=null, $fresh=false ) {
 		
 		$uid = (empty($uid) ? get_current_user_id() : (int)$uid );
 		$U = get_userdata( $uid );
 		if ( empty($U) ) {
 			return new WP_Error('user', 'Invalid user');
 		}

 		$assets = wp_cache_get( $uid, 'user_assets' );
 		if ( !$fresh && !empty($assets) ) {
 			return $assets;
 		}

 		# Assets as single meta - serialized data
 		$assets = get_user_meta( $U->ID, self::META_USER_ASSET, TRUE );
 		if ( !empty($assets) ) {
 			wp_cache_set( $uid, $assets, 'user_assets' );
 		}
 		# Maybe filter
 		return $assets;
 	}

 	/**
 	 * Add asset to a user
 	 */
 	static function saveAssets( $user_assets, $uid=null ) {
 		$uid = (empty($uid) ? get_current_user_id() : (int)$uid );
 		$U = get_userdata( $uid );
 		if ( empty($U) ) {
 			return new WP_Error('user', 'Invalid user');
 		}
 		
 		$res = update_user_meta( $uid, self::META_USER_ASSET, $user_assets );
 		if ( $res ) {
 			wp_cache_set( $uid, $user_assets, 'user_assets' );
 		}
 		return $res;
 	}
 }
