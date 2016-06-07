<?php 
	class PurchaseProductViewModel extends ViewModel{
		public $viewFields = array(
			'PurchaseProduct'=>array('product_id','amount','unit_price','discount_rate','tax_rate','_type'=>'LEFT'),
			'Purchase'=>array('type','is_checked','purchase_time','creator_role_id','_on'=>'PurchaseProduct.purchase_id=Purchase.purchase_id')
		);
	}