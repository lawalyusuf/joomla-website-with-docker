<?php
/**
 *
 * Description
 *
 * @package    VirtueMart
 * @subpackage Userfields
 * @author Oscar van Eijk
 * @link https://virtuemart.net
 * @copyright Copyright (c) 2004 - Copyright (C) 2004 - 2022 Virtuemart Team. All rights reserved. VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: edit.php 10985 2024-03-18 09:32:39Z  $
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

vmJsApi::JvalideForm();
$adminTemplate = VMPATH_ROOT . '/administrator/templates/vmadmin/html/com_virtuemart/';
JLoader::register('vmuikitAdminUIHelper', $adminTemplate . 'helpers/vmuikit_adminuihelper.php');

vmuikitAdminUIHelper::startAdminArea($this);
vmuikitAdminUIHelper::imitateTabs('start', 'COM_VIRTUEMART_USERFIELD_DETAILS');

?>
<div class="uk-card   uk-card-small uk-card-vm">
	<div class="uk-card-header">
		<div class="uk-card-title">
						<span class="md-color-cyan-600 uk-margin-small-right"
								uk-icon="icon: settings; ratio: 1.2"></span>
			<?php echo vmText::_('COM_VIRTUEMART_USERFIELD_DETAILS'); ?>
		</div>
	</div>
	<div class="uk-card-body">
		<form action="index.php" method="post" name="adminForm" id="adminForm" class="uk-form-horizontal">


			<table class="uk-table  uk-table-small uk-table-responsive">
				<?php echo VmHTML::row('raw', 'COM_VIRTUEMART_FIELDMANAGER_TYPE', $this->lists['type']); ?>

				<!-- Start Type specific attributes -->
				<tr>
					<td colspan="2" style="text-align:left;overflow: auto;" id="toggler">
						<div id="divText">
							<fieldset>
								<legend><?php echo vmText::_('COM_VIRTUEMART_TEXTFIELD_ATTRIBUTES'); ?></legend>
								<table class="admintable">
									<?php echo VmHTML::row('input', 'COM_VIRTUEMART_USERFIELDS_MAXLENGTH', 'maxlength', $this->userField->maxlength, 'class="inputbox"', '', 5); ?>
								</table>
							</fieldset>
						</div>

						<div id="divColsRows">
							<fieldset>
								<legend><?php echo vmText::_('COM_VIRTUEMART_COL_ROWS_ATTRIBUTES'); ?></legend>
								<table class="admintable">
									<?php echo VmHTML::row('input', 'COM_VIRTUEMART_USERFIELDS_COLUMNS', 'cols', $this->userField->cols, 'class="inputbox"', '', 5); ?>
									<?php echo VmHTML::row('input', 'COM_VIRTUEMART_USERFIELDS_ROWS', 'rows', $this->userField->rows, 'class="inputbox"', '', 5); ?>
								</table>
							</fieldset>
						</div>

						<!--div id="divAgeVerification" style="text-align:left;">
					<fieldset>
					<legend><?php /*echo vmText::_('COM_VIRTUEMART_FIELDS_AGEVERIFICATION_ATTRIBUTES'); ?></legend>
						<table class="admintable">
							<?php echo VmHTML::row('raw','COM_VIRTUEMART_FIELDS_AGEVERIFICATION_MINIMUM', $this->lists['minimum_age'] );*/ ?>
						</table>
					</fieldset>
				</div-->

						<div id="divWeb">
							<fieldset>
								<legend><?php echo vmText::_('COM_VIRTUEMART_FIELDS_WEBADDRESS'); ?></legend>
								<table class="admintable">
									<?php echo VmHTML::row('raw', 'COM_VIRTUEMART_FIELDMANAGER_TYPE', $this->lists['webaddresstypes']); ?>
								</table>
							</fieldset>
						</div>

						<div id="divValues" style="text-align:left;">
							<fieldset>
								<legend><?php echo vmText::_('COM_VIRTUEMART_USERFIELDS_ADDVALUES_TIP'); ?></legend>
								<table align=left id="divFieldValues" cellpadding="4" cellspacing="1" border="0"
										width="100%" class="admintable">
									<thead>
									<tr>
										<th class="title"
												width="20%"><?php echo vmText::_('COM_VIRTUEMART_VALUE') ?></th>
										<th class="title"
												width="60%"><?php echo vmText::_('COM_VIRTUEMART_TITLE') ?></th>
									</tr>
									</thead>
									<tbody id="fieldValuesBody"><?php echo $this->lists['userfield_values']; ?></tbody>
								</table>
								<input type="button" class="button insertRow"
										value="<?php echo vmText::_('COM_VIRTUEMART_USERFIELDS_ADDVALUE') ?>"/>
							</fieldset>
						</div>
						<div id="divPlugin" style="text-align:left;">
							<fieldset>
								<legend><?php echo vmText::_('COM_VIRTUEMART_USERFIELDS_PLUGIN_TIP'); ?></legend>
								<div id="fieldPluginBody">
									<?php echo $this->userFieldPlugin; ?>
								</div>
							</fieldset>
						</div>
					</td>
				</tr>
				<!-- End Type specific attributes -->

				<tr>
					<td width="110" class="key">
						<label for="name">
							<?php echo vmText::_('COM_VIRTUEMART_FIELDMANAGER_NAME') ?>
						</label>
					</td>
					<td>
						<input type="text" name="name" id="name" size="50" value="<?php
						echo $this->userField->name;
						?>" <?php
						echo($this->userField->sys ? 'readonly="readonly"' : '');
						$readonly = $this->userField->sys ? 'readonly' : ''
						?> class="required <?php echo $readonly ?> "/>
					</td>
				</tr>

				<?php
				$lang = vmLanguage::getLanguage();
				$text = $lang->hasKey($this->userField->title) ? vmText::_($this->userField->title) : $this->userField->title;
				echo VmHTML::row('input', 'COM_VIRTUEMART_FIELDMANAGER_TITLE', 'title', $this->userField->title, 'class="inputbox"', '', 50, '255', '(' . $text . ')');
				?>

				<?php echo VmHTML::row('editor', 'COM_VIRTUEMART_USERFIELDS_DESCRIPTION', 'description', $this->userField->description, '100%', '50', array('image', 'pagebreak', 'readmore')); ?>
				<?php echo VmHTML::row('input', 'COM_VIRTUEMART_DEFAULT', 'default', $this->userField->default, 'class="inputbox"', '', 50); ?>

				<?php if ($this->userField->type == 'password' or $this->userField->type == 'emailaddress' or $this->userField->type == 'webaddress' or $this->userField->type == 'text' or $this->userField->type == 'textarea') {
					$lang = vmLanguage::getLanguage();
					$text = $lang->hasKey($this->userField->placeholder) ? vmText::_($this->userField->placeholder) : $this->userField->placeholder;
					echo VmHTML::row('input', 'COM_VIRTUEMART_FIELDMANAGER_PLACEHOLDER', 'placeholder', $this->userField->placeholder, 'class="inputbox"', '', 50, '255', '(' . $text . ')');
				} ?>

				<?php echo $this->lists['required']; ?>
				<?php echo $this->lists['cart']; ?>
				<?php echo $this->lists['account']; ?>
				<?php echo $this->lists['shipment']; ?>
				<?php echo $this->lists['readonly']; ?>
				<?php echo $this->lists['published']; ?>
				<?php echo VmHTML::row('input', 'COM_VIRTUEMART_USERFIELDS_SIZE', 'size', $this->userField->size, 'class="inputbox"', '', 5); ?>
				<?php echo VmHTML::row('raw', 'COM_VIRTUEMART_ORDERING', $this->ordering);// VmHTML::row('input','COM_VIRTUEMART_ORDERING','ordering',$this->userField->ordering,'class="inputbox"','',5); ?>
				<?php if (Vmconfig::get('multix', 'none') !== 'none') {
					echo VmHTML::row('raw', 'COM_VIRTUEMART_VENDOR', $this->lists['vendors']);
				} ?>
			</table>


			<input type="hidden" name="virtuemart_userfield_id"
					value="<?php echo $this->userField->virtuemart_userfield_id; ?>"/>
			<input type="hidden" name="valueCount" value="<?php echo $this->valueCount; ?>"/>
			<?php echo $this->addStandardHiddenToForm(); ?>
		</form>
	</div>
