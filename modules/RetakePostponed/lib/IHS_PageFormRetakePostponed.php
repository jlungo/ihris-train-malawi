<?php
/*
 * © Copyright 2014 IntraHealth International, Inc.
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
 * Manage adding or editing forms associated with a person to the database.
 * 
 * @package iHRIS
 * @subpackage Common
 * @access public
 * @author Ally Shaban <allyshaban5@gmail.com>
 * @copyright Copyright &copy; 2014, 2008 IntraHealth International, Inc. 
 * @since v2.0.0
 * @version v2.0.0
 */

/**
 * Page object to handle the adding or editing forms associated with a person to the database.
 * 
 * @package iHRIS
 * @subpackage Common
 * @access public
 */
class IHS_PageFormRetakePostponed extends iHRIS_PageFormParentPerson {
    /**
     * Create and load data for the objects used for this form.
     * 
     * Create the list object and if this is a form submission load
     * the data from the form data.  It determines the type based on the
     * {@link $type} member variable.
     */
    protected function loadObjects() {
        if ($this->isPost()) {
            $primary = $this->factory->createContainer($this->getForm());
            if (!$primary instanceof I2CE_Form) {
                return false;
            }
            $primary->load($this->post);
            $person_id=$primary->getField("parent")->getDBValue();
        } elseif ( $this->get_exists('id') ) {
            if ($this->get_exists('id')) {
                $id = $this->get('id');
                if (strpos($id,'|')=== false) {
                    I2CE::raiseError("Deprecated use of id variable");
                    $id = $this->getForm() . '|' . $id;
                }
            } else {
                $id = $this->getForm() . '|0';
            }
            $primary = $this->factory->createContainer($id);
            if (!$primary instanceof I2CE_Form || $primary->getName() != $this->getForm()) {
                I2CE::raiseError("Could not create valid " . $this->getForm() . "form from id:$id");
                return false;
            }
            $primary->populate();
            $person_id=$primary->getField("person")->getDBValue();
        } elseif ( $this->get_exists('parent') ) {
        		$person_id=$this->request("parent");
        		$this->student_registration=STS_PageFormPerson::load_current_registration($person_id);
				###Deny course exemption for completed students###
				$progObj=$this->factory->createContainer($this->student_registration["training_program"]);
				$progObj->populate();
				if($this->student_registration["admission_type"]=="admission_type|full-time")
				$total_semesters=$progObj->getField("total_semesters_fulltime")->getDBValue();
				else if($this->student_registration["admission_type"]=="admission_type|part-time")
				$total_semesters=$progObj->getField("total_semesters_parttime")->getDBValue();
		    	$completed=IHS_PageFormEnrollcourse::completed_school(
		    																				$this->student_registration["semester"],
		    																				$total_semesters,$this->request("parent")
		    																			 );
		    	if($completed) {
		    		$this->userMessage("You Have Completed The Program");
		      	$this->setRedirect("view?id=" . $this->request("parent"));
		    		}
		    	###End of denying course exemption for completed students###
            $primary = $this->factory->createContainer($this->getForm());
            if (!$primary instanceof I2CE_Form) {
                return;
            }
            $parent = $this->get('parent');
            if (strpos($parent,'|')=== false) {
                I2CE::raiseError("Deprecated use of parent variable");
                $parent =  'person|' . $parent;            
            }
            $primary->setParent($parent);
        }
        if ($this->isGet()) {
            $primary->load($this->get());
        }
        $person = parent::loadPerson(  $primary->getParent() );
        if (!$person instanceof iHRIS_Person) {
            I2CE::raiseError("Could not create person form from " . $primary->getParent());
            return;
        }
        $person_id=$primary->getField("parent")->getDBValue();
        $this->student_registration=STS_PageFormPerson::load_current_registration($person_id);
        $this->applyLimits($primary);
        $this->setObject($primary, I2CE_PageForm::EDIT_PRIMARY, null, true);
        $this->setObject($person, I2CE_PageForm::EDIT_PARENT, null, true);
        return true;
    }

