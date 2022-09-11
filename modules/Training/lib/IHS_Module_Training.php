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
*  iHRIS_Module_Trainng_Course
* @package I2CE
* @subpackage Core
* @author Ally Shaban <allyshaban5@yahoo.com>
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

class IHS_Module_Training extends I2CE_Module{
    /**
     * Return the array of hooks available in this module.
     * @return array
     */
    public static function getHooks() {
        return array(
'validate_form_training'=>'validate_form_training',
                );
    }
    
    public static function getMethods() {
        return array(
            'iHRIS_PageView->action_training' => 'action_training'
            );
    }


    public function action_training($obj) {
        if (!$obj instanceof iHRIS_PageView) {
            return;
        }
        return $obj->addChildForms('training', 'siteContent');
    }    


    /**
     * Perform any extra validation for the license.
     * @param I2CE_Form $form
     */

	public function validate_form_training($form) {
		$factory = I2CE_FormFactory::instance();
		//make sure that general education courses have no program while other courses must have programs
		$course_type=implode("|",$form->course_type);
		if($course_type=="course_type|general_education" and $form->training_program[1]!="") {
			$form->setInvalidMessage("training_program","General Education Courses Must Not Be Attached To Any Program");
			}
		else if($course_type!="course_type|general_education" and $form->training_program[1]=="") {
			$form->setInvalidMessage("training_program","You Must Select Training Program");
			}
		if($course_type=="course_type|general_education" and $form->training_institution[0][1]=="") {
			$form->setInvalidMessage("training_institution","You Must Select Training Institution");
			}
	  /**
		**Check to ensure that prerequisite course is of the previous semester
		**/
		$prerequisites=$form->prerequisite;
		$course_sem=$form->semester;		
		foreach($prerequisites as $prerequisite) {
			$preObj=$factory->createContainer("training|".$prerequisite[1]);
			$preObj->populate();
			$prer_sem=$preObj->getField("semester")->getDBValue();
			$prer_sem=explode("|",$prer_sem);
			$prer_sem=$prer_sem[1];
			if($prer_sem >= $course_sem[1]) {
				$form->setInvalidMessage("prerequisite","Prerequisites Course Must Be A Semester Before The Semester Of This Course");
				break;
				}
			}

	  /**
		**Check to ensure that corequisite course is of the same semester as this course
		**/
		$corequisites=$form->corequisite;
		$course_sem=$form->semester;
		foreach($corequisites as $corequisite) {
			$coreObj=$factory->createContainer("training|".$corequisite[1]);
			$coreObj->populate();
			$core_sem=$coreObj->getField("semester")->getDBValue();
			$core_sem=explode("|",$core_sem);
			$core_sem=$core_sem[1];
			if($core_sem != $course_sem[1]) {
				$form->setInvalidMessage("corequisite","Corequisite Course Must Be In The Same Semester As The Semester Of This Course");
				break;
				}
			}

	  /**
		**Check to ensure that Level and semester are consistency
		**/
		$semester=$form->semester;
		$level=$form->academic_level;
		if($semester[1]!=(2*$level[1]-1) and $semester[1]!=(2*$level[1])) {
			$form->setInvalidMessage("academic_level","Level And Semester Are Not Consistency");
			$form->setInvalidMessage("semester","Level And Semester Are Not Consistency");
			}
			
	$parent_form = $form->getParent();
	$exam_types = $form->training_course_exam_type;
	//check to ensure that,each exam type selected has its assessment field
	foreach($exam_types as $exam_type) {	
	$value=$form->$exam_type[1];
	if($value=="") {
	$form->setInvalidMessage($exam_type[1],ucfirst($exam_type[1])." Assessment Must Be Filled");
	}
	elseif(!is_numeric($value) or $value==0) {
	$form->setInvalidMessage($exam_type[1],ucfirst($exam_type[1])." Must Be Numeric And Greater Than 0");
	}
	else
	$total_assessment=$value+$total_assessment;
	}
	
	//ensure that assessment sum up to 100
	if($total_assessment > 100 or $total_assessment < 100) {
		foreach($exam_types as $exam_type) {
			$form->setInvalidMessage($exam_type[1],"Assessments Must Sum Up To Hundred");
			}
		}
		
	//check to ensure that,no assessment is filled without being selected from exam types
	$exam_types=I2CE_FormStorage::listFields("training_course_exam_type",array("id"));
	foreach ($exam_types as $exam_type=>$exam_type_array)
	{
	$exit=false;
	foreach ($form->training_course_exam_type as $form_exam_type)
	if(in_array($exam_type,$form_exam_type))
	$exit=true;
	if($exit)
	continue;
	$value=$form->$exam_type;
	if($value!="")
	$form->setInvalidMessage($exam_type,ucfirst($exam_type)." Should Not Be Filled As It Is Not Selected From Exam Types");
	}
	}

}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
