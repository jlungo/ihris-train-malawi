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
class IHS_PageFormTrainingProgram extends I2CE_PageForm {
	
protected function loadObjects() {
	if ($this->isPost()) {
			$program=$this->factory->createContainer("training_program");
			$program->load($this->post);
			$this->applyLimits($program);
			$program->getField("training_institution")->setFromDB($this->inst_id);
		}
	else {
		if($this->request_exists("id")) {
			$id=$this->request("id");
			}
		else
			$id="training_program";
		$program=$this->factory->createContainer($id);
		$program->populate();
		$program->load($this->request());
		$this->applyLimits($program);
		}
	$this->setObject($program,I2CE_PageForm::EDIT_PRIMARY);
	}
		
	protected function applyLimits($program){
	$username=$this->getUser()->username;
  	$this->inst_id=IHS_PageFormLecturer::fetch_institution($username);
  	$limit_add=array("operator"=>"FIELD_LIMIT",
  							"field"=>"training_institution",
  							"style"=>"like",
  							"data"=>array("value"=>"%".$this->inst_id."%")
  						 );

  	$dep_field=$program->getField("department");
  	$dep_field->setOption(array("meta","limits","default","department"),$limit_add);
  	
  	$inst=explode("|",$this->inst_id);
  	$inst_id=$inst[1];
  	$where=array(	"operator"=>"FIELD_LIMIT",
  						"field"=>"id",
  						"style"=>"equals",
  						"data"=>array("value"=>$inst_id)
  						 );
  	$inst_field=$program->getField("training_institution");
  	$inst_field->setOption(array("meta","limits","default","training_institution"),$where);
	}
	
	protected function save() {
		parent::save();
		$this->userMessage("Training Program Added Successfully!!!");
		$this->setRedirect("training_program");
		}
}