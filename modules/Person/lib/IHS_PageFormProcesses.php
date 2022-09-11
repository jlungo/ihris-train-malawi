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
	class IHS_PageFormProcesses {
	public function action($person_id) {
		$this->person_id=$person_id;
		$this->pageObj=new I2CE_Page;
		$this->user=new I2CE_User;
		$this->ff=I2CE_FormFactory::instance();
		$this->manual_constructor($this->person_id);
		iHRIS_AcademicYear::ensureAcademicYear();
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$this->current_academic_year_id="academic_year|".$academic_year_id;

		###do nothing if already completed school###
		$completed_school=IHS_PageFormAutoEnrollcourse::completed_school($this->current_semester,$this->total_semesters,$this->person_id);
		if($completed_school)
		return;
		###End of checking completed students###
		
		###Do nothing if discontinued###
		$discontinued=IHS_PageFormAutoEnrollcourse::check_discontinue($this->person_id,$this->student_registration["id"]);
		if($discontinued)
		return false;
		###End of checking discontinued###
		
		//disco this student if failed to complete a course within 12 months
		$this->failed_complete_course_intwelve_months();
		
		$this->calculateGPA();
		}
	
	protected function manual_constructor() {
		$ff = I2CE_FormFactory::instance();
		$this->student_registration=STS_PageFormPerson::load_current_registration($this->person_id);
		$this->program=$this->student_registration["training_program"];
		$this->current_semester=$this->student_registration["semester"];
		$admission_type=$this->student_registration["admission_type"];
		$progObj=$this->ff->createContainer($this->program);
		$progObj->populate();
		if($admission_type=="admission_type|full-time")
		$this->total_semesters=$progObj->getField("total_semesters_fulltime")->getDBValue();
		else if($admission_type=="admission_type|part-time")
		$this->total_semesters=$progObj->getField("total_semesters_parttime")->getDBValue();
		$progObj->cleanup();
		}
		
	protected function failed_complete_course_intwelve_months() {
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"])),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"status",
																"style"=>"equals",
																"data"=>array("value"=>"status|incomplete"))
												 ));
		$results=I2CE_FormStorage::listFields("students_results_grade",array("training","academic_year","semester"),false,$where);
		//if no incomplete then do nothing
		if(count($results)==0)
		return;
		
		foreach($results as $result) {
			list($form,$course_semester)=array_pad(explode("|",$result["semester"],2),2,"");
			list($form,$student_curr_semester)=array_pad(explode("|",$this->current_semester,2),2,"");
			$acadYrObj=$this->ff->createContainer($result["academic_year"]);
			$acadYrObj->populate();
			$from_academic_year=$acadYrObj->getField("name")->getValue();
			//if two semesters have ellapsed without completing this incomplete then this is Discontinued
			$total_semesters_ellapsed=$this->total_semesters_ellapsed($course_semester,$from_academic_year);
			$total_semesters_dropped=$this->total_dropped_semesters($course_semester);
			if($total_semesters_dropped=="dropped")
			continue;
			if($total_semesters_ellapsed-$total_semesters_dropped>=2) {
				$month=date("m");
				$month=(int)$month;
				if($month>=7 and $month<=12) {
					$year1=date("Y");
					$year2=$year1-1;
					$academic_year=$year2."/".$year1;
					$date_disco=date($year1."-05-01");
					}
				else if($month>=1 and $month<=6) {
					$year1=date("Y");
					$year2=$year1-1;
					$academic_year=$year2."/".$year1;
					$date_disco=date($year2."-12-01");
					}
				$academic_year_id=iHRIS_AcademicYear::academicYearId($academic_year);
				$academic_year_id="academic_year|".$academic_year_id;
				$discoObj=$this->ff->createContainer("discontinued");
				$discoObj->getField("academic_year")->setFromDB($academic_year_id);
				$discoObj->getField("date_discontinued")->setFromDB($date_disco);
				$discoObj->getField("disco_reason")->setFromDB("disco_reason|incomplete");
				$discoObj->getField("recommendations")->setFromDB("recommendations|FD");
				$discoObj->getField("registration")->setFromDB($this->student_registration["id"]);
				$discoObj->getField("parent")->setFromDB($this->person_id);
				$discoObj->save($this->user);
				break;
				}
			}
		}
		
	protected function total_dropped_semesters($from_sem) {
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
		$drp_sem=I2CE_FormStorage::listFields("drop_semester",array("semester"),false,$where);
		foreach($drp_sem as $drp_id=>$drp) {
			list($form,$sem_drped)=array_pad(explode("|",$drp["semester"],2),2,"");
			if($from_sem>$sem_drped)
			continue;
			$drpObj=$this->ff->createContainer("drop_semester|".$drp_id);
			$drpObj->populate();
			$dropped_academic_year=$drpObj->getField("academic_year")->getDisplayValue();
			$drpObj->populateChildren("resume_semester");
			//do nothing if this student is not yet resumed from this semester
			if(count($drpObj->getChildren("resume_semester"))==0) {
				return "dropped";
				break;
				}
			foreach ($drpObj->getChildren("resume_semester") as $resObj) {
				$resumed_academic_year=$resObj->getField("academic_year")->getDisplayValue();
				$resumed_date=$resObj->getField("resume_date")->getDBValue();
				}

			list($res_year,$res_month,$res_day)=array_pad(explode("-",$resumed_date,3),3,"");
			$res_month=(int)$res_month;
			
			list($drp_year1,$drp_year2)=array_pad(explode("/",$dropped_academic_year,2),2,"");
			list($res_year1,$res_year2)=array_pad(explode("/",$resumed_academic_year,2),2,"");
			$ac_year_diff=$res_year1-$drp_year1;
			if($dropped_academic_year!=$resumed_academic_year) {
				if($sem_drped % 2==0)
				$sem=$sem+1;
				else if($sem_drped % 2!=0)
				$sem=$sem+2;
				
				if($res_month>=7 and $res_month<=12) {
	     			$sem=$sem+0;
	     			}
	     		else if($res_month>=1 and $res_month<=5) {
	     			$sem=$sem+1;
	     			}
	     		$sem=$sem+(($ac_year_diff-1)*2);
	     		}
	     	else {
	     		if(($sem_drped % 2!=0) and ($res_month>=1 and $res_month<=5)) {
     				$sem=$sem+1;
     				}
     			else
     				$sem=$sem+0;
	     		}
			}
			return $sem;
		}
		
	protected function total_semesters_ellapsed($from_sem,$from_academic_year) {
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$today=date("Y-m-d");
		$cur_month=(int)date("m");
		list($from_year1,$from_year2)=array_pad(explode("/",$from_academic_year,2),2,"");
		list($cur_year1,$cur_year2)=array_pad(explode("/",$current_academic_year,2),2,"");
		$ac_year_diff=$cur_year1-$from_year1;
		if($from_academic_year!=$current_academic_year) {
			if($from_sem % 2==0)
			$sem=$sem+0;
			else if($from_sem % 2!=0)
			$sem=$sem+1;
			
			if($cur_month>=7 and $cur_month<=12) {
     			$sem=$sem+0;
     			}
     		else if($cur_month>=1 and $cur_month<=6) {
     			$sem=$sem+1;
     			}
     		$sem=$sem+(($ac_year_diff-1)*2);
     		}
     	else {
  				$sem=$sem+0;
     		}

		return $sem;
		}
		
	protected function failed_retake_course_twice () {
		$training_courses=$this->retake_courses();
		if (count($training_courses)==0)
		return;
		
		foreach($training_courses as $course) {
			$recent=$this->recent_retake($course);
			$recent_retake_semester=$recent["semester"];
			$recent_retake_academic_year=$recent["academic_year"];
			
			$trngObj=$this->factory->createContainer($course);
			$trngObj->populate();
			$course_semester=$trngObj->getField("semester")->getDisplayValue();
			if($course_semester % 2==0)
			$reschedule_semester=$course_semester-1;
			else
			$reschedule_semester=$course_semester+1;
			
			$acadYrObj=$this->ff->createContainer($recent_retake_academic_year);
			$acadYrObj->populate();
			$recent_retake_academic_year=$acadYrObj->getField("name")->getValue();
			$academic_year=$recent_retake_academic_year;
			
			$total_semesters_ellapsed=$this->total_semesters_ellapsed($recent_retake_semester,$recent_retake_academic_year);
			//move through all semesters and check if didnt retake without valid reason
			$no_retake_total=0;
			for($k=$recent_retake_semester+1;$k<=$recent_retake_semester+$total_semesters_ellapsed;$k++) {
				//increment the academic year
				if($k % 2!=0) {
					list($ac_year1,$ac_year2)=array_pad(explode("|",$academic_year,2),2,"");
					$ac_year1=$ac_year1+1;
					$ac_year2=$ac_year2+1;
					$academic_year=$ac_year1."/".$ac_year2;
					}
					
				if(!$this->course_offered_in_semester($course,"semester|".$k,$academic_year))
				continue;
				$no_retake_total=$no_retake_total+1;
				}

				###count number of times HOD allowed this student not to retake with valid reason###
				$where=array(	"operator"=>"AND",
									"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"parent",
																		"style"=>"equals",
																		"data"=>array("value"=>$this->person_id)
																	 ),
														  1=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"registration",
																		"style"=>"equals",
																		"data"=>array("value"=>$this->student_registration["id"])
																	 ),
														  2=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"training",
																		"style"=>"like",
																		"data"=>array("value"=>"%".$course."%")
																	 )
														)
							   );
				
				$allowed=I2CE_FormStorage::listFields("retake_postponed",array("training"),false,$where);
				foreach($allowed as $allow) {
					$allow=explode(",",$allow["training"]);
					if(in_array($course,$allow))
					$total_allowed=$total_allowed+1;
					}
				###end of counting total allowed not retake with valid reasons###
				$dropped_semesters=$this->total_dropped_semesters($recent_retake_semester);
				if($dropped_semesters=="dropped")
				continue;
				$total_no_retake_without_reason=$no_retake_total-$total_allowed-$dropped_semesters;
				if($total_no_retake_without_reason>=2) {
					$month=date("m");
					$month=(int)$month;
					if($month>=7 and $month<=12) {
						$year1=date("Y");
						$year2=$year1-1;
						$academic_year=$year2."/".$year1;
						$date_disco=date($year1."-05-01");
						}
					else if($month>=1 and $month<=6) {
						$year1=date("Y");
						$year2=$year1-1;
						$academic_year=$year2."/".$year1;
						$date_disco=date($year2."-12-01");
						}
					$academic_year_id=iHRIS_AcademicYear::academicYearId($academic_year);
					$academic_year_id="academic_year|".$academic_year_id;
					$discoObj=$this->ff->createContainer("discontinued");
					$discoObj->getField("academic_year")->setFromDB($academic_year_id);
					$discoObj->getField("date_discontinued")->setFromDB($date_disco);
					$discoObj->getField("disco_reason")->setFromDB("disco_reason|retake");
					$discoObj->getField("recommendations")->setFromDB("recommendations|FE");
					$discoObj->getField("registration")->setFromDB($this->student_registration["id"]);
					$discoObj->getField("parent")->setFromDB($this->person_id);
					$discoObj->save($this->user);
					break;
					}
			}
		}
		
	protected function retake_courses() {
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
			if(in_array($result["training"],$training_courses))
			continue;
			$training_courses[]=$result["training"];
			}
			return $training_courses;
		}
		
	protected function recent_retake($course) {
		//get program courses that this student is to retake
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"])),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"status",
																"style"=>"equals",
																"data"=>array("value"=>"status|fail")),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"equals",
																"data"=>array("value"=>$course))
												 ));
		$results=I2CE_FormStorage::listFields("students_results_grade",array("semester","academic_year"),false,$where);
		$semester=0;
		foreach($results as $result) {
			list($form,$sem)=array_pad(explode("|",$result["semester"],2),2,"");
			if($sem>$semester) {
				$semester=$sem;
				$recent=array("semester"=>$sem,"academic_year"=>$result["semester"]);
				}
			}
		return $recent;
		}
	
	protected function course_offered_in_semester($course,$semester,$academic_year) {
		//if there exist results of even a single student for this course then this course was offered
		$academic_year=iHRIS_AcademicYear::academicYearId($academic_year);
		$academic_year="academic_year|".$academic_year;
		$courseObj=$this->factory->createContainer($course);
		$courseObj->populate();
		
		//detect the semester to which this course is assigned
		$course_semester=$courseObj->getField("semester")->getDBValue();
		list($form,$course_semester)=array_pad(explode("|",$course_semester,2),2,"");
		list($form,$checking_semester)=array_pad(explode("|",$semester,2),2,"");
		$month=date("m");
		if($course_semester % 2 ==0 and $checking_semester % 2!=0)
		$course_semester=$course_semester-1;
		else if($course_semester % 2 !=0 and $checking_semester % 2==0)
		$course_semester=$course_semester+1;
		$course_semester="semester|".$course_semester;
		
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training_institution",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["training_institution"])
															 ),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"equals",
																"data"=>array("value"=>$course)
															 ),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$course_semester)
															 ),
												  3=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"academic_year",
																"style"=>"equals",
																"data"=>array("value"=>$academic_year)
															 )
												)
						);
		$results=I2CE_FormStorage::search("students_results_grade",false,$where);
		if(count($results)>0)
		return true;
		else
		return false;
		}
	
	protected function calculateGPA() {
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"registration",
							"style"=>"equals",
							"data"=>array("value"=>$this->student_registration["id"])
						 );

		$enrolls=I2CE_FormStorage::listFields("enroll_course",array("semester","academic_year","training"),false,$where);
		foreach($enrolls as $enroll_id=>$enroll) {
			$enroll_id="enroll_course|".$enroll_id;
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"enroll_course",
								"style"=>"equals",
								"data"=>array("value"=>$enroll_id)
						 	 );
			$semester_GPA=I2CE_FormStorage::search("semester_GPA",false,$where);
			if(count($semester_GPA)==0) {
				$training_courses=explode(",",$enroll["training"]);
				foreach ($training_courses as $course) {
					$total_course_marks=$this->current_semester_results($course,$enroll_id);
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
			$GPA=number_format($total_quality_points/$total_credits,3);
			$GPAObj=$this->ff->createContainer("semester_GPA");
			$GPAObj->getField("semester")->setFromDB($enroll["semester"]);
			$GPAObj->getField("academic_year")->setFromDB($enroll["academic_year"]);
			$GPAObj->getField("enroll_course")->setFromDB($enroll_id);
			$GPAObj->getField("parent")->setValue($this->person_id);
			$GPAObj->getField("registration_number")->setFromPost($this->student_registration["registration_number"]);
			$GPAObj->getField("registration")->setFromDB($this->student_registration["id"]);
			$date_calc=date("Y-m-d");
			$GPAObj->getField("GPA")->setFromDB($GPA);
			$GPAObj->getField("date_calculated")->setFromDB($date_calc);
			$GPAObj->save($this->user);
			}
			}
		}
	
	protected function current_semester_results($course,$enroll_id) {
		$where=array("operator"=>"AND","operand"=>array(
		 			0=>array("operator"=>"FIELD_LIMIT",
								"field"=>"parent",
								"style"=>"equals",
								"data"=>array("value"=>$this->person_id)),
					1=>array("operator"=>"FIELD_LIMIT",
								"field"=>"enroll_course",
								"style"=>"equals",
								"data"=>array("value"=>$enroll_id)),
					2=>array("operator"=>"FIELD_LIMIT",
								"field"=>"training",
								"style"=>"equals",
								"data"=>array("value"=>$course)),
					3=>array("operator"=>"FIELD_LIMIT",
								"field"=>"registration",
								"style"=>"equals",
								"data"=>array("value"=>$this->student_registration["id"]))
				));
	$results=I2CE_FormStorage::listFields("students_results_grade",array("total_marks"),false,$where);
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
	}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
