<?php
/**
* © Copyright 2009 IntraHealth International, Inc.
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
*
* @package ihris-common
* @author Ally Shaban <allyshaban5@gmail.com>
* @version v3.2
* @since v3.2
* @filesource
*/
/**
* Class iHRIS_Module_Lecturer
*
* @access public
*/


class IHS_Module_Lecturer extends I2CE_Module {


    /**
     * Return the array of hooks available in this module.
     * @return array
     */
    public static function getHooks() {
        return array(
                'validate_form_lecturer' => 'validate_form_lecturer',
                );
    }

    /**
     * Perform extra validation for the lecturer form.
     */
    public function validate_form_lecturer( $form ) {
    	if($form->identification_type[1]=="national_id" and $form->identification_number!="") {    		
  	 if(strlen($form->identification_number) < 2 or strlen($form->identification_number) > 30)
         $form->setInvalidMessage('identification_number','Invalid RegNo Number' );
	/*
		 if($form->gender[1]=="F" and substr($form->identification_number,4,1)!=2)
		 $form->setInvalidMessage('identification_number','Invalid RegNo Number,Gender Differs The RegNo Number' );
		 if($form->gender[1]=="M" and substr($form->identification_number,4,1)!=1)
		 $form->setInvalidMessage('identification_number','Invalid RegNo Number,Gender Differs The RegNo Number' );
	*/
       }            
    }

}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
