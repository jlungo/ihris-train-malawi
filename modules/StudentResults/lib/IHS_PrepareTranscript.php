<?php
class IHS_PrepareTranscript{
	public $footer;
	public $instObj;
	protected $semester_names=array (
													"semester|1"=>"ONE",
													"semester|2"=>"TWO",
													"semester|3"=>"THREE",
													"semester|4"=>"FOUR",
													"semester|5"=>"FIVE",
													"semester|6"=>"SIX",
													"semester|7"=>"SEVEN",
													"semester|8"=>"EIGHT",
													"semester|9"=>"NINE",
													"semester|10"=>"TEN",
													);
	protected $grade_points=array(
							"A+"=>5,"A"=>4.9,"A-"=>4.7,"B+"=>4.5,"B"=>4,"B-"=>3.5,"C+"=>3,"C"=>2.5,"C-"=>2,"D+"=>1.5,"D"=>1,"D-"=>0.5,"E"=>0,"I"=>0
									  );
	public function action() {
		$this->ff = I2CE_FormFactory::instance();
		$this->person_id= $_REQUEST["id"];
		$this->pageObj= new I2CE_Page;
		$this->student_registration=STS_PageFormPerson::load_current_registration($this->person_id);
		if(!$this->verify_student()) {
			header("location:view?id=$this->person_id");
			}
		require("../modules/StudentResults/templates/en_US/transcript_description.php");
		$username=$this->pageObj->getUser()->username;
      $this->inst_id=iHRIS_PageFormLecturer::fetch_institution($username);
      $this->instObj=$this->ff->createContainer($this->inst_id);
      $this->instObj->populate();

      $this->display_logo();
		$this->display_registration ();
		$this->display_semester_GPA ();
		file_put_contents("/tmp/transcript.html", ob_get_contents());
		return true;
		}

	public function verify_student() {
		$return=$this->completed_school();
		$dont_break=$return;
		$return=$this->has_overallGPA();
		if($dont_break)
		$dont_break=$return;
		$return=$this->is_discontinued();
		if($dont_break)
		$dont_break=$return;
		$return=$this->check_credit_requirements();
		if($dont_break)
		$dont_break=$return;
		$return=$this->passed_all_core_courses();
		if($dont_break)
		$dont_break=$return;
		return $dont_break;
		}
	
	public function completed_school() {
		$training_program=$this->student_registration["training_program"];
		$admission_type=$this->student_registration["admission_type"];
		$trprgrmObj=$this->ff->createContainer($training_program);
		$trprgrmObj->populate();
		if($admission_type=="admission_type|full-time")
		$total_semesters=$trprgrmObj->getField("total_semesters_fulltime")->getDBValue();
		else if($admission_type=="admission_type|part-time")
		$total_semesters=$trprgrmObj->getField("total_semesters_parttime")->getDBValue();
		$current_semester=$this->student_registration["semester"];
		
		$current_semester=explode("|",$current_semester);
		$current_semester=$current_semester[1];
		if($current_semester >= $total_semesters) {
			if(IHS_PageFormEnrollcourse::passed_all_courses($this->person_id)) {
				return true;
				}
			}
		$this->pageObj->userMessage("Student Have Not Completed School,Cant Print Transcript");
		return false;
		}
		
	public function has_overallGPA() {
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
		$overallGPA=I2CE_FormStorage::search("overall_GPA",false,$where);
		if(count($overallGPA)>0)
		return true;
		else {
			$this->pageObj->userMessage("No Overall GPA Found For This Student,Cant Print Transcript");
			return false;
			}
		}
		
