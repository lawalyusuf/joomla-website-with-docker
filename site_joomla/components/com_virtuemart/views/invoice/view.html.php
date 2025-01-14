<?php
/**
 *
 * Handle the orders view
 *
 * @package	VirtueMart
 * @subpackage Orders
 * @author Oscar van Eijk
 * @link https://virtuemart.net
 * @copyright Copyright (c) 2004 - 2021 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: view.html.php 5432 2012-02-14 02:20:35Z Milbo $
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

/**
 * Handle the orders view
 */
class VirtuemartViewInvoice extends VmView {

	var $format = 'html';
	var $doVendor = false;
	var $uselayout	= '';
	var $orderDetails = 0;
	var $invoiceNumber =0;
	var $doctype = 'invoice';
	var $showHeaderFooter = true;

	public function display($tpl = null)
	{

		$document = JFactory::getDocument();

		$orderModel = VmModel::getModel('orders');
		$orderDetails = 0;
		if(!empty($this->orderDetails)) $orderDetails = $this->orderDetails;

		if($orderDetails==0){

			shopFunctionsF::loadOrderLanguages(VmConfig::$jDefLangTag);
			$orderDetails = $orderModel ->getMyOrderDetails(0,false,false,true);
			if(!$orderDetails ){
				//echo vmText::_('COM_VIRTUEMART_CART_ORDER_NOTFOUND');
				vmdebug('COM_VIRTUEMART_CART_ORDER_NOTFOUND and $orderDetails ',$orderDetails);
				return;
			} else if(empty($orderDetails['details'])){
				echo vmText::_('COM_VIRTUEMART_CART_ORDER_DETAILS_NOTFOUND');
				return;
			}
		}

		if(empty($orderDetails['details'])){
			echo vmText::_('COM_VIRTUEMART_ORDER_NOTFOUND');
			return 0;
		}

		$this->assignRef('orderDetails', $orderDetails);

		if(VmConfig::get('invoiceInUserLang', false) and !empty($orderDetails['details']['BT']->order_language)){
			if($orderDetails['details']['BT']->order_language!=VmConfig::$vmlangTag){
				shopFunctionsF::loadOrderLanguages($orderDetails['details']['BT']->order_language);
				$orderDetails = $orderModel->getOrder($orderDetails['details']['BT']->virtuemart_order_id);
			}
		}


		/* It would be so nice to be able to load the override of the FE additionally from here
		 * joomlaWantsThisFolder\language\overrides\en-GB.override.ini
		 * $jlang =vmLanguage::getLanguage();
		$tag = $jlang->getTag();
		$jlang->load('override', 'language/overrides',$tag,true);*/

		//We never want that the cart is indexed
		$document->setMetaData('robots','NOINDEX, NOFOLLOW, NOARCHIVE, NOSNIPPET');

		if(empty($this->uselayout)){
			$layout = vRequest::getCmd('layout','mail');
		} else {
			$layout = $this->uselayout;
		}
		switch ($layout) {
			case 'invoice':
				$this->doctype = $layout;
				$title = vmText::_('COM_VIRTUEMART_INVOICE');
				break;
			case 'deliverynote':
				$this->doctype = $layout;
				$layout = 'invoice';
				$title = vmText::_('COM_VIRTUEMART_DELIVERYNOTE');
				break;
			case 'confirmation':
				$this->doctype = $layout;
				$layout = 'confirmation';
				$title = vmText::_('COM_VIRTUEMART_CONFIRMATION');
				break;
			case 'mail':
				if (VmConfig::get('order_mail_html')) {
					$layout = 'mail_html';
				} else {
					$layout = 'mail_raw';
				}
		}
		$this->setLayout($layout);

		$tmpl = vRequest::getCmd('tmpl');

		$this->print = false;
		if($tmpl and !$this->isPdf){
			$this->print = true;
		}

		$this->format = vRequest::getCmd('format','html');
		if($layout == 'invoice'){
			$document->setTitle( vmText::_('COM_VIRTUEMART_INVOICE') );
		}
		$order_print=false;

		if ($this->print and $this->format=='html') {
			$order_print=true;
		}

		$virtuemart_vendor_id = $orderDetails['details']['BT']->virtuemart_vendor_id;

		$vendorModel = VmModel::getModel('vendor');
		$vendor = $vendorModel->getVendor($virtuemart_vendor_id);
		$vendorModel->addImages($vendor);
		$vendor->vendorFields = $vendorModel->getVendorAddressFields($virtuemart_vendor_id);
		if (VmConfig::get ('enable_content_plugin', 0)) {

			shopFunctionsF::triggerContentPlugin($vendor, 'vendor','vendor_store_desc');
			shopFunctionsF::triggerContentPlugin($vendor, 'vendor','vendor_terms_of_service');
			shopFunctionsF::triggerContentPlugin($vendor, 'vendor','vendor_legal_info');
		}

		$this->assignRef('vendor', $vendor);



		$this->user_currency_id = $orderDetails['details']['BT']->user_currency_id;

		/*
		 * Deprecated trigger will be renamed or removed
		 */
		vDispatcher::importVMPlugins('vmpayment');
		vDispatcher::trigger('plgVmgetEmailCurrency',array( $orderDetails['details']['BT']->virtuemart_paymentmethod_id, $orderDetails['details']['BT']->virtuemart_order_id, &$this->user_currency_id));

		$this->currency = CurrencyDisplay::getInstance($this->user_currency_id,$virtuemart_vendor_id, $orderDetails['details']['BT']->user_currency_rate);

		if($vendor->vendor_currency!=$this->user_currency_id){
			$this->currencyV = CurrencyDisplay::getInstance($vendor->vendor_currency,$virtuemart_vendor_id);
		} else {
			$this->currencyV = $this->currency;
		}

		if($this->user_currency_id != $orderDetails['details']['BT']->payment_currency_id){
			$this->currencyP = CurrencyDisplay::getInstance($orderDetails['details']['BT']->payment_currency_id,$virtuemart_vendor_id);
			$this->currencyP->exchangeRateShopper = $orderDetails['details']['BT']->payment_currency_rate;
		} else {
			$this->currencyP = $this->currency;
		}

		$os_trigger_refunds = VmConfig::get('os_trigger_refunds', array('R'));
		$this->toRefund = array();
		$orderDetails['details']['BT']->order_total = $this->currency->roundByPriceConfig(floatval($orderDetails['details']['BT']->order_total));
		$orderDetails['details']['BT']->toPay = $orderDetails['details']['BT']->order_total;
		foreach($orderDetails['items'] as $i => $_item) {
			$orderDetails['items'][$i]->linkedit = 'index.php?option=com_virtuemart&view=product&task=edit&virtuemart_product_id='.$_item->virtuemart_product_id;


			if(in_array($_item->order_status,$os_trigger_refunds)){
				$this->toRefund[] = $_item;
				$orderDetails['details']['BT']->toPay -= $this->currency->roundByPriceConfig($_item->product_subtotal_with_tax);
			}

		}
		$orderDetails['details']['BT']->toPay = $this->currency->roundByPriceConfig(($orderDetails['details']['BT']->toPay));

		$rulesSorted = shopFunctionsF::summarizeRulesForBill($orderDetails);
		$this->discountsBill = $rulesSorted['discountsBill'];
		$this->taxBill = $rulesSorted['taxBill'];

		//Fallback for old layouts.
		foreach($this->taxBill as $i=> $rule) {
			$this->taxBill[$i]->calc_result = $rule->calc_amount;
		}

		if($orderDetails['details']['BT']->toPay != $orderDetails['details']['BT']->order_total){
			$document->setTitle( vmText::_('COM_VIRTUEMART_CREDIT_NOTE') );
		}

		//Create BT address fields
		$userFieldsModel = VmModel::getModel('userfields');
		$_userFields = $userFieldsModel->getUserFields(
				 'account'
				, array('captcha' => true, 'delimiters' => true) // Ignore these types
				, array('delimiter_userinfo','user_is_vendor' ,'username','password', 'password2', 'agreed', 'address_type') // Skips
		);

		$this->userfields = $userFieldsModel->getUserFieldsFilled( $_userFields, $orderDetails['details']['BT']);

		$civility="";

		$this->userfields['BT'] = array();
		foreach ($this->userfields['fields'] as  $field) {
			if ($field['name']=="title") {
				$civility=$field['value'];
				//break;
			}
			$this->userfields['BT'][$field['name']] = &$field;
		}

		//Create ST address fields
		$orderst = $orderDetails['details']['ST'];

		$skips = array('delimiter_userinfo', 'username', 'email', 'password', 'password2', 'agreed', 'address_type') ;
		if(empty($orderDetails['details']['has_ST'])){
			$skips[] = 'address_type_name';
		}

		$shipmentFieldset = $userFieldsModel->getUserFields(
				 'shipment'
				, array() // Default switches
				, $skips
		);

		$this->shipmentfields = $userFieldsModel->getUserFieldsFilled( $shipmentFieldset, $orderst );

		foreach ($this->shipmentfields['fields'] as  $field) {
			$this->userfields['ST'][$field['name']] = &$field;
		}


		$company= empty($orderDetails['details']['BT']->company) ?"":$orderDetails['details']['BT']->company.", ";
		$shopperName =  $company. $civility.' '.$orderDetails['details']['BT']->first_name.' '.$orderDetails['details']['BT']->last_name;
		$this->assignRef('shopperName', $shopperName);
		$this->assignRef('civility', $civility);

		// Create an array to allow orderlinestatuses to be translated
		// We'll probably want to put this somewhere in ShopFunctions..
		$orderStatusModel = VmModel::getModel('orderstatus');
		$_orderstatuses = $orderStatusModel->getOrderStatusList(true);
		$orderstatuses = array();
		foreach ($_orderstatuses as $_ordstat) {
			$orderstatuses[$_ordstat->order_status_code] = vmText::_($_ordstat->order_status_name);
		}
		$this->assignRef('orderstatuslist', $orderstatuses);
		$this->assignRef('orderstatuses', $orderstatuses);

		$_itemStatusUpdateFields = array();
		$_itemAttributesUpdateFields = array();

		$pM = VmModel::getModel('product');
		foreach($orderDetails['items'] as $k => $_item) {
// 			$_itemStatusUpdateFields[$_item->virtuemart_order_item_id] = JHtml::_('select.genericlist', $orderstatuses, "item_id[".$_item->virtuemart_order_item_id."][order_status]", 'class="selectItemStatusCode"', 'order_status_code', 'order_status_name', $_item->order_status, 'order_item_status'.$_item->virtuemart_order_item_id,true);
			$_itemStatusUpdateFields[$_item->virtuemart_order_item_id] =  $_item->order_status;
			/*if(!empty($_item->virtuemart_product_id)){
				$product = $pM->getProduct($_item->virtuemart_product_id);
				if(!empty($product->virtuemart_media_id)){
					$orderDetails['items'][$k]->virtuemart_media_id = $product->virtuemart_media_id;
				} else {
					$orderDetails['items'][$k]->virtuemart_media_id = false;
				}
			}*/
		}

		if (empty($orderDetails['shipmentName']) ) {
			vDispatcher::importVMPlugins('vmshipment');
		    $returnValues = vDispatcher::trigger('plgVmOnShowOrderFEShipment',array(  $orderDetails['details']['BT']->virtuemart_order_id, $orderDetails['details']['BT']->virtuemart_shipmentmethod_id, &$orderDetails['shipmentName']));
			if(empty($orderDetails['shipmentName'])){
				$mShip = VmModel::getModel('Shipmentmethod');
				$shipM = $mShip->getShipment($orderDetails['details']['BT']->virtuemart_shipmentmethod_id);
				if(!empty($shipM->shipment_name)){
					$orderDetails['shipmentName'] = $shipM->shipment_name;
				}
			}
		}

		if (empty($orderDetails['paymentName']) ) {
			vDispatcher::importVMPlugins('vmpayment');
		    $returnValues = vDispatcher::trigger('plgVmOnShowOrderFEPayment',array( $orderDetails['details']['BT']->virtuemart_order_id, $orderDetails['details']['BT']->virtuemart_paymentmethod_id,  &$orderDetails['paymentName']));
			if(empty($orderDetails['paymentName'])){
				$mpay = VmModel::getModel('paymentmethod');
				$payM = $mpay->getPayment($orderDetails['details']['BT']->virtuemart_paymentmethod_id);
				if(!empty($payM->payment_name)){
					$orderDetails['paymentName'] = $payM->payment_name;
				}
			}
		}

		if (strpos($layout,'mail') !== false) {
			$lineSeparator="<br />";
		} else {
			$lineSeparator="\n";
		}
		$this->assignRef('headFooter', $this->showHeaderFooter);

		//Attention, this function will be removed, it wont be deleted, but it is obsoloete in any view.html.php
	    $vendorAddress= shopFunctionsF::renderVendorAddress($virtuemart_vendor_id, $lineSeparator);
		$this->assignRef('vendorAddress', $vendorAddress);

		$vendorEmail = $vendorModel->getVendorEmail($virtuemart_vendor_id);
		$vars['vendorEmail'] = $vendorEmail;

		// this is no setting in BE to change the layout !
		//shopFunctionsF::setVmTemplate($this,0,0,$layoutName);

		if (strpos($layout,'mail') !== false) {
		    if ($this->doVendor) {
			    $this->subject = vmText::sprintf('COM_VIRTUEMART_MAIL_SUBJ_VENDOR_'.$orderDetails['details']['BT']->order_status, $this->shopperName, strip_tags($this->currencyV->priceDisplay($orderDetails['details']['BT']->order_total)), $orderDetails['details']['BT']->order_number);
			    $recipient = 'vendor';
		    } else {
			    $this->subject = vmText::sprintf('COM_VIRTUEMART_MAIL_SUBJ_SHOPPER_'.$orderDetails['details']['BT']->order_status, $vendor->vendor_store_name, strip_tags($this->currency->priceDisplay($orderDetails['details']['BT']->order_total, $this->user_currency_id)), $orderDetails['details']['BT']->order_number );
			    $recipient = 'shopper';
		    }
		    $this->assignRef('recipient', $recipient);
		}

		$tpl = null;

		parent::display($tpl);
	}

