<?php
/**
 * actions/view_vehicle-actions.php
 *
 * Every action for view_vehicle.php lives in this one file.
 *
 * This page has one action and no forms of its own: it is opened either by a POST
 * from the vehicles list or by a link carrying ?id=. The action is the redirect
 * guard - with no vehicle there is nothing to display, so the request goes back to
 * the list, exactly as it did inline.
 */

// The page includes this file once; the constant stops a re-render running it again.
if (defined('OCP_VIEW_VEHICLE_ACTIONS_RAN')) {
    return;
}
define('OCP_VIEW_VEHICLE_ACTIONS_RAN', true);

// The vehicle to show: posted from the list, or passed on the query string.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['vehicle_id'])) {
    $ocp_vehicle_id = $_POST['vehicle_id'];
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['id'])) {
    $ocp_vehicle_id = $_GET['id'];
} else {
    header('Location: vehicles.php');
    exit();
}
