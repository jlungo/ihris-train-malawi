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
 * View a person's record.
 * @package iHRIS
 * @subpackage Qualify
 * @access public
 * @author Luke Duncan <lduncan@intrahealth.org>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @since v2.0.0
 * @version v2.0.0
 */

/**
 * The page class for displaying the a person's record.
 * @package iHRIS
 * @subpackage Qualify
 * @access public
 */
class IHS_PageView extends iHRIS_PageView {
	
	protected function action() {
		iHRIS_AcademicYear::ensureAcademicYear();
		###call the autoincrement page to increment student semester and autoregister core courses,if its new semester###
		$auto_cors_reg_ob=new IHS_PageFormAutoEnrollcourse;
		$auto_cors_reg_ob->action($this->request("id"));
		###End of auto course registration and semester increment###
		
		//execute various checkup processes for this student
		$processes=new IHS_PageFormProcesses;
		$processes->action($this->request("id"));
		
		parent::action();
		}
  }



# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
