<?php
/*
 * © Copyright 2007, 2008 IntraHealth International, Inc.
 * 
 * This File is part of iHRIS
 * 
 * iHRIS is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * The page wrangler
 * 
 * This page loads the main HTML template for the home page of the site.
 * @package iHRIS
 * @subpackage DemoManage
 * @access public
 * @author Carl Leitner <litlfred@ibiblio.org>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @since Demo-v2.a
 * @version Demo-v2.a
 */



/**
 * setting system wide variables just like in index.php
 */
 
$dir = getcwd();
chdir("../pages");
$i2ce_site_user_access_init = null;
$i2ce_site_user_database = null;
require_once( getcwd() . DIRECTORY_SEPARATOR . 'config.values.php');

$local_config = getcwd() . DIRECTORY_SEPARATOR .'local' . DIRECTORY_SEPARATOR . 'config.values.php';
if (file_exists($local_config)) {
    require_once($local_config);
}

if(!isset($i2ce_site_i2ce_path) || !is_dir($i2ce_site_i2ce_path)) {
    echo "Please set the \$i2ce_site_i2ce_path in $local_config";
    exit(55);
}

require_once ($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'I2CE_config.inc.php');

I2CE::raiseMessage("Connecting to DB");
putenv('nocheck=1');
if (isset($i2ce_site_dsn)) {
    @I2CE::initializeDSN($i2ce_site_dsn,   $i2ce_site_user_access_init,    $i2ce_site_module_config);         
} else if (isset($i2ce_site_database_user)) {    
    I2CE::initialize($i2ce_site_database_user,
                     $i2ce_site_database_password,
                     $i2ce_site_database,
                     $i2ce_site_user_database,
                     $i2ce_site_module_config         
        );
} else {
    die("Do not know how to configure system\n");
}

I2CE::raiseMessage("Connected to DB");

require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php');



/*************************************************************************
 *
 *  Classes to handle reading headers and rows from data files
 *
 ************************************************************************/


abstract class DataFile {
    
    /**
     * @var protected string $file
     */
    protected $file;    
    
    abstract public function getDataRow();
    abstract public function hasDataRow();
    abstract public function getHeaders();
    public function __construct($file) {
        $this->file = $file;
    }
    
    /**
     * get the file name for the file we are going to deal with
     * @returns string
     */
    public function getFileName() {
        return $this->file;
    }
    
    /**
     * closes a file that was open
     */
    public function close() {

    }

}

class CSVDataFile extends DataFile {
    protected $fp;
    protected $in_file_sep = false;
    protected $file_size = false;
    public function __construct($file) {
        parent::__construct($file);
        $this->filesize = filesize($file);
        if ( ($this->fp = fopen($this->file,"r")) === false) {
            usage("Please specify the name of a spreadsheet to import: " . $file . " is not openable");
        }
    }
    
    /**
     * checks to confirm if the file has rows of data
     * @returns string
     */
    public function hasDataRow() {
        $currpos =  ftell($this->fp);
        if ($currpos === false) {
            return false;
        } else {
            return ($currpos < $this->filesize);
        }
    }
    
    /**
     * reads all the column headers from the CSV file
     * @returns array
     */
    public function getHeaders() {
        $this->in_file_sep = false;
        fseek($this->fp,0);
        foreach (array("\t",",",";") as $sep) {
            $headers = fgetcsv($this->fp, 4000, $sep);
            if ( $headers === FALSE|| count($headers) < 2 || !simple_prompt("Do these look like the correct headers?\n". print_r($headers,true))) {
                fseek($this->fp,0);
                continue;
            }
            $this->in_file_sep = $sep;
            break;
        }
        if (!$this->in_file_sep) {
            die("Could not get headers\n");
        }
        foreach ($headers as &$header) {
            $header = trim($header);
        }
        unset($header);
        return $headers;
    }

    public function getDataRow() {
        return $data = fgetcsv($this->fp, 4000, $this->in_file_sep);
    }
    
    /**
     * closes the open CSV file
     */
    public function close() {
        fclose($this->fp);
    }
}


class ExcelDataFile extends DataFile {    

    protected $rowIterator;

    public function __construct($file) {
        parent::__construct($file);
        include_once('PHPExcel/PHPExcel.php'); 
        if (!class_exists('PHPExcel',false)) {
            usage("You must have PHPExcel installed to load excel spreadsheets");
        }
        $readerType = PHPExcel_IOFactory::identify($this->file);
        $reader = PHPExcel_IOFactory::createReader($readerType);
        $reader->setReadDataOnly(false);
        $excel = $reader->load($this->file);        
        $worksheet = $excel->getActiveSheet();
        $this->rowIterator = $worksheet->getRowIterator();
    }

    
    /**
     * confirms if the excel file we are reading has rows with data
     * @returns boolean
     */
    public function hasDataRow() {
        return $this->rowIterator->valid();
    }

    /**
     * reads the file to get the headers
     * @returns array
     */
    public function getHeaders() {
        $this->rowIterator->rewind();
        $row = $this->rowIterator->current();
        if (!$this->rowIterator->valid()) {
            I2CE::raiseMessage("Could not find header row");
            return false;
        }
        return $this->_readRow($row);
    }
    
    /**
     * reads one data row at a time
     * @returns array
     */
    public function getDataRow() {
        $this->rowIterator->next();
        if (!$this->rowIterator->valid()) {
            return false;
        }
        return $this->_readRow($this->rowIterator->current());
    }
    
    /**
     * read the entire row and parse for data
     * @param string $row. If not an excel worksheet row, issue a message and return false
     * @returns array
     */
    protected function _readRow($row) {
        if (!$row instanceof PHPExcel_Worksheet_Row) {
            I2CE::raiseMessage("Invalid row object" . get_class($row));
            return false;
        }
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $data = array();
        foreach ($cellIterator as $cell) {
            $data[] =  $cell->getValue();
        }
        return $data;
    }


}



/*********************************************
*
*      Process Classes
*
*********************************************/

function convert($size)
{
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}



abstract  class Processor {
    abstract protected function _processRow();
    abstract protected function getExpectedHeaders();
    protected $headers;
    protected $mapped_data;
    protected $data;
    protected $user;
    protected $testmode = true;
    protected $db;
    protected $row = 1;
    protected $ff; 
    protected $processRows =null;
    protected $dataFile;
    protected $file;

    public function __construct($file) {
        $this->file = $file;
        $file_ext = strtolower(substr($file, strrpos($file, '.') + 1));
        if ($file_ext == 'csv') {
            //although CSV can be processed by PHPExcel, we keep this separate in case PHPExcel cannot be installed, we can still export the file as a CSV and process it
            $this->dataFile = new CSVDataFile($file);
        } else {
            $this->dataFile = new ExcelDataFile($file);
        }
        $this->user = new I2CE_User();
        $this->db= MDB2::singleton();
        $this->ff = I2CE_FormFactory::instance();
        $this->mapHeaders();
        $this->initBadFile();
        $this->testmode = simple_prompt("Is this a test run?");
        $this->createLogger();
    }
    
    /**
     * read the current row
     * @return $array
     */
    public function getCurrentRow() {
        return $this->row;
    }
    
    /**
     * check if the file has data 
     * @returns bool
     */
    public function hasDataRow() {
        return $this->dataFile->hasDataRow();
    }

    
    protected $success = 0;

