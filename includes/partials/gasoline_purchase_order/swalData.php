<?php
/*
 * includes/partials/gasoline_purchase_order/swalData.php
 *
 * PHP fragment captured from the inline <script> block of gasoline_purchase_order.php.
 *
 * It is rendered by the page's data island (includes/page_data.php ->
 * ocp_capture()) and read by assets/js/gasoline_purchase_order.js. Everything after this
 * comment is the original fragment, byte for byte.
 */
ob_start();
?>
<?php echo json_encode($swal_data['title'] ?? ''); ?>
<?php
echo ob_get_clean();
