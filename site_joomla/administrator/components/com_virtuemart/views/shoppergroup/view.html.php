<?php
/**
 *
 * Shopper group View
 *
 * @package	VirtueMart
 * @subpackage ShopperGroup
 * @author Markus �hler
 * @link https://virtuemart.net
 * @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: view.html.php 10975 2024-03-18 07:41:14Z  $
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

/**
 * HTML View class for maintaining the list of shopper groups
 *
 * @package	VirtueMart
 * @subpackage ShopperGroup
 * @author Markus �hler
 */
class VirtuemartViewShopperGroup extends VmViewAdmin {

	function display($tpl = null) {

		$model = VmModel::getModel();

		$layoutName = $this->getLayout();

		$task = vRequest::getCmd('task',$layoutName);
		$this->assignRef('task', $task);

		if ($layoutName == 'edit') {
			//For shoppergroup specific price display
			vmLanguage::loadJLang('com_virtuemart_config');
			vmLanguage::loadJLang('com_virtuemart_shoppers',true);
			$this->shoppergroup = $model->getShopperGroup(vRequest::getInt('virtuemart_shoppergroup_id',0));

			$this->SetViewTitle('SHOPPERGROUP',$this->shoppergroup->shopper_group_name);

			if($this->showVendors()){
				$this->vendorList = ShopFunctions::renderVendorList($this->shoppergroup->virtuemart_vendor_id);
			}

			$this->addStandardEditViewCommands();
			$this->shopgrp_price = false;
		} else {
			$this->SetViewTitle();

			$showVendors = $this->showVendors();
			$this->assignRef('showVendors',$showVendors);

			$this->addStandardDefaultViewCommands();
			$this->addStandardDefaultViewLists($model);

			$shoppergroups = $model->getShopperGroups(false, true);
			$this->assignRef('shoppergroups',	$shoppergroups);

			$pagination = $model->getPagination();
			$this->assignRef('sgrppagination', $pagination);

		}
		parent::display($tpl);
	}

} // pure php no closing tag