    protected $blank_lines = 0;
    protected $skip = 2;
    /**
     * process the import giving the user choices to run in test mode or in production mode
     * every unsuccessful processing for a row is recorded into a log file
     */
    public function run() {
        if (simple_prompt("Skip rows?")) {
            $this->skip = ask("Skip to which row?  Start row =2 (b/c row 1 is header)");
        }
        $this->success = 0;
        while ( $this->hasDataRow()) {
            if ($this->blank_lines > 10) {
                if (simple_prompt("Encounted 10 consective blank rows ending at row " . $this->row . ". Should we stop processing?")) {
                    break;
                } else {
                    $this->blank_lines = 0;
                }
            }
            if ($this->processRow()) {
                $this->success++;
                if ($this->testmode) {
                    //$this->addBadRecord("SUCCESS ON TEST");
                }
            }
        }
    }
    
    /**
     * collect statistics for the import process:total attempts and successes
     * @returns array
     */
    public function getStats() {
        return array('success'=>$this->success,'attempts'=>($this->row -1)); //this may be off by one.
        $row = $processor->getCurrentRow();
    }
    
    /**
     * processes each row encountered from the file
     * @returns bool
     */
    public function processRow() {
        if (!$this->dataFile->hasDataRow()) {
            return false;
        }
        //starts off with $row= 1, but we do a row++ below to get to row 2
        $this->data = $this->dataFile->getDataRow();
        if ($this->row < ($this->skip - 1)) { //if skip =2, the default, then 1 < (2-1)  is false so we don't skup
            $this->row++;
            return true;
        }
        if (!is_array($this->data)) {
            $this->blank_lines++;
            return false;
        }
        $is_blank = true;
        foreach ($this->data as $cell) {
            if (is_string($cell) && strlen(trim($cell)) != 0) {
                $is_blank = false;
                $this->blank_lines = 0;
                break;
            }
        }
        if ($is_blank) {
            $this->blank_lines++;
        } 
        $this->row++;
        if ( ! ($this->mapped_data = $this->mapData())) {
            return false;
        }
        if (($hash_val = $this->alreadyProcessed()) === true) {
            return true;
        }
        if (!prompt("Process row $this->row ?",$this->processRows,print_r(array('mapped'=>$this->mapped_data,'raw'=>$this->data),true))) {
            return false;
        }
        if ( $this->_processRow()) {
            $this->markProcessed($hash_val);    
            return true;
        } else {
            return false;
        }
    }
    
    
    /**
     * sets up the mode to run the script: test or production
     * @param string $testmode defaults yes
     */
    public function setTestMode($testmode) {
        $this->testmode = $testmode;
    }

    protected $continue_save = null;

    /**
     * save the data into the database
     * @param string $obj, the form object, 
     * @param bool $cleanup defaults
     * @returns string
     */
    protected function save($obj,$cleanup = true) {
        if (!$obj instanceof I2CE_Form) {
            return false;
        }
	echo "Row {$this->row}, Form " . $obj->getName() . " Used Memory  = " . convert(memory_get_usage(true)) . "\n"; 
        if ($this->testmode) {            
            echo "Saving " . $obj->getName() . "\n";
            foreach ($obj as $fieldName=>$fieldObj) {
                echo "\t$fieldName=>" . $fieldObj->getDBValue() . "\n";
            }
            if ($cleanup) {
                $obj->cleanup();
            }
            return "0";
        } else {
            $obj->save($this->user);    
            $id = $obj->getID();
            if ($cleanup) {
                $obj->cleanup();
            }
            prompt("Saved id $id. Continue?",$this->continue_save);
            return $id;
        }
    }



    protected $header_map;
    


    function getHeaderMap(&$headers,$expected_headers) {
        foreach ($headers as &$header) {
            $header = strtolower(trim($header));
        }
        unset($header);

        $header_map = array();
        foreach ($expected_headers as $expected_header_ref => $expected_header) {
            $expected_header = strtolower(trim($expected_header));
            if (($header_col = array_search($expected_header,$headers)) === false) {
                I2CE::raiseError("Could not find $expected_header in the following headers:\n\t" . implode(" ", $headers). "\nFull list of found headers is:\n\t" . implode(" ", $headers));
                die();
            }
            $header_map[$expected_header_ref] = $header_col;
        }
        return $header_map;
    }


