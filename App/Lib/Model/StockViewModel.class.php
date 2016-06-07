<?php 
	class StockViewModel extends ViewModel{
		public $viewFields = array(
			'stock'=>array('stock_id','product_id','warehouse_id','amounts','last_change_time', '_type'=>'LEFT'),
			'warehouse'=>array('name'=>'warehouse_name','_on'=>'stock.warehouse_id = warehouse.warehouse_id','_type'=>'LEFT'),
			'product'=>array('name'=>'product_name','_on'=>'product.product_id = stock.product_id')
		);
	}