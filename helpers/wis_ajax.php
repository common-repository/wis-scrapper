<?php
class wis_ajax{
	public function __construct(){
		add_action( 'wp_ajax_backup_setup', [$this,'backup_setup'] );
		add_action( 'wp_ajax_package_build',[$this,'package_build']);
	}
	public function backup_setup(){
		$dir = WIS_SCRAPPER_SSDIR_PATH."/*";
		foreach (glob($dir) as $file) {
			if( is_dir($file) ){
				continue;
			}
			if( time() - filemtime($file) > 3600 ){
				unlink($file);
			}
		}
		/** Create Package **/
		$Package = new WIS_SCR_Package();
		$Package->saveActive($_POST);
		$Package = WIS_SCR_Package::getActive();
		$mysqldump_on	 = WIS_SCR_Settings::Get('package_mysqldump') && WIS_SCR_DB::getMySqlDumpPath();
		$mysqlcompat_on  = isset($Package->Database->Compatible) && strlen($Package->Database->Compatible);
		$mysqlcompat_on  = ($mysqldump_on && $mysqlcompat_on) ? true : false;
		$dbbuild_mode    = ($mysqldump_on) ? 'mysqldump' : 'PHP';
		$zip_check		 = WIS_SCR_Util::getZipPath();
		/** Scan Package **/
		WIS_SCR_Util::hasCapability('export');
		@set_time_limit(0);
		$errLevel = error_reporting();
		error_reporting(E_ERROR);
		WIS_SCR_Util::initSnapshotDirectory();
		$package = WIS_SCR_Package::getActive();
		$report = $package->runScanner();
		$package->saveActiveItem('ScanFile', $package->ScanFile);
		$json_response = WIS_SCR_JSON::safeEncode($report);
		WIS_SCR_Package::tempFileCleanup();
		error_reporting($errLevel);
		/* TODO Login Need to go here */
		$core_dir_included   = array();
		$core_files_included = array();
		$core_dir_notice     = false;
		$core_file_notice    = false;
		if (!$Package->Archive->ExportOnlyDB && isset($_POST['filter-on']) && isset($_POST['filter-dirs'])) {
			$filter_dirs = explode(";", trim($_POST['filter-dirs']));
			for ($i = 0; $i < count($filter_dirs); $i++) {
				$filter_dirs[$i] = trim($filter_dirs[$i]);
				$filter_dirs[$i] = (substr($filter_dirs[$i], -1) == "/") ? substr($filter_dirs[$i],0, strlen($filter_dirs[$i])-1):$filter_dirs[$i] ;
			}
			$core_dir_included = array_intersect($filter_dirs,
				WIS_SCR_Util::getWPCoreDirs());
			if (count($core_dir_included)) $core_dir_notice   = true;
			$filter_files = explode(";", trim($_POST['filter-files']));
			for ($i = 0; $i < count($filter_files); $i++) {
				$filter_files[$i] = trim($filter_files[$i]);
			}
			$core_files_included = array_intersect($filter_files,
				WIS_SCR_Util::getWPCoreFiles());
			if (count($core_files_included)) $core_file_notice    = true;
		}
		exit;
	}
	public function package_build() {
		WIS_SCR_Util::hasCapability('export');
		header('Content-Type: application/json');
		@set_time_limit(0);
		$errLevel = error_reporting();
		error_reporting(E_ERROR);
		WIS_SCR_Util::initSnapshotDirectory();
		$Package = WIS_SCR_Package::getActive();
		if (!is_readable(WIS_SCRAPPER_SSDIR_PATH_TMP . "/{$Package->ScanFile}")) {
			die("The scan result file was not found.  Please run the scan step before building the package.");
		}
		$Package->runBuild();
	/* JSON:Debug Response */
	/* Pass = 1, Warn = 2, Fail = 3 */
		$json = array();
		$json['Status']   = 1;
		$json['Package']  = $Package;
		$json['Runtime']  = $Package->Runtime;
		$json['ExeSize']  = $Package->ExeSize;
		$json['ZipSize']  = $Package->ZipSize;
		$json['CompleteZipURL'] = $Package->StoreURL.$Package->Archive->File;
		$json_response = json_encode($json);
	/* Simulate a Host Build Interrupt */
		echo $json_response;
		exit;
		include(WIS_SCRAPPER_PLUGIN_PATH."/templates/menus/multi-step/wis-step-3.php");
		exit;
		error_reporting($errLevel);
	}
}
new wis_ajax;