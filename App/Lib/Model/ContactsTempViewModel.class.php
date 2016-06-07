<?php 
	class ContactsTempViewModel extends ViewModel {
	   public $viewFields = array(
		'contacts'=>array('contacts_id','creator_role_id','name','post','department','sex','saltname','telephone','email','qq_no','address','zip_code','description','create_time','update_time','is_deleted','delete_role_id','delete_time','_type'=>'LEFT'),
		'RContactsCustomer'=>array('_on'=>'contacts.contacts_id=RContactsCustomer.contacts_id','_type'=>'LEFT'),
		'customer'=>array('customer_id','owner_role_id','name'=>'customer_name','_on'=>'customer.customer_id=RContactsCustomer.customer_id')
	   );
/* 	   public function _initialize(){
			$this->viewFields = array(  'contacts'=>array('*'),
						'contacts_data'=>array('*', '_on'=>'contacts.contacts_id = contacts_data.contacts_id','_type'=>'LEFT'),
						'RContactsCustomer'=>array('customer_id','_on'=>'RContactsCustomer.contacts_id=contacts.contacts_id','_type'=>'LEFT'),
						'Customer'=>array('name'=>'customer_name', '_on'=>'RContactsCustomer.customer_id=Customer.customer_id')
				);
	   } */

	} 