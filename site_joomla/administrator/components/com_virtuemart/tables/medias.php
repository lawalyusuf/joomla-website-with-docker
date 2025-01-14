<?php
/**
 *
 * Media table
 *
 * @package    VirtueMart
 * @subpackage Media
 * @author Max Milbers
 * @link https://virtuemart.net
 * @copyright Copyright (c) 2004 - 2014 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: medias.php 10916 2023-09-27 14:01:59Z Milbo $
 */

// Check to ensure this file is included in Joomla!
defined ('_JEXEC') or die('Restricted access');

/**
 * Media table class
 * The class is is used to manage the media in the shop.
 *
 * @author Max Milbers
 * @package        VirtueMart
 */
class TableMedias extends VmTable {

	/** @var int Primary key */
	var $virtuemart_media_id = 0;
	var $virtuemart_vendor_id = 0;

	/** @var string File title */
	var $file_title = '';
	/** @var string File description */
	var $file_description = '';
	/** @var string File Meta or alt  */
	var $file_meta = '';
	/** @var string File Class  */
	var $file_class = '';

	/** @var string File mime type */
	var $file_mimetype = '';
	/** @var string File typ, this determines where a media is stored */
	var $file_type = '';

	var $file_url = '';
	var $file_url_thumb = '';

	var $published = 0;
	var $file_is_downloadable = 0;
	var $file_is_forSale = 0;
	var $file_is_product_image = 0;


	var $shared = 0;
	var $file_params = '';
	var $file_lang = '';

	/**
	 * @author Max Milbers
	 * @param JDataBase $db
	 */
	function __construct (&$db) {

		parent::__construct ('#__virtuemart_medias', 'virtuemart_media_id', $db);

		//In a VmTable the primary key is the same as the _tbl_key and therefore not needed
// 		$this->setPrimaryKey('virtuemart_media_id');
//		$this->setUniqueName('file_title');

		$this->setLoggable ();
		$this->setLockable();
	}

