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
	class IHS_PageFormTransferAHTI extends I2CE_PageForm {
		/**
		* Create and load data for the objects used for this form.
		*/
	protected function action() {
		if($this->isPost() ) {
			$student_node=$this->template->getElementByID("student");
			if($this->pers_id!="")
			$this->template->appendFileByID("display_student.html","li","student",false,$student_node);
			else
			$this->template->appendFileByID("no_student.html","span","student",false,$student_node);
			}
		}
		protected function loadObjects() {
	//check to ensure that the current academic year is available
	iHRIS_AcademicYear::ensureAcademicYear();
	if(!$this->hasPermission("task(person_can_edit)" or $this->getUser()->role=="admin")) {
		//$this->setRedirect("noaccess");
		}
		if($this->isPost() ) {
			$id_num=$this->request("identification_number");
			if($id_num=="")
			return;
			$where=array(	"operator"=>"FIELD_LIMIT",
								"field"=>"identification_number",
								"style"=>"equals",
								"data"=>array("value"=>$id_num));
			$registration=I2CE_FormStorage::listFields("registration",array("parent"),false,$where);
			foreach($registration as $reg) {
				$this->pers_id = $reg["parent"];
				}
			if(!($persObj=$this->factory->createContainer($this->pers_id)) instanceof iHRIS_Person)
			return;
			$persObj->populate();
			$this->template->setForm($persObj);
			}
		}
	}
	# Local Variables:
	# mode: php
	# c-default-style: "bsd"
	# indent-tabs-mode: nil
	# c-basic-offset: 4
	# End:
