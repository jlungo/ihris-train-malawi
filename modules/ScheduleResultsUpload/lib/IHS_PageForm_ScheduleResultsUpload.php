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
	class IHS_PageForm_ScheduleResultsUpload extends I2CE_PageForm {
	protected function loadObjects() {
		if(!$this->hasPermission("task(can_schedule_lecturer_results_upload)") or $this->getUser()->role=="admin") {
			$this->setRedirect("noaccess");
			}
		$factory = I2CE_FormFactory::instance();
		$username=$this->getUser()->username;
		$training_institution=IHS_PageFormLecturer::fetch_institution($username);
		$where=array(	"operator"=>"FIELD_LIMIT",
							"field"=>"training_institution",
							"style"=>"equals",
							"data"=>array("value"=>$training_institution));
		
		$fields=I2CE_FormStorage::search("schedule_results_upload",false,$where);
		foreach($fields as $id) {
		//do nothing
		}
		if($id)
		$form="schedule_results_upload|".$id;
		else
		$form="schedule_results_upload";	

		$resultsUpObj=$factory->createContainer($form);
		$resultsUpObj->populate();
		if($this->isPost())
		$resultsUpObj->load($this->post);
		$resultsUpObj->getField("training_institution")->setFromDB($training_institution);
		$this->setObject($resultsUpObj);
	}
	
	protected function save() {
		parent::save();
		$this->userMessage("Lecturer Results Upload Scheduled Successfully");
		$this->setRedirect( "schedule_results_upload");		
		}
	}
