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
	class IHS_PageFormEnrollcourse extends I2CE_PageForm {
	protected $subject_courses=array();
	protected $elective_courses=array();
	static $failed_prerequisite=array();
	static $curr_semester;
	static $curr_level;
	static $program;
	static $date_registered;
	static $training_institution;
	static $person_id;
	static $joined_semester;
	static $admission_type;
	static $passing_score;
	static $total_semesters;
	static $student_registration=array();
	static $exempted_courses=array();
	
	protected function action() {
		$role=$this->getUser()->role;
		if($role!="student" and $role!="hod") {
	     $this->userMessage("Only A Student/Head Of Department Can Enroll Into Courses");
	     $this->setRedirect(  "view?id=" . $this->Get("parent") );
	     return false;     	
		}

	iHRIS_AcademicYear::ensureAcademicYear();
	self::$person_id=$this->Get("parent");
	self::manual_constructor(self::$person_id);
	
	############Deny Course Registration For A Student Dropped Out Of Semester###################
	$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>self::$person_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>self::$student_registration["id"])),
												 ));
		
		$drp_sem=I2CE_FormStorage::search("drop_semester",false,$where);
		if(count($drp_sem)>0) {
			foreach($drp_sem as $drp_id) {
				$drpObj=$this->factory->createContainer("drop_semester|".$drp_id);
				$drpObj->populateChildren("resume_semester");
				$resumed=false;
				foreach ($drpObj->getChildren("resume_semester") as $resObj) {
					$resumed=true;
					}
				if(!$resumed) {
					$this->userMessage("You Are Currently Dropped From A Semester,Course Enrollment Not Allowed");
      			$this->setRedirect(  "view?id=" . self::$person_id );
      			return;
					break;
					}
				}
			}
	############End Of Denying Course Registration For A Student Dropped Out Of Semester###################
	
	############Deny Course Registration For Completed Students###########
	if(self::completed_school(self::$curr_semester,self::$total_semesters,self::$person_id)) {
		$this->userMessage("You Have Completed The Program");
      $this->setRedirect("view?id=" . self::$person_id);
      return false;
		}
	############End Of Denying Course Registration For Completed Students###########
	
	############checking if course enrollment closed#################
	$username=$this->getUser()->username;
	$training_institution=IHS_PageFormLecturer::fetch_institution($username);
	$where=array(	"operator"=>"FIELD_LIMIT",
						"field"=>"training_institution",
						"style"=>"equals",
						"data"=>array("value"=>$training_institution));
	$fields=I2CE_FormStorage::listFields("schedule_course_enrollment",array("start_date","end_date"),false,$where);
	foreach($fields as $id=>$field) {
		$start_date=$field["start_date"];
		$end_date=$field["end_date"];
	}
	
	if(count($fields)==0) {
		$this->userMessage("Course Registration Closed");
		$this->setRedirect("view?id=" . self::$person_id);
		return false;	
	}
	
	else {
		$start_date=strtotime($start_date);
		$end_date=strtotime($end_date);
		$today=strtotime(date("Y-m-d"));
		if($today>$end_date) {
			$this->userMessage("Course Registration Closed");
			$this->setRedirect("view?id=" . self::$person_id);
			return false;
		}
	}
	########### End checking of course enrollment deadline #####################
	
	########### Check if this student is not discontinued  #####################
	if(self::check_discontinue(self::$person_id,self::$student_registration["id"])) {
		$this->userMessage("You have discontinued from this program!!!");
		$this->setRedirect( "view?id=" . self::$person_id);
		return false;
	}
	########### End of checking if a student has discontinued #################
	
	########### If its a new semester then increment the semester and level ################
	self::increment_semester(self::$person_id);
	
	
	
	/*
	**Automatically Enroll This Student To All Core Courses
	*/
	$current_semester=explode("|",self::$curr_semester);
	$current_semester=$current_semester[1];
	if($current_semester<=self::$total_semesters and !self::GPA_exist(self::$person_id,self::$curr_semester,self::$student_registration["id"])) {
		//get the number of semesters ellapsed since the previous enrollment
		//$this->semester_ellapsed ("semester|".($current_semester-1));
		//$semester=$current_semester-1+$this->sem;
		$month=date("m");
		if((($month>=1 and $month<=5) and $current_semester % 2!=0) or (($month>=7 and $month<=12) and $current_semester % 2==0)) {
			$this->userMessage("Sorry,Course Registration Is Not Allowed For Now Until The New Semester Begins");
			$this->setRedirect("view?id=" . self::$person_id);
			return false;
			}
			
		else if((($month>=1 and $month<=6) and $current_semester % 2==0) or (($month>=7 and $month<=12) and $current_semester % 2!=0)) {
			self::enroll_core_courses(self::$person_id);
			$this->getProgramCourses(self::$curr_semester);
			$this->getElectiveCourses(self::$curr_semester);
			$this->getPreviousSemesterCourses(self::$curr_semester);
			}
		}
	else	{
		$this->semester_ellapsed(self::$curr_semester);
		$semester=$current_semester+$this->sem;
		$this->getPreviousSemesterCourses("semester|".$semester);
		if($current_semester<self::$total_semesters) {
			$this->userMessage("Sorry,Course Registration Not Allowed For Now Until Next Semester!!!");
			$this->setRedirect( "view?id=" . self::$person_id);
			return false;
			}
		}
