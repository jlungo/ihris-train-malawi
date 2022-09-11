<?php
/*
 * © Copyright 2012 IntraHealth International, Inc.
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
 * @package iHRIS
 * @subpackage Train
 * @access public
 * @author Ally Shaban <allyshaban5@gmail.com>
 * @copyright Copyright &copy; 2014 IntraHealth International, Inc. 
 * @since v4.1.4
 * @version v4.1.4
 */

/**
 * The page class for editing participants for a training
 * @package iHRIS
 * @subpackage Manage
 * @access public
 */
class IHS_PageFormSelectcourse extends I2CE_PageForm  {

    protected function action() {
	//check to ensure that the current academic year is available
	iHRIS_AcademicYear::ensureAcademicYear();
	$this->showCourses();
}

	protected function showCourses() {
        if (! ($listNode = $this->template->getElementByID("existing_course_list")) instanceof DOMNode) {
	return ;
        }

	if($this->getUser()->role=="registrar" || $this->getUser()->role=="lecturer" || 
		$this->getUser()->role=="hod" || $this->getUser()->role=="principal" || $this->getUser()->role=="deputy_principal") {
	
	######getting id of the currently logged in lecturer######		
	$username=$this->getUser()->username;
	$where=array(
						"operator"=>"FIELD_LIMIT",
						"field"=>"identification_number",
						"style"=>"equals",
						"data"=>array("value"=>$username)
					 );
	$lecturer=I2CE_FormStorage::search("lecturer",false,$where);
	foreach($lecturer as $id)
	$lecturer_id="lecturer|".$id;
	
	######Getting the current academic year######
	$academic_year=iHRIS_AcademicYear::currentAcademicYear();	
	$where=array(
						"operator"=>"FIELD_LIMIT",
						"field"=>"name",
						"style"=>"equals",
						"data"=>array("value"=>$academic_year)
					 );
	$academic_year_id=I2CE_FormStorage::search("academic_year",false,$where);
	$academic_year_id="academic_year|".$academic_year_id[0];
	######Getting a list of courses assigned to this lecturer######
	$where_assign_course=array("operator"=>"AND",
											"operand"=>array(
																	0=>array(
																				"operator"=>"FIELD_LIMIT",
																				"field"=>"lecturer",
																				"style"=>"equals",
																				"data"=>array("value"=>$lecturer_id)),
																	1=>array(
																				"operator"=>"FIELD_LIMIT",
																				"field"=>"academic_year",
																				"style"=>"equals",
																				"data"=>array("value"=>$academic_year_id)
																				)
																 )
										  );
	$assigned_courses=I2CE_FormStorage::listFields("assign_course_trainer",array("training"),false,$where_assign_course);
	//display all department courses for the head of department
	if($this->getUser()->role=="hod") {
		$lectObj=$this->factory->createContainer($lecturer_id);
		$lectObj->populate();
		$department=$lectObj->getField("department")->getDBValue();
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"department",
							"style"=>"like",
							"data"=>array("value"=>"%".$department."%")
						 );
		$progrs=I2CE_FormStorage::listFields("training_program",array("department"),false,$where);
		foreach ($progrs as $id=>$prgr) {
			$deps=explode(",",$prgr["department"]);
			if(in_array($department,$deps))
			$training_program[$id]="training_program|".$id;
			}
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"training_program",
							"style"=>"in",
							"data"=>array("value"=>$training_program)
						 );

		$training_courses=I2CE_FormStorage::listFields("training",array("id"),false,$where);
		foreach ($training_courses as $id=>$course) {
			$courses[]="training|".$id;
			}
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"in",
																"data"=>array("value"=>$courses)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"academic_year",
																"style"=>"equals",
																"data"=>array("value"=>$academic_year_id))
												 ));
												 
		$hod_courses=I2CE_FormStorage::listFields("assign_course_trainer",array("training"),false,$where);		
		}
	}
	else {
		$this->userMessage("Login as a training provider to add results");
		$this->redirect("manage?action=provider");
		return false;
		}
		$courses=array();
		
	 if(count($assigned_courses)==0 and count($hod_courses)==0) {
	 	$this->userMessage("No courses assigned to you,contact the Registrar for further assistance");
		$this->redirect("manage?action=provider");
		return false;
	 	}
	 	
	 foreach ($assigned_courses as $id=>$course) {
	 	$courses[$id]=$course["training"];
	 	}

	 	if(count($hod_courses)>0) {
	 		foreach($hod_courses as $id=>$course) {
				if(in_array($course["training"],$courses))
				continue;
				else
				$courses[$id]=$course["training"];
	 			}
	 		}

	//arranging courses in ascending order
	foreach($courses as $id=>$course) {
		$courseObj=$this->factory->createContainer($course);
		$courseObj->populate();
		$course_codes[$id]=$courseObj->code;
		}
	asort($course_codes);
	foreach($course_codes as $id=>$code) {
		$courses_id[$id]=$courses[$id];
		}

	 ######Displaying courses assigned to this lecturer######
    foreach ($courses_id as $id=>$course) {
    		$course_id=explode("|",$course);
    		$course_id=$course_id[1];
    		$where=array(
    							"operator"=>"FIELD_LIMIT",
    							"field"=>"id",
    							"style"=>"equals",
    							"data"=>array("value"=>$course_id)
    				  		 );
  	 		$training_courses=I2CE_FormStorage::ListFields("training",array("name","code"),false,$where);
    		foreach($training_courses as $id=>$training_course) {	
	 			$course_name = $training_course["name"];
    			$course_code = $training_course["code"];
    			$course=$course_code."-".$course_name;
    			$id="training|".$id;
        		$aNode =$this->template->createElement("a",array(href=>"add_results?id=" . $id),$course);
     			$liNode =$this->template->createElement("li");
     			$this->template->appendNode($aNode,$liNode);
     			$this->template->appendNode($liNode,$listNode); 
	 		}
    }

}
	
}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: