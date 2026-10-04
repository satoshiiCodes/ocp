<?php
/*
 * includes/partials/pr_spare_view_routing/withdrawalSlipDetails.php
 *
 * PHP fragment captured from the inline <script> block of pr_spare_view_routing.php.
 *
 * It is rendered by the page's data island (includes/page_data.php ->
 * ocp_capture()) and read by assets/js/pr_spare_view_routing.js. Everything after this
 * comment is the original fragment, byte for byte.
 */
ob_start();
?>
<?php echo json_encode($withdrawal_slip_details['items'] ?? []); ?>
<?php
echo ob_get_clean();