/*
	if(count($this->subject_courses)==0 and count($this->elective_courses)==0) {
		$this->userMessage("No courses defined into the system,try later on!!!");
		$this->setRedirect( "view?id=" . self::$person_id);
		return false;
	}*/

	//check if the max number of credits reached and disable course registration
	$deny_registration=$this->check_max_credits();
	$dsply_deny_msg=$deny_registration;
	$this->displayCourses($this->subject_courses,"subject","Subject Courses","",$deny_registration,$dsply_deny_msg);
	$this->display_pending();
	if(count($this->elective_courses)>0)
	$this->displayCourses($this->elective_courses,"elective","Elective Courses","",$deny_registration,false);
	if(count(self::$failed_prerequisite)>0)
	$this->displayCourses(self::$failed_prerequisite,"failed","You are not allowed To enroll to these course(s) as you have failed their pre-requisites",true,$deny_registration,false);
	//append hidden values to be used on the onload
	    if (! ($hidden = $this->template->getElementByID("hidden_values")) instanceof DOMNode)
		return ;
	$input=$this->template->createElement("input",array("type"=>"hidden","name"=>"person_id","value"=>self::$person_id));
	$this->template->appendNode($input,$hidden);
	$input=$this->template->createElement("input",array("type"=>"hidden","name"=>"curr_semester","value"=>self::$curr_semester));
	$this->template->appendNode($input,$hidden);
	
	if (! ($div = $this->template->getElementByID("button")) instanceof DOMNode)
	return ;
	$input =$this->template->createElement("input",array("type"=>"submit","class"=>"submitCell","id"=>"button","value"=>"Save"));
	$this->template->appendNode($input,$div);	
	}
		
	protected function semester_ellapsed ($current_semester) {
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>self::$person_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$current_semester)),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>self::$student_registration["id"]))
												 ));
		$enrolled_academic=I2CE_FormStorage::listFields("enroll_course",array("academic_year","date_enrolled","semester"),false,$where);
		if(count($enrolled_academic)==0) {
			return;			
			}
		foreach($enrolled_academic as $academic_year) {
			$enrolled_academic_year=$academic_year["academic_year"];
			$enrolled_date=$academic_year["date_enrolled"];
			$semester=$academic_year["semester"];
			}
		$GPA_exist=self::GPA_exist(self::$person_id,$semester,self::$student_registration["id"]);
		if(!$GPA_exist) {
			$this->sem=0;
			return;
			}

		$ac_year1=explode("/",$current_academic_year);
		$ac_year1=$ac_year1[0];
		$acYrObj=$this->factory->createContainer($enrolled_academic_year);
		$acYrObj->populate();
		$enrolled_ac_year=$acYrObj->getField("name")->getDBValue();
		$ac_year2=explode("/",$enrolled_ac_year);
		$ac_year2=$ac_year2[0];
		$ac_year_diff=$ac_year1-$ac_year2;
		
		$curr_month=date("m");
		$enrolled_month=explode("-",$enrolled_date);
		$enrolled_month=$enrolled_month[1];
		//check the number of semesters passed since discontinued
		if($current_academic_year!=$enrolled_ac_year) {
     		if($enrolled_month>=7 and $enrolled_month<=12) {
     			$this->sem=$this->sem+2;
     			}
     		else if($enrolled_month>=1 and $enrolled_month<=5) {
     			$this->sem=$this->sem+1;
     			}
     			
     		if($curr_month>=7 and $curr_month<=12) {
     			$this->sem=$this->sem+0;
     			}
     		else if($curr_month>=1 and $curr_month<=5) {
     			$this->sem=$this->sem+1;
     			}
     		$this->sem=$this->sem+(($ac_year_diff-1)*2);
     		}
     	else {
     		if(($enrolled_month>=7 and $enrolled_month<=12) and ($curr_month>=1 and $curr_month<=5)) {
     			$this->sem=1;
     			}
     		else
     		$this->sem=0;
     		}
		}

	static function GPA_exist($person_id,$semester,$registration) {
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$person_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$semester)),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$registration))
												 ));
		$GPA=I2CE_FormStorage::search("semester_GPA",false,$where);
		if(count($GPA)>0)
		return true;
		else if(self::completed_semester_withincomplete($semester,$registration))
		return true;
		else
		return false;
		}
		
	static function completed_semester_withincomplete($semester,$registration) {
		$where=array("operator"=>"AND","operand"=>array(
																 			0=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"registration",
																						"style"=>"equals",
																						"data"=>array("value"=>$registration)),
																			1=>array("operator"=>"FIELD_LIMIT",
																						"field"=>"semester",
																						"style"=>"equals",
																						"data"=>array("value"=>$semester))
																		));
		$enroll_course=I2CE_FormStorage::listFields("enroll_course",array("training"),false,$where);
		foreach($enroll_course as $enroll_id=>$courses) {
			$enrolled_courses=explode(",",$courses["training"]);
			}
		//start checking if all enrolled courses has results and there is atleast one incomplete
		$incomplete=false;
		foreach($enrolled_courses as $course) {
			$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"enroll_course",
																	"style"=>"equals",
																	"data"=>array("value"=>"enroll_course|".$enroll_id)),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"training",
																	"style"=>"equals",
																	"data"=>array("value"=>$course))
													 ));
			$results=I2CE_FormStorage::ListFields("students_results_grade",array("total_marks"),false,$where);
			if(count($results)==0) {
				return false;
				}
			else {
				foreach($results as $result) {
					$mark=$result["total_marks"];
					//return false if mark is incomplete
					if($mark==-1) {
						$incomplete=true;
						}
					}
				}
			}
		if($incomplete) {
			return true;
			}
		else
		return false;
		}
		
	static function load_exempted_courses($reg_id,$person_id) {
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$person_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$reg_id))
												 ));
		$exempted=I2CE_FormStorage::listFields("course_exemption",array("training"),false,$where);
		foreach($exempted as $exempt) {
			$courses=explode(",",$exempt["training"]);
			foreach ($courses as $course) {
				self::$exempted_courses[]=$course;
				}
			}
		return self::$exempted_courses;
		}
	
	static function completed_school($current_semester,$total_semesters,$person_id) {
		$current_semester=explode("|",$current_semester);
		$current_semester=$current_semester[1];
		if($current_semester >= $total_semesters) {
			if(self::passed_all_courses($person_id)) {
				return true;
				}
			}
		return false;
		}
		
	protected function check_max_credits() {
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$where=array(	"operator" => "AND",
							"operand" => array( 0 => array (	"operator"=>"FIELD_LIMIT",
																		"field"=>"semester",
																		"style"=>"equals",
																		"data"=>array("value"=>self::$curr_semester)),
													  1 => array (	"operator"=>"FIELD_LIMIT",
																		"field"=>"parent",
																		"style"=>"equals",
																		"data"=>array("value"=>self::$person_id)),
													  2 => array (	"operator"=>"FIELD_LIMIT",
																		"field"=>"registration",
																		"style"=>"equals",
																		"data"=>array("value"=>self::$student_registration["id"])),
													 ));
		$enrolled_courses=I2CE_FormStorage::listFields("enroll_course",array("training"),false,$where);
		foreach ($enrolled_courses as $enrolled) {
			$sem_enrolled_courses=explode(",",$enrolled["training"]);
			}
		foreach ($sem_enrolled_courses as $course) {
			$courseObj=$this->factory->createContainer($course);
			$courseObj->populate();
			$credits=$courseObj->getField("course_credits")->getDBValue();
			$total_credits=$total_credits+$credits;
			}			
		$progObj=$this->factory->createContainer(self::$program);
		$progObj->populate();
		if(self::$admission_type=="admission_type|full-time")
		$max_sem_credits=$progObj->getField("max_sem_credits_fulltime")->getDBValue();
		if(self::$admission_type=="admission_type|part-time")
		$max_sem_credits=$progObj->getField("max_sem_credits_parttime")->getDBValue();
		if($max_sem_credits <= $total_credits) {
			return true;		
			}
		else {
			return false;
			}
		}
	
	static function get_total_semesters($program,$admission_type) {
		list($prog_form,$prog_id)=array_pad(explode("|",self::$program,2),2,"");
		if($admission_type=="admission_type|full-time") {
			$total_sems=I2CE_FormStorage::lookupField("training_program",$prog_id,array("total_semesters_fulltime"),false);
			$total_semesters=$total_sems["total_semesters_fulltime"];
			}
			
		else if($admission_type=="admission_type|part-time") {
			$total_sems=I2CE_FormStorage::lookupField("training_program",$prog_id,array("total_semesters_parttime"),false);
			$total_semesters=$total_sems["total_semesters_parttime"];
			}
		return $total_semesters;		
		}
		
	static function increment_semester ($person_id,$allow_increment=false) {
		//$allow_increment parameter helps to control students who have gone beyond the required semesters
		//if $allow_increment is not set then this student still under normal semesters
		
		self::manual_constructor($person_id);
		
		$month=date("m");
		list($form,$semester)=explode("|",self::$student_registration["semester"]);
		if((($month>=7 and $month<=12) and $semester % 2!=0) or 
		 	 (($month>=1 and $month<=6) and $semester % 2==0))
			return self::$curr_semester;
			
		$current_semester=explode("|",self::$curr_semester);
		$current_semester=$current_semester[1];
		
		//ensure that if this student is beyond the normal program semesters then is allowed to increment semester
		$GPA_exist=self::GPA_exist($person_id,self::$curr_semester,self::$student_registration["id"]);
		if(($current_semester>self::$total_semesters or 
			($current_semester==self::$total_semesters and $GPA_exist)) and 
			!$allow_increment)
			return self::$curr_semester;
		 
		$ff=I2CE_FormFactory::instance();
		
		$regObj=$ff->createContainer(self::$student_registration["id"]);
		
		$total_semesters=self::get_total_semesters(self::$program,self::$admission_type);
		
		$semester_name=self::getSemesterName(self::$curr_semester);
		
		######if GPA for current semester available then increment the semester######
		$GPA_exist=self::GPA_exist($person_id,self::$curr_semester,self::$student_registration["id"]);
		if($GPA_exist) {
			list($form,$level)=array_pad(explode("|",self::$curr_level,2),2,"");
			$semester_name=++$semester_name;
			$month=date("m");
			######check to ensure that if this is the final semester of a level it is incremented only when a new academic year begins in July###
			$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
			$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
			$current_academic_year_id="academic_year|".$academic_year_id;
			//check if student not enrolled courses for this academic year and then increment semester
			$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"academic_year",
																	"style"=>"equals",
																	"data"=>array("value"=>$current_academic_year_id)),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"registration",
																	"style"=>"equals",
																	"data"=>array("value"=>self::$student_registration["id"]))
													  ));
													  
			$enrolled=I2CE_FormStorage::Search("enroll_course",false,$where);
			if($semester_name % 2!=0 and count($enrolled)>0)
			return self::$curr_semester;
			
			$month=date("m");
			
			//if a new semester is even then increment it when it is between January and May
			if($semester_name % 2==0 and ($month>=7 and $month<=12))
			return self::$curr_semester; 
			########End of checking last semester of a level########
			if($semester_name % 2!=0)
			$new_level=++$level;
			$new_semester="semester|".$semester_name;	
			$regObj->populate();
			$regObj->getField("semester")->setFromDB($new_semester);
			if($semester_name % 2!=0)
			$regObj->getField("academic_level")->setFromDB("academic_level|".$new_level);
			$user=new I2CE_User;
			$regObj->save($user);
			self::manual_constructor($person_id);
			return $new_semester;
		}
		else {
			return self::$curr_semester;
			}
		}
		
	static function enroll_core_courses($person_id) {
		$ff=I2CE_FormFactory::instance();
		self::manual_constructor($person_id);
		
		//make sure that students can enroll courses at a proper semester
		$month=date("m");
		list($form,$semester)=explode("|",self::$student_registration["semester"]);
		 if((($month>=7 and $month<=12) and $semester % 2==0) or 
		 	 (($month>=1 and $month<=6) and $semester % 2!=0))
			return;
		 
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$current_academic_year_id="academic_year|".$academic_year_id;
		//check to ensure this student didnt discontinue from the program
		if(self::check_discontinue($person_id,self::$student_registration["id"])) {
			return;
			}
		//check if this is the new semester for this student
		$semester=self::increment_semester($person_id);
		//load exempted courses
		self::load_exempted_courses(self::$student_registration["id"],$person_id);
		//check if course registration for this semester already performed and stop processing
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$semester)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$person_id)),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>self::$student_registration["id"])),
												  ));
		$enrolled_this_sem=I2CE_FormStorage::search("enroll_course",false,$where);
		if(count($enrolled_this_sem)==0){
			//load all core courses for this semester
			$where=array(	"operator"=>"AND",
								"operand"=>array(
												0=>array("operator"=>"FIELD_LIMIT",
															"field"=>"semester",
															"style"=>"equals",
															"data"=>array("value"=>$semester)),
												1=>array("operator"=>"OR",
															"operand"=>array(0=>array("operator"=>"AND",
																							  "operand"=>array(
																							  0=>array(	"operator"=>"FIELD_LIMIT",
																											"field"=>"training_program",
																											"style"=>"equals",
																											"data"=>array("value"=>self::$program)),
	  																						  1=>array(	"operator"=>"FIELD_LIMIT",
																										  	"field"=>"course_type",
																											"style"=>"equals",
																											"data"=>array("value"=>"course_type|core"))
																													 )
																							  ),
																				  1=>array("operator"=>"AND",
																							  "operand"=>array(
																							  0=>array(	"operator"=>"FIELD_LIMIT",
																											"field"=>"training_institution",
																											"style"=>"equals",
																											"data"=>array("value"=>self::$training_institution)),
																							  1=>array( "operator"=>"FIELD_LIMIT",
																											"field"=>"course_type",
																											"style"=>"equals",
																											"data"=>array("value"=>"course_type|general_education"))
																													 )
																							  )
																				))));
												
			$courses=I2CE_FormStorage::listFields("training",array("id","prerequisite"),false,$where);
			foreach($courses as $course) {
				//check if this course has been rescheduled to other semesters and ignore it
				if(self::is_rescheduled("training|".$course["id"],$current_academic_year_id,$semester))
				continue;
				
				//skip enrolling exempted courses
				if(in_array("training|".$course["id"],self::$exempted_courses)) {
					continue;
					}
				//skip registering courses for which a student didnt pass prerequisites
				if(count($course["prerequisite"])!="" and !self::checkIfPassedPrerequisite($course["prerequisite"],"training|".$course["id"],$person_id))
				continue;
				$training_course[]="training|".$course["id"];
				$courseObj=$ff->createContainer("training|".$course["id"]);
				$courseObj->populate();
				$total_credits=$total_credits+$courseObj->getField("course_credits")->getDBValue();
				}
				
			$training_courses=implode(",",$training_course);
			$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
			$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
			$academic_year_id="academic_year|".$academic_year_id;				
			$enrollcourseObj=$ff->createContainer("enroll_course");
			$date_enrolled=date("Y-m-d");
			$enrollcourseObj->getField("date_enrolled")->setFromDB($date_enrolled);
			$enrollcourseObj->getField("total_credits")->setValue($total_credits);
			$enrollcourseObj->getField("registration")->setFromDB(self::$student_registration["id"]);
			$enrollcourseObj->getField("semester")->setFromDB($semester);
			$enrollcourseObj->getField("training")->setFromDB($training_courses);
			$enrollcourseObj->getField("academic_year")->setFromDB($academic_year_id);
			$enrollcourseObj->getField("parent")->setFromDB($person_id);
			$user=new I2CE_User;
			if($training_courses!="")
			$enrollcourseObj->save($user);
			}
		}
	
	static function passed_all_courses($person_id) {
		self::manual_constructor($person_id);
		$exempted_courses=IHS_PageFormEnrollcourse::load_exempted_courses(self::$student_registration["id"],$person_id);
		list($form,$joined_sem)=explode("|",self::$joined_semester);
		$total_semesters=self::get_total_semesters(self::$program,self::$admission_type);
		$current_semester=self::$student_registration["semester"];
		$current_semester=explode("|",$current_semester);
		$current_semester=$current_semester[1];
		for ($sem=$joined_sem;$sem<=$current_semester;$sem++) {
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
												"data"=>array("value"=>self::$program)),
									2=>array("operator"=>"OR",
												"operand"=>array( 0=>array("operator"=>"FIELD_LIMIT",
																					"field"=>"course_type",
																					"style"=>"equals",
																					"data"=>array("value"=>"course_type|core")),
																		1=>array("operator"=>"FIELD_LIMIT",
																					"field"=>"course_type",
																					"style"=>"equals",
																					"data"=>array("value"=>"course_type|required_general_education"))
																	 ))
																	 
											));
			$courses=I2CE_FormStorage::search("training",false,$where);
			
			//include even optional and elective courses that this student enrolled
			$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"semester",
																	"style"=>"equals",
																	"data"=>array("value"=>$semester)),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"registration",
																	"style"=>"equals",
																	"data"=>array("value"=>self::$student_registration["id"]))
													 ));
	      $enroll=I2CE_FormStorage::listFields("enroll_course",array("training"),false,$where);
	      foreach($enroll as $enrolled_courses) {
	      	$enrolled_courses=explode(",",$enrolled_courses);
	      	foreach($enrolled_courses as $enrolled_course) {
	      		$crs=explode("|",$enrolled_course);
	      		$crs=$crs[1];
	      		if(!in_array($crs,$courses))
	      		$courses[]=$crs;
	      		}
	      	}
						
			foreach($courses as $course) {
				$course="training|".$course;
				//ignore exempted courses
				if(!in_array($course,$exempted_courses)) {
					//get results for this course
					$mark=IHS_PageFormAddResultsProcess::getCourseHighestMark($course,self::$student_registration["id"]);
					//return false if results does not exist or mark is less than the passing score
					if(!isset($mark) or $mark < self::$passing_score or $mark==-1)
					return false;
					}
				}
			}
		return true;
		}
		
	static function check_discontinue($person_id,$registration) {
		$where_disco=array("operator"=>"AND",
									"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"parent",
																		"style"=>"equals",
																		"data"=>array("value"=>$person_id)),
														  1=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"registration",
																		"style"=>"equals",
																		"data"=>array("value"=>$registration)),
							     ));
		$isdisco=I2CE_FormStorage::search("discontinued",false,$where_disco);
		if(count($isdisco)>0){
			return true;
			}
		}
		
	static function manual_constructor($person_id) {
		$ff = I2CE_FormFactory::instance();
		self::$student_registration=STS_PageFormPerson::load_current_registration($person_id);
		self::$curr_semester=self::$student_registration["semester"];
		self::$curr_level=self::$student_registration["academic_level"];
		self::$joined_semester=self::$student_registration["joined_semester"];
		self::$date_registered=self::$student_registration["registration_date"];
		self::$program=self::$student_registration["training_program"];
		self::$training_institution=self::$student_registration["training_institution"];
		self::$admission_type=self::$student_registration["admission_type"];
		
		$instObj=$ff->createContainer(self::$training_institution);
		$instObj->populate();
		self::$passing_score=$instObj->getField("passing_score")->getDBValue();
		$instObj->cleanup();
		
		$progObj=$ff->createContainer(self::$program);
		$progObj->populate();
		if(self::$admission_type=="admission_type|full-time")
		self::$total_semesters=$progObj->getField("total_semesters_fulltime")->getDBValue();
		else if(self::$admission_type=="admission_type|part-time")
		self::$total_semesters=$progObj->getField("total_semesters_parttime")->getDBValue();
		$progObj->cleanup();
		}
	
	protected function isEnrolled($training_course) {
		$academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($academic_year);
		$academic_year_id="academic_year|".$academic_year_id;
		$where=array("operator"=>"AND","operand"=>array(
									0=>array("operator"=>"FIELD_LIMIT",
												"field"=>"parent",
												"style"=>"equals",
												"data"=>array("value"=>self::$person_id)),
									1=>array("operator"=>"FIELD_LIMIT",
												"field"=>"training",
												"style"=>"like",
												"data"=>array("value"=>"%".$training_course."%")),
									2=>array("operator"=>"FIELD_LIMIT",
												"field"=>"academic_year",
												"style"=>"equals",
												"data"=>array("value"=>$academic_year_id)),
									3=>array("operator"=>"FIELD_LIMIT",
												"field"=>"semester",
												"style"=>"equals",
												"data"=>array("value"=>self::$curr_semester)),
									4=>array("operator"=>"FIELD_LIMIT",
												"field"=>"registration",
												"style"=>"equals",
												"data"=>array("value"=>self::$student_registration["id"]))
												));
		$is_enrolled=I2CE_FormStorage::listFields('enroll_course',array("training"),false, $where);
		if(count($is_enrolled)>0) {
			foreach($is_enrolled as $enrolled) {
				$courses=explode(",",$enrolled["training"]);
				if(in_array($training_course,$courses))
				return true;
				}
			}
		}
	
	protected function isEnrolledIncomplete($course) {
		$academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($academic_year);
		$academic_year_id="academic_year|".$academic_year_id;
		$where=array("operator"=>"AND","operand"=>array(
								0=>array("operator"=>"FIELD_LIMIT",
											"field"=>"parent",
											"style"=>"equals",
											"data"=>array("value"=>self::$person_id)),
								1=>array("operator"=>"FIELD_LIMIT",
											"field"=>"training",
											"style"=>"equals",
											"data"=>array("value"=>$course)),
								2=>array("operator"=>"FIELD_LIMIT",
											"field"=>"academic_year",
											"style"=>"equals",
											"data"=>array("value"=>$academic_year_id)),
								3=>array("operator"=>"FIELD_LIMIT",
											"field"=>"semester",
											"style"=>"equals",
											"data"=>array("value"=>self::$curr_semester)),
								4=>array("operator"=>"FIELD_LIMIT",
											"field"=>"registration",
											"style"=>"equals",
											"data"=>array("value"=>self::$student_registration["id"]))
											));
		$is_enrolled=I2CE_FormStorage::search('enroll_incomplete_course',false, $where);
		if(count($is_enrolled)>0)
		return true;
		}
		
	protected function getProgramCourses($semester) {
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$current_academic_year_id="academic_year|".$academic_year_id;
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(
																"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$semester)
															 ),
												  1=>array(	"operator"=>"OR",
												  				"operand"=>array(0=>array(
																									"operator"=>"FIELD_LIMIT",
																									"field"=>"training_program",
																									"style"=>"equals",
																									"data"=>array("value"=>self::$program)
																								 ),
																					  1=>array(
																					  "operator"=>"AND",
																					  	"operand"=>array(
																					  		0=>array("operator"=>"FIELD_LIMIT",
																										"field"=>"training_institution",
																										"style"=>"like",
																										"data"=>array("value"=>"%".self::$training_institution."%")),
																							1=>array("operator"=>"FIELD_LIMIT",
																										"field"=>"course_type",
																										"style"=>"like",
																										"data"=>array("value"=>"course_type|general_education")),
																														  
																											 )
																								 )))));

		$courses=I2CE_FormStorage::listFields("training",array("corequisite","prerequisite","id"),false,$where);
		foreach ($courses as $id=>$course_array) {
			$training_course="training|".$id;
			//check if this course has been rescheduled to other semesters and ignore it
			if(self::is_rescheduled($training_course,$current_academic_year_id,$semester))
			continue;
			$this->prerequisite[$id]=$course_array["prerequisite"];
			$this->corequisite[$id]=$course_array["corequisite"];
			if(count($course_array["prerequisite"])!="") {
				if (self::checkIfPassedPrerequisite($course_array["prerequisite"],$training_course,self::$person_id))
				$this->subject_courses[$id]=$training_course;
				}
			else
			$this->subject_courses[$id]=$training_course;	
		}

		//get program courses that have been rescheduled to this semester
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"academic_year",
																"style"=>"equals",
																"data"=>array("value"=>$current_academic_year_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$semester)),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training_program",
																"style"=>"equals",
																"data"=>array("value"=>self::$program)),
												  3=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training_institution",
																"style"=>"equals",
																"data"=>array("value"=>self::$training_institution))
												 ));
		$courses=I2CE_FormStorage::listFields("reschedule_course",array("training"),false,$where);
		foreach ($courses as $course) {
			$trn_id=explode("|",$course["training"]);
			$id=$trn_id[1];
			$courseObj=$this->factory->createContainer($course["training"]);
			$courseObj->populate();
			$prerequisites=$courseObj->getField("prerequisite")->getDBValue();
			if (self::checkIfPassedPrerequisite($prerequisites,$course["training"],self::$person_id))
			$this->subject_courses[$id]=$course["training"];
			}
	}

	static function is_rescheduled($course,$academic_year,$curr_semester) {
		$page=new I2CE_Page;
		$username=$page->getUser()->username;
		$training_institution=IHS_PageFormLecturer::fetch_institution($username);
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"equals",
																"data"=>array("value"=>$course)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training_institution",
																"style"=>"equals",
																"data"=>array("value"=>$training_institution)),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"old_semester",
																"style"=>"equals",
																"data"=>array("value"=>$curr_semester)),
												  3=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"academic_year",
																"style"=>"equals",
																"data"=>array("value"=>$academic_year))
												 ));
		$is_rescheduled=I2CE_FormStorage::search("reschedule_course",false,$where);
		if(count($is_rescheduled)>0) {
			return true;
			}
		return false;
		}
	
	//check courses that a student failed or didnt take in previous semesters
	protected function getPreviousSemesterCourses($current_semester) {
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$current_academic_year_id="academic_year|".$academic_year_id;
		$curr_semester=self::getSemesterName($current_semester);
		$joined_semester=self::getSemesterName(self::$joined_semester);
		$month=date("m");
		for($semester=$curr_semester;$semester>=$joined_semester;$semester=$semester-1) {
			//odd semesters are offered btn Jan and May while even semesters are offered btn July and Dec
			if(($month>=6 and $month<=12 and $semester % 2 ==0) or ($month>=1 and $month<=5 and $semester % 2 !=0))
			continue; 
			if($semester==$curr_semester)
			continue;
			$semester_id="semester|". self::getSemesterId($semester);
			//check previous semester courses a student failed which have been rescheduled and include them
			$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"new_semester",
																	"style"=>"equals",
																	"data"=>array("value"=>$semester_id)),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"training_program",
																	"style"=>"equals",
																	"data"=>array("value"=>self::$program)),
													  2=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"academic_year",
																	"style"=>"equals",
																	"data"=>array("value"=>$current_academic_year_id)),
													  3=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"training_institution",
																	"style"=>"equals",
																	"data"=>array("value"=>self::$training_institution))
									  				 ));
			$rescheduled_courses=I2CE_FormStorage::listFields("reschedule_course",array("training"),false,$where);
			$training_courses=array(0);
			foreach($rescheduled_courses as $courses) {
				$trng_id=explode("|",$courses["training"]);
				$training_courses[]=$trng_id[1];
				}
			$where_rescheduled=array(	"operator"=>"FIELD_LIMIT",
													"field"=>"id",
													"style"=>"in",
													"data"=>array("value"=>$training_courses));
			$resch_trang=I2CE_FormStorage::listFields("training",array("id","prerequisite"),false,$where_rescheduled);
			$where=array(
					"operator"=>"AND",
					"operand"=>array(
							0=>array(
								"operator"=>"FIELD_LIMIT",
								"field"=>"semester",
								"style"=>"equals",
								"data"=>array("value"=>$semester_id)
									  ),
							1=>array(
								"operator"=>"FIELD_LIMIT",
								"field"=>"training_program",
								"style"=>"equals",
								"data"=>array("value"=>self::$program)
									  )
					));
	
			$sem_courses=I2CE_FormStorage::listFields("training",array("id","prerequisite","semester"),false,$where);
			$courses=$sem_courses+$resch_trang;
			foreach ($courses as $course_array) {
				//check if results for this course does not exist or the student failed this course
				$training_course="training|".$course_array["id"];
				$obj=$this->factory->createContainer($training_course);
				$obj->populate();
				$code=$obj->code;
				//skip checking exempted courses
				if(in_array($training_course,self::$exempted_courses))
				continue;
				//check if this course has been rescheduled to other semesters and ignore it
				if(self::is_rescheduled($training_course,$current_academic_year_id,$course_array["semester"]))
				continue;
				$where=array(
									"operator"=>"AND",
									"operand"=>array(0=>array(								
																		"operator"=>"FIELD_LIMIT",
																		"field"=>"training",
																		"style"=>"equals",
																		"data"=>array("value"=>$training_course)
								 									 ),
								 						  1=>array(
																		"operator"=>"FIELD_LIMIT",
																		"field"=>"parent",
																		"style"=>"equals",
																		"data"=>array("value"=>self::$person_id)
								 									 ),
														  2=>array(
														  				"operator"=>"FIELD_LIMIT",
																		"field"=>"registration",
																		"style"=>"equals",
																		"data"=>array("value"=>self::$student_registration["id"])
								 						 			 ),
								 ));
				$results=I2CE_FormStorage::listFields("students_results_grade",array("total_marks","status","training","semester"),false,$where);
				//if no results,check if he managed to pass the prerequisite and display the course
				if(count($results)==0) {
					//if this course has no pre-requisites then add it to subject courses
					if($course_array["prerequisite"]=="") {
						$this->undone_courses[$course_array["id"]]=$training_course;
						$courseObj=$this->factory->createContainer($training_course);
						$courseObj->populate();
						$course_type=$courseObj->getField("course_type")->getDBValue();
						if($course_type!="course_type|core" and $course_type!="course_type|required_general_education")
						$this->subject_courses[$course_array["id"]]=$training_course;
						}
					else if ($this->checkIfPassedPrerequisite($course_array["prerequisite"],$training_course,self::$person_id)) {
						$this->undone_courses[$course_array["id"]]=$training_course;
						$courseObj=$this->factory->createContainer($training_course);
						$courseObj->populate();
						$course_type=$courseObj->getField("course_type")->getDBValue();
						if($course_type!="course_type|core" and $course_type!="course_type|required_general_education")
						$this->subject_courses[$course_array["id"]]=$training_course;
						}
					}
				else {
					$status="display";
					foreach($results as $result) {
					if($result["status"]=="status|pass")
						$status="dont_diplay";
						}
					if($status=="display")
						$this->undone_courses[$course_array["id"]]=$training_course;
					}
				}
		}
	}
	
	protected function getElectiveCourses($current_semester) {
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$current_academic_year_id="academic_year|".$academic_year_id;
		$semester=self::getSemesterName($current_semester);
		$trng_prgrms=iHRIS_PageFormEnrollcourse::get_institution_programs();
		unset($trng_prgrms[self::$program]);
		for($semester=$semester;$semester>0;$semester=$semester-2) {
			$semester_id="semester|". self::getSemesterId($semester);
			$where=array(
					"operator"=>"AND",
					"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
														"field"=>"semester",
														"style"=>"equals",
														"data"=>array("value"=>$semester_id)
													 ),
										  1=>array(	"operator"=>"FIELD_LIMIT",
														"field"=>"training_program",
														"style"=>"in",
														"data"=>array("value"=>$trng_prgrms)
												  	 ),
									    ),
						    );
			$courses=I2CE_FormStorage::listFields("training",array("id"),false,$where);
			foreach ($courses as $id=>$course_array) {	
				$training_course="training|".$id;
				if(self::is_rescheduled($training_course,$current_academic_year_id,$current_semester))
				continue;
				//check if a student took and passed this course then skip displaying it
				$where=array(
								"operator"=>"AND",
								"operand"=>array(0=>array(								
																	"operator"=>"FIELD_LIMIT",
																	"field"=>"training",
																	"style"=>"equals",
																	"data"=>array("value"=>$training_course)
							 									 ),
							 						  1=>array(
																	"operator"=>"FIELD_LIMIT",
																	"field"=>"parent",
																	"style"=>"equals",
																	"data"=>array("value"=>self::$person_id)
							 									 ),
													  3=>array(
													  				"operator"=>"FIELD_LIMIT",
																	"field"=>"registration",
																	"style"=>"equals",
																	"data"=>array("value"=>self::$student_registration["id"])
							 						 			 )
							 ));
				$results=I2CE_FormStorage::ListFields("students_results_grade",array("total_marks","status"),false,$where);
				$status="display";
				foreach($results as $result) {
					if($result["status"]=="status|pass") {
						$status="pass";
						break;
						}
					}
				if($status=="display")
				$this->elective_courses[$id]=$training_course;
				}

				//get elective courses that have been rescheduled to this semester
				$where=array(	"operator"=>"AND",
									"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"academic_year",
																		"style"=>"equals",
																		"data"=>array("value"=>$current_academic_year_id)),
														  1=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"semester",
																		"style"=>"equals",
																		"data"=>array("value"=>$semester)),
														  2=>array(	"operator"=>"NOT",
																		"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																											"field"=>"training_program",
																											"style"=>"equals",
																											"data"=>array("value"=>self::$program)))),
														  3=>array(	"operator"=>"FIELD_LIMIT",
									 									"field"=>"training_institution",
									 									"style"=>"equals",
									 									"data"=>array("value"=>self::$training_institution))
														 ));
				$courses=I2CE_FormStorage::listFields("reschedule_course",array("training"),false,$where);
				foreach ($courses as $course) {
					$trn_id=explode("|",$course["training"]);
					$id=$trn_id[1];
					$this->elective_courses[$id]=$course["training"];
					}
			}
	}

	static function checkIfPassedPrerequisite($prerequisite,$training_course,$person_id=false) {
		if(!$person_id)
		$person_id=self::$person_id;
		$prerequisites=explode(",",$prerequisite);
		foreach($prerequisites as $prerequisite) {
		//if this prerequisite is exempted then assume passed it.
		if(in_array($prerequisite,self::$exempted_courses)) {
			continue;
			}
		$where=array(
						"operator"=>"AND",
						"operand"=>array(
										0=>array(
												"operator"=>"FIELD_LIMIT",
												"field"=>"training",
												"style"=>"equals",
												"data"=>array("value"=>$prerequisite)
												),
										1=>array(
												"operator"=>"FIELD_LIMIT",
												"field"=>"parent",
												"style"=>"equals",
												"data"=>array("value"=>$person_id)
												),
										2=>array(
												"operator"=>"FIELD_LIMIT",
												"field"=>"registration",
												"style"=>"equals",
												"data"=>array("value"=>self::$student_registration["id"])
												  )
					));
		$course_marks=I2CE_FormStorage::listFields("students_results_grade",array("total_marks","status"),false,$where);
	
		//if no results for this prerequisite,return false
		if(count($course_marks)==0) {
			self::$failed_prerequisite[$prerequisite]=$training_course;
			return false;
			}
	
		//check if a student has more than one attempt
		$status="fail";
		foreach($course_marks as $marks) {
			if($marks["status"]=="status|pass") {
				$status="pass";
				break;
				}
			}
	
		//if a student has failed return false.
		if($status=="fail") {
			self::$failed_prerequisite[$prerequisite]=$training_course;
			return false;
			}
		}
	return true;
	}
	
	protected function get_all_failed_prerequisites($training_course) {
		$failed_prerequisite=array();
		$person_id=self::$person_id;
		$courseObj=$this->factory->createContainer($training_course);
		$courseObj->populate();
		$prerequisites=$courseObj->getField("prerequisite")->getDBValue();
		$prerequisites=explode(",",$prerequisites);
		foreach($prerequisites as $prerequisite) {
		//if this prerequisite is exempted then assume passed it.
		if(in_array($prerequisite,self::$exempted_courses)) {
			continue;
			}
		$where=array(
						"operator"=>"AND",
						"operand"=>array(
										0=>array(
												"operator"=>"FIELD_LIMIT",
												"field"=>"training",
												"style"=>"equals",
												"data"=>array("value"=>$prerequisite)
												),
										1=>array(
												"operator"=>"FIELD_LIMIT",
												"field"=>"parent",
												"style"=>"equals",
												"data"=>array("value"=>$person_id)
												),
										2=>array(
												"operator"=>"FIELD_LIMIT",
												"field"=>"registration",
												"style"=>"equals",
												"data"=>array("value"=>self::$student_registration["id"])
												  )
					));
		$course_marks=I2CE_FormStorage::listFields("students_results_grade",array("total_marks","status"),false,$where);
	
		//if no results for this prerequisite,return false
		if(count($course_marks)==0) {
			$failed_prerequisite[]=$prerequisite;
			}
	
		//check if a student has more than one attempt
		$status="fail";
		foreach($course_marks as $marks) {
			if($marks["status"]=="status|pass") {
				$status="pass";
				break;
				}
			}
	
		//if a student has failed return false.
		if($status=="fail") {
			$failed_prerequisite[]=$prerequisite;
			}
		}
	return $failed_prerequisite;
	}
	
	protected function display_pending() {
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"registration",
							"style"=>"equals",
							"data"=>array("value"=>self::$student_registration["id"]));
		$pendings=I2CE_FormStorage::listFields("pending_courses",array("training","semester"),false,$where);
		foreach($pendings as $pending) {
			$pending_courses[]=$pending["training"];
			}
		$results=I2CE_FormStorage::listFields("students_results_grade",array("training","status"),false,$where);
		foreach($results as $result) {
			$done_courses[]=$result["training"];
			if($result["status"]=="status|incomplete")
			$incomplete_courses[]=$result["training"];
			}
		
		foreach($pending_courses as $pending) {
			//getting courses which are not registered
			$pending=explode(",",$pending);
			$results=array_diff($pending,$done_courses);
			$results=array();
			if(is_array($not_registered))
			$not_registered=array_merge($not_registered,$results);
			else
			$not_registered=$results;
			}

		foreach($pending_courses as $pending) {
			$pending=explode(",",$pending);
			foreach($pending as $pend) {
				//getting courses which are to be retaken
				$where=array(	"operator"=>"AND",
									"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"registration",
																		"style"=>"equals",
																		"data"=>array("value"=>self::$student_registration["id"])),
														  1=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"training",
																		"style"=>"equals",
																		"data"=>array("value"=>$pend)),
														  2=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"status",
																		"style"=>"equals",
																		"data"=>array("value"=>"status|pass"))
														 ));
				$results=I2CE_FormStorage::search("students_results_grade",false,$where);
				print_r($where);
				if(count($results)==0 and !in_array($pend,$not_registered) and !in_array($pend,$incomplete_courses)) {
					$retake[]=$pend;
					}
				}
			}

			if(count($retake)>0 or count($not_registered)>0 or count($incomplete_courses)>0) {
				if (! ($div = $this->template->getElementByID("pending_courses")) instanceof DOMNode)
				return ;
				$table =$this->template->createElement("table",array("class"=>"multiFormTable","border"=>"0","cellpadding"=>"0","cellspacing"=>"0"));
				$header=$this->template->createElement("H2","","Pending/Retake Courses");
				$this->template->appendNode($header,$div);
				$tr =$this->template->createElement("tr");
				$th=$this->template->createElement("th",array("align"=>"center"),"SN");
				$this->template->appendNode($th,$tr);
				$th=$this->template->createElement("th",array("align"=>"center"),"Select");
				$this->template->appendNode($th,$tr);
				$th=$this->template->createElement("th",array("align"=>"center"),"Course");
				$this->template->appendNode($th,$tr);
				$th=$this->template->createElement("th",array("align"=>"center"),"Course Type");
				$this->template->appendNode($th,$tr);
				$th=$this->template->createElement("th",array("align"=>"center"),"Course Credits");
				$this->template->appendNode($th,$tr);
				$th=$this->template->createElement("th",array("align"=>"center"),"Course Semester");
				$this->template->appendNode($th,$tr);
				$th=$this->template->createElement("th",array("align"=>"center"),"Pending/Retake");
				$this->template->appendNode($th,$tr);
				$this->template->appendNode($tr,$table);
				if(is_array($incomplete_courses))
				foreach($incomplete_courses as $course) {
					$count++;
					$courseObj=$this->factory->createContainer($course);
					$courseObj->populate();
					$course_details=$courseObj->code."-".$courseObj->name;
					$course_type=$courseObj->getField("course_type")->getDisplayValue();
					$course_semester=$courseObj->getField("semester")->getDisplayValue();
					$course_credits=$courseObj->getField("course_credits")->getValue();
					$tr =$this->template->createElement("tr");
					$th=$this->template->createElement("td",array("align"=>"center"),$count);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"));
					if(in_array($course,$this->undone_courses)) {
						if($this->isEnrolledIncomplete($course))
						$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																										"checked"=>"checked",
																										"name"=>"incomplete[".$course."]",
																										"value"=>$course));
						else
						$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																										"name"=>"incomplete[".$course."]",
																										"value"=>$course));
						}
					else if(!in_array($course,$this->undone_courses))
					$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																									"disabled"=>"true",
																									"name"=>"incomplete[".$course."]",
																									"value"=>$course));
					$this->template->appendNode($checkbox,$th);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td","",$course_details);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),$course_type);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),$course_credits);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),$course_semester);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),"Incomplete");
					$this->template->appendNode($th,$tr);
					$this->template->appendNode($tr,$table);
					}
				if(is_array($not_registered))
				foreach($not_registered as $course) {
					$count++;
					$courseObj=$this->factory->createContainer($course);
					$courseObj->populate();
					$course_details=$courseObj->code."-".$courseObj->name;
					$course_type=$courseObj->getField("course_type")->getDisplayValue();
					$course_semester=$courseObj->getField("semester")->getDisplayValue();
					$course_credits=$courseObj->getField("course_credits")->getValue();
					$tr =$this->template->createElement("tr");
					$th=$this->template->createElement("td",array("align"=>"center"),$count);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"));
					if(in_array($course,$this->undone_courses)) {
						if($this->isEnrolled($course) and $this->results_uploaded($course)) {
							$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																											"checked"=>"checked",
																											"disabled"=>"true",
																											"name"=>"course[".$course."]",
																											"value"=>$course));
							$hidden_checkbox=$this->template->createElement("input",array(	"type"=>"hidden",
																													"name"=>"course[".$course."]",
																													"value"=>$course));
							$this->template->appendNode($hidden_checkbox,$th);
							}
						else if($this->isEnrolled($course))
						$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																										"checked"=>"checked",
																										"name"=>"course[".$course."]",
																										"value"=>$course));
						else
						$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																										"name"=>"course[".$course."]",
																										"value"=>$course));
						}
					else if(!in_array($course,$this->undone_courses))
					$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																									"disabled"=>"true",
																									"name"=>"course[".$course."]",
																									"value"=>$course));
					$this->template->appendNode($checkbox,$th);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td","",$course_details);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),$course_type);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),$course_credits);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),$course_semester);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),"Pending");
					$this->template->appendNode($th,$tr);
					$this->template->appendNode($tr,$table);
					}
				if(is_array($retake))
				foreach($retake as $course) {
					$count++;
					$courseObj=$this->factory->createContainer($course);
					$courseObj->populate();
					$course_details=$courseObj->code."-".$courseObj->name;
					$course_type=$courseObj->getField("course_type")->getDisplayValue();
					$course_semester=$courseObj->getField("semester")->getDisplayValue();
					$course_credits=$courseObj->getField("course_credits")->getValue();
					$tr =$this->template->createElement("tr");
					$th=$this->template->createElement("td",array("align"=>"center"),$count);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"));
					if(in_array($course,$this->undone_courses)) {
						if($this->isEnrolled($course) and $this->results_uploaded($course)) {
							$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																											"checked"=>"checked",
																											"disabled"=>"true",
																											"name"=>"course[".$course."]",
																											"value"=>$course));
							$hidden_checkbox=$this->template->createElement("input",array(	"type"=>"hidden",
																													"name"=>"course[".$course."]",
																													"value"=>$course));
							$this->template->appendNode($hidden_checkbox,$th);
							}
						else if($this->isEnrolled($course))
						$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																										"checked"=>"checked",
																										"name"=>"course[".$course."]",
																										"value"=>$course));
						else
						$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																										"name"=>"course[".$course."]",
																										"value"=>$course));
						}
					else if(!in_array($course,$this->undone_courses))
					$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																									"name"=>"course[".$course."]",
																									"value"=>$course));
					$this->template->appendNode($checkbox,$th);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td","",$course_details);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),$course_type);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),$course_credits);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),$course_semester);
					$this->template->appendNode($th,$tr);
					$th=$this->template->createElement("td",array("align"=>"center"),"Retake");
					$this->template->appendNode($th,$tr);
					$this->template->appendNode($tr,$table);
					}
				$this->template->appendNode($table,$div);
				}
		
		//getting courses which are to be retaken
		
		}

	static function getSemesterName($semester) {
		$ff = I2CE_FormFactory::instance();
		$semObj=$ff->createContainer($semester);
		$semObj->populate();
		$semester=$semObj->getField("name")->getDBValue();		
		return $semester;
	}
	
	static function getSemesterId($semester) {
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
	
	protected function results_uploaded($course) {
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>self::$curr_semester)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>self::$student_registration["id"]))
												  ));

		$enroll_courses=I2CE_FormStorage::search("enroll_course",false,$where);
		foreach ($enroll_courses as $enroll_course) {
			$enroll_course="enroll_course|".$enroll_course;
			}
		
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"enroll_course",
							"style"=>"equals",
							"data"=>array("value"=>$enroll_course));
		$results=I2CE_FormStorage::search("students_results_grade",false,$where);

