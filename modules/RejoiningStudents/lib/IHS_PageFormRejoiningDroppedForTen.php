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
	class IHS_PageFormRejoiningDroppedForTen extends I2CE_PageForm {
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
			$rejoinObj=$this->factory->createContainer("rejoin|0");
			$this->applyLimits($rejoinObj);
			$rejoinObj->load($this->post);
			$this->student_registration=STS_PageFormPerson::load_current_registration($rejoinObj->getParent());
			$persObj=$this->factory->createContainer($rejoinObj->getParent());
			$persObj->populate();
			$regObj=$this->factory->createContainer($this->student_registration["id"]);
			$regObj->populate();
			$this->template->setForm($regObj);
			
			$dropObj=$this->factory->createContainer("drop_semester");
			$dropObj->load($this->post);
			$this->template->setForm($dropObj);
			
			$rejoinObj->getField("prev_training_inst")->setFromDB($regObj->getField("training_institution")->getDBValue());
			$rejoinObj->getField("prev_training_prog")->setFromDB($regObj->getField("training_program")->getDBValue());
			$rejoinObj->getField("semester_ended")->setFromDB($regObj->getField("semester")->getDBValue());
			$rejoinObj->getField("rejoin_reason")->setFromDB("rejoin_reason|dropped_semester");
			}
			
		if($this->request_exists("id")) {
			$this->student_registration=STS_PageFormPerson::load_current_registration($this->request("parent"));
			$persObj=$this->factory->createContainer($this->request("parent"));
			$persObj->populate();
			$persObj->populateChildren("registration");
			foreach($persObj->getChildren("registration") as $regObj) {
				if($this->student_registration["id"]==$regObj->getField("id")->getDBValue()) {
					$this->template->setForm($regObj);
					break;				
					}
				}
			$dropObj=$this->factory->createContainer($this->request("id"));
			$dropObj->populate();
			$this->template->setForm($dropObj);
			$rejoinObj=$this->factory->createContainer("rejoin|0");
			$rejoinObj->populate();
			$rejoinObj->setParent($this->request("parent"));
			$this->applyLimits($rejoinObj);
			}
			$this->setObject($rejoinObj, I2CE_PageForm::EDIT_PRIMARY, null, true);
        	$this->setObject($persObj, I2CE_PageForm::EDIT_PARENT, null, true);
		}
		
		protected function applyLimits($rejoinObj) {
			$username=$this->getUser()->username;
			$inst_id=IHS_PageFormLecturer::fetch_institution($username);
			$id=explode("|",$inst_id);
			$id=$id[1];
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"id",
								"style"=>"equals",
								"data"=>array("value"=>$id)
							 );
			$inst_field=$rejoinObj->getField("new_training_inst");
			$inst_field->setOption(array("meta","limits","default","training_institution"),$where);
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"training_institution",
								"style"=>"like",
								"data"=>array("value"=>"%".$inst_id."%")
							 );
			$progr_field=$rejoinObj->getField("new_training_prog");
			$progr_field->setOption(array("meta","limits","default","training_program"),$where);
			
			$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
			$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"id",
								"style"=>"equals",
								"data"=>array("value"=>$academic_year_id)
							 );
			$ac_field=$rejoinObj->getField("academic_year_rejoin");
			$ac_field->setOption(array("meta","limits","default","academic_year"),$where);
		}
		
	protected function save() {
		$rejoinObj=$this->factory->createContainer("rejoin|0");
		$rejoinObj->load($this->post);
		$rejoinObj->populate();
		
		//mark this student resumed
		$dropObj=$this->factory->createContainer("drop_semester");
		$dropObj->load($this->post);
		$drop_id=$dropObj->getField("id")->getDBValue();
		$resObj=$this->factory->createContainer("resume_semester");
		$resObj->populate();
		$resObj->getField("academic_year")->setFromDB($rejoinObj->getField("academic_year_rejoin")->getDBValue());
		$resObj->getField("resume_date")->setFromDB($rejoinObj->getField("rejoin_date")->getDBValue());
		$resObj->getField("parent")->setFromDB($drop_id);
		$resObj->save($this->user);
		$resObj->cleanup();
		
		$current_prog=$this->student_registration["training_program"];
		$current_inst=$this->student_registration["training_institution"];
		$new_program=$rejoinObj->getField("new_training_prog")->getDBValue();
		$new_inst=$rejoinObj->getField("new_training_inst")->getDBValue();
		$new_semester=$rejoinObj->getField("rejoin_semester")->getDBValue();
		$new_admission_type=$rejoinObj->getField("new_admission_type")->getDBValue();
		$new_level=$rejoinObj->getField("rejoin_level")->getDBValue();
		$rejoin_date=$rejoinObj->getField("rejoin_date")->getDBValue();
		$rejoin_academic_year=$rejoinObj->getField("academic_year_rejoin")->getDBValue();
		
		//mark the ongoing registration as expired and create the new one
		$regObj=$this->factory->createContainer($this->student_registration["id"]);
		$regObj->populate();
		$regObj->getField("registration_status")->setFromDB("registration_status|expired");
		$regObj->getField("expire_date")->setFromDB($rejoin_date);
		$regObj->save($this->user);
		$regObj->cleanup();
		unset($regObj);
		
		//create the new registration
		$regObj=$this->factory->createContainer("registration");
		$regObj->populate();
		$regObj->getField("parent")->setFromDB($rejoinObj->getParent());
		$regObj->getField("admission_type")->setFromDB($new_admission_type);
		$regObj->getField("council_reg_num")->setFromDB($this->student_registration["council_reg_num"]);
		$regObj->getField("identification_number")->setFromDB($this->student_registration["identification_number"]);
		$regObj->getField("identification_type")->setFromDB($this->student_registration["identification_type"]);
		$regObj->getField("joined_semester")->setFromDB($new_semester);
		$regObj->getField("registration_number")->setFromDB($this->student_registration["registration_number"]);
		$regObj->getField("registration_status")->setFromDB("registration_status|ongoing");
		$regObj->getField("training_institution")->setFromDB($new_inst);
		$regObj->getField("training_program")->setFromDB($new_program);
		$regObj->getField("registration_date")->setFromPost($rejoin_date);
		$regObj->getField("semester")->setFromDB($new_semester);
		$regObj->getField("academic_level")->setFromDB($new_level);
		$regObj->getField("academic_year")->setFromDB($rejoin_academic_year);
		$regObj->save($this->user);
		$regObj->cleanup();
		
		parent::save();
		
		//enroll core courses for this semester
		IHS_PageFormEnrollcourse::enroll_core_courses($rejoinObj->getParent());
		
		$this->userMessage("Student Rejoined Successfully");
     	$this->setRedirect("view?id=".$rejoinObj->getParent());
		}
	}
	# Local Variables:
	# mode: php
	# c-default-style: "bsd"
	# indent-tabs-mode: nil
	# c-basic-offset: 4
	# End:
