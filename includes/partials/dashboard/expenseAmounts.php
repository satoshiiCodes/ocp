<?php
/*
 * includes/partials/dashboard/expenseAmounts.php
 *
 * PHP fragment captured from the inline <script> block of dashboard.php.
 *
 * dashboard.php decodes this back into an array before putting it in the data island; handing
 * the JSON text straight to the island made it a string in the browser, so Chart.js drew the
 * whole year of amounts as one 28-character data point.
 */
ob_start();
?>
<?php echo json_encode($expense_amounts); ?>
<?php
echo ob_get_clean();
