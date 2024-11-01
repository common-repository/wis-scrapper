<?php
/**
 * Used to get various pieces of information about the server environment
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package Scrapper
 * @subpackage classes/utilites
 * @copyright (c) 2017, Snapcreek LLC
 * @since 1.1.0
 * 
 */
require_once (WIS_SCRAPPER_PLUGIN_PATH.'helpers/utilities/class.u.php');
 /* Exit if accessed directly */
if (!defined('WIS_SCRAPPER_VERSION')) {
    exit;
}
class WIS_SCR_Server {
    /**
     * Gets the system requirements which must pass to buld a package
     *
     * @return array   An array of requirements
     */
    public static function getRequirements()
    {
        global $wpdb;
        $scr_tests = array();
        /* PHP SUPPORT */
        $safe_ini                      = strtolower(ini_get('safe_mode'));
        $scr_tests['PHP']['SAFE_MODE'] = $safe_ini != 'on' || $safe_ini != 'yes' || $safe_ini != 'true' || ini_get("safe_mode") != 1 ? 'Pass' : 'Fail';
        $scr_tests['PHP']['VERSION']   = WIS_SCR_Util::$on_php_529_plus ? 'Pass' : 'Fail';
        $scr_tests['PHP']['ZIP']       = class_exists('ZipArchive') ? 'Pass' : 'Fail';
        $scr_tests['PHP']['FUNC_1']    = function_exists("file_get_contents") ? 'Pass' : 'Fail';
        $scr_tests['PHP']['FUNC_2']    = function_exists("file_put_contents") ? 'Pass' : 'Fail';
        $scr_tests['PHP']['FUNC_3']    = function_exists("mb_strlen") ? 'Pass' : 'Fail';
        $scr_tests['PHP']['ALL']       = !in_array('Fail', $scr_tests['PHP']) ? 'Pass' : 'Fail';
        /* REQUIRED PATHS */
        $handle_test               = @opendir(WIS_SCRAPPER_WPROOTPATH);
        $scr_tests['IO']['WPROOT'] = is_writeable(WIS_SCRAPPER_WPROOTPATH) && $handle_test ? 'Pass' : 'Warn';
        @closedir($handle_test);
        $scr_tests['IO']['SSDIR'] = (file_exists(WIS_SCRAPPER_SSDIR_PATH) && is_writeable(WIS_SCRAPPER_SSDIR_PATH)) ? 'Pass' : 'Fail';
        $scr_tests['IO']['SSTMP'] = is_writeable(WIS_SCRAPPER_SSDIR_PATH_TMP) ? 'Pass' : 'Fail';
        $scr_tests['IO']['ALL']   = !in_array('Fail', $scr_tests['IO']) ? 'Pass' : 'Fail';
        /* SERVER SUPPORT */
        $scr_tests['SRV']['MYSQLi']    = function_exists('mysqli_connect') ? 'Pass' : 'Fail';
        $scr_tests['SRV']['MYSQL_VER'] = version_compare(WIS_SCR_DB::getVersion(), '5.0', '>=') ? 'Pass' : 'Fail';
        $scr_tests['SRV']['ALL']       = !in_array('Fail', $scr_tests['SRV']) ? 'Pass' : 'Fail';
        /* RESERVED FILES */
        /* $scr_tests['RES']['INSTALL'] = !(self::hasInstallerFiles()) ? 'Pass' : 'Fail'; */
        $scr_tests['Success']        = $scr_tests['PHP']['ALL'] == 'Pass'
        && $scr_tests['IO']['ALL'] == 'Pass'
        && $scr_tests['SRV']['ALL'] == 'Pass'
        && $scr_tests['RES']['INSTALL'] == 'Pass';
        $scr_tests['Warning'] = $scr_tests['IO']['WPROOT'] == 'Warn';
        return $scr_tests;
    }
    /**
     * Gets the system checks which are not required
     *
     * @return array   An array of system checks
     */
    public static function getChecks()
    {
        $checks = array();
        /* PHP/SYSTEM SETTINGS */
		/* Web Server */
        $php_test0 = false;
        foreach ($GLOBALS['WIS_SCRAPPER_SERVER_LIST'] as $value) {
            if (stristr($_SERVER['SERVER_SOFTWARE'], $value)) {
                $php_test0 = true;
                break;
            }
        }
        $php_test1 = ini_get("open_basedir");
        $php_test1 = empty($php_test1) ? true : false;
        $php_test2 = ini_get("max_execution_time");
        $php_test2 = ($php_test2 > WIS_SCRAPPER_SCAN_TIMEOUT) || (strcmp($php_test2, 'Off') == 0 || $php_test2 == 0) ? true : false;
        $php_test3 = function_exists('mysqli_connect');
        $php_test4 = WIS_SCR_Util::$on_php_53_plus ? true : false;
        $checks['SRV']['PHP']['websrv']   = $php_test0;
        $checks['SRV']['PHP']['openbase'] = $php_test1;
        $checks['SRV']['PHP']['maxtime']  = $php_test2;
        $checks['SRV']['PHP']['mysqli']   = $php_test3;
        $checks['SRV']['PHP']['version']  = $php_test4;
        $checks['SRV']['PHP']['ALL']      = ($php_test0 && $php_test1 && $php_test2 && $php_test3 && $php_test4) ? 'Good' : 'Warn';
        /* WORDPRESS SETTINGS */
        global $wp_version;
        $wp_test1 = version_compare($wp_version, WIS_SCRAPPER_SCAN_MIN_WP) >= 0 ? true : false;
        /* Core Files */
        $files                  = array();
        $files['wp-config.php'] = file_exists(WIS_SCR_Util::safePath(WIS_SCRAPPER_WPROOTPATH.'/wp-config.php'));
        /** searching wp-config in working word press is not worthy
         * if this script is executing that means wp-config.php exists :)
         * we need to know the core folders and files added by the user at this point
         * retaining old logic as else for the case if its used some where else
         */
        /* Core dir and files logic */
        if (isset($_POST['file_notice']) && isset($_POST['dir_notice'])) {
            /* means if there are core directories excluded or core files excluded return false */
            if ((bool) $_POST['file_notice'] || (bool) $_POST['dir_notice'])
                $wp_test2 = false;
            else $wp_test2 = true;
        }else {
            $wp_test2 = $files['wp-config.php'];
        }
        /* Cache */
        $Package       = WIS_SCR_Package::getActive();
        $cache_path    = WIS_SCR_Util::safePath(WP_CONTENT_DIR).'/cache';
        $dirEmpty      = WIS_SCR_Util::isDirectoryEmpty($cache_path);
        $dirSize       = WIS_SCR_Util::getDirectorySize($cache_path);
        $cach_filtered = in_array($cache_path, explode(';', $Package->Archive->FilterDirs));
        $wp_test3      = ($cach_filtered || $dirEmpty || $dirSize < WIS_SCRAPPER_SCAN_CACHESIZE ) ? true : false;
        $wp_test4      = is_multisite();
        $checks['SRV']['WP']['version'] = $wp_test1;
        $checks['SRV']['WP']['core']    = $wp_test2;
        $checks['SRV']['WP']['cache']   = $wp_test3;
        $checks['SRV']['WP']['ismu']    = $wp_test4;
        $checks['SRV']['WP']['ALL']     = $wp_test1 && $wp_test2 && $wp_test3 && !$wp_test4 ? 'Good' : 'Warn';
        return $checks;
    }
    /**
     * Check to see if scrapper installer files are present
     *
     * @return bool   True if any reserved files are found
     */    
    /**
     * Gets a list of all the installer files by name and full path
     *
     * @return array [file_name, file_path]
     */
    /**
     * Get the IP of a client machine
     *
     * @return string   IP of the client machine
     */
    public static function getClientIP() {
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            return $_SERVER["REMOTE_ADDR"];
        } else if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
            return $_SERVER["HTTP_CLIENT_IP"];
        }
        return '';
    }
    /**
     * Get PHP memory useage
     *
     * @return string   Returns human readable memory useage.
     */
    public static function getPHPMemory($peak = false) {
        if ($peak) {
            $result = 'Unable to read PHP peak memory usage';
            if (function_exists('memory_get_peak_usage')) {
                $result = WIS_SCR_Util::byteSize(memory_get_peak_usage(true));
            }
        } else {
            $result = 'Unable to read PHP memory usage';
            if (function_exists('memory_get_usage')) {
                $result = WIS_SCR_Util::byteSize(memory_get_usage(true));
            }
        }
        return $result;
    }
}
?>