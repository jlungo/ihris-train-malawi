<?php
/*
 * © Copyright 2014 IntraHealth International, Inc.
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
 * Manage adding or editing forms associated with a person to the database.
 * 
 * @package iHRIS
 * @subpackage Common
 * @access public
 * @author Ally Shaban <allyshaban5@gmail.com>
 * @copyright Copyright &copy; 2014, 2008 IntraHealth International, Inc. 
 * @since v2.0.0
 * @version v2.0.0
 */

/**
 * Page object to handle the adding or editing forms associated with a person to the database.
 * 
 * @package iHRIS
 * @subpackage Common
 * @access public
 */
class IHS_PageFormCourseExemption extends iHRIS_PageFormParentPerson {
    /**
     * Create and load data for the objects used for this form.
     * 
     * Create the list object and if this is a form submission load
     * the data from the form data.  It determines the type based on the
     * {@link $type} member variable.
     */
    protected function loadObjects() {
        if ($this->isPost()) {
            $primary = $this->factory->createContainer($this->getForm());
            if (!$primary instanceof I2CE_Form) {
                return false;
            }
            $primary->load($this->post);
        } elseif ( $this->get_exists('id') ) {
            if ($this->get_exists('id')) {
                $id = $this->get('id');
                if (strpos($id,'|')=== false) {
                    I2CE::raiseError("Deprecated use of id variable");
                    $id = $this->getForm() . '|' . $id;
                }
            } else {
                $id = $this->getForm() . '|0';
            }
            $primary = $this->factory->createContainer($id);
            if (!$primary instanceof I2CE_Form || $primary->getName() != $this->getForm()) {
                I2CE::raiseError("Could not create valid " . $this->getForm() . "form from id:$id");
                return false;
            }
            $primary->populate();
        } elseif ( $this->get_exists('parent') ) {
        		$person_id=$this->request("parent");
        		$this->student_registration=STS_PageFormPerson::load_current_registration($person_id);
				###Deny course exemption for completed students###
				$progObj=$this->factory->createContainer($this->student_registration["training_program"]);
				$progObj->populate();
				if($this->student_registration["admission_type"]=="admission_type|full-time")
				$total_semesters=$progObj->getField("total_semesters_fulltime")->getDBValue();
				else if($this->student_registration["admission_type"]=="admission_type|part-time")
				$total_semesters=$progObj->getField("total_semesters_parttime")->getDBValue();
		    	$completed=IHS_PageFormEnrollcourse::completed_school(
		    																				$this->student_registration["semester"],
		    																				$total_semesters,$this->request("parent")
		    																			 );
		    	if($completed) {
		    		$this->userMessage("You Have Completed The Program");
		      	$this->setRedirect("view?id=" . $this->request("parent"));
		    		}
		    	###End of denying course exemption for completed students###
            $primary = $this->factory->createContainer($this->getForm());
            if (!$primary instanceof I2CE_Form) {
                return;
            }
            $parent = $this->get('parent');
            if (strpos($parent,'|')=== false) {
                I2CE::raiseError("Deprecated use of parent variable");
                $parent =  'person|' . $parent;            
            }
            $primary->setParent($parent);
        }
        if ($this->isGet()) {
            $primary->load($this->get());
        }
        $person = parent::loadPerson(  $primary->getParent() );
        if (!$person instanceof iHRIS_Person) {
            I2CE::raiseError("Could not create person form from " . $primary->getParent());
            return;
        }
        $person_id=$primary->getField("parent")->getDBValue();
        $this->student_registration=STS_PageFormPerson::load_current_registration($person_id);
        $this->applyLimits($primary);
        $this->setObject($primary, I2CE_PageForm::EDIT_PRIMARY, null, true);
        $this->setObject($person, I2CE_PageForm::EDIT_PARENT, null, true);
        return true;
    }

	protected function applyLimits($primary) {
		$username=$this->getUser()->username;
		$where_users=array(
							"operator"=>"FIELD_LIMIT",
							"field"=>"identification_number",
							"style"=>"equals",
							"data"=>array("value"=>$username)
							    );
		$insts=I2CE_FormStorage::listFields("lecturer",array("training_institution","department"),false,$where_users);
		foreach ($insts as $inst) {
			$institution_id=$inst["training_institution"];
			$dep_id=$inst["department"];	
			}
		
		$inst_id=explode("|",$institution_id);
		$inst_id=$inst_id[1];
				$where=array(
						"operator"=>"FIELD_LIMIT",
						"field"=>"id",
						"style"=>"equals",
						"data"=>array("value"=>$inst_id)
						       );
		$inst_field=$primary->getField("training_institution");
		$inst_field->setOption(array("meta","limits","default","training_institution"),$where);
		
		$where_deps=array(	"operator"=>"AND",
									"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"department",
																		"style"=>"equals",
																		"data"=>array("value"=>$dep_id)),
														  1=>array(	"operator"=>"FIELD_LIMIT",
																		"field"=>"training_institution",
																		"style"=>"like",
																		"data"=>array("value"=>"%".$institution_id."%"))
													    ));

		$programs=I2CE_FormStorage::search("training_program",false,$where_deps);
		foreach($programs as $progrm) {
			$training_programs[]="training_program|".$progrm;
			}
		$where=array(	"operator"=>"OR",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"training_program",
																"style"=>"in",
																"data"=>array("value"=>$training_programs)
															 ),
												  1=>array(	"operator"=>"AND",
												  				"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																									"field"=>"training_institution",
																									"style"=>"equals",
																									"data"=>array("value"=>$institution_id)
																			 		 			 ),
																  					  1=>array(	"operator"=>"FIELD_LIMIT",
																									"field"=>"course_type",
																									"style"=>"equals",
																									"data"=>array("value"=>"course_type|general_education")
																			 					 )
										 									 		 )
						 									 )));
		$training_field=$primary->getField("training");
		$training_field->setOption(array("meta","limits","default","training"),$where);
		}

    /**
     * Save the objects to the database.
     * 
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {
    	$exemptionObj=$this->factory->createContainer("course_exemption");
		$exemptionObj->load($this->post);
		$exemptionObj->getField("registration")->setFromDB($this->student_registration["id"]);
		$exemptionObj->save($this->user);
		$exemptionObj->cleanup();
      if ($saved !== false) {
         $message = "Course(s) Exempted Successfully.";
      } else {
         $message = "Failed To Exempt Course(s) This Student.";            
      }
      $this->userMessage($message);
      $this->setRedirect(  "view?id=" . $this->getPrimary()->getParent() );
      return $saved;
    }
                
}


# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
