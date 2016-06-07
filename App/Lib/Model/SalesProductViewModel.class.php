<?php 
	class SalesProductViewModel extends ViewModel{
		public $viewFields = array(
			'sales_product'=>array('product_id'=>'product_id','amount'=>'amount','unit_price'=>'unit_price','discount_rate'=>'discount_rate','_type'=>'LEFT'),
			'sales'=>array('type','is_checked','sales_time','creator_role_id','_on'=>'sales_product.sales_id=sales.sales_id')
		);
	}