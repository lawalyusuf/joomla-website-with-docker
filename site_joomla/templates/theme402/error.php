<?php
/**
 * @version		$Id: error.php 20196 2011-01-09 02:40:25Z ian $
 * @package		Joomla.Site
 * @copyright	Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
if (!isset($this->error)) {
	$this->error = JError::raiseWarning(404, JText::_('JERROR_ALERTNOAUTHOR'));
	$this->debug = false; 
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo $this->language; ?>" lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
<head>
	<title><?php echo $this->error->getCode(); ?> - <?php echo $this->title; ?></title>
	<link rel="stylesheet" href="<?php echo $this->baseurl; ?>/templates/system/css/error.css" type="text/css" />
    <style>
	a:hover {text-decoration:none;}
	</style>
</head>
<body>
	<div class="error">
		<div id="outline">
		<div id="errorboxoutline">
			<div id="errorboxheader"><?php echo $this->error->getCode(); ?> - <?php echo $this->error->getMessage(); ?></div>
			<div id="errorboxbody">
			<p><strong><?php echo JText::_('You may not be able to visit this page because of:'); ?>:</strong></p>
				<ol>
					<li><?php echo JText::_('an out-of-date bookmark/favourite'); ?></li>
					<li><?php echo JText::_('a search engine that has an out-of-date listing for this site'); ?></li>
					<li><?php echo JText::_('a mistyped address'); ?></li>
					<li><?php echo JText::_('you have no access to this page'); ?></li>
					<li><?php echo JText::_('The requested resource was not found'); ?></li>
					<li><?php echo JText::_('An error has occurred while processing your request.'); ?></li>
				</ol>
			<p><strong><?php echo JText::_('Please try one of the following pages:'); ?>:</strong></p>

				<ul>
					<li><a href="<?php echo $this->baseurl; ?>/index.php" title="<?php echo JText::_('Go to home page'); ?>"><?php echo JText::_('Home page'); ?></a></li>
				</ul>

			<p><?php echo JText::_('If difficulties persist, please contact the System Administrator of this site.'); ?></p>
			<div id="techinfo">
			<p><?php echo $this->error->getMessage(); ?></p>
			<p>
				<?php if ($this->debug) :
					echo $this->renderBacktrace();
				endif; ?>
			</p>
			</div>
			</div>
		</div>
		</div>
	</div>
</body>
</html>
