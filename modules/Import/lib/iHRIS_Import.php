<?php
/*
 * © Copyright 2012 IntraHealth International, Inc.
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
 * @package iHRIS
 * @subpackage Manage
 * @access public
 * @author Ally Shaban <allyshaban5@gmail.com>
 * @copyright Copyright &copy; 2012 IntraHealth International, Inc. 
 * @since v4.1.4
 * @version v4.1.4
 */

/**
 * The page class for editing particpants for a training
 * @package iHRIS
 * @subpackage Manage
 * @access public
 */
 require_once 'import_aris.php';
	class iHRIS_Import extends I2CE_Page  {
	protected function action() {
		parent::action();
		if($this->isPost()) {
				$this->ff = I2CE_FormFactory::instance();
				move_uploaded_file($_FILES["file"]["tmp_name"],"/tmp/upload.xlsx");
				$file="/tmp/upload.xlsx";
				I2CE::raiseMessage("Loading from $file");
				$processor = new PersonalData_Import($file);
				$processor->run();
				$this->userMessage("Import Successfully");
        		$this->setRedirect("import");
				//I2CE::raiseError(print_r( $processor->getStats()));
		}
	}
	}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
