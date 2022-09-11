<?php
/**
* © Copyright 2014 IntraHealth International, Inc.
* 
* This File is part of I2CE 
* 
* I2CE is free software; you can redistribute it and/or modify 
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
* @package iHRIS
* @author Ally Shaban <allyshaban5@gmail.com>
* @version v3.2.2
* @since v3.2.2
* @filesource 
*/ 
/** 
* Class iHRIS_Module_DropSemester
* 
* @access public
*/


class IHS_Module_TransferStudents extends I2CE_Module {
    
    public static function getHooks() {
        return array(
'validate_form_transfer'=>'validate_form_transfer'
                     );
    }

	 public function validate_form_transfer($form) {
	 	$ff = I2CE_FormFactory::instance();
	 	if(($persObj=$ff->createContainer($form->getParent()) instanceof iHRIS_Person)) {
		 	$student_registration=STS_PageFormPerson::load_current_registration($form->getParent());
		 	$regObj=$ff->createContainer($student_registration["id"]);
			$current_program=implode("|",$form->source_program);
			$new_program=implode("|",$form->destination_program);
			$current_institution=implode("|",$form->source_institution);
			$new_institution=implode("|",$form->destination_institution);
			$new_semester=implode("|",$form->destination_semester);
			$current_semester=implode("|",$form->source_semester);
			if($current_institution==$new_institution and $current_program==$new_program) {
				$form->setInvalidMessage( "destination_institution" ,"Invalid Transfer,Institutions And Programs Are The Same");
				}
			if($current_semester!=$new_semester and $current_program==$new_program)
				$form->setInvalidMessage( "destination_semester" ,"New Semester Must Be ".$regObj->getField("semester")->getDisplayValue()." As This Student Has Just Transfered Institutions With The Same Program");
		}
		/**
		**Check to ensure that Level and semester are consistency
		**/
		$semester=$form->destination_semester;
		$level=$form->destination_level;
		if($semester[1]!=(2*$level[1]-1) and $semester[1]!=(2*$level[1])) {
			$form->setInvalidMessage("destination_level","Level And Semester Are Not Consistency");
			$form->setInvalidMessage("destination_semester","Level And Semester Are Not Consistency");
			}
			
		/**
		**Check to ensure that Academic Year Matches Registration Date
	   **/
	 	$academic_year=implode("|",$form->academic_year);
	 	if($academic_year=="|")
	 	return;
	 	$acadYrObj=$ff->createContainer($academic_year);
	 	$acadYrObj->populate();
	 	$academic_year=$acadYrObj->getField("name")->getDBValue();
		
		$academic_year=explode("/",$academic_year);
		$start_dateObj=I2CE_Date::getDate(01,07,$academic_year[0]);
		$end_dateObj=I2CE_Date::getDate(30,05,$academic_year[1]);
		$start_date=$start_dateObj->displayDate();
		$end_date=$end_dateObj->displayDate();
		if($start_dateObj->compare($form->transfer_date)==-1) {
			$form->setInvalidMessage( "transfer_date" ,"Transfer Date And Academic Year Do Not match");
			}
		if($end_dateObj->compare($form->transfer_date)!=-1) {
			$form->setInvalidMessage( "transfer_date" ,"Transfer Date And Academic Year Do Not match");
			}
	 	}
	 
}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
