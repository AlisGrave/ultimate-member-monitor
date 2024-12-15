<?php
defined('ABSPATH')  || die('die');

/**
 * Templates for custom tab for UM
 */

#### Display List of monitored items and buttons to manage them

do_action('umon_addlib', ['swal', 'fui' ], []);
$css = 'assets/umm-styles.css';
if ( file_exists(UMM_DIR.$css)) {	
	wp_enqueue_style( 'umm-user', UMM_URL.$css, [], filemtime(UMM_DIR.$css) );
}
?>
<div class="ui basic segment">

	<div class="ui icon info message">
		<i class="info circle icon"></i>
		<div class="content">
			<div class="header">
				This is your main panel
			</div>
			<ul class="list">
				<li>You can add new assets from tab "Add asset"</li>
				<li>Every asset will create it's own tab to display information</li>
			</ul>
		</div>
	</div>

</div>
