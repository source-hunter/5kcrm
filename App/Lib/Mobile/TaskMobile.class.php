<?php
class TaskMobile extends Action {

	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array()
		);
		B('AppAuthenticate', $action);
		$this->_permissionRes = getPerByAction(MODULE_NAME,ACTION_NAME);
		Global $roles;
		$this->roles = $roles;
	}
	//任务列表
	public function index(){ 
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$data['permission_list'] = array();
			$data['list'] = array();
			$data['page'] = 1;
			$this->ajaxReturn($data,'success',1);
		}
	}
}