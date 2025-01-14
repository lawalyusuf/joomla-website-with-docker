<?php

/**
 *
 * @package	VirtueMart
 * @subpackage   Models Fields
 * @author Max Milbers
 * @link https://virtuemart.net
 * @copyright Copyright (c) 2004 - 2011 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: $
 */
defined('JPATH_BASE') or die;
jimport('joomla.form.formfield');

/**
 * Supports a modal product picker.
 *
 *
 */
class JFormFieldVmLayout extends JFormField
{
	var $type = 'layout';

	/**
	 * Method to get the field input markup. Use as name the view of the desired layout list + "layout".
	 * For example <field name="categorylayout" for all layouts of hte category view.
	 *
     * @author   Max Milbers
	 * @return	string	The field input markup.
	 * @since	2.0.24a
	 */
  
	function getInput() {

		if (!class_exists( 'VmConfig' )) require(JPATH_ROOT .'/administrator/components/com_virtuemart/helpers/config.php');
		VmConfig::loadConfig();

		vmLanguage::loadJLang('com_virtuemart');

		$this->view = $this->getAttribute('view',false);

		if(empty($this->view)){
			$view = substr($this->fieldname,0,-6);;
		} else {
			$view = $this->view;
		}
		$gl = $this->getAttribute('allowGlobal',true);

		$vmLayoutList = VirtueMartModelConfig::getLayoutList($view,0,$gl);
		$html = JHtml::_('select.genericlist',$vmLayoutList, $this->name, 'class="form-select"', 'value', 'text', array($this->value));

        return $html;

    }

}