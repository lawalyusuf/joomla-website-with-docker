<?php
/**
 *
 * Enter address data for the cart, when anonymous users checkout
 *
 * @package	VirtueMart
 * @subpackage User
 * @author Oscar van Eijk, Max Milbers
 * @link http://www.virtuemart.net
 * @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: edit_address.php 5404 2012-02-09 11:09:12Z alatak $
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
// vmdebug('user edit address',$this->userFields['fields']);
// Implement Joomla's form validation
JHTML::_('behavior.formvalidation');
JHTML::stylesheet('vmpanels.css', JURI::root() . 'components/com_virtuemart/assets/css/');

if ($this->fTask === 'savecartuser') {
    $rtask = 'registercartuser';
} else {
    $rtask = 'registercheckoutuser';
}
?>
<div class="cart-view">

<h3><?php echo $this->page_title ?></h3>
<div class="billing-box">
<?php
echo shopFunctionsF::getLoginForm(false);
?>
<script language="javascript">
    function myValidator(f, t)
    {
        f.task.value=t; //I understand this as method to set the task of the form on the fTask. This is not longer needed, because we use another js method for the cancel button than before.
        if (document.formvalidator.isValid(f)) {
            f.submit();
            return true;
        } else {
            var msg = '<?php echo addslashes(JText::_('COM_VIRTUEMART_USER_FORM_MISSING_REQUIRED_JS')); ?>';
            alert (msg+' ');
        }
        return false;
    }

    function callValidatorForRegister(f){

        var elem = jQuery('#username_field');
        elem.attr('class', "required");

        var elem = jQuery('#name_field');
        elem.attr('class', "required");

        var elem = jQuery('#password_field');
        elem.attr('class', "required");

        var elem = jQuery('#password2_field');
        elem.attr('class', "required");

        var elem = jQuery('#userForm');

	return myValidator(f, '<?php echo $rtask ?>');

    }


</script>

<fieldset>

    <form method="post" id="adminForm" name="userForm" class="form-validate">
    <!--<form method="post" id="userForm" name="userForm" action="<?php echo JRoute::_('index.php'); ?>" class="form-validate">-->
	<div class="wrapper border-top">
	 <h2><?php
		if ($this->address_type == 'BT') {
			echo JText::_('COM_VIRTUEMART_USER_FORM_EDIT_BILLTO_LBL');
		} else {
			echo JText::_('COM_VIRTUEMART_USER_FORM_ADD_SHIPTO_LBL');
		}
		?>
    </h2>
</div>

	    <?php
	    if (!class_exists('VirtueMartCart'))
		require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
//             $cart = VirtueMartCart::getCart();
//             $cart->prepareAddressDataInCart();
//             $this->assignRef('cart', $cart);
// 				vmdebug('my cart in edit_adress',$cart->BTaddress);

	    if (count($this->userFields['functions']) > 0) {
		echo '<script language="javascript">' . "\n";
		echo join("\n", $this->userFields['functions']);
		echo '</script>' . "\n";
	    }
	     echo $this->loadTemplate('userfields');

	  ?>
	  
<div class="wrapper">	  
	    <?php
	    if (strpos($this->fTask, 'cart') || strpos($this->fTask, 'checkout')) {
		$rview = 'cart';
	    } else {
		$rview = 'user';
	    }
// echo 'rview = '.$rview;

	    if (strpos($this->fTask, 'checkout') || $this->address_type == 'ST') {
		$buttonclass = 'button';
	    } else {
		$buttonclass = 'button';
	    }


	    if (VmConfig::get('oncheckout_show_register', 1) && $this->userId == 0 && !VmConfig::get('oncheckout_only_registered', 0) && $this->address_type == 'BT' and $rview == 'cart') {
		echo '<div class="pad-top">'.JText::sprintf('COM_VIRTUEMART_ONCHECKOUT_DEFAULT_TEXT_REGISTER', JText::_('COM_VIRTUEMART_REGISTER_AND_CHECKOUT'), JText::_('COM_VIRTUEMART_CHECKOUT_AS_GUEST')).'</div>';
	    } else {
		//echo JText::_('COM_VIRTUEMART_REGISTER_ACCOUNT');
	    }
	    if (VmConfig::get('oncheckout_show_register', 1) && $this->userId == 0 && $this->address_type == 'BT' and $rview == 'cart') {
		?>
        
<div class="control-button floatleft">
    	    <button class="<?php echo $buttonclass ?> register" type="submit" onclick="javascript:return callValidatorForRegister(userForm);" title="<?php echo JText::_('COM_VIRTUEMART_REGISTER_AND_CHECKOUT'); ?>"><?php echo JText::_('COM_VIRTUEMART_REGISTER_AND_CHECKOUT'); ?></button>
			
    <?php if (!VmConfig::get('oncheckout_only_registered', 0)) { ?>
		    <button class="<?php echo $buttonclass ?> guest" title="<?php echo JText::_('COM_VIRTUEMART_CHECKOUT_AS_GUEST'); ?>" type="submit" onclick="javascript:return myValidator(userForm, '<?php echo $this->fTask; ?>');" ><?php echo JText::_('COM_VIRTUEMART_CHECKOUT_AS_GUEST'); ?></button>
		<?php } ?>
			</div>

<?php } else { ?>

<?php } ?>
<div class="control-buttons width30 floatleft">
	 	 <?php
	    if (strpos($this->fTask, 'cart') || strpos($this->fTask, 'checkout')) {
		$rview = 'cart';
	    } else {
		$rview = 'user';
	    }
// echo 'rview = '.$rview;

	    if (strpos($this->fTask, 'checkout') || $this->address_type == 'ST') {
		$buttonclass = 'button';
	    } else {
		$buttonclass = 'button';
	    }


	    if (VmConfig::get('oncheckout_show_register', 1) && $this->userId == 0 && !VmConfig::get('oncheckout_only_registered', 0) && $this->address_type == 'BT' and $rview == 'cart') {
		
	    } else {
		//echo JText::_('COM_VIRTUEMART_REGISTER_ACCOUNT');
	    }
	    if (VmConfig::get('oncheckout_show_register', 1) && $this->userId == 0 && $this->address_type == 'BT' and $rview == 'cart') {
		?>

    	    
			
    <?php if (!VmConfig::get('oncheckout_only_registered', 0)) { ?>
		    
		<?php } ?>
    	    <button class="button" type="reset" onclick="window.location.href='<?php echo JRoute::_('index.php?option=com_virtuemart&view=' . $rview); ?>'" ><?php echo JText::_('COM_VIRTUEMART_CANCEL'); ?></button>


<?php } else { ?>

    	    <button class="<?php echo $buttonclass ?>" type="submit" onclick="javascript:return myValidator(userForm, '<?php echo $this->fTask; ?>');" ><?php echo JText::_('COM_VIRTUEMART_SAVE'); ?></button>
    	    <button class="button" type="reset" onclick="window.location.href='<?php echo JRoute::_('index.php?option=com_virtuemart&view=' . $rview); ?>'" ><?php echo JText::_('COM_VIRTUEMART_CANCEL'); ?></button>

<?php } ?>
	 </div>
     </div>


<input type="hidden" name="option" value="com_virtuemart"/>
<input type="hidden" name="view" value="user"/>
<input type="hidden" name="controller" value="user"/>
<input type="hidden" name="task" value="<?php echo $this->fTask; // I remember, we removed that, but why?   ?>"/>
<input type="hidden" name="layout" value="<?php echo $this->getLayout (); ?>"/>
<input type="hidden" name="address_type" value="<?php echo $this->address_type; ?>"/>
<?php if (!empty($this->virtuemart_userinfo_id)) {
	echo '<input type="hidden" name="shipto_virtuemart_userinfo_id" value="' . (int)$this->virtuemart_userinfo_id . '" />';
}
echo JHTML::_ ('form.token');
?>
</fieldset>
</div>
</div>