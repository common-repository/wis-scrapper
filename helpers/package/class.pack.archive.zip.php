<?php
if (!defined('WIS_SCRAPPER_VERSION')) exit; /* Exit if accessed directly */
require_once (WIS_SCRAPPER_PLUGIN_PATH.'helpers/package/class.pack.archive.php');
/**
 *  Creates a zip file using the built in PHP ZipArchive class
 */
class WIS_SCR_Zip extends WIS_SCR_Archive
{
    /* PRIVATE */
    private static $compressDir;
    private static $countDirs  = 0;
    private static $countFiles = 0;
    private static $sqlPath;
    private static $zipPath;
    private static $zipFileSize;
    private static $zipArchive;
    private static $limitItems   = 0;
    private static $networkFlush = false;
    private static $scanReport;
    /**
     *  Creates the zip file and adds the SQL file to the archive
     */
    public static function create(WIS_SCR_Archive $archive)
    {
        try {
            $timerAllStart     = WIS_SCR_Util::getMicrotime();
            $package_zip_flush = WIS_SCR_Settings::Get('package_zip_flush');
            self::$compressDir  = rtrim(WIS_SCR_Util::safePath($archive->PackDir), '/');
            self::$sqlPath      = WIS_SCR_Util::safePath("{$archive->Package->StorePath}/{$archive->Package->Database->File}");
            self::$zipPath      = WIS_SCR_Util::safePath("{$archive->Package->StorePath}/{$archive->File}");
            self::$zipArchive   = new ZipArchive();
            self::$networkFlush = empty($package_zip_flush) ? false : $package_zip_flush;
            $filterDirs       = empty($archive->FilterDirs)  ? 'not set' : $archive->FilterDirs;
            $filterExts       = empty($archive->FilterExts)  ? 'not set' : $archive->FilterExts;
            $filterFiles      = empty($archive->FilterFiles) ? 'not set' : $archive->FilterFiles;
            $filterOn         = ($archive->FilterOn) ? 'ON' : 'OFF';
            $filterDirsFormat  = rtrim(str_replace(';', "\n\t", $filterDirs));
            $filterFilesFormat = rtrim(str_replace(';', "\n\t", $filterFiles));
            $lastDirSuccess   = self::$compressDir;
            /* LOAD SCAN REPORT */
            $json             = file_get_contents(WIS_SCRAPPER_SSDIR_PATH_TMP."/{$archive->Package->NameHash}_scan.json");
            self::$scanReport = json_decode($json);
            $isZipOpen = (self::$zipArchive->open(self::$zipPath, ZIPARCHIVE::CREATE) === TRUE);
            if (!$isZipOpen) {
                /* WIS_SCR_Log::Error("Cannot open zip file with PHP ZipArchive.", "Path location [".self::$zipPath."]"); */
            }
            /* ADD SQL */
            $sqlArkFilePath = $archive->Package->getSqlArkFilePath();
            $isSQLInZip = self::$zipArchive->addFile(self::$sqlPath, $sqlArkFilePath);
            self::$zipArchive->close();
            self::$zipArchive->open(self::$zipPath, ZipArchive::CREATE);
            /* ZIP DIRECTORIES */
            $info = '';
            foreach (self::$scanReport->ARC->Dirs as $dir) {
                if (is_readable($dir) && self::$zipArchive->addEmptyDir(ltrim(str_replace(self::$compressDir, 'WIS-Scrapper-Content/', $dir), '/'))) {
                    self::$countDirs++;
                    $lastDirSuccess = $dir;
                } else {
                    /* Don't warn when dirtory is the root path */
                    if (strcmp($dir, rtrim(self::$compressDir, '/')) != 0) {
                        $dir_path = strlen($dir) ? "[{$dir}]" : "[Read Error] - last successful read was: [{$lastDirSuccess}]";
                        $info .= "DIR: {$dir_path}\n";
                    }
                }
            }
            /* LOG Unreadable DIR info */
            if (strlen($info)) {}
            /* ZIP FILES: Network Flush
             *  This allows the process to not timeout on fcgi 
             *  setups that need a response every X seconds */
            $info = '';
            if (self::$networkFlush) {
                foreach (self::$scanReport->ARC->Files as $file) {
                    if (is_readable($file) && self::$zipArchive->addFile($file, ltrim(str_replace(self::$compressDir, '', $file), '/'))) {
                        self::$limitItems++;
                        self::$countFiles++;
                    } else {
                        $info .= "FILE: [{$file}]\n";
                    }
                    /* Trigger a flush to the web server after so many files have been loaded. */
                    if (self::$limitItems > WIS_SCRAPPER_ZIP_FLUSH_TRIGGER) {
                        $sumItems         = (self::$countDirs + self::$countFiles);
                        self::$zipArchive->close();
                        self::$zipArchive->open(self::$zipPath);
                        self::$limitItems = 0;
                        WIS_SCR_Util::fcgiFlush();
                    }
                }
            }
            /* Normal */
            else {
                foreach (self::$scanReport->ARC->Files as $file) {
                    if (is_readable($file) && self::$zipArchive->addFile($file, ltrim(str_replace(self::$compressDir, 'WIS-Scrapper-Content/', $file), '/'))) {
                        self::$countFiles++;
                    } else {
                        $info .= "FILE: [{$file}]\n";
                    }
                }
            }
            /* LOG Unreadable FILE info */
            if (strlen($info)) {
                unset($info);
            }
			/* -------------------------------- */
            /* LOG FINAL RESULTS */
            WIS_SCR_Util::fcgiFlush();
            $zipCloseResult = self::$zipArchive->close();
            $timerAllEnd = WIS_SCR_Util::getMicrotime();
            $timerAllSum = WIS_SCR_Util::elapsedTime($timerAllEnd, $timerAllStart);
            self::$zipFileSize = @filesize(self::$zipPath);
        } catch (Exception $e) {
        }
    }
}
?>