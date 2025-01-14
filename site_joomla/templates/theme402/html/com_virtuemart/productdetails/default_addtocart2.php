<?php
/**
 *
 * Show the product details page
 *
 * @package    VirtueMart
 * @subpackage
 * @author Max Milbers, Valerie Isaksen
 * @todo handle child products
 * @link http://www.virtuemart.net
 * @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: default_addtocart.php 6433 2012-09-12 15:08:50Z openglobal $
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
if (isset($this->product->step_order_level))
	$step=$this->product->step_order_level;
else
	$step=1;
if($step==0)
	$step=1;
$alert=JText::sprintf ('COM_VIRTUEMART_WRONG_AMOUNT_ADDED', $step);

		if ((!VmConfig::get('use_as_catalog', 0) and !empty($this->product->prices['salesPrice'])) && !$this->product->images[0]->file_is_downloadable) { ?>
		<div class="addtocart-area">

	<form method="post" class="product js-recalculate" action="<?php echo JRoute::_ ('index.php'); ?>">
                <input name="quantity" type="hidden" value="<?php echo $step ?>" />
		<?php // Product custom_fields
		if (!empty($this->product->customfieldsCart)) {
			?>
			<div class="product-fields">
				<?php foreach ($this->product->customfieldsCart as $field) { ?>
				<div class="product-field product-field-type-<?php echo $field->field_type ?>">
					<span class="product-fields-title-wrapper"><span class="product-fields-title"><strong><?php echo JText::_ ($field->custom_title) ?></strong></span>
					<?php if ($field->custom_tip) {
					echo JHTML::tooltip ($field->custom_tip, JText::_ ($field->custom_title), 'tooltip.png');
				} ?></span>
					<span class="product-field-display"><?php echo $field->display ?></span>

					<span class="product-field-desc"><?php echo $field->custom_field_desc ?></span>
				</div>
				<?php
			}
				?>
			</div>
			<?php
		}
		/* Product custom Childs
			 * to display a simple link use $field->virtuemart_product_id as link to child product_id
			 * custom_value is relation value to child
			 */

		if (!empty($this->product->customsChilds)) {
			?>
			<div class="product-fields">
				<?php foreach ($this->product->customsChilds as $field) { ?>
				<div class="product-field product-field-type-<?php echo $field->field->field_type ?>">
					<span class="product-fields-title"><strong><?php echo JText::_ ($field->field->custom_title) ?></strong></span>
					<span class="product-field-desc"><?php echo JText::_ ($field->field->custom_value) ?></span>
					<span class="product-field-display"><?php echo $field->display ?></span>

				</div>
				<?php } ?>
			</div>
			<?php } ?>


		<div class="addtocart-bar2">

<script type="text/javascript">
		function check(obj) {
 		// use the modulus operator '%' to see if there is a remainder
		remainder=obj.value % <?php echo $step?>;
		quantity=obj.value;
 		if (remainder  != 0) {
 			alert('<?php echo $alert?>!');
 			obj.value = quantity-remainder;
 			return false;
 			}
 		return true;
 		}