    /**
     * map expected header references to header columns in the data file 
     */
    protected function mapHeaders() {
        $this->headers = $this->dataFile->getHeaders();
        $expected_headers = $this->getExpectedHeaders();
        $this->header_map = $this->getHeaderMap($this->headers,$expected_headers);
    }



    /**
     * Utility function to remap data in an associative array to an associative array based on a map.
     * @param array $data the source data
     * @param array $header_map, the mapping array of data.  Keys are keys for the source data.  Values will be the keys for the mapped data
     * @param boolean $normalize.  Defaults to false.  If true we trim and upcase the data that is being remapped
     * @returns array, data mapped 
     */
    function mapByHeaderData($data,$header_map,$normalize = false) {
        $mapped_data = array();
        foreach ($header_map as $header_ref=>$header_col) {
            if (!array_key_exists($header_col,$data)) {
                $mapped_data[$header_ref] = false;
            } else if ($normalize) {
                $mapped_data[$header_ref] = strtoupper(trim($data[$header_col]));
            } else {
                $mapped_data[$header_ref] = $data[$header_col];
            }
        }
    
        return $mapped_data;
    }


    /**
     * map the data found from a column in the data file to a specific 
     * header reference for saving into the database
     * @returns array, data mapped into header references
     */
    protected function mapData() {
        return $this->mapByHeaderData($this->data,$this->header_map);
    }

    

    protected $bad_headers;
    protected $bad_fp;        
    protected $bad_file_name;
    
    /**
     * initialize a file into which we record all the bad data/unsuccessful row imports
     * 
     */
    protected function initBadFile() {
        $info = pathinfo($this->file);
        $bad_fp =false;
        if ($this->testmode) {
            $append = 'test_bad_';
        } else{
            $append = 'bad_';
        }
        $this->bad_file_name = dirname($this->file) . DIRECTORY_SEPARATOR . basename($this->file,'.'.$info['extension']) . '.' . $append .date('d-m-Y_G:i') .'.csv';
        I2CE::raiseMessage("Will put any bad records in $this->bad_file_name");
        $this->bad_headers = $this->headers;
        $this->bad_headers[] = "Row In " . basename($this->file);
        $this->bad_headers[] = "Reasons for failure";
    }



    /**
     * add a bad record to the file holding all unsuccessful imports
     * @param string $reason, the reason for the failure of import of this record
     */
    function addBadRecord($reason) {
        if (!is_resource($this->bad_fp)) {
            $this->bad_fp = fopen($this->bad_file_name,"w");
            if (!is_resource($this->bad_fp)) {
                I2CE::raiseMessage("Could not open $this->bad_file_name for writing.", E_USER_ERROR);
                die();
            }        
            fputcsv($this->bad_fp, $this->bad_headers);
        }
        I2CE::raiseMessage("Skipping processing of row $this->row: $reason");
        $raw_data = $this->data;
        $raw_data[] = $this->row;
        $raw_data[] = $reason;
        fputcsv($this->bad_fp, $raw_data);
    }
    protected $log_table;
    
    /**
     * creating a logger table in the database to track every import process timing
     * 
     */
    protected function createLogger() {
        $this->log_table  = 'import_logger_' . get_class($this);
        $sql = "CREATE TABLE IF NOT EXISTS `" . $this->log_table . "` (`hash` BINARY(16) NOT NULL, UNIQUE KEY `hash` (`hash`))";
        if (I2CE::pearError($this->db->exec($sql),"Cannot create logging table", E_USER_ERROR)) {
            die();
        }
    }

