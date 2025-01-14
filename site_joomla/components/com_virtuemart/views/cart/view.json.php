<?php

/**
 *
 * View for the shopping cart
 *
 * @package	VirtueMart
 * @subpackage
 * @author Max Milbers
 * @link https://virtuemart.net
 * @copyright Copyright (c) 2013 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: view.html.php 6292 2012-07-20 12:27:44Z alatak $
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

/**
 * View for the shopping cart
 * @package VirtueMart
 * @author Max Milbers
 */
class VirtueMartViewCart extends VmView {

	public function display($tpl = null) {

		$layoutName = $this->getBaseLayout();
		if (!$layoutName) $layoutName = vRequest::getCmd('layout', 'default');
		$this->assignRef('layoutName', $layoutName);

		$this->cart = VirtueMartCart::getCart();

		if (isset($this->products) and is_array($this->products) and count($this->products)>0 ) {
			$this->prepareContinueLink(reset($this->products));
		} else {
			$this->prepareContinueLink();
		}

		VmTemplate::setVmTemplate($this, 0, 0, $layoutName);

		parent::display($tpl);
	}


}

//no closing tag
