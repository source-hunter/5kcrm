<?php 
class LogViewModel extends ViewModel{
	public $viewFields = array(
		'Log'=>array('log_id', 'role_id', 'category_id','create_date','subject', 'content', '_type'=>'LEFT'),
		'role'=>array('_on'=>'Log.role_id=role.role_id', '_type'=>'LEFT'),
		'user'=>array('name'=>'user_name', 'img', '_on'=>'user.user_id=role.user_id',  '_type'=>'LEFT'),
		'position'=>array('name'=>'role_name', '_on'=>'position.position_id=role.position_id', '_type'=>'LEFT'),
		'role_department'=>array('name'=>'department_name', '_on'=>'role_department.department_id=position.department_id')
	);
}