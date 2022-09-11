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
	class IHS_PageFormTransferAHTIProcess extends I2CE_PageForm {
		/**
		* Create and load data for the objects used for this form.
		*/
		protected function loadObjects() {
		//check to ensure that the current academic year is available
		iHRIS_AcademicYear::ensureAcademicYear();
		if(!$this->hasPermission("task(person_can_edit)" or $this->getUser()->role=="admin")) {
			//$this->setRedirect("noaccess");
			}
		if($this->isPost()) {
			$transferObj=$this->factory->createContainer("transfer|0");
			$transferObj->load($this->post);
			$persObj=$this->factory->createContainer($transferObj->getParent());
			$persObj->populate();
			$student_registration=STS_PageFormPerson::load_current_registration($transferObj->getParent());
			$regObj=$this->factory->createContainer($student_registration["id"]);
			$regObj->populate();
			$transferObj->getField("source_institution")->setFromDB($regObj->getField("training_institution")->getDBValue());
			$transferObj->getField("source_program")->setFromDB($regObj->getField("training_program")->getDBValue());
			$transferObj->getField("source_semester")->setFromDB($regObj->getField("semester")->getDBValue());
			$transferObj->getField("source_registration")->setFromDB($student_registration["id"]);
			}
			
		if($this->request_exists("parent")) {
			$persObj=$this->factory->createContainer($this->request("parent"));
			$persObj->populate();
			
			$student_registration=STS_PageFormPerson::load_current_registration($this->request("parent"));
			$regObj=$this->factory->createContainer($student_registration["id"]);
			$regObj->populate();
			
			$transferObj=$this->factory->createContainer("transfer|0");
			$transferObj->populate();
			$transferObj->setParent($this->request("parent"));
			}
			
			$this->template->setForm($regObj);
			$this->setObject($transferObj, I2CE_PageForm::EDIT_PRIMARY, null, true);
        	$this->setObject($persObj, I2CE_PageForm::EDIT_PARENT, null, true);
        	$this->applyLimits($transferObj);
		}
		
		protected function applyLimits($transferObj) {
			$username=$this->getUser()->username;
			$inst_id=IHS_PageFormLecturer::fetch_institution($username);
			$id=explode("|",$inst_id);
			$id=$id[1];
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"id",
								"style"=>"equals",
								"data"=>array("value"=>$id)
							 );
			$inst_field=$transferObj->getField("destination_institution");
			$inst_field->setOption(array("meta","limits","default","training_institution"),$where);
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"training_institution",
								"style"=>"like",
								"data"=>array("value"=>"%".$inst_id."%")
							 );
			$progr_field=$transferObj->getField("destination_program");
			$progr_field->setOption(array("meta","limits","default","training_program"),$where);
		}
		
		protected function convertMatchingCourses ($new_inst,$student_registration) {
			$where_registration=array(	"operator"=>"FIELD_LIMIT",
														"field"=>"registration",
														"style"=>"equals",
														"data"=>array("value"=>$student_registration["id"])
								 					 );
			$forms=array("enroll_course","students_results_grade","course_exemption","pending_courses");
			foreach($forms as $form) {
				$courses=I2CE_FormStorage::listFields($form,array("training"),false,$where_registration);
				foreach($courses as $id=>$course) {
					$matching_training="";
					$training_array=explode(",",$course["training"]);
					foreach($training_array as $training) {
						$trainObj=$this->factory->createContainer($training);
						$trainObj->populate();
						$training_name=$trainObj->name;
						$training_code=$trainObj->code;
						$where=array(	"operator"=>"AND",
											"operand"=>array(	0=>array("operator"=>"FIELD_LIMIT",
																				"field"=>"name",
																				"style"=>"equals",
																				"data"=>array("value"=>$training_name)),
																	1=>array("operator"=>"FIELD_LIMIT",
																				"field"=>"code",
																				"style"=>"equals",
																				"data"=>array("value"=>$training_code)),
																	2=>array("operator"=>"FIELD_LIMIT",
																				"field"=>"training_institution",
																				"style"=>"equals",
																				"data"=>array("value"=>$new_inst))
																 ));
						$matching_courses=I2CE_FormStorage::search("training",false,$where);
						foreach($matching_courses as $matching_course) {
							$matching_course="training|".$matching_course;
							}
						if(count($matching_courses)==0)
						$matching_course=$training;
						if($matching_training=="")
						$matching_training=$matching_course;
						else
						$matching_training=$matching_training.",".$matching_course;
						}
					$formObj=$this->factory->createContainer($form."|".$id);
					$formObj->populate();
					$formObj->getField("training")->setFromPost($matching_training);
					$formObj->save($this->user);
					$formObj->cleanup();
					}
				}
			}
			
		protected function save() {
			$transferObj=$this->factory->createContainer("transfer|0");
			$transferObj->load($this->post);
			$transferObj->populate();
			$parent=$transferObj->getParent();
			$persObj=$this->factory->createContainer($parent);
			$persObj->populate();
			
			$student_registration=STS_PageFormPerson::load_current_registration($parent);
			
			$new_program=$transferObj->getField("destination_program")->getDBValue();
			$new_inst=$transferObj->getField("destination_institution")->getDBValue();
			$new_semester=$transferObj->getField("destination_semester")->getDBValue();
			$new_level=$transferObj->getField("destination_level")->getDBValue();
			$new_admission_type=$transferObj->getField("destination_admission_type")->getDBValue();
			$transfer_date=$transferObj->getField("transfer_date")->getDBValue();
			$transfer_academic_year=$transferObj->getField("academic_year")->getDBValue();
			$regObj=$this->factory->createContainer($student_registration["id"]);
			$regObj->populate();
			$current_prog=$regObj->getField("training_program")->getDBValue();
			$progObj=$this->factory->createContainer($current_prog);
			$progObj->populate();
			$current_prog_name=$progObj->name;
			$progObj->cleanup();
			$progObj=$this->factory->createContainer($new_program);
			$progObj->populate();
			$new_prog_name=$progObj->name;
			
			if($new_prog_name!=$current_prog_name) {
				//mark the ongoing registration as expired and create the new one
				$regObj->getField("registration_status")->setFromDB("registration_status|expired");
				$regObj->getField("expire_date")->setFromDB($transfer_date);
				$regObj->save($this->user);
				$regObj->cleanup();
				unset($regObj);
				
				//create the new registration
				$regObj=$this->factory->createContainer("registration");
				$regObj->populate();
				$regObj->getField("parent")->setFromDB($transferObj->getParent());
				$regObj->getField("admission_type")->setFromDB($new_admission_type);
				$regObj->getField("council_reg_num")->setFromDB($student_registration["council_reg_num"]);
				$regObj->getField("identification_number")->setFromDB($student_registration["identification_number"]);
				$regObj->getField("identification_type")->setFromDB($student_registration["identification_type"]);
				$regObj->getField("joined_semester")->setFromDB($new_semester);
				$regObj->getField("registration_number")->setFromDB($student_registration["registration_number"]);
				$regObj->getField("registration_status")->setFromDB("registration_status|ongoing");
				$regObj->getField("training_institution")->setFromDB($new_inst);
				$regObj->getField("training_program")->setFromDB($new_program);
				$regObj->getField("registration_date")->setFromPost($transfer_date);
				$regObj->getField("semester")->setFromDB($new_semester);
				$regObj->getField("academic_level")->setFromDB($new_level);
				$regObj->getField("academic_year")->setFromDB($transfer_academic_year);
				$regObj->save($this->user);
				$regObj->cleanup();
				}
			else {
				$regObj->getField("training_institution")->setFromDB($new_inst);
				$regObj->getField("admission_type")->setFromDB($new_admission_type);
				$regObj->save($this->user);
				$regObj->cleanup();
				//change the courses id to match those in the destination institution
				$this->convertMatchingCourses($new_inst,$student_registration);
				}
			$student_registration=STS_PageFormPerson::load_current_registration($transferObj->getParent());
			$transferObj->getField("destination_registration")->setFromDB($student_registration["id"]);
			parent::save();
			$this->userMessage("Student Transferred Successfully");
			if($new_prog_name!=$current_prog_name) {
				$this->setRedirect("transfer_banked_credits?person_id=".$transferObj->getParent());
				}
			else
	     	$this->setRedirect("view?id=".$transferObj->getParent());
			}
	}
	# Local Variables:
	# mode: php
	# c-default-style: "bsd"
	# indent-tabs-mode: nil
	# c-basic-offset: 4
	# End:
