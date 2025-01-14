<?php
defined('_JEXEC') or die('');
/**
 * abstract controller class containing get,store,delete,publish and pagination
 *
 *
 * This class provides the functions for the calculatoins
 *
 * @package	VirtueMart
 * @subpackage Helpers
 * @author Max Milbers
 * @copyright Copyright (c) 2011 - 2014 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */

if (!class_exists( 'VmConfig' )) require(JPATH_ROOT .'/administrator/components/com_virtuemart/helpers/config.php');
VmConfig::loadConfig();
vmDefines::tcpdf();

class VmPdf {



	/** Function to create a nice vendor-styled PDF for the output of the given view.
	    The $path and $dest arguments are directly passed on to TCPDF::Output. 
	    To create a PDF directly from a given HTML (i.e. not through a view), one
	    can directly use the VmVendorPDF class, see VirtueMartControllerInvoice::samplePDF */
	static function createVmPdf($view, $path='', $dest='F', $meta=array()) {
		if(!$view){
			// TODO: use some default view???
			$er = 'createVmPdf Cannot render without view';
			vmError($er, $er);
			return 0;
		}
		
		if(!class_exists('VmVendorPDF')){
			$er = 'createVmPdf  Cannot find class VmVendorPDF';
			vmError($er, $er);
			return 0;
		}
		vmdebug('createVmPdf passed basic conditions to render pdf');

		$pdf = new VmVendorPDF();
		if (isset($meta['title'])) $pdf->SetTitle($meta['title']);
		if (isset($meta['subject'])) $pdf->SetSubject($meta['subject']);
		if (isset($meta['keywords'])) $pdf->SetKeywords($meta['keywords']);
		// Make the formatter available, just in case some specialized view wants/needs it
		$view->pdf_formatter = $pdf;
		$view->isPdf = true;
		$view->print = false;

		ob_start();
		$view->display();
		$html = ob_get_contents();
		ob_end_clean();

		$pdf->AddPage();
		$pdf->PrintContents($html);

		// Close and output PDF document
		// This method has several options, check the source code documentation for more information.
		$pdf->Output($path, $dest);

		return $path;
	}
}




