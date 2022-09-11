<?php
/*
 * © Copyright 2006, 2007, 2008, 2009 IntraHealth International, Inc.
 * 
 * This File is part of I2CE
 * 
 * I2CE is free software; you can redistribute it and/or modify
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
 * @author Ally Shaban <allyshaban5@yahoo.com>
 * @since v4.1.0
 * @version v4.1.0
 */
/**
 * iHRIS_Person class for the person form.
 *
 * @package iHRIS
 * @subpackage ihris-train
 */
class IHS_PageFormTrainingCourse extends I2CE_PageForm {

protected function loadObjects() {
	if(!$this->hasPermission("task(can_edit_database_list_training)")) {
			$this->setRedirect("noaccess");
			}
	$username=$this->getUser()->username;		
	$factory = I2CE_FormFactory::instance();
	if ($this->isPost()) {
		$traingObj=$this->factory->createContainer("training|0");
		$traingObj->populate();
		$traingObj->load($this->post);
		}
	else {
		if($this->request_exists("id")) {
			$id=$this->request("id");
			}
		else
		$id="training|0";
		$traingObj=$this->factory->createContainer($id);
		$traingObj->populate();
		$traingObj->load($this->request());
		}
	$this->applyLimits($traingObj);
	$this->setObject($traingObj,I2CE_PageForm::EDIT_PRIMARY,null,true);
	}
		
	protected function applyLimits($courses){
		$username=$this->getUser()->username;
	  	$inst_id=iHRIS_PageFormLecturer::fetch_institution($username);
	  	$limit_program=array("operator"=>"FIELD_LIMIT",
	  							"field"=>"training_institution",
	  							"style"=>"like",
	  							"data"=>array("value"=>"%".$inst_id."%")
	  						  );
	  	$trng_prgrms=iHRIS_PageFormEnrollcourse::get_institution_programs();
	  	$limit_course=array(	"operator"=>"FIELD_LIMIT",
	  									"field"=>"training_program",
	  									"style"=>"in",
	  									"data"=>array("value"=>$trng_prgrms)
	  								);
	  	$program_field=$courses->getField("training_program");
	  	$program_field->setOption(array("meta","limits","default","training_program"),$limit_program);
	  	$corequisite_field=$courses->getField("corequisite");
	  	$corequisite_field->setOption(array("meta","limits","default","training"),$limit_course);
	  	$prequisite_field=$courses->getField("prerequisite");
	  	$prequisite_field->setOption(array("meta","limits","default","training"),$limit_course);  	
		}

	protected function save() {
		$traingObj=$this->factory->createContainer("training|0");
		$traingObj->load($this->post);
		$traingObj->save($this->user);
		$this->userMessage("Course Added Successfully!!!");
		$this->setRedirect("add_training_course");
		}
}