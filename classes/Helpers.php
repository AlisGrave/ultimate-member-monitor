<?php
namespace umm;

use UMM as PluginMain;
use WP_Error;
use Exception;

defined('ABSPATH') || die();

/**
 * Some helpers functions as static class
 */

class Helpers {


	/**
	 * Pings a host 
	 * @return -1|int - if -1 => host is down
	 */
	static function ping($host, $port=80, $timeout=5) { 
		$tB = microtime(true); 
		$fP = fSockOpen($host, $port, $errno, $errstr, $timeout); 
		if (!$fP) { 
			//throw new Exception( $errstr, $errno );
			return $host; 
			return -1; 
		} 
		$tA = microtime(true); 
		return round((($tA - $tB) * 1000), 0); 
	}
} 
