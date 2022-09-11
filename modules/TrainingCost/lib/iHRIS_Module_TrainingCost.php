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
	* @author Juma Lungo <juma.lungo@zalongwa.com>
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
	class iHRIS_Module_TrainingCost extends I2CE_Module {
	public static function getMethods() {
		return array(
		    'iHRIS_PageView->action_person_traincost' => 'action_person_traincost'
		    'iHRIS_PageView->action_person_continuous_traincost' => 'action_person_continuous_traincost'
		    );
	    }
	 	 
	    public function action_person_profdev($obj) {
		if (!$obj instanceof iHRIS_PageView) {
		    return;
		}
		return $obj->addChildForms('person_traincost', 'siteContent');
	    }
	    public function action_person_continuous_profdev($obj) {
		if (!$obj instanceof iHRIS_PageView) {
		    return;
		}
		return $obj->addChildForms('person_continuous_traincost', 'siteContent');
	    }
	}
	# Local Variables:
	# mode: php
	# c-default-style: "bsd"
	# indent-tabs-mode: nil
	# c-basic-offset: 4
	# End:
