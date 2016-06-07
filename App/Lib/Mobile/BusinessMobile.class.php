<?php
class BusinessMobile extends Action {

	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('addproduct','ajax','info','advance','status','business_list','advancehistory','loglist')
		);
		B('AppAuthenticate', $action);
		$this->_permissionRes = getPerByAction(MODULE_NAME,ACTION_NAME);
		Global $role;
		$this->role = $role;
		Global $roles;
		$this->roles = $roles;
	}

	public function info(){
		$content = parseAlert();
		if($content){
			foreach($content['content'] as $k=>$v){
				if($k == 'success'){
					$this->model = 'Business';
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

	public function ajax(){
		//选择客户
		$where = array();
		$outdays = M('config') -> where('name="customer_outdays"')->getField('value');
		$outdate = empty($outdays) ? 0 : time()-86400*$outdays;
		$m_customer = M('Customer');
		$m_contacts = M('Contacts');
		$m_r_contacts_customer = M('RContactsCustomer');
		$underling_ids = $this->_permissionRes;
		if(isset($_GET['search'])){
			$where['name'] = array('like','%'.trim($_GET['search']).'%');
		}
		$where['owner_role_id'] = array('in',implode(',',$underling_ids));
		$where['is_deleted'] = 0;
		$where['_string'] = 'update_time > '.$outdate.' OR is_locked = 1';
		$customer = $m_customer->where($where)->field('name,customer_id,contacts_id,update_time,owner_role_id,is_locked')->order('create_time desc')->limit(10)->select();
		foreach($customer as $k=>$v){
			//如果存在首要联系人，则查出首要联系人。否则查出联系人中第一个。
			if(!empty($v['contacts_id'])){
				$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$v['contacts_id'])->field('name')->find();
				$customer[$k]['contacts_name'] = $contacts['name'];
			}else{
				$contacts_customer = $m_r_contacts_customer->where('customer_id = %d',$v['customer_id'])->limit(1)->field('name,contacts_id')->order('id desc')->select();
				$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$contacts_customer[0]['contacts_id'])->find();
				$customer[$k]['contacts_id'] = $contacts['contacts_id'];
				$customer[$k]['contacts_name'] = $contacts['name'];
			}
		}
		$this->customerList = $customer;
		$count = $m_customer->where('owner_role_id in (%s) and is_deleted = 0',implode(',',$underling_ids))->count();
		$this->total = $count%10 > 0 ? ceil($count/10) : $count/10;
		$this->display();
	}

	//商机放入回收站
	public function delete(){
		if($this->role == 1){
			$this->ajaxReturn('','您没有此权利！',-2);
		}
		if($this->isPost()){
			$m_business = M('business');
			$business = $m_business ->where('business_id = %d',$this->_request('business_id'))->find();
			if (!$business || !$this->_request('business_id')) {
				$this->ajaxReturn('删除失败！','删除失败！',2);	//参数错误
			}elseif(!in_array($business['owner_role_id'], $this->_permissionRes)){
				$this->ajaxReturn('您没有此权利！','您没有此权利！',-2);	//没有权限
			}
			$data = array('is_deleted'=>1, 'delete_role_id'=>session('role_id'), 'delete_time'=>time());
			if($m_business->where('business_id = %d', $business['business_id'])->setField($data)){
				actionLog($business['business_id']);
				$this->ajaxReturn('删除成功！','删除成功！',1);	//删除成功
			} else {
				$this->ajaxReturn('删除失败！','删除失败！',2);	//删除失败
			}
		}
	}

	public function index(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$permission_list = apppermission(MODULE_NAME,ACTION_NAME);
			if($permission_list){
				$data['permission_list'] = $permission_list;
			}else{
				$data['permission_list'] = array();
			}
			getDateTime('business');
			$d_v_business = D('BusinessView');
			$below_ids = getPerByAction('business',ACTION_NAME,true);
			$p = isset($_POST['p']) ? intval($_POST['p']) : 1 ;
			$by = isset($_GET['by']) ? trim($_GET['by']) : '';
			$searchfield = isset($_POST['searchfield']) ? trim($_POST['searchfield']) : '';
			$params_search = json_decode($searchfield,true);
			$where = array();
			$order = "";
			switch ($by) {
				case 'create' : $where['creator_role_id'] = session('role_id'); break;
				case 'sub' : $where['owner_role_id'] =array('in',$below_ids); break;
				case 'subcreate' :
					$where['creator_role_id'] =array('in',$below_ids); break;
				case 'today' :
					$where['nextstep_time'] =  array(array('lt',strtotime(date('Y-m-d', time()))+86400), array('gt',0), 'and');
					break;
				case 'week' :
					$where['nextstep_time'] =  array(array('lt',strtotime(date('Y-m-d', time())) + (8-date('N', time())) * 86400), array('gt', 0),'and');
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
				case 'deleted' : $where['is_deleted'] = 1; break;
				case 'add' : $order = 'business.create_time desc,business.business_id asc'; break;
				case 'update' : $order = 'business.update_time desc,business.business_id asc'; break;
				case 'me' : $where['business.owner_role_id'] = session('role_id'); break;
			}

			if (!isset($where['is_deleted'])) {
				$where['business.is_deleted'] = 0;
			}
			if (!isset($where['business.owner_role_id'])) {
				$where['business.owner_role_id'] = array('in', $this->_permissionRes);
			}
			if ($_REQUEST["search"]) {
				$where['name'] = array('like','%'.$_REQUEST["search"].'%');
			}
			if($params_search){
				$where[$params_search['field']] = array('like','%'.trim($params_search['val']).'%');
			}
			$order = empty($order) ? 'business.update_time desc' : $order;
			if($_GET['act'] == 'new'){
				$time_now = time();
				$compare_time = $time_now - 86400*3;
				//$where['owner_role_id'] = array('in',implode(',', getSubRoleId()));
				$where['update_time'] = array('gt',$compare_time);
			}
			if(intval($_GET['customer_id'])){
				$where['customer_id'] = intval($_GET['customer_id']);
			}
			$list = $d_v_business->where($where)->field('name,business_id,total_price,customer_id,owner_role_id')->order($order)->page($p.',10')->select();
			foreach($list as $k=>$v){
				$list[$k]['customer_name'] = M('Customer')->where('customer_id = %d',$v['customer_id'])->getField('name');
				$owner_role_id = $v['owner_role_id'];
				//获取操作权限
				$list[$k]['permission'] = permissionlist(MODULE_NAME,$owner_role_id);
			}
			$list = empty($list) ? array() : $list;
			$count = $d_v_business->where($where)->count();
			//获取查询条件信息
			$fields_list = M('Fields')->where(array('model'=>'business','form_type'=>array('in','text,box'),'is_main'=>1))->field('name,field,setting,form_type,input_tips')->select();
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
			$this->ajaxReturn('','非法请求！',2);
		}
	}

	//选择商机列表
	public function business_list(){
		if($this->isPost()){
			$d_v_business = D('BusinessView');
			$m_customer = M('customer');
			$m_contacts = M('contacts');
			$m_r_contacts_customer = M('RContactsCustomer');
			$below_ids = getPerByAction(MODULE_NAME,ACTION_NAME,true);
			$where = array();
			if(isset($_POST['search'])){
				$where['name'] = array('like','%'.trim($_POST['search']).'%');
			}
			$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
			$by = isset($_GET['by']) ? trim($_GET['by']) : '';
			$order = "create_time desc";
			$where['business.status_id'] = array(array('neq', 99), array('neq', 100), 'and');
			$where['owner_role_id'] = array('in', $this->_permissionRes);
			$where['is_deleted'] = 0;
			$order = empty($order) ? 'business.update_time desc' : $order;
			$list = $d_v_business->where($where)->order($order)->page($p.',10')->field('name,business_id,total_price,customer_id,owner_role_id,creator_role_id,status_id')->select();
			$count =  $d_v_business->where($where)->count();
			$page = ceil($count/10);

			foreach($list as $key => $value){
				$list[$key]['owner_name'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->getField('user_name');
				$list[$key]['customer_name'] = $m_customer->where('customer_id = %s',$value['customer_id'])->getField('name');
				$customer = $m_customer->where('customer_id = %s',$value['customer_id'])->find();
				foreach($customer as $k=>$v){
					//如果存在首要联系人，则查出首要联系人。否则查出联系人中第一个。
					if(!empty($v['contacts_id'])){
						$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$v['contacts_id'])->find();
						$list[$key]['contacts_name'] = $contacts['name'];
						$list[$key]['contacts_id'] = $contacts['contacts_id'];
					}else{
						$contacts_customer = $m_r_contacts_customer->where('customer_id = %d',$v['customer_id'])->limit(1)->order('id desc')->select();
						if(!empty($contacts_customer)){
							$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$contacts_customer[0]['contacts_id'])->find();
						}
						$list[$key]['contacts_id'] = $contacts['contacts_id'];
						$list[$key]['contacts_name'] = $contacts['name'];
					}
				}
				$list[$key]['status_name'] = M('BusinessStatus')->where('status_id = %d', $value['status_id'])->getField('name');
			}
			$list = empty($list) ? array() : $list;
			$data['list'] = $list;
			$data['page'] = $page;
			$this->ajaxReturn($data,'success',1);
		}
	}
	//商机新版动态
	public function dynamic(){
		if($this->isPost()){
			if($this->roles == 2){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$business_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
			$business = D('BusinessView')->where('business.business_id = %d', $business_id)->find();
			if (!$business || $business['is_deleted'] == 1) {
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}elseif(!in_array($business['owner_role_id'], getPerByAction('business','view'))){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			//获取商机当前状态
			$data['status_id'] = $business['status_id'];
			$data['status_name'] = M('BusinessStatus')->where('status_id = %d',$business['status_id'])->getField('name');
			//沟通日志
			$log_ids = M('rBusinessLog')->where('business_id = %d', $business_id)->getField('log_id', true);
			$log_count = M('log')->where('log_id in (%s)', implode(',', $log_ids))->count();
			$type_list[0]['count'] = empty($log_count) ? 0 : intval($log_count);
			$type_list[0]['name'] = '沟通日志';
			//推进历史
			$advance_count = M('RBusinessStatus')->where('business_id = %d',$business_id)->count();
			$type_list[1]['count'] = empty($advance_count) ? 0 : intval($advance_count);
			$type_list[1]['name'] = '推进历史';

			//产品统计
			$product_count =  M('RBusinessProduct')->where('business_id = %d', $business_id)->count();
			$type_list[2]['count'] = empty($product_count)? 0 : intval($product_count);
			$type_list[2]['name'] = '产品';

			//合同统计
			$contract_ids = M('RBusinessContract')->where('business_id = %d', $business_id)->getField('contract_id', true);
			$contract_count = M('contract')->where('contract_id in (%s) and is_deleted=0', implode(',', $contract_ids))->count();
			$type_list[3]['count'] = empty($contract_count) ? 0 : intval($contract_count);
			$type_list[3]['name'] = '合同';

			//任务统计
			$task_ids = M('RBusinessTask')->where('business_id = %d', $business_id)->getField('task_id', true);
			$task_count = M('task')->where('task_id in (%s) and is_deleted=0', implode(',', $task_ids))->count();
			$type_list[4]['count'] = empty($task_count) ? 0 : intval($task_count);
			$type_list[4]['name'] = '任务';

			//判断客户是否为客户池中
			$outdays = M('config')->where('name="cutomer_outdays"')->getField('value');
			$outdate = empty($outdays) ? 0 :time()-86400*$outdays;
			if($business['customer']['owner_role_id'] == 0 || ($business['customer']['update_time'] < $outdate && $business['customer']['id_locked'] = 0)){
				$this->flag = 1;
			}else{
				$this->flag = 0;
			}
			$data['type'] = $type_list;
			$page = ceil($log_count/10);
			$data['page'] = $page;
			$this->ajaxReturn($data,'success',1);
		}
	}
	//商机下沟通日志
	public function loglist(){
		if($this->isPost()){
			$m_log = M('Log');
			$business_id = isset($_POST['id']) ? intval($_POST['id']) : 0 ;
			if(empty($business_id)){
				$this->ajaxReturn('参数错误！','参数错误！',2);
			}
			$business = D('BusinessView')->where('business.business_id = %d', $business_id)->find();
			$p = isset($_POST['p']) ? intval($_POST['p']) : 1 ;
			//沟通日志
			$log_ids = M('rBusinessLog')->where('business_id = %d', $business_id)->getField('log_id', true);
			$log_list = $m_log->where('log_id in (%s)', implode(',', $log_ids))->page($p.',10')->select();
			$log_count = $m_log->where('log_id in (%s)', implode(',', $log_ids))->count();
			foreach ($log_list as $key=>$value) {
				$log_list[$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->field('user_name,role_id,img,role_name,department_name')->find();
				$log_list[$key]['type'] = 1;
			}
			if($log_list){
				$log_list_data = $log_list;
			}else{
				$log_list_data = array();
			}
			$data['log_list'] = $log_list_data;
			$page = ceil($log_count/10);
			$data['page'] = $page;
			$this->ajaxReturn($data,'success',1);
		}
	}
	//商机推进
	public function advance(){
		if($this->isPost()){
			$business_id = $_POST['id'];
			$params = json_decode($_POST['params'],true);
			$m_r_bs = M('RBusinessStatus');
			$business = D('BusinessView')->where('business.business_id = %d', $business_id)->find();

			/* if(!in_array($business['owner_role_id'] , $this->_permissionRes)){
				$this->ajaxReturn('您没有此权利!','error',-2);
			} */
			if(!$params['status_id']){
				$this->ajaxReturn('请选择推进阶段！','请选择推进阶段！',2);
			}
			$data['business_id'] = $business['business_id'];
			if($business['gain_rate']) $data['gain_rate'] = $business['gain_rate'];
			$data['status_id'] = $params['status_id'];
			$data['description'] = $params['description'];
			$data['owner_role_id'] = $business['owner_role_id'];
			$data['update_time'] = $business['update_time'];
			$data['update_role_id'] = $business['update_role_id'];
			$m_r_bs->add($data);

			$m_business = M('business');
			$m_business_data = M('businessData');
			$data2['update_time'] = time();
			$data2['status_id'] = $params['status_id'];
			$data2['nextstep_time'] = $params['nextstep_time'];
			$data2['nextstep'] = $params['nextstep'];
			$data3['description'] = $params['description'];
			$data2['update_role_id'] = session('role_id');

			if(intval($params['status_id']) == 100){
				M('Customer')->where('customer_id = %d', $business['customer_id'])->setField('is_locked',1);
			}
			if($m_business->where('business_id = %d', $business_id)->save($data2)){
				$m_business_data->where('business_id = %d', $business_id)->save($data3);
				M('customer')->where('customer_id = %d',$business['customer_id'])->setField('update_time',time());
				$this->ajaxReturn('推进成功!','推进成功!',1);
			}else{
				$this->ajaxReturn('推进失败，请重试!','推进失败，请重试!',2);
			}
		}
	}
	//获取商机推进状态
	public function status(){
		if($this->isPost()){
			$business_id = intval(trim($_POST['id']));
			if($business_id > 0){
				$status_id = M('Business')->where('business_id = %d', $business_id)->getField('status_id');
				$order_id = M('BusinessStatus')->where('status_id = %d', $status_id)->getField('order_id');
				if(!$order_id) $order_id = 0;
				$StatusList =  M('BusinessStatus')->where('order_id >= %d', $order_id)->order('order_id')->select();
				$this->ajaxReturn($StatusList,'success',1);
			}else{
				$this->ajaxReturn('参数错误！','参数错误！',2);
			}
		}
	}
	//新版商机添加
	public function addnew(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','error',-2);
		}
		if($this->isPost()){
			$m_business = D('Business');
			$m_business_data = D('BusinessData');
			$params = json_decode($_POST['params'],true);
			$field_list = M('Fields')->where('model = "business" and in_add = 1')->order('order_id')->select();
			foreach ($field_list as $v){
				if($v['is_validate'] == 1){
					if($v['is_null'] == 1){
						if($params[$v['field']] == ''){
							$this->ajaxReturn($v['name'].'不能为空',$v['name'].'不能为空',2);
						}
					}
					if($v['is_unique'] == 1){
						$res = validate('business',$v['field'],$params[$v['field']]);
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
			if(empty($params['customer_id'])){
				$this->ajaxReturn('客户不能为空','客户不能为空',2);
			}
			if($params['status_id']){
				$status_id = M('BusinessStatus')->where('status_id = "%s"',$params['status_id'])->getField('status_id');
				$params['status_id'] = $status_id;
			}
			if($m_business->create($params)){
				if($m_business_data->create($params)!==false){
					//商机状态为空时
					if(empty($params['status_id'])){
						$statusid = M('BusinessStatus')->order('order_id asc')->getField('status_id');
						$m_business->status_id = $statusid;
					}
					$m_business->create_time = $m_business->update_time = time();
					$m_business->creator_role_id = $m_business->update_role_id = session('role_id');
					if($business_id = $m_business->add()){
						$m_business_data->business_id = $business_id;
						if($m_business_data->add()){
							if(is_array($_POST['products'])){
								foreach($_POST['products'] as $val){
									$data = array();
									$data['product_id'] = $val['product_id'];
									$data['estimate_price'] = $val['estimate_price'];
									$data['sales_price'] = $val['sales_price'];
									$data['amount'] = $val['product_amount'];
									$data['description'] = $val['product_description'];
									$data['business_id'] = $business_id;
									M('RBusinessProduct')->add($data);
								}
							}
							if(intval($params['status_id']) == 100){
								//项目成功，把客户锁定
								M('Customer')->where('customer_id = %d', intval($params['customer_id']))->setField('is_locked',1);
							}
							actionLog($business_id);
							$this->ajaxReturn('添加成功','success',1);
						}else{
							$m_business->where(array('business_id'=>$business_id))->delete();
							$this->ajaxReturn('添加失败','添加失败',2);
						}
					}else{
						$this->ajaxReturn('添加失败','添加失败',2);
					}
				}else{
					$this->ajaxReturn($m_business_data->getError(),'添加失败',2);
				}
			}else{
				$this->ajaxReturn($m_business->getError(),'添加失败',2);
			}
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
	//新版商机编辑
	public function editnew(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$params = json_decode($_POST['params'],true);
			$business_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			$v_business = D('BusinessView');
			$business = $v_business ->where('business.business_id = %d',$business_id)->find();
			if(!$business_id){
				$this->ajaxReturn('参数错误!','参数错误!',2);
			}elseif(!$business){
				$this->ajaxReturn('商机不存在或已被删除!','商机不存在或已被删除!',2);
			}elseif(!in_array($business['owner_role_id'],getPerByAction('business','edit'))){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$params['business_id'] = $business_id;
			$field_list = M('Fields')->where('model = "business"')->order('order_id')->select();
			$m_business = D('business');
			$m_business_data = D('BusinessData');
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
						$res = validate('business',$v['field'],$params[$v['name']],$business_id);
						if($res == 1){
							$this->ajaxReturn($v['name'].':'.$params[$v['name']].'已存在',$v['name'].':'.$params[$v['name']].'已存在',2);
						}
					}
				}
			}
			if($params['status_id']){
				$status_id = M('BusinessStatus')->where('status_id = "%s"',$params['status_id'])->getField('status_id');
				$params['status_id'] = $status_id;
			}
			if(empty($params['customer_id'])){
				$this->ajaxReturn('客户不能为空','客户不能为空',2);
			}
			if($m_business->create($params)){
				if($m_business_data->create($params)!==false){
					$m_business->update_time = time();
					$a = $m_business->where('business_id=' . $business['business_id'])->save();
					$b = $m_business_data->where('business_id=' . $business['business_id'])->save();
					if($a && $b!==false) {
						if($params['status_id'] == 100){
							M('Customer')->where('customer_id = %d', intval($params['customer_id']))->setField('is_locked',1);
						}
						actionLog($business_id);
						$this->ajaxReturn('修改成功','success',1);
					} else {
						$this->ajaxReturn('修改失败','修改失败',2);
					}
				}else{
					$this->ajaxReturn($m_business_data->getError(),'修改失败',2);
				}
			}else{
				$this->ajaxReturn($m_business->getError(),'修改失败',2);
			}
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
	//新版商机详情
	public function viewnew(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$business_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			$v_business = D('BusinessView');
			$business = $v_business ->where('business.business_id = %d',$business_id)->find();
			if(!$business_id){
				$this->ajaxReturn('参数错误!','参数错误!',2);
			}elseif(!$business) {
				$this->ajaxReturn('商机不存在或已被删除!','商机不存在或已被删除!',2);
			}elseif(!in_array($business['owner_role_id'],getPerByAction('business','view'))){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			//查询固定信息
			//负责人
			$business_owner = D('RoleView')->where('role.role_id = %d', $business['owner_role_id'])->field('user_name,role_id')->find();
			$data_list[0]['field'] = 'owner_role_id';
			$data_list[0]['name'] = '负责人';
			$data_list[0]['form_type'] = 'box';
			$data_list[0]['val'] = $business_owner['user_name'];
			$data_list[0]['id'] = $business_owner['role_id'];
			$data_list[0]['type'] = 1;

			//自定义字段
			$field_list = M('Fields')->where('model = "business"')->order('order_id')->select();
			$i = 1;
			foreach($field_list as $k=>$v){
				$field = trim($v['field']);
				$data_list[$i]['field'] = $field;
				$data_list[$i]['name'] = trim($v['name']);
				if($field == 'status_id'){
					//商机状态
					$status_id = trim($business[$v['field']]);
					$business_status_info = M('BusinessStatus')->where('status_id = %d',$status_id)->find();
					$data_a = $business_status_info['name'];
					$data_list[$i]['type'] = 0;
					$data_list[$i]['id'] = $business_status_info['status_id'];
				}elseif($field == 'customer_id'){
					//客户
					$data_a = M('Customer')->where('customer_id = %d', $business['customer_id'])->getField('name');
					$data_list[$i]['type'] = 3;
					$data_list[$i]['id'] = $business['customer_id'];
				}elseif($field == 'contacts_id'){
					//联系人
					$business_contacts = M('contacts')->where('contacts_id = %d and is_deleted=0', $business['contacts_id'])->getField('name');
					$data_list[$i]['val'] = $business_contacts;
					$data_list[$i]['id'] = $business['contacts_id'];
					$data_list[$i]['type'] = 5;
				}else{
					$data_a = trim($business[$field]);
					$data_list[$i]['type'] = 0;
					$data_list[$i]['id'] = '';
				}
				$data_list[$i]['form_type'] = $v['form_type'];
				if($field != 'contacts_id'){
					if($v['form_type'] == 'editor'){
						$data_list[$i]['val'] = '暂不支持';
					}elseif($v['form_type'] == 'address'){
						$address_array = str_replace(chr(10),' ',$data_a);
						$data_list[$i]['val'] = $address_array;
					}else{
						$data_list[$i]['val'] = $data_a;
					}
				}
				$i++;
			}
			//获取权限
			$data['permission'] = permissionlist(MODULE_NAME,$business['owner_role_id']);
			$data['data'] = $data_list;
			$data['info'] = 'success';
			$data['status'] = 1;
			$this->ajaxReturn($data,'JSON');
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
	//商机推进历史
	public function advancehistory(){
		if($this->isPost()){
			$business_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
			if(!intval($business_id)){
				$this->ajaxReturn('参数错误',"参数错误",2);
			}
			$advance_list = M('RBusinessStatus')->where('business_id = %d',$business_id)->field('status_id,description,update_time,update_role_id')->order('update_time desc')->select();
			if($advance_list){
				$m_business_status = M('BusinessStatus');
				foreach($advance_list as $k=>$v){
					$status_name = $m_business_status->where('status_id = %d',$v['status_id'])->getField('name');
					$advance_list[$k]['status_name'] = empty($status_name) ? '' : $status_name;
					//负责人
					$user_info = D('RoleView')->where('role.role_id = %d',$v['update_role_id'])->field('user_name,img')->find();
					$advance_list[$k]['role_name'] = $user_info['user_name'];
					$advance_list[$k]['img'] = $user_info['img'];
				}
			}
			$data['data'] = empty($advance_list) ? array() : $advance_list;
			$data['info'] = 'success';
			$data['status'] = 1;
			$this->ajaxReturn($data,'JSON');
		}
	}
}