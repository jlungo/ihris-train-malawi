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
	class IHS_PageFormTransferBankedCredits extends I2CE_PageForm {
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
				}
				
			$this->student_registration=STS_PageFormPerson::load_current_registration($this->person_id);
			$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"parent",
																	"style"=>"equals",
																	"data"=>array("value"=>$this->person_id)),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"destination_registration",
																	"style"=>"equals",
																	"data"=>array("value"=>$this->student_registration["id"]))
													 ));
			$transfers=I2CE_FormStorage::search("transfer",false,$where);
			if(count($transfers)!=1) {
				I2CE::raiseError ("Didn't return one transfer object");
				return;
				}
			$transfer_id="transfer|".$transfers[0];
			$this->transferObj=$this->factory->createContainer($transfer_id);
			$this->transferObj->populate();
			$this->carried_banked_credits=$this->get_carried_banked_credits($transfer_id);
			$already_exempted_courses=$this->get_exempted_courses($this->student_registration["id"]);
			if($this->isPost()) {
				$selected_banked_credits=$this->post["results"];
				$exempted_courses=$this->post["course"];
				if(is_array($selected_banked_credits)) {
					//check if there is anything that was previousely selected and now has not been selected as well as ignore the saved one
					if(is_array($this->carried_banked_credits)) {
							foreach($this->carried_banked_credits as $carried) {
								$carried=explode("|",$carried);
								if(in_array($carried[1],$selected_banked_credits)) {
									unset($selected_banked_credits[$carried[1]]);
									}
								else if(!in_array($carried[1],$selected_banked_credits)) {
									$where=array(	"operator"=>"FIELD_LIMIT",
														"field"=>"students_results_grade",
														"style"=>"equals",
														"data"=>array("value"=>"students_results_grade|".$carried[1])
													 );
									$banked_crdt=I2CE_FormStorage::search("banked_credits_carried",false,$where);
									if(count($banked_crdt)==1) {
										$bankedObj=$this->factory->createContainer("banked_credits_carried|".$banked_crdt[0]);
										$bankedObj->populate();
										$bankedObj->delete();
										}
									else {									
										I2CE::raiseError("Didn't return one banked credits id for results id ".$carried[1]);
										return;
										}
									}
								}
							}
					foreach($selected_banked_credits as $results_id) {
						$bankedObj=$this->factory->createContainer("banked_credits_carried");
						$bankedObj->getField("registration")->setFromDB($this->student_registration["id"]);
						$bankedObj->getField("students_results_grade")->setFromDB("students_results_grade|".$results_id);
						$bankedObj->getField("parent")->setFromDB($this->transferObj->getField("id")->getDBValue());
						$bankedObj->save($this->user);
						}
					}
					
					
					//start handling exempted courses
					$where=array(	"operator"=>"AND",
										"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																			"field"=>"parent",
																			"style"=>"equals",
																			"data"=>array("value"=>$this->person_id)),
															  1=>array(	"operator"=>"FIELD_LIMIT",
																			"field"=>"training_institution",
																			"style"=>"equals",
																			"data"=>array("value"=>$this->student_registration["training_institution"]))
															 ));
					$exemption=I2CE_FormStorage::search("course_exemption",false,$where);
					if(count($exemption)==1) {
						$exempObj=$this->factory->createContainer("course_exemption|".$exemption[0]);
						$exempObj->populate();
						}
					else if(count($exemption)==0) {
						$exempObj=$this->factory->createContainer("course_exemption");
						}
					else {
						I2CE::raiseError("Didn't return one exemption id");
						return;
						}
					
					if(is_array($exempted_courses)) {
						foreach($exempted_courses as $course_id) {
							$exempted[]="training|".$course_id;
							}
						$exempted_courses=implode(",",$exempted);
						$today=I2CE_Date::now();
						$exempObj->getField("registration")->setFromDB($this->student_registration["id"]);
						$exempObj->getField("training")->setFromDB($exempted_courses);
						$exempObj->getField("training_institution")->setFromDB($this->student_registration["training_institution"]);
						$exempObj->getField("parent")->setFromDB($this->person_id);
						$exempObj->getField("exemption_reason")->setFromDB("Did a relative course in a previous program");
						$exempObj->getField("date_exempted")->setFromPost($today);
						$exempObj->save($this->user);
						}
					else
					$exempObj->delete();
					$this->save();
				}

			$persObj=$this->factory->createContainer($this->person_id);
			$persObj->populate();
			$this->template->setForm($this->transferObj);
			$this->template->setForm($persObj);
		}
		
	protected function action() {
		$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"registration",
																	"style"=>"equals",
																	"data"=>array("value"=>$this->transferObj->getField("source_registration")->getDBValue())),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"status",
																	"style"=>"equals",
																	"data"=>array("value"=>"status|pass")),
													  2=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"parent",
																	"style"=>"equals",
																	"data"=>array("value"=>$this->person_id))
													 ));

			$students_results=I2CE_FormStorage::listFields("students_results_grade",array(
																														"training",
																														"status",
																														"grade",
																														"semester"
																													  ),false,$where);

			$table_node = $this->template->appendFileById("student_results_table.html","div","students_results");
			$counter=0;

		   foreach($students_results as $id=>$results) {
		   	$counter++;
		   	$trngObj=$this->factory->createContainer($results["training"]);
		   	$trngObj->populate();
		   	$semObj=$this->factory->createContainer($results["semester"]);
		   	$semObj->populate();
		   	$statusObj=$this->factory->createContainer($results["status"]);
		   	$statusObj->populate();
				if (! ($rows = $this->template->getElementByID("student_results_rows")) instanceof DOMNode)
				return ;
				$tr =$this->template->createElement("tr");
				$td =$this->template->createElement("td");
				if(in_array("students_results_grade|".$id,$this->carried_banked_credits))
				$checkbox=$this->template->createElement("input",array("type"=>"checkbox","checked"=>"checked","name"=>"results[".$id."]","value"=>$id));
				else
				$checkbox=$this->template->createElement("input",array("type"=>"checkbox","name"=>"results[".$id."]","value"=>$id));
				$this->template->appendNode($checkbox,$td);
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td","",$counter);
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td","",$trngObj->getField("code")->getDBValue()."-".
																		$trngObj->getField("name")->getDBValue());
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td","",$semObj->getField("name")->getDBValue());
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td","",$trngObj->getField("course_credits")->getDBValue());
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td","",$results["grade"]);
				$this->template->appendNode($td,$tr);
				$td =$this->template->createElement("td","",$statusObj->getField("name")->getDBValue());
				$this->template->appendNode($td,$tr);
				$this->template->appendNode($tr,$rows);
		   	}
		   	
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"training_program",
							"style"=>"like",
							"data"=>array("value"=>"%".$this->student_registration["training_program"]."%"));
		$courses=I2CE_formStorage::listFields("training",array("id","name","code","course_credits","semester","training_program"),false,$where);
		$table_node = $this->template->appendFileById("exempt_courses_table.html","div","exempt_courses");
		$counter=0;
		$exempted_courses=$this->get_exempted_courses($this->student_registration["id"]);
		foreach($courses as $id=>$course) {
			$progrms=explode(",",$course["training_program"]);
			if(!in_array($this->student_registration["training_program"],$progrms)) {
				unset($courses[$id]);
				continue;
				}
				
			$counter++;
	   	$semObj=$this->factory->createContainer($course["semester"]);
	   	$semObj->populate();
			if (! ($rows = $this->template->getElementByID("exempt_courses_rows")) instanceof DOMNode)
			return ;
			$tr =$this->template->createElement("tr");
			$td =$this->template->createElement("td");
			if(in_array("training|".$course["id"],$exempted_courses))
			$checkbox=$this->template->createElement("input",array("type"=>"checkbox",
																						"checked"=>"checked",
																						"name"=>"course[".$course["id"]."]",
																						"value"=>$course["id"]));
			else
			$checkbox=$this->template->createElement("input",array("type"=>"checkbox",
																						"name"=>"course[".$course["id"]."]",
																						"value"=>$course["id"]));
			$this->template->appendNode($checkbox,$td);
			$this->template->appendNode($td,$tr);
			$td =$this->template->createElement("td","",$counter);
			$this->template->appendNode($td,$tr);
			$td =$this->template->createElement("td","",$course["code"]."-".$course["name"]);
			$this->template->appendNode($td,$tr);
			$td =$this->template->createElement("td","",$semObj->getField("name")->getDBValue());
			$this->template->appendNode($td,$tr);
			$td =$this->template->createElement("td","",$course["course_credits"]);
			$this->template->appendNode($td,$tr);
			$this->template->appendNode($tr,$rows);
			}
			
		}
		
		protected function get_carried_banked_credits($transfer_id) {
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"parent",
								"style"=>"equals",
								"data"=>array("value"=>$transfer_id));
			$carried_array=I2CE_FormStorage::listFields("banked_credits_carried",array("students_results_grade"),false,$where);
			foreach($carried_array as $carried) {
				$carried_banked_credits[]=$carried["students_results_grade"];
				}
			return $carried_banked_credits;
			}
		
		protected function get_exempted_courses($registration) {
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"registration",
								"style"=>"equals",
								"data"=>array("value"=>$registration));
			$exemption_array=I2CE_FormStorage::listFields("course_exemption",array("training"),false,$where);
			foreach($exemption_array as $exemption) {
				$exempted_courses=explode(",",$exemption["training"]);
				}
			return $exempted_courses;
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
