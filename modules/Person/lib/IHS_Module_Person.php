<?php
/**
* © Copyright 2008 IntraHealth International, Inc.
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
*/
/**
*  IHS_Module_Person
* @package I2CE
* @subpackage Core
* @author Ally Shaban <allyshaban5@gmail.com>
* @copyright Copyright &copy; 2008 IntraHealth International, Inc. 
* This file is part of I2CE. I2CE is free software; you can redistribute it and/or modify it under 
* the terms of the GNU General Public License as published by the Free Software Foundation; either 
* version 3 of the License, or (at your option) any later version. I2CE is distributed in the hope 
* that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
* or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have 
* received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
* @version 2.1
* @access public
*/

class IHS_Module_Person extends I2CE_Module{
    /**
     * Return the array of hooks available in this module.
     * @return array
     */
    public static function getHooks() {
    return array(
	'validate_form_registration'=>'validate_form_registration',
	'validate_form_person'=>'validate_form_person',
       );
    }

    /**
     * Perform any extra validation for the license.
     * @param I2CE_Form $form
     */
	
	public function validate_form_registration($form) {
		/**
	**Check to ensure that Level and semester are consistency
	**/
		if ($form->registration_date->month()>=1 and $form->registration_date->month()<=5) {
			$form->setInvalidMessage("registration_date","New Students Can Be Registered Between June And December Only.");
			}

		$semester=$form->semester;
		$level=$form->academic_level;
		if($semester[1]!=(2*$level[1]-1) and $semester[1]!=(2*$level[1])) {
			$form->setInvalidMessage("academic_level","Level And Semester Are Not Consistency.");
			$form->setInvalidMessage("semester","Level And Semester Are Not Consistency.");
			}
		  /**
		**Check to ensure that Identification Type Omang Is Used For BW Citizens Only
		**/
		/*if($form->identification_type[1]=="national_id" and $form->nationality[1]!="BW") {
			$form->setInvalidMessage("identification_type","Only Botswana Citizen Is Having Omang Number");
			}*/
	
	  /**
		**Check to ensure that RegNo Number Is Correct
	  **/
	 	if($form->identification_type[1]=="national_id" and $form->identification_number!="") {
	  	   if(strlen($form->identification_number) < 2 or strlen($form->identification_number) > 30)
		      $form->setInvalidMessage('identification_number','Invalid RegNo Number.' );
		/*
			      if($form->gender[1]=="F" and substr($form->identification_number,4,1)!=2)
			      $form->setInvalidMessage('identification_number','Invalid RegNo Number,Gender Differs The RegNo Number.' );
			      if($form->gender[1]=="M" and substr($form->identification_number,4,1)!=1)
			      $form->setInvalidMessage('identification_number','Invalid RegNo Number,Gender Differs The RegNo Number.' );
		*/
	      }
	   
	   /**
		**Check to ensure that semester joined is before the the current semester
	  **/
	   $joined_sem=$form->joined_semester[1];
	   $curr_semester=$form->semester[1];
	   if($curr_semester < $joined_sem) {
	   	$form->setInvalidMessage('semester','Current semester should be after the joined semester.');
	   	}
		/**
		**Check to ensure that Academic Year Matches Registration Date
	   **/
	  	$ff = I2CE_FormFactory::instance();
	 	$academic_year=implode("|",$form->academic_year);
	 	if($academic_year=="|")
	 	return;
	 	$acadYrObj=$ff->createContainer($academic_year);
	 	$acadYrObj->populate();
	 	$academic_year=$acadYrObj->getField("name")->getDBValue();
		
		$academic_year=explode("/",$academic_year);
		$start_dateObj=I2CE_Date::getDate(01,07,$academic_year[0]);
		$end_dateObj=I2CE_Date::getDate(30,06,$academic_year[1]);
		$start_date=$start_dateObj->displayDate();
		$end_date=$end_dateObj->displayDate();
		if($start_dateObj->compare($form->registration_date)==-1) {
			$form->setInvalidMessage( "registration_date" ,"Registration Date And Academic Year Do Not match.");
			}
		if($end_dateObj->compare($form->registration_date)!=-1) {
			$form->setInvalidMessage( "registration_date" ,"Registration Date And Academic Year Do Not match.");
			}
	  /**
	**Check to ensure that Registration And Birth Date Are Consistence 
	**/
	
	  if ( I2CE_Validate::checkDate( $form->registration_date ) && I2CE_Validate::checkDate( $form->date_of_birth ) ) {  	
	      $compare = $form->date_of_birth->compare( $form->registration_date );
	      if ( $compare  == -1 or $compare  == 0) {
	          $form->setInvalidMessage( "registration_date" ,'Registration Date Should Be After Date Of Birth.');
	      	}
	  		}
	  }
	  
	public function validate_form_person ($form) {
		/**
	   ** Ensure title mathes Gender
	   **/
	   if(count($form->title)>0) {
			if(($form->title[1]=="mr" and $form->gender[1]=="F") or ($form->title[1]=="ms" and $form->gender[1]=="M"))
				$form->setInvalidMessage( "title" ,'Gender And Title Mismatch.');
	   	}
	}
}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
