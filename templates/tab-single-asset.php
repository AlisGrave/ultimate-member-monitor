<?php
defined('ABSPATH')  || die('die');

/**
 * Templates for custom tab for UM
 * Display single asset from user assets
 */

$asset_id = 0;
if ( !empty($the_tab) && !empty($the_tab['asset_id']) ) {
	$asset_id = (int)$the_tab['asset_id'];
}

$UASS = new \umm\UserAssets();

$Asset = $UASS->getID($asset_id);

do_action('umon_addlib', ['swal', 'ajax', 'apexcharts', 'fui', /*'ping'*/ ], [] );
$css = 'assets/umm-styles.css';
if ( file_exists(UMM_DIR.$css)) {
	wp_enqueue_style( 'umm-user', UMM_URL.$css, [], filemtime(UMM_DIR.$css) );
}
wp_enqueue_script( 'moment' );

###############################################################################
# Check for valid asset
###############################################################################
if ( empty($asset_id) || empty($Asset) ) {
	?>
	<div class="ui message negative">
		<div class="header">
			Asset failed to load
		</div>
		<p>Try again later</p>
	</div>
	<?php
	return;
}

$id = 'asset-'.$Asset['id'];
?>
<div id="<?php echo $id;?>" class="ui segment">

	<div class="ui basic segment">
	<table class="ui definition table">
		<tbody>
		<tr>
			<td>Added</td>
			<td><?php echo $Asset['added'];?></td>
		</tr>
		<tr>
			<td>Name</td>
			<td><?php echo $Asset['name'];?></td>
		</tr>
		<tr>
			<td>Host</td>
			<td><?php echo $Asset['host'];?></td>
		</tr>
		<tr>
			<td>URL</td>
			<td><?php echo $Asset['url'];?></td>
		</tr>
		</tbody>

		<tfoot>
			<tr><td>Actions</td>
				<td>
					<div class="ui button red" data-task="delete" data-id="<?php echo $Asset['id'];?>"> Delete </div>
					<div class="ui button green" data-task="refresh"> Force Refresh </div>
				</td>
			</tr>
		</tfoot>
	</table>

	<div class="ui basic segment">

		<div id="chart"></div>
	</div>

</div>

