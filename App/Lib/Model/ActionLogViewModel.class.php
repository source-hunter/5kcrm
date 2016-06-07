<?php 
	class ActionLogViewModel extends ViewModel{
		public $viewFields = array(
			'action_log'=>array('log_id','role_id','module_name','action_name','param_name','action_id','content','create_time','_type'=>'LEFT'),
			'business'=>array('is_deleted'=>'b_deleted','business_id','_on'=>'action_log.action_id=business.business_id and action_log.module_name="business"', '_type'=>'LEFT'),
			'customer'=>array('is_deleted'=>'c_deleted','customer_id','_on'=>'action_log.action_id=customer.customer_id and action_log.module_name="customer"','_type'=>'LEFT'),
			'sign'=>array('sign_id','customer_id'=>'sign_customer_id','x','y','title','address','log','_on'=>'action_log.action_id=sign.sign_id and action_log.module_name="sign"'),
		);
	}