<?php
/**
* © Copyright 2009 IntraHealth International, Inc.
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
* Class IHS_Module_StudentsViewResults
* 
* @access public
*/


	class IHS_Module_StudentsViewResults extends I2CE_Module {
	
	    public static function getMethods() {
	        return array(
	            'iHRIS_PageView->action_students_results_grade' => array('priority'=>250,'method'=>'action_students_results_grade'),
	            'iHRIS_PageView->action_overall_GPA' => 'action_overall_GPA'
	            );
	    }
	
	protected $person_id;
	protected $program;
	protected $curr_semester;
	protected $enrolled_courses=array();
	protected $template;
	
	public function action_overall_GPA ($page) {
		if (!$page instanceof iHRIS_PageView) {
            return false;
        }
        return $page->addChildForms('overall_GPA','siteContent');
		}
		
	public function action_students_results_grade($page) {
    	$this->ff = I2CE_FormFactory::instance();
      if (!$page instanceof iHRIS_PageView) {
      	
         return false;
			}
		$this->template = $page->getTemplate();
		$this->person_id=$page->getPerson()->getNameId();
		$this->persObj=$this->ff->createContainer($this->person_id);
		$this->manual_constructor();
		$this->getEnrolledCourses();
		$no_results=true;
		//start displaying results
		foreach($this->enrolled_courses as $id=>$courses) {
			$semester_node = $this->template->appendFileById("student_view_results_table.html", "div", "students_results");
			$results_available=$this->displayResults(
																	  $this->enrolled_semesters[$id],
																	  $this->enrolled_academic_years[$id],
																	  $courses,$semester_node,$id
																	 );
			if($no_results==true and $results_available>0)
				$no_results=false;
			}
	
		}
	
	protected function manual_constructor() {
		$this->student_registration=STS_PageFormPerson::load_current_registration($this->person_id);
		$this->program=$this->student_registration["training_program"];
		$this->curr_semester=$this->student_registration["semester"];
		$this->reg_num=$this->student_registration["registration_number"];
		}
	
	protected function getEnrolledCourses() {
		$semester_name=$this->getSemesterName($this->curr_semester);
		for($semester=$semester_name;$semester>0;$semester--) {
			$semester_name="semester|".$semester;
				$this->persObj->populateChildren("enroll_course");
				foreach($this->persObj->getChildren("enroll_course") as $enrollcourseObj) {
					if($enrollcourseObj->getField("semester")->getDBValue()==$semester_name and $enrollcourseObj->getField("registration")->getDBValue()==$this->student_registration["id"]) {
						$id=$enrollcourseObj->getField("id")->getDBValue();
						$this->enrolled_courses[$id]=$enrollcourseObj->getField("training")->getDBValue();
						$this->enrolled_semesters[$id]=$enrollcourseObj->getField("semester")->getDBValue();
						$this->enrolled_academic_years[$id]=$enrollcourseObj->getField("academic_year")->getDBValue();
					}
					}
				}
		}
	
	protected function getSemesterName($semester) {
		$ff = I2CE_FormFactory::instance();
		$semObj=$ff->createContainer($semester);
		$semObj->populate();
		$semester=$semObj->getField("name")->getDBValue();		
		return $semester;
	}
	
	protected function getSemesterId($semester) {
		$where=array(
							"operator"=>"FIELD_LIMIT",
							"field"=>"name",
							"style"=>"equals",
							"data"=>array("value"=>$semester)
						 );
		$semester_id=I2CE_FormStorage::listFields("semester",array("id"),false,$where);
		foreach($semester_id as $semester)
		$semester=$semester["id"];
		return $semester;
	}
	
	protected function displayResults($semester,$academic_year,$courses,$semester_node,$enroll_id) {
		$display_GPA=true;
		$accObj=$this->ff->createContainer($academic_year);
		$accObj->populate();
		$acc_year_name=$accObj->getField("name")->getDBValue();
		$this->template->setDisplayDataImmediate( "student_results_header", "$acc_year_name Semester ".$this->getSemesterName($semester)." Results", $semester_node);		
		$counter=1;				
		$courses=explode(",",$courses);
		foreach ($courses as $course) {
			$row_node = $this->template->appendFileByName( "student_view_results_row.html", "tr", "student_results_rows", 0, $semester_node);
			$mark=0;
			$status="";
			$grade="";
			$recommendations="";
			$this->template->setDisplayDataImmediate( "results_row_counter", $counter, $row_node );
			list($course_form,$course_id) = array_pad(explode("|", $course,2),2,'');
			$field_data = I2CE_FormStorage::lookupField($course_form,$course_id,array('name','code','training_course_exam_type'),false);
			$this->template->setDisplayDataImmediate( "results_row_code", $field_data["code"], $row_node );
			$this->template->setDisplayDataImmediate( "results_row_name", $field_data["name"], $row_node );
			$exam_types_array=explode(",",$field_data["training_course_exam_type"]);
			
			//arranging exam types according to what IHS wants
			$tests=array();
			$final=array();
			$assessments=array();
			foreach($exam_types_array as $id=>$exam_type) {
				$pos=strpos($exam_type,"test");
				if($pos!==false) {
					$tests[]=$exam_type;
					unset($exam_types_array[$id]);
					}
				}
	
			foreach($exam_types_array as $id=>$exam_type) {
				$pos=strpos($exam_type,"final");
				if($pos!==false) {
					$final[]=$exam_type;
					unset($exam_types_array[$id]);
					}
				}
			$assessments=array_merge($tests,$exam_types_array);
			$assessments_array=array_merge($assessments,$final);
			$exam_types_array=array();
			$exam_types_array=$assessments_array;

			$has_final_exam=$this->has_final_exam($course);
			//if course has final exam,check if is approved,otherwise it is approved
			if($has_final_exam) {
				$is_approved=$this->is_approved($academic_year,$course);
				if(!$is_approved) {
					$display_GPA=false;
					}
				}
			else {
				$is_approved=true;
				}
			
			foreach($exam_types_array as $exam_type) {
				$total_marks="";
				$this->persObj->populateChildren("students_results_grade");
				$theory_mark="";
				$clinical_mark="";
				foreach($this->persObj->getChildren("students_results_grade") as $resultsObj) {
					$training=$resultsObj->getField("training")->getDBValue();
					if($resultsObj->getField("enroll_course")->getDBValue() != $enroll_id or $training!=$course)
					continue;
					$results_acc_year=$resultsObj->getField("academic_year")->getDBValue();						
					$recommendations=$resultsObj->getField("recommendations")->getDisplayValue();
					$theory_mark=$resultsObj->getField("theory_mark")->getDBValue();
					$clinical_mark=$resultsObj->getField("clinical_mark")->getDBValue();
					
					//check if this course has clinical component,if not then dont show clinical mark
					$courseObj=$this->ff->createContainer($course);
					$courseObj->populate();
					$assessments=$courseObj->getField("training_course_exam_type")->getDBValue();
					$assessments_array=explode(",",$assessments);
					if(!in_array("training_course_exam_type|clinical",$assessments_array))
					$clinical_mark="Non Clinical Course";
					if(in_array("training_course_exam_type|clinical",$assessments_array) and count($assessments_array)==1)
					$theory_mark="No Theory Component";
					$attempt=$resultsObj->getField("attempt")->getDisplayValue();
					$status=$resultsObj->getField("status")->getDisplayValue();
					$where=array(	"operator"=>"FIELD_LIMIT",
										"field"=>"registration",
										"style"=>"equals",
										"data"=>array("value"=>$this->student_registration["id"])
									 );
					$disco=I2CE_FormStorage::listFields("discontinued",array("status"),false,$where);
					if(count($disco)>0) {
						foreach($disco as $disc) {
							$recommendations=$disc["recommendations"];
							$recObj=$this->factory->createContainer($recommendations);
							$recObj->populate();
							$recommendations=$recObj->getField("name")->getDBValue();
							}
						}
						
					if($recommendations=="Fail And Discontinue") {
						$semester_status_array[]="Fail And Discontinue";
						}
					if($recommendations=="Fail And Exclude") {
						$semester_status_array[]="Fail And Exclude";
						}
						
					if($recommendations=="Academic Warning") {
						$status=$status." (AW)";
						$semester_status_array[]="Proceed On Academic Warning";
						}
					else if($recommendations=="Academic Probation") {
						$status=$status." (AP)";
						$semester_status_array[]="Proceed On Academic Probation";
						}
					else if($recommendations=="Incomplete") {
						$status=$status;
						$semester_status_array[]="Proceed-Incomplete";
						}
					else if($recommendations=="Proceed") {
						$semester_status_array[]="Proceed";
						}
					$grade=$resultsObj->getField("grade")->getDBValue();
					$total_marks=$resultsObj->getField("total_marks")->getDBValue();
					$id=$resultsObj->getField("id")->getDBValue();
					$assessparentObj=$this->ff->createContainer($id);
					$assessparentObj->populateChildren("students_results");
					foreach($assessparentObj->getChildren("students_results") as $assessObj) {
						$mark=$assessObj->getField("score")->getDBValue();
						if($mark==-1)
						$mark="I";
						$assessment=$assessObj->getField("training_course_exam_type")->getDBValue();
						if($assessment!=$exam_type)
						continue;
						if($mark=="")
							$mark="-";
						if($mark!="" and $mark!="I")
						$mark=number_format($mark,1);
						$examtypeObj=$this->ff->createContainer($exam_type);
						$examtypeObj->populate();
						$exam_type_name=$examtypeObj->getField("name")->getDBValue();
						$type_node = $this->template->appendFileByName( "student_view_results_row_exam_types.html", "div", "results_row_exam_types", 0, $row_node );
						if($exam_type=="training_course_exam_type|final" and !$is_approved)
						$mark="-";
						$this->template->setDisplayDataImmediate( "exam_types_name", $exam_type_name.": ".$mark, $type_node );					
						}
					}			
			}
			if($recommendations=="")
			$recommendations="-";
			if($grade=="")
			$grade="-";
			if($total_marks=="")
			$total_marks="-";
			if(!$is_approved and ($grade!="-" or $total_marks!="-")) {
				$recommendations="-";
				$grade="-";
				$total_marks="Waiting Approval";
				$theory_mark="-";
				$clinical_mark="-";
				$status="-";
				}
			else if($attempt>1) {
				$total_marks=$total_marks." R";
				}
				
				if($theory_mark>0)
				$theory_mark=number_format($theory_mark,1);
				if($clinical_mark>0)
				$clinical_mark=number_format($clinical_mark,1);
				if($total_marks>0)
				$total_marks=number_format($total_marks,1);
				$this->template->setDisplayDataImmediate( "results_row_theory", $theory_mark, $row_node );
				$this->template->setDisplayDataImmediate( "results_row_clinical", $clinical_mark, $row_node );
				$this->template->setDisplayDataImmediate( "results_row_total", $total_marks, $row_node );
				$this->template->setDisplayDataImmediate( "results_row_grade", $grade, $row_node );
				$this->template->setDisplayDataImmediate( "results_row_status", $status, $row_node );
			$counter++;
		}
			$this->persObj->populateChildren("semester_GPA");
			foreach($this->persObj->getChildren("semester_GPA") as $semGPAObj) {
				if($semGPAObj->getFIeld("semester")->getDBValue()==$semester and 
					$semGPAObj->getField("registration")->getDBValue()==$this->student_registration["id"]) {
					$GPA=$semGPAObj->getField("GPA")->getDBValue();
					}
				}
		if($GPA=="")
		$GPA="-";
		if(!$display_GPA) {
			$GPA="-";
			}
		if(in_array("Fail And Discontinue",$semester_status_array))
		$semester_status="Fail And Discontinue (FD)";
		else if(in_array("Fail And Exclude",$semester_status_array))
		$semester_status="Fail And Exclude (FE)";		
		else if(in_array("Proceed On Academic Probation",$semester_status_array))
		$semester_status="Proceed On Academic Probation (AP)";
		else if(in_array("Proceed On Academic Warning",$semester_status_array))
		$semester_status="Proceed On Academic Warning (AW)";
		else if(in_array("Proceed-Incomplete",$semester_status_array))
		$semester_status="Proceed-Incomplete";
		else if(in_array("Proceed",$semester_status_array))
		$semester_status="Proceed (P)";
		$this->template->setDisplayDataImmediate( "semester_status","Semester Status:".$semester_status,$semester_node);
		$this->template->setDisplayDataImmediate( "semester_gpa", "Semester ".$this->getSemesterName($semester)." GPA ". $GPA ,$semester_node);		
		$this->display_undone_courses($enroll_id);
		return $counter;
	}
	
	protected function is_approved($results_academic_year,$course) {
		$academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($academic_year);
		$academic_year_id="academic_year|".$academic_year_id;
		if($results_academic_year!=$academic_year_id) {
			return true;
			}
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"academic_year",
																"style"=>"equals",
																"data"=>array("value"=>$academic_year_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"like",
																"data"=>array("value"=>"%".$course."%")),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training_institution",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["training_institution"])),
												 ));
		$is_approved=I2CE_FormStorage::listFields("results_approval",array("training"),false,$where);
		if(count($is_approved)>0) {
			foreach($is_approved as $approved) {
				$approved_courses=explode(",",$approved["training"]);
				if(in_array($course,$approved_courses))
				return true;
				}
			}
		return false;													
		}
		
	protected function has_final_exam($course) {
		$ff = I2CE_FormFactory::instance();
		if(!($courseObj=$ff->createContainer($course)) instanceof iHRIS_Training)
		return;
		$courseObj->populate();
		$assessment=$courseObj->getField("training_course_exam_type")->getDBValue();
		$assessments=explode(",",$assessment);
		if(in_array("training_course_exam_type|final",$assessments)) {
			return true;
			}
		return false;
		}
		
	protected function display_undone_courses($enroll_id) {
		$pending=array();
		$retaking=array();
		$incomplete=array();
		$exempted=array();
		$enrollObj=$this->ff->createContainer($enroll_id);
		$enrollObj->populate();
		$trainings=$enrollObj->getField("training")->getDBValue();
		$semester=$enrollObj->getField("semester")->getDBValue();
		$enrolled_courses=explode(",",$trainings);
		//get failed courses for this enrollment
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"enroll_course",
																"style"=>"equals",
																"data"=>array("value"=>$enroll_id)),
												  1=>array(	"operator"=>"OR",
												  				"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
												  												  	"field"=>"status",
												  												  	"style"=>"equals",
												  												  	"data"=>array("value"=>"status|incomplete")),
												  									  1=>array(	"operator"=>"FIELD_LIMIT",
																									"field"=>"status",
																									"style"=>"equals",
																									"data"=>array("value"=>"status|fail")))
												 )));
												 
		$failed_courses=I2CE_FormStorage::listFields("students_results_grade",array("training","status"),false,$where);
		if(count($failed_courses)>0) {
			foreach($failed_courses as $course) {
				$courseObj=$this->ff->createContainer($course["training"]);
				$courseObj->populate();
				if($course["status"]=="status|fail")
				$retaking[]=$courseObj->code;
				else if($course["status"]=="status|incomplete")
				$incomplete[]=$courseObj->code;
				}
			}
		//get core courses that this student didnt register
		$program=$this->student_registration["training_program"];
		$institution=$this->student_registration["training_institution"];
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
																											"data"=>array("value"=>$program)),
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
																											"data"=>array("value"=>$institution)),
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
			$courseObj=$this->ff->createContainer($course);
			$courseObj->populate();
			$isprerequisite=IHS_PageFormAddResultsProcess::isPrerequisite($course,$program,$institution);
			$course_type=$courseObj->getField("course_type")->getDBValue(); 
			if(!in_array($course,$enrolled_courses) and !in_array($exempted_courses)) {
				$pending[]=$courseObj->code;
				}
			else if(!in_array($course,$enrolled_courses) and in_array($exempted_courses)) {
				$exempted[]=$courseObj->code;
				}
			}

		list($form,$joined_sem)=explode("|",$this->student_registration["joined_semester"]);
		if($joined_sem>1 and $this->student_registration["joined_semester"]==$semester) {
			$all_undone=array_merge($retaking,$incomplete,$pending,$exempted);
			$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"])),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["joined_semester"]))
												 ));
			$pending_list=I2CE_FormStorage::listFields("pending_courses",array("training"),false,$where);
			foreach($pending_list as $list) {
				$courses=explode(",",$list["training"]);
				foreach($courses as $course) {
					if(!in_array($course,$all_undone)) {
						$courseObj=$this->ff->createContainer($course);
						$courseObj->populate();
						$pending[]=$courseObj->code;
						}
					}
				}
			}
			
		$retaking=implode(",",$retaking);
		$incomplete=implode(",",$incomplete);
		$pending=implode(",",$pending);
		$exempted=implode(",",$exempted);
		if($retaking or $incomplete or $pending or $exempted) {
			if($pending=="")
			$pending="NO";
			if($retaking=="")
			$retaking="NO";
			if($incomplete=="")
			$incomplete="NO";
			if($exempted=="")
			$exempted="NO";
			$undone_node = $this->template->appendFileById("student_view_results_table_pending.html", "div", "students_results");
			$row_node = $this->template->appendFileByName( "student_undone_courses_row.html", "tr", "student_results_rows", 0, $undone_node);
			$this->template->setDisplayDataImmediate( "undone_row_pending",$pending,$row_node );
			$this->template->setDisplayDataImmediate( "undone_row_retake",$retaking,$row_node );
			$this->template->setDisplayDataImmediate( "undone_row_exempted",$exempted,$row_node );
			$this->template->setDisplayDataImmediate( "undone_row_incomplete",$incomplete,$row_node );
			}
		}
	}

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
