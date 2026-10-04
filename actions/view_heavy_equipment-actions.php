<?php
/**
 * actions/view_heavy_equipment-actions.php
 *
 * Every action for view_heavy_equipment.php lives in this one file.
 *
 * This page has one action and no forms of its own: it is opened either by a POST
 * from the equipment list or by a link carrying ?id=. The action is the redirect
 * guard - with no equipment there is nothing to display, so the request goes back
 * to the list, exactly as it did inline.
 *
 * $ocp_equipment_id is prefixed because the page shares its scope with this file
 * and with the endpoint.
 */

// The page includes this file once; the constant stops a re-render running it again.
if (defined('OCP_VIEW_HEAVY_EQUIPMENT_ACTIONS_RAN')) {
    return;
}
define('OCP_VIEW_HEAVY_EQUIPMENT_ACTIONS_RAN', true);

// The equipment to show: posted from the list, or passed on the query string.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['equipment_id'])) {
    $ocp_equipment_id = $_POST['equipment_id'];
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['id'])) {
    $ocp_equipment_id = $_GET['id'];
} else {
    header('Location: heavy_equipment.php');
    exit();
}
