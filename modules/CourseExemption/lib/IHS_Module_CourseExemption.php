<?php
/**
* © Copyright 2014 IntraHealth International, Inc.
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
* @package iHRIS
* @author Ally Shaban <allyshaban5@gmail.com>
* @version v3.2.2
* @since v3.2.2
* @filesource 
*/ 
/** 
* Class iHRIS_Module_CourseExemption
* 
* @access public
*/


class IHS_Module_CourseExemption extends I2CE_Module {

    public static function getMethods() {
        return array(
            'iHRIS_PageView->action_course_exemption' => 'action_course_exemption'
            			);
    }

    public function action_course_exemption($page) {
    	$template = $page->getTemplate();
		$appendNode = $template->getElementById('course_exemption');
		if (!$appendNode instanceof DOMNode) {
			return true;
	  		}
		$person = $page->getPerson();
		if (!$person instanceof iHRIS_Person) {
			return false;
			}
		$factory = I2CE_FormFactory::instance();
		$where=array(	"operator"=>"FIELD_LIMIT",
	  						"field"=>"parent",
	  						"style"=>"equals",
	  						"data"=>array("value"=>"person|".$person->getId())
	  				    );
		$courseExemObj=I2CE_FormStorage::search("course_exemption",false,$where);
		$crsexemp = array();
		foreach ($courseExemObj as $id) {
            $courseExemForm = $factory->createContainer('course_exemption|'.$id);
            $courseExemForm->populate();            
            $crsexemp[] = $courseExemForm;
        }
      if(count($crsexemp)==0) {
        	return false;
        	}
      
      foreach ($crsexemp as $child) {
      		$date_exempted=$child->getField("date_exempted")->getDBValue();
            $node = $template->appendFileByNode('view_course_exemption.html', 'div',  $appendNode );
            if (!$node instanceof DOMNode) {
                I2CE::raiseError("Could not find template $template for child form $form of person");
                return false;
            }
            $template->setForm($child,$node);
            $persObj=$factory->createContainer("person|".$person->getId());
            $persObj->populateChildren("registration");
            foreach($persObj->getChildren("registration") as $regObj) {
            	$date_registered=$regObj->getField("registration_date")->getDBValue();
            	}
            if($date_registered <= $date_exempted)
            $template->appendFileById('add_drop_course_exemption.html', 'li','edit',false, $node );
        }
    }
}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