	function check () {

		$ok = TRUE;
		$notice = TRUE;

		if (empty($this->file_type) and empty($this->file_is_forSale)) {
			$ok = FALSE;
			vmError (vmText::sprintf ('COM_VIRTUEMART_MEDIA_NO_TYPE'), $this->file_title);
		}

		if (!empty($this->file_url)) {
			if (function_exists ('mb_strlen')) {
				$length = mb_strlen ($this->file_url);
			} else {
				$length = strlen ($this->file_url);
			}
			if($length>254){
				vmError (vmText::sprintf ('COM_VIRTUEMART_URL_TOO_LONG', $length));
			}

			if (strpos ($this->file_url, '..') !== FALSE) {
				$ok = FALSE;
				vmError (vmText::sprintf ('COM_VIRTUEMART_URL_NOT_VALID', $this->file_url));
			}

			if (empty($this->virtuemart_media_id)) {

				//Check for already existing entries for the media
				$q = 'SELECT `virtuemart_media_id`,`file_url` FROM `' . $this->_tbl . '` WHERE `file_url` = "' . $this->_db->escape ($this->file_url) . '" ';
				$this->_db->setQuery ($q);
				$unique_id = $this->_db->loadAssocList ();

				$count = count ($unique_id);
				if ($count !== 0) {

					if ($count == 1) {
						if (empty($this->virtuemart_media_id)) {
							$this->virtuemart_media_id = $unique_id[0]['virtuemart_media_id'];
						}
						else {
							vmError (vmText::_ ('COM_VIRTUEMART_MEDIA_IS_ALREADY_IN_DB'));
							$ok = FALSE;
						}
					}
					else {
						//      			vmError(vmText::_('COM_VIRTUEMART_MEDIA_IS_DOUBLED_IN_DB'));
						vmError (vmText::_ ('COM_VIRTUEMART_MEDIA_IS_DOUBLED_IN_DB'));
						$ok = FALSE;
					}
				}
			}

		}
		else {
			vmError (vmText::_ ('COM_VIRTUEMART_MEDIA_MUST_HAVE_URL'));
			$ok = FALSE;
		}


		if (!empty($this->file_description)) {
			if (strlen ($this->file_description) > 254) {
				vmError (vmText::sprintf ('COM_VIRTUEMART_DESCRIPTION_TOO_LONG', strlen ($this->file_description)));
			}
		}

//		$app = JFactory::getApplication();

		//vmError('Checking '.$this->file_url);

		if ($this->file_is_forSale) {
			$lastIndexOfSlash = strrpos ($this->file_url, DS);
		} else {
			$lastIndexOfSlash = strrpos ($this->file_url, '/');
		}

		$name = substr ($this->file_url, $lastIndexOfSlash + 1);
		$file_extension = strtolower (JFile::getExt ($name));

		if (empty($this->file_title) && !empty($name)) {
			$this->file_title = $name;
		}

		if (!empty($this->file_title)) {
			if (strlen ($this->file_title) > 126) {
				vmError (vmText::sprintf ('COM_VIRTUEMART_TITLE_TOO_LONG', strlen ($this->file_title)));
			}

			$q = 'SELECT * FROM `' . $this->_tbl . '` ';
			$q .= 'WHERE `file_title`="' . $this->_db->escape ($this->file_title) . '" AND `file_type`="' . $this->_db->escape ($this->file_type) . '"';
			$this->_db->setQuery ($q);
			$unique_id = $this->_db->loadAssocList ();

			$tblKey = 'virtuemart_media_id';

			if (!empty($unique_id)) {
				foreach ($unique_id as $item) {
					if ($item['virtuemart_media_id'] != $this->virtuemart_media_id) {
						/*$lastDir = substr ($this->file_url, 0, strrpos ($this->file_url, '/'));
						$lastDir = substr ($lastDir, strrpos ($lastDir, '/') + 1);
						if (!empty($lastDir)) {
							$this->file_title = $this->file_title . '_' . $lastDir;
						}
						else {
							$this->file_title = $this->file_title . '_' . rand (1, 9);
						}*/
						$this->file_title = $name.'_'.count($unique_id);
					}
				}
			}
		}
		else {
			vmdebug('Media table check, media has no file_title',$this);
			vmError (vmText::_ ('COM_VIRTUEMART_MEDIA_MUST_HAVE_TITLE'));
			$ok = FALSE;
		}

		if (empty($this->file_mimetype)) {

			if (empty($name)) {
				vmError ('Table: '.vmText::_ ('COM_VIRTUEMART_NO_MEDIA'));
			}

			//images
			elseif($file_extension === 'jpg' or $file_extension === 'jpeg' or $file_extension === 'jpe'){
				$this->file_mimetype = 'image/jpeg';
			}
			elseif($file_extension === 'gif'){
				$this->file_mimetype = 'image/gif';
			}
			elseif($file_extension === 'png'){
				$this->file_mimetype = 'image/png';
			}
			elseif($file_extension === 'bmp'){
				vmInfo(vmText::sprintf('COM_VIRTUEMART_MEDIA_SHOULD_NOT_BMP',$name));
				$notice = true;
			}

			//audio
			elseif($file_extension === 'mp3'){
				$this->file_mimetype = 'audio/mpeg';
			}
			elseif($file_extension === 'ogg'){
				$this->file_mimetype = 'audio/ogg';
			}
			elseif($file_extension === 'oga'){
				$this->file_mimetype = 'audio/vorbis';
			}
			elseif($file_extension === 'wma'){
				$this->file_mimetype = 'audio-/x-ms-wma';
			}

			//video
			//added missing mimetypes: m2v
			elseif( $file_extension === 'mp4' or $file_extension === 'mpe' or $file_extension === 'mpeg' or $file_extension === 'mpg' or $file_extension === 'mpga' or $file_extension === 'm2v'){
				$this->file_mimetype = 'video/mpeg';
			}
			elseif($file_extension === 'avi'){
				$this->file_mimetype = 'video/x-msvideo';
			}

			elseif($file_extension === 'qt' or $file_extension === 'mov'){
				$this->file_mimetype = 'video/quicktime';
			}
			elseif($file_extension === 'wmv'){
				$this->file_mimetype = 'video/x-ms-wmv';
			}

			//Added missing formats
			elseif($file_extension === '3gp'){
				$this->file_mimetype = 'video/3gpp';
			}
			elseif($file_extension === 'ogv'){
				$this->file_mimetype = 'video/ogg';
			}
			elseif($file_extension === 'flv'){
				$this->file_mimetype = 'video/x-flv';
			}

			elseif($file_extension === 'f4v'){
				$this->file_mimetype = 'video/x-f4v';
			}

			elseif($file_extension === 'm4v'){
				$this->file_mimetype = 'video/x-m4v';
			}

			elseif($file_extension === 'webm'){
				$this->file_mimetype = 'video/webm';
			}

			//applications
			elseif($file_extension === 'zip'){
				$this->file_mimetype = 'application/zip';
			}
			elseif($file_extension === 'pdf'){
				$this->file_mimetype = 'application/pdf';
			}
			elseif($file_extension === 'gz'){
				$this->file_mimetype = 'application/x-gzip';
			}
			elseif($file_extension === 'exe'){
				$this->file_mimetype = 'application/octet-stream';
			}
			elseif($file_extension === 'swf'){
				$this->file_mimetype = 'application/x-shockwave-flash';
			}
			//missing types
			elseif($file_extension === 'doc'){
				$this->file_mimetype = 'application/msword';
			}
			elseif($file_extension === 'docx'){
				$this->file_mimetype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
			}
			elseif($file_extension === 'xls'){
				$this->file_mimetype = 'application/vnd.ms-excel';
			}
			elseif($file_extension === 'xlsx'){
				$this->file_mimetype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
			}
			elseif($file_extension === 'ppt'){
				$this->file_mimetype = 'application/vnd.ms-powerpoint';
			}
			elseif($file_extension === 'pptx'){
				$this->file_mimetype = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
			}
			elseif($file_extension === 'txt'){
				$this->file_mimetype = 'text/plain';
			}
			elseif($file_extension === 'rar'){
				$this->file_mimetype = 'application/x-rar-compressed';
			}

			else {
				vmInfo (vmText::sprintf ('COM_VIRTUEMART_MEDIA_SHOULD_HAVE_MIMETYPE', $name));
				$notice = TRUE;
			}

		}

		if(substr($this->file_url,0,4)=='http'){
			$this->file_url = substr($this->file_url,5);
		} else if(substr($this->file_url,0,5)=='https') {
			$this->file_url = substr($this->file_url,6);
		}

		//Nasty small hack, should work as long the word for default is in the language longer than 3 words and the first
		//letter should be always / or something like this
		//It prevents storing of the default path
		$a = trim(substr($this->file_url_thumb,0,4));
		$b = trim(substr(vmText::_('COM_VIRTUEMART_DEFAULT_URL'),0,4));

		if( strpos($a,$b)!==FALSE ){
			$this->file_url_thumb = null;
		}

		if ($ok) {
			return parent::check ();
		}
		else {
			return FALSE;
		}

	}

	/**
	 * We need a customised error handler to catch the errors maybe thrown by
	 * mime_content_type
	 *
	 * @author Max Milbers derived from Philippe Gerber
	 */
	function handleError ($errno, $errstr) {

		// error was suppressed with the @-operator
		if (0 === error_reporting ()) {
			return FALSE;
		}
		throw new ErrorException($errstr, 0);
		//echo 'I throw exception';
		//throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	}

}
// pure php no closing tag
