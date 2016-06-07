<?php

class ContactsMobile extends Action{

	 public function _initialize(){
		$action = array(
			'permission'=>array('radiolistdialog','radiolistdialogs')
		);
		B('AppAuthenticate', $action);
		$this->_permissionRes = getPerByAction(MODULE_NAME,ACTION_NAME);
		Global $roles;
		$this->roles = $roles;
	}

	public function radioListDialogs(){
		if($this->isAjax()){
			$rcc =  M('RContactsCustomer');
			$m_contacts = M('contacts');
			$where['owner_role_id'] = array('in', implode(',', $this->_permissionRes));
			$where['is_deleted'] = 0;
			$p = isset($_GET['p']) ? intval($_GET['p']) : 1;
			if($_GET['customer_id']){
				$customer_id = $_GET['customer_id'];
				$contacts_id = $rcc->where('customer_id = %d',$customer_id )->getField('contacts_id', true);
				$where['contacts_id'] = array('in', implode(',', $contacts_id));
			}
			if($_GET['search']){
				$where['name'] = array('like','%'.trim($_GET['search']).'%');
			}
			$list = $m_contacts->where($where)->order('create_time desc')->page($p.',10')->field('name,contacts_id')->select();
			$count = $m_contacts->where($where)->order('create_time desc')->count();

			$total = $count%10 > 0 ? ceil($count/10) : $count/10;
			$data['list'] = $list;
			$data['total'] = $total;
			$data['p'] = $p;
			$data['customer_id'] = $customer_id;
			$this->ajaxReturn($data,'success',1);
		}else{
			$rcc =  M('RContactsCustomer');
			$m_contacts = M('contacts');
			$where['owner_role_id'] = array('in', implode(',', $this->_permissionRes));
			$where['is_deleted'] = 0;
			$where['contacts_id'] = 0;
			$p = isset($_GET['p']) ? intval($_GET['p']) : 1;
			if($_GET['customer_id']){
				$customer_id = $_GET['customer_id'];
				$contacts_id = $rcc->where('customer_id = %d',$customer_id )->getField('contacts_id', true);
				$where['contacts_id'] = array('in', implode(',', $contacts_id));
			}
			$list = $m_contacts->where($where)->order('create_time desc')->page($p.',10')->field('name,contacts_id')->select();
			$count = $m_contacts->where($where)->order('create_time desc')->count();

			$this->total = $count%10 > 0 ? ceil($count/10) : $count/10;
			$this->contactsList = $list;
			$this->p = $p;
			$this->customer_id = $customer_id;
			$this->display();
		}
	}


