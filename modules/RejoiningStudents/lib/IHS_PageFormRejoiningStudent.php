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
	class IHS_PageFormRejoiningStudent extends I2CE_PageForm {
		/**
		* Create and load data for the objects used for this form.
		*/
	protected function action() {
		if($this->isPost() ) {
			$student_node=$this->template->getElementByID("student");
			//check if FD/FE and can rejoin
			if($this->recommendations and $this->rejoin) {
				$this->template->appendFileByID("display_canrejoin_student.html","li","student",false,$student_node);
				}
			else if($this->recommendations and !$this->rejoin) {
				$this->template->appendFileByID("display_cantrejoin_student.html","li","student",false,$student_node);
				$info_node=$this->template->getElementByID("info");
				if($this->recommendations=="recommendations|FD")
				$pending_sem=2-$this->sem;
				else if($this->recommendations=="recommendations|FE")
				$pending_sem=4-$this->sem;
				$info=$this->template->createElement("H2","","This Student Does Not Qualify To Rejoin The Institution Until After $pending_sem Semester(s)");
			$this->template->appendNode($info,$info_node);
				}
			else if($this->dropped) {
				if($this->semesters_elapsed > 10)
				$this->template->appendFileByID("display_dropped_student_10.html","li","student",false,$student_node);
				else
				$this->template->appendFileByID("display_dropped_student.html","li","student",false,$student_node);
				}
			else if($this->pers_id) {
				$this->template->appendFileByID("displays_student.html","div","student",false,$student_node);
				}
			else {
				$this->template->appendFileByID("no_student.html","span","student",false,$student_node);
				}
			
			}
		}
		protected function loadObjects() {
	//check to ensure that the current academic year is available
	iHRIS_AcademicYear::ensureAcademicYear();
	if(!$this->hasPermission("task(person_can_edit)" or $this->getUser()->role=="admin")) {
		//$this->setRedirect("noaccess");
		}
		if($this->isPost() ) {
			$this->disco=false;
			$this->dropped=false;
			$id_num=$this->request("identification_number");
			if($id_num=="")
			return;
			$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"identification_number",
																	"style"=>"equals",
																	"data"=>array("value"=>$id_num)),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"registration_status",
																	"style"=>"equals",
																	"data"=>array("value"=>"registration_status|ongoing"))
													 ));
			$registration=I2CE_FormStorage::listFields("registration",array("parent","registration_date"),false,$where);
			foreach($registration as $reg) {
				$pers_id = $reg["parent"];
				$this->date_registered=$reg["registration_date"];
				}
			
			//load current registration detailes
			$this->student_registration=STS_PageFormPerson::load_current_registration($pers_id);
			//check if this student is in the discontinue form
			$recommendations=$this->is_discontinued($pers_id);
			if($recommendations) {
				$this->recommendations=$recommendations;
				if($this->can_rejoin($recommendations)) {
					$this->rejoin=true;
					$this->pers_id=$pers_id;
					}
				}
			else if($this->is_dropped($pers_id)) {
				$this->semesters_elapsed=$this->semesters_elapsed();				
				$this->dropped=true;
				$this->pers_id=$pers_id;
				$this->template->setForm($this->dropObj);
				}
			else if($pers_id) {
				$this->pers_id=$pers_id;
				}

			if(!($persObj=$this->factory->createContainer($pers_id)) instanceof iHRIS_Person)
			return;
			$persObj->populate();
			$this->template->setForm($persObj);
			}
		}
		
	protected function is_discontinued($pers_id) {
		$recommendations=false;
		$where=array(	"operator"=>"AND",
								"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"parent",
																	"style"=>"equals",
																	"data"=>array("value"=>$pers_id)),
													  1=>array(	"operator"=>"FIELD_LIMIT",
																	"field"=>"registration",
																	"style"=>"equals",
																	"data"=>array("value"=>$this->student_registration["id"])),
													 ));
			$discontinued=I2CE_FormStorage::listFields("discontinued",array("recommendations","date_discontinued","academic_year"),false,$where);
			if(count($discontinued)>0) {
				foreach($discontinued as $disco) {
					$recommendations=$disco["recommendations"];
					$this->date_disco=$disco["date_discontinued"];
					$this->ac_yr_disco=$disco["academic_year"];
					}
				}
			return $recommendations;
		}
	
	protected function can_rejoin($recommendations) {
		$current_academic_year=iHRIS_AcademicYear::currentAcademicYear();
		$ac_year1=explode("/",$current_academic_year);
		$ac_year1=$ac_year1[0];
		$acYrObj=$this->factory->createContainer($this->ac_yr_disco);
		$acYrObj->populate();
		$disco_ac_year=$acYrObj->getField("name")->getDBValue();
		$ac_year2=explode("/",$disco_ac_year);
		$ac_year2=$ac_year2[0];
		$ac_year_diff=$ac_year1-$ac_year2;
		
		$rejoin_month=date("m");
		$disco_month=explode("-",$this->date_disco);
		$disco_month=$disco_month[1];
		//check the number of semesters passed since discontinued
		if($current_academic_year!=$disco_ac_year) {
     		if($disco_month>=7 and $disco_month<=12) {
     			$this->sem=$this->sem+1;
     			}
     		else if($disco_month>=1 and $disco_month<=6) {
     			$this->sem=$this->sem+0;
     			}
     			
     		if($rejoin_month>=7 and $rejoin_month<=12) {
     			$this->sem=$this->sem+0;
     			}
     		else if($rejoin_month>=1 and $rejoin_month<=6) {
     			$this->sem=$this->sem+1;
     			}
     		$this->sem=$this->sem+(($ac_year_diff-1)*2);
     		}
     	else {
     		$this->sem=0;
     		}
     		
     	if($recommendations=="recommendations|FD" and $this->sem >= 2) {
     		return true;
     		}
     	else if($recommendations=="recommendations|FE" and $this->sem >= 4) {
     		return true;
     		}
     	else {
     		return false;
     		}
		}
		
	protected function is_dropped($pers_id) {
		$where=array(	"operator"=>"AND",
							"operand"=>array(0=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"parent",
																"style"=>"equals",
																"data"=>array("value"=>$pers_id)),
												  1=>array(	"operator"=>"FIELD_LIMIT",
																"field"=>"registration",
																"style"=>"equals",
																"data"=>array("value"=>$this->student_registration["id"])),
												 ));
		$drp_sem=I2CE_FormStorage::search("drop_semester",false,$where);
		if(count($drp_sem)>0) {
			foreach($drp_sem as $drp_id) {
				$drpObj=$this->factory->createContainer("drop_semester|".$drp_id);
				$drpObj->populate();
				$drpObj->populateChildren("resume_semester");
				$resumed=false;
				foreach ($drpObj->getChildren("resume_semester") as $resObj) {
					$resumed=true;
					}
				if(!$resumed) {
					$this->dropObj=$drpObj;
					break;
					}
				}
			}
		else
		$resumed=true;
		if($resumed==false)
		return true;
		else
		return false;
		}
		
	protected function semesters_elapsed() {
     	$drp_sem_ac=$this->dropObj->getField("academic_year")->getDBValue();
     	
     	$drp_month=$this->dropObj->getField("drop_date")->getDBValue();
     	$res_sem_ac=iHRIS_AcademicYear::currentAcademicYear();;
     	
		$drpacObj=$this->factory->createContainer($drp_sem_ac);
		$drpacObj->populate();
		$drp_sem_ac_name=$drpacObj->getField("name")->getDBValue();
		$res_sem_ac_name=$res_sem_ac;
		$ac_year1=explode("/",$drp_sem_ac_name);
		$ac_year1=$ac_year1[0];
		$ac_year2=explode("/",$res_sem_ac_name);
		$ac_year2=$ac_year2[0];
		
		
		if($drp_sem_ac_name!=$res_sem_ac_name) {
     		$res_month=date("Y-m-d");
     		$drp_month=explode("-",$drp_month);
     		$drp_month=$drp_month[1];
     		$res_month=explode("-",$res_month);
     		$res_month=$res_month[1];
     		$ac_year_diff=$ac_year2-$ac_year1;
     		if($drp_month>=7 and $drp_month<=12) {
     			$sem=$sem+2;
     			}
     		else if($drp_month>=1 and $drp_month<=5) {
     			$sem=$sem+1;
     			}
     			
     		if($res_month>=7 and $res_month<=12) {
     			$sem=$sem+0;
     			}
     		else if($res_month>=1 and $res_month<=5) {
     			$sem=$sem+1;
     			}
     		$sem=$sem+(($ac_year_diff-1)*2);
     		}
     		
     	else {
			$sem=1;     		
     		}

     	return $sem;
		}
	}
	# Local Variables:
	# mode: php
	# c-default-style: "bsd"
	# indent-tabs-mode: nil
	# c-basic-offset: 4
	# End:
