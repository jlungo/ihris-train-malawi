<?php
/**
* © Copyright 2008 IntraHealth International, Inc.
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
*  iHRIS_Module_Bond
* @package I2CE
* @subpackage Core
* @author Carl Leitner <litlfred@ibiblio.org>
* @copyright Copyright &copy; 2008 IntraHealth International, Inc. 
* This file is part of I2CE. I2CE is free software; you can redistribute it and/or modify it under 
* the terms of the GNU General Public License as published by the Free Software Foundation; either 
* version 3 of the License, or (at your option) any later version. I2CE is distributed in the hope 
* that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
* or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have 
* received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
* @version 2.1
* @access public
*/


class iHRIS_Module_Bond extends I2CE_Module {

    public static function getMethods() {
        return array(
            'iHRIS_PageView->action_bond' => 'action_bond'
            );
    }

    /** 
     * Return the array of hooks available in this module.
     * @return array
     */
    public static function getHooks() {
        return array(
                'validate_form_bond' => 'validate_form_bond',
                );  
    }   



    public function action_bond($obj) {
        if (!$obj instanceof iHRIS_PageView) {
            I2CE::raiseError("invalid call");
            return false;
        }
        return $obj->addChildForms('bond');
    }


    /** 
     * Checks to make sure the end of applicability is after the start of applicability.
     * @param I2CE_Form $form
     */
    public function validate_form_bond( $form ) { 
        if ( $form->start_date->isValid() && $form->end_date->isValid() ) { 
            if ( $form->start_date->compare( $form->end_date ) < 0 ) { 
                $form->setInvalidMessage('end_date','bad_date');
            }   
        }   
    }   

}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
