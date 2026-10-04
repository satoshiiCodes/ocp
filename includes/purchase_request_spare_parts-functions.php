<?php
/**
 * includes/purchase_request_spare_parts-functions.php
 *
 * The helpers purchase_request_spare_parts.php and its actions file share: the PR,
 * withdrawal-slip and job-order number generators, the employee-name and date
 * formatters, and the status badge class.
 *
 * They live in their own file because both entry points need them. The handlers call
 * the three generators; the page's markup calls the formatters and the badge helper;
 * and the endpoint calls formatEmployeeName() while building its dropdowns. They must
 * therefore be loaded before whichever runs first, which is why
 * actions/purchase_request_spare_parts-actions.php and
 * api/purchase_request_spare_parts-endpoint.php both require this file.
 *
 * The bodies below are lifted verbatim from purchase_request_spare_parts.php.
 */

if (defined('OCP_PURCHASE_REQUEST_SPARE_PARTS_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_PURCHASE_REQUEST_SPARE_PARTS_FUNCTIONS_LOADED', true);

// Generate PR number for spare parts
function generateSparePartsPRNumber($pdo, $request_type = 'stock') {
    $year = date('Y');
    
    if ($request_type === 'issue_materials') {
        // Generate Withdrawal Slip (WS) number for issue materials
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'issue_materials'");
        $stmt->execute([$year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = $result['count'] + 1;
        return "PRWS-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    } 
    elseif ($request_type === 'issue') {
        // Generate Issue Parts Purchase Request (IPPR) number for issue parts
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'issue'");
        $stmt->execute([$year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = $result['count'] + 1;
        return "IPPR-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    } 
    else {
        // Generate regular PR number for stock
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'stock'");
        $stmt->execute([$year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = $result['count'] + 1;
        return "SPR-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}

// Generate Withdrawal Slip Number
function generateWithdrawalSlipNumber($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_withdrawal_slips WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    return "WS-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// Generate Job Order Number
function generateJobOrderNumber($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_job_orders WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    return "JO-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// Function to format employee name for display
function formatEmployeeName($employee) {
    $name = $employee['firstname'];
    if (!empty($employee['middlename'])) {
        $name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
    }
    $name .= ' ' . $employee['lastname'];
    if (!empty($employee['suffix'])) {
        $name .= ' ' . $employee['suffix'];
    }
    return $name;
}

// Function to format date as mm-dd-yyyy
function formatDate($date) {
    if (empty($date) || $date == '0000-00-00') {
        return 'Not Set';
    }
    return date('m-d-Y', strtotime($date));
}

