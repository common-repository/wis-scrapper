<?php
if (!defined('WIS_SCRAPPER_VERSION')) exit; /* Exit if accessed directly */
class WIS_SCR_Database
{
    /* PUBLIC */
    public $Type = 'MySQL';
    public $Size;
    public $File;
    public $Path;
    public $FilterTables;
    public $FilterOn;
    public $Name;
    public $Compatible;
    public $Comments;
    /* PROTECTED */
    protected $Package;
    /* PRIVATE */
    private $dbStorePath;
    private $EOFMarker;
    private $networkFlush;
    /**
     *  Init this object
     */
    function __construct($package)
    {
        $this->Package      = $package;
        $this->EOFMarker    = "";
        $package_zip_flush  = WIS_SCR_Settings::Get('package_zip_flush');
        $this->networkFlush = empty($package_zip_flush) ? false : $package_zip_flush;
    }
    /**
     *  Build the database script
     *
     *  @param obj $package A reference to the package that this database object belongs in
     *
     *  @return null
     */
    public function build($package)
    {
        try {
            $this->Package = $package;
            $time_start        = WIS_SCR_Util::getMicrotime();
            $this->Package->setStatus(WIS_SCR_PackageStatus::DBSTART);
            $this->dbStorePath = "{$this->Package->StorePath}/{$this->File}";
            $package_mysqldump        = WIS_SCR_Settings::Get('package_mysqldump');
            $package_phpdump_qrylimit = WIS_SCR_Settings::Get('package_phpdump_qrylimit');
            $mysqlDumpPath        = WIS_SCR_DB::getMySqlDumpPath();
            $mode                 = ($mysqlDumpPath && $package_mysqldump) ? 'MYSQLDUMP' : 'PHP';
            $reserved_db_filepath = WIS_SCRAPPER_WPROOTPATH.'database.sql';
            switch ($mode) {
                case 'MYSQLDUMP':
                    $this->mysqlDump($mysqlDumpPath);
                    break;
                case 'PHP' :
                    $this->phpDump();
                    break;
            }
            $time_end = WIS_SCR_Util::getMicrotime();
            $time_sum = WIS_SCR_Util::elapsedTime($time_end, $time_start);
            /* File below 10k will be incomplete */
            $sql_file_size = filesize($this->dbStorePath);
            if ($sql_file_size < 10000) {
                WIS_SCR_Log::Error("SQL file size too low.", "File does not look complete.  Check permission on file and parent directory at [{$this->dbStorePath}]");
            }
            $this->Size = @filesize($this->dbStorePath);
            $this->Package->setStatus(WIS_SCR_PackageStatus::DBDONE);
        } catch (Exception $e) {
        }
    }
    /**
     *  Get the database meta-data suc as tables as all there details
     *
     *  @return array Returns an array full of meta-data about the database
     */
    public function getScannerData()
    {
        global $wpdb;
        $filterTables = isset($this->FilterTables) ? explode(',', $this->FilterTables) : null;
        $tblCount     = 0;
        $tables                     = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        $info                       = array();
        $info['Status']['Success']  = is_null($tables) ? false : true;
        /* DB_Case for the database name is never checked on */
        $info['Status']['DB_Case']  = 'Good';
        $info['Status']['DB_Rows']  = 'Good';
        $info['Status']['DB_Size']  = 'Good';
        $info['Status']['TBL_Case'] = 'Good';
        $info['Status']['TBL_Rows'] = 'Good';
        $info['Status']['TBL_Size'] = 'Good';
        $info['Size']       = 0;
        $info['Rows']       = 0;
        $info['TableCount'] = 0;
        $info['TableList']  = array();
        $tblCaseFound       = 0;
        $tblRowsFound       = 0;
        $tblSizeFound       = 0;
        /* Grab Table Stats */
        foreach ($tables as $table) {
            $name = $table["Name"];
            if ($this->FilterOn && is_array($filterTables)) {
                if (in_array($name, $filterTables)) {
                    continue;
                }
            }
            $size = ($table["Data_length"] + $table["Index_length"]);
            $rows = empty($table["Rows"]) ? '0' : $table["Rows"];
            $info['Size'] += $size;
            $info['Rows'] += ($table["Rows"]);
            $info['TableList'][$name]['Case']  = preg_match('/[A-Z]/', $name) ? 1 : 0;
            $info['TableList'][$name]['Rows']  = number_format($rows);
            $info['TableList'][$name]['Size']  = WIS_SCR_Util::byteSize($size);
			$info['TableList'][$name]['USize'] = $size;
            $tblCount++;
            /* Table Uppercase */
            if ($info['TableList'][$name]['Case']) {
                if (!$tblCaseFound) {
                    $tblCaseFound = 1;
                }
            }
            /* Table Row Count */
            if ($rows > WIS_SCRAPPER_SCAN_DB_TBL_ROWS) {
                if (!$tblRowsFound) {
                    $tblRowsFound = 1;
                }
            }
            /* Table Size */
            if ($size > WIS_SCRAPPER_SCAN_DB_TBL_SIZE) {
                if (!$tblSizeFound) {
                    $tblSizeFound = 1;
                }
            }
        }
        $info['Status']['DB_Case'] = preg_match('/[A-Z]/', $wpdb->dbname) ? 'Warn' : 'Good';
        $info['Status']['DB_Rows'] = ($info['Rows'] > WIS_SCRAPPER_SCAN_DB_ALL_ROWS) ? 'Warn' : 'Good';
        $info['Status']['DB_Size'] = ($info['Size'] > WIS_SCRAPPER_SCAN_DB_ALL_SIZE) ? 'Warn' : 'Good';
        $info['Status']['TBL_Case'] = ($tblCaseFound) ? 'Warn' : 'Good';
        $info['Status']['TBL_Rows'] = ($tblRowsFound) ? 'Warn' : 'Good';
        $info['Status']['TBL_Size'] = ($tblSizeFound) ? 'Warn' : 'Good';
        $info['Size']       = WIS_SCR_Util::byteSize($info['Size']) or "unknown";
        $info['Rows']       = number_format($info['Rows']) or "unknown";
        $info['TableList']  = $info['TableList'] or "unknown";
        $info['TableCount'] = $tblCount;
        return $info;
    }
    /**
     *  Build the database script using mysqldump
     *
     *  @return bool  Returns true if the sql script was succesfully created
     */
    private function mysqlDump($exePath)
    {
        global $wpdb;
        $host           = explode(':', DB_HOST);
        $host           = reset($host);
        $port           = strpos(DB_HOST, ':') ? end(explode(':', DB_HOST)) : '';
        $name           = DB_NAME;
        $mysqlcompat_on = isset($this->Compatible) && strlen($this->Compatible);
        /* Build command */
        $cmd = escapeshellarg($exePath);
        $cmd .= ' --no-create-db';
        $cmd .= ' --single-transaction';
        $cmd .= ' --hex-blob';
        $cmd .= ' --skip-add-drop-table';
        /* Compatibility mode */
        if ($mysqlcompat_on) {
            WIS_SCR_Log::Info("COMPATIBLE: [{$this->Compatible}]");
            $cmd .= " --compatible={$this->Compatible}";
        }
        /* Filter tables */
        $tables       = $wpdb->get_col('SHOW TABLES');
        $filterTables = isset($this->FilterTables) ? explode(',', $this->FilterTables) : null;
        $tblAllCount  = count($tables);
        if (is_array($filterTables) && $this->FilterOn) {
            foreach ($tables as $key => $val) {
                if (in_array($tables[$key], $filterTables)) {
                    $cmd .= " --ignore-table={$name}.{$tables[$key]} ";
                    unset($tables[$key]);
                }
            }
        }
        $cmd .= ' -u '.escapeshellarg(DB_USER);
        if(WIS_SCR_Util::isWindows())
         $cmd .= (DB_PASSWORD) ? ' -p'.escapeshellcmd(DB_PASSWORD) : '';
        else
         $cmd .= (DB_PASSWORD) ? ' -p'.escapeshellarg(DB_PASSWORD) : '';
        $cmd .= ' -h '.escapeshellarg($host);
        $cmd .= (!empty($port) && is_numeric($port) ) ?
            ' -P '.$port : '';
        $cmd .= ' -r '.escapeshellarg($this->dbStorePath);
        $cmd .= ' '.escapeshellarg(DB_NAME);
        $cmd .= ' 2>&1';
        $output = shell_exec($cmd);
         /* Password bug > 5.6 (@see http://bugs.mysql.com/bug.php?id=66546) */
        if (trim($output) === 'Warning: Using a password on the command line interface can be insecure.') {
            $output = '';
        }
        $output = (strlen($output)) ? $output : "Ran from {$exePath}";
        $tblCreateCount = count($tables);
        $tblFilterCount = $tblAllCount - $tblCreateCount;
        /* DEBUG */
        $sql_footer = "\n\n/* Scrapper WordPress Timestamp: ".date("Y-m-d H:i:s")."*/\n";
        $sql_footer .= "/* ".WIS_SCRAPPER_DB_EOF_MARKER." */\n";
        file_put_contents($this->dbStorePath, $sql_footer, FILE_APPEND);
        return ($output) ? false : true;
    }
    /**
     *  Build the database script using php
     *
     *  @return bool  Returns true if the sql script was succesfully created
     */
    private function phpDump()
    {
        global $wpdb;
        $wpdb->query("SET session wait_timeout = ".WIS_SCRAPPER_DB_MAX_TIME);
        $handle = fopen($this->dbStorePath, 'w+');
        $tables = $wpdb->get_col('SHOW TABLES');
        $filterTables = isset($this->FilterTables) ? explode(',', $this->FilterTables) : null;
        $tblAllCount  = count($tables);
        $qryLimit     = WIS_SCR_Settings::Get('package_phpdump_qrylimit');
        if (is_array($filterTables) && $this->FilterOn) {
            foreach ($tables as $key => $val) {
                if (in_array($tables[$key], $filterTables)) {
                    unset($tables[$key]);
                }
            }
        }
        $tblCreateCount = count($tables);
        $tblFilterCount = $tblAllCount - $tblCreateCount;
		/* Added 'NO_AUTO_VALUE_ON_ZERO' at plugin version 1.2.12 to fix : */
		/* **ERROR** database error write 'Invalid default value for for older mysql versions */
        $sql_header  = "/* SCRAPPER-LITE (PHP BUILD MODE) MYSQL SCRIPT CREATED ON : ".@date("Y-m-d H:i:s")." */\n\n";
		$sql_header .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n";
        $sql_header .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        fwrite($handle, $sql_header);
        /* BUILD CREATES: */
        /* All creates must be created before inserts do to foreign key constraints */
        foreach ($tables as $table) {
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            @fwrite($handle, "{$create[1]};\n\n");
        }
        /* BUILD INSERTS: */
        /* Create Insert in 100 row increments to better handle memory */
        foreach ($tables as $table) {
            $row_count = $wpdb->get_var("SELECT Count(*) FROM `{$table}`");
            if ($row_count > $qryLimit) {
                $row_count = ceil($row_count / $qryLimit);
            } else if ($row_count > 0) {
                $row_count = 1;
            }
            if ($row_count >= 1) {
                fwrite($handle, "\n/* INSERT TABLE DATA: {$table} */\n");
            }
            for ($i = 0; $i < $row_count; $i++) {
                $sql   = "";
                $limit = $i * $qryLimit;
                $query = "SELECT * FROM `{$table}` LIMIT {$limit}, {$qryLimit}";
                $rows  = $wpdb->get_results($query, ARRAY_A);
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        $sql .= "INSERT INTO `{$table}` VALUES(";
                        $num_values  = count($row);
                        $num_counter = 1;
                        foreach ($row as $value) {
                            if (is_null($value) || !isset($value)) {
                                ($num_values == $num_counter) ? $sql .= 'NULL' : $sql .= 'NULL, ';
                            } else {
                                ($num_values == $num_counter) 
									? $sql .= '"' . SCR_DB::escSQL($value, true) . '"'
									: $sql .= '"' . SCR_DB::escSQL($value, true) . '", ';
                            }
                            $num_counter++;
                        }
                        $sql .= ");\n";
                    }
                    fwrite($handle, $sql);
                }
            }
            /* Flush buffer if enabled */
            if ($this->networkFlush) {
                WIS_SCR_Util::fcgiFlush();
            }
            $sql  = null;
            $rows = null;
        }
        $sql_footer = "\nSET FOREIGN_KEY_CHECKS = 1; \n\n";
        $sql_footer .= "/* Scrapper WordPress Timestamp: ".date("Y-m-d H:i:s")."*/\n";
        $sql_footer .= "/* ".WIS_SCRAPPER_DB_EOF_MARKER." */\n";
        fwrite($handle, $sql_footer);
        $wpdb->flush();
        fclose($handle);
    }
}
?>