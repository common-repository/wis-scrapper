<?php
class wis_enqueue_assets{
	public function __construct(){
		add_action( 'admin_enqueue_scripts', [$this,'register_css'] );
		add_action( 'admin_enqueue_scripts', [$this,'register_js'] );
	}
	public function register_js(){
		/** JS for main Scrapper Main Page **/
		wp_register_script("multi-step","http://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.3/jquery.easing.min.js",["jquery"]);
		wp_register_script("custom-multi-step", WIS_SCRAPPER_PLUGIN_URL . 'assets/js/multi-step/index.js',
			["jquery","multi-step"]);
		wp_register_script("wis-scrapper-ajax-form", "http://malsup.github.com/jquery.form.js",
			["jquery"]);
		wp_register_script("wis-scrapper-settings-js", WIS_SCRAPPER_PLUGIN_URL . 'assets/js/wis-settings.js',
			["jquery"]);
	}
	public function register_css(){
		/** CSS for main Scrapper Main Page **/
		wp_register_style( 'multi-step', WIS_SCRAPPER_PLUGIN_URL . "assets/css/multi-step/multi-step.css");
		wp_register_style( 'wis-font-aweosome', "https://use.fontawesome.com/releases/v5.3.1/css/all.css");
		wp_register_style('wis-scrapper-settings-css',WIS_SCRAPPER_PLUGIN_URL . 'assets/css/wis-settings.css');
		/** Main Css of Plugin **/
		wp_enqueue_style('wis-scrapper',WIS_SCRAPPER_PLUGIN_URL . 'assets/css/main.css');
	}
}
new wis_enqueue_assets;