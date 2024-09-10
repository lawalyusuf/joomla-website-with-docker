<?php
/**
 *
 * Show the product details page
 *
 * @package	VirtueMart
 * @subpackage
 * @author Max Milbers, Valerie Isaksen

 * @link http://www.virtuemart.net
 * @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: default_images.php 5406 2012-02-09 12:22:33Z alatak $
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

?>
<?php
// Showing The Additional Images
// if(!empty($this->product->images) && count($this->product->images)>1) {
if (!empty($this->product->images)) {
    ?>
	<?php 
	if (count($this->product->images) <= 2) { 
	$css_class='none';
	} else {
	$css_class='';	
		}
	?>
<div id="products_example">	
<div id="products" class="<?php echo $css_class; ?>">
	<div class="slides_container">
	<?php
	// List all Images
	if (count($this->product->images) > 0) {
	    foreach ($this->product->images as $image) {
		echo '<div class="slide">' . $image->displayMediaThumb('class="productimage"', true,'class="jqzoom modal"', true, true) . '</div>'; //'class="modal"'
	    }
	}
	?>
	</div>
	<?php 
	if (count($this->product->images) >= 2) { ?>
			<ul id="carousel" class="pagination jcarousel-skin-tango">
				<?php
			// List all Images
			if (count($this->product->images) > 0) {
				foreach ($this->product->images as $image) {
				echo '<li>' . $image->displayMediaThumb('class="productimage"', true, true, true) . '</li>'; //'class="modal"'
				}
			}
			?>
			</ul>
	<?php }	else { ?>
		<ul class="pagination2">
				<?php
			// List all Images
			if (count($this->product->images) > 0) {
				foreach ($this->product->images as $image) {
				echo '<li>' . $image->displayMediaThumb('class="productimage2"', false, true, true) . '</li>'; //'class="modal"'
				}
			}
			?>
			</ul>
	<?php } ?>	
</div>	
</div>	
<?php
} // Showing The Additional Images END ?>