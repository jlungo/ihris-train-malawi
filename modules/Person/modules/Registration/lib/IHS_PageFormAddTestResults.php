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
	class IHS_PageFormAddTestResults extends I2CE_PageForm {
		/**
		* Create and load data for the objects used for this form.
		*/
		protected function loadObjects() {
			if($this->request_exists("person_id")) {
				$this->person_id=$this->request("person_id");
				}
			else if($this->isPost()) {
				$persObj=$this->factory->createContainer("person|0");
				$persObj->load($this->post);
				$this->person_id=$persObj->getField("id")->getDBValue();
				$this->student_registration=STS_PageFormPerson::load_current_registration($this->person_id);
				$this->current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
				$academic_year_id=iHRIS_AcademicYear::academicYearId($this->current_academic_year);
				$this->current_academic_year="academic_year|".$academic_year_id;
				
				/***start enrolling student to these courses***/
				foreach($this->post["total"] as $id=>$results) {
					if($results=="")
					continue;
					$selected_courses[]="training|".$id;
					}
					
				//lets keep records of which courses this student exempted by given tests
				$where=array(	"operator"=>"FIELD_LIMIT",
									"field"=>"registration",
									"style"=>"equals",
									"data"=>array("value"=>$this->student_registration["id"])
								 );
				$grade_based_on_test=I2CE_FormStorage::search("grade_based_on_test",false,$where);
				if(count($grade_based_on_test)>0) {
					$grade_based_on_testObj=$this->factory->createContainer("grade_based_on_test|".$grade_based_on_test[0]);
					$grade_based_on_testObj->populate();
					}
				else
				$grade_based_on_testObj=$this->factory->createContainer("grade_based_on_test");
				$selected_courses=implode(",",$selected_courses);
				$grade_based_on_testObj->getField("parent")->setFromDB($this->person_id);
				$grade_based_on_testObj->getField("registration")->setFromDB($this->student_registration["id"]);
				$grade_based_on_testObj->getField("training")->setFromDB($selected_courses);
				$grade_based_on_testObj->getField("training_institution")->setFromDB($this->student_registration["training_institution"]);
				$grade_based_on_testObj->save($this->user);
				$selected_courses=explode(",",$selected_courses);
				//end of keeping records for these courses
				
				$where=array(	"operator"=>"AND", 
						"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
						"field"=>"registration", 
						"style"=>"equals", 
						"data"=>array("value"=>$this->student_registration["id"])),
				  1=>array(	"operator"=>"FIELD_LIMIT",
						"field"=>"semester",
						"style"=>"equals",
						"data"=>array ("value"=>$this->student_registration["joined_semester"]))
				));
				$enroll=I2CE_FormStorage::search("enroll_course",false,$where);
				if(count($enroll)==0) {
					$training_courses=$selected_courses;
					$enrollObj=$this->factory->createContainer("enroll_course");
					}
				else if(count($enroll)==1) {
					$enrollObj=$this->factory->createContainer("enroll_course|".$enroll[0]);
					$enrollObj->populate();
					$enrolled_courses=$enrollObj->getField("training")->getDBValue();
					$enrolled_courses=explode(",",$enrolled_courses);
					$training_courses=array_merge($enrolled_courses,$selected_courses);
					}
				//count credits
				$training_courses=implode(",",$training_courses);
				$total_credits=$this->count_credits($training_courses);
				$today=date("Y-m-d");
				$enrollObj->getField("semester")->setFromDB($this->student_registration["joined_semester"]);
				$enrollObj->getField("training")->setFromDB($training_courses);
				$enrollObj->getField("academic_year")->setFromDB($this->current_academic_year);
				$enrollObj->getField("registration")->setFromDB($this->student_registration["id"]);
				$enrollObj->getField("date_enrolled")->setFromDB($today);
				$enrollObj->getField("total_credits")->setFromDB($total_credits);
				$enrollObj->getField("parent")->setFromDB($this->person_id);
				$enrollObj->save($this->user);
				$enroll_id="enroll_course|".$enrollObj->getID();
				/***End of enrolling students to courses***/
				
				/***start saving results***/
				foreach($this->post["total"] as $id=>$results) {
					if($results=="")
					continue;
					$where=array(	"operator"=>"AND",
										"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																			"field"=>"registration",
																			"style"=>"equals",
																			"data"=>array("value"=>$this->student_registration["id"])),
															  1=>array(	"operator"=>"FIELD_LIMIT",
																			"field"=>"training",
																			"style"=>"equals",
																			"data"=>array("value"=>"training|".$id))
															 ));
					$stud_res_grd=I2CE_FormStorage::search("students_results_grade",false,$where);
					if(count($stud_res_grd)>1)
					continue;
					else if(count($stud_res_grd)==0)
					$resultsObj=$this->factory->createContainer("students_results_grade");
					else if(count($stud_res_grd)==1)
					$resultsObj=$this->factory->createContainer("students_results_grade|".$stud_res_grd[0]);
					$resultsObj->populate();
					$resultsObj->getField("academic_year")->setFromDB($this->current_academic_year);
					$resultsObj->getField("training")->setFromDB("training|".$id);
					$resultsObj->getField("semester")->setFromDB($this->student_registration["joined_semester"]);
					$resultsObj->getField("registration_number")->setFromDB($this->student_registration["registration_number"]);
					$resultsObj->getField("enroll_course")->setFromDB($enroll_id);
					$resultsObj->getField("attempt")->setFromDB("1");
					$resultsObj->getField("date_uploaded")->setFromDB($today);
					$resultsObj->getField("total_marks")->setFromDB($results);
					$resultsObj->getField("grade")->setFromDB($this->post["grade"][$id]);
					$resultsObj->getField("recommendations")->setFromDB("recommendations|P");
					$resultsObj->getField("registration")->setFromDB($this->student_registration["id"]);
					$resultsObj->getField("status")->setFromDB("status|pass");
					$resultsObj->getField("parent")->setFromDB($this->person_id);
					$resultsObj->save($this->user);
					}
				/***End of saving results***/
				$this->add_pending($enroll_id);
				}
				
			$this->student_registration=STS_PageFormPerson::load_current_registration($this->person_id);
			$regObj=$this->factory->createContainer($this->student_registration["id"]);
			$regObj->populate();

			$persObj=$this->factory->createContainer($this->person_id);
			$persObj->populate();
			$this->template->setForm($regObj);
			$this->setObject($persObj, I2CE_PageForm::EDIT_PRIMARY, null, true);
			//$this->template->setForm($persObj);
		}
	
	protected function add_pending($enroll_id) {
		$all_courses=$this->get_all_courses();
		$enrollObj=$this->factory->createContainer($enroll_id);
		$enrolled_courses=$enrollObj->getField("training")->getDBValue();
		$enrolled_courses=explode(",",$enrolled_courses);
		$notenrolled_courses=array_diff($all_courses,$enrolled_courses);
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
		$pending_search=I2CE_FormStorage::search("pending_courses",false,$where);
		if(count($pending_search)==0) {
			$pendingObj=$this->factory->createContainer("pending_courses");
			$pending_courses=$notenrolled_courses;
			}
		else if(count($pending_search)==1) {
			$pendingObj=$this->factory->createContainer("pending_courses|".$pending_search[0]);
			$pendingObj->populate();
			$in_pending=$pendingObj->getField("training")->getDBValue();
			$in_pending=explode(",",$in_pending);
			foreach($in_pending as $id=>$pending) {
				if(in_array($pending,$enrolled_courses))
				unset($in_pending[$id]);
				}
			$pending_courses=array_merge($in_pending,$notenrolled_courses);
			}
		$pending_courses=implode(",",$pending_courses);
		$pendingObj->getField("training")->setFromDB($pending_courses);
		$pendingObj->getField("semester")->setFromDB($this->student_registration["joined_semester"]);
		$pendingObj->getField("registration_number")->setFromDB($this->student_registration["registration_number"]);
		$pendingObj->getField("registration")->setFromDB($this->student_registration["id"]);
		$pendingObj->getField("enroll_course")->setFromDB($enroll_id);
		$pendingObj->getField("academic_year")->setFromDB($this->current_academic_year);
		$pendingObj->getField("parent")->setFromDB($this->person_id);
		$pendingObj->save($this->user);
		}
		
	protected function count_credits($training_courses) {
		$training_courses=explode(",",$training_courses);
		foreach($training_courses as $course) {
			$courseObj=$this->factory->createContainer($course);
			$courseObj->populate();
			$total_credits=$total_credits+$courseObj->getField("course_credits")->getDBValue();
			}
		return $total_credits;
		}
	
	protected function get_all_courses() {
		list($form,$sem)=explode("|",$this->student_registration["joined_semester"]);
		for($k=1;$k<$sem;$k++) {
			$semesters[]="semester|".$k;
			}
		$program=$this->student_registration["training_program"];
		$institution=$this->student_registration["training_institution"];
		$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"semester",
																	"style"=>"in",
																	"data"=>array("value"=>$semesters)),
													  1=>array(	"operator"=>"OR",
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
																				)
													 )));

		$courses=I2CE_FormStorage::search("training",false,$where);
		foreach($courses as $course) {
			$training_courses[]="training|".$course;
			}
		return $training_courses;
		}
		
	protected function action() {
		list($form,$sem)=explode("|",$this->student_registration["joined_semester"]);
		$semesters=array();
		for($k=1;$k<$sem;$k++) {
			$semesters[]="semester|".$k;
			}
			if($sem==1) {
				$error_id = $this->template->getElementByID("error");
				$error_msg =$this->template->createElement("label","","This Action Is Possible For Only Students Joined In A Semester Higher Than 1");
				$this->template->appendNode($error_msg,$error_id);
				return;
				}
		$program=$this->student_registration["training_program"];
		$institution=$this->student_registration["training_institution"];
		$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"semester",
																	"style"=>"in",
																	"data"=>array("value"=>$semesters)),
													  1=>array(	"operator"=>"OR",
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
																				)
													 )));

			$courses=I2CE_FormStorage::listFields("training",array("semester","name","code","course_credits"),false,$where,array("semester"));
			$this->template->appendFileById("test_results_form.html","div","test_results_form");
			$table_node = $this->template->appendFileById("courses_table.html","div","test_results");
			$counter=0;

		   foreach($courses as $id=>$course) {
		   	$counter++;
		   	$semObj=$this->factory->createContainer($course["semester"]);
		   	$semObj->populate();
				if (! ($rows = $this->template->getElementByID("courses_rows")) instanceof DOMNode)
				return ;
				$results=$this->get_results("training|".$id);
				$tr =$this->template->createElement("tr");
				$td =$this->template->createElement("td");
				$td =$this->template->createElement("td","",$counter);
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td",array("width"=>"1000"),$course["code"]."-".$course["name"]);
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td","",$semObj->getField("name")->getDBValue());
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td","",$course["course_credits"]);
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td");
				if(count($results)>0) {
					$input=$this->template->createElement("input",array("type"=>"text","name"=>"total[".$id."]","value"=>$results["marks"]));
					$this->template->appendNode($input,$td);
					$this->template->appendNode($td,$tr);
					$td =$this->template->createElement("td");
					$input=$this->template->createElement("input",array("type"=>"text","name"=>"grade[".$id."]","value"=>$results["grade"]));
					$this->template->appendNode($input,$td);
					$this->template->appendNode($td,$tr);
					}
				else	{
					$input=$this->template->createElement("input",array("type"=>"text","name"=>"total[".$id."]"));
					$this->template->appendNode($input,$td);
					$this->template->appendNode($td,$tr);
					$td =$this->template->createElement("td");
					$input=$this->template->createElement("input",array("type"=>"text","name"=>"grade[".$id."]"));
					$this->template->appendNode($input,$td);
					$this->template->appendNode($td,$tr);
					}
				$this->template->appendNode($tr,$rows);
		   	}
		}
		
		protected function get_results($course) {
			$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"registration",
																	"style"=>"equals",
																	"data"=>array("value"=>$this->student_registration["id"])),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"semester",
																	"style"=>"equals",
																	"data"=>array("value"=>$this->student_registration["joined_semester"])),
													  2=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"training",
																	"style"=>"equals",
																	"data"=>array("value"=>$course))
													 ));
			$results=I2CE_FormStorage::listFields("students_results_grade",array("total_marks","grade"),false,$where);
			foreach($results as $result) {
				$result=array("marks"=>$result["total_marks"],"grade"=>$result["grade"]);
				}
			return $result;
			}

		protected function save() {
			$this->userMessage("Selected Credits Carried Successfully As Well As Exemption Done Successfully To Selected Courses");
			$this->setRedirect( "view?id=" . $this->person_id);
			}
	}
	# Local Variables:
	# mode: php
	# c-default-style: "bsd"
	# indent-tabs-mode: nil
	# c-basic-offset: 4
	# End:
