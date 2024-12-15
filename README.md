# ultimate-member-monitor
Wordpress plugin to display custom account tabs in Ultimate Member plugin. 

This plugin requires plugin Ultimate Member to work
Adds custom tabs in user account for adding assets and displaying asset charts

Note: Plugin Ultimate Member is not perfect and in order to display custom tabs, they must be allowed in admin settings
since this addon creates tabs dynamically, in order to display them without admin settings, a small "fix" in plugin Ultimate Memeber must be made:

file: /ultimate-member/includes/core/class-options.php
line ~ 43
```
public function get( $option_id ) {
  # START FIX: Set to empty to allow filter "um_get_option_filter__{$option_id}" to run
  if ( !isset($this->options[ $option_id]) ) {
	  $this->options[ $option_id ] = '';
	}
  # END FIX
  if ( isset( $this->options[ $option_id ] ) ) {
```

