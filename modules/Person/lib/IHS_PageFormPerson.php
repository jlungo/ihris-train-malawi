<?php
/**
* © Copyright 2007 IntraHealth International, Inc.
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
*  iHRIS_PageFormPerson
* @package I2CE
* @subpackage Core
* @author Carl Leitner <litlfred@ibiblio.org>
* @copyright Copyright &copy; 2007 IntraHealth International, Inc. 
* This file is part of I2CE. I2CE is free software; you can redistribute it and/or modify it under 
* the terms of the GNU General Public License as published by the Free Software Foundation; either 
* version 3 of the License, or (at your option) any later version. I2CE is distributed in the hope 
* that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
* or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have 
* received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
* @version 2.1
* @access public
*/


class IHS_PageFormPerson extends I2CE_PageForm{
    /**
     * Create and load data for the objects used for this form.
     * 
     * Create the list object and if this is a form submission load
     * the data from the form data.
     */
    	
    protected function loadObjects() {
		$role=$this->getUser()->role;
		$deny=true;
		if(($role=="hod" and ($this->get_exists('id') or $this->isPost())) or $role=="registrar")
		$deny=false;
		
		else if($deny) {
			$this->userMessage("Only Registrar Can Add Students");
	     		$this->setRedirect(  "home" );
	     		return;
		}
			
	//check to ensure that the current academic year is available
	iHRIS_AcademicYear::ensureAcademicYear();
	$username=$this->getUser()->username;
	$inst_id=iHRIS_PageFormLecturer::fetch_institution($username);
	$factory = I2CE_FormFactory::instance();
     	if ($this->isPost()) {
	      	$person = $factory->createContainer("person|0");
	      	$person->load($this->post);
	      	$regObj = $factory->createContainer("registration");
	      	$regObj->load($this->post);
      	if($regObj->getField("joined_semester")->getDBValue()=="") {
      		$regObj->getField("joined_semester")->setFromDB("semester|1");
      		}
      	if($regObj->getField("semester")->getDBValue()=="") {
      		$regObj->getField("semester")->setFromDB("semester|1");
      		}
      	if($regObj->getField("academic_level")->getDBValue()=="") {
      		$regObj->getField("academic_level")->setFromDB("academic_level|1");
      		}
      	//validate identification type and citizenship
	      	$identification_type=$regObj->getField("identification_type")->getDBValue();
	      	$identification_number=$regObj->getField("identification_number")->getDBValue();
	      	$gender=$person->getField("gender")->getDBValue();
	      	$nationality=$person->getField("nationality")->getDBValue();
	      	$nationality=explode("|",$nationality);
/*
      	if($identification_type=="identification_type|national_id" and $nationality[1]!="BW")
      	$regObj->setInvalidMessage("identification_type","Only Botswana Citizen Is Having Omang Number");
*/

      	if($identification_type=="identification_type|national_id" and $identification_number!="") {	  
        if(strlen($identification_number) < 2 or strlen($identification_number) > 30)
	      	$regObj->setInvalidMessage('identification_number','Invalid Omang Number' );
	/*
	      	if($gender=="gender|F" and substr($identification_number,4,1)!=2)
	      		$regObj->setInvalidMessage('identification_number','Invalid RegNo Number,Gender Differs The RegNo Number' );
	      	if($gender=="gender|M" and substr($identification_number,4,1)!=1)
	      		$regObj->setInvalidMessage('identification_number','Invalid RegNo Number,Gender Differs The RegNo Number' );
	*/
	      }
      	$regObj->getField("training_institution")->setFromDB($inst_id);
      	$regObj->getField("registration_status")->setFromDB("registration_status|ongoing");
      	$program=$regObj->getField("training_program")->getDBValue();
      	$st_reg_num=self::generateRegistrationNumber($program,$inst_id);
      	if($regObj->getField("registration_number")->getDBValue()=="") {
      		$regObj->getField("registration_number")->setFromPost($st_reg_num);
      		$this->generated_regnum=true;
      		}
        	}
       else {
            if ($this->get_exists('id')) {
                $id = $this->get('id');
                if (strpos($id,'|')=== false) {
                    I2CE::raiseError("Depcreated use of id variable");
                    $id = 'person|' . $id;
                }
            } else {
                $id = 'person|0';
            }
            $person = $factory->createContainer($id);
            if($this->get_exists('id')) {
            	$person->getField("id")->setFromDB($id);
            	$person->populateChildren("registration");
            	foreach($person->getChildren("registration") as $regObj) {
            		
            		}
            	}
            else {
            	$regObj=$this->factory->createContainer("registration|0");
            	}
            
            if (!$person instanceof iHRIS_Person) {
                I2CE::raiseError("Could not create valid person form from id:$id");
                return;
            }
            $person->populate();
            $person->load($this->request());
        }
        $this->applyLimits($inst_id,$person,$regObj);
        $this->setObject( $person, I2CE_PageForm::EDIT_PRIMARY, null, true );
        $this->setObject( $regObj, I2CE_PageForm::EDIT_SECONDARY, null, true );
    }
	
