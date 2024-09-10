<?php defined('_JEXEC') or die('Restricted access');

/** @var TYPE_NAME $viewData */
$related = $viewData['related'];
$customfield = $viewData['customfield'];
$thumb = $viewData['thumb'];

?>
<div class="product-container">
<div class="vm-product-media-container"><?php 
echo JHtml::link (JRoute::_ ($related->link), $thumb   . $related->product_name, array('title' => $related->product_name,'target'=>'_blank'));
?></div><?php
if($customfield->wPrice){
	?> <div class="product-price" id="productPrice<?php echo $related->virtuemart_product_id ?>"> <?php
	$currency = calculationHelper::getInstance()->_currencyDisplay;
	echo shopFunctionsF::renderVmSubLayout('prices',array('product'=>$related,'currency'=>$currency));
	//echo $currency->createPriceDiv ('salesPrice', 'COM_VIRTUEMART_PRODUCT_SALESPRICE', $related->prices);
	?></div><div class="clear"></div><?php
}

if($customfield->waddtocart){
	?><div class="vm3pr-related" ><?php
	echo shopFunctionsF::renderVmSubLayout('addtocart',array('product'=>$related, 'position' => array('ontop', 'addtocart')));
	?></div><?php
}

if($customfield->wDescr){
	echo '<p class="product_s_desc">'.$related->product_s_desc.'</p>';
}
	?></div>