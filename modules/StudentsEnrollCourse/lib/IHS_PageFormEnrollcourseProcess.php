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
 * Manage license renewals.
 * 
 * @package iHRIS
 * @subpackage Qualify
 * @access public
 * @author Ally Shaban <allyshaban5@gmail.com>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @since v2.0.0
 * @version v2.0.0
 */

/**
 * Page object to handle the renewal of licenses.
 * 
 * @package iHRIS
 * @subpackage Qualify
 * @access public
 */
	class IHS_PageFormEnrollcourseProcess extends I2CE_PageForm {
    
    /**
     * Create and load data for the objects used for this form.
     */


	protected function loadObjects() {
		$this->ff = I2CE_FormFactory::instance();
		//check to ensure that the current academic year is available
		iHRIS_AcademicYear::ensureAcademicYear();
		$selected_courses=$this->post("course");
		$selected_incomplete_courses=$this->post("incomplete");
		$person_id=$this->post("person_id");
		$curr_semester=$this->post("curr_semester");
		$student_registration=STS_PageFormPerson::load_current_registration($person_id);
		$admission_type=$student_registration["admission_type"];
		$program=$student_registration["training_program"];
		
		$total_semesters=IHS_PageFormEnrollcourse::get_total_semesters($program,$admission_type);
		$current_semester=explode("|",$student_registration["semester"]);
		$current_semester=$current_semester[1];
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$academic_year_id="academic_year|".$academic_year_id;
		$date_enrolled=date("Y-m-d");
			
		if(count($selected_courses)==0 and count($selected_incomplete_courses)==0) {
			$this->userMessage("No courses Selected!!!");
			$this->setRedirect( "view?id=" . $person_id);
			return;
			}
		
		//if this student have gone beyond the normal institutional semesters,increment the semester before saving it
		$GPA_exist=IHS_PageFormEnrollcourse::GPA_exist($person_id,$student_registration["semester"],$student_registration["id"]);
		if($current_semester>=$total_semesters and $GPA_exist) {
			IHS_PageFormEnrollcourse::increment_semester ($person_id,true);
			$student_registration=STS_PageFormPerson::load_current_registration($person_id);
			$admission_type=$student_registration["admission_type"];
			$program=$student_registration["training_program"];
			$curr_semester=$student_registration["semester"];
			}
		
		//register incomplete courses
		if(count($selected_incomplete_courses)>0) {
			foreach($selected_incomplete_courses as $course) {
				$where=array(	"operator"=>"AND",
									"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"training",
																		"style"=>"equals",
																		"data"=>array("value"=>$course)),
														  1=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"semester",
																		"style"=>"equals",
																		"data"=>array("value"=>$curr_semester)),
														  2=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"registration",
																		"style"=>"equals",
																		"data"=>array("value"=>$student_registration["id"]))
														 ));
				$incomplt=I2CE_FormStorage::search("enroll_incomplete_course",false,$where);
				if(count($incomplt)>0)
				$form="enroll_incomplete_course|".$incomplt[0];
				else
				$form="enroll_incomplete_course";
				
				//fetch the results id of this incomplete
				$where=array(	"operator"=>"AND",
									"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"training",
																		"style"=>"equals",
																		"data"=>array("value"=>$course)),
														  1=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"status",
																		"style"=>"equals",
																		"data"=>array("value"=>"status|incomplete")),
														  2=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"registration",
																		"style"=>"equals",
																		"data"=>array("value"=>$student_registration["id"]))
														 ));
				$results=I2CE_FormStorage::search("students_results_grade",false,$where);
				$results_id="students_results_grade|".$results[0];
				$assessments=$this->incomplete_assessments($results[0]);
				$exam_types=implode(",",$assessments);
				$enrollIncObj=$this->factory->createContainer($form);
				$enrollIncObj->getField("training")->setFromDB($course);
				$enrollIncObj->getField("academic_year")->setFromDB($academic_year_id);
				$enrollIncObj->getField("semester")->setFromDB($curr_semester);
				$enrollIncObj->getField("students_results_grade")->setFromDB($results_id);
				$enrollIncObj->getField("training_course_exam_type")->setFromDB($exam_types);
				$enrollIncObj->getField("registration")->setFromDB($student_registration["id"]);
				$enrollIncObj->getField("date_enrolled")->setFromDB($date_enrolled);
				$enrollIncObj->getField("parent")->setFromDB($person_id);
				$enrollIncObj->save($this->user);
				}
			}
		if(count($selected_courses)>0) {
			//Tracking credits not to exceeds max sem credits
			$persObj=$this->factory->createContainer($person_id);
			$progObj=$this->factory->createContainer($program);
			$progObj->populate();
			if($admission_type=="admission_type|full-time")
				$max_sem_credits=$progObj->getField("max_sem_credits_fulltime")->getDBValue();
			if($admission_type=="admission_type|part-time")
				$max_sem_credits=$progObj->getField("max_sem_credits_parttime")->getDBValue();
			foreach($selected_courses as $id=>$course) {
				$courseObj=$this->factory->createContainer($course);
				$courseObj->populate();
				$credits=$courseObj->getField("course_credits")->getDBValue();
				$total_credits=$total_credits+$credits;
				}
				if($total_credits > $max_sem_credits) {
					$this->userMessage("Failed To Register Courses As Maximum Semester Credits ($max_sem_credits) Have Been Exceeded");
					$this->setRedirect( "enroll_course?parent=" . $person_id);
					return;
					}
			$selected_courses=implode(",",$selected_courses);
			$where=array(
								"operator"=>"AND",
								"operand"=>array(0=>array(
																	"operator"=>"FIELD_LIMIT",
																	"field"=>"parent",
																	"style"=>"equals",
																	"data"=>array("value"=>$person_id)
																	),
													  1=>array(
													  				"operator"=>"FIELD_LIMIT",
													  				"field"=>"semester",
													  				"style"=>"equals",
													  				"data"=>array("value"=>$curr_semester)
													  			 ),
													  	2=>array(
													  				"operator"=>"FIELD_LIMIT",
													  				"field"=>"registration",
													  				"style"=>"equals",
													  				"data"=>array("value"=>$student_registration["id"])
													  			 )
													 )
							 );
			$enrolled_courses=I2CE_FormStorage::search("enroll_course",false,$where);
			
			if(count($enrolled_courses)>0) {
				foreach ($enrolled_courses as $enrollment)
				$course_enrollment_form="enroll_course|".$enrollment;
				}
			else
				$course_enrollment_form="enroll_course";
			
			if (! ( $enrollcourseObj=$this->ff->createContainer($course_enrollment_form))  instanceof I2CE_Form) {	
				I2CE::raiseError("Invalid Object");
				return false;
			}
			
			//make sure we dont drop courses enrolled for students exempted by being given tests.
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"registration",
								"style"=>"equals",
								"data"=>array("value"=>$student_registration["id"])
							 );
			$grade_based_on_test=I2CE_FormStorage::listFields("grade_based_on_test",array("training"),false,$where);
			foreach($grade_based_on_test as $test) {
				$enrolled_exempted=$test["training"];
				$enrolled_exempted=explode(",",$enrolled_exempted);
				$selected_courses=explode(",",$selected_courses);
				$selected_courses=array_merge($enrolled_exempted,$selected_courses);
				$selected_courses=implode(",",$selected_courses);
				}
			$registration=STS_PageFormPerson::load_current_registration($person_id);
			$trainingCourseField  = $enrollcourseObj->getField("training");
			$trainingCourseField->setFromPost($selected_courses);
			$semesterField  = $enrollcourseObj->getField("semester");
			$semesterField->setFromPost($curr_semester);
			$date_enrolled=date("Y-m-d");
			$enrollcourseObj->getField("total_credits")->setValue($total_credits);
			$enrollcourseObj->getField("registration")->setFromDB($student_registration["id"]);
			$enrollcourseObj->getField("date_enrolled")->setFromDB($date_enrolled);
			$enrollcourseObj->getField("parent")->setFromDB($person_id);
			
			if(!($academicYearField  = $enrollcourseObj->getField("academic_year")) instanceof I2CE_FormField_MAP)
			return;
			$academicYearField->setFromDB($academic_year_id);
		
			$parentObj = $this->ff->createContainer($person_id);
			if ($parentObj instanceof I2CE_Form) {	
				$parentObj->populate();
			}
	
			$enrollcourseObj->save($this->user);
			}
		$this->userMessage("Courses Enrolled Successfully");
		$this->setRedirect( "view?id=" . $person_id);
		return true;
		}
	
	protected function incomplete_assessments($results_id) {
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>"students_results_grade|".$results_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"score",
																"style"=>"equals",
																"data"=>array("value"=>-1)),
															 ));
		$ex_typs=I2CE_FormStorage::listFields("students_results",array("training_course_exam_type"),false,$where);
		foreach($ex_typs as $ex_typ) {
			$exam_types[]=$ex_typ["training_course_exam_type"];
			}
		return $exam_types;
		}
	}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
