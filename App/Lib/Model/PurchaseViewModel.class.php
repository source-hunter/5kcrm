<?php 
	class PurchaseViewModel extends ViewModel{
		public $viewFields = array(
			'purchase'=>array('purchase_id','supplier_id','creator_role_id','sn_code','subject','prime_price','purchase_price','total_amount','type','status','is_checked','discount_price','description','create_time','purchase_time','_type'=>'LEFT'),
			'supplier'=>array('name'=>'supplier_name','_on'=>'supplier.supplier_id=purchase.supplier_id','_type'=>'LEFT'),
		);
	}