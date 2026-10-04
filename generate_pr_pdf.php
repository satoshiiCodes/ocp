<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if PR ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Invalid PR ID');
}

$pr_id = $_GET['id'];

// Database connection
require_once 'config/db_config.php';

// Fetch PR details
try {
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
               u.firstname, u.middlename, u.lastname, u.suffix, u.department, u.position,
               p.project_name, p.address as project_address
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN projects p ON pr.project_id = p.id
        WHERE pr.id = ?
    ");
    $prStmt->execute([$pr_id]);
    $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pr) {
        die('Purchase Request not found.');
    }
    
    // Fetch PR items
    $itemsStmt = $pdo->prepare("
        SELECT pri.*, 
               i.item_code, i.item_name,
               w.warehouse_name
        FROM pr_items pri
        LEFT JOIN item_names i ON pri.item_id = i.id
        LEFT JOIN warehouses w ON pri.warehouse_id = w.id
        WHERE pri.pr_id = ?
    ");
    $itemsStmt->execute([$pr_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total cost
    $total_cost = 0;
    foreach ($items as $item) {
        $unit_cost = $item['unit_cost'] ?? 0;
        $total_cost += ($item['quantity'] * $unit_cost);
    }
    
} catch (PDOException $e) {
    die('Error fetching PR data: ' . $e->getMessage());
}

// Format requester name
function formatUserName($user) {
    $name = $user['firstname'] ?? '';
    if (!empty($user['middlename'])) {
        $name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    $name .= ' ' . ($user['lastname'] ?? '');
    if (!empty($user['suffix'])) {
        $name .= ' ' . $user['suffix'];
    }
    return $name;
}

// Include DOMPDF
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Create PDF options
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('chroot', __DIR__); // Add chroot to restrict file access to current directory

// Create DOMPDF instance
$dompdf = new Dompdf($options);

// Set paper size to Legal (8.5 x 14 inches)
$dompdf->setPaper('legal', 'portrait');

// Get absolute path to logo
$logo_path = __DIR__ . '/assets/images/logo/OCP.png';

// Check if logo exists, if not, use base64 encoded fallback or empty
$logo_src = '';
if (file_exists($logo_path)) {
    // Encode logo to base64 for reliable display
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = base64_encode($logo_data);
    $logo_src = 'data:image/png;base64,' . $logo_base64;
} else {
    // Use a placeholder if logo doesn't exist
    $logo_src = 'https://via.placeholder.com/80x80/2c3e50/ffffff?text=OCP';
}

// HTML content for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Request - ' . htmlspecialchars($pr['pr_number']) . '</title>
    <style>
        @page {
            margin: 20px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.2;
        }
        .header {
            margin-bottom: 10px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        .company-logo {
            height: 80px;
            width: auto;
            margin-bottom: -110px;
            margin-left: 170px;
        }
        .company-info {
            text-align: center;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
        }
        .document-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .info-container {
            margin-top: 5px;
            width: 100%;
            font-size: 10px;
        }
        .sign-name-container {
            margin-top: 40px;
            width: 100%;
            font-size: 10px;
        }
        .sign-container {
            margin-top: -3px;
            width: 100%;
            border-bottom: 2px solid #000000ff;
            padding-bottom: 30px;
            margin-bottom: 30px;
        }
        .info-left, .info-right {
            display: inline-block;
            width: 49%;
            vertical-align: top;
        }
        .info-left {
            text-align: left;
        }
        .info-right {
            text-align: right;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
        }
        .approved-label {
            font-weight: bold;
            display: inline-block;
        }
        .prepared-label {
            font-weight: bold;
            display: inline-block;
        }
        .info-value {
            display: inline-block;
            margin-left: 5px;
            min-width: 200px;
        }
        .info-value-label1 {
            display: inline-block;
            margin-left: 95px;
            min-width: 200px;
            text-align: center;
            font-size: 8px;
        }
        .info-value-label2 {
            display: inline-block;
            margin-left: 5px;
            min-width: 200px;
            text-align: center;
            font-size: 8px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }
        .info-table .label {
            width: 25%;
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .deliver-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .deliver-table th {
            background-color: #2c3e50;
            color: white;
            padding: 8px;
            text-align: center;
            border: 1px solid #dee2e6;
            font-size: 11px;
            font-weight: bold;
        }
        .deliver-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
            text-align: center;
            font-size: 11px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .items-table th {
            background-color: #2c3e50;
            color: white;
            padding: 10px 8px;
            text-align: left;
            border: 1px solid #dee2e6;
            font-size: 11px;
        }
        .items-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
            font-size: 11px;
            vertical-align: top;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .total-row {
            font-weight: bold;
            background-color: #e9ecef !important;
            font-size: 12px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-bold {
            font-weight: bold;
        }
        .mb-3 {
            margin-bottom: 15px;
        }
        .mt-3 {
            margin-top: 15px;
        }
        .remarks-box {
            min-height: 80px;
            border: 1px solid #dee2e6;
            padding: 10px;
            margin-top: 10px;
            background-color: #f8f9fa;
            font-size: 11px;
            line-height: 1.4;
        }
        .no-items {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
            border: 1px dashed #dee2e6;
            background-color: #f8f9fa;
        }
        .date-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
            margin-left: 24px;
        }
        .to-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
            margin-left: 33px;
        }
        .pr-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
            color: #ff0000
        }
        .pr-name-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
        }
        .address-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
        }
        .name-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
        }
        .prepared-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
            margin-left: 33px;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 9px;
        }
        .approved-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
            margin-left: 33px;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 9px;
        }
        .please-deliver {
            font-style: italic;
            font-size: 8px;
            margin: 5px 0 5px 0;
        }
        .deliveries-should {
            font-style: italic;
            font-size: 8px;
            margin: 5px 0 20px 0;
        }
        .empty-row {
            height: 25px;
        }
        .empty-row td {
            border: 1px solid #dee2e6;
        }
        .text-left {
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <img src="' . $logo_src . '" class="company-logo" alt="OCP Logo">
            <div class="company-info">
                <div class="company-name">OCP CONSTRUCTION</div>
                <div class="document-title">PURCHASE REQUEST (PROJECT)</div>
            </div>
        </div>
    </div>

    <div class="info-container">
        <div class="info-left">
            <span class="info-label">Date:</span>
            <span class="info-value date-value">' . date('m-d-Y', strtotime($pr['request_date'])) . '</span>
        </div>
        <div class="info-right">
            <span class="info-label">PR Control No:</span>
            <span class="info-value pr-value">' . htmlspecialchars($pr['pr_number']) . '</span>
        </div>
    </div>
    <div class="info-container">
        <div class="info-left">
            <span class="info-label">To:</span>
            <span class="info-value to-value">' . htmlspecialchars($item['warehouse_name'] ?? 'N/A') . '</span>
        </div>
        <div class="info-right">
            <span class="info-label">Deliver to:</span>
            <span class="info-value pr-name-value">' . htmlspecialchars($pr['project_name']) . '</span>
        </div>
    </div>
    <div class="info-container">
        <div class="info-left">
            <span class="info-label">Address:</span>
            <span class="info-value address-value">' . htmlspecialchars($pr['project_address'] ?? 'N/A') . '</span>
        </div>
        <div class="info-right">
            <span class="info-label">Name:</span>
            <span class="info-value name-value">' . htmlspecialchars(formatUserName($pr)) . '</span>
        </div>
    </div>
    <div class="please-deliver">
        <span>Please deliver the following goods, materials or supplies to OCP CONSTRUCTION</span>
    </div>

    <!-- Delivery Table with 7 extra empty rows -->
    <table class="deliver-table">
        <thead>
            <tr>
                <th width="15%">QTY</th>
                <th width="10%">UNIT</th>
                <th width="45%">DESCRIPTION</th>
                <th width="15%">UNIT PRICE</th>
                <th width="15%">TOTAL</th>
            </tr>
        </thead>
        <tbody>';
        
// Add items to the deliver table
$item_count = count($items);
if ($item_count > 0) {
    foreach ($items as $item) {
        $unit_cost = $item['unit_cost'] ?? 0;
        $item_total = $item['quantity'] * $unit_cost;
        
        $html .= '
            <tr>
                <td class="text-right">' . number_format($item['quantity'], 2) . '</td>
                <td>' . htmlspecialchars($item['unit'] ?? 'pc') . '</td>
                <td class="text-left">' . htmlspecialchars($item['item_name'] ?? 'N/A') . '</td>
                <td class="text-right">Php ' . number_format($unit_cost, 2) . '</td>
                <td class="text-right">Php ' . number_format($item_total, 2) . '</td>
            </tr>';
    }
    
    // Add empty rows after the actual items
    $empty_rows_needed = max(0, 8 - $item_count);
    for ($i = 1; $i <= $empty_rows_needed; $i++) {
        $html .= '
            <tr class="empty-row">
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>';
    }
} else {
    // If no items, add 7 empty rows
    for ($i = 1; $i <= 7; $i++) {
        $html .= '
            <tr class="empty-row">
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>';
    }
}

// If there are more than 7 items, we still want the total row
$html .= '
            <tr>
                <td colspan="4" class="text-right text-bold">TOTAL ESTIMATED COST:</td>
                <td class="text-right text-bold">Php ' . number_format($total_cost, 2) . '</td>
            </tr>
        </tbody>
    </table>

    <div class="deliveries-should">
        <span>Deliveries should meet our order specifications. Not valid unless signed by authorized officer. We reserve the right to reject items other than the
        above indicated items and those not in accordance with the specifications as ordered.</span>
    </div>

    <div class="sign-name-container">
        <div class="info-left">
            <span class="prepared-label">Prepared by:</span>
            <span class="info-value prepared-value">LESTER R. ALIPIO </span>
        </div>
        <div class="info-right">
            <span class="approved-label">Approved by:</span>
            <span class="info-value approved-value">OSCAR C. PACLIBON </span>
        </div>
    </div>

    <div class="sign-container">
        <div class="info-left">
            <span class="prepared-label"></span>
            <span class="info-value-label1">PURCHASER</span>
        </div>
        <div class="info-right">
            <span class="approved-label"></span>
            <span class="info-value-label2">GENERAL MANAGER </span>
        </div>
    </div>

</body>
</html>';

// Load HTML content
$dompdf->loadHtml($html);

// Render PDF
$dompdf->render();

// Output PDF - Open in browser instead of downloading
$dompdf->stream('PR_' . htmlspecialchars($pr['pr_number']) . '.pdf', [
    'Attachment' => false, // Changed to false to open in browser
    'compress' => true
]);

exit();
?>