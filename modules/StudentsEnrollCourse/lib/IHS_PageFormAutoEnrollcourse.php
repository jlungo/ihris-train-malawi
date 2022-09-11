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
	class IHS_PageFormAutoEnrollcourse {
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
	
	public function action($person_id) {
	self::$person_id=$person_id;
	$this->pageObj=new I2CE_Page;
	$this->ff=I2CE_FormFactory::instance();
	iHRIS_AcademicYear::ensureAcademicYear();
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
				$drpObj=$this->ff->createContainer("drop_semester|".$drp_id);
				$drpObj->populateChildren("resume_semester");
				$resumed=false;
				foreach ($drpObj->getChildren("resume_semester") as $resObj) {
					$resumed=true;
					}
				if(!$resumed) {
      			return false;
					break;
					}
				}
			}
	############End Of Denying Course Registration For A Student Dropped Out Of Semester###################
	
	############Deny Course Registration For Completed Students###########
	if(self::completed_school(self::$curr_semester,self::$total_semesters,self::$person_id)) {
      return false;
		}
	############End Of Denying Course Registration For Completed Students###########
	
	########### Check if this student is not discontinued  #####################
	if(self::check_discontinue(self::$person_id,self::$student_registration["id"])) {
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
	$GPA_exist=IHS_PageFormEnrollcourse::GPA_exist(self::$person_id,self::$curr_semester,self::$student_registration["id"]);
	if($current_semester<=self::$total_semesters and !$GPA_exist) {
		//get the number of semesters ellapsed since the previous enrollment
		//$this->semester_ellapsed ("semester|".($current_semester-1));
		//$semester=$current_semester-1+$this->sem;
		$month=date("m");
		if((($month>=1 and $month<=5) and $current_semester % 2!=0) or (($month>=7 and $month<=12) and $current_semester % 2==0)) {
			return false;
			}
			
		else if((($month>=1 and $month<=5) and $current_semester % 2==0) or 
					 (($month>=7 and $month<=12) and $current_semester % 2!=0)) {
			self::enroll_core_courses(self::$person_id);
			}
		}
	else	{
		$this->semester_ellapsed(self::$curr_semester);
		$semester=$current_semester+$this->sem;
		if($current_semester<self::$total_semesters) {
			return false;
			}
		}
	}
	
	protected function course_registration_open() {
		############checking if course enrollment closed#################
		$username=$this->pageObj->getUser()->username;
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
			return false;	
		}
		
		else {
			$start_date=strtotime($start_date);
			$end_date=strtotime($end_date);
			$today=strtotime(date("Y-m-d"));
			if($today>$end_date) {
				return false;
			}
		}
		return true;
		########### End checking of course enrollment deadline #####################		
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
		
		$GPA_exist=IHS_PageFormEnrollcourse::GPA_exist(self::$person_id,$semester,self::$student_registration["id"]);
		if(!$GPA_exist) {
			$this->sem=0;
			return;
			}
		$ac_year1=explode("/",$current_academic_year);
		$ac_year1=$ac_year1[0];
		$ff=I2CE_FormFactory::instance();
		$acYrObj=$ff->createContainer($enrolled_academic_year);
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
		$GPA_exist=IHS_PageFormEnrollcourse::GPA_exist($person_id,self::$curr_semester,self::$student_registration["id"]);
		if(($current_semester>self::$total_semesters or ($current_semester==self::$total_semesters and $GPA_exist)) and !$allow_increment)
		return self::$curr_semester;

		$ff=I2CE_FormFactory::instance();
		
		$regObj=$ff->createContainer(self::$student_registration["id"]);
		
		$total_semesters=self::get_total_semesters(self::$program,self::$admission_type);
		
		$semester_name=self::getSemesterName(self::$curr_semester);
		######if GPA for current semester available then increment the semester######
		$GPA_exist=IHS_PageFormEnrollcourse::GPA_exist($person_id,self::$curr_semester,self::$student_registration["id"]);

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
		self::manual_constructor($person_id);
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
					if(!isset($mark) or $mark < self::$passing_score)
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

	static function is_rescheduled($course,$academic_year,$curr_semester) {
		$pageObj=new I2CE_page;
		$username=$pageObj->getUser()->username;
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

	static function getSemesterName($semester) {
		$ff = I2CE_FormFactory::instance();
		$semObj=$ff->createContainer($semester);
		$semObj->populate();
		$semester=$semObj->getField("name")->getDBValue();		
		return $semester;
	}
	}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
