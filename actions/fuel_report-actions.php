<?php
/**
 * actions/fuel_report-actions.php
 *
 * Every action for fuel_report.php lives in this one file.
 *
 * This page is a read-only report: it has no forms of its own and every filter
 * arrives on the query string, so there is nothing to handle here beyond the
 * signed-in check the page itself performs before this file is reached.
 *
 * The file exists so that the page follows the same layout as every other one -
 * one actions file, one endpoint file - and so there is an obvious place to put an
 * action if the report ever gains an export button or similar.
 */

// Only ever a guard: a POST to this page has nothing to act on.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_FUEL_REPORT_ACTIONS_RAN')) {
    return;
}
define('OCP_FUEL_REPORT_ACTIONS_RAN', true);

// Keep the response empty and well-formed for any POST that arrives.
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'This report has no actions.']);
exit();
