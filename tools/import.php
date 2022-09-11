#!/usr/bin/php
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
 * @author Ally Shaban <allyshaban5@gmail.com>
 * @copyright Copyright &copy; 2007, 2008-2013 IntraHealth International, Inc.
 * @version 4.6.0
 */
//error_reporting(E_ALL);
ini_set('display_errors',1);
ini_set("date.timezone", "Africa/Dar-es-Salaam");
//date_default_timezone_set('Africa/Dar-es-Salaam');
require_once("./import_base.php");
 
class PersonalData_Import extends Processor{
   
    public function __construct($file) {
        parent::__construct($file);
    }
   
    //map headers from the spreadsheet
    //what you do here is change the values on the right to match what you have on the spreadsheet. comment out lines that are not in the spreadsheet
    //the values of the left are used by the script to refer to the spreadsheet columns on the right of this array.
    //the order of the columns in the spreadsheet doesn't matter
   
    protected function getExpectedHeaders(){
        return array(
          'name' => 'NAME',
          'regno' => 'REGNO',
          'gender' => 'SEX',
          'location' => 'ADDRESS',
          'sponsor' => 'SPONSOR',
          'programme' => 'PROGRAMME',
          'institution' => 'INSTITUTION',
          'birth_date' => 'DATE OF BIRTH'
        );
      }
 
    protected static $required_cols_by_transaction = array(
        'NE'=>array('name','birth_date','gender','regno')
        );
 
   protected function _processRow() {
      $success = true;
      $this->create_person();
      return $success;
      }
     
    protected function create_person() {
/*
        if($this->person_added())
        return;
*/
        $personObj = $this->ff->createContainer('person');
        $names=str_replace(".","",$this->mapped_data['name']);
        $names=ucwords(strtolower($names));
        $names=explode(" ",$names);
        $fname=current($names);
        unset($names[0]);
        $surname=end($names);
        $names=array_values($names);
        unset($names[count($names)-1]);
        if(count($names)>0)
        $othernames=implode(" ",$names);
        $personObj->getField("firstname")->setValue($fname);
        $personObj->getField("surname")->setValue($surname);
        $personObj->getField("othername")->setValue($othernames);
      if($this->mapped_data['gender']=="F" or $this->mapped_data['gender']=="FEMALE")
      $gender="gender|F";
      else if($this->mapped_data['gender']=="M" or $this->mapped_data['gender']=="MALE")
      $gender="gender|M";
      $personObj->getField("gender")->setFromDB($gender);
      $dob=date("Y-m-d",strtotime(str_replace("/","-",$this->mapped_data['birth_date'])));
      $personObj->getField("date_of_birth")->setFromDB($dob);
      if($gender=="gender|M")
      $title="title|mr";
      else if($gender=="gender|F")
      $title="title|ms";
      $personObj->getField("title")->setFromDB($title);
        $this->person_id=$this->save($personObj);
        $this->person_id="person|".$this->person_id;
        $this->add_registration();
        return $this->person_id;
        }
       
    protected function add_registration() {
        $regObj = $this->ff->createContainer('registration');
        $regObj->getField("registration_number")->setValue($this->mapped_data['regno']);
        $regObj->getField("registration_status")->setFromDB("registration_status|ongoing");
        $training_prog=$this->add_magicdata("training_program",$this->mapped_data['programme']);
        $regObj->getField("training_program")->setFromDB($training_prog);
        $sponsor=$this->add_magicdata("sponsor",$this->mapped_data['sponsor']);
        $regObj->getField("sponsor")->setFromDB($sponsor);
        $training_inst=$this->add_magicdata("training_institution",$this->mapped_data['institution']);
        $regObj->getField("training_institution")->setFromDB($training_inst);
        $this->save($regObj);
    }
   
    protected function add_magicdata($form,$data,$field=false,$mapped_data=array()) {
        if(!$field)
        $field="name";
        $where=array(   "operator"=>"FIELD_LIMIT",
                            "field"=>$field,
                            "style"=>"equals",
                            "data"=>array("value"=>$data)
                         );
        $exist=I2CE_FormStorage::search($form,false,$where);
        if(count($exist)==0) {
            $formObj=$this->ff->createContainer($form);
            $formObj->getField($field)->setValue($data);
            if(count($mapped_data)>0) {
                $formObj->getField($mapped_data["form"])->setFromDB($mapped_data["data"]);
            }
            $data_id=$this->save($formObj);
            return $form."|".$data_id;
            }
        else {
            return $form."|".$exist[0];
            }
        } 
  /*     
        protected function person_added() {
		$contact=str_replace("-","",$this->mapped_data['phone']);
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"mobile_phone",
							"style"=>"equals",
							"data"=>array("value"=>$contact)
						);
*/
}
  
/*********************************************
*
*      Execute!
*
*********************************************/
 
//ini_set('memory_limit','3000MB');
  
if (count($arg_files) != 1) {
    usage("Please specify the name of a spreadsheet to process");
}
 
reset($arg_files);
$file = current($arg_files);
if($file[0] == '/') {
    $file = realpath($file);
} else {
    $file = realpath($dir. '/' . $file);
}
if (!is_readable($file)) {
    usage("Please specify the name of a spreadsheet to import: " . $file . " is not readable");
}
 
I2CE::raiseMessage("Loading from $file");
 
 
$processor = new PersonalData_Import($file);
$processor->run();
 
echo "Processing Statistics:\n";
print_r( $processor->getStats());
 
 
 
 
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
