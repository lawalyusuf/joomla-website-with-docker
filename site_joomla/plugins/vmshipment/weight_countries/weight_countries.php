<?php

defined ('_JEXEC') or die('Restricted access');

/**
 * Shipment plugin for weight_countries shipments, like regular postal services
 *
 * @version $Id: weight_countries.php 10948 2023-12-19 18:52:59Z  $
 * @package VirtueMart
 * @subpackage Plugins - shipment
 * @copyright Copyright (C) 2004-2021 VirtueMart Team - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 * @author Valerie Isaksen, Max Milbers
 *
 */


class plgVmShipmentWeight_countries extends vmPSPlugin {

	/**
	 * @param object $subject
	 * @param array  $config
	 */
	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);

		$this->_loggable = TRUE;
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$varsToPush = $this->getVarsToPush ();
		$this->addVarsToPushCore($varsToPush,0);

		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
		$this->setConvertable(array('min_amount','max_amount','shipment_cost','package_fee','free_shipment'));

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * @deprecated
	 * @author Valérie Isaksen
	 *
	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Shipment Weight Countries Table');
	}*/

	/**
	 * @return array
	 */
	function getTableSQLFields () {

		$SQLfields = array(
			'id'                           => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'          => 'int(11) UNSIGNED',
			'order_number'                 => 'char(32)',
			'virtuemart_shipmentmethod_id' => 'mediumint(1) UNSIGNED',
			'shipment_name'                => 'varchar(5000)',
			'order_weight'                 => 'decimal(10,4)',
			'shipment_weight_unit'         => 'char(3) DEFAULT \'KG\'',
			'shipment_cost'                => 'decimal(10,2)',
			'shipment_package_fee'         => 'decimal(10,2)',
			'tax_id'                       => 'smallint(1)'
		);
		return $SQLfields;
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the shipment-specific data.
	 *
	 * @param integer $virtuemart_order_id The order ID
	 * @param integer $virtuemart_shipmentmethod_id The selected shipment method id
	 * @param string  $shipment_name Shipment Name
	 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
	 * @author Valérie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmOnShowOrderFEShipment ($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
	}

	/**
	 * This event is fired after the order has been stored; it gets the shipment method-
	 * specific data.
	 *
	 * @param int    $order_id The order_id being processed
	 * @param object $cart  the cart
	 * @param array  $order The actual order saved in the DB
	 * @return mixed Null when this method was not selected, otherwise true
	 * @author Valerie Isaksen
	 */
	function plgVmConfirmedOrder (VirtueMartCart $cart, $order) {

		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_shipmentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->shipment_element)) {
			return FALSE;
		}
		$values['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
		$values['order_number'] = $order['details']['BT']->order_number;
		$values['virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
		$values['shipment_name'] = $this->renderPluginName ($method);
		$values['order_weight'] = $this->getOrderWeight ($cart, $method->weight_unit);
		$values['shipment_weight_unit'] = $method->weight_unit;

		$costs = $this->getCosts($cart,$method,$cart->cartPrices);
		if(!empty($costs)){
			$values['shipment_cost'] = $method->shipment_cost;
			$values['shipment_package_fee'] = $method->package_fee;
		}
		if(empty($values['shipment_cost'])) $values['shipment_cost'] = 0.0;
		if(empty($values['shipment_package_fee'])) $values['shipment_package_fee'] = 0.0;

		$values['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData ($values);

		$extra = $this->renderByLayout( 'orderdone', array("method" => $this->_currentMethod, 'order' => $order, 'cart'=>$cart) );
		if(!empty($extra)){
			$cart->orderdoneHtml = $extra;
			// $cart->setCartIntoSession(false,true);
		}
		return TRUE;
	}

	/**
	 * This method is fired when showing the order details in the backend.
	 * It displays the shipment-specific data.
	 * NOTE, this plugin should NOT be used to display form fields, since it's called outside
	 * a form! Use plgVmOnUpdateOrderBE() instead!
	 *
	 * @param integer $virtuemart_order_id The order ID
	 * @param integer $virtuemart_shipmentmethod_id The order shipment method ID
	 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderBEShipment ($virtuemart_order_id, $virtuemart_shipmentmethod_id) {

		if (!($this->selectedThisByMethodId ($virtuemart_shipmentmethod_id))) {
			return NULL;
		}
		$html = $this->getOrderShipmentHtml ($virtuemart_order_id);
		return $html;
	}

	/**
	 * @param $virtuemart_order_id
	 * @return string
	 */
	function getOrderShipmentHtml ($virtuemart_order_id) {

		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
		$db->setQuery ($q);
		if (!($shipinfo = $db->loadObject ())) {
			$msg=vmText::sprintf('VMSHIPMENT_WEIGHT_COUNTRIES_NO_ENTRY_FOUND', $virtuemart_order_id);
			//vmWarn ($msg);
			//vmDebug($msg, $q );
			return $msg;
		}

		$currency = CurrencyDisplay::getInstance ();
		$tax = ShopFunctions::getTaxByID ($shipinfo->tax_id);
		$taxDisplay = is_array ($tax) ? $tax['calc_value'] . ' ' . $tax['calc_value_mathop'] : $shipinfo->tax_id;
		$taxDisplay = ($taxDisplay == -1) ? vmText::_ ('COM_VIRTUEMART_PRODUCT_TAX_NONE') : $taxDisplay;

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('COM_VIRTUEMART_SHIPMENT_NAME', $shipinfo->shipment_name);
		$html .= $this->getHtmlRowBE ('VMSHIPMENT_WEIGHT_COUNTRIES_WEIGHT', $shipinfo->order_weight . ' ' . ShopFunctions::renderWeightUnit ($shipinfo->shipment_weight_unit));
		$html .= $this->getHtmlRowBE ('VMSHIPMENT_WEIGHT_COUNTRIES_COST', $currency->priceDisplay ($shipinfo->shipment_cost));
		$html .= $this->getHtmlRowBE ('VMSHIPMENT_WEIGHT_COUNTRIES_PACKAGE_FEE', $currency->priceDisplay ($shipinfo->shipment_package_fee));
		$html .= $this->getHtmlRowBE ('VMSHIPMENT_WEIGHT_COUNTRIES_TAX', $taxDisplay);
		$html .= '</table>' . "\n";

		return $html;
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param                $method
	 * @param                $cart_prices
	 * @return int
	 */
	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

		if ($method->free_shipment && $cart_prices['salesPrice'] >= $method->free_shipment) {
			return 0.0;
		} else {
			if(empty($method->shipment_cost)) $method->shipment_cost = 0.0;
			if(empty($method->package_fee)) $method->package_fee = 0.0;
			return $method->shipment_cost + $method->package_fee;
		}
	}

	/**
	 * @param \VirtueMartCart $cart
	 * @param int             $method
	 * @param array           $cart_prices
	 * @return bool
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {

		static $result = array();

		if($cart->STsameAsBT == 0){
			$type = 'ST';
		} else {
			$type = 'BT';
		}

		$address = $cart -> getST();

		if(!is_array($address)) $address = array();
		if(isset($cart_prices['salesPrice'])){
			$hashSalesPrice = $this->getCartAmount($cart_prices,true); ;//$cart_prices['salesPrice'];
		} else {
			$hashSalesPrice = '';
		}


		if(empty($address['virtuemart_country_id'])) $address['virtuemart_country_id'] = 0;
		if(empty($address['zip'])) $address['zip'] = 0;

		$hash = $method->virtuemart_shipmentmethod_id.$type.$address['virtuemart_country_id'].'_'.$address['zip'].'_'.$hashSalesPrice;

		if(isset($result[$hash])){
			return $result[$hash];
		}

		$this->convert ($method);

		$result[$hash] = parent::checkConditions($cart, $method, $cart_prices);

		if(!$result[$hash]) return false;

		$orderWeight = $this->getOrderWeight ($cart, $method->weight_unit);

		$weight_cond = $this->testRange($orderWeight,$method,'weight_start','weight_stop','weight');
		$nbproducts_cond = $this->_nbproductsCond ($cart, $method);

		$userFieldsModel =VmModel::getModel('Userfields');
		if ($userFieldsModel->fieldPublished('zip', $type)){
			if (!isset($address['zip'])) {
				$address['zip'] = '';
			}
			$zip_cond = $this->testRange($address['zip'],$method,'zip_start','zip_stop','zip');
		} else {
			$zip_cond = true;
		}

		$allconditions = (int) $weight_cond + (int)$zip_cond + (int)$nbproducts_cond;
		if($allconditions === 3){
			$result[$hash] = true;
			return TRUE;
		} else {
			$result[$hash] = false;
			//vmdebug('checkConditions '.$method->shipment_name.' does not fit ',(int)$weight_cond,(int)$zip_cond,(int)$nbproducts_cond,(int)$orderamount_cond,(int)$country_cond);
			return FALSE;
		}

		$result[$hash] = false;
		return FALSE;
	}

	/**
	 * @param $method
	 */
	function convert (&$method) {

		//$method->weight_start = (float) $method->weight_start;
		//$method->weight_stop = (float) $method->weight_stop;
		$method->min_amount =  (float)str_replace(',','.',$method->min_amount);
		$method->max_amount =   (float)str_replace(',','.',$method->max_amount);
		$method->zip_start = (int)$method->zip_start;
		$method->zip_stop = (int)$method->zip_stop;
		$method->nbproducts_start = (int)$method->nbproducts_start;
		$method->nbproducts_stop = (int)$method->nbproducts_stop;
		$method->free_shipment = (float)str_replace(',','.',$method->free_shipment);
	}

	/**
	 * @param $cart
	 * @param $method
	 * @return bool
	 */
	private function _nbproductsCond ($cart, $method) {

		if (empty($method->nbproducts_start) and empty($method->nbproducts_stop)) {
			//vmdebug('_nbproductsCond',$method);
			return true;
		}

		$nbproducts = 0;
		foreach ($cart->products as $product) {
			$nbproducts += $product->quantity;
		}

		if ($nbproducts) {

			$nbproducts_cond = $this->testRange($nbproducts,$method,'nbproducts_start','nbproducts_stop','products quantity');

		} else {
			$nbproducts_cond = false;
		}

		return $nbproducts_cond;
	}


	private function testRange($value, $method, $floor, $ceiling,$name){

		$cond = true;
		if(!empty($method->{$floor}) and !empty($method->{$ceiling})){
			$cond = (($value >= $method->{$floor} AND $value <= $method->{$ceiling}));
			if(!$cond){
				$result = 'FALSE';
				$reason = 'is NOT within Range of the condition from '.$method->{$floor}.' to '.$method->{$ceiling};
			} else {
				$result = 'TRUE';
				$reason = 'is within Range of the condition from '.$method->{$floor}.' to '.$method->{$ceiling};
			}
		} else if(!empty($method->{$floor})){
			$cond = ($value >= $method->{$floor});
			if(!$cond){
				$result = 'FALSE';
				$reason = 'is not at least '.$method->{$floor};
			} else {
				$result = 'TRUE';
				$reason = 'is over min limit '.$method->{$floor};
			}
		} else if(!empty($method->{$ceiling})){
			$cond = ($value <= $method->{$ceiling});
			if(!$cond){
				$result = 'FALSE';
				$reason = 'is over '.$method->{$ceiling};
			} else {
				$result = 'TRUE';
				$reason = 'is lower than the set '.$method->{$ceiling};
			}
		} else {
			$result = 'TRUE';
			$reason = 'no boundary conditions set';
		}

		if($reason != 'no boundary conditions set') vmdebug('shipmentmethod '.$method->shipment_name.' = '.$result.' for variable '.$name.' = '.$value.' Reason: '.$reason);
		return $cond;
	}


	function plgVmOnProductDisplayShipment($product, &$productDisplayShipments){

		if ($this->getPluginMethods($product->virtuemart_vendor_id) === 0) {

			return FALSE;
		}

		$html = array();

		$currency = CurrencyDisplay::getInstance();
		
		foreach ($this->methods as $this->_currentMethod) {

			if($this->_currentMethod->show_on_pdetails){

				if(!isset($cart)){
					$cart = VirtueMartCart::getCart();
					$cart->products['virtual'] = $product;
					$cart->_productAdded = true;
					$cart->prepareCartData();
				}
				if($this->checkConditions($cart,$this->_currentMethod,$cart->cartPrices)){

					$product->prices['shipmentPrice'] = $this->getCosts($cart,$this->_currentMethod,$cart->cartPrices);

					if(isset($product->prices['VatTax']) and count($product->prices['VatTax'])>0){
						reset($product->prices['VatTax']);
						$rule = current($product->prices['VatTax']);
						if(isset($rule[1])){
							$product->prices['shipmentTax'] = $product->prices['shipmentPrice'] * $rule[1]/100.0;
							$product->prices['shipmentPrice'] = $product->prices['shipmentPrice'] * (1 + $rule[1]/100.0);
						}
					}

					$html[$this->_currentMethod->virtuemart_shipmentmethod_id] = $this->renderByLayout( 'default', array("method" => $this->_currentMethod, "cart" => $cart,"product" => $product,"currency" => $currency) );
				}
			}
		}
		if(isset($cart)){
			unset($cart->products['virtual']);
			$cart->_productAdded = true;
			$cart->prepareCartData();
		}


		$productDisplayShipments[] = $html;

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallShipmentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * @param VirtueMartCart $cart
	 * @return null
	 */
	public function plgVmOnSelectCheckShipment (VirtueMartCart &$cart) {

		return $this->OnSelectCheck ($cart);
	}

	/**
	 * plgVmDisplayListFE
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEShipment (VirtueMartCart $cart, $selected, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param array          $cart_prices
	 * @param                $cart_prices_name
	 * @return bool|null
	 */
	public function plgVmOnSelectedCalculatePriceShipment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * plgVmOnCheckAutomaticSelected
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedShipment (VirtueMartCart $cart, array $cart_prices, &$shipCounter) {
		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $shipCounter);
	}

	function plgVmOnCheckoutCheckDataShipment(VirtueMartCart $cart){

		if(empty($cart->virtuemart_shipmentmethod_id)) return false;

		if ($this->getPluginMethods($cart->vendorId) === 0) {
			return NULL;
		}

		foreach ($this->methods as $this->_currentMethod) {
			if($cart->virtuemart_shipmentmethod_id == $this->_currentMethod->virtuemart_shipmentmethod_id){
				if(!$this->checkConditions($cart,$this->_currentMethod,$cart->cartPrices)){
					return false;
				} else {
					//return true;
				}
				break;
			}
		}
	}

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmOnShowOrderPrint ($order_number, $method_id) {
		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsShipmentVM3 (&$data) {
		return $this->declarePluginParams ('shipment', $data);
	}


	/**
	 * @author Max Milbers
	 * @param $data
	 * @param $table
	 * @return bool
	 */
	function plgVmSetOnTablePluginShipment(&$data,&$table){

		$name = $data['shipment_element'];
		$id = $data['shipment_jplugin_id'];

		if (!empty($this->_psType) and !$this->selectedThis ($this->_psType, $name, $id)) {
			return FALSE;
		} else {
			$tCon = array('weight_start','weight_stop','min_amount','max_amount','shipment_cost','package_fee');
			foreach($tCon as $f){
				if(!empty($data[$f])){
					$data[$f] = str_replace(array(',',' '),array('.',''),$data[$f]);
				}
			}

			$data['nbproducts_start'] = (int) $data['nbproducts_start'];
			$data['nbproducts_stop'] = (int) $data['nbproducts_stop'];

			//Reasonable tests:
			if(!empty($data['zip_start']) and !empty($data['zip_stop']) and (int)$data['zip_start']>=(int)$data['zip_stop']){
				vmWarn('VMSHIPMENT_WEIGHT_COUNTRIES_ZIP_CONDITION_WRONG');
			}
			if(!empty($data['weight_start']) and !empty($data['weight_stop']) and (float)$data['weight_start']>=(float)$data['weight_stop']){
				vmWarn('VMSHIPMENT_WEIGHT_COUNTRIES_WEIGHT_CONDITION_WRONG');
			}

			if(!empty($data['min_order']) and !empty($data['max_order']) and (float)$data['min_order']>=(float)$data['max_order']){
				vmWarn('VMSHIPMENT_WEIGHT_COUNTRIES_AMOUNT_CONDITION_WRONG');
			}

			if(!empty($data['nbproducts_start']) and !empty($data['nbproducts_stop']) and (float)$data['nbproducts_start']>=(float)$data['nbproducts_stop']){
				vmWarn('VMSHIPMENT_WEIGHT_COUNTRIES_NBPRODUCTS_CONDITION_WRONG');
			}

			//$data['orderamount_start'] = $data['min_amount'];
			//$data['orderamount_stop'] = $data['max_amount'];

			//$data['show_on_pdetails'] = (int) $data['show_on_pdetails'];
			return $this->setOnTablePluginParams ($name, $id, $table);
		}
	}


}

// No closing tag
