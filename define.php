<?php
define('WIS_SCRAPPER_VERSION','0.1');
define('WIS_SCRAPPER_PLUGIN_URL',plugin_dir_url(__FILE__));
define( 'WIS_SCRAPPER_PLUGIN_PATH',plugin_dir_path(__FILE__) );
define('WIS_SCRAPPER_SITE_URL',		get_site_url());
if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__FILE__));
}
if (! defined('WIS_SCRAPPER_WPROOTPATH')) {
	define('WIS_SCRAPPER_WPROOTPATH', str_replace('\\', '/', ABSPATH));
}
define('WIS_SCRAPPER_SSDIR_NAME',     'wp-wis-scrapper');
define('WIS_SCRAPPER_SSDIR_PATH',     str_replace("\\", "/", WIS_SCRAPPER_WPROOTPATH . WIS_SCRAPPER_SSDIR_NAME));
define('WIS_SCRAPPER_SSDIR_PATH_TMP', WIS_SCRAPPER_SSDIR_PATH . '/tmp');
define('WIS_SCRAPPER_SSDIR_URL',      WIS_SCRAPPER_SITE_URL . "/" . WIS_SCRAPPER_SSDIR_NAME);
/* GENERAL CONSTRAINTS */
define('WIS_SCRAPPER_PHP_MAX_MEMORY',  '2048M');
define('WIS_SCRAPPER_DB_MAX_TIME',     5000);
define('WIS_SCRAPPER_DB_EOF_MARKER',   'WIS_SCRAPPER_MYSQLDUMP_EOF');
/* SCANNER CONSTRAINTS  */
define('WIS_SCRAPPER_SCAN_SIZE_DEFAULT',	157286400);	/*150MB*/
define('WIS_SCRAPPER_SCAN_WARNFILESIZE',	3145728);	/*3MB*/
define('WIS_SCRAPPER_SCAN_CACHESIZE',		1048576);	/*1MB*/
define('WIS_SCRAPPER_SCAN_DB_ALL_ROWS',	500000);	/*500k per DB*/
define('WIS_SCRAPPER_SCAN_DB_ALL_SIZE',	52428800);	/*50MB DB*/
define('WIS_SCRAPPER_SCAN_DB_TBL_ROWS',	100000);    /*100K rows per table*/
define('WIS_SCRAPPER_SCAN_DB_TBL_SIZE',	10485760);  /*10MB Table*/
define('WIS_SCRAPPER_SCAN_TIMEOUT',		150);		/*Seconds*/
define('WIS_SCRAPPER_SCAN_MIN_WP',		'4.7.0');
$GLOBALS['WIS_SCRAPPER_SERVER_LIST'] = array('Apache','LiteSpeed', 'Nginx', 'Lighttpd', 'IIS', 'WebServerX', 'uWSGI');
$GLOBALS['WIS_SCRAPPER_OPTS_DELETE'] = array('scrapper_ui_view_state', 'scrapper_package_active', 'scrapper_settings');
/* Used to flush a response every N items. 
 * Note: This value will cause the Zip file to double in size durning the creation process only*/
define("WIS_SCRAPPER_ZIP_FLUSH_TRIGGER", 1000);
/* Let's setup few things to cover all PHP versions */
if(!defined('PHP_VERSION')) {
	define('PHP_VERSION', phpversion());
}
if (!defined('PHP_VERSION_ID')) {
	$version = explode('.', PHP_VERSION);
	define('PHP_VERSION_ID', (($version[0] * 10000) + ($version[1] * 100) + $version[2]));
}
if (PHP_VERSION_ID < 50207) {
	if(!(isset($version))) $version = explode('.', PHP_VERSION);
	if(!defined('WIS_PHP_MAJOR_VERSION'))   define('WIS_PHP_MAJOR_VERSION',   $version[0]);
	if(!defined('WIS_PHP_MINOR_VERSION'))   define('WIS_PHP_MINOR_VERSION',   $version[1]);
	if(!defined('WIS_PHP_RELEASE_VERSION')) define('WIS_PHP_RELEASE_VERSION', $version[2]);
}