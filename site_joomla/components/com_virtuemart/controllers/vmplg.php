<?php

/**
 *
 * Controller for the Plugins Response
 *
 * @package	VirtueMart
 * @subpackage pluginResponse
 * @author Valérie Isaksen
 * @link https://virtuemart.net
 * @copyright Copyright (c) 2004 - 2014 VirtueMart Team and authors. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: cart.php 3388 2011-05-27 13:50:18Z alatak $
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Load the controller framework
jimport('joomla.application.component.controller');

/**
 * Controller for the plugin response view
 *
 * @package VirtueMart
 * @subpackage pluginresponse
 * @author Valérie Isaksen
 *
 */
class VirtueMartControllerVmplg extends JControllerLegacy {

    /**
     * Construct the cart
     *
     * @access public
     */
    public function __construct() {
		parent::__construct();
    }

    /**
     * ResponseReceived()
     * From the plugin page, the user returns to the shop. The order email is sent, and the cart emptied.
     *
     * @author Valerie Isaksen
     *
     */
    function pluginResponseReceived() {

	$this->PaymentResponseReceived();
	$this->ShipmentResponseReceived();
    }

    /**
     * ResponseReceived()
     * From the payment page, the user returns to the shop. The order email is sent, and the cart emptied.
     *
     */
    function PaymentResponseReceived() {

	JPluginHelper::importPlugin('vmpayment');

	$return_context = "";
	$html = "";
	$paymentResponse = vmText::_('COM_VIRTUEMART_CART_THANKYOU');
	$returnValues = vDispatcher::trigger('plgVmOnPaymentResponseReceived', array( 'html' => &$html,&$paymentResponse));

	$view = $this->getView('vmplg', 'html');
	$layoutName = vRequest::getVar('layout', 'default');
	$view->setLayout($layoutName);

	$view->assignRef('paymentResponse', $paymentResponse);
	$view->assignRef('paymentResponseHtml', $html);

	// Display it all
	$view->display();
    }

	/**
	 *
	 */
    function ShipmentResponseReceived() {
		// TODO: not ready yet

	    JPluginHelper::importPlugin('vmshipment');

	    $return_context = "";

	    $html = "";
	    $shipmentResponse = vmText::_('COM_VIRTUEMART_CART_THANKYOU');
	    vDispatcher::trigger('plgVmOnShipmentResponseReceived', array( 'html' => &$html,&$shipmentResponse));

    }

    /**
     * PaymentUserCancel()
     * From the payment page, the user has cancelled the order. The order previousy created is deleted.
     * The cart is not emptied, so the user can reorder if necessary.
     * then delete the order
     *
     */
    function pluginUserPaymentCancel() {

    $cart = VirtueMartCart::getCart ();
	$cart->prepareCartData();
    if (!empty($cart->cartData['couponCode'])) {
	    CouponHelper::setInUseCoupon($cart->cartData['couponCode'], false);
    }

	JPluginHelper::importPlugin('vmpayment');
    vDispatcher::trigger('plgVmOnUserPaymentCancel', array());

	//Todo this could be useful, prevent errors and spares one sql later, but for a mayor version
	/*
	$cart->virtuemart_order_id = false;
	$cart->setCartIntoSession();*/

	// return to cart view
	$view = $this->getView('cart', 'html');
	$layoutName = vRequest::getCmd('layout', 'default');
	$view->setLayout($layoutName);

	// Display it all
	$view->display();
    }

    /**
     * Attention this is the function which processs the response of the payment plugin
     *
     * @return success of update
     */
    function pluginNotification() {


		vDispatcher::importVMPlugins('vmpayment');
	    $html = "";
	    $returnValues = vDispatcher::trigger('plgVmOnPaymentNotification', array( 'html' => &$html));

	    $view = $this->getView('vmplg', 'html');
	    $layoutName = vRequest::getVar('layout', 'blank');
	    $view->setLayout($layoutName);

	    $view->html = $html;

	    // Display it all
	    $view->display();

    }


	/**
	 * Alias for task=pluginNotification
	 *
	 * @return success of update
	 */
	function notify () {

		$this->pluginNotification();

	}
}

//pure php no Tag