//if(class_exists('TCPDF')){

	// Extend the TCPDF class to create custom Header and Footer as configured in the Backend
	class VmVendorPDF extends TCPDF {
		var $vendor = 0;
		var $vendorImage = '';
		var $vendorAddress = '';
		var $css = '';
		var $virtuemart_vendor_id = 1;
		var $tcpdf6 = false;

		public function __construct() {
			// Load the vendor, so we have the data for the header/footer...
			// The images are NOT loaded by default, so do it manually, just in case
			$vendorModel = VmModel::getModel('vendor');
			$this->vendor = $vendorModel->getVendor($this->virtuemart_vendor_id);
			$vendorModel->addImages($this->vendor,1);
			$this->vendor->vendorFields = $vendorModel->getVendorAddressFields($this->virtuemart_vendor_id);
			
			parent::__construct($this->vendor->vendor_letter_orientation, 'mm', $this->vendor->vendor_letter_format);

			$this->css = $this->vendor->vendor_letter_css;
			
			// set document information
			$this->SetCreator(vmText::sprintf('COM_VIRTUEMART_PDF_CREATOR', vmVersion::$RELEASE));

			$this->vendorImage = '';
			if(empty($this->vendor->images[0])){
				vmError('Vendor image given path empty ');
			} else if(empty($this->vendor->images[0]->file_url_folder) or empty($this->vendor->images[0]->file_name) or empty($this->vendor->images[0]->file_extension) ){
				vmError('Vendor image given image is not complete '.$this->vendor->images[0]->file_url_folder.$this->vendor->images[0]->file_name.'.'.$this->vendor->images[0]->file_extension);
			/*} else if(!empty($this->vendor->images[0]->file_extension) and strtolower($this->vendor->images[0]->file_extension)=='png'){
				vmError('Warning extension of the vendor image '.$this->vendor->images[0]->file_name.'is a png, tpcdf has problems with that in the header, choose a jpg or gif');*/
			} else {
				$imagePath = str_replace('/',DS, $this->vendor->images[0]->file_url_folder.$this->vendor->images[0]->file_name.'.'.$this->vendor->images[0]->file_extension);
				if(!file_exists(VMPATH_ROOT . DS . $imagePath)){
					vmError('Vendor image missing '.$imagePath);
				} else {
					$this->vendorImage=$imagePath;
				}
			}
			// Generate PDF header
			$this->tcpdf6 = JFile::exists(VMPATH_TCPDF .'/include/tcpdf_colors.php');
			if($this->tcpdf6){
				$this->tcpdf6 = method_exists('TCPDF','getAllSpotColors');
			}
			if($this->tcpdf6){
				$getAllSpotColors = TCPDF::getAllSpotColors();
				$vlfooterlcolor = TCPDF_COLORS::convertHTMLColorToDec($this->vendor->vendor_letter_footer_line_color,$getAllSpotColors);
			} else {
				$vlfooterlcolor = $this->convertHTMLColorToDec($this->vendor->vendor_letter_footer_line_color);
			}

			$this->setHeaderData(($this->vendor->vendor_letter_header_image?$this->vendorImage:''),
				($this->vendor->vendor_letter_header_image?$this->vendor->vendor_letter_header_imagesize:0),
				'', $this->vendor->vendor_letter_header_html,
				array(0,0,0),$vlfooterlcolor );
			$this->vendorAddress = shopFunctionsF::renderVendorAddress($this->vendor->virtuemart_vendor_id, "<br/>");
			// Trim the final <br/> from the address, which is inserted by renderVendorAddress automatically!
			if (substr($this->vendorAddress, -5, 5) == '<br/>') {
				$this->vendorAddress = substr($this->vendorAddress, 0, -5);
			}

			$vmFont=$this->vendor->vendor_letter_font;
			$this->SetFont($vmFont, '', $this->vendor->vendor_letter_font_size, '', 'false');                 
			$this->setHeaderFont(Array($vmFont, '', $this->vendor->vendor_letter_header_font_size ));
			$this->setFooterFont(Array($vmFont, '', $this->vendor->vendor_letter_footer_font_size ));

			// Remove all vertical margins and padding from the HTML cells (default is excessive padding):
			$this->SetCellPadding(0);
			$tagvs = array('p' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
				'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
				'h1' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
				'h2' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
				'h3' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
				'table' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
				);
			$this->setHtmlVSpace($tagvs);
			
			// set default font subsetting mode
			$this->setFontSubsetting(true);
			// set default monospaced font
// 			$this->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

			//set margins
			$this->SetMargins($this->vendor->vendor_letter_margin_left, $this->vendor->vendor_letter_margin_top, $this->vendor->vendor_letter_margin_right);
			$this->SetHeaderMargin($this->vendor->vendor_letter_margin_header);
			$this->SetFooterMargin($this->vendor->vendor_letter_margin_footer);
			$this->SetAutoPageBreak(TRUE, $this->vendor->vendor_letter_margin_bottom);

			//set image scale factor
			$this->setImageScale(PDF_IMAGE_SCALE_RATIO);

			//TODO include the right file (in libraries/tcpdf/config/lang set some language-dependent strings
			$l='';
			$this->setLanguageArray($l);

		}


		/** Replace variables like {vm:page}, {vm:pagecount} etc. in the given string 
		 */
		function replace_variables($txt) {
			// TODO: Implement more Placeholders (ordernr, invoicenr, etc.)
			// Use PageNo rather than getAliasNumPage, since the alias will be misaligned (spaced like the {:npn:} text rather than the final number)
			$txt = str_replace('{vm:pagenum}', $this->PageNo(), $txt);
			// Can't use getNumPages, because when this is evaluated, we don't know the final number of pages (getNumPages is always equal to the current page numbe)
			$txt = str_replace('{vm:pagecount}', $this->getAliasNbPages(), $txt);
			$txt = str_replace('{vm:vendorname}', $this->vendor->vendor_store_name, $txt);
			$imgrepl='';
			if (!empty($this->vendor->images)) {
				$img = $this->vendor->images[0];
				$imgrepl = "<div class=\"vendor-image\">".$img->displayIt($img->file_url,'','',false, '', false, false)."</div>";
			}
			$txt = str_replace('{vm:vendorimage}', $imgrepl, $txt);
			$txt = str_replace('{vm:vendoraddress}', $this->vendorAddress, $txt);
			$txt = str_replace('{vm:vendorlegalinfo}', $this->vendor->vendor_legal_info, $txt);
			$txt = str_replace('{vm:vendordescription}', $this->vendor->vendor_store_desc, $txt);
			$txt = str_replace('{vm:tos}', $this->vendor->vendor_terms_of_service, $txt);
			return "$txt";
		}
		
		public function PrintContents($html) {
			$contents = $this->replace_variables ($html);
			$this->writeHTMLCell($w=0, $h=0, $x='', $y='', $contents, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
		}

		public function Footer() {

			if ($this->vendor->vendor_letter_footer == 1) {
				$footertxt = '<style>' . $this->css . '</style>';
				$footertxt .= '<div id="vmdoc-footer" class="vmdoc-footer">' . $this->replace_variables($this->vendor->vendor_letter_footer_html) . '</div>';

				$currentCHRF = $this->getCellHeightRatio();
				$this->setCellHeightRatio($this->vendor->vendor_letter_footer_cell_height_ratio);

				$this->tcpdf6 = JFile::exists(VMPATH_TCPDF .'/include/tcpdf_colors.php');
				if($this->tcpdf6){
					$this->tcpdf6 = method_exists('TCPDF','getAllSpotColors');
				}
				if($this->tcpdf6){
					$getAllSpotColors = TCPDF::getAllSpotColors();
					$vlfooterlcolor = TCPDF_COLORS::convertHTMLColorToDec($this->vendor->vendor_letter_footer_line_color,$getAllSpotColors);
				} else {
					$vlfooterlcolor = $this->convertHTMLColorToDec($this->vendor->vendor_letter_footer_line_color);
				}

				//set style for cell border
				$border = 0;
				if ($this->vendor->vendor_letter_footer_line == 1) {
					$line_width = 0.85 / $this->getScaleFactor();
					$this->SetLineStyle(array('width' => $line_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $vlfooterlcolor));
					$border = 'T';
				}
			
				// TODO: Implement cell_height
// 				$cell_height = round(($this->getCellHeightRatio() * $footerfont[2]) / $this->getScaleFactor(), 2);
				$cell_height=1;
		
		
				$this->writeHTMLCell(0, $cell_height, '', '', $footertxt, $border, 1, 0, true, '', true);
				// Set it back
				$this->setCellHeightRatio($currentCHRF);
			}
		}

	public function Header() {

		if ($this->vendor->vendor_letter_header != 1) return;

		if ($this->header_xobjid === false) {
			// start a new XObject Template
			$this->header_xobjid = $this->startTemplate($this->w, $this->tMargin);
			$headerfont = $this->getHeaderFont();
			$headerdata = $this->getHeaderData();
			$this->y = $this->header_margin;
			
			$headertxt = '<style>' . $this->css . '</style>';
			$headertxt .= '<div id="vmdoc-header" class="vmdoc-header">' . $this->replace_variables($headerdata['string']) . '</div>';
			$currentCHRF = $this->getCellHeightRatio();
			$this->setCellHeightRatio($this->vendor->vendor_letter_header_cell_height_ratio);
			$this->tcpdf6 = JFile::exists(VMPATH_TCPDF .'/include/tcpdf_images.php');

			if ($this->rtl) {
				$this->x = $this->w - $this->original_rMargin;
			} else {
				$this->x = $this->original_lMargin;
			}
			$header_x = (($this->getRTL())?($this->original_rMargin):($this->original_lMargin));
			$cw = $this->w - $this->original_lMargin - $this->original_rMargin;
			if (($headerdata['logo']) and ($headerdata['logo'] != K_BLANK_IMAGE)) {

				if($this->tcpdf6){
					if (!class_exists ('TCPDF_IMAGES')) {
						require(VMPATH_TCPDF .'/include/tcpdf_images.php');
					}

					$imgtype = TCPDF_IMAGES::getImageFileType(VMPATH_ROOT.DS.$headerdata['logo']);
				} else {
					$imgtype = $this->getImageFileType(VMPATH_ROOT.DS.$headerdata['logo']);
				}

				if (($imgtype == 'eps') or ($imgtype == 'ai')) {
					$this->ImageEps(VMPATH_ROOT.DS.$headerdata['logo'], '', '', $headerdata['logo_width']);
				} elseif ($imgtype == 'svg') {
					$this->ImageSVG(VMPATH_ROOT.DS.$headerdata['logo'], '', '', $headerdata['logo_width']);
				} else {
					$this->Image(VMPATH_ROOT.DS.$headerdata['logo'], '', '', $headerdata['logo_width']);
				}
				$imgy = $this->getImageRBY();
				$header_x +=  ($headerdata['logo_width'] * 1.1);
				$cw -= ($headerdata['logo_width'] * 1.1);
			} else {
				$imgy = $this->y;
			}

			// set starting margin for text data cell
			$this->SetTextColorArray($this->header_text_color);
			// header string
			$this->SetFont($headerfont[0], $headerfont[1], $headerfont[2]);
			$this->SetX($header_x);
			
			$this->writeHTMLCell($cw, 0, $this->x, $this->header_margin, $headertxt, '', 2, false, true, '', true);
			// print an ending header line
			if ($this->vendor->vendor_letter_header_line == 1) {
				if($this->tcpdf6){
					$getAllSpotColors = TCPDF::getAllSpotColors();
					$vlheaderlcolor = TCPDF_COLORS::convertHTMLColorToDec($this->vendor->vendor_letter_header_line_color,$getAllSpotColors);
				} else {
					$vlheaderlcolor = $this->convertHTMLColorToDec($this->vendor->vendor_letter_header_line_color);
				}
				$this->SetLineStyle(array('width' => 0.85 / $this->k, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $vlheaderlcolor));
				$this->SetY(max($imgy,$this->y));
				if ($this->rtl) {
					$this->SetX($this->original_rMargin);
				} else {
					$this->SetX($this->original_lMargin);
				}
				$this->Cell(($this->w - $this->original_lMargin - $this->original_rMargin), 0, '', 'T', 0, 'C');
			}
			$this->setCellHeightRatio($currentCHRF);
			$this->endTemplate();
		}
		// print header template
		$x = 0;
		$dx = 0;
		if (!$this->header_xobj_autoreset AND $this->booklet and (($this->page % 2) == 0)) {
			// adjust margins for booklet mode
			$dx = ($this->original_lMargin - $this->original_rMargin);
		}
		if ($this->rtl) {
			$x = $this->w + $dx;
		} else {
			$x = 0 + $dx;
		}
		$this->printTemplate($this->header_xobjid, $x, 0, 0, 0, '', '', false);
		if ($this->header_xobj_autoreset) {
			// reset header xobject template at each page
			$this->header_xobjid = false;
		}
	}

//	}

}

