<?php
/*
 * includes/partials/upload_attendance/missingEmployees.php
 *
 * PHP fragment captured from the inline <script> block of upload_attendance.php.
 *
 * It is rendered by the page's data island (includes/page_data.php ->
 * ocp_capture()) and read by assets/js/upload_attendance.js. Everything after this
 * comment is the original fragment, byte for byte.
 */
ob_start();
?>
<?php echo $missing_employees_json ? $missing_employees_json : 'null'; ?>
<?php
echo ob_get_clean();
