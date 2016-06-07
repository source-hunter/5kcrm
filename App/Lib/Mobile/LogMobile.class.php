<?php
class LogMobile extends Action {
	
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('add','view','edit','delete')
		);
		B('AppAuthenticate', $action);
		$this->_permissionRes = getPerByAction(MODULE_NAME,ACTION_NAME);
		Global $role;
		$this->role = $role;
		Global $roles;
		$this->roles = $roles;
	}
	/*
	沟通日志创建
	module = customer 需添加沟通日志的模块
	*/
	public function add(){
		if($this->isPost()){
			$module = $_POST['module'];
			$id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			$params = json_decode($_POST['params'],true);
			if(!$id || !$module){
				$this->ajaxReturn('参数错误','参数错误',2);
			}
			if($module == 'customer'){
				$m_r = M('RCustomerLog');
			}elseif($module == 'business'){
				$m_r = M('RBusinessLog');
			}elseif($module == 'leads'){
				$m_r = M('RLeadsLog');
			}
			$m_log = M('Log');
			$m_log->create($params);
			$m_log->role_id = session('role_id');
			$m_log->category_id = 1;
			$m_log->create_date = time();
			$m_log->update_date = time();
			if($log_id = $m_log->add()){
				$m_id = $module . '_id';
				$data['log_id'] = $log_id;
				$data[$m_id] = $id;
				if($m_r -> add($data)){
					if($params['nextstep_time']){
						$nextstep_time = $params['nextstep_time'];
						if($module == 'leads' || $module == 'business'){	
							$save_array['nextstep_time'] = $nextstep_time;
							$save_array['nextstep'] = $params['nextstep'];
							M($module)->where($module.'_id = %d', $id)->save($save_array);
						}
					}
					$this->ajaxReturn('添加成功','添加成功',1);
				}else{
					$this->ajaxReturn('添加失败','添加失败',2);
				}
			}else{
				$this->ajaxReturn('添加失败','添加失败',2);
			}
		}
	}
	//沟通日志详情
	public function view(){
		if($this->isPost()){
			$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
			if(!$id){
				$this->ajaxReturn('参数错误','参数错误',2);
			}
			$log = M('Log');
			$log_info = $log->where('log_id = %d', $id)->find();
			$data['log_info'] = !empty($log_info) ? $log_info : array();
			$this->ajaxReturn($data,'success',1);
		}
	}
	//修改沟通日志
	public function edit(){
		if($this->isPost()){
			$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
			$params = json_decode($_POST['params'],true);
			if(!is_array($params)){
				$this->ajaxReturn('非法的数据格式!','非法的数据格式!',2);
			}
			if(!$id){
				$this->ajaxReturn('参数错误','参数错误',2);
			}
			$log = M('Log');
			$log -> create($params);
			$log -> update_date = time();
			$result = $log->save();
			if($result){
				$this->ajaxReturn('修改成功','修改成功',1);
			}else{
				$this->ajaxReturn('修改失败，请重试','修改失败，请重试',2);
			}
		}
	}
	//删除沟通日志
	public function delete(){
		if($this->isPost()){
			$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
			if(!$id){
				$this->ajaxReturn('参数错误','参数错误',2);
			}
			if(M('Log')->where('log_id = %d',$id)->delete()){
				$this->ajaxReturn('删除成功','删除成功',1);
			}else{
				$this->ajaxReturn('删除失败，请重试','删除失败，请重试',2);
			}
		}
	}
}