	// FE public function renderMailLayout($doVendor=false)
	function renderMailLayout ($doVendor, $recipient) {

		$this->doVendor=$doVendor;

		//customplugins cannot easily access the view
		vRequest::setVar('doVendor', $this->doVendor);

		$this->frompdf=false;
		$this->uselayout = 'mail';

		$attach = VmConfig::get('attach',false);

		//quorvia is there a mail layout override for this status
		if(isset($this->statusemailoverride)  && !empty($this->statusemailoverride) ) {
			//we append "_" + statusmailoverride to uselayout to facilitate shopper and vendor handling in the layout
			$this->uselayout .= "_".$this->statusemailoverride;
		}
		//quorvia end
		if(empty($this->mediaToSend))$this->mediaToSend = array();
		if(empty($this->recipient)) $this->recipient = $recipient;
		if(!empty($attach) and !$doVendor and in_array($this->orderDetails['details']['BT']->order_status,VmConfig::get('attach_os',0)) ){
			$attachment = explode(',',$attach);
			foreach($attachment as $file){
				$p = VMPATH_ROOT.'/'.VmConfig::get('media_vendor_path','').trim($file);
				if(JFile::exists($p)){
					$this->mediaToSend[] = VMPATH_ROOT.'/'.VmConfig::get('media_vendor_path','').'/'.trim($file);
				} else {
					$log = VmEcho::$logDebug;
					VmEcho::$logDebug = 1;
					vmdebug('could not find file attaching to mail ',$p);
					VmEcho::$logDebug = $log;

				}

			}
		}
		$this->isMail = true;
		$this->display();

	}
	
