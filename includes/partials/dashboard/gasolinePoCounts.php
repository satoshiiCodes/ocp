<?php
/*
 * includes/partials/dashboard/gasolinePoCounts.php
 *
 * PHP fragment captured from the inline <script> block of dashboard.php.
 *
 * dashboard.php decodes this back into an array before putting it in the data island; handing
 * the JSON text straight to the island made it a string in the browser.
 */
ob_start();
?>
<?php echo json_encode($gasoline_po_counts); ?>
<?php
echo ob_get_clean();
