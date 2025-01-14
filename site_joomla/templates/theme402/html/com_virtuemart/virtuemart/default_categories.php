<?php
// Access
defined('_JEXEC') or die('Restricted access');

// Category and Columns Counter
$iCol = 1;
$iCategory = 1;

// Calculating Categories Per Row
$categories_per_row = VmConfig::get('homepage_categories_per_row', 3);
$category_cellwidth = floor(100 / $categories_per_row);

// Separator
$verticalseparator = " vertical-separator";
?>

<div class="category-view">

    <h1><?php echo JText::_('COM_VIRTUEMART_CATEGORIES') ?></h1>

    <?php
    // Start the Output
    foreach ($this->categories as $category) {

	// Show the horizontal seperator
	if ($iCol == 1 && $iCategory > $categories_per_row) {
	    ?>
	    <div class="horizontal-separator"></div>
	<?php
	}

	// this is an indicator wether a row needs to be opened or not
	if ($iCol == 1) {
	    ?>
	    <div class="row">
	    <?php
	    }

	    // Show the vertical seperator
	    if ($iCategory == $categories_per_row or $iCategory % $categories_per_row == 0) {
		$show_vertical_separator = ' ';
	    } else {
		$show_vertical_separator = $verticalseparator;
	    }

	    // Category Link
	    $caturl = JRoute::_('index.php?option=com_virtuemart&view=category&virtuemart_category_id=' . $category->virtuemart_category_id);

	    // Show Category
	    ?>
    	<div class="category floatleft<?php echo $show_vertical_separator ?>" style="width:<?php echo $category_cellwidth ?>%">
			<div class="spacer">
				<h2>
					<a href="<?php echo $caturl ?>" title="<?php echo $category->category_name ?>">
					<div class="category-border">
					<?php // if ($category->ids) {
						echo $category->images[0]->displayMediaThumb("",false);
					//} ?>
					</div>
					<div class="category-title"><?php echo $category->category_name ?></div>
					</a>
				</h2>
			</div>
		</div>
	<?php
	$iCategory++;

	// Do we need to close the current row now?
	if ($iCol == $categories_per_row) {
	    ?>
		<div class="clear"></div>
	    </div>
	<?php
	$iCol = 1;
    } else {
	$iCol++;
    }
}
// Do we need a final closing row tag?
if ($iCol != 1) {
    ?>
        <div class="clear"></div>
    </div>
    <?php
}
?>
</div>