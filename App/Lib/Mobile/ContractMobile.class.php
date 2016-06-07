<?php
class ContractMobile extends Action {

	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('supplierlist')
		);
		B('AppAuthenticate',$action);
		$this->_permissionRes = getPerByAction(MODULE_NAME,ACTION_NAME);
		Global $role;
		$this->role = $role;
		Global $roles;
		$this->roles = $roles;
	}
	//合同列表
	public function index(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		$contract_custom = M('config') -> where('name="contract_custom"')->getField('value');
		if(!$contract_custom)  $contract_custom = '5k_crm';
		if($this->isPost()){
			//获取权限
			$permission_list = apppermission(MODULE_NAME,ACTION_NAME);
			if($permission_list){
				$data['permission_list'] = $permission_list;
			}else{
				$data['permission_list'] = array();
			}
			$m_user = M('user');
			$last_read_time_js = $m_user->where('role_id = %d', session('role_id'))->getField('last_read_time');
			$last_read_time = json_decode($last_read_time_js, true);
			$last_read_time['contract'] = time();
			$m_user->where('role_id = %d', session('role_id'))->setField('last_read_time',json_encode($last_read_time));
			$d_contract = D('ContractView');
			$where = array();
			//按合同编号查询
			if(isset($_POST['search'])){
				$where['number'] = array('like','%'.trim($_POST['search']).'%');
			}
			//接收查询条件
			$searchfield = isset($_POST['searchfield']) ? trim($_POST['searchfield']) : '';
			$params_search = json_decode($searchfield,true);
			$below_ids = getPerByAction(MODULE_NAME,ACTION_NAME,$sub_role=true);
			$where['contract.owner_role_id'] = array('in', $this->_permissionRes);
			$order = 'contract.update_time desc,contract.contract_id asc';
			//查询条件
			switch ($_GET['by']){
				case 'create':
					$where['creator_role_id'] = session('role_id');
					break;
				case 'sub' :
					$where['contract.owner_role_id'] = array('in',implode(',', $below_ids));
					break;
				case 'subcreate' :
					$where['creator_role_id'] = array('in',implode(',', $below_ids));
					break;
				case 'today' :
					$where['due_time'] =  array('between',array(strtotime(date('Y-m-d')) -1 ,strtotime(date('Y-m-d')) + 86400));
					break;
				case 'week' :
					$week = (date('w') == 0)?7:date('w');
					$where['due_time'] =  array('between',array(strtotime(date('Y-m-d')) - ($week-1) * 86400 -1 ,strtotime(date('Y-m-d')) + (8-$week) * 86400));
					break;
				case 'month' :
					$next_year = date('Y')+1;
					$next_month = date('m')+1;
					$month_time = date('m') ==12 ? strtotime($next_year.'-01-01') : strtotime(date('Y').'-'.$next_month.'-01');
					$where['due_time'] = array('between',array(strtotime(date('Y-m-01')) -1 ,$month_time));
					break;
				case 'add' :
					$order = 'contract.create_time desc,contract.contract_id asc';
					break;
				case 'deleted' :
					$where['is_deleted'] = 1;
					break;
				case 'update' :
					$order = 'contract.update_time desc,contract.contract_id asc';
					break;
				case 'me' :
					$where['contract.owner_role_id'] = session('role_id');
					break;
			}
			if (!isset($where['is_deleted'])) {
				$where['is_deleted'] = 0;
			}
			if($params_search){
				$where[$params_search['field']] = array('like','%'.trim($params_search['val']).'%');
			}
			//商机下的合同
			if($_GET['business_id']){
				$contract_ids = M('rBusinessContract')->where('business_id = %d', $_GET['business_id'])->getField('contract_id', true);
				$where['contract.contract_id'] = array('in',$contract_ids);
			}
			//客户下的合同
			if($_GET['customer_id']){
				//$where['contract.customer_id'] = $_GET['customer_id'];
				$business_ids = M('business')->where('customer_id = %d and is_deleted=0', $_GET['customer_id'])->getField('business_id',true);
				$where['contract.business_id'] = array('in',$business_ids);
			}
			$p = isset($_POST['p']) ? intval($_POST['p']) : 1 ;
			$list = $d_contract->where($where)->page($p.',10')->order($order)->field('number,price,customer_id,contract_id,supplier_id,owner_role_id')->select();
			foreach($list as $k=>$v){
				$customer_name = M('Customer')->where('customer_id = %d',$v['customer_id'])->getField('name');
				if($customer_name){
					$list[$k]['customer_name'] = $customer_name;
				}else{
					$list[$k]['customer_name'] = '';
				}
				$owner_role_id = $v['owner_role_id'];
				//获取操作权限
				$list[$k]['permission'] = permissionlist(MODULE_NAME,$owner_role_id);
				//合同到期时间
				$end_date = 0;
				$end_date =  $d_contract->where('contract_id = %d', $v['contract_id'])->getField('end_date');
				if($end_date){
					$list[$k]['days'] = floor(($end_date-time())/86400);
				}else{
					$list[$k]['days'] = '';
				}
			}
			$count = $d_contract->where($where)->count();
			//获取查询条件信息
			$list = empty($list) ? array() : $list;
			$page = ceil($count/10);
			if($p == 1){
				$data['contract_custom'] = $contract_custom;
			}
			$data['list'] = $list;
			$data['page'] = $page;
			$data['info'] = 'success';
			$data['status'] = 1;
			$this->ajaxReturn($data,'JSON');
		}else{
			$this->ajaxReturn('非法请求',"非法请求",2);
		}
	}
	
	//合同详情
	public function view(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$id = $_POST['id'];
			$contract = D('ContractView');
			$m_user = M('User');
			$m_contacts = M('Contacts');
			$info = $contract->where('contract_id = %d',$id)->find();
			//权限判断
			if(empty($info) || empty($id)) {
				$this->ajaxReturn('合同不存在或已被删除！','合同不存在或已被删除！',2);
			}elseif(!in_array($info['owner_role_id'], $this->_permissionRes)) {
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			unset($info['supplier_id']);
			$i = 0;
			foreach($info as $k=>$v){
				$contract_list[$i]['field'] = $k;
				$contract_list[$i]['name'] = '';
				if($k == 'content'){
					$contract_list[$i]['val'] = '--暂不支持--';
				}else{
					$contract_list[$i]['val'] = $v;
				}
				if($k == 'owner_role_id'){
					$contract_list[$i]['id'] = $v;
					if($v){
						unset($contract_list[$i]['val']);
					}
					$owner = $m_user->where('role_id = %d',$v)->getField('name');
					$contract_list[$i]['val'] = $owner;
					$contract_list[$i]['type'] = 1;
				}elseif($k == 'creator_role_id'){
					$contract_list[$i]['id'] = $v;
					if($v){
						unset($contract_list[$i]['val']);
					}
					$creator_name = $m_user->where('role_id = %d',$v)->getField('name');
					$contract_list[$i]['id'] = $v;
					$contract_list[$i]['val'] = $creator_name;
					$contract_list[$i]['type'] = 1;
				}elseif($k == 'business_id'){
					$contract_list[$i]['id'] = $v;
					if($v){
						unset($contract_list[$i]['val']);
					}
					$business_name = M('Business')->where('business_id = %d',$v)->getField('name');
					$contract_list[$i]['id'] = $v;
					$contract_list[$i]['val'] = $business_name;
					$contract_list[$i]['type'] = 4;
				}elseif($k == 'customer_id'){
					$contract_list[$i]['id'] = $v;
					if($v){
						unset($contract_list[$i]['val']);
					}
					$customer_name = M('Customer')->where('customer_id = %d',$v)->getField('name');
					$contract_list[$i]['id'] = $v;
					$contract_list[$i]['val'] = $customer_name;
					$contract_list[$i]['type'] = 3;
				}elseif($k == 'type'){
					$contract_list[$i]['id'] = $v;
					if($v){
						unset($contract_list[$i]['val']);
					}
					$customer_name = M('Customer')->where('customer_id = %d',$v)->getField('name');
					$contract_list[$i]['id'] = $v;
					if($v == 1){
						$contract_list[$i]['val'] = '销售';
					}elseif($v == 2){
						$contract_list[$i]['val'] = '采购';
					}else{
						$contract_list[$i]['val'] = '销售合同';
					}
					$contract_list[$i]['type'] = 0;
				}elseif($k == 'contacts_id'){
					$contract_list[$i]['id'] = $v;
					if($v){
						unset($contract_list[$i]['val']);
					}
					$contract_list[$i]['id'] = $v;
					$contract_list[$i]['val'] = $info['contacts_name'];
					$contract_list[$i]['type'] = 0;
				}else{
					$contract_list[$i]['id'] = '';
					$contract_list[$i]['type'] = 0;
				}
				$i++;
			}

			$owner_role_id = $info['owner_role_id'];
			//获取权限
			$data['permission'] = permissionlist(MODULE_NAME,$owner_role_id);
			$data['data'] = $contract_list;
			$data['info'] = 'success';
			$data['status'] = 1;
			$this->ajaxReturn($data,'JSON');
		}
	}
}