</div>
<?php
vmuikitAdminUIHelper::imitateTabs('end');
vmuikitAdminUIHelper::endAdminArea(); ?>

<?php $duration = 650;
?>
<script type="text/javascript">


	jQuery('.insertRow').click(function () {
		nr = jQuery('#fieldValuesBody tr').length
		row = '<tr><td><input type="text" name="vValues[' + nr + ']" value=""> </td><td><input type="text" name="vNames[' + nr + ']" value=""> <input type="button" class="button deleteRow" value=" - " /></td></tr>'
		jQuery('#fieldValuesBody').append(row)
	})
	jQuery('#fieldValuesBody').on('click', 'input.deleteRow', function () {
		let nr = jQuery('#fieldValuesBody tr').length
		if (nr > 1) jQuery(this).closest('tr').remove()
	})

	jQuery('.readonly').click(function (e) {
		return false
	})

	jQuery('select#type').chosen().change(function () {
		selected = jQuery(this).find('option:selected').val()
		toggleType(selected)
	})

	function toggleType (sType) {
		jQuery('#toggler').children('div').filter(':visible').slideUp()
		jQuery('input[name="vNames[0]"]').attr('mosReq', 0)
	  <?php if (!$this->userField->sys) : ?>
		prep4SQL(document.adminForm.name)
	  <?php endif; ?>
		switch (sType) {
			case 'editorta':
			case 'textarea':
				jQuery('#divText').slideDown()
				jQuery('#divColsRows').slideDown()
				break

			case 'euvatid':
				jQuery('#divShopperGroups').slideDown()
				break
			case 'age_verification':
				jQuery('#divAgeVerification').slideDown()
				break

			case 'emailaddress':
			case 'password':
			case 'text':
				jQuery('#divText').slideDown()
				break

			case 'select':
			case 'multiselect':
				jQuery('#divValues').slideDown()
				jQuery('input[name="vNames[0]"]').attr('mosReq', 1)

				break

			case 'radio':
			case 'multicheckbox':
				jQuery('#divColsRows').slideDown()
				jQuery('#divValues').slideDown()
				jQuery('input[name="vNames[0]"]').attr('mosReq', 1)

				break

			case 'webaddress':
				jQuery('#divWeb').slideDown()
				break

			case 'delimiter':
				break
			default:
				//pluginistraxx_euvatchecker
	  <?php if(!$this->userField->virtuemart_userfield_id) : ?>
				jQuery('#fieldPluginBody').load('index.php?option=com_virtuemart&view=userfields&format=json&field=' + sType, function () {
					jQuery(this).find('[title]').vm2admin('tips', tip_image)
				})
	  <?php endif; ?>
				if (sType.substring(0, 6) == 'plugin') jQuery('#divPlugin').slideDown()
				break

		}
	}
  <?php if (!$this->userField->virtuemart_userfield_id ) { ?>
	function checkName (field, rules, i, options) {
		name = field.val()
		field.val(name.replace(/[^0-9a-zA-Z\_]+/g, ''))
		var existingFields = new Array(<?php echo $this->existingFields ?>)
		if (jQuery.inArray(name, existingFields) > -1) {
			return options.allrules.onlyLetterNumber.alertText
		}
		var pattern = new RegExp(/^[0-9a-zA-Z\_]+$/)
		if ( !pattern.test(name)) {
			return options.allrules.onlyLetterNumber.alertText
		}
	}
  <?php } ?>
	function submitbutton (pressbutton) {
		if (pressbutton == 'cancel') submitform(pressbutton)
		if (jQuery('#adminForm').validationEngine('validate') == true) submitform(pressbutton)
		else return false
	}

	function prep4SQL (o) {
		if (o.value != '') {
			o.value = o.value.replace(/[^0-9a-zA-Z\_]+/g, '')
		}
	}
  <?php if($this->userField->virtuemart_userfield_id > 0) { ?>
	document.adminForm.name.readOnly = true
	toggleType(jQuery('#type').val())
  <?php } else { ?>
	toggleType(jQuery('#type').find('option:selected').val())
  <?php } ?>
	//toggleType('<?php echo $this->userField->type;?>');

	//<?php if ($this->userField->type !== "E") { ?>jQuery('#userField_plg').hide();<?php } ?>

</script>