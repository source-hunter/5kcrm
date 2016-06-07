<?php
class CustomerMobile extends Action {

	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('radiolistdialog','getrole','checkrole','receive','allot','ajax','info','focus','customer_list','loglist')
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
					$this->model = 'customer';
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
		//联系人列表
		$customer_id = $_REQUEST['customer_id'];
		$contacts_id_list = M('RContactsCustomer')->where('customer_id = %d',$customer_id)->select();
		foreach($contacts_id_list as $v){
			$contacts_ids[] = $v['contacts_id'];
		}
		if($contacts_ids){
			$tmp['contacts_id'] = array('in',$contacts_ids);
		}
		$rcc =  M('RContactsCustomer');
		$m_contacts = M('contacts');
		$permissionRes =  getPerByAction('contacts',ACTION_NAME);
		$tmp['owner_role_id'] = array('in', implode(',',$permissionRes));
		$tmp['is_deleted'] = 0;
		$list = $m_contacts->where($tmp)->order('create_time desc')->limit(10)->field('contacts_id,name,post')->select();
		$count = $m_contacts->where($tmp)->count();
		$this->total = $count%10 > 0 ? ceil($count/10) : $count/10;
		$this->customer_id = $customer_id;
		foreach ($list as $k=>$value) {
			$customer_id = $rcc->where('contacts_id = %d', $value['contacts_id'])->getField('customer_id');
			$list[$k]['customer'] = M('customer')->where('customer_id = %d', $customer_id)->field('name')->find();
		}
		$this->contactsList = $list;
		$this->display();
	}

	public function radioListDialog(){
		if($this->isAjax()){
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
			$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
			$customer = $m_customer->where($where)->field('name,customer_id,contacts_id,update_time,owner_role_id,is_locked')->order('create_time desc')->page($p.',10')->select();
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
			$count = $m_customer->where($where)->count();
			$total = $count%10 > 0 ? ceil($count/10) : $count/10;
			$data['list'] =  $customer;
			$data['total'] =  $total;
			$data['p'] =  $p;

			$this->ajaxReturn($data,'success',1);
		}
	}

	//客户放入回收站
	public function delete(){
		if($this->role == 1){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$m_customer = M('Customer');
			$customer_id = intval($_REQUEST['customer_id']);
			$customer = $m_customer->where('customer_id = %d',$customer_id)->find();
			if(!$customer_id || !$customer){
				$this->ajaxReturn('参数错误','参数错误',2);	//参数错误
			}elseif(!in_array($customer['owner_role_id'], $this->_permissionRes)){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$data = array('is_deleted'=>1, 'delete_role_id'=>session('role_id'), 'delete_time'=>time());
			if($m_customer->where('customer_id = %d', $customer_id)->setField($data)){
				actionLog($customer_id);
				$this->ajaxReturn('删除成功','删除成功',1);	//删除成功
			}else{
				$this->ajaxReturn('删除失败','删除失败',2);	//删除失败
			}
		}
	}
	
	//客户池领取
	/*
	 * 1.领取成功
	 * 2.领取失败
	 * 3.领取失败，您的领取次数已超过领取限制
	 * 0.非POST方式提交
	 */
	public function receive(){
		if($this->isPost()){
			$m_customer = M('Customer');
			$m_config = M('Config');
			$m_customer_record = M('customer_record');
			$data['owner_role_id'] = session('role_id');
			$data['update_time'] = time();
			$customer_id = isset($_REQUEST['customer_id']) ? intval(trim($_REQUEST['customer_id'])) : 0;
			//判断是否符合领取条件
			$customer_limit_counts = $m_config->where('name = "customer_limit_counts"')->getField('value');
			$m_config = M('config');
			$m_customer_record = M('customer_record');
			$customer_limit_condition = $m_config->where('name = "customer_limit_condition"')->getField('value');

			$today_begin = strtotime(date('Y-m-d',time()));
			$today_end = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
			$this_week_begin = ($today_begin -((date('w'))-1)*86400);
			$this_week_end = ($today_end+(7-(date('w')==0?7:date('w')))*86400);
			$this_month_begain = strtotime(date('Y-m', time()).'-01 00:00:00');
			$this_month_end = mktime(23,59,59,date('m'),date('t'),date('Y'));

			$condition['user_id'] = session('user_id');
			$condition['type'] = 1;
			if($customer_limit_condition == 'day'){
				$condition['start_time'] = array('between', array($today_begin, $today_end));
			}elseif($customer_limit_condition == 'week'){
				$condition['start_time'] = array('between', array($this_week_begin, $this_week_end));
			}elseif($customer_limit_condition == 'month'){
				$condition['start_time'] = array('between', array($this_month_begain, $this_month_end));
			}
			$customer_record_count = $m_customer_record->where($condition)->count();

			if($customer_record_count < $customer_limit_counts){
				if($m_customer->where('customer_id = %d', $customer_id)->save($data)){
					$info['customer_id'] = $customer_id;
					$info['user_id'] = session('user_id');
					$info['start_time'] = time();
					$info['type'] = 1;
					$m_customer_record->add($info);
					$this->ajaxReturn('','领取成功',1);
				}else{
					$this->ajaxReturn('','领取失败',2);
				}
			}else{
				$this->ajaxReturn('','领取失败，您的领取次数已超过领取限制',2);
			}
		}
	}

	//得到可操作分配的员工列表
	public function getrole(){
		if($this->isPost()){
			$role_ids = getSubRoleId();
			$user_info = D('RoleView')->where('role.role_id in (%s)',implode(',',$role_ids))->field('user_name,role_id,department_id,department_name,role_name')->select();
			$user_info = empty($user_info) ? array() : $user_info;
			$this->ajaxReturn($user_info,'success',1);
		}
	}

	//客户池分配
	public function allot(){
		if($this->isPost()){
			$m_customer = M('Customer');
			if(!empty($_POST['owner_role_id'])){
				$owner_role_id = $_GET['role_id'];
			}else{
				$owner_role_id = session('role_id');
			}
			$customer_id = isset($_GET['customer_id']) ? intval(trim($_GET['customer_id'])) : 0;
			$data['owner_role_id'] = $owner_role_id;
			$data['update_time'] = time();

			$where['update_time'] = array('lt',(time()-86400));
			$where['customer_id'] = intval($customer_id);
			$where['owner_role_id'] = array('gt',0);
			$updated_owner = $m_customer->where($where)->save($data);

			unset($where['update_time']);
			$where['owner_role_id'] = array('eq',0);
			$updated_time = $m_customer->where($where)->save($data);
			if($updated_owner || $updated_time){
				$customer = $m_customer->where('customer_id = %d', intval($customer_id))->find();
				$content= session('name').'将客户资源:'.$customer['name'].'分配给了你负责!请注意跟进!';
				sendMessage($owner_role_id,$content,1);
				$this->ajaxReturn('','分配成功',1);
			}else{
				$this->ajaxReutnr('','分配失败',2);
			}
		}
	}

	//客户列表
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
			getDateTime('customer');
			$d_v_customer = D('CustomerView');
			$m_user = M('User');
			$m_fields = M('Fields');
			$by = isset($_GET['by']) ? trim($_GET['by']) : '';
			$searchfield = isset($_POST['searchfield']) ? trim($_POST['searchfield']) : '';
			$params_search = json_decode($searchfield,true);
			$below_ids = getPerByAction('customer',ACTION_NAME,true);
			//查询关注
			$m_focus = M('customerFocus');
			$focus_id = $m_focus ->where('user_id =%d',session('role_id'))->getField('customer_id',true);
			//查询分享给我的
			$m_share =  M('customerShare');
			$sharing_id = session('role_id');
			$m_customer_share = $m_share ->select();
			foreach($m_customer_share as $k=>$v){
				$by_sharing_id = explode(',',$v['by_sharing_id']);
				if(in_array($sharing_id,$by_sharing_id)){
					$customerid[] = $v['customer_id'];
				}
			}
			//查询我分享的
			$share_customer_ids = $m_share ->where('share_role_id =%d',session('role_id'))->getField('customer_id',true);
			$outdays = M('config') -> where('name="customer_outdays"')->getField('value');
			$outdate = empty($outdays) ? 0 : time()-86400*$outdays;
			$where = array();
			switch ($by) {
				case 'today' : $where['create_time'] =  array('gt',strtotime(date('Y-m-d', time()))); break;
				case 'week' : $where['create_time'] =  array('gt',(strtotime(date('Y-m-d')) - (date('N', time()) - 1) * 86400)); break;
				case 'month' : $where['create_time'] = array('gt',strtotime(date('Y-m-01', time()))); break;
				case 'add' : $order = 'customer.create_time desc,customer.customer_id asc'; break;
				case 'update' : $order = 'customer.update_time desc,customer.customer_id asc'; break;
				case 'sub' : $where['owner_role_id'] = array('in',$below_ids); break;
				case 'deleted' : $where['is_deleted'] = 1;break;
				case 'me' : $where['owner_role_id'] = session('role_id'); break;
				case 'focus' : $where['customer_id'] = array('in',$focus_id);break;
				case 'share' : $where['customer_id'] = array('in',$customerid);break;
				case 'myshare' : $where['customer_id'] = array('in',$share_customer_ids);break;
				default :
					if($this->_get('content') == 'resource'){
						$where['_string'] = "customer.owner_role_id=0 or (customer.update_time < $outdate and customer.is_locked = 0)";
					}else{
						$where['owner_role_id'] = array('in',implode(',', $this->_permissionRes));
					}
				break;
			}
			if (!isset($where['owner_role_id']) && $this->_get('content') !== 'resource') {
				if($by != 'deleted' && $by != 'share'){
					$where['owner_role_id'] = array('in',implode(',', $this->_permissionRes));
				}
			}
			$where['is_deleted'] = array('neq',1);
			if($this->_get('content') != 'resource'){
				$where['_string'] = 'update_time > '.$outdate.' OR is_locked = 1';
			}
			if(isset($_POST['search'])){
				$where['name'] = array('like','%'.trim($_POST['search']).'%');
			}
			if($params_search){
				$where[$params_search['field']] = array('like','%'.trim($params_search['val']).'%');
			}
			$p = isset($_POST['p']) ? intval($_POST['p']) : 1 ;
			if($_GET['act'] == 'new'){
				$time_now = time();
				$compare_time = $time_now - 86400*3;
				//$where['owner_role_id'] = array('in',implode(',', getSubRoleId()));
				$customer_below_ids = getPerByAction('customer','index');
				$where['owner_role_id'] = array('in',$customer_below_ids);
				$where['update_time'] = array('gt',$compare_time);
			}
			$list_data = $d_v_customer->where($where)->order('create_time desc')->page($p.',10')->select();
			$list = array();
			foreach($list_data as $k=>$v){
				$list[$k]['name'] = $v['name'];
				$list[$k]['customer_id'] = $v['customer_id'];
				$list[$k]['owner_role_id'] = $v['owner_role_id'];
				$list[$k]['industry'] = $v['industry'];
				$list[$k]['creator_role_id'] = $v['creator_role_id'];
				$list[$k]['owner_name'] = $m_user->where(array('role_id'=>$v['owner_role_id'],'status'=>1))->getField('name');
				$list[$k]['creator_name'] = $m_user->where(array('role_id'=>$v['creator_role_id'],'status'=>1))->getField('name');
				$owner_role_id = $v['owner_role_id'];
				//获取操作权限
				if($this->_get('content') == 'resource'){
					$list[$k]['permission'] = array("view"=>1);
				}else{
					$list[$k]['permission'] = permissionlist(MODULE_NAME,$owner_role_id);
				}
			}
			$list = empty($list) ? array() : $list;
			$count = $d_v_customer->where($where)->count();
			//获取查询条件信息
			$fields_list = M('Fields')->where(array('model'=>'customer','form_type'=>array('in','text,box'),'is_main'=>1))->field('name,field,setting,form_type,input_tips')->select();
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
			$page = ceil($count/10);
			if($p == 1 && $by == '' && $_POST['search'] == '' && $searchfield == '' && $_GET['content'] != 'resource'){
				$data['fields_list'] = $fields_list;
			}else{
				$data['fields_list'] = array();
			}
			$data['list'] = $list;
			$data['page'] = $page;
			$data['info'] = 'success';
			$data['status'] = 1;
			$this->ajaxReturn($data,'JSON');
		}
	}
	//选择客户列表时调用
	public function customer_list(){
		if($this->isPost()){
			if(isset($_POST['search'])){
				$where['name'] = array('like','%'.trim($_POST['search']).'%');
			}
			$m_customer = M('Customer');
			$m_contacts = M('Contacts');
			$m_r_contacts_customer = M('RContactsCustomer');
			$outdays = M('config') -> where('name="customer_outdays"')->getField('value');
			$outdate = empty($outdays) ? 0 : time()-86400*$outdays;
			$where['owner_role_id'] = array('in',implode(',', getPerByAction(customer,index)));
			$where['is_deleted'] = array('neq',1);
			$where['_string'] = 'update_time > '.$outdate.' OR is_locked = 1';
			$p = isset($_POST['p']) ? intval($_POST['p']) : 1 ;
			$customer = $m_customer->where($where)->order('create_time desc')->page($p.',10')->field('name,customer_id,contacts_id')->select();
			foreach($customer as $k=>$v){
				//如果存在首要联系人，则查出首要联系人。否则查出联系人中第一个。
				if(!empty($v['contacts_id'])){
					$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$v['contacts_id'])->find();
					$customer[$k]['contacts_name'] = $contacts['name'];
				}else{
					$contacts_customer = $m_r_contacts_customer->where('customer_id = %d',$v['customer_id'])->limit(1)->order('id desc')->select();
					if(!empty($contacts_customer)){
						$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$contacts_customer[0]['contacts_id'])->find();
					}
					$customer[$k]['contacts_id'] = $contacts['contacts_id'];
					$customer[$k]['contacts_name'] = $contacts['name'];
				}
			}
			$customer = empty($customer) ? array() : $customer;
			$count = $m_customer->where($where)->count();
			$page = ceil($count/10);
			$data['list'] = $customer;
			$data['page'] = $page;
			$this->ajaxReturn($data,'success',1);
		}
	}
	//新版客户动态页面
	public function dynamic(){
		if($this->isPost()){
			if($this->roles == 2){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$customer_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			if(!$customer_id){
				$this->ajaxReturn('参数错误!','参数错误!',2);
			}
			$customer = D('CustomerView')->where('customer.customer_id = %d', $customer_id)->find();
			if (!$customer || $customer['is_deleted'] == 1) {
				$this->type = 1;
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$outdays = M('config') -> where('name="customer_outdays"')->getField('value');
			$outdate = empty($outdays) ? 0 : time()-86400*$outdays;
			if($customer['owner_role_id'] != 0 && ($customer['update_time'] > $outdate || $customer['is_locked'] == 1)){
				if(!in_array($customer['owner_role_id'],getPerByAction('customer','view'))){
					$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
				}
			}
			//沟通日志
			$customer_business = M('business')->where('customer_id = %d and is_deleted=0', $customer_id)->select();
			foreach($customer_business as $k=>$v){
				$business_id[] = $v['business_id'];
			}
			$customer_log_ids = M('rCustomerLog')->where('customer_id = %d', $customer_id)->getField('log_id', true);
			$customer_log_ids = $customer_log_ids ? $customer_log_ids : array();
			$business_log_ids = M('rBusinessLog')->where('business_id in (%s)', implode(',', $business_id))->getField('log_id', true);
			$business_log_ids = $business_log_ids ? $business_log_ids : array();
			$m_log = M('Log');
			$logcount = $m_log->where('log_id in (%s)', implode(',', array_merge($customer_log_ids,$business_log_ids)))->count();
			$log_count = empty($logcount) ? 0 : intval($logcount);

			$type_list[0]['count'] = $log_count;
			$type_list[0]['name'] = '沟通日志';

			//商机统计
			$business_count = M('business')->where('customer_id = %d and is_deleted=0', $customer['customer_id'])->count();
			$type_list[1]['count'] = empty($business_count) ? 0 : intval($business_count);
			$type_list[1]['name'] = '商机';

			//合同统计
			$contract_count = D('ContractView')->where('contract.business_id in (%s) and contract.is_deleted=0', implode(',', $business_id))->count();
			$type_list[2]['count'] = empty($contract_count) ? 0 : intval($contract_count);
			$type_list[2]['name'] = '合同';

			//联系人统计
			$contacts_ids = M('rContactsCustomer')->where('customer_id = %d', $customer_id)->getField('contacts_id', true);
			$contacts_count = M('contacts')->where('contacts_id in (%s) and is_deleted=0', implode(',', $contacts_ids))->count();
			$type_list[3]['count'] = empty($contacts_count) ? 0 : intval($contacts_count);
			$type_list[3]['name'] = '联系人';

			//任务统计
			$m_task = M('Task');
			$task_ids = M('rCustomerTask')->where('customer_id = %d', $customer_id)->getField('task_id', true);
			$task_list = $m_task->where('task_id in (%s) and is_deleted=0', implode(',', $task_ids))->select();
			$task_count = $m_task->where('task_id in (%s) and is_deleted=0', implode(',', $task_ids))->count();
			foreach ($task_list as $key=>$value) {
				$customer['task'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
			}
			$type_list[4]['count'] = empty($task_count) ? 0 : intval($task_count);
			$type_list[4]['name'] = '任务';
			//是否关注
			$m_focus = M('CustomerFocus');
			$focus_id = $m_focus->where(array('customer_id'=>$customer_id,'user_id'=>session('user_id')))->getField('focus_id');
			if(!empty($focus_id)){
				$data['focus'] = 1;
			}else{
				$data['focus'] = 0;
			}
			$data['type'] = $type_list;
			$this->ajaxReturn($data,'success',1);
		}
	}
	//客户下沟通日志
	public function loglist(){
		if($this->isPost()){
			$customer_id = isset($_POST['id']) ? intval($_POST['id']) : 0 ;
			if(empty($customer_id)){
				$this->ajaxReturn('参数错误！','参数错误！',2);
			}
			$customer_business = M('business')->where('customer_id = %d and is_deleted=0', $customer_id)->select();
			foreach($customer_business as $k=>$v){
				$business_id[] = $v['business_id'];
			}
			//沟通日志统计
			$p = isset($_POST['p']) ? intval($_POST['p']) : 1 ;
			$customer_log_ids = M('rCustomerLog')->where('customer_id = %d', $customer_id)->getField('log_id', true);
			$customer_log_ids = $customer_log_ids ? $customer_log_ids : array();
			$business_log_ids = M('rBusinessLog')->where('business_id in (%s)', implode(',', $business_id))->getField('log_id', true);
			$business_log_ids = $business_log_ids ? $business_log_ids : array();
			$m_log = M('log');
			$log_list = $m_log->where('log_id in (%s)', implode(',', array_merge($customer_log_ids,$business_log_ids)))->page($p.',10')->order('create_date')->select();
			$logcount = $m_log->where('log_id in (%s)', implode(',', array_merge($customer_log_ids,$business_log_ids)))->count();
			//客户、商机沟通日志
			foreach ($log_list as $key=>$value) {
				$log_list[$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->field('user_name,role_id,img,role_name,department_name')->find();
				$log_list[$key]['type'] = 1;//沟通日志
			}
			$log_count = empty($logcount)?0:$logcount;
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
	//客户关注
	public function focus(){
		if($this->isPost()){
			$customer_id = $_POST['id'];
			$m_focus = M('CustomerFocus');
			$focus_id = $m_focus->where(array('customer_id'=>$customer_id,'user_id'=>session('role_id')))->find();
			if(!$focus_id){
				$data['user_id'] = session('role_id');
				$data['customer_id'] = $customer_id;
				$data['focus_time'] = time();
				if($m_focus->add($data)){
					$this->ajaxReturn(1,'关注成功',1);
				}else{
					$this->ajaxReturn('关注失败，请重试','关注失败，请重试',2);
				}
			}else{
				if($m_focus->where(array('customer_id'=>$customer_id,'user_id'=>session('role_id')))->delete()){
					$this->ajaxReturn(0,'取消关注成功',1);
				}else{
					$this->ajaxReturn('取消关注失败，请重试','取消关注失败，请重试',2);
				}
			}
		}
	}
	//新版客户添加
	public function addnew(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		$leads_id = intval($_GET['leads_id']);
		if($this->isPost()){
			$m_customer = D('Customer');
			$m_customer_data = D('CustomerData');
			$params = json_decode($_POST['params'],true);
			if(!is_array($params)){
				$this->ajaxReturn('非法的数据格式!','非法的数据格式!',2);
			}
			$field_list = M('Fields')->where('model = "customer" and in_add = 1')->order('order_id')->select();
			foreach ($field_list as $v){
				if($v['is_validate'] == 1){
					if($v['is_null'] == 1){
						if($params[$v['field']] == ''){
							$this->ajaxReturn($v['name'].'不能为空',$v['name'].'不能为空',2);
						}
					}
					if($v['is_unique'] == 1){
						$res = validate('customer',$v['field'],$params[$v['field']]);
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
			if($m_customer->create($params) && $m_customer_data->create($params)!==false){
				if($params['con_name']){
					$contacts = array();
					if($params['con_name']) $contacts['name'] = $params['con_name'];
					if($params['owner_role_id']) $contacts['owner_role_id'] = $params['owner_role_id'];
					if($params['saltname']) $contacts['saltname'] = $params['saltname'];
					if($params['con_email']) $contacts['email'] = $params['con_email'];
					if($params['con_post']) $contacts['post'] = $params['con_post'];
					if($params['con_qq']) $contacts['qq_no'] = $params['con_qq'];
					if($params['con_telephone']) $contacts['telephone'] = $params['con_telephone'];
					if($params['con_description']) $contacts['description'] = $params['con_description'];
					if(!empty($contacts)){
						$contacts['creator_role_id'] = session('role_id');
						$contacts['create_time'] = time();
						$contacts['update_time'] = time();
						$contacts_id = M('Contacts')->add($contacts);
					}
				}
                $m_customer->create_time = time();
                $m_customer->update_time = time();
                if($contacts_id) $m_customer->contacts_id = $contacts_id;
                $m_customer->creator_role_id = session('role_id');
                if(!$customer_id = $m_customer->add()){
                    $this->ajaxReturn('添加失败','添加失败',2);
                }
                $m_customer_data->customer_id = $customer_id;
                $m_customer_data->add();
				//线索转换客户
				if ($leads_id) {
					$r_module = array(
						array('key'=>'log_id','r1'=>'RCustomerLog','r2'=>'RLeadsLog'), 
						array('key'=>'file_id','r1'=>'RCustomerFile','r2'=>'RFileLeads'),
						array('key'=>'event_id','r1'=>'RCustomerEvent','r2'=>'REventLeads'),
						array('key'=>'task_id','r1'=>'RCustomerTask','r2'=>'RLeadsTask')
					);
					foreach ($r_module as $key=>$value) {
						$key_id_array = M($value['r2'])->where('leads_id = %d', $leads_id)->getField($value['key'],true);
						$r1 = M($value['r1']);
						$data['customer_id'] = $customer_id;
						foreach($key_id_array as $k=>$v){
							$data[$value['key']] = $v;
							$r1->add($data);
						}
					}
					$leads_data['is_transformed'] = 1;
					$leads_data['update_time'] = time();
					$leads_data['customer_id'] = $customer_id;
					$leads_data['contacts_id'] = $contacts_id;
					$leads_data['transform_role_id'] = session('role_id');
					M('Leads')->where('leads_id = %d', $leads_id)->save($leads_data);
				}
                //记录操作记录
                actionLog($customer_id);
                if ($contacts_id && $customer_id) {
                    $rcc['contacts_id'] = $contacts_id;
                    $rcc['customer_id'] = $customer_id;
                    M('RContactsCustomer')->add($rcc);

                }
				$this->ajaxReturn('添加成功','添加成功',1);
			}else{
				//$this->ajaxReturn('添加失败','error',2);
				$this->ajaxReturn('添加失败，'.$m_customer->getError().$m_customer_data->getError(),'添加失败',2);
            }
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
	//新版编辑客户
	public function editnew(){
		if($this->isPost()){
			if($this->roles == 2){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$params = json_decode($_POST['params'],true);
			$customer_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			if(!$customer_id){
				$this->ajaxReturn('参数错误!','参数错误!',2);
			}
			$params['customer_id'] = $customer_id;
			$customer = D('CustomerView')->where('customer.customer_id = %d', $customer_id)->find();
			if (!$customer) {
				$this->ajaxReturn('记录不存在或已被删除','记录不存在或已被删除',2);
			}elseif(!in_array($customer['owner_role_id'],getPerByAction('customer','edit')) && !session('?admin')){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$field_list = M('Fields')->where('model = "customer"')->order('order_id')->select();
			$d_customer = D('Customer');
			$d_customer_data = D('CustomerData');
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
						$res = validate('customer',$v['field'],$params[$v['name']],$customer_id);
						if($res == 1){
							$this->ajaxReturn($v['name'].':'.$params[$v['name']].'已存在','error',2);
						}
					}
				}
			}
			if($d_customer->create($params) && $d_customer_data->create($params)!==false){
				$d_customer->update_time = time();
				$a = $d_customer->where('customer_id=' . $customer['customer_id'])->save();
				$b = $d_customer_data->where('customer_id=' . $customer['customer_id'])->save();
				if($a !== false && $b !== false){
					actionLog($customer['customer_id']);
					$this->ajaxReturn('修改成功','修改成功',1);
				}else{
					$this->ajaxReturn('修改失败','修改失败',2);
				}
			}else{
				$this->ajaxReturn($d_customer->getError().$d_customer_data->getError(),'修改失败',2);
			}
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
	//新版客户详情
	public function viewnew(){
		if($this->isPost()){
			if($this->roles == 2){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			$customer_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			$customer = D('CustomerView')->where('customer.customer_id = %d', $customer_id)->find();
			if(!$customer || $customer['is_deleted'] == 1){
				$this->ajaxReturn('客户不存在或已删除!','客户不存在或已删除!',2);
			}
			$outdays = M('config') -> where('name="customer_outdays"')->getField('value');
			$outdate = empty($outdays) ? 0 : time()-86400*$outdays;
			if($customer['owner_role_id'] != 0 && ($customer['update_time'] > $outdate || $customer['is_locked'] == 1)){
				if(!in_array($customer['owner_role_id'],getPerByAction('customer','view'))){
					$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
				}
			}
			$data_list = array();
			//查询固定信息
			//负责人
			$customer_owner = D('RoleView')->where('role.role_id = %d', $customer['owner_role_id'])->field('user_name,role_id')->find();
			$data_list[0]['field'] = 'owner_role_id';
			$data_list[0]['name'] = '负责人';
			$data_list[0]['form_type'] = 'box';
			$data_list[0]['val'] = empty($customer_owner['user_name']) ? '' : $customer_owner['user_name'];
			$data_list[0]['id'] = empty($customer_owner['role_id']) ? '' : $customer_owner['role_id'];
			$data_list[0]['type'] = 1;
			//创建人
			$customer_create = D('RoleView')->where('role.role_id = %d', $customer['creator_role_id'])->field('user_name,role_id')->find();
			$data_list[1]['field'] = 'creator_role_id';
			$data_list[1]['name'] = '创建人';
			$data_list[1]['form_type'] = 'text';
			$data_list[1]['val'] = $customer_create['user_name'];
			$data_list[1]['id'] = $customer_create['role_id'];
			$data_list[1]['type'] = 1;
			//获取首要联系人字段信息
			$contacts_id = $customer['contacts_id'];
			$contacts_info = M('Contacts')->where('contacts_id = %d',$contacts_id)->find();
			$data_list[2]['field'] = 'contacts_id';
			$data_list[2]['name'] = '首要联系人';
			$data_list[2]['form_type'] = 'text';
			if(!$contacts_info){
				$data_list[2]['val'] = '';
				$data_list[2]['id'] = '';
			}else{
				$data_list[2]['val'] = $contacts_info['name'];
				$data_list[2]['id'] = $contacts_info['contacts_id'];
			}
			$data_list[2]['type'] = 5;
			//取得字段列表
			$where = array();
			$where['field'] = array('neq','tags');
			$where['model'] = 'customer';
			$field_list = M('Fields')->where($where)->order('order_id')->select();
			$i = 3;
			foreach($field_list as $k=>$v){
				$field = trim($v['field']);
				$data_list[$i]['field'] = $field;
				$data_list[$i]['name'] = trim($v['name']);
				$data_list[$i]['form_type'] = $v['form_type'];
				$data_a = trim($customer[$v['field']]);
				if($v['form_type'] == 'editor'){
					$data_list[$i]['val'] = '暂不支持';
				}elseif($v['form_type'] == 'address'){
					$address_array = str_replace(chr(10),' ',$data_a);
					$data_list[$i]['val'] = $address_array;
				}else{
					$data_list[$i]['val'] = $data_a;
				}
				$data_list[$i]['type'] = 0;
				$data_list[$i]['id'] = '';
				$i++;
			}
			//判断是否客户池,客户池只给看权限
			//获取权限
			if($customer['owner_role_id'] && $customer['update_time'] >= $outdate){
				$data['permission'] = permissionlist(MODULE_NAME,$customer['owner_role_id']);
			}else{
				if(session('?admin')){
					$data['permission'] = array('edit'=>1,'view'=>1,'delete'=>1);
				}else{
					$data['permission'] = array('view'=>1);
				}
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