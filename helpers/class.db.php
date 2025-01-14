<?php
/**
 * Lightweight abstraction layer for common simple database routines
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package Scrapper
 * @subpackage classes/utilities
 * @copyright (c) 2017, Snapcreek LLC
 * @since 1.1.32
 *
 */
 /* Exit if accessed directly */
if (!defined('WIS_SCRAPPER_VERSION')) {
	exit;
}
class WIS_SCR_DB extends wpdb {
	public static $remove_placeholder_escape_exists = null;
	public static function init() {
		global $wpdb;
		self::$remove_placeholder_escape_exists = method_exists($wpdb, 'remove_placeholder_escape');
	}
    /**
     * Get the requested MySQL system variable
     *
     * @param string $name The database variable name to lookup
     *
     * @return string the server variable to query for
     */
    public static function getVariable($name) {
    	global $wpdb;
    	if (strlen($name)) {
    		$row = $wpdb->get_row("SHOW VARIABLES LIKE '{$name}'", ARRAY_N);
    		return isset($row[1]) ? $row[1] : null;
    	} else {
    		return null;
    	}
    }
    /**
     * Gets the MySQL database version number
     *
     * @param bool $full    True:  Gets the full version
     *                      False: Gets only the numeric portion i.e. 5.5.6 or 10.1.2 (for MariaDB)
     *
     * @return false|string 0 on failure, version number on success
     */
    public static function getVersion($full = false) {
    	global $wpdb;
    	if ($full) {
    		$version = self::getVariable('version');
    	} else {
    		$version = preg_replace('/[^0-9.].*/', '', self::getVariable('version'));
    	}
		/* Fall-back for servers that have restricted SQL for SHOW statement */
    	if (empty($version)) {
    		$version = $wpdb->db_version();
    	}
    	return empty($version) ? 0 : $version;
    }
    /**
     * Try to return the mysqldump path on Windows servers
	 *
     * @return boolean|string
     */
    public static function getWindowsMySqlDumpRealPath() {
    	try {
    		if (function_exists('php_ini_loaded_file')) {
    			$get_php_ini_path = php_ini_loaded_file();
    			if (@file_exists($get_php_ini_path)) {
    				$search = array(
    					dirname(dirname($get_php_ini_path)).'/mysql/bin/mysqldump.exe',
    					dirname(dirname(dirname($get_php_ini_path))).'/mysql/bin/mysqldump.exe',
    					dirname(dirname($get_php_ini_path)).'/mysql/bin/mysqldump',
    					dirname(dirname(dirname($get_php_ini_path))).'/mysql/bin/mysqldump',
    				);
    				foreach ($search as $mysqldump) {
    					if (@file_exists($mysqldump)) {
    						return str_replace("\\", "/", $mysqldump);
    					}
    				}
    			}
    		}
    		unset($search);
    		unset($get_php_ini_path);
    		return false;
    	} catch(Exception $ex) {
    		return false;
    	}
    }
	/**
     * Returns the mysqldump path if the server is enabled to execute it otherwise false
	 *
     * @return boolean|string
     */
	public static function getMySqlDumpPath() {
        /* Is shell_exec possible */
		if (!WIS_SCR_Util::hasShellExec()) {
			return false;
		}
		$custom_mysqldump_path = WIS_SCR_Settings::Get('package_mysqldump_path');
		$custom_mysqldump_path = (strlen($custom_mysqldump_path)) ? $custom_mysqldump_path : '';
        /* COMMON WINDOWS PATHS */
		if (WIS_SCR_Util::isWindows()) {
			$paths = array(
				$custom_mysqldump_path,
				self::getWindowsMySqlDumpRealPath(),
				'C:/xampp/mysql/bin/mysqldump.exe',
				'C:/Program Files/xampp/mysql/bin/mysqldump',
				'C:/Program Files/MySQL/MySQL Server 6.0/bin/mysqldump',
				'C:/Program Files/MySQL/MySQL Server 5.5/bin/mysqldump',
				'C:/Program Files/MySQL/MySQL Server 5.4/bin/mysqldump',
				'C:/wamp64/bin/mysql/mysql5.7.21/bin',
			);
        /* COMMON LINUX PATHS */
		} else {
			$path1     = '';
			$path2     = '';
			$mysqldump = `which mysqldump`;
			if (WIS_SCR_Util::isExecutable($mysqldump))  {
				$path1 = (!empty($mysqldump)) ? $mysqldump : '';
			}
			$mysqldump = dirname(`which mysql`)."/mysqldump";
			if (WIS_SCR_Util::isExecutable($mysqldump)) {
				$path2 = (!empty($mysqldump)) ? $mysqldump : '';
			}
			$paths = array(
				$custom_mysqldump_path,
				$path1,
				$path2,
				'/usr/local/bin/mysqldump',
				'/usr/local/mysql/bin/mysqldump',
				'/usr/mysql/bin/mysqldump',
				'/usr/bin/mysqldump',
				'/opt/local/lib/mysql6/bin/mysqldump',
				'/opt/local/lib/mysql5/bin/mysqldump',
			);
		}
        /* Try to find a path that works.  With open_basedir enabled, the file_exists may not work on some systems */
		/* So we fallback and try to use exec as a last resort */
		$exec_available = function_exists('exec');
		foreach ($paths as $path) {
			if(@file_exists($path)) {
				if (WIS_SCR_Util::isExecutable($path)) {
					return $path;
				}
			} elseif ($exec_available) {
				$out = array();
				$rc  = -1;
				$cmd = $path . ' --help';
				@exec($cmd, $out, $rc);
				if ($rc === 0) {
					return $path;
				}
			}
		}
		return false;
	}
	/**
     * Returns an escaped SQL string
	 *
	 * @param string	$sql						The SQL to escape
	 * @param bool		$removePlaceholderEscape	Patch for how the default WP function works.
	 *
     * @return boolean|string
	 * @also see: https://make.wordpress.org/core/2017/10/31/changed-behaviour-of-esc_sql-in-wordpress-4-8-3/
     */
	public static function escSQL($sql, $removePlaceholderEscape = false) {
		global $wpdb;
		$removePlaceholderEscape = $removePlaceholderEscape && self::$remove_placeholder_escape_exists;
		if ($removePlaceholderEscape) {
			return $wpdb->remove_placeholder_escape(@esc_sql($sql));
		} else {
			return @esc_sql($sql);
		}
	}
}
WIS_SCR_DB::init();