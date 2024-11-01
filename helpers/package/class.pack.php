<?php
if (!defined('WIS_SCRAPPER_VERSION')) exit;  /*Exit if accessed directly */
require_once (WIS_SCRAPPER_PLUGIN_PATH.'helpers/utilities/class.u.php');
require_once (WIS_SCRAPPER_PLUGIN_PATH.'helpers/package/class.pack.archive.php');
require_once (WIS_SCRAPPER_PLUGIN_PATH.'helpers/package/class.pack.database.php');
final class WIS_SCR_PackageStatus
{
    private function __construct()
    {
    }
    const START    = 10;
    const DBSTART  = 20;
    const DBDONE   = 30;
    const ARCSTART = 40;
    const ARCDONE  = 50;
    const COMPLETE = 100;
}
final class WIS_SCR_PackageType
{
    const MANUAL    = 0;
    const SCHEDULED = 1;
}
/**
 * Class used to store and process all Package logic
 *
 * @package Scrapper\classes
 */
class WIS_SCR_Package
{
    const OPT_ACTIVE = 'wis_backup_package_active';
    /* Properties */
    public $Created;
    public $Version;
    public $VersionWP;
    public $VersionDB;
    public $VersionPHP;
    public $VersionOS;
    public $ID;
    public $Name;
    public $Hash;
    public $NameHash;
    public $Type;
    public $Notes;
    public $StorePath;
    public $StoreURL;
    public $ScanFile;
    public $Runtime;
    public $ExeSize;
    public $ZipSize;
    public $Status;
    public $WPUser;
    /* Objects */
    public $Archive;
    /* public $Installer; */
    public $Database;
    /**
     *  Manages the Package Process
     */
    function __construct()
    {
        $this->ID      = null;
        $this->Version = WIS_SCRAPPER_VERSION;
        $this->Type      = WIS_SCR_PackageType::MANUAL;
        $this->Name      = self::getDefaultName();
        $this->Notes     = null;
        $this->StoreURL  = WIS_SCR_Util::snapshotURL();
        $this->StorePath = WIS_SCRAPPER_SSDIR_PATH_TMP;
        $this->Database  = new WIS_SCR_Database($this);
        $this->Archive   = new WIS_SCR_Archive($this);
    }
    /**
     * Generates a json scan report
     *
     * @return array of scan results
     *
     * @notes: Testing = /wp-admin/admin-ajax.php?action=scrapper_package_scan
     */
    public function runScanner()
    {
        $timerStart     = WIS_SCR_Util::getMicrotime();
        $report         = array();
        $this->ScanFile = "{$this->NameHash}_scan.json";
        $report['RPT']['ScanTime'] = "0";
        $report['RPT']['ScanFile'] = $this->ScanFile;
        /* SERVER */
        $srv           = WIS_SCR_Server::getChecks();
        $report['SRV'] = $srv['SRV'];
        /* FILES */
        $this->Archive->getScannerData();
        $dirCount  = count($this->Archive->Dirs);
        $fileCount = count($this->Archive->Files);
        $fullCount = $dirCount + $fileCount;
        $report['ARC']['Size']      = WIS_SCR_Util::byteSize($this->Archive->Size) or "unknown";
        $report['ARC']['DirCount']  = number_format($dirCount);
        $report['ARC']['FileCount'] = number_format($fileCount);
        $report['ARC']['FullCount'] = number_format($fullCount);
        $report['ARC']['FilterDirsAll'] = $this->Archive->FilterDirsAll;
        $report['ARC']['FilterFilesAll'] = $this->Archive->FilterFilesAll;
        $report['ARC']['FilterExtsAll'] = $this->Archive->FilterExtsAll;
        $report['ARC']['FilterInfo'] = $this->Archive->FilterInfo;
        $report['ARC']['RecursiveLinks'] = $this->Archive->RecursiveLinks;
        $report['ARC']['UnreadableItems'] = array_merge($this->Archive->FilterInfo->Files->Unreadable,$this->Archive->FilterInfo->Dirs->Unreadable);
        $report['ARC']['Status']['Size']  = ($this->Archive->Size > WIS_SCRAPPER_SCAN_SIZE_DEFAULT) ? 'Warn' : 'Good';
        $report['ARC']['Status']['Names'] = (count($this->Archive->FilterInfo->Files->Warning) + count($this->Archive->FilterInfo->Dirs->Warning)) ? 'Warn' : 'Good';
        $report['ARC']['Status']['UnreadableItems'] = !empty($this->Archive->RecursiveLinks) || !empty($report['ARC']['UnreadableItems'])? 'Warn' : 'Good';
        $report['ARC']['Dirs']  = $this->Archive->Dirs;
        $report['ARC']['Files'] = $this->Archive->Files;
        $report['ARC']['Status']['AddonSites'] = count($this->Archive->FilterInfo->Dirs->AddonSites) ? 'Warn' : 'Good';
        /* DATABASE */
        $db  = $this->Database->getScannerData();
        $report['DB'] = $db;
        $warnings = array(
            $report['SRV']['PHP']['ALL'],
            $report['SRV']['WP']['ALL'],
            $report['ARC']['Status']['Size'],
            $report['ARC']['Status']['Names'],
            $db['Status']['DB_Size'],
            $db['Status']['DB_Rows']);
        /* array_count_values will throw a warning message if it has null values, */
        /* so lets replace all nulls with empty string */
        foreach ($warnings as $i => $value) {
            if (is_null($value)) {
                $warnings[$i] = '';
            }
        }
        $warn_counts               = is_array($warnings) ? array_count_values($warnings) : 0;
        $report['RPT']['Warnings'] = is_null($warn_counts['Warn']) ? 0 : $warn_counts['Warn'];
        $report['RPT']['Success']  = is_null($warn_counts['Good']) ? 0 : $warn_counts['Good'];
        $report['RPT']['ScanTime'] = WIS_SCR_Util::elapsedTime(WIS_SCR_Util::getMicrotime(), $timerStart);
        $fp                        = fopen(WIS_SCRAPPER_SSDIR_PATH_TMP."/{$this->ScanFile}", 'w');
        fwrite($fp, json_encode($report));
        fclose($fp);
        return $report;
    }
    /**
     * Starts the package build process
     *
     * @return obj Returns a SCR_Package object
     */
    public function runBuild()
    {
        global $wp_version;
        global $wpdb;
        global $current_user;
        $timerStart = WIS_SCR_Util::getMicrotime();
        $this->Archive->File   = "{$this->NameHash}_archive.zip";
        $this->Database->File  = "{$this->NameHash}_database.sql";
        $this->WPUser          = isset($current_user->user_login) ? $current_user->user_login : 'unknown';
        /* START LOGGING */
        WIS_SCR_Log::Open($this->NameHash);
        $php_max_time   = @ini_get("max_execution_time");
        $php_max_memory = @ini_set('memory_limit', WIS_SCRAPPER_PHP_MAX_MEMORY);
        $php_max_time   = ($php_max_time == 0) ? "(0) no time limit imposed" : "[{$php_max_time}] not allowed";
        $php_max_memory = ($php_max_memory === false) ? "Unabled to set php memory_limit" : WIS_SCRAPPER_PHP_MAX_MEMORY." ({$php_max_memory} default)";
        /* CREATE DB RECORD */
        $packageObj = serialize($this);
        if (!$packageObj) {
            WIS_SCR_Log::Error("Unable to serialize pacakge object while building record.");
        }
        $this->ID = $this->getHashKey($this->Hash);
        if ($this->ID != 0) {
            $this->setStatus(WIS_SCR_PackageStatus::START);
        } else {
            $results = $wpdb->insert($wpdb->prefix."wis_backup_packages",
                array(
                    'name' => $this->Name,
                    'hash' => $this->Hash,
                    'status' => WIS_SCR_PackageStatus::START,
                    'created' => current_time('mysql', get_option('gmt_offset', 1)),
                    'owner' => isset($current_user->user_login) ? $current_user->user_login : 'unknown',
                    'package' => $packageObj)
            );
            if ($results === false) {
                $wpdb->print_error();
                WIS_SCR_Log::Error("Scrapper is unable to insert a package record into the database table.", "'{$wpdb->last_error}'");
            }
            $this->ID = $wpdb->insert_id;
        }
        /* START BUILD */
        /* PHPs serialze method will return the object, but the ID above is not passed */
        /* for one reason or another so passing the object back in seems to do the trick */
        $this->Database->build($this);
        $this->Archive->build($this);
        /* INTEGRITY CHECKS */
        $dbSizeRead  = WIS_SCR_Util::byteSize($this->Database->Size);
        $zipSizeRead = WIS_SCR_Util::byteSize($this->Archive->Size);
        if (!($this->Archive->Size && $this->Database->Size)) {}
        /* Validate SQL files completed */
        $sql_tmp_path     = WIS_SCR_Util::safePath(WIS_SCRAPPER_SSDIR_PATH_TMP.'/'.$this->Database->File);
        $sql_complete_txt = WIS_SCR_Util::tailFile($sql_tmp_path, 3);
        if (!strstr($sql_complete_txt, 'SCRAPPER_MYSQLDUMP_EOF')) {}
        $timerEnd = WIS_SCR_Util::getMicrotime();
        $timerSum = WIS_SCR_Util::elapsedTime($timerEnd, $timerStart);
        $this->Runtime = $timerSum;
        $this->ExeSize = $exeSizeRead;
        $this->ZipSize = $zipSizeRead;
        $this->buildCleanup();
        /* FINAL REPORT */
        $info = "\n********************************************************************************\n";
        $info .= "RECORD ID:[{$this->ID}]\n";
        $info .= "TOTAL PROCESS RUNTIME: {$timerSum}\n";
        $info .= "PEAK PHP MEMORY USED: ".WIS_SCR_Server::getPHPMemory(true)."\n";
        $info .= "DONE PROCESSING => {$this->Name} ".@date(get_option('date_format')." ".get_option('time_format'))."\n";
        $this->setStatus(WIS_SCR_PackageStatus::COMPLETE);
        return $this;
    }
    /**
     *  Saves the active options associted with the active(latest) package.
     *
     *  @see SCR_Package::getActive
     *
     *  @param $_POST $post The Post server object
     * 
     *  @return null
     */
    public function saveActive($post = null)
    {
        global $wp_version;
        if (isset($post)) {
            $post = stripslashes_deep($post);
            $name       = ( isset($post['package-name']) && !empty($post['package-name'])) ? $post['package-name'] : self::getDefaultName();
            $name       = substr(sanitize_file_name($name), 0, 40);
            $name       = str_replace(array('.', '-', ';', ':', "'", '"'), '', $name);
            $filter_dirs  = isset($post['filter-dirs'])  ? $this->Archive->parseDirectoryFilter($post['filter-dirs']) : '';
            $filter_files = isset($post['filter-files']) ? $this->Archive->parseFileFilter($post['filter-files']) : '';
            $filter_exts  = isset($post['filter-exts'])  ? $this->Archive->parseExtensionFilter($post['filter-exts']) : '';
            $tablelist    = isset($post['dbtables'])	 ? implode(',', $post['dbtables']) : '';
            $compatlist   = isset($post['dbcompat'])	 ? implode(',', $post['dbcompat']) : '';
            $dbversion    = WIS_SCR_DB::getVersion();
            $dbversion    = is_null($dbversion) ? '- unknown -'  : $dbversion;
            $dbcomments   = WIS_SCR_DB::getVariable('version_comment');
            $dbcomments   = is_null($dbcomments) ? '- unknown -' : $dbcomments;
            /* PACKAGE */
            $this->Created    = date("Y-m-d H:i:s");
            $this->Version    = WIS_SCRAPPER_VERSION;
            $this->VersionOS  = defined('PHP_OS') ? PHP_OS : 'unknown';
            $this->VersionWP  = $wp_version;
            $this->VersionPHP = phpversion();
            $this->VersionDB  = esc_html($dbversion);
            $this->Name       = sanitize_text_field($name);
            $this->Hash       = $this->makeHash();
            $this->NameHash   = "{$this->Name}_{$this->Hash}";
            $this->Notes                    = WIS_SCR_Util::escSanitizeTextAreaField($post['package-notes']);
            /* ARCHIVE */
            $this->Archive->PackDir         = rtrim(WIS_SCRAPPER_WPROOTPATH, '/');
            $this->Archive->Format          = 'ZIP';
            $this->Archive->FilterOn        = isset($post['filter-on']) ? 1 : 0;
            $this->Archive->ExportOnlyDB    = isset($post['export-onlydb']) ? 1 : 0;
            $this->Archive->FilterDirs      = WIS_SCR_Util::escSanitizeTextAreaField($filter_dirs);
            $this->Archive->FilterFiles    = WIS_SCR_Util::escSanitizeTextAreaField($filter_files);
            $this->Archive->FilterExts      = str_replace(array('.', ' '), '', WIS_SCR_Util::escSanitizeTextAreaField($filter_exts));
            /* DATABASE */
            $this->Database->FilterOn       = isset($post['dbfilter-on']) ? 1 : 0;
            $this->Database->FilterTables   = esc_html($tablelist);
            $this->Database->Compatible     = $compatlist;
            $this->Database->Comments       = esc_html($dbcomments);
            update_option(self::OPT_ACTIVE, $this);
        }
    }
    /**
     * Save any property of this class through reflection
     *
     * @param $property     A valid public property in this class
     * @param $value        The value for the new dynamic property
     *
     * @return null
     */
    public function saveActiveItem($property, $value)
    {
        $package = self::getActive();
        $reflectionClass = new ReflectionClass($package);
        $reflectionClass->getProperty($property)->setValue($package, $value);
        update_option(self::OPT_ACTIVE, $package);
    }
    /**
     * Sets the status to log the state of the build
     *
     * @param $status The status level for where the package is
     *
     * @return void
     */
    public function setStatus($status)
    {
        global $wpdb;
        $packageObj = serialize($this);
        if (!isset($status)) {}
        if (!$packageObj) {}
        $wpdb->flush();
        $table = $wpdb->prefix."wis_backup_packages";
        $sql   = "UPDATE `{$table}` SET  status = {$status}, package = '{$packageObj}'	WHERE ID = {$this->ID}";
        $wpdb->query($sql);
    }
    /**
     * Does a hash already exist
     *
     * @param string $hash An existing hash value
     *
     * @return int Returns 0 if no hash is found, if found returns the table ID
     */
    public function getHashKey($hash)
    {
        global $wpdb;
        $table = $wpdb->prefix."wis_backup_packages";
        $qry   = $wpdb->get_row("SELECT ID, hash FROM `{$table}` WHERE hash = '{$hash}'");
        if (strlen($qry->hash) == 0) {
            return 0;
        } else {
            return $qry->ID;
        }
    }
    /**
     *  Makes the hashkey for the package files
	 *  Rare cases will need to fall back to GUID
     *
     *  @return string  Returns a unique hashkey
     */
    public function makeHash()
    {
         /* IMPORTANT!  Be VERY careful in changing this format - the FTP delete logic requires 3 segments with the last segment to be the date in YmdHis format. */
        try {
            if (function_exists('random_bytes') && WIS_SCR_Util::PHP53()) {
                return bin2hex(random_bytes(8)) . mt_rand(1000, 9999) . '_' . date("Y-m-d_H-i-s");
            } else {
                return strtolower(md5(uniqid(rand(), true))) . '_' . date("Y-m-d_H-i-s");
            }
        } catch (Exception $exc) {
            return strtolower(md5(uniqid(rand(), true))) . '_' . date("Y-m-d_H-i-s");
        }
    }
    /**
     * Gets the active package which is defined as the package that was lasted saved.
     * Do to cache issues with the built in WP function get_option moved call to a direct DB call.
     *
     * @see SCR_Package::saveActive
     *
     * @return obj  A copy of the SCR_Package object
     */
    public static function getActive()
    {
        global $wpdb;
        $obj = new WIS_SCR_Package();
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s LIMIT 1", self::OPT_ACTIVE));
        if (is_object($row)) {
            $obj = @unserialize($row->option_value);
        }
        /* Incase unserilaize fails */
        $obj = (is_object($obj)) ? $obj : new WIS_SCR_Package();
        return $obj;
    }
    /**
     * Gets the Package by ID
     *  
     * @param int $id A valid package id form the scrapper_packages table
     *
     * @return obj  A copy of the SCR_Package object
     */
    public static function getByID($id)
    {
        global $wpdb;
        $obj = new WIS_SCR_Package();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$wpdb->prefix}wis_backup_packages` WHERE ID = %s", $id));
        if (is_object($row)) {
            $obj         = @unserialize($row->package);
            $obj->Status = $row->status;
        }
        /* Incase unserilaize fails */
        $obj = (is_object($obj)) ? $obj : null;
        return $obj;
    }
    /**
     *  Gets a default name for the package
     *
     *  @return string   A default package name such as 20170218_blogname
     */
    public static function getDefaultName($preDate = true)
    {
        /* Remove specail_chars from final result */
        $special_chars = array(".", "-");
        $name          = ($preDate) 
        ? date('Ymd') . '_' . sanitize_title(get_bloginfo('name', 'display'))
        : sanitize_title(get_bloginfo('name', 'display')) . '_' . date('Ymd');
        $name          = substr(sanitize_file_name($name), 0, 40);
        $name          = str_replace($special_chars, '', $name);
        return $name;
    }
    /**
     *  Cleanup all tmp files
     *
     *  @param all empty all contents
     *
     *  @return null
     */
    public static function tempFileCleanup($all = false)
    {
        /* Delete all files now */
        if ($all) {
            $dir = WIS_SCRAPPER_SSDIR_PATH_TMP."/*";
            foreach (glob($dir) as $file) {
                @unlink($file);
            }
        }
        /* Remove scan files that are 24 hours old */
        else {
            $dir = WIS_SCRAPPER_SSDIR_PATH_TMP."/*_scan.json";
            foreach (glob($dir) as $file) {
                if (filemtime($file) <= time() - 86400) {
                    @unlink($file);
                }
            }
        }
    }
    /**
     *  Provides various date formats
     * 
     *  @param $date    The date to format
     *  @param $format  Various date formats to apply
     * 
     *  @return a formated date based on the $format
     */
    public static function getCreatedDateFormat($date, $format = 1)
    {
        $date = new DateTime($date);
        switch ($format) {
            /* YEAR */
            case 1: return $date->format('Y-m-d H:i');
            break;
            case 2: return $date->format('Y-m-d H:i:s');
            break;
            case 3: return $date->format('y-m-d H:i');
            break;
            case 4: return $date->format('y-m-d H:i:s');
            break;
            /* MONTH */
            case 5: return $date->format('m-d-Y H:i');
            break;
            case 6: return $date->format('m-d-Y H:i:s');
            break;
            case 7: return $date->format('m-d-y H:i');
            break;
            case 8: return $date->format('m-d-y H:i:s');
            break;
            /* DAY */
            case 9: return $date->format('d-m-Y H:i');
            break;
            case 10: return $date->format('d-m-Y H:i:s');
            break;
            case 11: return $date->format('d-m-y H:i');
            break;
            case 12: return $date->format('d-m-y H:i:s');
            break;
            default :
            return $date->format('Y-m-d H:i');
        }
    }
    /**
     *  Cleans up all the tmp files as part of the package build process
     */
    private function buildCleanup()
    {
        $files   = WIS_SCR_Util::listFiles(WIS_SCRAPPER_SSDIR_PATH_TMP);
        $newPath = WIS_SCRAPPER_SSDIR_PATH;
        if (function_exists('rename')) {
            foreach ($files as $file) {
                $name = basename($file);
                if (strstr($name, $this->NameHash)) {
                    rename($file, "{$newPath}/{$name}");
                }
            }
        } else {
            foreach ($files as $file) {
                $name = basename($file);
                if (strstr($name, $this->NameHash)) {
                    copy($file, "{$newPath}/{$name}");
                    @unlink($file);
                }
            }
        }
    }
    /**
     * Get package hash
     * 
     * @return string package hash
     */
    public function getPackageHash() {
        $hashParts = explode('_', $this->Hash);
        $firstPart = substr($hashParts[0], 0, 7);
        $secondPart = substr($hashParts[1], -8);
		$packageHash = $this->Hash;
        return $packageHash;
    }
    /**
     *  Provides the full sql file path in archive
     *
     *  @return the full sql file path in archive
     */
    public function getSqlArkFilePath()
    {
        $packageHash = $this->getPackageHash();
        $sqlArkFilePath = '/WIS-Scrapper-SQL/Wis-Scrapper-Database__'.$packageHash.'.sql';
        return $sqlArkFilePath;
    }
}
?>