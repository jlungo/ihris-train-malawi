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
	class IHS_PageFormTransferNonAHTI extends I2CE_PageForm {
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
			$persObj=$this->factory->createContainer("person");
			$persObj->populate();
			$regObj=$this->factory->createContainer("registration");
			$transferObj->load($this->post);
			$regObj->load($this->post);
			$persObj->load($this->post);
			$this->applyLimits($transferObj,$regObj);
			$regObj->getField("training_institution")->setFromDB($this->inst_id);
			$program=$regObj->getField("training_program")->getDBValue();
			$reg_num=IHS_PageFormPerson::generateRegistrationNumber($program,$this->inst_id);
			
			$regObj->getField("registration_number")->setValue($reg_num);
			$transferObj->getField("destination_semester")->setFromDB($regObj->getField("joined_semester")->getDBValue());
			$transferObj->getField("destination_level")->setFromDB($regObj->getField("academic_level")->getDBValue());
			$transferObj->getField("destination_institution")->setFromDB($this->inst_id);
			$transferObj->getField("destination_program")->setFromDB($program);
			$transferObj->getField("destination_admission_type")->setFromDB($regObj->getField("admission_type")->getDBValue());
			}
			
		else {
			$persObj=$this->factory->createContainer("person|0");
			$persObj->populate();
			$regObj=$this->factory->createContainer("registration|0");
			$regObj->populate();
			$transferObj=$this->factory->createContainer("transfer|0");
			$transferObj->populate();
			$this->applyLimits($transferObj,$regObj);
			}
			
			$this->setObject($transferObj, I2CE_PageForm::EDIT_PRIMARY, null, true);
			$this->setObject($persObj, I2CE_PageForm::EDIT_SECONDARY, null, true);
			$this->setObject($regObj, I2CE_PageForm::EDIT_SECONDARY, null, true);
		}
		
		protected function applyLimits($transferObj,$regObj) {
			$username=$this->getUser()->username;
			$this->inst_id=IHS_PageFormLecturer::fetch_institution($username);
			$id=explode("|",$this->inst_id);
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
								"data"=>array("value"=>"%".$this->inst_id."%")
							 );
			$progr_field=$transferObj->getField("destination_program");
			$progr_field->setOption(array("meta","limits","default","training_program"),$where);
			
			$progr_field=$regObj->getField("training_program");
			$progr_field->setOption(array("meta","limits","default","training_program"),$where);
		}
		
	protected function save() {
		$persObj=$this->factory->createContainer("person");
		$persObj->populate();
		$persObj->load($this->post);
		$persObj->save($this->user);
		$parent_id=$persObj->getID();
		$persObj->cleanup();
		$parent_id="person|".$parent_id;
		$regObj=$this->factory->createContainer("registration");
		$regObj->populate();
		$regObj->load($this->post);
		$regObj->setParent($parent_id);
		$regObj->getField("registration_status")->setFromPost("registration_status|ongoing");
		$regObj->save($this->user);
		$transferObj=$this->factory->createContainer("transfer");
		$transferObj->populate();
		$transferObj->load($this->post);
		$transferObj->setParent($parent_id);
		$transferObj->save($this->user);
		//increment registration number form institution form
		$reg_num=$regObj->getField("registration_number")->getDBValue();
		$institution = $this->factory->createContainer($this->inst_id);
      $institution->populate();
      $reg_num=explode("-",$reg_num);
      $reg_num=$reg_num[2];
      $institution->getField("last_reg_num")->setValue($reg_num);
      $institution->save($this->user);
      $regObj->cleanup();
      $persObj->cleanup();
      IHS_PageFormEnrollcourse::enroll_core_courses($parent_id);
		$this->userMessage("Student Transfered Successfully");
     	$this->setRedirect("view?id=".$parent_id);
		}
	}
	# Local Variables:
	# mode: php
	# c-default-style: "bsd"
	# indent-tabs-mode: nil
	# c-basic-offset: 4
	# End:
