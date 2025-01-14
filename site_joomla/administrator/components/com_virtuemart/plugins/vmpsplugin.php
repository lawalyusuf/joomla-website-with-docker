<?php

defined ('_JEXEC') or die('Restricted access');
/**
 * abstract class for payment/shipment plugins
 *
 * @package    VirtueMart
 * @subpackage Plugins
 * @author Max Milbers
 * @author Valérie Isaksen
 * @link https://virtuemart.net
 * @copyright Copyright (C) 2004-2021 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id$
 */

abstract class vmPSPlugin extends vmPlugin {

	protected $_toConvert = false;
	public $methods = null;
	public $_currentMethod = null;

	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);

		$this->_tablepkey = 'id'; //virtuemart_order_id';
		$this->_idName = 'virtuemart_' . $this->_psType . 'method_id';
		$this->_configTable = '#__virtuemart_' . $this->_psType . 'methods';
		$this->_configTableFieldName = $this->_psType . '_params';
		$this->_configTableFileName = $this->_psType . 'methods';
		$this->_configTableClassName = 'Table' . ucfirst ($this->_psType) . 'methods'; //TablePaymentmethods
		// 		$this->_configTableIdName = $this->_psType.'_jplugin_id';
		$this->_loggable = TRUE;

		//We set the usually existing values convertible (converts , to .)
		$this->setConvertDecimal(array('min_amount','max_amount','cost_per_transaction','cost_min_transaction','cost_percent_total','shipment_cost','package_fee'));
	}

	public function getVarsToPush () {
		return self::getVarsToPushByXML($this->_xmlFile,$this->_name.'Form');
	}

	static public function addVarsToPushCore(&$varsToPush, $payment=1){

		$varsToPush['categories'] = array('','char');
		$varsToPush['blocking_categories'] = array('','char');
		$varsToPush['countries'] = array('','char');
		$varsToPush['blocking_countries'] = array('','char');
		$varsToPush['min_amount'] = array('','char');
		$varsToPush['max_amount'] = array('','char');
		$varsToPush['virtuemart_shipmentmethod_ids'] = array('','char');
		$varsToPush['byCoupon'] = array('','int');
		$varsToPush['couponCode'] = array('','char');
		if($payment)
			$varsToPush['progressive'] = array('1','int');

		unset($varsToPush['checkConditionsCore']);
	}

	public function setConvertable($toConvert) {
		$this->_toConvert = $toConvert;
	}

	/**
	 * check if it is the correct type
	 *
	 * @param string $psType either payment or shipment
	 * @return boolean
	 */
	public function selectedThisType ($psType) {

		if ($this->_psType <> $psType) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function onSelectCheck (VirtueMartCart $cart) {

		$idName = $this->_idName;
		if (!$this->selectedThisByMethodId ($cart->{$idName})) {
			return NULL; // Another method was selected, do nothing
		}
		return TRUE; // this method was selected , and the data is valid by default
	}

	/**
	 * displayListFE
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, vmWarn) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function displayListFE (VirtueMartCart $cart, $selected, &$htmlIn) {

		if ($this->getPluginMethods ($cart->vendorId) === 0) {
			if (empty($this->_name)) {
				vmAdminInfo ('displayListFE cartVendorId=' . $cart->vendorId);
				$app = JFactory::getApplication ();
				$app->enqueueMessage (vmText::_ ('COM_VIRTUEMART_CART_NO_' . strtoupper ($this->_psType)));
				return FALSE;
			} else {
				return FALSE;
			}
		}

		$mname = $this->_psType . '_name';
		$idN = 'virtuemart_'.$this->_psType.'method_id';

		$ret = FALSE;
		foreach ($this->methods as $method) {
			if(!isset($htmlIn[$this->_psType][$method->{$idN}])) {
				if ($this->checkConditions ($cart, $method, $cart->cartPrices)) {

					// the price must not be overwritten directly in the cart
					$prices = $cart->cartPrices;
					$methodSalesPrice = $this->setCartPrices ($cart, $prices ,$method);

					//This makes trouble, because $method->$mname is used in  renderPluginName to render the Name, so it must not be called twice!
					$method->{$mname} = $this->renderPluginName ($method);

					$htmlIn[$this->_psType][$method->{$idN}] = $this->getPluginHtml ($method, $selected, $methodSalesPrice);

					$ret = TRUE;
				}
			} else {
				$ret = TRUE;
			}
		}

		return $ret;
	}

	/*
	 * onSelectedCalculatePrice
	* Calculate the price (value, tax_id) of the selected method
	* It is called by the calculator
	* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	* @author Valerie Isaksen
	* @cart: VirtueMartCart the current cart
	* @cart_prices: array the new cart prices
	* @return null if the method was not selected, false if the shipping rate is not valid any more, true otherwise
	*
	*/

	public function onSelectedCalculatePrice (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		$idName = $this->_idName;

		if (!($method = $this->selectedThisByMethodId ($cart->{$idName}))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$method = $this->getVmPluginMethod ($cart->{$idName}) or empty($method->{$idName})) {
			return NULL;
		}

		$cart_prices_name = '';
		$cart_prices['cost'] = 0;

		if (!$this->checkConditions ($cart, $method, $cart_prices)) {
			return FALSE;
		}

		$cart_prices_name = $this->renderPluginName ($method);

		$this->setCartPrices ($cart, $cart_prices, $method);

		return TRUE;
	}

	function plgVmOnCheckAutomaticSelected (VirtueMartCart $cart, array $cart_prices, &$shipCounter, $type) {
		if($type!=$this->_psType) return ;
		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $shipCounter);

	}

	/**
	 * onCheckAutomaticSelected
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function onCheckAutomaticSelected (VirtueMartCart $cart, array $cart_prices = array(), &$methodCounter = 0) {

		$virtuemart_pluginmethod_ids = array();

		$nbMethod = $this->getSelectables ($cart, $virtuemart_pluginmethod_ids, $cart_prices);
		$methodCounter += $nbMethod;

		if ($nbMethod == NULL) {
			return NULL;
		} else {
			return $virtuemart_pluginmethod_ids;
		}
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	protected function onShowOrderFE ($virtuemart_order_id, $virtuemart_method_id, &$method_info) {

		if (!($this->selectedThisByMethodId ($virtuemart_method_id))) {
			return NULL;
		}
		$method_info = $this->getOrderMethodNamebyOrderId ($virtuemart_order_id);
	}


	/**
	 * check if it is the correct element
	 *
	 * @param string $element either standard or paypal
	 * @return boolean
	 */
	public function selectedThisElement ($element) {

		if ($this->_name <> $element) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/**
	 * This method is fired when showing the order details in the backend.
	 * It displays the the payment method-specific data.
	 * All plugins *must* reimplement this method.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $_paymethod_id Payment method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	function onShowOrderBE ($_virtuemart_order_id, $_method_id) {
		return NULL;
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
	function onShowOrderPrint ($order_number, $method_id) {

		if (!$this->selectedThisByMethodId ($method_id)) {
			return NULL; // Another method was selected, do nothing
		}
		if (!($order_name = $this->getOrderPluginName ($order_number, $method_id))) {
			return NULL;
		}

		vmLanguage::loadJLang('com_virtuemart');

		$html = '<table class="admintable">' . "\n"
			. '	<thead>' . "\n"
			. '		<tr>' . "\n"
			. '			<td class="key" style="text-align: center;" colspan="2">' . vmText::_ ('COM_VIRTUEMART_ORDER_PRINT_' . strtoupper($this->_type) . '_LBL') . '</td>' . "\n"
			. '		</tr>' . "\n"
			. '	</thead>' . "\n"
			. '	<tr>' . "\n"
			. '		<td class="key">' . vmText::_ ('COM_VIRTUEMART_ORDER_PRINT_' . strtoupper($this->_type) . '_LBL') . ': </td>' . "\n"
			. '		<td align="left">' . $order_name . '</td>' . "\n"
			. '	</tr>' . "\n";

		$html .= '</table>' . "\n";
		return $html;
	}

	private function getOrderPluginName ($order_number, $pluginmethod_id) {

		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE `order_number` = "' . $order_number . '"
		AND `' . $this->_idName . '` =' . $pluginmethod_id;
		$db->setQuery ($q);
		if (!($order = $db->loadObject ())) {
			return NULL;
		}

		$plugin_name = $this->_psType . '_name';
		return $order->{$plugin_name};
	}

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 */
	public function onUpdateOrder ($formData) {
		return NULL;
	}

	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 */
	public function onUpdateOrderLine ($formData) {
		return NULL;
	}

	/**
	 * OnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 */
	public function onEditOrderLineBE ($orderId, $lineId) {
		return NULL;
	}

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 */
	public function onShowOrderLineFE ($orderId, $lineId) {
		return NULL;
	}

	/**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param      $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int  $virtuemart_order_id : payment  order id
	 * @param char $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	public function onNotification () {
		return NULL;
	}

	/**
	 * OnResponseReceived
	 * This event is fired when the  method returns to the shop after the transaction
	 *
	 *  the method itself should send in the URL the parameters needed
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param int  $virtuemart_order_id : should return the virtuemart_order_id
	 * @param text $html: the html to display
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	function onResponseReceived (&$virtuemart_order_id, &$html) {
		return NULL;
	}

	function getDebug () {
		return $this->_debug;
	}

	function setDebug ($params) {
		return $this->_debug = $params->get ('debug', 0);
	}

	/**
	 * Get Plugin Data for a go given plugin ID
	 *
	 * @author Valérie Isaksen
	 * @param int $pluginmethod_id The method ID
	 * @return  method data
	 */
	final protected function getPluginMethod ($method_id) {

		return $this->getVmPluginMethod ($method_id);

	}

	/**
	 * Fill the array with all plugins found with this plugin for the current vendor
	 * Todo it would be nicer to use here the correct vmtable methods
	 * @return True when plugins(s) was (were) found for this vendor, false otherwise
	 * @author Max Milbers
	 * @author valerie Isaksen
	 */
	static $mC = array();
	public function getPluginMethods ($vendorId, $onlyPublished = true, $userId = null) {

		if($this->methods !== null and is_array($this->methods)){
			return count($this->methods);
		}
		if(empty($vendorId)) $vendorId = 1;

		if(empty(self::$mC[$this->_psType])) {

			$db = JFactory::getDBO();

			$select = 'SELECT i.*, ';

			$extPlgTable = '#__extensions';
			$extField1 = 'extension_id';
			$extField2 = 'element';

			$select .= 'j.`'.$extField1.'`,j.`name`, j.`type`, j.`element`, j.`folder`, j.`client_id`, j.`enabled`, j.`access`, j.`protected`, j.`manifest_cache`,
				j.`params`, j.`custom_data`, j.`checked_out`, j.`checked_out_time`, j.`state` ';

			if(JVM_VERSION == 3){
				$select .= ', j.`system_data` ';
			}

			if($userId!==false){
				$select .= ', s.virtuemart_shoppergroup_id ';
			}

			if(!VmConfig::$vmlang) {
				vmLanguage::initialise();
			}

			$joins = array();

			$langFields = array($this->_psType.'_name', $this->_psType.'_desc');

			$select .= ', '.implode( ', ', VmModel::joinLangSelectFields( $langFields ) );

			$joins = VmModel::joinLangTables( '#__virtuemart_'.$this->_psType.'methods', 'i', 'virtuemart_'.$this->_psType.'method_id' );
			array_unshift( $joins, ' FROM #__virtuemart_'.$this->_psType.'methods as i' );

			$joins[] = ' LEFT JOIN `'.$extPlgTable.'` as j ON j.`'.$extField1.'` =  i.`'.$this->_psType.'_jplugin_id` ';

			$w = '';
			if($onlyPublished){
				$w .= ' i.`published` = "1" AND ';
			}

			//AND  j.`' . $extField2 . '` = "' . $this->_name . '"
			$w .= ' (i.`virtuemart_vendor_id` = "'.$vendorId.'" OR i.`virtuemart_vendor_id` = "0" OR i.`shared` = "1") ';

			if($userId!==false){

				$usermodel = VmModel::getModel( 'user' );
				$user = $usermodel->getUser($userId);
				$user->shopper_groups = (array)$user->shopper_groups;

				$joins[] = ' LEFT OUTER JOIN `#__virtuemart_'.$this->_psType.'method_shoppergroups` AS s ON i.`virtuemart_'.$this->_psType.'method_id` = s.`virtuemart_'.$this->_psType.'method_id` ';

				$w .= ' AND  (';
				foreach( $user->shopper_groups as $groups ) {
					$w .= ' s.`virtuemart_shoppergroup_id`= "'.(int)$groups.'" OR';
				}
				$w .= ' (s.`virtuemart_shoppergroup_id`) IS NULL ) ';
			}

			$q = $select.implode( ' '."\n", $joins ).' WHERE '."\n".$w;
			$q .= ' GROUP BY i.`virtuemart_'.$this->_psType.'method_id` ORDER BY i.`ordering` ASC';




			try {
				$db->setQuery( $q );
				$allMethods = $db->loadObjectList();
				//vmdebug('getPluginMethods '.$this->_psType.' my query ',str_replace('#__',$db->getPrefix(),$q));
				//vmdebug( 'loaded all methods for the vendor ', $allMethods );
				foreach( $allMethods as $methd ) {
					$h = $vendorId.$methd->element;

					$psType = substr($methd->folder,2);
					//vmdebug( '$h  methods for the vendor '.$psType, $h );
					if(!isset( self::$mC[$psType][$h] )) {
						self::$mC[$psType][$h] = array();
					}
					self::$mC[$psType][$h][] = $methd;

				}
			} catch (Exception $e){
				vmError( 'Error in sql vmpsplugin.php function getPluginMethods '.$e->getMessage() );
				VmEcho::$logDebug= 1;
				vmdebug('getPluginMethods Error in my query ',str_replace('#__',$db->getPrefix(),$q));
				return false;
			}

		}

		$h = $vendorId.$this->_name;
		//vmdebug('getPluginMethods requesting methods for '.$this->_psType.' '.$h);
		if(isset(self::$mC[$this->_psType][$h])) {
			$this->methods = self::$mC[$this->_psType][$h];

			if($this->methods) {
				foreach( $this->methods as $method ) {
					VmTable::bindParameterable( $method, $this->_xParams, $this->_varsToPushParam );
					$this->decryptFields( $method );
				}
			} else if($this->methods === null or empty( $this->methods )) {
				$this->methods = array();
				//vmError ('Error reading getPluginMethods ' . $q);
			}
			//vmdebug('getPluginMethods return cached '.$h);
			return count($this->methods);
		}

		//self::$mC[$h] = $this->methods;
		//vmdebug('getPluginMethods there is no method for the plugin with the folder '.$this->_name);
		return 0;//count($this->methods);
	}

	function decryptFields($method){
		if($this->_cryptedFields and is_array($this->_cryptedFields)){

			if(isset($method->modified_on) and $method->modified_on!='0000-00-00 00:00:00'){
				$date = JFactory::getDate($method->modified_on);
				$date = $date->toUnix();
			} else if(isset($method->created_on) and $method->created_on!='0000-00-00 00:00:00'){
				$date = JFactory::getDate($method->created_on);
				$date = $date->toUnix();
			} else {
				$date = 0;
			}

			foreach($this->_cryptedFields as $field){
				if(isset($method->{$field})){
					$t = $method->{$field};
					$method->{$field} = vmCrypt::decrypt($method->{$field}, $date);
					//vmdebug(' Field '.$field.' crypted '.$t.' decrypted = '.$method->{$field});
				}
			}
			$this->_encrypted = false;
		}
	}

	/**
	 *
	 * @author Max Milbers
	 * @param int $virtuemart_order_id
	 * @return string pluginName from the plugin table
	 */
	protected function getOrderMethodNamebyOrderId ($virtuemart_order_id) {

		$idName = $this->_psType . '_name';
		$mData = $this->getDataByOrderId($virtuemart_order_id);
		if($mData){
			return $mData->{$idName};
		} else {
			return false;
		}

	}

	/**
	 * Get Method Data for a given Payment ID
	 *
	 * @author Valérie Isaksen
	 * @author Max Milbers
	 * @param int $virtuemart_order_id The order ID
	 * @return  $methodData
	 */
	final protected function getDataByOrderId ($virtuemart_order_id) {

		static $methodDatas = array();  //In current case, a simple cache would be enough, but to prevent bugs in future we use an array

		$t = substr($this->_tablename,3);

		$h = $virtuemart_order_id.$t;
		if(empty($methodDatas[$h])){
			if(!vmTable::checkTableExists($t)) return false;
			$db = JFactory::getDBO ();
			$q = 'SELECT * FROM `' . $this->_tablename . '` '
				. 'WHERE `virtuemart_order_id` = ' . (int)$virtuemart_order_id . ' ORDER BY `id` DESC LIMIT 1';

			$db->setQuery ($q);
			if (!($mData = $db->loadObject ())) {
				$methodDatas[$h] = false;
				vmdebug ('Attention, ' . $this->_tablename . ' has not any entry for order_id = '.$virtuemart_order_id);
				return false;
			} else {
				$methodDatas[$h] = $mData;
			}

		}

		return $methodDatas[$h];
	}

	/**
	 * Get Method Datas for a given Payment ID
	 *
	 * @author Valérie Isaksen
	 * @param int $virtuemart_order_id The order ID
	 * @return  $methodData
	 */
	final protected function getDatasByOrderId ($virtuemart_order_id) {

		$t = substr($this->_tablename,3);
		if(!vmTable::checkTableExists($t)) return false;
		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `virtuemart_order_id` = "' . (int)$virtuemart_order_id. '" '
			. 'ORDER BY `id` ASC';

		$db->setQuery ($q);
		$methodData = $db->loadObjectList ();

		return $methodData;
	}
	/**
	 * Get Method Data for a given Payment ID
	 *
	 * @author Valérie Isaksen
	 * @param int $order_number The order Number
	 * @return  $methodData
	 */
	final protected function getDataByOrderNumber ($order_number) {

		$t = substr($this->_tablename,3);
		if(!vmTable::checkTableExists($t)) return false;
		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `order_number`="'.$db->escape($order_number).'" ORDER BY `id` LIMIT 1';

		$db->setQuery ($q);
		$methodData = $db->loadObject ();

		return $methodData;
	}

	/**
	 * Get Method Datas for a given Payment ID
	 *
	 * @author Valérie Isaksen
	 * @param int $order_number The order Number
	 * @return  $methodData
	 */
	final protected function getDatasByOrderNumber ($order_number) {

		$t = substr($this->_tablename,3);
		if(!vmTable::checkTableExists($t)) return false;
		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `order_number`="'.$db->escape($order_number).'" and payment_currency > "0"';

		$db->setQuery ($q);
		$methodData = $db->loadObjectList ();

		return $methodData;
	}

	/**
	 * Get the total weight for the order, based on which the proper shipping rate
	 * can be selected.
	 *
	 * @param object $cart Cart object
	 * @return float Total weight for the order
	 */
	protected function getOrderWeight (VirtueMartCart $cart, $to_weight_unit) {

		return VirtueMartCart::getCartWeight($cart, $to_weight_unit);
		/*static $weight = array();
		if(!isset($weight[$to_weight_unit])) $weight[$to_weight_unit] = 0.0;
		if(count($cart->products)>0 and empty($weight[$to_weight_unit])){

			foreach ($cart->products as $product) {
				$weight[$to_weight_unit] += (ShopFunctions::convertWeightUnit ($product->product_weight, $product->product_weight_uom, $to_weight_unit) * $product->quantity);
			}
		}

		return $weight[$to_weight_unit];*/
	}

	/**
	 * getThisName
	 * Get the name of the method
	 *
	 * @param int $id The method ID
	 * @author Valérie Isaksen
	 * @return string Shipment name
	 */
	final protected function getThisName ($virtuemart_method_id) {

		$db = JFactory::getDBO ();
		$q = 'SELECT `' . $this->_psType . '_name` '
			. 'FROM #__virtuemart_' . $this->_psType . 'methods '
			. 'WHERE ' . $this->_idName . ' = "' . (int)$virtuemart_method_id . '" ';
		$db->setQuery ($q);
		return $db->loadResult (); // TODO Error check
	}


	/**
	 * Extends the standard function in vmplugin. Extendst the input data by virtuemart_order_id
	 * Calls the parent to execute the write operation
	 *
	 * @author Max Milbers
	 * @param array  $_values
	 * @param string $_table
	 */
	protected function storePSPluginInternalData ($values, $primaryKey = 0, $preload = FALSE) {

		if (!isset($values['virtuemart_order_id'])) {
			$values['virtuemart_order_id'] = VirtueMartModelOrders::getOrderIdByOrderNumber ($values['order_number']);
		}
		return $this->storePluginInternalData ($values, $primaryKey, 0, $preload);
	}

	/**
	 * Something went wrong, Send notification to all administrators
	 *
	 * @param string subject of the mail
	 * @param string message
	 */
	protected function sendEmailToVendorAndAdmins ($subject = NULL, $message = NULL) {

		// recipient is vendor and admin
		$vendorId = 1;
		$vendorModel = VmModel::getModel('vendor');
		$vendor = $vendorModel->getVendor($vendorId);
		$vendorEmail = $vendorModel->getVendorEmail($vendorId);
		$vendorName = $vendorModel->getVendorName($vendorId);
		vmLanguage::loadJLang('com_virtuemart');
		if ($subject == NULL) {
			$subject = vmText::sprintf('COM_VIRTUEMART_ERROR_SUBJECT', $this->_name, $vendor->vendor_store_name);
		}
		if ($message == NULL) {
			$message = vmText::sprintf('COM_VIRTUEMART_ERROR_BODY', $subject, $this->getLogFilename().VmConfig::LOGFILEEXT);
		}
		JFactory::getMailer()->sendMail($vendorEmail, $vendorName, $vendorEmail, $subject, $message);

		$query = 'SELECT name, email, sendEmail FROM #__users WHERE sendEmail=1';

		$db = JFactory::getDBO();
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$subject = html_entity_decode($subject, ENT_QUOTES);

		// get superadministrators id
		foreach ($rows as $row) {
			if ($row->sendEmail) {
				$message = html_entity_decode($message, ENT_QUOTES);
				JFactory::getMailer()->sendMail($vendorEmail, $vendorName, $row->email, $subject, $message);
			}
		}
	}

	/**
	 * displays the logos of a VirtueMart plugin
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 * @param array $logo_list
	 * @return html with logos
	 */
	protected function displayLogos ($logo_list) {

		$img = "";

		if (!(empty($logo_list))) {

			$url ='images/virtuemart/' . $this->_psType ;

			if(!JFolder::exists( VMPATH_ROOT .'/'. $url)){
				$url = 'images/stories/virtuemart/' . $this->_psType;
				if(!JFolder::exists(VMPATH_ROOT .'/'. $url)){
					return $img;
				}
			}

			if (!is_array ($logo_list)) {
				$logo_list = (array)$logo_list;
			}
			foreach ($logo_list as $logo) {
				if(!empty($logo)){
					if(JFile::exists(VMPATH_ROOT .'/'. $url .'/'. $logo)){
						$alt_text = substr ($logo, 0, strpos ($logo, '.'));
						//Currently we do not see  the images in the invoices, because they are given with absolute URLS, which are needed for the email. We need a better solution here.
						$img .= '<span class="vmCart' . ucfirst($this->_psType) . 'Logo" ><img align="middle" src="' . JUri::root().$url.'/'.$logo . '"  alt="' . $alt_text . '" /></span> ';
					}
				}
			}
		}
		return $img;
	}

	protected function renderPluginName ($plugin) {
		//stAn - do not cache here as some variables like obj->plugin_name might be shared in DB schema vs plugin datas
		$c = array();
		$idN = 'virtuemart_'.$this->_psType.'method_id';

		if(isset($c[$this->_psType][$plugin->{$idN}])){
			return $c[$this->_psType][$plugin->{$idN}];
		}

		$return = '';
		$plugin_name = $this->_psType . '_name';
		$plugin_desc = $this->_psType . '_desc';
		$description = '';
		$logosFieldName = $this->_psType . '_logos';
		$logos = property_exists($plugin,$logosFieldName)? $plugin->{$logosFieldName}:array();
		if (!empty($logos)) {
			$return = $this->displayLogos ($logos) . ' ';
		}
		if (!empty($plugin->{$plugin_desc})) {
			$description = '<span class="' . $this->_type . '_description">' . $plugin->{$plugin_desc} . '</span>';
		}
		$c[$this->_psType][$plugin->{$idN}] = $return . '<span class="' . $this->_type . '_name">' . $plugin->{$plugin_name} . '</span>' . $description;

		return $c[$this->_psType][$plugin->{$idN}];
	}

	protected function getPluginHtml ($plugin, $selectedPlugin, $pluginSalesPrice) {

		$currency = CurrencyDisplay::getInstance ();

		$input = array('plugin' => $plugin,
		'psType'=>$this->_psType,
		'selectedPlugin' => $selectedPlugin,
		'pluginSalesPrice' => $pluginSalesPrice,
		'currency'=>$currency);
		$html = shopFunctionsF::renderVmSubLayout('methodprices', $input);

		return $html;

	}

	protected function getHtmlHeaderBE () {

		$class = "class='key'";
		$html = ' 	<thead>' . "\n"
			. '		<tr>' . "\n"
			. '			<th ' . $class . ' style="text-align: center;" colspan="2">' . vmText::_ ('COM_VIRTUEMART_ORDER_PRINT_' . strtoupper($this->_psType) . '_LBL') . '</th>' . "\n"
			. '		</tr>' . "\n"
			. '	</thead>' . "\n";

		return $html;
	}


	protected function getHtmlRow ($key, $value=null, $class = '') {

		$lang = vmLanguage::getLanguage ();
		$key_text = '';
		$complete_key = strtoupper ($this->_type . '_' . $key);

		if ($lang->hasKey($complete_key)) {
			$key_text = vmText::_ ($complete_key);
		} else {
			$key_text = vmText::_ ($key);
		}

		if(isset($value)){
			$more_key = strtoupper($complete_key . '_' . $value);
			if ($lang->hasKey ($more_key)) {
				$value .= " (" . vmText::_ ($more_key) . ")";
			}
			$html = "<tr>\n<td " . $class . ">" . $key_text . "</td>\n <td align='left'>" . $value . "</td>\n</tr>\n";
			return $html;
		} else {
			$html = "<tr>\n<td " . $class . " colspan='2'>" . $key_text . "</td>\n</tr>\n";
		}

	}

	function getHtmlRowBE ($key, $value=null) {
		return $this->getHtmlRow ($key, $value, "class='key'");
	}

	/**
	 * getSelectable
	 * This method returns the number of valid methods
	 *
	 * @param VirtueMartCart cart: the cart object
	 * @param $method_id eg $virtuemart_shipmentmethod_id
	 *
	 */
	function getSelectable (VirtueMartCart $cart, &$method_id, $cart_prices) {

		$nbMethod = 0;

		if ($this->getPluginMethods ($cart->vendorId) === 0) {
			return FALSE;
		}

		foreach ($this->methods as $k=>$method) {
			if ($nb = (int)$this->checkConditions ($cart, $method, $cart_prices)) {

				$nbMethod = $nbMethod + $nb;
				$idName = $this->_idName;
				$method_id = $method->{$idName};
			} else {
				unset($this->methods[$k]);
			}
		}
		return $nbMethod;
	}

	function getSelectables (VirtueMartCart $cart, &$method_ids, $cart_prices) {

		$nbMethod = 0;

		if ($this->getPluginMethods ($cart->vendorId) === 0) {
			return FALSE;
		}

		foreach ($this->methods as $k=>$method) {
			if ($nb = (int)$this->checkConditions ($cart, $method, $cart_prices)) {

				$nbMethod = $nbMethod + $nb;
				$idName = $this->_idName;
				$method_ids[] = $method->{$idName};
			} else {
				unset($this->methods[$k]);
			}
		}

		return $nbMethod;
	}

	/**
	 * If getSelectable or getSelectables was used, then methods may got removed. For some jobs, we need them back.
	 *
	 * @param $vendorId
	 */
	public function reloadPlugins($vendorId)
	{
		$this->methods = null;
		$this->getPluginMethods($vendorId);
	}

	/**
	 *
	 * Enter description here ...
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 * @param VirtueMartCart $cart
	 * @param int            $method
	 * @param array          $cart_prices
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {

		$address = $cart -> getST();

		if(!empty($method->byCoupon) ){
			if(empty($method->couponCode) and empty($cart->cartData['couponCode'])){
				return false;
			} else if(!empty($method->couponCode) and $method->couponCode!=$cart->cartData['couponCode']){
				return false;
			}
		}
		$this->convert_condition_amount($method);

		$this->convertToVendorCurrency($method);

		if(!empty($method->min_amount) or !empty($method->max_amount)){

			if(empty($method->min_amount)) $method->min_amount = 0.0;
			if(empty($method->max_amount)) $method->max_amount = 0.0;

			$forShipment = ($this->_psType == 'shipment')? true:false;

			$amount = $this->getCartAmount($cart_prices,$forShipment);
			$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND (empty($method->max_amount))));
			if(!$amount_cond) {
				vmdebug($this->_psType.'method '.$method->{$this->_psType.'_name'}.' = FALSE for cart amount '.$amount.'. NOT within Range of the condition from '.$method->min_amount.' to '.$method->max_amount);
				return false;
			}
		}

		$cat_cond = true;
		if( !empty($method->categories) or !empty($method->blocking_categories) ){
			if(!empty($method->categories)) $cat_cond = false;

			//if at least one product is  in a certain category, display this shipment
			if(!empty($method->categories) and !is_array($method->categories)) $method->categories = array($method->categories);
			if(!empty($method->blocking_categories) and !is_array($method->blocking_categories)) $method->blocking_categories = array($method->blocking_categories);

			$msg = 'None of the products is in a category of the method '.$method->{$this->_psType.'_name'};
			//Gather used cats
			foreach($cart->products as $product){
				if(empty($method->categories) or array_intersect($product->categories,$method->categories)){
					$cat_cond = true;
					//break;
				}
				if(!empty($method->blocking_categories) and array_intersect($product->categories,$method->blocking_categories)){
					$cat_cond = false;
					$msg = 'At least one of the products is in a category which blockes the method '.$method->{$this->_psType.'_name'};
					break;
				}
			}
			//if all products in a certain category, display the shipment
			//if a product has a certain category, DO NOT display the shipment
			if(!$cat_cond) {
				vmdebug($msg);
				return false;
			}
		}

		if (!empty($method->virtuemart_shipmentmethod_ids)) {

			if (!is_array($method->virtuemart_shipmentmethod_ids)) {
				$method->virtuemart_shipmentmethod_ids = array($method->virtuemart_shipmentmethod_ids);
			}
			vmdebug('Check for shipment methods ',$cart->virtuemart_shipmentmethod_id,$method->virtuemart_shipmentmethod_ids);
			if(empty($cart->virtuemart_shipmentmethod_id)){
				return false;
			} else {
				if(!in_array($cart->virtuemart_shipmentmethod_id,$method->virtuemart_shipmentmethod_ids)){
					vmdebug('Check for shipment method shipment method not allowed for paypal',$cart->virtuemart_shipmentmethod_id,$method->virtuemart_shipmentmethod_ids);
					return false;
				}
				vmdebug('Check for shipment method for '.$method->{$this->_psType.'_name'}.' PASSED');
			}

		}

		if (!empty($method->countries)){

			if (!isset($address['virtuemart_country_id'])) {
				$address['virtuemart_country_id'] = 0;
			}

			$countries = array();
			if (!empty($method->countries)) {
				if (!is_array ($method->countries)) {
					$countries[0] = $method->countries;
				} else {
					$countries = $method->countries;
				}
			}

			if (count ($countries) > 0 and !in_array ($address['virtuemart_country_id'], $countries)) {
				vmdebug($this->_psType.'method '.$method->{$this->_psType.'_name'}.' = FALSE for variable virtuemart_country_id = '.$address['virtuemart_country_id'].', Reason: Country '.implode(', ', $countries).' does not fit');
				return false;

			}
		}

		if (!empty($method->blocking_countries)){

			if (!isset($address['virtuemart_country_id'])) {
				$address['virtuemart_country_id'] = 0;
			}

			$blocking_countries = array();
			if (!empty($method->blocking_countries)) {
				if (!is_array ($method->blocking_countries)) {
					$blocking_countries[0] = $method->blocking_countries;
				} else {
					$blocking_countries = $method->blocking_countries;
				}
			}

			if (count ($blocking_countries) > 0 and in_array ($address['virtuemart_country_id'], $blocking_countries) ) {
				//vmdebug('checkConditions '.$method->shipment_name.' fit ',$weight_cond,(int)$zip_cond,$nbproducts_cond,$orderamount_cond);
				vmdebug($this->_psType.'method '.$method->{$this->_psType.'_name'}.' = FALSE for variable virtuemart_country_id = '.$address['virtuemart_country_id'].', Reason: Country '.implode(', ', $blocking_countries).' blocks');
				return false;
			}
			else{
				//vmdebug($this->_psType.'method '.$method->{$this->_psType.'_name'}.' = TRUE for variable virtuemart_country_id = '.$address['virtuemart_country_id'].', Reason: Countries in rule '.implode($countries,', ').' or none set');
				$country_cond = true;
			}
		}

		return true;
	}

  	/**
	 * For backward compatibilites reasons we keep this function (setDezimal should do it already)
	 * @param $method
	 */
	function convert_condition_amount (&$method) {
		if(!isset($method->min_amount)) $method->min_amount = 0.0;
		if(!isset($method->max_amount)) $method->max_amount = 0.0;
		$method->min_amount = (float)str_replace(',','.',$method->min_amount);
		$method->max_amount = (float)str_replace(',','.',$method->max_amount);
	}

	/**
	 * @param      $method
	 * @param bool $getCurrency
	 */
	static function getPaymentCurrency (&$method, $getCurrency = FALSE) {

		if (empty($method->payment_currency) or $getCurrency) {
			if(empty($method->currency_id)){
				$vendor_model = VmModel::getModel('vendor');
				$vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
				$method->payment_currency = $vendor->vendor_currency;
			} else {
				$currencyM = VmModel::getModel('currency');
				$currency = $currencyM->getCurrency($method->currency_id);
				$method->payment_currency = $currency->virtuemart_currency_id;
			}

			//vmdebug('getPaymentCurrency',$method->payment_currency);
		} elseif (isset($method->payment_currency) and $method->payment_currency== -1) {

			$vendor_model = VmModel::getModel('vendor');
			$vendor_currencies = $vendor_model->getVendorAndAcceptedCurrencies($method->virtuemart_vendor_id);

			$mainframe = JFactory::getApplication();
			$method->payment_currency = $mainframe->getUserStateFromRequest( "virtuemart_currency_id", 'virtuemart_currency_id',vRequest::getInt('virtuemart_currency_id', $vendor_currencies['vendor_currency']) );
		}

	}

	function getEmailCurrency (&$method) {

		if (!isset($method->email_currency)  or $method->email_currency=='vendor') {
			$vendor_model = VmModel::getModel('vendor');
			$vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
			return $vendor->vendor_currency;
		} else {
			return $method->payment_currency; // either the vendor currency, either same currency as payment
		}
	}

	/**
	 * displayTaxRule
	 *
	 * @param int $tax_id
	 * @return string $html:
	 */
	function displayTaxRule ($tax_id) {

		$html = '';
		$db = JFactory::getDBO ();
		if (!empty($tax_id)) {
			$q = 'SELECT * FROM #__virtuemart_calcs WHERE `virtuemart_calc_id`="' . (int)$tax_id . '" ';
			$db->setQuery ($q);
			$taxrule = $db->loadObject ();

			$lang = vmLanguage::getLanguage();
			$text = $lang->hasKey($taxrule->calc_name) ? '('.vmText::_($taxrule->calc_name).')' : $taxrule->calc_name;
			$html = $text . '(' . $taxrule->calc_kind . ':' . $taxrule->calc_value_mathop . $taxrule->calc_value . ')';
		}
		return $html;
	}

	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

		if(!isset($method->cost_percent_total)) $method->cost_percent_total = 0.0;
		if (preg_match ('/%$/', $method->cost_percent_total)) {
			$method->cost_percent_total = substr ($method->cost_percent_total, 0, -1);
		} else {
			if(empty($method->cost_percent_total)){
				$method->cost_percent_total = 0;
			}
		}
		$cartPrice = !empty($cart->cartPrices['withTax'])? $cart->cartPrices['withTax']:$cart->cartPrices['salesPrice'];

		if(empty($method->cost_per_transaction)) $method->cost_per_transaction = 0.0;
		if(empty($method->cost_percent_total)) $method->cost_percent_total = 0.0;

		$costs = $method->cost_per_transaction + $cartPrice * $method->cost_percent_total * 0.01;
		if(!empty($method->cost_min_transaction) and $method->cost_min_transaction!='' and $costs < $method->cost_min_transaction){
			return $method->cost_min_transaction;
		} else {
			return $costs;
		}
	}


	/**
	 * Get the cart amount for checking conditions if the payment conditions are fullfilled
	 * @param $cart_prices
	 * @return mixed
	 */
	function getCartAmount($cart_prices, $forShipment = false){
		if(empty($cart_prices['salesPrice'])) $cart_prices['salesPrice'] = 0.0;
		$cartPrice = !empty($cart_prices['withTax'])? $cart_prices['withTax']:$cart_prices['salesPrice'];
		if(empty($cart_prices['salesPriceShipment'])) $cart_prices['salesPriceShipment'] = 0.0;
		if(empty($cart_prices['salesPriceCoupon'])) $cart_prices['salesPriceCoupon'] = 0.0;
		if($forShipment){
			$amount = $cartPrice + $cart_prices['salesPriceCoupon'] ;
		} else {
			$amount = $cartPrice + $cart_prices['salesPriceShipment'] + $cart_prices['salesPriceCoupon'] ;
		}

		if ($amount <= 0) $amount=0;
		return $amount;

	}

	function convertToVendorCurrency(&$method){

		if($this->_toConvert){
			$idN = 'virtuemart_'.$this->_psType.'method_id';

			if(!isset($method->converted)){

				$calculator = calculationHelper::getInstance ();
				foreach($this->_toConvert as $c){
					if(!empty($method->{$c})){
						$method->{$c} = $calculator->_currencyDisplay->convertCurrencyTo($method->currency_id,$method->{$c},true);
					} else {
						$method->{$c} = 0.0;
					}
				}
				$method->converted = 1;

			}
		}

	}

	/**
	 * @param VirtueMartCart $cart
	 * @param $cart_prices
	 * @param $method
	 * @param bool $progressive
	 * @return mixed
	 */

	function setCartPrices (VirtueMartCart $cart, &$cart_prices, $method, $progressive = true) {

		$calculator = calculationHelper::getInstance ();

		$_psType = ucfirst ($this->_psType);
		if($this->_toConvert){
			$this->convertToVendorCurrency($method);
		}
		$cart_prices[$this->_psType . 'Value'] = $calculator->roundInternal ($this->getCosts ($cart, $method, $cart_prices), 'salesPrice');
		if(!isset($cart_prices[$this->_psType . 'Value'])) $cart_prices[$this->_psType . 'Value'] = 0.0;
		if(!isset($cart_prices[$this->_psType . 'Tax'])) $cart_prices[$this->_psType . 'Tax'] = 0.0;

		if($this->_psType=='payment'){
			$cartTotalAmountOrig = $this->getCartAmount($cart_prices);

			if(empty($method->cost_percent_total)) $method->cost_percent_total = 0.0;
			if(empty($method->cost_per_transaction)) $method->cost_per_transaction = 0.0;

			$progressive = isset($method->progressive)? $method->progressive: $progressive;
			if(!$progressive){
				//Simple
				$cartTotalAmount=($cartTotalAmountOrig + $method->cost_per_transaction) * (1 +($method->cost_percent_total * 0.01));
				//vmdebug('Simple $cartTotalAmount = ('.$cartTotalAmountOrig.' + '.$method->cost_per_transaction.') * (1 + ('.$method->cost_percent_total.' * 0.01)) = '.$cartTotalAmount );
				//vmdebug('Simple $cartTotalAmount = '.($cartTotalAmountOrig + $method->cost_per_transaction).' * '. (1 + $method->cost_percent_total * 0.01) .' = '.$cartTotalAmount );
			} else {
				//progressive
				$cartTotalAmount = ($cartTotalAmountOrig + $method->cost_per_transaction) / (1 -($method->cost_percent_total * 0.01));
				//vmdebug('Progressive $cartTotalAmount = ('.$cartTotalAmountOrig.' + '.$method->cost_per_transaction.') / (1 - ('.$method->cost_percent_total.' * 0.01)) = '.$cartTotalAmount );
				//vmdebug('Progressive $cartTotalAmount = '.($cartTotalAmountOrig + $method->cost_per_transaction) .' / '. (1 - $method->cost_percent_total * 0.01) .' = '.$cartTotalAmount );
			}

			$cart_prices[$this->_psType . 'Value'] = $cartTotalAmount - $cartTotalAmountOrig;
			if(!empty($method->cost_min_transaction) and $method->cost_min_transaction!='' and $cart_prices[$this->_psType . 'Value'] < $method->cost_min_transaction){
				$cart_prices[$this->_psType . 'Value'] = $method->cost_min_transaction;

			}
		}

		if(!isset($cart_prices['salesPrice' . $_psType])) $cart_prices['salesPrice' . $_psType] = $cart_prices[$this->_psType . 'Value'];

		$taxrules = array();
		if(isset($method->tax_id) and (int)$method->tax_id === -1){

		} else if (!empty($method->tax_id)) {
			$cart_prices[$this->_psType . '_calc_id'] = $method->tax_id;

			$db = JFactory::getDBO ();
			$q = 'SELECT * FROM #__virtuemart_calcs WHERE `virtuemart_calc_id`="' . $method->tax_id . '" ';
			$db->setQuery ($q);
			$taxrules = $db->loadAssocList ();

			if(!empty($taxrules) ){
				foreach($taxrules as &$rule){
					if(!isset($rule['subTotal'])) $rule['subTotal'] = 0;
					if(!isset($rule['taxAmount'])) $rule['taxAmount'] = 0;
					$rule['subTotalOld'] = $rule['subTotal'];
					$rule['taxAmountOld'] = $rule['taxAmount'];
					$rule['taxAmount'] = 0;
					$rule['subTotal'] = $cart_prices[$this->_psType . 'Value'];
					$rule['psType'] = $this->_psType;
					$cart_prices[$this->_psType . 'TaxPerID'][$rule['virtuemart_calc_id']] = $calculator->roundInternal($calculator->roundInternal($calculator->interpreteMathOp($rule, $rule['subTotal'])) - $rule['subTotal'], 'salesPrice');
					$cart_prices[$this->_psType . 'Tax'] += $cart_prices[$this->_psType . 'TaxPerID'][$rule['virtuemart_calc_id']];
				}
			}
		} else {

			if ( !isset($cart->cartData['taxRulesBill']) ) {
				$cart->cartData['taxRulesBill'] = array();
			}
			if ( !isset($cart->cartData['DBTaxRulesBill']) ) {
				$cart->cartData['DBTaxRulesBill'] = array();
			}
			if ( !isset($cart->cartData['VatTax']) ) {
				$cart->cartData['VatTax'] = array();
			}
			// end code addition
			$taxrules = array_merge($cart->cartData['VatTax'],$cart->cartData['taxRulesBill']);
			//$taxrules = $cart->cartData['VatTax'];	//calculationh. around line 901, disabled
			if(!isset($cart->cartPrices['salesPrice'])) $cart->cartPrices['salesPrice'] = 0.0;
			$cartdiscountBeforeTax = $calculator->roundInternal($calculator->cartRuleCalculation($cart->cartData['DBTaxRulesBill'], $cart->cartPrices['salesPrice']));

			if(!empty($taxrules) ){

				$idWithMax = 0;
				$maxValue = 0.0;
				foreach($taxrules as $i=>$rule){
					//Quickn dirty
					if(!isset($rule['calc_kind'])) $rule = (array)VmModel::getModel('calc')->getCalc($rule['virtuemart_calc_id']);

					if(!isset($rule['subTotal'])) $rule['subTotal'] = 0;
					if(!isset($rule['taxAmount'])) $rule['taxAmount'] = 0;
					if(!isset($rule['DBTax'])) $rule['DBTax'] = 0;
					if(VmConfig::get('radicalShipPaymentVat',true)){
						if(empty($idWithMax) or $maxValue<=$rule['subTotal']){
							$idWithMax = $rule['virtuemart_calc_id'];
							$maxValue = $rule['subTotal'];
						}
						$rule['percentage'] = 0;
					} else {
						if(!isset($rule['percentage']) && $rule['subTotal'] < $cart->cartPrices['salesPrice']) {
							$rule['percentage'] = ($rule['subTotal'] + $rule['DBTax']) / ($cart->cartPrices['salesPrice'] + $cartdiscountBeforeTax);
						} else if(!isset($rule['percentage'])) {
							$rule['percentage'] = 1;
						}
					}
//vmdebug('My TaxPerID '.$rule['subTotal'] .' + '.$rule['DBTax'].' / '.$cart->cartPrices['salesPrice'].' + '.$cartdiscountBeforeTax,$rule['percentage']);
					$rule['subTotalOld'] = $rule['subTotal'];
					$rule['subTotal'] = 0;
					$rule['taxAmountOld'] = $rule['taxAmount'];
					$rule['taxAmount'] = 0;
					$taxrules[$i] = $rule;
				}

				foreach($taxrules as $i=>$rule){

					if(VmConfig::get('radicalShipPaymentVat',true) and $idWithMax == $rule['virtuemart_calc_id']) {
						$rule['percentage'] = 1.0;
						//vmdebug('setCartPrices 100% '.$this->_psType,$idWithMax);
					}

					$rule['subTotal'] = $cart_prices[$this->_psType . 'Value'] * $rule['percentage'];
					$rule['psType'] = $this->_psType;
					$taxrules[$i] = $rule;

					if(!isset($cart_prices[$this->_psType . 'Tax'])) $cart_prices[$this->_psType . 'Tax'] = 0.0;

					$cart_prices[$this->_psType . 'TaxPerID'][$rule['virtuemart_calc_id']] = $calculator->roundInternal($calculator->roundInternal($calculator->interpreteMathOp($rule, $rule['subTotal'])) - $rule['subTotal'], 'salesPrice');


					$cart_prices[$this->_psType.'Tax'] += $cart_prices[$this->_psType.'TaxPerID'][$rule['virtuemart_calc_id']];

				}
			}
		}

		if(empty($method->cost_per_transaction)) $method->cost_per_transaction = 0.0;
		if(empty($method->cost_min_transaction)) $method->cost_min_transaction = 0.0;
		if(empty($method->cost_percent_total)) $method->cost_percent_total = 0.0;

		if (count ($taxrules) > 0 ) {

			$cart_prices['salesPrice' . $_psType] = $calculator->roundInternal ($calculator->executeCalculation ($taxrules, $cart_prices[$this->_psType . 'Value'],true,false), 'salesPrice');
//			$cart_prices[$this->_psType . 'Tax'] = $calculator->roundInternal (($cart_prices['salesPrice' . $_psType] -  $cart_prices[$this->_psType . 'Value']), 'salesPrice');
			reset($taxrules);

			foreach($taxrules as &$rule){
				if(!isset($cart_prices[$this->_psType . '_calc_id']) or !is_array($cart_prices[$this->_psType . '_calc_id'])) $cart_prices[$this->_psType . '_calc_id'] = array();
				$cart_prices[$this->_psType . '_calc_id'][] = $rule['virtuemart_calc_id'];

				if(isset($rule['subTotalOld'])) $rule['subTotal'] += $rule['subTotalOld'];
				if(isset($rule['taxAmountOld'])) $rule['taxAmount'] += $rule['taxAmountOld'];
				if(isset($rule['psType'])) unset($rule['psType']);
			}

		} else {

			$cart_prices['salesPrice' . $_psType] = $cart_prices[$this->_psType . 'Value'];
			$cart_prices[$this->_psType . 'Tax'] = 0;
			$cart_prices[$this->_psType . '_calc_id'] = 0;
		}

		return $cart_prices['salesPrice' . $_psType];

	}

	/**
	 * calculateSalesPrice
	 *
	 * @param $value
	 * @param $tax_id: tax id
	 * @return $salesPrice
	 */
	protected function calculateSalesPrice ($cart, $method, $cart_prices) {

		return $this -> setCartPrices($cart,$cart_prices,$method);
	}

	/**
	 * setInConfirmOrder
	 * In VM 2.6.7, we introduced a double order checking.
	 * Now plugin itself can define if it should be possible to use the same trigger more than one time.
	 * this should be done at the begin of the trigger
	 * @author Valérie Isaksen
	 * @param $cart
	 */
	function setInConfirmOrder($cart) {
		$cart->_inConfirm = true;
		$cart->setCartIntoSession(false,true);
	}


	public function processConfirmedOrderPaymentResponse ($returnValue, $cart, $order, $html, $payment_name, $new_status = '') {

		if ($returnValue == 1) {
			//We delete the old stuff
			// send the email only if payment has been accepted
			// update status

			$modelOrder = VmModel::getModel ('orders');
			$order['order_status'] = $new_status;
			$order['customer_notified'] = 1;
			$order['comments'] = '';
			$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

			$order['paymentName'] = $payment_name;
			//shopFunctionsF::sentOrderConfirmedEmail($order);
			//We delete the old stuff
			$cart->emptyCart ();
			//vRequest::setVar ('html', $html);
			$cart->orderdoneHtml = $html;
			// payment echos form, but cart should not be emptied, data is valid
		} elseif ($returnValue == 2) {
			$cart->_confirmDone = false;
			$cart->_dataValidated = false;
			$cart->_inConfirm = false;
			//vRequest::setVar ('html', $html);
			$cart->orderdoneHtml = $html;
			$cart->setCartIntoSession (false,true);
		} elseif ($returnValue == 0) {
			// error while processing the payment
			$mainframe = JFactory::getApplication ();
			$mainframe->enqueueMessage ($html);
			$msg = vmText::_('COM_VIRTUEMART_CART_ORDERDONE_DATA_NOT_VALID').' '.$payment_name.' '.$order['details']['BT']->virtuemart_order_id;
			vmError($msg,'COM_VIRTUEMART_CART_ORDERDONE_DATA_NOT_VALID');
			$mainframe->redirect (JRoute::_ ('index.php?option=com_virtuemart&view=cart',FALSE));
		}
	}

	/**
	 * @param $amount
	 * @param $currencyId
	 * @return array
	 */
	static function getAmountInCurrency($amount, $currencyId){
		$return = array();

		$paymentCurrency = CurrencyDisplay::getInstance($currencyId);

		$return['value'] = $paymentCurrency->roundForDisplay($amount,$currencyId,1.0,false,2);
		$return['display'] = $paymentCurrency->getFormattedCurrency($return['value']) ;
		return $return;
	}

	/**
	 * @param $amount
	 * @param $currencyId
	 * @return number
	 */
	static function getAmountValueInCurrency($amount, $currencyId){
		$return= vmPSPlugin::getAmountInCurrency($amount, $currencyId);
		return $return['value'];
	}

	function emptyCart ($session_id = NULL, $order_number = NULL) {

		$cart = VirtueMartCart::getCart ();
		$cart->emptyCart ();
		return TRUE;
	}

	/**
	 * @deprecated
	 * recovers the session from Storage, and only empty the cart if it has not been done already
	 */

	function emptyCartFromStorageSession ($session_id, $order_number) {

		$conf = JFactory::getConfig ();
		$handler = $conf->get ('session_handler', 'none');

		$config['session_name'] = 'site';
		$name = vRequest::getHash ($config['session_name']);
		$options['name'] = $name;
		$sessionStorage = JSessionStorage::getInstance ($handler, $options);

		// The session store MUST be registered.
		$sessionStorage->register ();

		// reads directly the session from the storage
		$sessionStored = $sessionStorage->read ($session_id);
		if (empty($sessionStored)) {
			return;
		}
		$sess2store = $this->_emptyCartFromStorageSession($sessionStored);
		if(!empty($sess2store)){
			$sessionStorage->write ($session_id, $sess2store);
		}

	}

	function _emptyCartFromStorageSession ($data) {

		if(strlen($data)<8) return false;
		$t = substr($data,7);
		if(empty($t)) return false;

		$unserb64enc = unserialize($t);

		if(!empty($unserb64enc)){
			$unser = base64_decode($unserb64enc);
			$unsunser = unserialize($unser);
			if($unsunser===FALSE){
				$m = 'Unserialize failed';
				vmError($m,$m);
				return false;
			} else {
				$cl = strtolower(get_class($unsunser));
				if(JVM_VERSION<3){
					$cld = 'jregistry';
				} else {
					$cld = 'joomla\registry\registry';
				}

				if($cl != $cld){
					$m = 'Wrong class in Session, check your installation, update your joomla
					 '.$cl;
					vmError($m, $m);
					return false;
				}
			}

			$vmObj = $unsunser->get('__vm',false);
			$tcart = vmJsApi::safe_json_decode($vmObj->vmcart, false);
			if(empty($tcart) or json_last_error()!=JSON_ERROR_NONE){
				return false;
			}

			if($tcart->virtuemart_cart_id){
				$model = new VmModel();
				$carts = $model->getTable('carts');
				if(!empty($tcart->virtuemart_cart_id)){
					$carts->delete($tcart->virtuemart_cart_id,'virtuemart_cart_id');
				}
			}
			VirtuemartCart::emptyCartValues($tcart,false);

			$vmObj->vmcart = vmJsApi::safe_json_encode($tcart);
			$unsunser->set('__vm',$vmObj);

			$runser = serialize($unsunser);
			$runserb64enc = base64_encode($runser);
			$rt = serialize($runserb64enc);
			return 'joomla|'.$rt;
		}

	}


	private static function session_decode ($session_data) {

		$decoded_session = array();
		$offset = 0;

		while ($offset < strlen ($session_data)) {
			if (!strstr (substr ($session_data, $offset), "|")) {
				return array();
			}
			$pos = strpos ($session_data, "|", $offset);
			$num = $pos - $offset;
			$varname = substr ($session_data, $offset, $num);
			$offset += $num + 1;

			$value = substr ($session_data, $offset);

			if(!empty($value) && !is_int($value)){
				$data = unserialize($value);
			}
			$decoded_session[$varname] = $data;
			$offset += strlen (serialize($data));
		}
		return $decoded_session;
	}


	private static function session_encode ($session_data_array) {

		$encoded_session = "";
		foreach ($session_data_array as $key => $session_data) {
			$encoded_session .= $key . "|" . serialize ($session_data);
		}
		return $encoded_session;
	}


	/**
	 *  @param integer $virtuemart_order_id the id of the order
	 */
	function handlePaymentUserCancel ($virtuemart_order_id) {

		if ($virtuemart_order_id) {
			// set the order to cancel , to handle the stock correctly
			$modelOrder = VmModel::getModel ('orders');
			$order['order_status'] = 'P';   //There is no reason to X the order. P should be better.
			$order['virtuemart_order_id'] = $virtuemart_order_id;
			$order['customer_notified'] = 0;
			$order['comments'] = vmText::_ ('COM_VIRTUEMART_PAYMENT_CANCELLED_BY_SHOPPER').' '.$this->_name;
			$modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);
			//$modelOrder->remove (array('virtuemart_order_id' => $virtuemart_order_id));
		}
	}

	function getCartMenuItemid($layout=0){

		vmrouterHelper::getInstance(self::$request);
		$menu = vmrouterHelper::setMenuItemId();
		$m= null;
		if(isset($menu['cart'])){
			foreach($menu[self::$request['view']] as $mLayout=>$menuItem){
				if(!empty($mLayout) and $mLayout == $layout){
					return $menuItem;
				}
				$m = $menuItem;
			}
		}
		return $m;
	}

	/**
	 * logInfo
	 * to help debugging Payment notification for example
	 * Keep it for compatibilty
	 */
	protected function logInfo ($text, $type = 'message', $doLog=false) {
		if (!class_exists( 'VmConfig' )) require(JPATH_ROOT .'/administrator/components/com_virtuemart/helpers/config.php');
		VmConfig::loadConfig();
		if ((isset($this->_debug) and $this->_debug) OR $doLog) {
			$oldLogFileName = vmEcho::$logFileName;
			vmEcho::$logFileName = $this->getLogFileName() ;
			logInfo($text, $type);
			vmEcho::$logFileName = $oldLogFileName;
		}
	}

	/**
	 *
	 */
	function getLogFileName() {
		$name=$this->_idName;
		$methodId=0;
		if (isset ($this->_currentMethod) ) {
			$methodId=$this->_currentMethod->{$name};
		}

		return $this->_name. '.'.$methodId ;
	}

	/**
	 * log all messages of type ERROR
	 * log in case the debug option is on, and the log option is on
	* @param string $message the message to write
	* @param string $title
	* @param string $type message, deb-ug,  info, error
	* @param boolean $doDebug in payment notification, we don't want to use vmdebug even if the debug option  is on
	 *
	 */
	public function debugLog($message, $title='', $type = 'message', $doDebug=true) {

		if ( isset($this->_currentMethod) and !$this->_currentMethod->log and $type !='error') {
			//Do not log message messages if we are not in LOG mode
			return;
		}

		if ( $type == 'error') {
			$this->sendEmailToVendorAndAdmins();
		}

		$this->logInfo($title.': '.print_r($message,true), $type, true);
	}


}

