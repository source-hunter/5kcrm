<?php
class LeadsMobile extends Action {

	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('radiolistdialog','getrole','checkrole','receive','allot','ajax','info','loglist')
		);
		B('AppAuthenticate', $action);
		$this->_permissionRes = getPerByAction(MODULE_NAME,ACTION_NAME);
		Global $role;
		$this->role = $role;
		Global $roles;
		$this->roles = $roles;
	}
	
	//线索列表
	public function index(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			//获取权限
			$permission_list = apppermission(MODULE_NAME,ACTION_NAME);
			if($permission_list){
				$data['permission_list'] = $permission_list;
			}else{
				$data['permission_list'] = array();
			}
			getDateTime('leads');			
			$d_v_leads = D('LeadsView');
			$by = isset($_GET['by']) ? trim($_GET['by']) : '';
			$searchfield = isset($_POST['searchfield']) ? trim($_POST['searchfield']) : '';
			$params_search = json_decode($searchfield,true);
			$below_ids = getPerByAction('leads',ACTION_NAME,true);
			$outdays = M('config') -> where('name="leads_outdays"')->getField('value');
			$outdate = empty($outdays) ? 0 : time()-86400*$outdays;
			$where = array();
			switch ($by) {
				case 'today' :
					$where['nextstep_time'] =  array(array('lt',strtotime(date('Y-m-d', time()))+86400), array('gt',0), 'and'); 
					break;
				case 'week' : 
					$where['nextstep_time'] =  array(array('lt',strtotime(date('Y-m-d', time())) + (date('N', time()) - 1) * 86400), array('gt', 0),'and'); 
					break;
				case 'month' : 
					$where['nextstep_time'] =  array(array('lt',strtotime(date('Y-m-01', strtotime('+1 month')))), array('gt', 0),'and'); 
					break;
				case 'd7' : 
					$where['update_time'] =  array('lt',strtotime(date('Y-m-d', time()))-86400*6); 
					break;
				case 'd15' : 
					$where['update_time'] =  array('lt',strtotime(date('Y-m-d', time()))-86400*14); 
					break;
				case 'd30' : 
					$where['update_time'] =  array('lt',strtotime(date('Y-m-d', time()))-86400*29); 
					break;
				case 'add' : $order = 'create_time desc';  break;
				case 'update' : $order = 'update_time desc';  break;
				case 'sub' : $where['owner_role_id'] = array('in',implode(',', $below_ids)); break;
				case 'subcreate' : $where['creator_role_id'] = array('in',implode(',', $below_ids)); break;
				case 'public' :
					unset($where['have_time']);
					$where['_string'] = "leads.owner_role_id=0 or leads.have_time < $outdate";
					break;
				case 'deleted': $where['is_deleted'] = 1;unset($where['have_time']); break;
				case 'transformed' : $where['is_transformed'] = 1; break;
				case 'me' : $where['owner_role_id'] = session('role_id'); break;
			}
			if ($by != 'deleted') {
				$where['is_deleted'] = array('neq',1);
			}
			if ($by != 'transformed') {
				$where['is_transformed'] = array('neq',1);
			}
			if ($this->_permissionRes && !isset($where['owner_role_id']) && $by != 'public') {
				if($by != 'deleted') $where['owner_role_id'] = array('in', $this->_permissionRes);
				else $where['owner_role_id'] = array('in', '0,'.implode(',', $this->_permissionRes));
			}
			$where['have_time'] = array('egt',$outdate);
			if($params_search){
				$where[$params_search['field']] = array('like','%'.trim($params_search['val']).'%');
			}
			$where['is_deleted'] = array('neq',1);
			if ($_REQUEST["name"] != "") {
				$where['name'] = array('like','%'.$_REQUEST["name"].'%');
			}
			$p = isset($_POST['p']) ? intval($_POST['p']) : 1 ;
			if($_GET['act'] == 'new'){
				$time_now = time();
				$compare_time = $time_now - 86400*3;
				$where['owner_role_id'] = array('in',implode(',', getSubRoleId()));
				$where['update_time'] = array('gt',$compare_time);
			}
			if(isset($_POST['search'])){
				$where['name'] = array('like','%'.trim($_POST['search']).'%');
			}
			$list = $d_v_leads->where($where)->order('create_time desc')->page($p.',10')->select();
			foreach($list as $k=>$v){
				$list[$k]['user_name'] = M('user')->where('role_id = %d',$v['owner_role_id'])->getField('name');
				$owner_role_id = $v['owner_role_id'];
				//获取操作权限
				$list[$k]['permission'] = permissionlist(MODULE_NAME,$owner_role_id);
			}
			$list = empty($list) ? array() : $list;
			$count = $d_v_leads->where($where)->count();
			//获取查询条件信息
			$fields_list = M('Fields')->where(array('model'=>'leads','form_type'=>array('in','text,box'),'is_main'=>1))->field('name,field,setting,form_type,input_tips')->select();
			foreach($fields_list as $k=>$v){
				if($v['setting']){
					eval("\$setting = ".$v['setting'].'; ');
					$setting_info['type'] = $setting['type'];
					$setting_info['data'] = array();
					foreach($setting['data'] as $key=>$val){
						$setting_info['data'][] = $val;
					}
				}else{
					$setting_info = array();
				}
				$fields_list[$k]['setting'] = $setting_info;
			}
			if($p == 1 && $by == '' && $_POST['search'] == '' && $searchfield == ''){
				$data['fields_list'] = $fields_list;
			}else{
				$data['fields_list'] = array();
			}
			$page = ceil($count/10);
			$data['list'] = $list;
			$data['page'] = $page;
			$data['info'] = 'success'; 
			$data['status'] = 1; 			
			$this->ajaxReturn($data,'JSON');
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
	//线索放入回收站
	public function delete(){
		if($this->role == 1){
			$this->ajaxReturn('','您没有此权利!',-2);
		}
		if($this->isPost()){
			$m_leads = M('leads');		
			$leads_id = intval($_REQUEST['id']);
			$leads = $m_leads->where('leads_id = %d',$leads_id)->find();			
			if(!$leads_id || !$leads){
				$this->ajaxReturn('删除失败！','删除失败！',2);	//参数错误
			}elseif(!in_array($leads['owner_role_id'], $this->_permissionRes)){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);	//没有权限
			}
			$data = array('is_deleted'=>1, 'delete_role_id'=>session('role_id'), 'delete_time'=>time());
			if($m_leads->where('leads_id = %d', $leads_id)->setField($data)){
				actionLog($leads_id);
				$this->ajaxReturn('','删除成功！',1);	//删除成功
			}else{
				$this->ajaxReturn('删除失败！','删除失败！',2);	//删除失败
			}
		}
	}
	
	public function info(){		
		$content = parseAlert();	
		if($content){
			foreach($content['content'] as $k=>$v){
				if($k == 'success'){					
					$this->model = 'leads';
					$this->id = $v[0]['id'];
					$this->content = $v[0]['info'];
					$this->display('Public:app_success');
				}
				if($k == 'error'){					
					$this->content = $v[0];
					$this->display('Public:role');
				}				
			}
		}			
	}
	//新版线索动态
	public function dynamic(){
		if($this->isPost()){
			if($this->roles == 2){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$leads_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			$leads = D('LeadsView')->where('leads.leads_id = %d', $leads_id)->find();
			if (!$leads || $leads['is_deleted'] == 1) {   
				$this->ajaxReturn('数据不存在，或已删除','数据不存在，或已删除',2);
			}
			$owner_role_id = $leads['owner_role_id'];
			$outdays = M('config') -> where('name="leads_outdays"')->getField('value');
			$outdate = empty($outdays) ? 0 : time()-86400*$outdays;	
			
			if($leads['owner_role_id'] != 0 && ($leads['update_time'] > $outdate)){
				if(!in_array($leads['owner_role_id'],getPerByAction('leads','view'))){
					$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
				}
			}
			//沟通日志
			$log_ids = M('rLeadsLog')->where('leads_id = %d', $leads_id)->getField('log_id', true);
			$log_count = M('log')->where('log_id in (%s)', implode(',', $log_ids))->count();
			//判断是否已转换线索
			$data['transformed'] = empty($leads['is_transformed']) ? 0 : intval($leads['is_transformed']);
			$data['type'][0]['count'] = empty($log_count)? 0 : intval($log_count);
			$data['type'][0]['name'] = '沟通日志';
			$this->ajaxReturn($data,'success',1);
		}
	}
	//线索下沟通日志列表
	public function loglist(){
		if($this->isPost()){
			$m_log = M('log');
			$leads_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			if(empty($leads_id)){
				$this->ajaxReturn('参数错误！','参数错误！',2);
			}
			$p = isset($_POST['p']) ? intval($_POST['p']) : 1 ;
			$log_ids = M('rLeadsLog')->where('leads_id = %d', $leads_id)->getField('log_id', true);
			$log_list = $m_log->where('log_id in (%s)', implode(',', $log_ids))->page($p.',10')->select();
			$log_count = $m_log->where('log_id in (%s)', implode(',', $log_ids))->count();
			$d_role_view = D('RoleView');
			foreach ($log_list as $key=>$value) {
				$log_list[$key]['owner'] = $d_role_view->where('role.role_id = %d', $value['role_id'])->field('user_name,role_id,img,role_name,department_name')->find();
				$log_list[$key]['type'] = 1;
			}
			if($log_list){
				$log_list_data = $log_list;
			}else{
				$log_list_data = array();
			}
			$data['log_list'] = $log_list_data;
			$log_count = empty($logcount) ? 0 : $logcount;
			$page = ceil($log_count/10);
			$data['page'] = $page;
			$this->ajaxReturn($data,'success',1);
		}
	}
	//新版线索添加
	public function addnew(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$m_leads = D('Leads');
			$m_leads_data = D('LeadsData');
			$params = json_decode($_POST['params'],true);
			$field_list = M('Fields')->where('model = "leads"  and in_add = 1')->order('order_id')->select();
			foreach ($field_list as $v){
				if($v['is_validate'] == 1){
					if($v['is_null'] == 1){
						if($params[$v['field']] == ''){
							$this->ajaxReturn($v['name'].'不能为空',$v['name'].'不能为空',2);
						}
					}
					if($v['is_unique'] == 1){
						$res = validate('leads',$v['field'],$params[$v['field']]);
						if($res){
							$this->ajaxReturn($v['name'].':'.$params[$v['name']].'已存在',$v['name'].':'.$params[$v['name']].'已存在',2);
						}
					}
				}
				if($params[$v['field']]){
					switch($v['form_type']) {
						case 'address':
							$params[$v['field']] = implode(chr(10),$params[$v['field']]);
						break;
						case 'datetime':
							$params[$v['field']] = $params[$v['field']];
						break;
						case 'box':
							eval('$field_type = '.$v['setting'].';');
							if($field_type['type'] == 'checkbox'){
								$a =array_filter($params[$v['field']]);
								$params[$v['field']] = !empty($a) ? implode(chr(10),$a) : '';
							}
						break;
					}
				}
			}
			if($m_leads->create($params)){
				if($m_leads_data->create($params)!==false){
					if($params['nextstep_time']) $m_leads->nextstep_time = $params['nextstep_time'];
					$m_leads->creator_role_id = session('role_id');
					$m_leads->create_time = time();
					$m_leads->update_time = time();
					$m_leads->have_time = time();
					if ($leads_id = $m_leads->add()) {
						$m_leads_data->leads_id = $leads_id;
						$m_leads_data->add();
						actionLog($leads_id);
						$this->ajaxReturn('添加成功','添加成功',1);
					} else {
						$this->ajaxReturn('添加失败','添加失败',2);
					}
				}else{
					$this->ajaxReturn($m_leads_data->getError(),'添加失败',2);
				}
			}else{
				$this->ajaxReturn($m_leads->getError(),'添加失败',2);
			}
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
	//新版线索编辑
	public function editnew(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$params = json_decode($_POST['params'],true);
			$leads_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			if(!$leads_id){
				$this->ajaxReturn('参数错误!','参数错误!',2);
			}elseif(!$d_v_leads = D('LeadsView')->where('leads.leads_id = %d',$leads_id)->find()){
				$this->ajaxReturn('线索不存在或已被删除!','线索不存在或已被删除!',2);
			}elseif(!in_array($d_v_leads['owner_role_id'],getPerByAction('leads','edit'))){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$params['leads_id'] = $leads_id;
			$field_list = M('Fields')->where('model = "leads"')->order('order_id')->select();
			$m_leads = M('Leads');
			$m_leads_data = M('LeadsData');
			foreach ($field_list as $v){
				switch($v['form_type']) {
					case 'address':
						$params[$v['field']] = implode(chr(10),$params[$v['field']]);
					break;
					case 'datetime':
						$params[$v['field']] = $params[$v['field']];
					break;
					case 'box':
						eval('$field_type = '.$v['setting'].';');
						if($field_type['type'] == 'checkbox'){
							$params[$v['field']] = implode(chr(10),$params[$v['field']]);
						}
					break;
				}
				if($v['is_validate'] == 1){
					if($v['is_null'] == 1){
						if($params[$v['field']] == ''){
							$this->ajaxReturn($v['name'].'不能为空',$v['name'].'不能为空',2);
						}
					}
					if($v['is_unique'] == 1){
						$res = validate('leads',$v['field'],$params[$v['name']],$leads_id);
						if($res == 1){
							$this->ajaxReturn($v['name'].':'.$params[$v['name']].'已存在',$v['name'].':'.$params[$v['name']].'已存在',2);
						}
					}
				}
			}
			if($m_leads->create($params)){
				if($m_leads_data->create($params)!==false){
					$m_leads->update_time = time();
					$a = $m_leads->where('leads_id= %d',$leads_id)->save();
					$b = $m_leads_data->where('leads_id=%d',$leads_id)->save();
					if($a && $b!==false) {
						actionLog($leads_id);
						$this->ajaxReturn('修改成功','修改成功',1);
					} else {
						$this->ajaxReturn('修改失败','修改失败',2);
					}
				}else{
					$this->ajaxReturn($m_leads_data->getError(),'修改失败',2);
				}
			}else{
				$this->ajaxReturn($m_leads->getError(),'修改失败',2);
			}
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
	//新版线索查看
	public function viewnew(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$leads_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			$outdays = M('config') -> where('name="leads_outdays"')->getField('value');
			$outdate = empty($outdays) ? 0 : time()-86400*$outdays;	
			$where['have_time'] = array('egt',$outdate);
			$where['owner_role_id'] = array('neq',0);
			$where['leads_id'] = $leads_id;
			if(!$leads_id){
				$this->ajaxReturn('参数错误!','参数错误!',2);
			}elseif($temp = D('Leads')->where($where)->find()){
				if(!in_array($temp['owner_role_id'],getPerByAction('leads','view'))){
					$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
				}
			}
			$leads = D('LeadsView')->where('leads.leads_id = %d', $leads_id)->find();
			if (!$leads || $leads['is_deleted'] == 1) {
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			//查询固定信息
			//负责人
			$leads_owner = D('RoleView')->where('role.role_id = %d', $leads['owner_role_id'])->field('user_name,role_id')->find();
			$data_list[0]['field'] = 'owner_role_id';
			$data_list[0]['name'] = '负责人';
			$data_list[0]['form_type'] = 'box';
			if($leads_owner){
				$data_list[0]['val'] = $leads_owner['user_name'];
				$data_list[0]['id'] = $leads_owner['role_id'];
			}else{
				$data_list[0]['val'] = '';
				$data_list[0]['id'] = '';
			}
			$data_list[0]['type'] = 1;
			//创建人
			$leads_creator = D('RoleView')->where('role.role_id = %d', $leads['creator_role_id'])->field('user_name,role_id')->find();
			$data_list[1]['field'] = 'creator_role_id';
			$data_list[1]['name'] = '创建人';
			$data_list[1]['form_type'] = 'text';
			$data_list[1]['val'] = $leads_creator['user_name'];
			$data_list[1]['id'] = $leads_creator['role_id'];
			$data_list[1]['type'] = 1;
			//自定义字段
			$field_list = M('Fields')->where('model = "leads"')->order('order_id')->select();
			$i = 2;
			foreach($field_list as $k=>$v){
				$field = trim($v['field']);
				$data_list[$i]['field'] = $field;
				$data_list[$i]['name'] = trim($v['name']);
				$data_list[$i]['form_type'] = $v['form_type'];
				$data_a = trim($leads[$field]);
				if($v['form_type'] == 'editor'){
					$data_list[$i]['val'] = '暂不支持';
				}elseif($v['form_type'] == 'address'){
					$address_array = str_replace(chr(10),' ',$data_a);
					$data_list[$i]['val'] = $address_array;
				}else{
					$data_list[$i]['val'] = $data_a;
				}
				$data_list[$i]['id'] = '';
				$data_list[$i]['type'] = 0;
				$i++;
			}
			//获取权限
			//判断是否线索池
			if($leads['owner_role_id'] && $leads['have_time'] >= $outdate){
				$data['permission'] = permissionlist(MODULE_NAME,$leads['owner_role_id']);
			}else{
				$data['permission'] = array('edit'=>1,'view'=>1,'delete'=>1);
			}
			$data['data'] = $data_list;
			$data['info'] = 'success';
			$data['status'] = 1;
			$this->ajaxReturn($data,'JSON');
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
}