	protected function applyLimits($primary) {
		$username=$this->getUser()->username;
		$user_info=iHRIS_PageFormLecturer::fetch_user_info($username);
		$institution_id=$user_info["training_institution"];
		$dep_id=$user_info["department"];
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$current_academic_year_id="academic_year|".$academic_year_id;
		
		$inst_id=explode("|",$institution_id);
		$inst_id=$inst_id[1];
				$where=array(
						"operator"=>"FIELD_LIMIT",
						"field"=>"id",
						"style"=>"equals",
						"data"=>array("value"=>$inst_id)
						       );
		$inst_field=$primary->getField("training_institution");
		$inst_field->setOption(array("meta","limits","default","training_institution"),$where);
		
		//get program courses that this student is to retake
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"])),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"status",
																"style"=>"equals",
																"data"=>array("value"=>"status|fail"))
												 ));
		$results=I2CE_FormStorage::listFields("students_results_grade",array("training"),false,$where);
		foreach($results as $result) {
			$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"])),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"status",
																"style"=>"equals",
																"data"=>array("value"=>"status|pass")),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"equals",
																"data"=>array("value"=>$result["training"]))
												 ));
			$passed=I2CE_FormStorage::search("students_results_grade",false,$where);
			if(count($passed)>0)
			continue;
			$trngObj=$this->factory->createContainer($result["training"]);
			$trngObj->populate();
			$course_type=$trngObj->getField("course_type")->getDBValue();
			$course_semester=$trngObj->getField("semester")->getDBValue();
			if($course_type!="course_type|core")
			continue;
			//make sure that this course is offered in this semester
			if($this->student_registration["semester"]!=$course_semester and !$this->rescheduled_to_this_semester())
			continue;
			else if($this->student_registration["semester"]==$course_semester and 
						IHS_PageFormEnrollcourse::is_rescheduled(
																				$result["training"],
																				$current_academic_year_id,
																				$course_semester
																			 ))
			continue;
			list($form,$training_id)=array_pad(explode("|",$result["training"],2),2,"");
			$training_courses[]=$training_id;
			}
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"id",
							"style"=>"in",
							"data"=>array("value"=>$training_courses)
						 );
		$training_field=$primary->getField("training");
		$training_field->setOption(array("meta","limits","default","training"),$where);
		
		list($form,$sem_id)=array_pad(explode("|",$this->student_registration["semester"],2),2,"");
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"id",
							"style"=>"equals",
							"data"=>array("value"=>$sem_id)
						 );
		$semester_field=$primary->getField("semester");
		$semester_field->setOption(array("meta","limits","default","semester"),$where);
		}
		
	protected function rescheduled_to_this_semester($course) {
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$current_academic_year_id="academic_year|".$academic_year_id;
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"academic_year",
																"style"=>"equals",
																"data"=>array("value"=>$current_academic_year_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"new_semester",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["semester"])),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"equals",
																"data"=>array("value"=>$course)),
												  3=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training_institution",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["training_institution"]))
												 ));
		$courses=I2CE_FormStorage::search("reschedule_course",false,$where);
		if(count($courses)>0)
		return true;
		else
		return false;
		}
    /**
     * Save the objects to the database.
     * 
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {
    	$exemptionObj=$this->factory->createContainer("course_exemption");
		$exemptionObj->load($this->post);
		$exemptionObj->getField("registration")->setFromDB($this->student_registration["id"]);
		$exemptionObj->save($this->user);
		$exemptionObj->cleanup();
      if ($saved !== false) {
         $message = "Course(s) Exempted Successfully.";
      } else {
         $message = "Failed To Exempt Course(s) This Student.";            
      }
      $this->userMessage($message);
      $this->setRedirect(  "view?id=" . $this->getPrimary()->getParent() );
      return $saved;
    }
                
}


# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
