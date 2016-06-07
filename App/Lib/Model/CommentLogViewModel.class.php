<?php 
class CommentLogViewModel extends ViewModel{
	public $viewFields = array(
		'comment'=>array('content','module_id','update_time','creator_role_id','to_role_id'),
		'log'=>array('subject', 'update_date', 'role_id', 'log_id','_on'=>'comment.module_id=log.log_id', '_type'=>'LEFT')
	);
}