return false;
		if(count($results) > 0)
		return true;
		else
		return false;
		}
		
	protected function displayCourses($courses,$div_id,$text,$limit=false,$deny_registration,$dsply_deny_msg) {
	    if (! ($div = $this->template->getElementByID($div_id)) instanceof DOMNode)
		return ;
		if($dsply_deny_msg) {
			$max_credits_div=$this->template->getElementByID("max_credits");
			$header=$this->template->createElement("H2","","YOU HAVE REACHED MAXIMUM CREDITS FOR THIS SEMESTER,COURSE REGISTRATION CLOSED FOR YOU");
			$this->template->appendNode($header,$max_credits_div);
			}
		$table =$this->template->createElement("table",array("class"=>"multiFormTable","border"=>"0","cellpadding"=>"0","cellspacing"=>"0"));
		$header=$this->template->createElement("H2","","$text");
		$this->template->appendNode($header,$div);
		$tr =$this->template->createElement("tr");
		$th=$this->template->createElement("th",array("align"=>"center"),"SN");
		$this->template->appendNode($th,$tr);
		$th=$this->template->createElement("th",array("align"=>"center"),"Select");
		$this->template->appendNode($th,$tr);
		$th=$this->template->createElement("th",array("align"=>"center"),"Course Code");
		$this->template->appendNode($th,$tr);
		$th=$this->template->createElement("th","","Course Name");
		$this->template->appendNode($th,$tr);
		$th=$this->template->createElement("th","","Course Type");		
		$this->template->appendNode($th,$tr);
		$th=$this->template->createElement("th","","Course Credits");
		$this->template->appendNode($th,$tr);	
		$th=$this->template->createElement("th","","Course Semester");
		$this->template->appendNode($th,$tr);	
		if($div_id=="failed") {
			$th=$this->template->createElement("th","","Prerequisites Failed");
			$this->template->appendNode($th,$tr);
			}
		$this->template->appendNode($tr,$table);
		$counter=1;
		$role=$this->getUser()->role;
		foreach ($courses as $id=>$subject)
		{
			//skip displaying exempted courses
			if(in_array($subject,self::$exempted_courses)) {
				continue;
				}
			$tr =$this->template->createElement("tr");
			$td =$this->template->createElement("td",array("align"=>"center"),$counter);
			$this->template->appendNode($td,$tr);
			$td =$this->template->createElement("td",array("align"=>"center"));
			if($this->isEnrolled($subject)) {
				$trainObj=$this->factory->createContainer($subject);
				$trainObj->populate();
				$course_type = $trainObj->getField("course_type")->getDBValue();
				$course_program=$trainObj->getField("training_program")->getDBValue();
				if(($role!="hod" and $role!="level_coordinator") and 
               ($course_type=="course_type|core" or $course_type=="course_type|required_general_education") and 
               $course_program==self::$program) {
					$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																									"name"=>"course[".$subject."]",
																									"value"=>$subject,
																									"checked"=>"checked",
																									"disabled"=>"true"));
					$hidden_checkbox=$this->template->createElement("input",array(	"type"=>"hidden",
																											"name"=>"course[".$subject."]",
																											"value"=>$subject));
					$this->template->appendNode($hidden_checkbox,$td);
					}
				else {
					
					//disable the display if results exists
					if($this->results_uploaded($subject)) {
					$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																									"name"=>"course[".$subject."]",
																									"value"=>$subject,
																									"checked"=>"checked",
																									"disabled"=>"true"));
					$hidden_checkbox=$this->template->createElement("input",array(	"type"=>"hidden",
																											"name"=>"course[".$subject."]",
																											"value"=>$subject));
					$this->template->appendNode($hidden_checkbox,$td);
					}
					else
					$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																									"name"=>"course[".$subject."]",
																									"value"=>$subject,
																									"checked"=>"checked"));
					}
				}
			else if($limit==true) {
				$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																								"name"=>"course[".$subject."]",
																								"value"=>$subject,
																								"disabled"=>"true"));
				}
			else {
				if($deny_registration) {
					$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																									"name"=>"course[".$subject."]",
																									"value"=>$subject,
																									"disabled"=>"true"));
					}
				else
				$checkbox =$this->template->createElement("input",array(	"type"=>"checkbox",
																								"name"=>"course[".$subject."]",
																								"value"=>$subject));
				
				}
			$this->template->appendNode($checkbox,$td);
			$this->template->appendNode($td,$tr);
			if($limit==true)
			{				
			$ids=explode("|",$subject);
			$subject_id=$ids[1];
			}
			$where=array(
								"operator"=>"FIELD_LIMIT",
								"field"=>"id",
								"style"=>"equals",
								"data"=>array("value"=>$subject_id)
							 );
			$course_descriptions=I2CE_FormStorage::listFields("training",array(	"name","code",
																											"course_type",
																											"course_credits","semester"),false,$where);
			$courseObj=$this->factory->createContainer($subject);
			$courseObj->populate();
			$td =$this->template->createElement("td",array("align"=>"center"),$courseObj->getField("code")->getDBValue());
			$this->template->appendNode($td,$tr);
			$td =$this->template->createElement("td","",$courseObj->getField("name")->getDBValue());
			$this->template->appendNode($td,$tr);
			$td =$this->template->createElement("td",array("align"=>"center"),$courseObj->getField("course_type")->getDisplayValue());
			$this->template->appendNode($td,$tr);			
			$td =$this->template->createElement("td",array("align"=>"center"),$courseObj->getField("course_credits")->getDBValue());
			$this->template->appendNode($td,$tr);	
			$td =$this->template->createElement("td",array("align"=>"center"),$courseObj->getField("semester")->getDisplayValue());
			$this->template->appendNode($td,$tr);
			if($div_id=="failed") {
				$prerequisites=$this->get_all_failed_prerequisites($subject);
				foreach($prerequisites as $prerequisite) {
					$prereqObj=$this->factory->createContainer($prerequisite);
					$prereqObj->populate();
					if($prerequisite_name=="")
					$prerequisite_name=$prereqObj->code."-".$prereqObj->name;
					else
					$prerequisite_name=$prerequisite_name.",".$prereqObj->code."-".$prereqObj->name;
					}
				$td =$this->template->createElement("td",array("align"=>"center"),$prereqObj->code."-".$prereqObj->name);
				$this->template->appendNode($td,$tr);
				}
			$this->template->appendNode($tr,$table);
			$counter++;
		}
		$this->template->appendNode($table,$div);
	}
	
	}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
