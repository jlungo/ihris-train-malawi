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


class IHS_Module_RejoiningStudents extends I2CE_Module {
    
    public static function getHooks() {
        return array(
'validate_form_rejoin'=>'validate_form_rejoin'
                     );
    }

	 public function validate_form_rejoin($form) {
	 	$ff = I2CE_FormFactory::instance();
	 	
		/**
		**Check to ensure that Level and semester are consistency
		**/
		$semester=$form->rejoin_semester;
		$level=$form->rejoin_level;
		if($semester[1]!=(2*$level[1]-1) and $semester[1]!=(2*$level[1])) {
			$form->setInvalidMessage("rejoin_level","Level And Semester Are Not Consistency");
			$form->setInvalidMessage("rejoin_semester","Level And Semester Are Not Consistency");
			}
			
		/**
		**Check to ensure that Academic Year Matches Registration Date
	   **/
	 	$academic_year=implode("|",$form->academic_year_rejoin);
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
		if($start_dateObj->compare($form->rejoin_date)==-1) {
			$form->setInvalidMessage( "rejoin_date" ,"Transfer Date And Academic Year Do Not match");
			}
		if($end_dateObj->compare($form->rejoin_date)!=-1) {
			$form->setInvalidMessage( "rejoin_date" ,"Transfer Date And Academic Year Do Not match");
			}
	 	}
}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