	protected function applyLimits($inst_id,$person,$regObj) {
	$where=array(
						"operator"=>"FIELD_LIMIT",
						"field"=>"training_institution",
						"style"=>"like",
						"data"=>array("value"=>"%".$inst_id."%")
					 );
	$program_field=$regObj->getField("training_program");		
	$program_field->setOption(array("meta","limits","default","training_program"),$where);
	$where_title=array("operator"=>"OR",
								"operand"=>array(
														0=>array("operator"=>"FIELD_LIMIT",
																	"field"=>"name",
																	"style"=>"equals",
																	"data"=>array("value"=>"Mr")
																	),
														1=>array("operator"=>"FIELD_LIMIT",
																	"field"=>"name",
																	"style"=>"equals",
																	"data"=>array("value"=>"Ms")
																	),
														)
								);
	$title_field=$person->getField("title");		
	$title_field->setOption(array("meta","limits","default","title"),$where_title);
	
	if($this->getUser()->role=="registrar") {
		$where_semester=array("operator"=>"FIELD_LIMIT",
										"field"=>"name",
										"style"=>"equals",
										"data"=>array("value"=>"1"));
		$semester_field=$regObj->getField("semester");		
		$semester_field->setOption(array("meta","limits","default","semester"),$where_semester);
		$semester_field=$regObj->getField("joined_semester");		
		$semester_field->setOption(array("meta","limits","default","semester"),$where_semester);
		
		//limit level to 1
		$where_level=array("operator"=>"FIELD_LIMIT",
										"field"=>"name",
										"style"=>"equals",
										"data"=>array("value"=>"1"));
		$level_field=$regObj->getField("academic_level");		
		$level_field->setOption(array("meta","limits","default","academic_level"),$where_level);
		}
	$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
	$academic_year_id=iHRIS_AcademicYear::academicYearId($current_academic_year);
	$where=array(	"operator"=>"FIELD_LIMIT",
                  "field"=>"id",
                  "style"=>"equals",
                  "data"=>array("value"=>$academic_year_id));
 	$ac_field=$regObj->getField("academic_year");
 	$ac_field->setOption(array("meta","limits","default","academic_year"),$where);
	}
		
	static function generateRegistrationNumber($program,$inst_id){
	list($form,$prog_id)=array_pad(explode("|",$program,2),2,"");
	$program_name=I2CE_FormStorage::lookupField($form,$prog_id,array("name"),false);
	$program_name=$program_name["name"];
	
	list($institution_form,$institution_id)=array_pad(explode("|",$inst_id),2,"");
	$where=array(
						"operator"=>"FIELD_LIMIT",
						"field"=>"id",
						"style"=>"equals",
						"data"=>array("value"=>$institution_id)
					 );
	$last_reg_num=I2CE_FormStorage::lookupField($institution_form,$institution_id,array("last_reg_num","code"),false);
	$inst_code=$last_reg_num["code"];
	$last_reg_num=$last_reg_num["last_reg_num"];
	$reg_num=++$last_reg_num;
	$year=date("Y");
	$st_reg_num=$year."-".$inst_code."-".$reg_num;
	return $st_reg_num;	
	}
    /**
     * Save the objects to the database.
     * 
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {
      $persObj=$this->factory->createContainer("person");
      $persObj->load($this->post);
      $person_id=$persObj->getField("id")->getDBValue();
      $person_id=explode("|",$person_id);
      $person_id=$person_id[1];
        
      $regObj=$this->factory->createContainer("registration");
      $regObj->load($this->post);
      $registration_number=$regObj->getField("registration_number")->getDBValue();
      $training_institution=$regObj->getField("training_institution")->getDBValue();
        
        //save the new reg_number to institution form
      if($person_id==0) {
	      $instObj = $this->factory->createContainer($training_institution);
	      $instObj->populate();
	      $reg_num=explode("-",$registration_number);
         $reg_num=$reg_num[2];
         $instObj->getField("last_reg_num")->setValue($reg_num);
         $instObj->save($this->user);
         $userObj = $this->factory->createContainer( "user".'|'.$registration_number);
			$userObj->getField("username")->setFromPost($registration_number);
			$userObj->getField("firstname")->setFromPost($persObj->getField("firstname")->getDBValue());
			$userObj->getField("lastname")->setFromPost($persObj->getField("surname")->getDBValue());
			$userObj->getField("role")->setFromDB("role|student");
			$userObj->getField("password")->setFromPost($persObj->getField("surname")->getDBValue());
			$userObj->save($this->user);
			$userObj->cleanup();
	      }
			$persObj->save($this->user);
			$person_id=$persObj->getID();
			$persObj->cleanup();
			$regObj->setParent("person|".$person_id);
			$regObj->getField("registration_status")->setFromPost("registration_status|ongoing");
			$regObj->save($this->user);
			$regObj->cleanup();
        $message = "This record has been saved.";
        I2CE::getConfig()->setIfIsSet( $message, "/modules/forms/page_feedback_messages/person_save" );
        $this->userMessage($message);
        
		  $where=array(	"operator"=>"FIELD_LIMIT",
		  						"field"=>"parent",
		  						"style"=>"equals",
		  						"data"=>array("value"=>"person|".$person_id));
		  $user_map_exists=I2CE_FormStorage::search("user_map",false,$where);
		  if(count($user_map_exists)==0) {
			$usermapObj=$this->factory->createContainer("user_map");
			$usermapObj->getField("username")->setFromDB("user|".$registration_number);
			$usermapObj->getField("parent")->setFromDB("person|".$person_id);
			$usermapObj->save($this->user);
			$usermapObj->cleanup();
		  	}
		  
		  $pers_inst_exists=I2CE_FormStorage::search("person_instance",false,$where);
		  if(count($pers_inst_exists)==0) {
			  $person_instance=$this->factory->createContainer("person_instance");
			  $person_instance->getField("parent")->setFromDB("person|".$person_id);
			  $person_instance->save($this->user);
			  }
        IHS_PageFormEnrollcourse::enroll_core_courses("person|".$person_id);
        if($this->getUser()->role=="hod")
        $this->setRedirect("add_test_results?person_id=person|".$person_id);
        else
        $this->setRedirect("view?id=person|".$person_id);
    }       


}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
