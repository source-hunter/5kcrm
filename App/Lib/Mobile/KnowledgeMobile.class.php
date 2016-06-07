<?php

class KnowledgeMobile extends Action{
	/**
	 *	permission 未登录可访问
	 * 	allow 登录访问
	 **/
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('knowledge_info')
		);
		B('AppAuthenticate', $action);
		$this->_permissionRes = getPerByAction(MODULE_NAME,ACTION_NAME);
		Global $roles;
		$this->roles = $roles;
	}

	//知识列表
	public function index(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			$m_knowledge = M('Knowledge');
			$category = M('knowledgeCategory');
			$p = isset($_POST['p'])?$_POST['p']:1;
			$where = array();
			if($this->_permissionRes) $where['role_id'] = array('in', $this->_permissionRes);
			if(isset($_POST['search'])){
				$where['title'] = array('like','%'.trim($_POST['search']).'%');
			}
			if($_GET['category_id']){
				$idArray = Array();
				$categoryList = getSubCategory($_GET['category_id'],$category->select(),'');
				foreach ($categoryList as $value) {
					$idArray[] = $value['category_id'];
				}
				$idList  =empty($idArray) ? $_GET['category_id'] : $_GET['category_id'] . ',' . implode(',', $idArray);
				$where['category_id'] = array('in',$idList);
			}
			$count = $m_knowledge->where($where)->count();
			$list = $m_knowledge->where($where)->order('create_time desc')->field('title,knowledge_id,update_time,category_id,role_id,hits')->Page($p.',10')->select();
			foreach($list as $k=>$v){
				$list[$k]['role_name'] = M('User')->where(array('role_id'=>$v['role_id'],'status'=>1))->getField('name');
				$owner_role_id = $v['role_id'];
				//获取操作权限
				$list[$k]['permission'] = permissionlist(MODULE_NAME,$owner_role_id);
			}
			$list = empty($list) ? array() : $list;

			$page = ceil($count/10);
			$category_list = $category->where('parent_id = 0')->field('name,category_id')->select();

			$category_list = empty($category_list) ? array() : $category_list ;
			$data['category_list'] = $category_list;
			$data['list'] = $list;
			$data['page'] = $page;
			$data['info'] = 'success';
			$data['status'] = 1;
			$this->ajaxReturn($data,'JSON');
		}
	}

	//知识详情
	public function view(){
		if($this->roles == 2){
			$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
		}
		if($this->isPost()){
			if($_GET['id']){
				$m_knowledge = M('Knowledge');
				$knowledge = M('Knowledge')->where('knowledge_id = %d',intval($_GET['id']))->find();
				$m_knowledge->where('knowledge_id=%d',intval($_GET['id']))->setInc('hits');
				if($this->_permissionRes && !in_array($knowledge['role_id'], $this->_permissionRes)){
					$this->ajaxReturn('您没有此权利!','您没有此权利!',-2);
				}
				if($knowledge){
					$knowledge_id = intval($_GET['id']);
					$knowledge['name'] = M('User')->where('role_id = %d',$knowledge['role_id'])->getField('name');
					$knowledge['content_link'] = 'm=knowledge&a=knowledge_info&id='.$knowledge_id;
					$this->ajaxReturn($knowledge,'success',1);
				}else{
					$this->ajaxReturn('数据不存在或已删除！','数据不存在或已删除！',2);
				}
			}else{
				$this->ajaxReturn('参数错误！','参数错误！',2);
			}
		}
	}
	//知识详情网页
	public function knowledge_info(){
		$knowledge_id = $_REQUEST['id'];
		$m_knowledge = M('Knowledge');
		$knowledge_info = $m_knowledge->where('knowledge_id = %d',$knowledge_id)->find();
		$this->assign('knowledge_info',$knowledge_info);
		$this->display();
	}
}