<?php
	class WorkorderStatusViewModel extends ViewModel{
		public $viewFields = array(
			'workorderStatus'=>array('*', '_type'=>'LEFT'),
			'user'=>array('name'=>'user_name', '_on'=>'workorderStatus.role_id=user.role_id' )
		);
	}