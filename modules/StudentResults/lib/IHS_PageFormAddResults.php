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
	class IHS_PageFormAddResults extends I2CE_PageForm  {
	
	protected $course_id;
	protected $role;
	protected $exam_types=array();
	protected function action() {
		$this->ff = I2CE_FormFactory::instance();
		//update academic year
		iHRIS_AcademicYear::ensureAcademicYear();
		//retrive the academic year
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
		$academic_year_id="academic_year|".$academic_year_id;		
		$this->course_id=$this->request("id");
		$this->role=$this->getUser()->role;
		$username=$this->getUser()->username;
	   $inst_id=iHRIS_PageFormLecturer::fetch_institution($username);
		$where_course_id= array("operator"=>"AND","operand"=>array(
											0=>array('operator' => 'FIELD_LIMIT',
	                								'field' => 'training',
	                								'style' => 'like',
	                								'data' => array('value' => "%".$this->course_id."%")
	                                      ),
											1=>array('operator'=>'FIELD_LIMIT',
														'field'=>'academic_year',
														'style'=>'equals',
														'data'=>array('value'=>$academic_year_id)
													  )
													                             ));
		$parents=I2CE_FormStorage::listFields("enroll_course",array("parent","training","academic_year"),false,$where_course_id);
		$incompletes=I2CE_FormStorage::listFields("enroll_incomplete_course",array("parent","training","academic_year"),false,$where_course_id);
		
		if(is_array($parents) and is_array($incompletes)) {
			$parents=$parents+$incompletes;
			}
		else if(!is_array($parents) and is_array($incompletes)) {
			$parents=$incompletes;
			}
			
	    if (! ($listNode = $this->template->getElementByID("students_list")) instanceof DOMNode) {
		return ;
	        }
	    if (! ($hidden_data = $this->template->getElementByID("hidden_data")) instanceof DOMNode) {
		return ;
	        }
	
		//arrange $parents according to alphabetical order
		foreach($parents as $id=>$parent) {
			$crses=explode(",",$parent["training"]);
			if(!in_array($this->course_id,$crses)) {
				unset($parents[$id]);			
				continue;
				}
			$person_id=$parent["parent"];
			$reg_details=STS_PageFormPerson::load_current_registration($person_id);
			###drop students which are on different institution###
			if($inst_id!=$reg_details["training_institution"]) {
				unset($parents[$id]);			
				continue;
				}
			###End of dropping students which are on different institutions###
			
			###Drop students which have been assigned grades for this course by just being given a test###
			$courses_assigned_grades_based_ontest=IHS_PageFormAddResultsProcess::get_courses_assigned_grades_based_ontest($reg_details["id"]);
			$courses_assigned_grades_based_ontest=explode(",",$courses_assigned_grades_based_ontest);
			if(in_array($this->course_id,$courses_assigned_grades_based_ontest)) {
				unset($parents[$id]);			
				continue;
				}
			###End of Dropping students which have been assigned grades for this course by just being given a test###
			
			$persObj=$this->factory->createContainer($person_id);
			$persObj->populate();
			$curr_name=$persObj->firstname." ".$persObj->othername." ".$persObj->surname;
			$curr_name=strtolower($curr_name);
			$names[$person_id]=$curr_name;
			}
		array_values($parents);
		asort($names);
		foreach($names as $id=>$name) {
			foreach($parents as $enroll_id=>$parent) {
				if(in_array($id,$parent)) {
					$parents_id[$enroll_id]=$parent;
					continue;
					}
				}
			}
		//end of arrange $parents in alphabetical order
		
		if(count($parents)==0) {
			$this->userMessage("No students enrolled for this course!!!");
			$this->setRedirect("add_results_select_course");
			}
		
		$courseObj=$this->factory->createContainer($this->course_id);
		$courseObj->populate();
		if(!$this->check_results_upload_timeframe() and $this->role!="hod") {
			$title=$this->template->createElement("h1","","Results Upload Is Closed,Try Later On");
			$this->template->appendNode($title,$listNode);
			}
		$title=$this->template->createElement("h2","","Students Enrolled For ".$courseObj->code."-".$courseObj->name);
		$this->template->appendNode($title,$listNode);
		$input =$this->template->createElement("input",array("type"=>"hidden","name"=>"course_id","value"=>$this->course_id));
		$this->template->appendNode($input,$listNode);
		$table =$this->template->createElement("table",array("class"=>"multiFormTable","width"=>"100%","border"=>"0","cellpadding"=>"0","cellspacing"=>"0"));
		$tr =$this->template->createElement("tr");
		$th=$this->template->createElement("th",array("width"=>"30%"),"Student Name");
		$this->template->appendNode($th,$tr);
		$th=$this->template->createElement("th",array("align"=>"center"),"Registration Number");
		$this->template->appendNode($th,$tr);
		$this->appendExamTypesHeaders($tr);
		$this->template->appendNode($tr,$table);
		
			//retrieving the max mark for each assessment	
		foreach($this->exam_types as $exam_type) {
		list($form,$id) = array_pad(explode("|", $exam_type,2),2,'');
		
		list($form,$course_id) = array_pad(explode("|", $this->course_id,2),2,'');
		$max_mark=I2CE_FormStorage::lookupField("training",$course_id,array($id),false);
		$max_mark=$max_mark[$id];
		$input =$this->template->createElement("input",array("type"=>"hidden","name"=>$id,"id"=>$id,"value"=>$max_mark));	
		$this->template->appendNode($input,$listNode);
		}
		foreach ($parents_id as $enroll_id=>$parent) {
			$trainings=explode(",",$parent["training"]);
			$tr =$this->template->createElement("tr");
			$person_id=$parent["parent"];
			$reg_details=STS_PageFormPerson::load_current_registration($person_id);
			$reg_num=$this->getRegistrationNumber($person_id);
			$reg_id=$reg_details["id"];
			$input =$this->template->createElement("input",array("type"=>"hidden","name"=>"reg_id[".$reg_id."]","value"=>$reg_id));
			$this->template->appendNode($input,$tr);
			list($form,$id) = array_pad(explode("|", $person_id,2),2,'');
			$field_data = I2CE_FormStorage::lookupField("person",$id,array('firstname','surname'),false);
			if (is_array($field_data) && array_key_exists('surname',$field_data) && array_key_exists('firstname',$field_data)) {
				$fullname=$field_data['firstname'] . ' ' . $field_data['surname'];
	        	$aNode =$this->template->createElement("a",array("href"=>"view?id=" . $person_id),$fullname);
				$td =$this->template->createElement("td");
	        	$this->template->appendNode($aNode,$td);
	        	$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td",array("id"=>$reg_id,"align"=>"center"));
				$this->template->addTextNode($reg_id,$reg_num,$td);
	        	$this->template->appendNode($td,$tr);
				$this->appendExamTypesInput($tr,$reg_id,$person_id,$this->course_id,$parent["academic_year"],$enroll_id);
				}
			$this->template->appendNode($tr,$table);
		}
		$tr =$this->template->createElement("tr");
		$td =$this->template->createElement("td",array("colspan"=>"10","align"=>"right"));
		$input =$this->template->createElement("input",array("type"=>"submit","value"=>"Save","id"=>"save","onclick"=>"return verify()"));
		$this->template->appendNode($input,$td);
		$this->template->appendNode($td,$tr);
		$this->template->appendNode($tr,$table);
		$this->template->appendNode($table,$listNode);
	}
	
	protected function is_incomplete($course,$person_id,$registration,$semester) {
		$where=array("operator"=>"AND","operand"=>array(
									0=>array("operator"=>"FIELD_LIMIT",
												"field"=>"parent",
												"style"=>"equals",
												"data"=>array("value"=>$person_id)),
									1=>array("operator"=>"FIELD_LIMIT",
												"field"=>"training",
												"style"=>"equals",
												"data"=>array("value"=>$course)),
									2=>array("operator"=>"FIELD_LIMIT",
												"field"=>"semester",
												"style"=>"equals",
												"data"=>array("value"=>$semester)),
									3=>array("operator"=>"FIELD_LIMIT",
												"field"=>"registration",
												"style"=>"equals",
												"data"=>array("value"=>$registration))
												));
		$incompletes=I2CE_FormStorage::listFields("enroll_incomplete_course",array("training_course_exam_type","students_results_grade"),false,$where);
		$results=I2CE_FormStorage::search("students_results_grade",false,$where);
		if(count($incompletes)>0) {
			foreach($incompletes as $inc) {
				$this->incomplete_assessments=$inc["training_course_exam_type"];
				return $inc["students_results_grade"];
				}
			}
		}
		
	protected function check_results_upload_timeframe() {
		$username=$this->getUser()->username;
		$training_institution=IHS_PageFormLecturer::fetch_institution($username);
		$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"training_institution",
								"style"=>"equals",
								"data"=>array("value"=>$training_institution));
		$fields=I2CE_FormStorage::listFields("schedule_results_upload",array("start_date","end_date"),false,$where);
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
		}
		
	protected function appendExamTypesInput($tr,$reg_id,$person_id,$training_courses,$enroll_academic_year,$enroll_id) {
		$student_registration=STS_PageFormPerson::load_current_registration($person_id);
		$is_incomplete=$this->is_incomplete($training_courses,$person_id,
															$student_registration["id"],$student_registration["semester"]);
		if($is_incomplete) {
			$resultObj=$this->factory->createContainer($is_incomplete);
			$resultObj->populate();
			$enroll_id=$resultObj->getField("enroll_course")->getDBValue();
			$enrollObj=$this->factory->createContainer($enroll_id);
			$enrollObj->populate();
			$enroll_academic_year=$enrollObj->getField("academic_year")->getDBValue();
			iHRIS_AcademicYear::ensureAcademicYear();
			$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
			$current_academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
			$current_academic_year_id="academic_year|".$current_academic_year_id;
			$enroll_id=explode("|",$enroll_id);
			$enroll_id=$enroll_id[1];
			$incomplete_assessments=explode(",",$this->incomplete_assessments);
			}
		//check if this course is in the current semester enrollment,if not,make it readonly
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"semester",
																"style"=>"equals",
																"data"=>array("value"=>$student_registration["semester"])),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$person_id)),
												  2=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training",
																"style"=>"like",
																"data"=>array("value"=>"%".$training_courses."%")),
												  3=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$student_registration["id"])),
												));
		$enrolls=I2CE_FormStorage::listFields("enroll_course",array("training"),false,$where);
		foreach($enrolls as $id=>$enroll) {
			$enroll=explode(",",$enroll["training"]);
			if(!in_array($training_courses,$enroll))
			$enrolls=false;
			else
			$enrolls=true;
			}
		
		foreach ($this->exam_types as $exam_type) {
			//check if results available
			$inputNodeHidden=0;
			$results=$this->checkResults($exam_type,$person_id,$training_courses,$enroll_academic_year,$enroll_id);
			unset($mark);
			foreach($results as $results_id=>$mark)
			{
				//do nothing
			}
			
			/***handle students who are completing incomplete courses***/
			if($is_incomplete) {
				if(!in_array($exam_type,$incomplete_assessments) and $current_academic_year_id!=$enroll_academic_year) {
					$inputNode =$this->template->createElement("input",array(	"type"=>"text",
																									"maxlength"=>"5",
																									"class"=>"results",
																									"name"=>$exam_type."/".$reg_id,
																									"value"=>$mark,
																									"readonly"=>"true"));
				$inputNodeHidden =$this->template->createElement("input",array("type"=>"hidden",
																										"name"=>$reg_id."_results",
																										"value"=>$results_id));
				$this->template->appendNode($inputNodeHidden,$tr);
					}
				else {
					//allow lecturer to make ammendments if the results upload timeframe is not over
				if($this->check_results_upload_timeframe() and (($this->role=="lecturer" or 
																					$this->role=="registrar" or 
																					$this->role=="principal" or 
																					$this->role=="deputy_principal") or 
																					($this->role=="hod" and $this->is_assigned()))) {
					$inputNode =$this->template->createElement("input",array(	"type"=>"text",
																									"maxlength"=>"5",
																									"class"=>"results",
																									"name"=>$exam_type."/".$reg_id,
																									"value"=>$mark));
					}
				//Deny HOD to make ammendment when the results upload time frame is not over
				else if($this->check_results_upload_timeframe() and $this->role=="hod") {
					$inputNode =$this->template->createElement("input",array(	"type"=>"text",
																									"maxlength"=>"5",
																									"class"=>"results",
																									"name"=>$exam_type."/".$reg_id,
																									"value"=>$mark,
																									"readonly"=>"true"));
					}
				//Deny lecurer to make ammendments if the results upload timeframe is over
				else if(!$this->check_results_upload_timeframe() and (	$this->role=="lecturer" or 
																							$this->role=="registrar" or 
																							$this->role=="principal" or 
																							$this->role=="deputy_principal")) {
					$inputNode =$this->template->createElement("input",array(	"type"=>"text",
																									"maxlength"=>"5",
																									"class"=>"results",
																									"name"=>$exam_type."/".$reg_id,
																									"value"=>$mark,
																									"readonly"=>"true"));
					}
				//Allow HOD to make ammendments if the results upload timeframe is over
				else if(!$this->check_results_upload_timeframe() and $this->role=="hod") {
					$inputNode =$this->template->createElement("input",array("type"=>"text",
																				"maxlength"=>"5",
																				"class"=>"results",
																				"name"=>$exam_type."/".$reg_id,
																				"value"=>$mark));
					}
				$errorNode=$this->template->createElement("span",array("class"=>"error","id"=>$exam_type."/".$reg_id));
			if($results_id>0) {
				$inputNodeHidden =$this->template->createElement("input",array("type"=>"hidden","name"=>$reg_id."_results","value"=>$results_id));
				$this->template->appendNode($inputNodeHidden,$tr);
				}
					}
				}
			/***end of handling students who are completing incomplete courses***/
			
			else if(!$enrolls) {
				if($this->role=="hod")
				$inputNode =$this->template->createElement("input",array("type"=>"text","maxlength"=>"5","class"=>"results","name"=>$exam_type."/".$reg_id,"value"=>$mark));
				else
				$inputNode =$this->template->createElement("input",array("type"=>"text","maxlength"=>"5","class"=>"results","name"=>$exam_type."/".$reg_id,"value"=>$mark,"readonly"=>"true"));
				$inputNodeHidden =$this->template->createElement("input",array("type"=>"hidden","name"=>$reg_id."_results","value"=>$results_id));
				$this->template->appendNode($inputNodeHidden,$tr);
				}
				
			else {
				
				//allow lecturer to make ammendments if the results upload timeframe is not over
				if($this->check_results_upload_timeframe() and (($this->role=="lecturer" or $this->role=="registrar" or $this->role=="principal" or $this->role=="deputy_principal") or ($this->role=="hod" and $this->is_assigned()))) {
					$inputNode =$this->template->createElement("input",array("type"=>"text","maxlength"=>"5","class"=>"results","name"=>$exam_type."/".$reg_id,"value"=>$mark));
					}
				//Deny HOD to make ammendment when the results upload time frame is not over
				else if($this->check_results_upload_timeframe() and $this->role=="hod") {
					$inputNode =$this->template->createElement("input",array("type"=>"text","maxlength"=>"5","class"=>"results","name"=>$exam_type."/".$reg_id,"value"=>$mark,"readonly"=>"true"));
					}
				//Deny lecurer to make ammendments if the results upload timeframe is over
				else if(!$this->check_results_upload_timeframe() and ($this->role=="lecturer" or $this->role=="registrar" or $this->role=="principal" or $this->role=="deputy_principal")) {
					$inputNode =$this->template->createElement("input",array("type"=>"text","maxlength"=>"5","class"=>"results","name"=>$exam_type."/".$reg_id,"value"=>$mark,"readonly"=>"true"));
					}
				//Allow HOD to make ammendments if the results upload timeframe is over
				else if(!$this->check_results_upload_timeframe() and $this->role=="hod") {
					$inputNode =$this->template->createElement("input",array("type"=>"text","maxlength"=>"5","class"=>"results","name"=>$exam_type."/".$reg_id,"value"=>$mark));
					}
				$errorNode=$this->template->createElement("span",array("class"=>"error","id"=>$exam_type."/".$reg_id));
			if($results_id>0) {
				$inputNodeHidden =$this->template->createElement("input",array("type"=>"hidden","name"=>$reg_id."_results","value"=>$results_id));
				$this->template->appendNode($inputNodeHidden,$tr);
				}
				}
			$td =$this->template->createElement("td");
			$this->template->appendNode($errorNode,$td);
			$this->template->appendNode($inputNode,$td);		
			$this->template->appendNode($td,$tr);
			}
		}
	
		protected function is_assigned() {
			$username=$this->getUser()->username;
			$where=array(
								"operator"=>"FIELD_LIMIT",
								"field"=>"identification_number",
								"style"=>"equals",
								"data"=>array("value"=>$username)
							 );
			$lecturer=I2CE_FormStorage::listFields("lecturer",array("id"),false,$where);
			foreach($lecturer as $id=>$name)
			$lecturer_id="lecturer|".$id;
			$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
			$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
			$academic_year_id="academic_year|".$academic_year_id;
			$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"training",
																	"style"=>"like",
																	'data' => array('value' => "%".$this->course_id."%")),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"lecturer",
																	"style"=>"equals",
																	"data" => array("value" => $lecturer_id)),
													  2=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"academic_year",
																	"style"=>"equals",
																	"data"=>array("value"=>$academic_year_id))
													 ));
			$is_assigned=I2CE_FormStorage::listFields("assign_course_trainer",array("training"),false,$where);
			if(count($is_assigned)>0) {
				foreach($is_assigned as $assigned) {
					$courses=explode(",",$assigned["training"]);
					if(in_array($this->course_id,$courses))
					return true;
					}
				}
				return false;
			}
			
		protected function appendExamTypesHeaders($tr) {
			list($form,$id) = array_pad(explode("|", $this->course_id,2),2,'');
			$field_data = I2CE_FormStorage::lookupField($form,$id,array('training_course_exam_type'),false);
	
			$this->exam_types=explode(",",$field_data["training_course_exam_type"]);
			
			//arranging exam types according to what IHS wants
			$tests=array();
			$final=array();
			$assessments=array();
			foreach($this->exam_types as $id=>$exam_type) {
				$pos=strpos($exam_type,"test");
				if($pos!==false) {
					$tests[]=$exam_type;
					unset($this->exam_types[$id]);
					}
				}
	
			foreach($this->exam_types as $id=>$exam_type) {
				$pos=strpos($exam_type,"final");
				if($pos!==false) {
					$final[]=$exam_type;
					unset($this->exam_types[$id]);
					}
				}
			$assessments=array_merge($tests,$this->exam_types);
			$assessments_array=array_merge($assessments,$final);
			$this->exam_types=array();
			$this->exam_types=$assessments_array;
			
			foreach ($this->exam_types as $exam_type) {
				list($form,$id) = array_pad(explode("|", $exam_type,2),2,'');
				$field_data = I2CE_FormStorage::lookupField($form,$id,array('name'),false);
				$th=$this->template->createElement("th",array("width"=>"10","align"=>"center"),$field_data["name"]);
				$this->template->appendNode($th,$tr);
				}
			}
	
		protected function checkResults($exam_type,$person_id,$training_courses,$enroll_academic_year,$enroll_id) {
			$this->student_registration=STS_PageFormPerson::load_current_registration($person_id);
			$where=array("operator"=>"AND","operand"=>array(
															0=>array("operator"=>"FIELD_LIMIT",
															   		"field"=>"parent",
															   		"style"=>"equals",
															   		"data"=>array("value"=>$person_id)),
															1=>array("operator"=>"FIELD_LIMIT",
															   		"field"=>"training",
															   		"style"=>"equals",
												               	"data"=>array("value"=>$training_courses)), 														    	2=>array("operator"=>"FIELD_LIMIT",
															   		"field"=>"enroll_course",
															   		"style"=>"equals",
								                        		"data"=>array("value"=>"enroll_course|".$enroll_id))
						                                         )
						    );
			$id=I2CE_FormStorage::Search("students_results_grade",false,$where);	
			$id=$id[0];
			$resultsObj=$this->ff->createContainer("students_results_grade|".$id);
			$resultsObj->populateChildren("students_results");
			$result=array();
			
			foreach($resultsObj->getChildren("students_results") as $results) {
				$assessment=$results->getField("training_course_exam_type")->getDBValue();
				if($assessment==$exam_type) {
					$mark=$results->getField("score")->getDBValue();
					if($mark==-1)
					$mark="I";
					$results_id=$results->getField("id")->getDBValue();
					$result[$id]=$mark;				
					}
				}
			return $result;
	}
	
		protected function getRegistrationNumber($person_id) {
			$persObj=$this->ff->createContainer($person_id);
			$persObj->populateChildren("registration");
			foreach($persObj->getChildren("registration") as $regObj)
			return $regObj->getField("registration_number")->getDBValue();	
		}
	}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
