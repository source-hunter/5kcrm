<?php
	class WorkorderViewModel extends ViewModel{
		public $viewFields = array(
			'workorder'=>array('*', '_type'=>'LEFT'),
			'customer'=>array('name'=>'customer_name', '_on'=>'workorder.module_id=customer.customer_id' )
		);
	}