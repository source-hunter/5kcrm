<?php

class ExamineMobile extends Action{
	/**
	 *	permission 未登录可访问
	 * 	allow 登录访问
	 **/
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

	//审批动态
	public function dynamic(){
		if($this->isPost()){
			$data['type'] = 'OpenSource';
			$this->ajaxReturn($data,'success',1);
		}else{
			$this->ajaxReturn('非法请求','非法请求',2);
		}
	}
}