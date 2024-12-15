<?php
namespace umm;

use UMM as PluginMain;
use WP_Error;
use Exception;

defined('ABSPATH') || die();

/**
 * Class for User Assets instance
 */

class UserAssets {

	protected $uid;
	# Initially loaded assets, updated on save
	protected $user_assets;

	protected $last_id = 0;
	protected $list = [];
	
	function __construct( ?int $uid=null ) {
		if ( empty($uid) ) {
			$uid = get_current_user_id();
		}
		$this->uid = $uid;
		$this->_loadAssets();
	}

	protected function _loadAssets() {
		$this->user_assets = Assets::getAssets( $this->uid );
		//var_dump($this->user_assets)
		# Expect associative array with assets
		if ( !empty($this->user_assets) && is_array($this->user_assets) ) {
			$this->last_id = $this->user_assets['last_id'];
			$this->list    = $this->user_assets['list'];
		}
	}

	public function save() {
		$this->user_assets = [
			'last_id'	=> $this->last_id,
			'list'		=> $this->list,
		];
		return Assets::saveAssets( $this->user_assets, $this->uid );
	}

	###########################################################################

	public function getList( $args=[] ) {
		$all_props = FALSE;
		$props = ['id', 'added', 'name', 'url', 'host'];
		if ( !empty($args['props']) ) {
			if ( is_scalar($args['props']) && $args['props'] == '*' ) {
				$all_props = TRUE;
			}
			else {
				$props = (array)$args['props'];
			}
		}
		# Flip to intersect
		$props = array_flip($props);

		$out = [];
		foreach( $this->list as $id=>$data ) {
			if ( !$all_props ) {
				$data = array_intersect_key( $data, $props );
			}

			if ( !empty($args['as-array']) ) {
				$out[] = $data;				
			}
			else {
				$out[$id] = $data;
			}
		}
		return $out;
	}

	/**
	 * Get Single asset by id
	 */
	public function getID( int $id ) {
		return ( isset($this->list[$id]) ? $this->list[$id] : FALSE );
	} 

	/**
	 * Get Asset history for asset id
	 */
	public function getAssetHistory( int $id ) {
		$asset = !empty($this->list[$id]) ? $this->list[$id] : [];
		if ( empty($asset) || empty($asset['zitemid']) ) {
			return new WP_Error('asset', 'Invalid asset');
		}

		$ZAPI = apply_filters( 'zapideb_class', null );
		if ( empty($ZAPI) || !class_exists($ZAPI) ) {
			return new WP_Error('zapi', 'API is on lunch break. Come back later.');
		}
		$history = $ZAPI::getItemHistory( $asset['zitemid'] );
		if ( is_wp_error($history) ) {
			return $history;
		}

		$series = [];
		foreach( $history as $row ) {
			# Only clock & value, ignore ns(nanosecond) & itemid
			$series[] = [ 'clock' => $row->clock, 'value'=>$row->value ];
		}
		return $series;
	}

	public function addAsset( array $data ) {

		# Validate data
		$ERR = new WP_Error();

		# Expect data to have name and url
		$name = !empty($data['name']) ? sanitize_text_field($data['name']) : '';
		$url  = !empty($data['url']) ? sanitize_url($data['url'], ['http', 'https']) : '';
		
		$host = wp_parse_url( $url, PHP_URL_HOST );
		
		if ( empty($name) ) {
			$ERR->add('name', 'Invalid name' );
		}
		if ( empty($url) ) {
			$ERR->add('url', 'Invalid url' );
		}
		if ( empty($host) ) {
			$ERR->add('host', 'Invalid host');
		}

		# Check if host already exists for the user
		if ( !empty($this->list) ) {
			foreach( $this->list as $asset ) {
				if ( $asset['host'] == $host ) {
					return new WP_Error('host', 'Host already exists');
				}
			}
		}
		# Try to ping host
		$res = Helpers::ping( $host );
		if ( $res <= 0 ) {
			$ERR->add('ping', 'Uri is not reponding. Try again later' );
		}


		if ( $ERR->has_errors() ) {
			return $ERR;
		}

		# Proceed with adding
		$new_id = ++$this->last_id;
		$item['id']    = $new_id;
		$item['added'] = current_time( 'mysql', true );
		$item['name']  = $name;
		$item['url']   = $url;
		$item['host']  = $host;
		$item['ping']  = $res;
		
		# Before adding asset, create WebScenario OR Host ICMP
		$ZAPI = apply_filters('zapideb_class', null);
		if ( empty($ZAPI) || !class_exists($ZAPI) ) {
			return new WP_Error('zapi', 'API is not available. Try again later');
		}
		# Name must be unique for all users and hosts, so add suffix time
		//$z_name = '['.$this->uid.']-'.$name.'('.$host.')-'.$item['added'];
		$z_name = $this->uid.'__'.$host.'__'.strtotime($item['added']);
		$z_tags = [ 
			'wpuid' => 'uid_'.$this->uid, 
			'added' => $item['added'], 
			'host'  => $host 
		];
		
		$hostID = $ZAPI::createHost_ICMP( $z_name, $host, $z_tags );
		if ( empty($hostID) ) {
			return new WP_Error('host', 'Error adding host');
		}
		# Now that the host i created, need to get the item for history
		$item['zhostid'] = $hostID;
		$item['zitemid'] = $ZAPI::host_ICMP_item_ping( $hostID );
		$this->list[$new_id] = $item;

		$this->save();

		return $new_id;
	}

	public function deleteAsset( $id ) {
		$Asset = $this->getID($id);
		if ( !$Asset ) {
			return new WP_Error('asset', 'Invalid asset');
		}
		# Get Zabbix API
		$ZAPI = apply_filters( 'zapideb_class', null );
		if ( empty($ZAPI) || !class_exists($ZAPI) ) {
			return new WP_Error('zapi', 'API is on lunch break. Come back later.');
		}

		# Try to remove web scenario
		$res = $ZAPI::removeHost_ICMP( $Asset['zhostid'] );
		if ( is_wp_error($res) ) {
			return $res;
		}

		unset($this->list[$id]);
		$this->save();
		return TRUE;
	}


	###########################################################################
	static function validateURL( $url ) {

	}
} 
