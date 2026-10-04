<?php
/*
 * includes/partials/dashboard/gasolineAmounts.php
 *
 * PHP fragment captured from the inline <script> block of dashboard.php.
 *
 * dashboard.php decodes this back into an array before putting it in the data island; handing
 * the JSON text straight to the island made it a string, so Chart.js drew the year of amounts
 * as one 31-character data point.
 */
ob_start();
?>
<?php echo json_encode($gasoline_amounts); ?>
<?php
echo ob_get_clean();
