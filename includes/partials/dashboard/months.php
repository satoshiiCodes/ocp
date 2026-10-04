<?php
/*
 * includes/partials/dashboard/months.php
 *
 * PHP fragment captured from the inline <script> block of dashboard.php.
 *
 * It prints json_encode() of the value, which dashboard.php then decodes back into an array
 * before handing it to the data island - see the island block at the foot of that page.
 *
 * The round trip is not decoration. The island encodes whatever it is given, so handing it this
 * JSON text produced a *string* in the browser: Chart.js read the month names as 73 separate
 * characters ("[", '"', "J", "a", "n", ...) instead of 12 labels, and every expense amount
 * collapsed into a single 28-character data point. Decoding here restores a real array.
 */
ob_start();
?>
<?php echo json_encode($months); ?>
<?php
echo ob_get_clean();
