<?php
class wis_menus_settings{
	public function __construct(){
		add_action('admin_menu', [$this,'add_admin_menus'] );
	}
	public function add_admin_menus(){
		$icon_svg = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iQXJ0d29yayIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeD0iMHB4IiB5PSIwcHgiIHdpZHRoPSIyMy4yNXB4IiBoZWlnaHQ9IjIyLjM3NXB4IiB2aWV3Qm94PSIwIDAgMjMuMjUgMjIuMzc1IiBlbmFibGUtYmFja2dyb3VuZD0ibmV3IDAgMCAyMy4yNSAyMi4zNzUiIHhtbDpzcGFjZT0icHJlc2VydmUiPjxwYXRoIGZpbGw9IiM5Q0ExQTYiIGQ9Ik0xOC4wMTEsMS4xODhjLTEuOTk1LDAtMy42MTUsMS42MTgtMy42MTUsMy42MTRjMCwwLjA4NSwwLjAwOCwwLjE2NywwLjAxNiwwLjI1TDcuNzMzLDguMTg0QzcuMDg0LDcuNTY1LDYuMjA4LDcuMTgyLDUuMjQsNy4xODJjLTEuOTk2LDAtMy42MTUsMS42MTktMy42MTUsMy42MTRjMCwxLjk5NiwxLjYxOSwzLjYxMywzLjYxNSwzLjYxM2MwLjYyOSwwLDEuMjIyLTAuMTYyLDEuNzM3LTAuNDQ1bDIuODksMi40MzhjLTAuMTI2LDAuMzY4LTAuMTk4LDAuNzYzLTAuMTk4LDEuMTczYzAsMS45OTUsMS42MTgsMy42MTMsMy42MTQsMy42MTNjMS45OTUsMCwzLjYxNS0xLjYxOCwzLjYxNS0zLjYxM2MwLTEuOTk3LTEuNjItMy42MTQtMy42MTUtMy42MTRjLTAuNjMsMC0xLjIyMiwwLjE2Mi0xLjczNywwLjQ0M2wtMi44OS0yLjQzNWMwLjEyNi0wLjM2OCwwLjE5OC0wLjc2MywwLjE5OC0xLjE3M2MwLTAuMDg0LTAuMDA4LTAuMTY2LTAuMDEzLTAuMjVsNi42NzYtMy4xMzNjMC42NDgsMC42MTksMS41MjUsMS4wMDIsMi40OTUsMS4wMDJjMS45OTQsMCwzLjYxMy0xLjYxNywzLjYxMy0zLjYxM0MyMS42MjUsMi44MDYsMjAuMDA2LDEuMTg4LDE4LjAxMSwxLjE4OHoiLz48L3N2Zz4=';
		/* Main Menu */
		$perms = 'export';
		$main_menu = add_menu_page('Scrapper Plugin', 'WIS Scrapper','export', 'wis-scrapper',[$this,'scrapper_get_menu'],'
			dashicons-share-alt2',3);
		$page_main = add_submenu_page('wis-scrapper',
			'Backups',
			'Backups', 
			'manage_options', 
			'wis-scrapper', 
			[$this,'scrapper_get_menu']
		);
		add_action("admin_print_styles-$page_main",[$this,"enqueue_css_assets"] );
		add_action("admin_print_scripts-$page_main",[$this,"enqueue_js_assets"] );
	}
	public function enqueue_css_assets(){
		wp_enqueue_style( 'multi-step' );
		wp_enqueue_style( 'wis-font-aweosome' );
	}
	public function enqueue_js_assets(){
		wp_enqueue_script( 'multi-step' );
		wp_enqueue_script( 'custom-multi-step' );
		wp_enqueue_script( 'wis-scrapper-ajax-form' );
	}
	public function scrapper_get_menu(){
		$page_type = empty($_GET['page'])?'wis-scrapper':$_GET['page'];
		switch( $page_type ){
			case('wis-scrapper'):
			include(WIS_SCRAPPER_PLUGIN_PATH."templates/menus/wis-main-add.php");
			break;
		}
	}
}
new wis_menus_settings;