	public function radioListDialog(){
		if($this->isAjax()){
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
			if(isset($_GET['search'])){
				$where['name'] = array('like','%'.trim($_GET['search']).'%');
			}
			$where['owner_role_id'] = array('in', implode(',', $this->_permissionRes));
			$where['is_deleted'] = 0;
			if($_GET['customer_id']){
				$contacts_id = $rcc->where('customer_id = %d', intval($_GET['customer_id']))->getField('contacts_id', true);
				$where['contacts_id'] = array('in', implode(',', $contacts_id));
				$this->customer_id = intval($_GET['customer_id']);
			}
			$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
			$list = $m_contacts->where($where)->field('contacts_id,name')->order('create_time desc')->field('contacts_id,name,post')->page($p.',10')->select();
			$this->customer_id = $customer_id;
			$count = $m_contacts->where($where)->count();
			foreach ($list as $k=>$value) {
				$customer_id = $rcc->where('contacts_id = %d', $value['contacts_id'])->getField('customer_id');
				$list[$k]['customer'] = M('customer')->where('customer_id = %d', $customer_id)->field('name')->find();
			}
			$total = $count%10 > 0 ? ceil($count/10) : $count/10;
			$data['list'] = $list;
			$data['total'] = $total;
			$data['p'] = $p;
			$this->ajaxReturn($data,'success',1);
		}
	}
	//联系人列表
	public function index(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			//获取添加权限
			$permission_list = apppermission(MODULE_NAME,ACTION_NAME);
			if($permission_list){
				$data['permission_list'] = $permission_list;
			}else{
				$data['permission_list'] = array();
			}
			$rcc =  M('RContactsCustomer');
			$m_contacts = M('contacts');
			$d_contacts = D('ContactsView');
			$m_fields = M('Fields');
			$m_customer = M('Customer');
			$where = array();
			$searchfield = isset($_POST['searchfield']) ? trim($_POST['searchfield']) : '';
			$params_search = json_decode($searchfield,true);
			if($params_search){
				$where[$params_search['field']] = array('like','%'.trim($params_search['val']).'%');
			}
			$all_ids = getPerByAction('contacts',ACTION_NAME);
			if(isset($_POST['search'])){
				$where['name'] = array('like','%'.trim($_POST['search']).'%');
			}
			switch ($by) {
				case 'today' : $where['create_time'] =  array('gt',strtotime(date('Y-m-d', time()))); break;
				case 'week' : $where['create_time'] =  array('gt',(strtotime(date('Y-m-d', time())) - (date('N', time()) - 1) * 86400)); break;
				case 'month' : $where['create_time'] = array('gt',strtotime(date('Y-m-01', time()))); break;
				case 'add' : $order = 'create_time desc'; break;
				case 'update' : $order = 'update_time desc'; break;
				case 'deleted' : $where['is_deleted'] = 1; break;
				default : $where['owner_role_id'] = array('in', $all_ids); break;
			}
			if($_GET['customer_id']){
				$contacts_ids = M('rContactsCustomer')->where('customer_id = %d', $_GET['customer_id'])->getField('contacts_id', true);
				$where['contacts_id'] = array('in',$contacts_ids);
				unset($where['owner_role_id']);
			}else{
				$all_customer = M('customer')->where('is_deleted = 0')->getField('customer_id',true);
				$customer_Str = implode(',',$all_customer);
				$where['Customer.is_deleted'] = 0;
				$where['RContactsCustomer.customer_id'] = array('in',$customer_Str);
			}
			//$where['owner_role_id'] = array('in', implode(',', $this->_permissionRes));
			$where['is_deleted'] = 0;
			if($_POST['customer_id']){
				$contacts_id = $rcc->where('customer_id = %d', intval($_POST['customer_id']))->getField('contacts_id', true);
				$where['contacts_id'] = array('in', implode(',', $contacts_id));
			}
			$p = isset($_POST['p']) ? intval($_POST['p']) : 1 ;
			$list = $d_contacts->where($where)->order('create_time desc')->field('contacts_id,name,post,telephone')->page($p.',10')->select();
			$count = $d_contacts->where($where)->count();
			foreach ($list as $key=>$value) {
				$customer_id = $rcc->where('contacts_id = %d', $value['contacts_id'])->getField('customer_id');
				$customer_list = $m_customer->where('customer_id = %d', $customer_id)->field('name,owner_role_id')->find();
				$list[$key]['customer_name'] = $customer_list['name'];
				$owner_role_id = $customer_list['owner_role_id'];
				//获取操作权限
				$list[$key]['permission'] = permissionlist(MODULE_NAME,$owner_role_id);
			}
			//获取筛选条件
			$data['fields_list'] = array();
			$page = ceil($count/10);
			$data['list'] = $list;
			$data['page'] = $page;
			$this->ajaxReturn($data,'success',1);
		}
	}
	//联系人详情
	public function view(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$contacts_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
			$rContactsCustomer = M('RContactsCustomer');
			$d_contacts = D('ContactsView');
			$m_customer = M('Customer');
			$contacts = $d_contacts->where('contacts.contacts_id = %d' , $contacts_id)->find();
			if (!$contacts || $contacts['is_deleted'] == 1) {
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}
			if (empty($contacts_id)) {
				$this->ajaxReturn('参数错误','参数错误',2);
			} else {
				//检查权限
				$all_ids = getPerByAction('contacts',ACTION_NAME);
				$customer_idArr = $m_customer->where(array('owner_role_id'=>array('in', $all_ids)))->getField('customer_id', true);
				$customer_id = $rContactsCustomer->where('contacts_id = %d', $contacts_id)->getField('customer_id');
				$owner_role_id = $m_customer->where('customer_id = %d',$customer_id)->getField('owner_role_id');
				
				//判断联系人所在客户是否在客户池，如果在则不判断权限
			
				 //查询客户数据
				$customer = D('CustomerView')->where('customer.customer_id = %d', $customer_id)->find();
				$outdays = M('config') -> where('name="customer_outdays"')->getField('value');
				$outdate = empty($outdays) ? 0 : time()-86400*$outdays;
				$m_customer_share = M('customer_share')->select();
				$sharing_id = session('role_id');
				foreach($m_customer_share as $k=>$v){
					$by_sharing_id = explode(',',$v['by_sharing_id']);
					if(in_array($sharing_id,$by_sharing_id)){
						$customerid[] = $v['customer_id'];
					}
				}
				$is_share = in_array($customer_id,$customerid);
				if($customer['owner_role_id'] != 0 && ($customer['update_time'] > $outdate || $customer['is_locked'] == 1) && $is_share ==0 && !in_array($customer_id, $customer_idArr)){
					$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
				}else{
					//联系人二维码
					$qrcode = 'index.php?m=contacts&a=qrcode&contacts_id='.$contacts_id;
					//联系人字段显示
					$field_list = array(
						'0'=>array('field'=>'name','name'=>'姓名','is_null'=>1,'form_type'=>'text'),
						'1'=>array('field'=>'saltname','name'=>'尊称','is_null'=>0,'form_type'=>'text'),
						'2'=>array('field'=>'customer_id','name'=>'所属客户','is_null'=>1,'form_type'=>'text'),
						'3'=>array('field'=>'post','name'=>'职位','is_null'=>0,'form_type'=>'text'),
						'4'=>array('field'=>'telephone','name'=>'电话','is_null'=>0,'form_type'=>'phone'),
						'5'=>array('field'=>'email','name'=>'邮件','is_null'=>0,'form_type'=>'email'),
						'6'=>array('field'=>'qq_no','name'=>'QQ','is_null'=>0,'form_type'=>'number'),
						'7'=>array('field'=>'zip_code','name'=>'邮编','is_null'=>0,'form_type'=>'number'),
						'8'=>array('field'=>'address','name'=>'联系地址	','is_null'=>0,'form_type'=>'text'),
						'9'=>array('field'=>'description','name'=>'备注','is_null'=>0,'form_type'=>'textarea'),
					);
					foreach($field_list as $k=>$v){
						$field = trim($v['field']);
						$data_list[$k]['field'] = $field;
						$data_list[$k]['name'] = trim($v['name']);
						$data_a = trim($contacts[$v['field']]);
						$data_list[$k]['form_type'] = $v['form_type'];
						if($v['form_type'] == 'editor'){
							$data_list[$k]['val'] = '暂不支持';
						}elseif($v['form_type'] == 'address'){
							$address_array = str_replace(chr(10),' ',$data_a);
							$data_list[$k]['val'] = $address_array;
						}else{
							$data_list[$k]['val'] = $data_a;
						}
						if($field == 'customer_id'){
							unset($data_list[$k]['val']);
							$customer_name = $m_customer->where(array('customer_id'=>$customer_id))->getField('name');
							$data_list[$k]['val'] = $customer_name;
							$data_list[$k]['type'] = 3;
							$data_list[$k]['id'] = $customer_id;
						}else{
							$data_list[$k]['type'] = 0;
							$data_list[$k]['id'] = '';
						}
					}
					//获取权限
					$data['permission'] = permissionlist(MODULE_NAME,$owner_role_id);
					$data['customer_id'] = $customer_id;
					$data['qrcode'] = $qrcode;
					$data['data'] = $data_list;
					$data['info'] = 'success';
					$data['status'] = 1;
					$this->ajaxReturn($data,'JSON');
				}
			}
		}
	}
	public function object_array($array){
		if(is_object($array)){
			$array = (array)$array;
		}
		if(is_array($array)){
			foreach($array as $key=>$value){
			  $array[$key] = object_array($value);
			}
		}
		return $array;
	}
	//联系人字段
	public function add(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$params = json_decode($_POST['params'],true);
			$name = trim($params['name']);
			$customer_id = trim($params['customer_id']);
			if ($name == '' || $name == null) {
				$this->ajaxReturn('姓名不能为空','姓名不能为空',2);
			}
			if ($customer_id == '' || $customer_id == null) {
				$this->ajaxReturn('客户不能为空','客户不能为空',2);
			}
			$m_contacts = M('contacts');
			$m_contacts_data = M('ContactsData');
			//自定义字段
			$field_list = array(
				'0'=>array('field'=>'name','name'=>'姓名','is_null'=>1,'form_type'=>'text'),
				'1'=>array('field'=>'saltname','name'=>'尊称','is_null'=>0,'form_type'=>'text'),
				'2'=>array('field'=>'customer_id','name'=>'所属客户','is_null'=>1,'form_type'=>'text'),
				'3'=>array('field'=>'post','name'=>'职位','is_null'=>0,'form_type'=>'text'),
				'4'=>array('field'=>'telephone','name'=>'电话','is_null'=>0,'form_type'=>'phone'),
				'5'=>array('field'=>'email','name'=>'邮件','is_null'=>0,'form_type'=>'email'),
				'6'=>array('field'=>'qq_no','name'=>'QQ','is_null'=>0,'form_type'=>'number'),
				'7'=>array('field'=>'zip_code','name'=>'邮编','is_null'=>0,'form_type'=>'number'),
				'8'=>array('field'=>'address','name'=>'联系地址	','is_null'=>0,'form_type'=>'text'),
				'9'=>array('field'=>'description','name'=>'备注','is_null'=>0,'form_type'=>'textarea'),
			);
			foreach ($field_list as $v){
				if($v['is_validate'] == 1){
					if($v['is_null'] == 1){
						if($params[$v['field']] == ''){
							$this->ajaxReturn($v['name'].'不能为空',$v['name'].'不能为空',2);
						}
					}
					if($v['is_unique'] == 1){
						$res = validate('contacts',$v['field'],$params[$v['field']]);
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
			$m_contacts->create($params);
			$m_contacts->create_time = time();
			$m_contacts->update_time = time();
			$m_contacts->creator_role_id = session('role_id');
			if($contacts_id = $m_contacts->add()){
				if($contacts_id){
					$rContactsCustomer['contacts_id'] =  $contacts_id;
					$rContactsCustomer['customer_id'] =  $customer_id;
					if(M('RContactsCustomer') ->add($rContactsCustomer)){
						$this->ajaxReturn('添加成功','添加成功',1);
					}else{
						$this->ajaxReturn('添加失败','添加失败',2);
					}
				}
			}else{
				$this->ajaxReturn('添加失败','添加失败',2);
			}
		}
	}
	//联系人修改
	public function edit(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$params = json_decode($_POST['params'],true);
			$d_contacts = D('ContactsView');
			$rContactsCustomer = M('RContactsCustomer');
			$contacts_id =  isset($_POST['id']) ? intval($_POST['id']) : 0;
			if(empty($contacts_id)){
				$this->ajaxReturn('参数错误','参数错误',2);
			}
			$params['contacts_id'] = $contacts_id;
			//检查权限
			$all_ids = getPerByAction('contacts','edit');
			$field_list = array(
				'0'=>array('field'=>'name','name'=>'姓名','is_null'=>1,'form_type'=>'text'),
				'1'=>array('field'=>'saltname','name'=>'尊称','is_null'=>0,'form_type'=>'text'),
				'2'=>array('field'=>'customer_id','name'=>'所属客户','is_null'=>1,'form_type'=>'text'),
				'3'=>array('field'=>'post','name'=>'职位','is_null'=>0,'form_type'=>'text'),
				'4'=>array('field'=>'telephone','name'=>'电话','is_null'=>0,'form_type'=>'phone'),
				'5'=>array('field'=>'email','name'=>'邮件','is_null'=>0,'form_type'=>'email'),
				'6'=>array('field'=>'qq_no','name'=>'QQ','is_null'=>0,'form_type'=>'number'),
				'7'=>array('field'=>'zip_code','name'=>'邮编','is_null'=>0,'form_type'=>'number'),
				'8'=>array('field'=>'address','name'=>'联系地址	','is_null'=>0,'form_type'=>'text'),
				'9'=>array('field'=>'description','name'=>'备注','is_null'=>0,'form_type'=>'textarea'),
			);
			$customer_idArr = M('customer')->where(array('owner_role_id'=>array('in', $all_ids)))->getField('customer_id', true);
			$customer_id = $rContactsCustomer->where('contacts_id = %d', $contacts_id)->getField('customer_id');
			if(!in_array($customer_id, $customer_idArr)){
				$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
			}else{
				$contacts = $d_contacts->where(array('contacts_id'=>$contacts_id))->find();
				if(empty($contacts)) $this->ajaxReturn('记录不存在或已被删除','记录不存在或已被删除',2);
				$m_contacts = M('Contacts');
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
							$res = validate('contacts',$v['field'],$params[$v['name']],$contacts_id);
							if($res == 1){
								$this->ajaxReturn($v['name'].':'.$params[$v['name']].'已存在',$v['name'].':'.$params[$v['name']].'已存在',2);
							}
						}
					}
				}
				if($m_contacts->create($params)){
					$m_contacts->update_time = time();
					if (!empty($params['customer_id'])) {
						if (empty($customer_id)) {
							$data['contacts_id'] = $_POST['contacts_id'];
							$data['customer_id'] = $params['customer_id'];
							$rContactsCustomer ->where('contacts_id = %d', $_POST['contacts_id'])->delete();
							$rContactsCustomer -> add($data);
						}elseif ($params['customer_id'] != $customer_id) {
							M('RContactsCustomer') -> where('contacts_id = %d' , $_POST['contacts_id']) -> setField('customer_id',$params['customer_id']);
						}
					}else{
						$this->ajaxReturn('客户不能为空','客户不能为空',2);
					}
					$a = $m_contacts->where('contacts_id= %d',$contacts['contacts_id'])->save();
					if ($a !== false) {
						$this->ajaxReturn('修改成功','修改成功',1);
					} else {
						$this->ajaxReturn('修改失败','修改失败',2);
					}
				}else{
					$this->ajaxReturn($m_contacts->getError(),'修改失败',2);
				}
			}
		}
	}
	//联系人删除
	public function delete(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$id = $_POST['contacts_id'];
			//检查权限
			$rContactsCustomer = M('RContactsCustomer');
			$customer_id = $rContactsCustomer->where('contacts_id = %d', $id)->getField('customer_id');
			$all_ids = getPerByAction('contacts','delete');
			$customer_idArr = M('customer')->where(array('owner_role_id'=>array('in', $all_ids)))->getField('customer_id', true);
			$m_contacts = M('Contacts');
			if($id == '' || $id == null){
				$this->ajaxReturn('参数错误','参数错误',2);
			}else{
				if(session('?admin') || in_array($customer_id, $customer_idArr)){
					$m_contacts->delete_role_id = session('role_id');
					$m_contacts->delete_time = time();
					$m_contacts->is_deleted = 1;
					if($m_contacts->where(array('contacts_id'=>$id,'is_deleted'=>0))->save()){
						$this->ajaxReturn('删除成功','删除成功',1);
					}else{
						$this->ajaxReturn('删除失败','删除失败',2);
					}
				}else{
					$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
				}
			}
		}
	}
	//选择联系人列表时调用
	public function contacts_list(){
		if($this->isPost()){
			$customer_id = intval($_POST['id']);
			if(!$customer_id){
				$this->ajaxReturn('参数错误！','参数错误！',2);
			}
			$where = array();
			$where['is_deleted'] = 0;
			$contacts_ids = M('rContactsCustomer')->where('customer_id = %d', $customer_id)->getField('contacts_id', true);
			$where['contacts_id'] = array('in',implode(',', $contacts_ids));
			$contacts_list = M('Contacts')->where($where)->field('contacts_id,name')->select();
			if($contacts_list){
				$data['list'] = $contacts_list;
			}else{
				$data['list'] = array();
			}
			$this->ajaxReturn($data,'success',1);
		}
	}
}