<script type="text/javascript">
;(function($){
	'use strict';

	class localStorage {
		#dbname;

		error = false;
		constructor( dbname ) {
			this.#dbname = dbname;

			this.initStorage();
		}

		// Local storage driver - using indexedDB
		#db;
		initStorage() {
			const T = this;
			const req = indexedDB.open( T.#dbname );
			req.onerror = (event) => {
				T.error = 'Can not initialize local DB';
				// If the current user database has a higher version than in the open call, e.g. the existing DB version is 3, and we try to open(...2), then that’s an error, openRequest.onerror triggers.
				// That’s rare, but such a thing may happen when a visitor loads outdated JavaScript code, e.g. from a proxy cache. So the code is old, but his database is new.
				// To protect from errors, we should check db.version and suggest a page reload. Use proper HTTP caching headers to avoid loading the old code, so that you’ll never have such problems.

			};
			// If the local database version is less than specified in open, then a special event upgradeneeded is triggered, and we can compare versions and upgrade data structures as needed.
			// The upgradeneeded event also triggers when the database doesn’t yet exist (technically, its version is 0), so we can perform the initialization.
			req.onupgradeneeded  = (event)=>{
				console.debug( 'indexed DB upgradeneeded', event );
				// Could possibly use T.#db
				const db = req.result;
				switch(event.oldVersion) { // existing db version
				case 0:
					// version 0 means that the client had no database
					// perform initialization
					const store_ping = db.createObjectStore( 'ping', {keyPath: 'time'} );
					// Indexes
					store_ping.createIndex( "time", "time", { unique: true });
					break;
				case 1:
					// client had version 1
					// update
					break;
				}
			};
			req.onblocked = function() {
				// this event shouldn't trigger if we handle onversionchange correctly

				// it means that there's another open connection to the same database
				// and it wasn't closed after db.onversionchange triggered for it
			};
			req.onsuccess = (event) => {
				//T.#db = event.target.result;
				T.#db = req.result;
				T.#onsuccess();
			};
		}

		#onsuccess() {
			const T = this;
			T.#db.onerror = (event) => {
				// Generic error handler for all errors targeted at this database's
				// requests!
				T.error = `Database error: ${event.target.error?.message}`;
			};
			T.#db.onversionchange = function() {
				T.#db.close();
				T.error = "Database is outdated, please reload the page.";
			};
		}

		async addTime( time, value ) {
			const T = this;
			return new Promise((resolve, reject)=>{

				// 1) Create a transaction, mentioning all the stores it’s going to access, (pass array if more than 1)
				const transaction = T.#db.transaction( 'ping', 'readwrite' );
				// Not monitored for now
					// report on the success of opening the transaction
					transaction.oncomplete = function(event) {};
					// report on transaction error
					transaction.onerror = function(event) {};

				// 2) get an object store to operate on it
				const store_ping = transaction.objectStore('ping');

				// 3) Perform the request to the object store books.add(book), at (3).
				const req = store_ping.add( { time: time, value: value } );


				// 4) …Handle req success/error (4), then we can make other requests if needed, etc.

				req.onsuccess = ()=> {
					// req.result contains key of the added object
					resolve( req.result );
				}

				req.onerror = (err)=> {
					reject( req.error );
				}
			});
		}

		// Return data from storage by given args
		async getDataAll() {
			const T = this;
			return new Promise( (resolve, reject)=>{

				const req = T.#db.transaction('ping', 'readonly')
					.objectStore('ping')
					.getAll();

				req.onsuccess = ()=> {
					let entries = [];
					req.result.forEach( row => {
						//console.debug(row);
						entries.push( Object.values(row) );
					});
					resolve( entries );
				}

				req.onerror = (err)=> {
					reject( `Error to get student information: ${err}` );
				}
			});
		}

		async getDataLatest( limit=100 ) {
			const T = this;

			return new Promise( (resolve, reject)=>{

				const store_ping = T.#db.transaction('ping', 'readonly').objectStore('ping');

				let entries = [];

				const curReq = store_ping.openCursor( null, 'prev');
				curReq.onsuccess = (event) => {
					
					const cursor = curReq.result;
					
					//console.debug('curReq success', curReq.result, event);					

					if ( entries.length >= limit ) {
						resolve( entries );
					}
					else if ( cursor ) {
						entries.push( Object.values(cursor.value) );
						cursor.continue();
					}
					else{
						// Less than limit, but that's it
						resolve( entries );
					}
				}



				curReq.onerror = (err)=> {
					reject( `Error to get student information: ${err}` );
				}
			});
		}

		async deleteDB() {
			return new Promise( (resolve, reject)=>{
				let deleteRequest = indexedDB.deleteDatabase( this.#dbname );
				// deleteRequest.onsuccess/onerror tracks the result
				deleteRequest.onsuccess( (event)=>{
					resolve(`Database ${this.#dbname} deleted.`);
				});
				deleteRequest.onerror( (event)=>{
					reject('Error deleting database');
				});
			})
		}
		// TODO: Maybe deleteStore() -> db.deleteObjectStore('books')
	}

	class APing {

		#max = 5e3;
		#host;
		#url;
		#mp = 0.2; // compensation for http

		#storage;
		constructor(host, opts={} ){
			this.#host = host;
			this.#url = `https://${host}/`;
			this.#max = opts?.max ?? this.#max;

			this.#storage = new localStorage(host);
		}

		#int;
		start( cb=()=>null, interval=30e3 ) {
			const T = this;
			T.stop();
			T.#int = setInterval( ()=>{
					T.ping().then( cb ).catch( cb );
				}
				, interval
			);
		}
		stop(){
			const T = this;
			if ( T.#int ) {
				clearInterval(T.#int);
				T.#int = null;
			}
		}
		/////////////////////////////////////
		async getSaved( limit=100 ) {
			if ( this.#storage.error ) {
				return [];
			}
			//return this.#storage.getDataAll();
			return this.#storage.getDataLatest( limit );
		}
		get storage() {
			return this.#storage;
		}

		/////////////////////////////////////
		async ping() {
			const T = this;
			let time = (new Date()).getTime();
			let res = await new Promise((resolve,reject)=>{
				const start = (new Date()).getTime();
				const response = ()=>{
					let delta = ((new Date()).getTime() - start);
					delta *= T.#mp;
					resolve(delta);
				};
				// No matter the response, call response
				T.getImage( T.#url ).then(response).catch(response);

				// Set a timeout for max ping
				setTimeout( ()=>reject( -1 ), this.#max );
			});
			// Store in localStorage
			if ( T.#storage.error ) {
				console.warn('Cannot save to localstorage');
			}
			else {
				T.#storage.addTime( time, res );
			}
			return res;
		}
		async getImage(url) {
			return new Promise((resolve,reject)=>{
				let img = new Image();
				img.onload = ()=>resolve(img);
				img.onerror = ()=>reject(url);
				img.src = `${url}/favicon.ico?v=${Math.floor(Math.random()*1e6)}`;
			});
		}
	};



	const A = {
		$W: $(),
		$C: $(),
		chart: null, rendered: false,
		opts: {},
		interval: null,
		ajaxing: false,
		host: '<?php echo $Asset['host'];?>',
		init: ()=>{
			// $.toast({
			// 	class: 'success',
			// 	message: 'inited',
			// 	position: 'bottom right'
			// });

			A.$W = $('#<?php echo $id;?>');
			A.$C = A.$W.find('#chart');

			A.$W.on('click', '[data-task]', A.btnClick );

			try {
				A.initAPing();
				A.initChart();
				A.getData();
			}
			catch( Err ) {
				console.debug(Err);
			}
		},
		btnClick: function(e){
			let task = $(this).data('task');
			let data = $(this).data();
			switch( task ) {
				case 'delete':{
					// Confirm with timed toast ? $.toast({})
					if ( !confirm('Remove asset?') ) {
						return;
					}
					A.$W.addClass('loading');
					A.REQ({task:'asset-delete', args:data})
						.then((resp)=>{
							$.toast({
								icon:'success',
								message: resp
							});
							window.location = `${window.location.origin}${window.location.pathname}`;
						})
						.catch((err)=>{
							Swal.fire({
								icon:'error',
								title: 'Error',
								html: err.join('<br>')
							});
							A.$W.removeClass('loading');
						})
						.finally(()=>{});
					} break;

				case 'refresh': {
					A.getData();
					} break;
			}
		},

		aping: null,
		initAPing: ()=>{
			A.aping = new APing( A.host );
			A.aping.start( (res)=>{
				//console.debug( `Pinged host ${A.host} - ${res}`);
			});
		},
		getLocalData: async (limit=100)=>{
			if ( A.aping ) {
				return A.aping.getSaved(limit);
			}
			return [];
		},

		initChart: ()=>{
			A.destroyChart();
			A.opts = {
				series: [
					{
						name: "Server response time",
						data: []
					},
					{
						name: "Browser response time",
						data: []
					}
				],
				chart: {
					id: 'realtime',
					height: 350,
					type: 'line',
				},
				toolbar: {
					show: false
				},
				zoom: {
					enabled: false
				},
				dataLabels: {
					enabled: false,
				},
				title: {
					text: 'Response time',
					align: 'left'
				},
				markers: {
					size: 0
				},
				xaxis: {
					type: 'datetime',
					title: {
						text: 'Time',
					},
					labels: {
						// datetimeFormatter: {
						// 	year: 'yyyy',
						// 	month: 'MM.yy',
						// 	day: 'dd MMM',
						// 	hour: 'HH:mm'
						// },
						format: 'HH:mm',
						// formatter: (val, index) =>{
						// 	console.debug(val, index);
						// 	return val;
						// }
					},
					tooltip: {
						enabled: true,
						formatter: function(val, set){
							//console.debug(arguments);
							return moment(val).format('HH:mm:ss');
						},
					}
				},
				yaxis: [
					{
						//max: 100,
						opposite: false,
						forceNiceScale: true,
						labels: {
							/**
							  * Allows users to apply a custom formatter function to yaxis labels.
							  *
							  * @param { String } value - The generated value of the y-axis tick
							  * @param { index } index of the tick / currently executing iteration in yaxis labels array
							  */
							formatter: function(val, index) {
								return val.toFixed(3);
							},
						},
						title: {
							text: 'Server lag [ms]'
						}
					},
					{
						//max: 100,
						opposite: true,
						forceNiceScale: true,
						labels: {
							/**
							  * Allows users to apply a custom formatter function to yaxis labels.
							  *
							  * @param { String } value - The generated value of the y-axis tick
							  * @param { index } index of the tick / currently executing iteration in yaxis labels array
							  */
							formatter: function(val, index) {
								return val.toFixed(3);
							}
						},
						title: {
							text: 'Your browser lag [ms]'
						}
					},
				],
				legend: {
					show: true,
					showForSingleSeries: true,
					// customLegendItems: [
					// ]
				},
				tooltip: {
					enabled: true,
					x: {
						formatter: function(value, { series, seriesIndex, dataPointIndex, w }) {
							return moment(value).format('DD.MMM.Y HH:mm:ss');
						}
					}
				},
			};
			A.chart = new ApexCharts( A.$C[0], A.opts );
			A.chart.render();
		},
		render: ()=>{
			if ( !A.rendered ) {
				A.chart.render();
			}
		},

		getData: async()=>{
			let rData = {
				task: 'get-history',
				assetid: <?php echo $Asset['id']; ?>
			};
			A.$W.addClass('loading');
			return A.REQ( rData )
				.then( async (resp=false)=>{
					if ( !resp ) return Promise.reject('Invalid response');
					try {
						// Modify response data to series for chart
						let data = [];

						let jsdata = [];
						resp.history.forEach( r=>{
							data.push( [r.clock*1000, r.value] );
							//jsdata.push( { x:r.clock*1000, y:parseFloat(r.value)*1.23*Math.random() } );
						});

						jsdata = await A.getLocalData(100).catch((err)=>{
								console.debug('Error getting jsdata', err);
								return [];
							});
						//console.debug( jsdata );

						A.chart.updateSeries([
							{
								data: data
							},
							{
								data: jsdata
							}
						]);
						A.setInterval();
					}
					catch( Err ) {
						console.debug('Catched error after getData()', Err);
					}
				})
				.catch( (err)=>{
					//A.clearInterval();
					$.toast({
						class:'error',
						title: 'Error getting data',
						message: 'Try again.',
						position: 'bottom right'
					});
					return err;
				})
				.finally(()=>{
					A.$W.removeClass('loading');
				});
		},

		destroyChart: ()=>{
			if ( A.chart ) {
				A.chart.destroy();
				A.chart = null;
			}
		},
		setInterval: ()=>{
			A.clearInterval();
			A.interval = setInterval( async ()=>{
				A.getData();
			}, 30e3 );
		},
		clearInterval: ()=>{
			if ( A.interval ) {
				clearInterval(A.interval);
				A.interval = null;
			}
		},

		REQ: async function(rData){
			if ( A.ajaxing ) {
				return false;
			}
			return new Promise( (resolve, reject)=>{
				A.ajaxing = true;
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
					A.ajaxing = false;
				});
			});
		}
	};

	$(A.init);

	// Debug expose
	// window.A = A;
	// window.pStore = localStorage;
	// window.APing = APing;


})(jQuery);
</script>
