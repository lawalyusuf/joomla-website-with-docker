<?php

/**
 *
 * Product details view
 *
 * @package VirtueMart
 * @subpackage
 * @author RolandD
 * @link https://virtuemart.net
 * @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: view.html.php 2796 2011-03-01 11:29:16Z Milbo $
 */
// Check to ensure this file is included in Joomla!
defined ('_JEXEC') or die('Restricted access');


/**
 * Product details
 *
 * @package VirtueMart
 * @author Max Milbers
 */
class VirtueMartViewAskquestion extends VmView {

	/**
	 * Collect all data to show on the template
	 *
	 * @author Max Milbers
	 */
	function display ($tpl = NULL) {

		$app = JFactory::getApplication();
		if(!VmConfig::get('ask_question',false) and !VmConfig::get('askprice',false)){
			vmWarn('Disabled function');
			$app->redirect(JRoute::_('index.php?option=com_virtuemart'));
		}

		$this->login = '';
		$this->user = JFactory::getUser ();
		$recAuthConfig = VmConfig::get('recommend_unauth','0');

		if($recAuthConfig == '0'){
			if($this->user->guest){
				VmInfo('JGLOBAL_YOU_MUST_LOGIN_FIRST');
				$this->login = shopFunctionsF::getLoginForm(false);
			}
		} else if(VmConfig::get('recommend_unauth','customers')=="customers"){
			if($this->user->guest){
				VmInfo('JGLOBAL_YOU_MUST_LOGIN_FIRST');
				$this->login = shopFunctionsF::getLoginForm(false);
			} else {
				JLoader::register('virtuemartViewrecommend', VMPATH_SITE.'/views/recommend/view.html.php');
				$recAuth = virtuemartViewrecommend::checkRecommendAuth($this->user);
				if(!$recAuth){
					//$this->login = shopFunctionsF::getLoginForm(false);
					return;
				}
			}
		}

		$this->show_prices = (int)VmConfig::get ('show_prices', 1);

		$document = JFactory::getDocument ();

		$mainframe = JFactory::getApplication ();
		$pathway = $mainframe->getPathway ();
		$task = vRequest::getCmd ('task');

		// Load the product
		$product_model = VmModel::getModel ('product');
		$category_model = VmModel::getModel ('Category');

		$virtuemart_product_idArray = vRequest::getInt ('virtuemart_product_id', 0);
		if (is_array ($virtuemart_product_idArray)) {
			$virtuemart_product_id = $virtuemart_product_idArray[0];
		} else {
			$virtuemart_product_id = $virtuemart_product_idArray;
		}

		if (empty($virtuemart_product_id)) {
			self::showLastCategory ($tpl);
			return;
		}

		$product = $product_model->getProduct ($virtuemart_product_id);

		// Set Canonic link
		$format = vRequest::getCmd('format', 'html');
		if ($format == 'html') {
			$document->addHeadLink (JUri::getInstance()->toString(array('scheme', 'host', 'port')).JRoute::_($product->canonical, FALSE), 'canonical', 'rel', '');
		}

		// Set the titles
		$document->setTitle (vmText::sprintf ('COM_VIRTUEMART_PRODUCT_DETAILS_TITLE', $product->product_name . ' - ' . vmText::_ ('COM_VIRTUEMART_PRODUCT_ASK_QUESTION')));

		$this->assignRef ('product', $product);

		if (empty($product)) {
			self::showLastCategory ($tpl);
			return;
		}

		$product_model->addImages ($product, 1);

		// Get the category ID
		$virtuemart_category_id = vRequest::getInt ('virtuemart_category_id');
		if ($virtuemart_category_id == 0 && !empty($product)) {
			if (array_key_exists ('0', $product->categories)) {
				$virtuemart_category_id = $product->categories[0];
			}
		}

		shopFunctionsF::setLastVisitedCategoryId ($virtuemart_category_id);

		if ($category_model) {
			$category = $category_model->getCategory ($virtuemart_category_id);
			$this->assignRef ('category', $category);
			$pathway->addItem (vmText::_($category->category_name), JRoute::_ ('index.php?option=com_virtuemart&view=category&virtuemart_category_id=' . $virtuemart_category_id, FALSE));
		}

		$pathway->addItem ($product->product_name, JRoute::_ ('index.php?option=com_virtuemart&view=productdetails&virtuemart_category_id=' . $virtuemart_category_id . '&virtuemart_product_id=' . $product->virtuemart_product_id, FALSE));

		// for askquestion
		$pathway->addItem (vmText::_ ('COM_VIRTUEMART_PRODUCT_ASK_QUESTION'));


		if ($product->metadesc) {
			$document->setDescription ($product->metadesc);
		}
		if ($product->metakey) {
			$document->setMetaData ('keywords', $product->metakey);
		}

		//We never want that ask a question is indexed
		$document->setMetaData('robots','NOINDEX, NOFOLLOW, NOARCHIVE, NOSNIPPET');

		if ($mainframe->getCfg ('MetaTitle') == '1') {
			$document->setMetaData ('title', $product->product_s_desc); //Maybe better product_name
		}
		if ($mainframe->getCfg ('MetaAuthor') == '1') {
			$document->setMetaData ('author', $product->metaauthor);
		}

		$this->captcha = shopFunctionsF::renderCaptcha('ask_captcha');

		parent::display ($tpl);
	}

	function renderMailLayout () {

		$this->setLayout ('mail_html_question');
		$this->comment = vRequest::getString ('comment');

		$this->user = JFactory::getUser ();
		if (empty($this->user->id)) {
			$fromMail = vRequest::getEmail ('email'); //is sanitized then
			$fromName = vRequest::getVar ('name', ''); //is sanitized then
			//$fromMail = str_replace (array('\'', '"', ',', '%', '*', '/', '\\', '?', '^', '`', '{', '}', '|', '~'), array(''), $fromMail);
			$fromName = str_replace (array('\'', '"', ',', '%', '*', '/', '\\', '?', '^', '`', '{', '}', '|', '~'), array(''), $fromName);
			$this->user->email = $fromMail;
			$this->user->name = $fromName;
		}

		$virtuemart_product_id = vRequest::getInt ('virtuemart_product_id', 0);

		$productModel = VmModel::getModel ('product');
		if(empty($this->product)){
			$this->product =  $productModel->getProduct ($virtuemart_product_id);
		}
		$productModel->addImages($this->product);

		$this->subject = vmText::_ ('COM_VIRTUEMART_QUESTION_ABOUT') . $this->product->product_name;

		$vendorModel = VmModel::getModel ('vendor');

		$this->vendor = $vendorModel->getVendor ($this->product->virtuemart_vendor_id);
		//$this->vendor->vendor_store_name = $fromName;

		$vendorModel->addImages ($this->vendor);

		$this->vendorEmail = $vendorModel->getVendorEmail($this->vendor->virtuemart_vendor_id);

		// in this particular case, overwrite the value for fix the recipient name
		$this->vendor->vendor_name = $this->user->get('name');

		if (VmConfig::get ('order_mail_html')) {
			$tpl = 'mail_html_question';
		} else {
			$tpl = 'mail_raw_question';
		}
		$this->setLayout ($tpl);
		$this->isMail = true;
		parent::display ();
	}

	public function showLastCategory ($tpl) {
		$this->prepareContinueLink();
		parent::display ($tpl);
	}


}

// pure php no closing tag