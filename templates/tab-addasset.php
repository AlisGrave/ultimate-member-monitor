<?php
defined('ABSPATH')  || die('die');

/**
 * Templates for custom tab for UM
 */

#### Display List of monitored items and buttons to manage them

do_action('umon_addlib', ['swal', 'ajax', 'apexcharts', 'fui', 'ping' ], []);
$css = 'assets/umm-styles.css';
if ( file_exists(UMM_DIR.$css)) {
	wp_enqueue_style( 'umm-user', UMM_URL.$css, [], filemtime(UMM_DIR.$css) );
}
wp_enqueue_script( 'moment' );

# Check of connection to Zabbix
?>
<div id="umm-main-wrap" class="ui basic segment">
	<div class="ui segment top attached blue">

	</div>
	<div class="ui segment bottom attached green clearing">

		<div class="ui form" id="umm-add-asset">
			<div class="ui message error"></div>
			<div class="two fields" data-edit>
				<div class="required field">
					<label> <?php _e('Asset name', UMM_DOMAIN); ?> </label>
					<input type="text" name="asset[name]">
				</div>
				<div class="required field">
					<label> <?php _e('Asset url', UMM_DOMAIN); ?> </label>
					<input type="text" name="asset[url]">
					<small> Make sure your URL has allowed this origin (CORS headers) </small>
				</div>
			</div>
			<div data-act="new-asset" class="ui button right floated primary"> Add new </div>
		</div>


	</div>
</div>

<script type="text/javascript">
;(function($){
	'use strict';

	function parseURL( aurl, tryFetch=true ) {
		let uri = aurl.trim().toLowerCase();
		// Check for protocol
		if ( uri.search('://') == -1 ) {
			uri = `http://${uri}`;
		}

		// Allows only http or https url, but also only hosts
		if ( uri.substr(0,7) != 'http://' && uri.substr(0,8) != 'https://' ) {
			return false;
		}
		try {
			let U = new URL(uri);
			return `https://${U.hostname}`; // Only hostname, not host. Need the domain only
		}
		catch( Err ) {
			//console.debug('Catch in validateURL', Err);
			return false;
		}
	}
	async function checkPing( aurl ) {
		aurl = parseURL(aurl);
		if ( typeof ping == 'function' ) {
			return await ping(aurl, 0.3).catch(r=>false);
		}
		return false;
	}

	// window.parseURL = parseURL;
	// window.checkPing = checkPing;

	const APP = {
		$W: $(),
		run:()=>{
			APP.$W = $('#umm-main-wrap').addClass('loading');
			APP.initForm();
			APP.$W.removeClass('loading');
		},
		initForm: ()=>{
			const $F = APP.$W.find('#umm-add-asset');
			const $ERR = $F.find('.ui.message.error');
			const fields = {
				$name : $F.find(`input[name="asset[name]"]`),
				$url  : $F.find(`input[name="asset[url]"]`)
			};
			$F.on('click', `[data-act="new-asset"]`, async function(e){
				e.preventDefault();
				$F.removeClass('error');
				$F.find('.field.error').removeClass('error');

				// Gather form data
				let rData = {
					task: 'asset-add',
					data: {
						name: fields.$name.val().trim(),
						url:  parseURL( fields.$url.val() )
					}
				};
				if ( rData.data.name.length < 3 ) {
					fields.$name.parents('.field:first').addClass('error');
				}
				if ( !rData.data.url ) {
					fields.$url.parents('.field:first').addClass('error');
				}
				if ( $F.find('.field.error').length ) {
					return false;
				}

				APP.$W.addClass('loading');
				// At this point, check url ping time
				await checkPing( rData.data.url )
				.then((r)=>{
					if ( !r ) {
						return false;
					}
					// Display
					$.toast({
						showProgress: 'bottom',
						classProgress: 'blue',
						class: 'blue',
						position: 'bottom right',
						message: `Ping: ${r.toFixed(2)}`
					});
				});

				//console.debug(rData);

				APP.REQ( rData )
				.then( (resp=false)=>{
					console.debug('then',resp);
					// Display Success and refresh
					$.toast({
						icon: 'success',
						title: 'Success',
						message: resp
					});
					location.reload();
				})
				.catch((errors)=>{
					//console.debug('catch',errors);
					$ERR.empty().html( errors.join('<br>') );
					$F.addClass('error');
					APP.$W.removeClass('loading');
				})
				.finally(()=>{
				});
			});
		},
		REQ: async function(rData){
			return new Promise( (resolve, reject)=>{
				let req = {
					data: rData
				};
				wp.ajax.umm(req)
				.done( function(resp) {
					//console.debug('done', resp);
					resolve( resp );
				})
				.fail( function() {
					//console.debug('fail', arguments);
					// Fail can have 1 argument or 3 arguments
					let msg = arguments[0];
					if ( arguments.length >= 2 ) {
						let xhr = arguments[0];
						msg = arguments[2];
						if ( $.isPlainObject(xhr) ) {
							if ( 	"responseJSON" in xhr
							 	 && $.isPlainObject(xhr.responseJSON)
							 	 && "data" in xhr.responseJSON
							 	 && typeof xhr.responseJSON.data == 'string'
								) {
								msg = xhr.responseJSON.data;
							}
							else if ( "responseText" in xhr
								 && xhr.responseText.trim() != ''
								) {
								msg = xhr.responseText.trim();
							}
						}
					}
					msg = $.isArray(msg) ? msg : [msg];
					//console.debug(msg);
					reject( msg )
				})
				.always(function(){
					//console.info('always');
				});
			});
		}
	};

	$(APP.run);
	// window.APP = APP;

})(jQuery);
</script>