	public function check_credit_requirements() {
		$training_program=$this->student_registration["training_program"];
		$admission_type=$this->student_registration["admission_type"];
		$trprgrmObj=$this->ff->createContainer($training_program);
		$trprgrmObj->populate();
		if($admission_type=="admission_type|full-time")
		$total_semesters=$trprgrmObj->getField("total_semesters_fulltime")->getDBValue();
		else if($admission_type=="admission_type|part-time")
		$total_semesters=$trprgrmObj->getField("total_semesters_parttime")->getDBValue();
		$minimum_prgrm_credits=$trprgrmObj->getField("program_credits")->getDBValue();
		$break=false;
		//get all enrolled courses
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
		$enrolled=I2CE_FormStorage::listFields("enroll_course",array("training"),false,$where);
		$this->enroll_courses=array();
		foreach ($enrolled as $enroll_course) {
			$course=explode(",",$enroll_course["training"]);
			foreach($course as $crs) {
				$courses[]=$crs;
				}
			}
		foreach($courses as $course) {
			if(!in_array($course,$this->enroll_courses)) {
				$this->enroll_courses[]=$course;
				$courseObj=$this->ff->createContainer($course);
				$courseObj->populate();
				$total_credits=$total_credits+$courseObj->getField("course_credits")->getDBValue();
				$course_type=$courseObj->getField("course_type")->getDBValue();
				if($course_type=="course_type|core" or $course_type=="course_type|optional") {
					$total_core_optional_credits=$total_core_optional_credits+$courseObj->getField("course_credits")->getDBValue();
					}
				}
			}
		//add exempted courses in the computation of total credits
		$exempted_courses=array();
		$exempted_courses=IHS_PageFormEnrollcourse::load_exempted_courses($this->student_registration["id"],$this->person_id);
		foreach($exempted_courses as $course) {
			if(!in_array($course,$this->enroll_courses)) {
				$courseObj=$this->ff->createContainer($course);
				$courseObj->populate();
				$total_credits=$total_credits+$courseObj->getField("course_credits")->getDBValue();
				$course_type=$courseObj->getField("course_type")->getDBValue();
				if($course_type=="course_type|core" or $course_type=="course_type|optional") {
					$total_core_optional_credits=$total_core_optional_credits+$courseObj->getField("course_credits")->getDBValue();
					}
				}
			}
		//check if a student has met the total credits for this program
		if($total_credits < $minimum_prgrm_credits) {
			$this->pageObj->userMessage("Student Didn't Meet The Minimum Required Program Credits ($minimum_prgrm_credits),Cant Print Transcript");
			$break=true;
			}
		//check if 2/3 of these credits are coming from core and optional
		$two_third_total_credits=2/3*$total_credits;
		if($total_core_optional_credits < $two_third_total_credits) {
			$this->pageObj->userMessage("2/3 Of The Total Credits Are Not Coming From Core And Optional Courses,Cant Print Transcript");
			$break=true;
			}
		if($break)
		return false;
		else
		return true;
		}
	