	static function replaceVendorFields ($txt, $vendor) {
		// TODO: Implement more Placeholders (ordernr, invoicenr, etc.); 
		// REMEMBER TO CHANGE VmVendorPDF::replace_variables IN vmpdf.php, TOO!!!
		// Page nrs. for mails is always "1"
		$txt = str_replace('{vm:pagenum}', "1", $txt);
		$txt = str_replace('{vm:pagecount}', "1", $txt);
		$txt = str_replace('{vm:vendorname}', $vendor->vendor_store_name, $txt);
		$imgrepl='';
		if (!empty($vendor->images)) {
			$img = $vendor->images[0];
			$imgrepl = "<div class=\"vendor-image\">".$img->displayIt($img->file_url,'','',false, '', false, false)."</div>";
		}
		$txt = str_replace('{vm:vendorimage}', $imgrepl, $txt);
		$vendorAddress = shopFunctionsF::renderVendorAddress($vendor->virtuemart_vendor_id, "<br/>");
		// Trim the final <br/> from the address, which is inserted by renderVendorAddress automatically!
		if (substr($vendorAddress, -5, 5) == '<br/>') {
			$vendorAddress = substr($vendorAddress, 0, -5);
		}
		$txt = str_replace('{vm:vendoraddress}', $vendorAddress, $txt);
		$txt = str_replace('{vm:vendorlegalinfo}', $vendor->vendor_legal_info, $txt);
		$txt = str_replace('{vm:vendordescription}', $vendor->vendor_store_desc, $txt);
		$txt = str_replace('{vm:tos}', $vendor->vendor_terms_of_service, $txt);
		return "$txt";
	}


}
