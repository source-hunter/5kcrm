<?php 

class AppAuthenticateBehavior extends Behavior {
	protected $options = array();
	
	public function run(&$params) {
		$m = MODULE_NAME;
		$a = ACTION_NAME;
		if($a == 'dynamic' || $a == 'viewnew'){
			$a = 'view';
		}elseif($a == 'addnew'){
			$a = 'add';
		}elseif($a == 'editnew'){
			$a = 'edit';
		}
		$allow = $params['allow'];
		$permission = $params['permission'];		
		
		if(session('?user_id')){
			M('user')->where('user_id = %d',session('user_id'))->setField(array('token_time'=>time()));
		}elseif(!session('?role_id') && $m != 'User' && $a != 'login'){
			echo json_encode(array('data'=>'请先登录!','info'=>'请先登录!','status'=>-1));die();
		}
		
		if (session('?admin')) {
			return true;
		}
		if (in_array($a, $permission)) {
			return true;
		} elseif (session('?position_id') && session('?role_id')) {
			if (in_array($a, $allow)) {
				return true;
			} else {
				if(!checkPerByAction($m, $a)){
					if(strtolower($m) == 'customer' || strtolower($m) == 'business' || strtolower($m) == 'leads'){
						if(ACTION_NAME == 'index' || ACTION_NAME == 'dynamic' || ACTION_NAME == 'viewnew' || ACTION_NAME == 'addnew' || ACTION_NAME == 'editnew'){
							Global $roles;
							$roles = 2;
						}else{
							Global $role;
							$role = 1;
						}
					}else{
						//echo json_encode(array('status'=>-2,'info'=>'您没有此权利!'));die();
						Global $roles;
						$roles = 2;
					}
				}else{
					return true;
				}
			}
		} else {
			if($_GET['token']){				
				$user = M('User')->where('token = "%s"',trim($_GET['token']))->find();
				if($user){
					$role = D('RoleView')->where('user.user_id = %d', $user['user_id'])->find();					
					if((time() - $user['token_time']) < (60*60*24*3)){
							if($user['category_id'] == 1){
								session('admin', 1);
							}	
							session('role_id', $role['role_id']);
							session('position_id', $role['position_id']);
							session('role_name', $role['role_name']);
							session('department_id', $role['department_id']);
							session('name', $user['name']);
							session('user_id', $user['user_id']);
							//userLog($user['user_id']);
							$data['info'] = 'success';
							$data['status'] = 1;
							$data['img'] = empty($user['img']) ? '' : $user['img'];
							$data['session_id'] = session_id();							
							/*if(session('?admin')){
								$data['admin'] = 1;
							}else{
								$data['admin'] = 0;
								$data['permission'] = $this->permission();
							} 	*/					
							echo json_encode($data);die();
					}
				}else{
					json_encode(array('status'=>-3,'info'=>'登录失效'));die();
				}
			}
			echo json_encode(array('status'=>-1,'info'=>'请先登录!'));die();
		}
	}
}
