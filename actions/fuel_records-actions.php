<?php
/**
 * actions/fuel_records-actions.php
 *
 * Every action for fuel_records.php lives in this one file.
 *
 * This page has one action and no forms of its own: it is opened by a POST from
 * the vehicles list carrying the vehicle whose fuel records should be shown. The
 * action is a redirect guard - with no vehicle there is nothing to display, so the
 * request goes back to the list, exactly as it did inline.
 */

// The page includes this file once; the constant stops a re-render running it again.
if (defined('OCP_FUEL_RECORDS_ACTIONS_RAN')) {
    return;
}
define('OCP_FUEL_RECORDS_ACTIONS_RAN', true);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['vehicle_id'])) {
    header('Location: vehicles.php');
    exit();
}
