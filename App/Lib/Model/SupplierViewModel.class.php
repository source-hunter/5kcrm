<?php 
	class SupplierViewModel extends ViewModel{
		public $viewFields = array(
			'supplier'=>array('supplier_id','contact_id','creator_role_id','name','category_id','description','create_time','update_time','is_deleted','delete_role_id','delete_time','_type'=>'LEFT'),
			'supplier_category'=>array('name'=>'category_name','_on'=>'supplier.category_id=supplier_category.category_id','_type'=>'LEFT'),
			'supplier_contact'=>array('contact_name','telphone','address','_on'=>'supplier.contact_id = supplier_contact.contact_id','_type'=>'LEFT'),
			'user'=>array('name'=>'creator_role_name', '_on'=>'user.role_id=supplier.creator_role_id'),
		);
	}