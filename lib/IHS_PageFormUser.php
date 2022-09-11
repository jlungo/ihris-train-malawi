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
*  IHS_PageFormUser
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


class IHS_PageFormUser extends I2CE_PageFormUser{
    
    /**
     * Perform the actions of the page.
     */
    protected function action() {
        parent::action();
        $this->template->setAttribute( "class", "active", "menuConfigure", "a[@href='configure']" );
        $this->template->appendFileById( "menu_configure.html", "ul", "menuConfigure" );
        $this->template->setAttribute( "class", "active", "menuUser", "a[@href='user']" );
    }
    
    protected function loadObjects() {
    	$factory = I2CE_FormFactory::instance();
        if ( $this->isPost() ) {
            $user = $factory->createContainer( "user".'|'.$this->post('username'));
            $user->load( $this->post );
            $this->setEditing();
            if ( !$this->isSave(false) ) {
                $user->tryGeneratePassword();
            }
            $user->getField("username")->setHref( "view_user?username=" );
            
            //assign all institutions to this user
            if($this->post("submit_type")=="save") {
            	//check if already added
            	$where=array(	"operator"=>"FIELD_LIMIT",
            						"field"=>"parent",
            						"style"=>"equals",
            						"data"=>array("value"=>$user->getField("username")->getValue())
            					 );
            	$access=I2CE_FormStorage::search("access_institution",false,$where);
            	$allowed_roles=array(	
            									"Ministry-IHS",
            									"Ministry-Administrator"
            								 );
            	if(count($access)==0 and in_array($user->getField("role")->getDisplayValue(),$allowed_roles)) {
		            $institutions=I2CE_FormStorage::search("training_institution");
		            foreach($institutions as $inst) {
		            	$training_institutions[]="training_institution|".$inst;
		            	}
		            $training_institutions=implode(",",$training_institutions);
		            $accInstObj=$factory->createContainer("access_institution");
		            $accInstObj->getField("parent")->setFromDB("user|".$user->getField("username")->getValue());
		            $accInstObj->getField("training_institution")->setFromDB($training_institutions);
		            $accInstObj->save($this->user);
	            	}
	            }
        } elseif ( $this->get_exists('username')) {
            $user = $factory->createContainer( "user".'|'.$this->get('username'));
            $user->populate( false );
            $this->setEditing();
            $user->getField("username")->setHref( "view_user?username=" );
        } else {
            $user = $factory->createContainer( "user".'|0');
        }
        $this->setObject( $user );
    	}
}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
