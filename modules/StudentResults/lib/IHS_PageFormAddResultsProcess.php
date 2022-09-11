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
 * @subpackage Manage
 * @access public
 * @author Ally Shaban <allyshaban5@gmail.com>
 * @copyright Copyright &copy; 2012 IntraHealth International, Inc. 
 * @since v4.1.4
 * @version v4.1.4
 */

/**
 * The page class for editing particpants for a training
 * @package iHRIS
 * @subpackage Manage
 * @access public
 */
	class IHS_PageFormAddResultsProcess extends I2CE_PageForm  {
	protected $exam_types=array();
	protected $course_id;
	protected $results=array();
	protected $reg_num;
	protected $user;
	protected $training_program;
	protected $person_id;
	protected $registered_academic_year;
	protected $date_registered;
	protected $core_courses=array();
	protected $optional_courses=array();
	protected $enrolled_courses=array();
	protected $assessment_weight=array();
	protected $curr_semester;
	protected $training_institution;
	protected $points;
	protected $GPA;
	protected $overall_GPA;
	protected $results_form;
	protected $update;
	protected $current_academic_year;
	protected $academic_year_id;
   
   protected function manual_constructor($reg_id) {
		$regObj=$this->factory->createContainer($reg_id);
		$regObj->populate();
		$this->person_id=$regObj->getField("parent")->getDBValue();
		$this->student_registration=STS_PageFormPerson::load_current_registration($this->person_id);
		$this->date_registered=$this->student_registration["registration_date"];
		$this->registration_number=$this->student_registration["registration_number"];
		$this->training_program=$this->student_registration["training_program"];
		$this->admission_type=$this->student_registration["admission_type"];
		$this->training_institution=$this->student_registration["training_institution"];
		$this->joined_semester=$this->student_registration["joined_semester"];
		$this->curr_semester=$this->student_registration["semester"];
		$this->registered_academic_year=$this->student_registration["academic_year"];
		
		$instObj=$this->ff->createContainer($this->training_institution);
		$instObj->populate();
		$this->min_semester_GPA=$instObj->getField("minimum_semester_GPA")->getDBValue();
		$this->min_overall_GPA=$instObj->getField("minimum_overall_GPA")->getDBValue();
		$this->passing_score=$instObj->getField("passing_score")->getDBValue();
		$instObj->cleanup();
		
		$progObj=$this->factory->createContainer($this->training_program);
		$progObj->populate();
		if($this->admission_type=="admission_type|full-time")
		$this->total_semesters=$progObj->getField("total_semesters_fulltime")->getDBValue();
		else if($this->admission_type=="admission_type|part-time")
		$this->total_semesters=$progObj->getField("total_semesters_parttime")->getDBValue();
		$this->program_category=$progObj->getField("program_category")->getDBValue();
		$progObj->cleanup();
	}
	
	protected function action() {
	$this->ff = I2CE_FormFactory::instance();
	$this->user=new I2CE_User;
	//check to ensure that the current academic year is available
	iHRIS_AcademicYear::ensureAcademicYear();
	$this->current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
	$academic_year_id=iHRIS_AcademicYear::academicYearId($this->current_academic_year);
	$this->academic_year_id="academic_year|".$academic_year_id;
	
	$this->course_id=$this->request("course_id");
	
	if ( ! ($courseObj = $this->ff->createContainer($this->course_id)) instanceof iHRIS_Training
	   || ! ($examTypesField = $courseObj->getField('training_course_exam_type')) instanceof I2CE_FormField_MAP_MULT
	   ) {
	  I2CE::raiseError("Invalid training course: " . $this->course_id);
	  return false;    
	}

	$courseObj->populate();
	$reg_ids=$this->post("reg_id");	
	######start processing results of each student######
	$process=false;
	foreach($reg_ids as $this->reg_id) {
		$this->manual_constructor($this->reg_id);
		$all_results=false;
		$this->update=false;	
		######process each course assessment for this student######
		$exam_types=explode(",",$examTypesField->getDBValue());
		foreach ($exam_types as $exam_type) {
			if($this->post_exists($this->reg_id."_results")) {
				$this->students_results_grade_form="students_results_grade|".$this->post($this->reg_id."_results");
				break;
				}
			else {
				$this->students_results_grade_form="students_results_grade";
				}
			}

		foreach ($exam_types as $exam_type) {
			######Skip processing existing assessment results######
			$mark=$this->check_assessment_mark($exam_type);
	   	if(
	   			($this->post($exam_type."/".$this->reg_id)!="" and 
	   			 $mark != $this->get_mark_inweight($exam_type,$this->post($exam_type."/".$this->reg_id))) or 
	   			 ($this->post($exam_type."/".$this->reg_id)=="" and ($mark!="" and $mark!=-1 and $mark!=0)) or 
	   			 (($this->post($exam_type."/".$this->reg_id)=="I" or $this->post($exam_type."/".$this->reg_id)=="i") and 
	   			 	($mark!="i" or $mark!="I")) or 
	   			 (($this->post($exam_type."/".$this->reg_id)!="I" or 
	   			 	$this->post($exam_type."/".$this->reg_id)!="i") and 
	   			  ($mark=="i" or $mark=="I"))) {
	        $this->results[$exam_type]=$this->post($exam_type."/".$this->reg_id);
	        	$process=true;
	        	}
			}
	if(count($this->results)>0)
	$this->saveResults($exam_types);
	
	unset($this->results);	
	######End of processing each course assessment for this student######
	}
	if (!$process) {
		$this->userMessage("No Changes Saved!!!");
		$this->setRedirect("add_results_select_course");
		}
	######End of processing results of each student######	
	}
	
	protected function saveResults($exam_types) {
	$resultsgradeObj=$this->ff->createContainer($this->students_results_grade_form);
	$resultsgradeObj->populate();
	$resultsgradeObj->getField("academic_year")->setFromDB($this->academic_year_id);
	$resultsgradeObj->getField('training')->setFromDB($this->course_id);
	$resultsgradeObj->getField("registration_number")->setFromPost($this->registration_number);		
	//retrieve the enroll_course form for this person
	$where=array(	"operator"=>"AND",
						"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
															"field"=>"semester",
															"style"=>"equals",
															"data"=>array("value"=>$this->curr_semester)),
											  1=>array(	"operator"=>"FIELD_LIMIT",
															"field"=>"parent",
															"style"=>"equals",
															"data"=>array("value"=>$this->person_id)),
											  2=>array(	"operator"=>"FIELD_LIMIT",
															"field"=>"training",
															"style"=>"like",
															"data"=>array("value"=>"%".$this->course_id."%")),
											  3=>array(	"operator"=>"FIELD_LIMIT",
															"field"=>"registration",
															"style"=>"equals",
															"data"=>array("value"=>$this->student_registration["id"])),
											));
	$enrolls=I2CE_FormStorage::listFields("enroll_course",array("training"),false,$where);
	//if $enrolls returns empty array then this course belongs to the previous	
	if(count($enrolls)==0) {
		$this->enroll_course=$resultsgradeObj->getField("enroll_course")->getDBValue();
		}
	else {
		foreach($enrolls as $id=>$enroll) {
			$enroll=explode(",",$enroll["training"]);
			if(!in_array($this->course_id,$enroll))
			$this->enroll_course=$resultsgradeObj->getField("enroll_course")->getDBValue();
			else
			$this->enroll_course="enroll_course|".$id;
			}
		}

	$resultsgradeObj->getField("enroll_course")->setFromDB($this->enroll_course);
	$enrollObj=$this->factory->createContainer($this->enroll_course);
	$enrollObj->populate();
	$enroll_sem=$enrollObj->getField("semester")->getDBValue();
	$resultsgradeObj->getField("enroll_course")->setFromDB($this->enroll_course);
	$resultsgradeObj->getField("semester")->setFromDB($enroll_sem);
	$resultsgradeObj->getField("registration")->setFromDB($this->student_registration["id"]);
	//check to see if all results are entered and calculate grade
	foreach ($exam_types as $exam_type) {
		if(($this->post($exam_type."/".$this->reg_id)) !="" or $this->check_assessment_mark($exam_type)>=0) {			
	  		$all_results_available=true;
	  		}
		else {
	  		$all_results_available=false;
	  		break;
	  		}
		}
	
	$disco_reason=null;
	if($all_results_available) {
	$total_marks=$this->totalMarks($exam_types);
	$grade=$this->calculateGrade($total_marks);
	if(!($gradeField=$resultsgradeObj->getField("grade")) instanceof I2CE_FormField) {
	I2CE::raiseError("Invalid Object");
	return false;
	}
	$gradeField->setFromDB($grade);
	
	if(!($totalMarksField=$resultsgradeObj->getField("total_marks")) instanceof I2CE_FormField) {
	I2CE::raiseError("Invalid Object");
	return false;
	}
	if($total_marks=="I")
	$totalMarksField->setFromDB(-1);
	else
	$totalMarksField->setFromDB($total_marks);
	
	$resultsgradeObj->getField("theory_mark")->setFromDB($this->theory_mark);
	$resultsgradeObj->getField("clinical_mark")->setFromDB($this->clinical_mark);
	
	//check if this is a course with clinical component and treat it differently
	if($total_marks=="I" or $total_marks=="i") {
		$recommendations="recommendations|I";
		}
	else if($this->has_clinical()){
		if($this->failed_course_with_clinical($exam_types) and $total_marks >= 40) {
			$recommendations="recommendations|AW";
			}
		else if($this->failed_course_with_clinical($exam_types) and $total_marks < 40) {
			$recommendations="recommendations|AP";
			}
		else
			$recommendations="recommendations|P";
		}
	else if($total_marks>=$this->passing_score) {
		$recommendations="recommendations|P";
		}
	else if($total_marks < 40) {
		$recommendations="recommendations|AP";
		}
	else if($total_marks>=40 and $total_marks<$this->passing_score) {
		$recommendations="recommendations|AW";
		}
	$resultsgradeObj->getField("recommendations")->setFromDB($recommendations);
	$status=$this->getStatus($total_marks);
	//if a course is having clinical component and a student has failed either clinical or theory assessment,change status to fail
	if($this->has_clinical()){
		if($total_marks=="I")
		$status="status|incomplete";
		else if($this->failed_course_with_clinical($exam_types)) {
			$status="status|fail";
			}
		else
			$status="status|pass";
		}
		
	if(!($statusField=$resultsgradeObj->getField("status")) instanceof I2CE_FormField) {
	I2CE::raiseError("Invalid Object");
	return false;
	}
	$statusField->setFromDB($status);
	
	$attempt=$this->checkAttempt($this->course_id);
	$attempt++;
	//check if academic probation already added for this course in this academic year
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$this->person_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"equals",
																"data"=>array("value"=>$this->course_id)),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$this->curr_semester)),
												  3=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"])						
														    )
												  )
						 );
	$prob_exists=I2CE_FormStorage::search("academic_probation",false,$where);
	//if mark is less than 40 then this is proceed with academic probation
	if($total_marks < 40 and count($prob_exists)==0) {
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$this->person_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"equals",
																"data"=>array("value"=>$this->course_id)),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"]))
												  )
						 );
		$probations=I2CE_FormStorage::listFields("academic_probation",array("count"),false,$where);

		$probation_count=0;
		foreach ($probations as $probation) {
			if($probation_count < $probation["count"]) {
				$probation_count=$probation["count"];
				}
			}

		++$probation_count;
		$probObj=$this->factory->createContainer("academic_probation");
		$probObj->getField("parent")->setValue($this->person_id);
		$probObj->getField("academic_year")->setFromDB($this->academic_year_id);
		$probObj->getField("semester")->setFromDB($this->curr_semester);
		$probObj->getField("registration")->setFromDB($this->student_registration["id"]);
		$probation_date=date("Y-m-d");
		$probObj->getField("probation_date")->setFromDB($probation_date);
		$probObj->getField("training")->setFromDB($this->course_id);
		$probObj->getField("count")->setFromDB($probation_count);
		$probObj->save($this->user);
		$probObj->cleanup();
		}
		//if probation for this ac year exists and mark is grt than 40 then remove this probation,mark might have changed by lecturer
		if($total_marks >= 40 and count($prob_exists)>0) {
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$this->person_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"equals",
																"data"=>array("value"=>$this->course_id)),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$this->curr_semester)),
												  3=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"]))
												  )
						 );
		$probations=I2CE_FormStorage::listFields("academic_probation",array("count","id"),false,$where);

		$probation_count=0;
		foreach ($probations as $probation) {
			if($probation_count < $probation["count"]) {
				$probation_count=$probation["count"];
				$id=$probation["id"];
				}
			}

		--$probation_count;
		$probObj=$this->factory->createContainer("academic_probation|".$id);
		$probObj->populate();
		if($probation_count<=0)
		$probObj->delete();
		else {
			$probObj->getField("count")->setFromDB($probation_count);
			$probObj->save($this->user);
			}
		$probObj->cleanup();
		}
	//if the status of this course is fail and attempt=3 then add to discontinued
	if($probation_count==3) {
		list($course_form,$course_id)=array_pad(explode("|",$this->course_id,2),2,"");
		$course_type=I2CE_FormStorage::lookupField($course_form,$course_id,array("course_type"),false);
		$course_type=$course_type["course_type"];
		//if this is a core course or this is the pre-requisite or co-requisite,then a student is discontinued
		$isprerequisite=self::isPrerequisite($this->course_id,$this->training_program,$this->training_institution);
		if($course_type=="course_type|core" or $isprerequisite or $this->isCorequisite()) {			
			$disco_reason="disco_reason|probations";
			}
		}
	$resultsgradeObj->getField("attempt")->setFromPost($attempt);
	}
	$date_uploaded=date("Y-m-d");
	$resultsgradeObj->getField("date_uploaded")->setFromDB($date_uploaded);
	$resultsgradeObj->getField("parent")->setValue($this->person_id);
	$resultsgradeObj->save($this->user);
	$results_grade_id=$resultsgradeObj->getID();
	
	foreach ($this->results as $field=>$result) {
		//check if this results exists and update it
		$where=array ("operator"=>"AND",
							"operand"=>array(	0=>array("operator" => "FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>"students_results_grade|".$results_grade_id)),
												  	1=>array("operator" => "FIELD_LIMIT",
																"field"=>"training_course_exam_type",
																"style"=>"equals",
																"data"=>array("value"=>$field))
												  ));
		$studResults=I2CE_FormStorage::listFields("students_results",array("id"),false,$where);
		if(count($studResults)>0) {
			foreach($studResults as $id=>$studResults) {
				$assesResultsObj=$this->ff->createContainer("students_results|".$id);
				}
			}
		else {
			$assesResultsObj=$this->ff->createContainer("students_results");
			}
		$assesResultsObj->getField("training_course_exam_type")->setFromDB($field);
		if($result=="i")
		$result="I";
		
		//this is because if it is zero then it saves nothing into the database
		if($result=="I" or $result=="i")
		$result=-1;
		else
		$result=number_format($result,1);
		$assesResultsObj->getField("score")->setFromDB($result);
		$assesResultsObj->getField("parent")->setValue("students_results_grade|".$results_grade_id);		
		$assesResultsObj->save($this->user);
		}

	if($this->calculateGPA()) {
		$GPAObj=$this->getGPAObj();
		if(!$GPAObj)
		$semester_GPA="semester_GPA";
		else
		$semester_GPA=$GPAObj;
		$semesterGPAObj=$this->ff->createContainer($semester_GPA);
		$semesterGPAObj->populate();
		//get semester to which this course a student has enrolled
		$enrollObj=$this->factory->createContainer($this->enroll_course);
		$enrollObj->populate();
		$semester=$enrollObj->getField("semester")->getDBValue();
		if(!$GPAObj) {
			$semesterGPAObj->getField("academic_year")->setFromDB($this->academic_year_id);
			$semesterGPAObj->getField("enroll_course")->setFromDB($this->enroll_course);
			$semesterGPAObj->getField("parent")->setValue($this->person_id);
			$semesterGPAObj->getField("semester")->setFromDB($semester);
			$semesterGPAObj->getField("registration_number")->setFromPost($this->registration_number);
			$semesterGPAObj->getField("registration")->setFromDB($this->student_registration["id"]);	
			}
		$date_calc=date("Y-m-d");
		$semesterGPAObj->getField("GPA")->setFromDB($this->GPA);
		$semesterGPAObj->getField("date_calculated")->setFromDB($date_calc);
		$semesterGPAObj->save($this->user);
			
		//if the GPA is less than 1.5 then add to discontinued form		
		if($this->GPA<$this->min_semester_GPA) {
			if(isset($disco_reason))
			$disco_reason=$disco_reason.","."disco_reason|below_gpa";
			else
			$disco_reason="disco_reason|below_gpa";
		}
		
		//save the semester status
		$semester_status=$this->get_semester_status();
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"enroll_course",
							"style"=>"equals",
							"data"=>array("value"=>$this->enroll_course));
		$sem_status=I2CE_FormStorage::search("semester_status",false,$where);
		if(count($sem_status)==0) {
			$sem_status_form="semester_status";
			}
		else {
			foreach ($sem_status as $status) {
				$sem_status_form="semester_status|".$status;
				}
			}
		//get semester for this enrollment
		$enroll=$this->factory->createContainer($this->enroll_course);
		$enroll->populate();
		$semester_name=$enroll->getField("semester")->getDBValue();
		$semStatusObj=$this->factory->createContainer($sem_status_form);
		$semStatusObj->getField("registration_number")->setFromPost($this->registration_number);
		$semStatusObj->getField("academic_year")->setFromPost($this->academic_year_id);
		$semStatusObj->getField("registration")->setFromDB($this->student_registration["id"]);
		$semStatusObj->getField("semester")->setFromPost($semester_name);
		$semStatusObj->getField("status")->setFromPost($semester_status);
		$semStatusObj->getField("enroll_course")->setFromDB($this->enroll_course);
		$semStatusObj->getField("parent")->setValue($this->person_id);
		$semStatusObj->save($this->user);
		//end of processing semester status
		
		//look for pending/retake courses
		$pending_courses=$this->get_pending_courses();
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"enroll_course",
							"style"=>"equals",
							"data"=>array("value"=>$this->enroll_course));
		$pendings=I2CE_FormStorage::search("pending_courses",false,$where);
		if(count($pendings)==0) {
			$pending_form="pending_courses";
			}
		else {
			foreach ($pendings as $pending) {
				$pending_form="pending_courses|".$pending;
				}
			$pendingObj=$this->factory->createContainer($pending_form);
			$pendingObj->populate();
				
				//make sure that we dont overwrite pending courses that were captured if this student was taken to a semester higher than 
				//semester one
			if($this->student_registration["joined_semester"]==$this->student_registration["semester"]) {
				$existing_pending=$pendingObj->getField("training")->getDBValue();
				$existing_pending=explode(",",$existing_pending);
				$courses_assigned_grades_based_ontest=self::get_courses_assigned_grades_based_ontest();
				$courses_assigned_grades_based_ontest=explode(",",$courses_assigned_grades_based_ontest);
				foreach($existing_pending as $id=>$course) {
					if(!in_array($course,$courses_assigned_grades_based_ontest))
					unset($existing_pending[$id]);
					}
				
				$pending_courses=array_merge($pending_courses,$existing_pending);
				}
			//if no more pending courses then delete this form
			if(count($pending_courses)==0) {
				$pendingObj->delete();
				}
			}
			
		if(count($pending_courses)>0) {
		//get semester for this enrollment
		$enroll=$this->factory->createContainer($this->enroll_course);
		$enroll->populate();
		$semester_name=$enroll->getField("semester")->getDBValue();
		$pendingObj=$this->factory->createContainer($pending_form);
		$pendingObj->getField("registration_number")->setFromPost($this->registration_number);
		$pendingObj->getField("academic_year")->setFromPost($this->academic_year_id);
		$pendingObj->getField("registration")->setFromDB($this->student_registration["id"]);
		$pendingObj->getField("semester")->setFromPost($semester_name);
		$pending_courses=implode(",",$pending_courses);
		$pendingObj->getField("training")->setFromPost($pending_courses);
		$pendingObj->getField("enroll_course")->setFromDB($this->enroll_course);
		$pendingObj->getField("parent")->setValue($this->person_id);
		$pendingObj->save($this->user);
			}
		//end of looking for pending courses
	//if failed all courses then add to discontinued form
	$enrollObj=$this->factory->createContainer($this->enroll_course);
	$enrollObj->populate();
	$semester=$enrollObj->getField("semester")->getDBValue();
	$failed_all_courses=true;
	list($sem_form,$sem_name)=explode("|",$semester);
	
	//if the current semester is greater than total semesters then this student is completing either 
	//failed courses or courses that didnt take,then dont disco even if fail all courses
	if($this->total_semesters < $sem_name)
	$failed_all_courses=false;
	
	//retrieve all passed courses and check if they belong to the current semester,if one found then a student didnt fail all courses
	$where=array("operator"=>"AND","operand"=>array(
						0=>array("operator"=>"FIELD_LIMIT",
									"field"=>"parent",
									"style"=>"equals",
									"data"=>array("value"=>$this->person_id)),
						1=>array("operator"=>"FIELD_LIMIT",
									"field"=>"enroll_course",
									"style"=>"equals",
									"data"=>array("value"=>$this->enroll_course)),
					   2=>array("operator"=>"FIELD_LIMIT",
					   			"field"=>"status",
					   			"style"=>"equals",
					   			"data"=>array("value"=>"status|pass")),
						3=>array("operator"=>"FIELD_LIMIT",
									"field"=>"registration",
			       				"style"=>"equals",
									"data"=>array("value"=>$this->student_registration["id"]))
					   			
					 ));
	$results=I2CE_FormStorage::search("students_results_grade",false,$where);
	if(count($results)>0) {
		$failed_all_courses=false;
		}
	
	if($failed_all_courses==true) {
		if(isset($disco_reason))
		$disco_reason=$disco_reason.","."disco_reason|failed_all";
		else
		$disco_reason="disco_reason|failed_all";
		}
	
			
		######If all semester GPA Available,calculate overall GPA######
				//check total semesters
	$persObj=$this->ff->createContainer($this->person_id);
	//check if student passed all core courses and calculate overall GPA
	list($form,$joined_sem)=explode("|",$this->joined_semester);
	
	$persObj->populateChildren("semester_GPA");
	if(count($persObj->getChildren("semester_GPA")) >= ($this->total_semesters-$joined_sem+1) and $this->passed_core_courses()) {
		$overallGPA=$this->calculate_overall_GPA();
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"])),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$this->person_id))
								              ));
		$overall_GPA=I2CE_FormStorage::search("overall_GPA",false,$where);
		if(count($overall_GPA)>0) {
			$overallGPAObj=$this->factory->createContainer("overall_GPA|".$overall_GPA[0]);
			}
		else {
			$overallGPAObj=$this->ff->createContainer("overall_GPA");
			}
		$overallGPAObj->populate();
		$overallGPAObj->getField("GPA")->setFromDB($overallGPA);
		$overallGPAObj->getField("registration")->setFromDB($this->student_registration["id"]);
		$overallGPAObj->getField("parent")->setValue($this->person_id);
		$year=date("Y");
		$overallGPAObj->getField("year")->setValue($year);
		$overallGPAObj->save($this->user);
		if($overallGPA<$this->min_overall_GPA) {
			if(isset($disco_reason))
				$disco_reason=$disco_reason.","."disco_reason|below_overall_gpa";
			else
				$disco_reason="disco_reason|below_overall_gpa";
			}
		}
	   ######End of calculating overall GPA######
	}
	
	//if the discontnued reason exist then add this person to discontinued form
	if(isset($disco_reason))
	$this->addToDiscontinued($disco_reason,$this->getDiscoForm());	
	$this->save();
	return true;
	}
	
	static function get_courses_assigned_grades_based_ontest($reg_id) {
		$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"registration",
								"style"=>"equals",
								"data"=>array("value"=>$reg_id)
							 );
		$grade_based_on_test=I2CE_FormStorage::listFields("grade_based_on_test",array("training"),false,$where);
		foreach($grade_based_on_test as $test) {
			return $test["training"];
			}
		}
		
	public function calculate_overall_GPA() {
		$where = array(	'operator'=>'AND',
								'operand'=>array(0=>array(	'operator'=>'FIELD_LIMIT',
	                      									'field'=>'parent',
	                      									'style'=>'equals',
	                      									'data'=>array('value'=>$this->person_id)),
													  1=>array(	'operator'=>'FIELD_LIMIT',
																	'field'=>'registration',
																	'style'=>'equals',
																	'data'=>array('value'=>$this->student_registration["id"]))
	                                     ));
		$courses= I2CE_FormStorage::listFields("enroll_course",array("training"),false,$where);
		foreach ($courses as $course) {
			$course_array=explode(",",$course["training"]);
			foreach($course_array as $course) {
				if(!in_array($course,$enrolled_courses))
				$enrolled_courses[]=$course;
				}
			}
		foreach ($enrolled_courses as $course) {
			$mark=self::getCourseHighestMark($course,$this->student_registration["id"]);
			$grade=$this->calculateGrade($mark);
			$courseObj = $this->factory->createContainer($course);
			$courseObj->populate();
			$credits=$courseObj->getField('course_credits')->getValue();
			$quality_points=$credits*$this->points;
			$total_quality_points=$total_quality_points+$quality_points;
			$total_credits=$total_credits+$credits;
			}
			$overall_GPA=number_format($total_quality_points/$total_credits,3);
			return $overall_GPA;
		}
		
	public function passed_core_courses() {
		$exempted_courses=IHS_PageFormEnrollcourse::load_exempted_courses($this->student_registration["id"],$this->person_id);
		list($form,$joined_sem)=explode("|",$this->joined_semester);
		for ($sem=$joined_sem;$sem<=$this->total_semesters;$sem++) {
			$semester="semester|".$sem;
			$where=array(	"operator"=>"AND",
					"operand"=>array(
									0=>array("operator"=>"FIELD_LIMIT",
												"field"=>"semester",
												"style"=>"equals",
												"data"=>array("value"=>$semester)),
									1=>array("operator"=>"FIELD_LIMIT",
												"field"=>"training_program",
												"style"=>"equals",
												"data"=>array("value"=>$this->training_program)),
									2=>array("operator"=>"FIELD_LIMIT",
												"field"=>"course_type",
												"style"=>"equals",
												"data"=>array("value"=>"course_type|core"))
											));
			$courses=I2CE_FormStorage::search("training",false,$where);
			foreach($courses as $course) {
				$course="training|".$course;
				//ignore exempted courses
				if(!in_array($course,$exempted_courses)) {
					//get results for this course
					$mark=self::getCourseHighestMark($course,$this->student_registration["id"]);
					//return false if results does not exist or mark is less than the passing score
					if(!isset($mark) or $mark<$this->passing_score)
					return false;
					}
				}
			}
		return true;
		}
		
	protected function has_clinical() {
		$courseObj=$this->factory->createContainer($this->course_id);
		$courseObj->populate();
		$exam_types=$courseObj->getField("training_course_exam_type")->getDBValue();
		$exam_types=explode(",",$exam_types);
		if(in_array("training_course_exam_type|clinical",$exam_types)) {
			return true;
			}
		else
			return false;
		}

	protected function failed_course_with_clinical($exam_types) {
		$no_theory=true;
		foreach ($exam_types as $exam_type) {
			$result=$this->check_assessment_mark($exam_type);
			//if results available into the database,compare with the one submitted to see if changed
			if($result != $this->get_mark_inweight($exam_type,$this->post($exam_type."/".$this->reg_id)))
			$result = $this->get_mark_inweight($exam_type,$this->post($exam_type."/".$this->reg_id));
			
			//if its incomplete then failed a course
			if($result=="i" or $result=="I")
			return true;
				
				$courseObj=$this->factory->createContainer($this->course_id);
				$courseObj->populate();
				list($form,$assessment)=explode("|",$exam_type);
				
				if($exam_type=="training_course_exam_type|clinical") {
					$clinical_mark=$result;
					$total_clinical_assessment=$total_clinical_assessment+$courseObj->getField($assessment)->getDBValue();
					}
				else {
					$no_theory=false;
					$theory_mark=$theory_mark+$result;
					$total_theory_assessment=$total_theory_assessment+$courseObj->getField($assessment)->getDBValue();
					}
			}
		$clinical_passmark=($total_clinical_assessment/2)-0.5;
		$theory_passmark=($total_theory_assessment/2)-0.5;
		if($clinical_mark < $clinical_passmark or ($theory_mark < $theory_passmark and !$no_theory))
				return true;
			else
				return false;
		}
		
	static function isPrerequisite($course_id,$program,$institution) {
		$where=array( "operator"=>"AND","operand"=>array(
																			0=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"prerequisite",
																						"style"=>"like",
																						"data"=>array("value"=>"%".$course_id."%")
																					  ),
																			1=>array("operator"=>"OR",
																						"operand"=>array(
																						0=>array("operator"=>"FIELD_LIMIT",
																									"field"=>"training_program",
																									"style"=>"equals",
																									"data"=>array("value"=>$program)),
																						1=>array("operator"=>"AND",
																									"operand"=>array(
																										0=>array(
																											"operator"=>"FIELD_LIMIT",
																											"field"=>"training_institution",
																											"style"=>"equals",
																											"data"=>array("value"=>$institution)
																												  ),
																										1=>array(
																											"operator"=>"FIELD_LIMIT",
																											"field"=>"course_type",
																											"style"=>"equals",
																											"data"=>array("value"=>"course_type|general_education")
																												  )
																											))))));
																											
		$prerequisites=I2CE_FormStorage::listFields("training",array("prerequisite"),false,$where);
		if(count($prerequisites)>0) {
			foreach($prerequisites as $prerequisite) {
				$courses=explode(",",$prerequisite["training"]);
				if(in_array($course_id,$courses))
				return true;
				}
			}
		return false;
		}
	
	protected function isCorequisite() {
		$where=array( "operator"=>"AND","operand"=>array(
																			0=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"corequisite",
																						"style"=>"like",
																						"data"=>array("value"=>"%".$this->course_id."%")
																					  ),
																			1=>array("operator"=>"OR",
																						"operand"=>array(
																						0=>array("operator"=>"FIELD_LIMIT",
																									"field"=>"training_program",
																									"style"=>"equals",
																									"data"=>array("value"=>$this->training_program)),
																						1=>array("operator"=>"AND",
																									"operand"=>array(
																										0=>array(
																											"operator"=>"FIELD_LIMIT",
																											"field"=>"training_institution",
																											"style"=>"equals",
																											"data"=>array("value"=>$this->training_institution)
																												  ),
																										1=>array(
																											"operator"=>"FIELD_LIMIT",
																											"field"=>"course_type",
																											"style"=>"equals",
																											"data"=>array("value"=>"course_type|general_education")
																												  )
																											))))));
		$corequisites=I2CE_FormStorage::listFields("training",array("corequisite"),false,$where);
		if(count($corequisites)>0) {
			foreach($corequisites as $corequisite) {
				$courses=explode(",",$corequisites["training"]);
				if(in_array($this->course_id,$courses))
				return true;
				}
			}
		return false;	
		}
	
		protected function get_semester_status() {
			$enrollObj=$this->factory->createContainer($this->enroll_course);
			$enrollObj->populate();
			$trainings=$enrollObj->getField("training")->getDBValue();
			$trainings=explode(",",$trainings);
			foreach ($trainings as $training) {
				$where=array(	"operator"=>"FIELD_LIMIT",
									"field"=>"enroll_course",
									"style"=>"equals",
									"data"=>array("value"=>$this->enroll_course));
				$results=I2CE_FormStorage::listFields("students_results_grade",array("recommendations"),false,$where);
				$recommendations="recommendations|P";
				foreach($results as $result) {
					if($result["recommendations"]=="recommendations|AW" or $result["recommendations"]=="recommendations|AP")
					$recommendations=$result["recommendations"];
					else if($result["recommendations"]=="recommendations|I") {
						$recommendations=$result["recommendations"];
						break;
						}
					}
				}
			return $recommendations;
			}

	protected function get_pending_courses() {
		$enrollObj=$this->factory->createContainer($this->enroll_course);
		$enrollObj->populate();
		$trainings=$enrollObj->getField("training")->getDBValue();
		$semester=$enrollObj->getField("semester")->getDBValue();
		$enrolled_courses=explode(",",$trainings);
		//get failed courses for this enrollment
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"enroll_course",
																"style"=>"equals",
																"data"=>array("value"=>$this->enroll_course)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"status",
																"style"=>"equals",
																"data"=>array("value"=>"status|fail"))
												 ));

		$failed_courses=I2CE_FormStorage::listFields("students_results_grade",array("training"),false,$where);
		if(count($failed_courses)>0) {
			foreach($failed_courses as $course) {
				$pending_courses[]=$course["training"];
				}
			}

		//get core courses that this student didnt register
		$where=array(	"operator"=>"AND",
							"operand"=>array(
								0=>array("operator"=>"FIELD_LIMIT",
											"field"=>"semester",
											"style"=>"equals",
											"data"=>array("value"=>$semester)),
								1=>array("operator"=>"OR",
											"operand"=>array(0=>array(	"operator"=>"AND",
																				"operand"=>array(
																							  0=>array(	"operator"=>"FIELD_LIMIT",
																											"field"=>"training_program",
																											"style"=>"equals",
																											"data"=>array("value"=>$this->training_program)),
	  																						  1=>array(	"operator"=>"FIELD_LIMIT",
																										  	"field"=>"course_type",
																											"style"=>"equals",
																											"data"=>array("value"=>"course_type|core"))
																													 )
																							  ),
																  1=>array(	"operator"=>"AND",
																				"operand"=>array(
																							  0=>array(	"operator"=>"FIELD_LIMIT",
																											"field"=>"training_institution",
																											"style"=>"equals",
																											"data"=>array("value"=>$this->training_institution)),
																							  1=>array( "operator"=>"FIELD_LIMIT",
																											"field"=>"course_type",
																											"style"=>"equals",
																											"data"=>array("value"=>"course_type|general_education"))
																									 )
																			 )
										))));
		$courses=I2CE_FormStorage::listFields("training",array("id"),false,$where);
		
		//load exempted courses
		$exempted_courses=IHS_PageFormEnrollcourse::load_exempted_courses($this->student_registration["id"],$this->person_id);
		foreach($courses as $course) {
			$course="training|".$course["id"];
			$courseObj=$this->factory->createContainer($course);
			$courseObj->populate();
			//if this is a general education course and it is a prerequisite of other courses then add it,otherwise ignore it
			$course_type=$courseObj->getField("course_type")->getDBValue();
			$isprerequisite=self::isPrerequisite($course,$this->training_program,$this->training_institution);
			if($course_type=="course_type|general_education" and !$isprerequisite)
			continue;
			if(!in_array($course,$enrolled_courses) and !in_array($exempted_courses)) {
				$pending_courses[]=$course;
				}
			}
		return $pending_courses;
		}
		
	protected function checkAttempt($course_id) {
		$where=array("operator"=>"AND","operand"=>array(
																			0=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"training",
																						"style"=>"equals",
																						"data"=>array("value"=>$course_id)),
																			1=>array("operator"=>"NOT",
																						"operand"=>array(
																										0=>array("operator"=>"FIELD_LIMIT",
																													"field"=>"semester",
																													"style"=>"equals",
																													"data"=>array("value"=>$this->curr_semester))
																											 )),
																			2=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"registration",
																						"style"=>"equals",
																						"data"=>array("value"=>$this->student_registration["id"]))
																		));
		$results=I2CE_FormStorage::listFields("students_results_grade",array("attempt"),false,$where);
		$attempt=0;
		foreach($results as $result) {
			if($attempt<$result["attempt"])
			{
			$attempt=$result["attempt"];
			}
		}	
		return $attempt;
		}
	
	protected function getDiscoForm() {
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$this->person_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"]))
													));
		$disco=I2CE_FormStorage::Search("discontinued",false,$where);
		if(count($disco)>0)
		return "discontinued|".$disco[0];
		else
		return "discontinued";
	}
	
	protected function addToDiscontinued($reason,$discoForm) {
	if(!($discoObj=$this->ff->createContainer($discoForm)) instanceof I2CE_Form) {
			I2CE::raiseError("invalid Object");
			return false;
		}
		$discoObj->populate();
		$id=explode("|",$discoForm);
		if(count($id)==2) {
			$id=$id[1];
			$fields=I2CE_FormStorage::lookupField("discontinued",$id,array("reason"),false);
			$reason=$reason.",".$fields["reason"];
			$reasons_array=explode(",",$reason);
			$reasons=array_unique($reasons_array);
			$reason=implode(",",$reasons);
			}
			
		//checking to see if this student was previously discontinued on this program category,if so then this is Fail & Exclude
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$this->person_id)),
												  1=>array(	"operator"=>"NOT",
												  				"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
												  													"field"=>"registration",
												  													"style"=>"equals",
												  													"data"=>array("value"=>$this->student_registration["id"]))))
												 ));
		$disco=I2CE_FormStorage::listFields("discontinued",array("registration"),false,$where);
		$counter=0;
		foreach($disco as $disc) {
			$registration=$disc["registration"];
			$regObj=$this->factory->createContainer($registration);
			$regObj->populate();
			$program=$regObj->getField("training_program")->getDBValue();
			$progObj=$this->factory->createContainer($program);
			$progObj->populate();
			$program_category=$progObj->getField("program_category")->getDBValue();
			//$counter is checking the number of Disco for the same prog categ,if even number is found then it is FD id odd then it is FE
			if($program_category==$this->program_category) {
				$counter++;
				}
			}
		if(($counter>0 and $counter % 2==0) or $counter==0)
		$recommendations="recommendations|FD";
		else if($counter>0 and $counter % 2!=0)
		$recommendations="recommendations|FE";
		
		$discoObj->getField("registration_number")->setFromPost($this->registration_number);
		$date_disco=date("Y-m-d");
		$discoObj->getField("date_discontinued")->setFromDB($date_disco);
		$discoObj->getField("disco_reason")->setFromDB($reason);
		$discoObj->getField("recommendations")->setFromDB($recommendations);
		$discoObj->getField("registration")->setFromDB($this->student_registration["id"]);
		$discoObj->getField("parent")->setFromPost($this->person_id);
		$discoObj->getField("academic_year")->setFromDB($this->academic_year_id);
		$discoObj->save($this->user);
	}
	
	protected function getGPAObj() {
		//get semester to which this course a student has enrolled
		$enrollObj=$this->factory->createContainer($this->enroll_course);
		$enrollObj->populate();
		$semester=$enrollObj->getField("semester")->getDBValue();
	   $where = array('operator'=>'AND','operand'=>array(
   																		0=>array('operator' => 'FIELD_LIMIT',
        	      																	'field' => 'parent',
																						'style' => 'equals',
        	      																	'data' => array('value' => $this->person_id)),
        	      														1=>array('operator'=> 'FIELD_LIMIT',
        	      																	'field' => 'semester',
        	      																	'style' =>'equals',
        	      																	'data' => array('value' => $semester)),
																			2=>array('operator'=>'FIELD_LIMIT',
																						'field'=>'registration',
																						'style'=>'equals',
																						'data'=>array('value'=>$this->student_registration['id']))
        	      													  ));
	$GPA_array = I2CE_FormStorage::search('semester_GPA',false,$where);
	if(count($GPA_array)==0)
	return false;
	foreach($GPA_array as $id)
	return "semester_GPA|".$id;
	}
	
	protected function getStatus($total_marks) {
		if($total_marks=="I" or $total_marks=="i")
		$status="status|incomplete";
		else if($total_marks>=$this->passing_score and $total_marks<=100)
		$status="status|pass";
		else if($total_marks>=0 and $total_marks<$this->passing_score)
		$status="status|fail";
		return $status;
		}
	
	protected function calculateGrade($total_marks) {
		if($total_marks=="I" or $total_marks=="i") {
			$this->points=0;
			$grade="I";
			}
		else if($total_marks>=90 and $total_marks<=100) {
			$this->points=5;
			$grade="A+";
		}
		else if($total_marks>=85 and $total_marks<=89.9) {
			$this->points=4.9;
			$grade="A";
		}
	else if($total_marks>=80 and $total_marks<=84.9) {
			$this->points=4.7;
			$grade="A-";
		}
	else if($total_marks>=75 and $total_marks<=79.9) {
			$this->points=4.5;
			$grade="B+";
		}
	else if($total_marks>=70 and $total_marks<=74.9) {
			$this->points=4;
			$grade="B";
		}
	else if($total_marks>=65 and $total_marks<=69.9) {
			$this->points=3.5;
			$grade="B-";
		}
	else if($total_marks>=60 and $total_marks<=64.9) {
			$this->points=3;
			$grade="C+";
		}
	else if($total_marks>=55 and $total_marks<=59.9) {
			$this->points=2.5;
			$grade="C";
		}
	else if($total_marks>=50 and $total_marks<=54.9) {
			$this->points=2;
			$grade="C-";
		}
	else if($total_marks>=45 and $total_marks<=49.9) {
			$this->points=1.5;
			$grade="D+";
		}
	else if($total_marks>=40 and $total_marks<=44.9) {
			$this->points=1;
			$grade="D";
		}
	else if($total_marks>=35 and $total_marks<=39.9) {
			$this->points=0.5;
			$grade="D-";
		}
	else if($total_marks>=0 and $total_marks<=34.9) {
			$this->points=0;
			$grade="E";
		}
	
	return $grade;
	}
	
	protected function totalMarks($exam_types) {
		$this->clinical_mark="";
		$this->theory_mark="";
		foreach ($exam_types as $exam_type) {
			$result=$this->check_assessment_mark($exam_type);
			//if results available into the database,compare with the one submitted to see if changed
			if($result != $this->get_mark_inweight($exam_type,$this->post($exam_type."/".$this->reg_id)))
			$result = $this->get_mark_inweight($exam_type,$this->post($exam_type."/".$this->reg_id));
			
			//if has incomplete,total mark should be I
			if($result=="i" or $result=="I") {
				return "I";
				}

			if($exam_type=="training_course_exam_type|clinical") {
					$this->clinical_mark=$this->clinical_mark+$result;
					}
			else {
				$this->theory_mark=$this->theory_mark+$result;
				}
			$total_marks=$total_marks+$result;			
		}
	if($this->theory_mark>0)
	$this->theory_mark=number_format($this->theory_mark,1);
	if($this->clinical_mark>0)
	$this->clinical_mark=number_format($this->clinical_mark,1);
	$total_marks=number_format($total_marks,1);
	return $total_marks;
	}
	
	protected function check_assessment_mark($exam_type) {
		$this->set_assessment_weight();
		$mark=-1;
		$resultsObj=$this->ff->createContainer($this->students_results_grade_form);		
		$resultsObj->populateChildren("students_results");
		foreach($resultsObj->getChildren("students_results") as $results) {
			$assessment=$results->getFIeld("training_course_exam_type")->getDBValue();
			if($assessment==$exam_type) {
				$mark=$results->getFIeld("score")->getDBValue();
				if($mark==-1) {
					$mark="I";
					return $mark;
					}
				//convert % mark to assessment weight
				$weight=$this->assessment_weight[$exam_type];
				$mark=$mark*$weight/100;
				}
			}
			return $mark;	          
	}
	
	protected function get_mark_inweight($exam_type,$mark) {
		if($mark=="i" or $mark=="I")
		return "I";
		$mark=number_format($mark,1);
		$this->set_assessment_weight();
		$weight=$this->assessment_weight[$exam_type];
		$mark=$mark*$weight/100;
		return $mark;
		}
		
	protected function set_assessment_weight() {
		$courseObj=$this->factory->createContainer($this->course_id);
		$courseObj->populate();
		$assessments=$courseObj->getField("training_course_exam_type")->getDBValue();
		$assessments=explode(",",$assessments);
		foreach($assessments as $assessment) {
			$assess=explode("|",$assessment);
			$assess=$assess[1];
			$this->assessment_weight[$assessment]=$courseObj->getField($assess)->getDBValue();
			}
		}
	protected function calculateGPA() {
		$enrollObj=$this->factory->createContainer($this->enroll_course);
		$enrollObj->populate();
		$semester=$enrollObj->getField("semester")->getDBValue();
		$this->loadEnrolledCourses($semester);
		if($this->allResultsLoaded()) {
			return true;
			}
		return false;
		}

	protected function loadEnrolledCourses($semester) {
		$where = array(	'operator'=>'AND',
								'operand'=>array(0=>array(	'operator'=>'FIELD_LIMIT',
	                      									'field'=>'parent',
	                      									'style'=>'equals',
	                      									'data'=>array('value'=>$this->person_id)),
	                    						  1=>array(	'operator'=>'FIELD_LIMIT',
	                          								'field'=>'semester',
	                          								'style'=>'equals',
	                          								'data'=>array('value'=>$semester)),
													  2=>array(	'operator'=>'FIELD_LIMIT',
																	'field'=>'registration',
																	'style'=>'equals',
																	'data'=>array('value'=>$this->student_registration["id"]))
	                                     ));
		$courses= I2CE_FormStorage::listFields("enroll_course",array("training"),false,$where);
		foreach ($courses as $course) {
			$course_array=explode(",",$course["training"]);
			foreach($course_array as $course)
				$this->enrolled_courses[]=$course;
			}
		return $this->enrolled_courses;
	}
	
	protected function current_semester_results($course) {
		$where=array("operator"=>"AND","operand"=>array(
																 			0=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"parent",
																						"style"=>"equals",
																						"data"=>array("value"=>$this->person_id)),
																			1=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"enroll_course",
																						"style"=>"equals",
																						"data"=>array("value"=>$this->enroll_course)),
																			2=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"training",
																						"style"=>"equals",
																						"data"=>array("value"=>$course)),
																			3=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"registration",
																						"style"=>"equals",
																						"data"=>array("value"=>$this->student_registration["id"]))
																		));
		$results=I2CE_FormStorage::ListFields("students_results_grade",array("total_marks"),false,$where);
		$mark="no results";
		foreach($results as $result) {
			$mark=$result["total_marks"];
			//return false if mark is incomplete
			if($mark==-1) {
				$mark=-1;
				break;
				}
			}
		return $mark;
		}
		
	protected function allResultsLoaded() {
		foreach ($this->enrolled_courses as $course) {
			$total_course_marks=$this->current_semester_results($course);
			//-1 means incomplete
			if($total_course_marks=="no results" or $total_course_marks==-1)
			return false;
			$grade=$this->calculateGrade($total_course_marks);
			$total_course_marks=null;
			$courseObj = $this->ff->createContainer($course);
			$courseObj->populate();
			$credits=$courseObj->getField('course_credits')->getValue();
			$quality_points=$credits*$this->points;
			$total_quality_points=$total_quality_points+$quality_points;
			$total_credits=$total_credits+$credits;
			}
				$this->GPA=number_format($total_quality_points/$total_credits,3);
				return true;
		}
	
	static function getCourseHighestMark($course_id,$registration) {
		$where=array("operator"=>"AND","operand"=>array(
																			0=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"training",
																						"style"=>"equals",
																						"data"=>array("value"=>$course_id)),
																			1=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"registration",
																						"style"=>"equals",
																						"data"=>array("value"=>$registration))
																		));
		$results=I2CE_FormStorage::ListFields("students_results_grade",array("total_marks","status"),false,$where);
		$mark=null;
		foreach($results as $result) {
			//if total marks is missing then this course has no all results,return false
			if($mark<$result["total_marks"])
			$mark=$result["total_marks"];
			}
			return $mark;
	}
	
	protected function save() {
		$this->userMessage("Results Entered Successfully!!!");
		$this->setRedirect("add_results_select_course");
		}	
	}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