    /**
     * checks to see if a row from the data file has been processed
     *  @returns string $hash_val. value of the hash from logger table
     */
    protected function alreadyProcessed() {
        $hash_data = '';
        $expected_headers = $this->getExpectedHeaders();
        foreach (array_keys($expected_headers) as $header_ref) {
            $hash_data .= $this->mapped_data[$header_ref];
        }
        if (strlen($hash_data) == 0) {
            I2CE::raiseError("No data");
            return true;
        }
        $hash_val = md5($hash_data,true);
        if (!is_string($hash_val) || strlen($hash_val) == 0) {
            die("bad has val:\n" . print_r($this->mapped_data,true) . "\n$hash_data\n" . self::raw2hex($hash_val) );
        }
        $esc_hash_val = mysql_real_escape_string($hash_val);
        $hash_check = 'SELECT hash FROM `'. $this->log_table . '` WHERE hash = \''. $esc_hash_val  .'\'';
        if (I2CE::pearError(($res = $this->db->query($hash_check)),"Cannot check value in logging table", E_USER_ERROR)) {
            die();
        }
        if ( $res->fetchRow()) {
            $this->addBadRecord('Row has already been processed');
            return true;
        }
        return $hash_val;
    }

    
    public static function raw2hex($s) {
        //thanks to: functionifelse at gmail dot com     09-Dec-2004 10:34  on http://theserverpages.com/php/manual/en/function.md5.php
        $op = '';
        for($i = 0; $i < strlen($s); $i++){
            $op .= str_pad(dechex(ord($s[$i])),2,"0",STR_PAD_LEFT);
        }
        return $op;
    }

    /**
     * marks every record that is processed inserting its $hash_val to the logger table
     * @param string $hash_val. the hash value generated from the import process
     */
    protected function markProcessed($hash_val) {
        if ($this->testmode) {
            return;
        }
        $esc_hash_val = mysql_real_escape_string($hash_val);
        $hash_insert = 'INSERT into   `' . $this->log_table . '` VALUES (\''.  $esc_hash_val .'\')';
        if (I2CE::pearError($this->db->exec($hash_insert),"Cannot add value " . self::raw2hex($hash_val). "to logging table from\n" . print_r($this->mapped_data,true) . "\n" . strlen($hash_val), E_USER_ERROR)) {
            die();
        }   
    }    



    /**
     * gets a date value from records read from the datafile
     * @param date $date, a date value as read from the data file
     * @returns date. formatted
     */
    protected function getDate($date,$date_format = 'm/d/Y' , $excel_date_format = 'DD/MM/YYYY') {
        //first check the date e.g 16/05/2011
        $matches = array();
        if (is_numeric($date) && class_exists('PHPExcel',false)) {
            //in case we are reading it from excel which returns 40777 instead of 22/08/2011 for example
            $date = PHPExcel_Style_NumberFormat::toFormattedString($date, $excel_date_format);
        }
        if (($datetime = DateTime::createFromFormat($date_format,$date)) === false) {
            $this->addBadRecord("Bad date format [$date] for $date_format");
            return false;
        }
        $date = I2CE_Date::now(I2CE_Date::DATE, $datetime->getTimestamp(),true);
        if (!$date) {
            $this->addBadRecord("Invalid date ($date)");
            return false;
        }
        return $date;
    }
    
    /**
     * gets a year value from records read from the datafile
     * @param date $date, a date value as read from the data file
     * @returns date. formatted
     */
    protected function getYear($date,$year_format = 'Y' , $excel_date_format = 'YYYY') {
        //first check the date e.g 16/05/2011
        $matches = array();
        if (is_numeric($date) && class_exists('PHPExcel',false)) {
            //in case we are reading it from excel which returns 40777 instead of 22/08/2011 for example
            $date = PHPExcel_Style_NumberFormat::toFormattedString($date, $excel_date_format);
        }
        if (($datetime = DateTime::createFromFormat($year_format,$date)) === false) {
            $this->addBadRecord("Bad date format [$date] for $date_format");
            return false;
        }
        $date = I2CE_Date::now(I2CE_Date::DATE, $datetime->getTimestamp(),true);
        if (!$date) {
            $this->addBadRecord("Invalid date ($date)");
            return false;
        }
        return $date;
    }

}






# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
