<?php
/*
	Plugin Name: WIS Scrapper
	Plugin URI: https://wisdominfosoft.com/wis-scrapper
	Description: Custom plugin to download WordPress setup with full database. This plugin will help to make the migration and backup process easier for all.
	Version: 0.1
	Author: Gurpal
	Author URI: https://wisdominfosoft.com
	License: GPLv2
	Copyright 2019 Gurpal (email: gurpal@wisdominfosoft.com)
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.
*/
if ( phpversion() > 5 ) {
	include("define.php");
	register_activation_hook(__FILE__,   'wis_activate_deactivate::scrapper_activate');
	register_deactivation_hook(__FILE__, 'wis_activate_deactivate::scrapper_deactivate');
	register_uninstall_hook(__FILE__, 'wis_activate_deactivate::scrapper_uninstall');
	class wis_activate_deactivate{
		public static function scrapper_activate(){
			global $wpdb;
			if (WIS_SCRAPPER_VERSION != get_option("wis_backup_version_plugin")) 
			{
				$table_name = $wpdb->prefix . "wis_backup_packages";
				$sql = "CREATE TABLE `{$table_name}` (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(250) NOT NULL,
				hash VARCHAR(50) NOT NULL,
				status INT(11) NOT NULL,
				created DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
				owner VARCHAR(60) NOT NULL,
				package MEDIUMBLOB NOT NULL,
				PRIMARY KEY  (id),
				KEY hash (hash))";
				require_once(WIS_SCRAPPER_WPROOTPATH . 'wp-admin/includes/upgrade.php');
				@dbDelta($sql);
			}
			/* WordPress Options Hooks */
			update_option('wis_backup_version_plugin', WIS_SCRAPPER_VERSION);
			/* Setup All Directories */
		}
		public static function scrapper_uninstall(){
			self::remove_options();
		}
		public static function scrapper_deactivate(){
			self::remove_options();
		}
		public static function remove_options(){
			$dir = WIS_SCRAPPER_SSDIR_PATH."/*";
			foreach (glob($dir) as $file) {
				if( is_dir($file) ){
					continue;
				}
				unlink($file);
			}
			global $wpdb;
			$table_name = $wpdb->prefix . "wis_backup_packages";
			$wpdb->query("DROP TABLE  IF EXISTS `{$table_name}`");
			delete_option('wis_backup_version_plugin');
			delete_option('wis_backup_package_active');
			delete_option('wis_backup_settings');
		}
	}
	include("add_helpers.php");
}