	public function passed_all_core_courses() {
    		//load core courses that a student didnt register and are not exempted
    					$where=array(	"operator"=>"AND",
								"operand"=>array(
												0=>array("operator"=>"FIELD_LIMIT",
															"field"=>"training_program",
															"style"=>"equals",
															"data"=>array("value"=>$this->student_registration["training_program"])),
												1=>array("operator"=>"FIELD_LIMIT",
															"field"=>"course_type",
															"style"=>"equals",
															"data"=>array("value"=>"course_type|core"))
														));
			$courses=I2CE_FormStorage::search("training",false,$where);
			$unregistered_courses=false;
			foreach($courses as $course) {
				if(!in_array("training|".$course,$this->enroll_courses)) {
					$courseObj=$this->ff->createContainer("training|".$course);
					$courseObj->populate();
					$unregistered_courses=$unregistered_courses.$courseObj->getField("code")->getDBValue().",";
					}
				}
			if($unregistered_courses) {
				$this->pageObj->userMessage("Didn't Take Core Course(s)".$unregistered_courses."Cant Print Transcript");
				}
			
			//check failed core/required general education courses
			$failed_mandatory=false;
    		foreach ($this->enroll_courses as $course) {
    			$courseObj=$this->ff->createContainer($course);
    			$courseObj->populate();
    			$course_type=$courseObj->getField("course_type")->getDBValue();
    			if($course_type=="course_type|core") {
    				$where=array(	"operator"=>"AND",
    									"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
    																		"field"=>"parent",
    																		"style"=>"equals",
    																		"data"=>array("value"=>$this->person_id)),
    													     1=>array(	"operator"=>"FIELD_LIMIT",
    																		"field"=>"training",
    																		"style"=>"equals",
    																		"data"=>array("value"=>$course)),
    														  2=>array(	"operator"=>"FIELD_LIMIT",
    																		"field"=>"status",
    																		"style"=>"equals",
    																		"data"=>array("value"=>"status|pass")),
    														  3=>array(	"operator"=>"FIELD_LIMIT",
    																		"field"=>"registration",
    																		"style"=>"equals",
    																		"data"=>array("value"=>$this->student_registration["id"]))
    														));
    				$results=I2CE_FormStorage::search("students_results_grade",false,$where);
    				if(count($results)==0) {
    					$failed_mandatory=$failed_mandatory.$courseObj->getField("code")->getDBValue().",";
    					}
    				}
    			}
    		if($failed_mandatory) {
    			$this->pageObj->userMessage("Failed Core Course(s)".$failed_mandatory."Cant Print Transcript");
    			}
    		
    		if($failed_mandatory or $unregistered_courses)
    		return false;
    		else
    		return true;
    		}
    		
	public function is_discontinued() {
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
    	$disco=I2CE_FormStorage::search("discontinued",false,$where);
    	if(count($disco)>0) {
    		$this->pageObj->userMessage("This student has been discontinued!!!,Cant Print Transcript");
			return false;
    		}
    	return true;
		}
		
	protected function display_semester_GPA () {
		$persObj=$this->ff->createContainer($this->person_id);
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
		$semester=I2CE_FormStorage::search("semester_GPA",false,$where);
		$total_semesters=count($semester);
		$persObj->populateChildren("semester_GPA");
		foreach($persObj->getChildren("semester_GPA") as $semGPAObj) {
			if($semGPAObj->getField("registration")->getDBValue()==$this->student_registration["id"]) {
				$counter++;
				$count++;
				if($counter==3) {
					$this->display_logo();
					$this->display_registration ();
					$counter=1;
					}
				$enrollObj=$this->ff->createContainer($semGPAObj->getField("enroll_course")->getDBValue());
				$enrollObj->populate();
				
				if($count!=$total_semesters)
				echo "<table border='1' cellpadding='0' cellspacing='0' width='1200px' style='page-break-after: always'>";
				else if($count==$total_semesters)
				echo "<table border='1' cellpadding='0' cellspacing='0' width='1200px'>";
				echo "<tr bgcolor='#D9D9D9'>";
					echo "<th colspan='5' style='border-right:none'>SEMESTER ".$this->semester_names[$semGPAObj->getField("semester")->getDBValue()]."</th>";
					echo "<th colspan='4' style='border-left:none'>Total Credits Registered: ".$enrollObj->getField("total_credits")->getDBValue()."</th>";
				echo "</tr>";
				
				echo "<tr bgcolor='#D9D9D9'>";
					echo "<th width='100px'>Course Code</th><th width='350px'>Course Description</th><th colspan='2'>Theory</th><th colspan='2'>Practice</th>
							<th width='50px'>Grade</th><th width='80px' align='center'>Grade Point</th><th width='85px'>Quality Points</th>";
				echo "</tr>";
				echo "<tr bgcolor='#D9D9D9'>";
					echo "<td colspan='2'></td><td width='30px' align='center'>Cr</td><td width='30px' align='center'>Hr</td><td width='30px' align='center'>Cr</td><td width='30px' align='center'>Hr</td><td colspan='3'></td>";
				echo "</tr>";
				
				$total_practice_cr=0;
				$total_practice_hr=0;
				$total_theory_cr=0;
				$total_theory_hr=0;
				$total_quality_points=0;
				$persObj->populateChildren("students_results_grade");
				foreach($persObj->getChildren("students_results_grade") as $resultsgradeObj) {
					if($semGPAObj->getField("enroll_course")->getDBValue()!=$resultsgradeObj->getField("enroll_course")->getDBValue())
					continue;
					
					$courseObj=$this->ff->createContainer($resultsgradeObj->getField("training")->getDBValue());
					$courseObj->populate();
					$course_description=$courseObj->name;
					$course_code=$courseObj->code;
					if($courseObj->getField("course_type")->getDBValue()=="course_type|optional")
					$course_code=$courseObj->code."<sup>**</sup>";
					$exam_types=$courseObj->getField("training_course_exam_type")->getDBValue();
					$exam_types=explode(",",$exam_types);
					if(in_array("training_course_exam_type|clinical",$exam_types)) {
						$course_code=$courseObj->code."<sup>*</sup>";
						}
					$course_credits=$courseObj->course_credits;
					$practice_cr=$courseObj->practice_credits;
					$practice_hr=$courseObj->practice_hours;
					$theory_cr=$courseObj->theory_credits;
					$theory_hr=$courseObj->theory_hours;
					$grade=$resultsgradeObj->getField("grade")->getDBValue();
					$grade_point=$this->grade_points[$grade];
					$quality_points=$course_credits*$grade_point;
					$total_practice_cr=$total_practice_cr+$practice_cr;
					$total_practice_hr=$total_practice_hr+$practice_hr;
					$total_theory_cr=$total_theory_cr+$theory_cr;
					$total_theory_hr=$total_theory_hr+$theory_hr;
					$total_quality_points=$total_quality_points+$quality_points;
					echo "<tr>";
						echo "<td align='center'>$course_code</td><td>$course_description</td><td align='center'>$theory_cr</td><td align='center'>$theory_hr</td>
								<td align='center'>$practice_cr</td><td align='center'>$practice_hr</td><td align='center'>$grade</td><td align='center'>$grade_point</td><td align='center'>$quality_points</td>";
					echo "</tr>";
					}
				echo "<tr>";
					echo "<td>&nbsp;</td><td align='right'>TOTALS</td><td align='center'>$total_theory_cr</td><td align='center'>$total_theory_hr</td>
							<td align='center'>$total_practice_cr</td><td align='center'>$total_practice_hr</td><td colspan='2'>&nbsp;</td><td align='center'>$total_quality_points</td>";
				echo "</tr>";
				$GPA=$semGPAObj->getField("GPA")->getDBValue();
				//load semester status
				$persObj->populateChildren("semester_status");
				foreach($persObj->getChildren("semester_status") as $semStatusObj) {
					if($semGPAObj->getField("enroll_course")->getDBValue()!=$semStatusObj->getField("enroll_course")->getDBValue())
					continue;
					$semester_status=$semStatusObj->getField("status")->getDisplayValue();
					}
				
				//load pending courses
				$persObj->populateChildren("pending_courses");
				$pending_courses="";
				foreach($persObj->getChildren("pending_courses") as $pendingObj) {
					if($semGPAObj->getField("enroll_course")->getDBValue()!=$pendingObj->getField("enroll_course")->getDBValue())
					continue;
					$pending_courses=$pendingObj->getField("training")->getDisplayValue();
					}
				echo "<tr>";
					echo "<td colspan='2' align='right'>GPA:</td><td colspan='7' align='center'><b>$GPA</b></td>";
				echo "</tr>";
				echo "<tr>";
					echo "<td colspan='2' align='right'>Semester Result:</td><td colspan='7' align='center'><b>$semester_status</b></td>";
				echo "</tr>";
				echo "<tr>";
					echo "<td colspan='2' align='right'>Pending/Retake Course(s):</td><td colspan='7' align='center'><b>$pending_courses</b></td>";
				echo "</tr>";
				echo "</table><p><p>";
				}
			}
				$this->display_signature();
		}
	
	protected function display_registration () {
		$persObj=$this->ff->createContainer($this->person_id);
		$persObj->populate();
		$persObj->populateChildren("overall_GPA");
		//capturing overall GPA Object		
		foreach($persObj->getChildren("overall_GPA") as $overallGPAObj) {
			if($overallGPAObj->getField("registration")->getDBValue()==$this->student_registration["id"])
			break;
			}

		//calculating total attempted credits
		$persObj->populateChildren("enroll_course");
		foreach($persObj->getChildren("enroll_course") as $enrollObj) {
			if($enrollObj->getField("registration")->getDBValue()==$this->student_registration["id"]) {
				$enrolled_courses=$enrollObj->getField("training")->getDBValue();
				$enrolled_course=explode(",",$enrolled_courses);
				foreach($enrolled_course as $course) {
					if(in_array($course,$processed_course))
					continue;
					$courseObj=$this->ff->createContainer($course);
					$courseObj->populate();
					$total_credits_attempted=$total_credits_attempted+$courseObj->getField("course_credits")->getDBValue();
					$processed_course[]=$course;
					}
				}
			}
		$registration_date=date("d-m-Y",strtotime($this->student_registration["registration_date"]));
		$progObj=$this->ff->createContainer($this->student_registration["training_program"]);
		$progObj->populate();
		
		//retrieve the minimum credits
		$total_credits_required=$progObj->getField("program_credits")->getDBValue();
		
		$prog_name=$progObj->getField("name")->getDisplayValue();
		$prog_name=explode("In ",$prog_name);
		$prog_name=$prog_name[1];
		echo "<br><table border='1' cellspacing='0' cellpadding='0' width='900px'>";
		echo "<tr>";
			echo "<td style='border-bottom:none'>Registration Number</td>
			<td style='border-style:solid solid dotted solid'>".$this->student_registration["registration_number"]."</td>
			<td style='border-bottom:none'>Surname</td>
			<td style='border-style:solid solid dotted solid'>".$persObj->surname."</td></tr>";
		echo "<tr>";
			echo "<td style='border-bottom:none;border-top:none'>Date of Commencement</td>
			<td style='border-style:dotted solid dotted solid'>".$registration_date."</td>
			<td style='border-bottom:none;border-top:none'>Other Names</td>
			<td style='border-style:dotted solid dotted solid'>".$persObj->firstname. $persObj->othername."</td></tr>";
		echo "<tr>";
			echo "<td style='border-bottom:none;border-top:none'>Programme:</td>
			<td style='border-style:dotted solid dotted solid'>".$prog_name."</td>
			<td style='border-bottom:none;border-top:none'>Gender</td>
			<td style='border-style:dotted solid dotted solid'>".$persObj->getField("gender")->getDisplayValue()."</td></tr>";
		echo "<tr>";
			echo "<td style='border-bottom:none;border-top:none'><b>Award:</b></td>
			<td style='border-style:dotted solid dotted solid'><b>".$progObj->getField("program_category")->getDisplayValue()."</b></td>
			<td style='border-bottom:none;border-top:none'>Date of Birth:</td>
			<td style='border-style:dotted solid dotted solid'>".$persObj->date_of_birth->displayDate()."</td></tr>";
		echo "<tr>";
			echo "<td style='border-bottom:none;border-top:none'>Classification:</td>
			<td style='border-style:dotted solid dotted solid'>CREDIT</td>
			<td style='border-bottom:none;border-top:none'>Place of Birth:</td>
			<td style='border-style:dotted solid dotted solid'>".$persObj->getField("location")->getDisplayValue()."</td></tr>";
		echo "<tr>";
			echo "<td style='border-bottom:none;border-top:none'>Cumulative GPA:</td>
			<td style='border-style:dotted solid dotted solid'>".$overallGPAObj->getField("GPA")->getDBValue()."</td>
			<td style='border-bottom:none;border-top:none'>Country of Citizenship:</td>
			<td style='border-style:dotted solid dotted solid'>".$persObj->getField("nationality")->getDisplayValue()."</td></tr>";
		echo "<tr>";
			echo "<td style='border-bottom:none;border-top:none'>Total Credits Attempted:</td>
			<td style='border-style:dotted solid dotted solid'>".$total_credits_attempted."</td>
			<td style='border-bottom:none;border-top:none'>Sponsor:</td>";
		
		$sponsorObj=$this->ff->createContainer($this->student_registration["sponsor"]);
		$sponsorObj->populate();				
		echo "<td style='border-style:dotted solid dotted solid'>".$sponsorObj->name."</td></tr>";
		echo "<tr>";
			echo "<td style='none;border-top:none'>Total Credits Required:</td>
			<td style='border-style:dotted solid solid solid'>".$total_credits_required."</td>
			<td style='border-top:none'>Year of Award:</td>
			<td style='border-style:dotted solid solid solid'>".$overallGPAObj->getField("year")->getDisplayValue()."</td></tr>";
		echo "</table><br><br>";
		}
	
	protected function display_footer($page) {
		echo "<div style='page-break-after: always'><strong>Page $page</strong>: ";
		echo $this->instObj->address;
		echo "  ";
		echo "TEL:".$this->instObj->telephone;
		echo "  ";
		echo "FAX:".$this->instObj->fax;
		echo "  ";
		echo "EMAIL:".$this->instObj->email;
		echo "  ";
		echo "&copy;". date("Y");
		echo "</div>";
		}
	
	protected function display_logo() {
		$b64Src = "data:image/png;base64," . base64_encode($this->instObj->logo);
		echo "<center><strong style='font-size:20'>UNIVERSITY OF BOTSWANA/AFFILIATED HEALTH INSTITUTIONS</strong></center>";
		echo "<center><strong style='font-size:20'>".strtoupper($this->instObj->name)." COLLEGE OF NURSING</strong></center>";
		echo "<center><strong style='font-size:20'>OFFICIAL ACADEMIC TRANSCRIPT</strong></center>";
		echo "<center><img src='../images/kanye-logo.png' width='150'></center>";
		}
	
	protected function display_signature() {
		echo "<br><br><center><strong> ---------------------  End of Transcript  ---------------------</strong></center><br><br><br>";
		echo "<font style='padding-left:300'>Signed:___________________________<br>";
		echo "<font style='padding-left:400'>Registrar<br>";
		echo "<font style='padding-left:1000'>Official Date Stamp</font>";
		}
	}

$obj= new IHS_PrepareTranscript;
if($obj->action()) {
	header("location:download_transcript?inst_id=$obj->inst_id");
}
?>