</script> 

			<?php // Display the quantity box

			$stockhandle = VmConfig::get ('stockhandle', 'none');
			if (
				($stockhandle == 'disableit' or $stockhandle == 'disableadd') and (($this->product->product_in_stock - $this->product->product_ordered) < 1) || 
			(
			 ($this->product->product_in_stock - $this->product->product_ordered) < $this->product->min_order_level ))  {
				?>
				 <div class="wrapper">

				<span class="addtocart-button2">
				<a href="<?php echo JRoute::_ ('index.php?option=com_virtuemart&view=productdetails&layout=notify&virtuemart_product_id=' . $this->product->virtuemart_product_id); ?>" class="addtocart-button notify"><?php echo JText::_ ('COM_VIRTUEMART_CART_NOTIFY') ?></a>
				</span>
				<div class="fright">
					
					<?php
					// Ask a question about this product
						if (VmConfig::get('ask_question', 1) == '1') { ?>
						   <div class="ask-a-question">
								<a class="ask-a-question" href="<?php echo $url ?>" ><?php echo JText::_('COM_VIRTUEMART_PRODUCT_ENQUIRY_LBL') ?></a>
								<!--<a class="ask-a-question modal" rel="{handler: 'iframe', size: {x: 800, y: 750}}" href="<?php echo $url ?>"><?php echo JText::_('COM_VIRTUEMART_PRODUCT_ENQUIRY_LBL') ?></a>-->
								</div>
						<?php } ?>
						<?php
					// Manufacturer of the Product
					if (!empty($this->product->virtuemart_manufacturer_id)) {
					  echo  $this->loadTemplate('manufacturer');
					}
					?>
					</div>
				</div>	
				<?php } else { ?>
				<div class="wrapper">
			<div class="price">
					<?php
							echo $this->loadTemplate('showprices');
					?>

				</div>
			</div>
				<?php // Display the quantity box END ?>

				<?php
				// Display the add to cart button
				?>
				<div class="controls">					
						<span class="quantity-box">
						<input type="text" class="quantity-input js-recalculate" name="quantity[]" onblur="check(this);" value="<?php if (isset($this->product->step_order_level) && (int)$this->product->step_order_level > 0) {
							echo $this->product->step_order_level;
						} else if(!empty($this->product->min_order_level)){
							echo $this->product->min_order_level;
						}else {
							echo '1';
						} ?>"/>
	  				  </span>
				<span class="quantity-controls js-recalculate">
					<input type="button" class="quantity-controls quantity-plus"/>
					<input type="button" class="quantity-controls quantity-minus"/>
				</span>
			</div>
				<br/>
			<div class="wrapper">
			<div class="fright">
					
					<?php
					// Ask a question about this product
						if (VmConfig::get('ask_question', 1) == '1') { ?>
						   <div class="ask-a-question">
								<a class="ask-a-question" href="<?php echo $url ?>" ><?php echo JText::_('COM_VIRTUEMART_PRODUCT_ENQUIRY_LBL') ?></a>
								<!--<a class="ask-a-question modal" rel="{handler: 'iframe', size: {x: 800, y: 750}}" href="<?php echo $url ?>"><?php echo JText::_('COM_VIRTUEMART_PRODUCT_ENQUIRY_LBL') ?></a>-->
								</div>
						<?php } ?>
						<?php
					// Manufacturer of the Product
					if (!empty($this->product->virtuemart_manufacturer_id)) {
					  echo  $this->loadTemplate('manufacturer');
					}
					?>
					</div>
			<span class="addtocart-button">
				<i>&gt;</i>
			<input type="submit" name="addtocart"  class="addtocart-button cart-click2" value="<?php echo JText::_('COM_VIRTUEMART_CART_ADD_TO') ?>" title="<?php echo JText::_('COM_VIRTUEMART_CART_ADD_TO') ?>" />
			</span>
		</div>
				<?php } ?>

			<div class="clear"></div>
		</div>
		
		<input type="hidden" class="pname" value="<?php echo htmlentities($this->product->product_name, ENT_QUOTES, 'utf-8') ?>"/>
		<input type="hidden" name="option" value="com_virtuemart"/>
		<input type="hidden" name="view" value="cart"/>
		<noscript><input type="hidden" name="task" value="add"/></noscript>
		<input type="hidden" name="virtuemart_product_id[]" value="<?php echo $this->product->virtuemart_product_id ?>"/>
	</form>

	<div class="clear"></div>
</div>
		<?php
	} else { ?> <a class="ask-a-question bold" href="<?php echo $this->askquestion_url ?>"><?php echo JText::_ ('COM_VIRTUEMART_PRODUCT_ASKPRICE') ?></a>
<?php
	}?>

