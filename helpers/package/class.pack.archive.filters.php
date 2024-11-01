<?php
/**
 * The base class for all filter types Directories/Files/Extentions
 *
 * @package Scrapper
 * @subpackage classes/package
 * @since 1.1.0
 *
 */
class WIS_SCR_Archive_Filter_Scope_Base
{
    /* All internal storage items that scrapper decides to filter */
    public $Core     = array();
    /* Items when creating a package or template that a user decides to filter */
    public $Instance = array();
}
/**
 * The filter types that belong to directories
 *
 * @package Scrapper
 * @subpackage classes/package
 * @since 1.1.0
 *
 */
class WIS_SCR_Archive_Filter_Scope_Directory extends WIS_SCR_Archive_Filter_Scope_Base
{
    /* Items that are not readable */
    public $Warning    = array();
    /* Items that are not readable */
    public $Unreadable = array();
}
/**
 * The filter types that belong to files
 *
 * @package Scrapper
 * @subpackage classes/package
 * @since 1.1.0
 *
 */
class WIS_SCR_Archive_Filter_Scope_File extends WIS_SCR_Archive_Filter_Scope_Directory
{
    /* Items that are too large */
    public $Size = array();

}
/**
 * The filter information object which store all information about the filtered
 * data that is gathered to the execution of a scan process
 *
 * @package Scrapper
 * @subpackage classes/package
 * @since 1.1.0
 *
 */
class WIS_SCR_Archive_Filter_Info
{
    /* Contains all folder filter info */
    public $Dirs       = array();
    /* Contains all file filter info */
    public $Files      = array();
    /* Contains all extensions filter info */
    public $Exts       = array();
    public $UDirCount  = 0;
    public $UFileCount = 0;
    public $UExtCount  = 0;
	public $TreeSize;
	public $TreeWarning;
    /**
     *  Init this object
     */
    public function __construct()
    {
        $this->Dirs  = new WIS_SCR_Archive_Filter_Scope_Directory();
        $this->Files = new WIS_SCR_Archive_Filter_Scope_File();
        $this->Exts  = new WIS_SCR_Archive_Filter_Scope_Base();
		$this->TreeSize = array();
		$this->TreeWarning = array();
    }
}