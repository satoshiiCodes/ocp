<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'includes/db_config.php';
require_once 'vendor/autoload.php'; // Make sure Dompdf is installed via Composer

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if PR ID is provided
if (!isset($_GET['pr_id']) || empty($_GET['pr_id'])) {
    $_SESSION['swal_data'] = array(
        'title' => 'Error!',
        'text' => 'PR ID is required.',
        'icon' => 'error'
    );
    header('Location: purchase_request.php');
    exit();
}

$pr_id = $_GET['pr_id'];

// Function to format user name
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

try {
    // Get PR details
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
               u.firstname, u.middlename, u.lastname, u.suffix, u.department, u.position,
               p.project_name, p.address as project_address, p.threshold_amount
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN projects p ON pr.project_id = p.id
        WHERE pr.id = ?
    ");
    $prStmt->execute([$pr_id]);
    $pr = $prStmt->fetch(PDO::FETCH_ASSOC);

    if (!$pr) {
        throw new Exception('Purchase Request not found.');
    }

    // Check if document type is 'ws'
    if ($pr['document_type'] !== 'ws' && $pr['document_type'] !== 'po_ws') {
        throw new Exception('Withdrawal Slip can only be generated for WS or PO_WS document types.');
    }

    // Get withdrawal slip details
    $wsStmt = $pdo->prepare("
        SELECT ws.*, w.warehouse_name, w.location,
               u.firstname, u.middlename, u.lastname, u.suffix, u.position, u.department
        FROM withdrawal_slips ws
        LEFT JOIN warehouses w ON ws.warehouse_id = w.id
        LEFT JOIN users u ON ws.requested_by = u.id
        WHERE ws.pr_id = ?
        ORDER BY ws.id DESC
        LIMIT 1
    ");
    $wsStmt->execute([$pr_id]);
    $ws = $wsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ws) {
        throw new Exception('No Withdrawal Slip found for this PR.');
    }

    // Get withdrawal slip items
    $itemsStmt = $pdo->prepare("
        SELECT wsi.*, 
               i.item_code, i.item_name,
               w.warehouse_name,
               COALESCE(wsi.batch_numbers, '[]') as batch_numbers_json
        FROM withdrawal_slip_items wsi
        LEFT JOIN item_names i ON wsi.item_id = i.id
        LEFT JOIN warehouses w ON wsi.warehouse_id = w.id
        WHERE wsi.withdrawal_slip_id = ?
        ORDER BY i.item_name ASC
    ");
    $itemsStmt->execute([$ws['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get stock withdrawals for this withdrawal slip (to get batch numbers and unit costs)
    $withdrawalsStmt = $pdo->prepare("
        SELECT sw.*, ib.batch_number
        FROM stock_withdrawals sw
        LEFT JOIN inventory_batches ib ON sw.batch_id = ib.id
        WHERE sw.withdrawal_slip_id = ?
        ORDER BY sw.id ASC
    ");
    $withdrawalsStmt->execute([$ws['id']]);
    $withdrawals = $withdrawalsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group withdrawals by withdrawal_slip_item_id
    $withdrawals_by_item = [];
    foreach ($withdrawals as $withdrawal) {
        $ws_item_id = $withdrawal['withdrawal_slip_item_id'];
        if (!isset($withdrawals_by_item[$ws_item_id])) {
            $withdrawals_by_item[$ws_item_id] = [];
        }
        $withdrawals_by_item[$ws_item_id][] = $withdrawal;
    }

    // Get approver/signatories
    $approversStmt = $pdo->prepare("
        SELECT prh.*, 
               u.firstname, u.middlename, u.lastname, u.suffix, u.position, u.department
        FROM pr_routing_history prh
        LEFT JOIN users u ON prh.action_by = u.id
        WHERE prh.pr_id = ? AND prh.action IN ('Approved by Approver', 'Withdrawal Slip Processed')
        ORDER BY prh.created_at DESC
    ");
    $approversStmt->execute([$pr_id]);
    $approvers = $approversStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get the approver (CEO) and warehouse processor
    $approved_by = null;
    $processed_by = null;

    foreach ($approvers as $approver) {
        if ($approver['action'] === 'Approved by Approver') {
            $approved_by = $approver;
        } elseif ($approver['action'] === 'Withdrawal Slip Processed') {
            $processed_by = $approver;
        }
    }

    // Get current user for prepared by
    $userStmt = $pdo->prepare("
        SELECT firstname, middlename, lastname, suffix, position, department
        FROM users WHERE id = ?
    ");
    $userStmt->execute([$_SESSION['user_id']]);
    $current_user = $userStmt->fetch(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_cost = 0;
    foreach ($items as $item) {
        $total_cost += ($item['quantity'] * $item['unit_cost']);
    }

    // Get absolute path to logo
    $logo_path = __DIR__ . '/img/logo/OCP.png';
    
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

    // Generate HTML for PDF with the correct table columns
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Withdrawal Slip - ' . htmlspecialchars($ws['ws_number']) . '</title>
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
                margin-top: 5px;
            }
            .info-container {
                margin-top: 5px;
                width: 100%;
                font-size: 10px;
            }
            .signature-row {
                margin-top: 20px;
                width: 100%;
                font-size: 10px;
                display: table;
                table-layout: fixed;
            }
            .signature-column {
                display: table-cell;
                width: 33.33%;
                text-align: center;
                vertical-align: bottom;
                padding: 0 5px;
            }
            .signature-label {
                font-weight: bold;
                display: block;
                margin-bottom: 30px;
            }
            .signature-line {
                border-bottom: 2px solid #000;
                margin-bottom: 5px;
            }
            .signature-name {
                font-weight: bold;
                text-transform: uppercase;
            }
            .signature-title {
                font-size: 9px;
                color: #555;
            }
            .sign-name-container {
                margin-top: 40px;
                width: 100%;
                font-size: 10px;
            }
            .sign-container {
                margin-top: -3px;
                width: 100%;
                border-bottom: 2px dashed #000000ff;
                padding-bottom: 20px;
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
            .items-table {
                width: 100%;
                border-collapse: collapse;
                page-break-inside: avoid;
            }
            .items-table th {
                background-color: #2c3e50;
                color: white;
                padding: 10px 8px;
                text-align: center;
                border: 1px solid #dee2e6;
                font-size: 11px;
                font-weight: bold;
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
            .text-right {
                text-align: right;
            }
            .text-center {
                text-align: center;
            }
            .text-left {
                text-align: left;
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
            }
            .ws-value {
                font-weight: normal;
                border-bottom: 1px solid #000000ff;
                padding-bottom: 2px;
                text-align: center;
                color: #ff0000;
                font-weight: bold;
            }
            .status-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 10px;
                font-weight: bold;
                text-transform: uppercase;
                margin-left: 10px;
            }
            .status-released {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .status-approved {
                background-color: #cce5ff;
                color: #004085;
                border: 1px solid #b8daff;
            }
            .status-draft {
                background-color: #fff3cd;
                color: #856404;
                border: 1px solid #ffeeba;
            }
            .amount-words {
                margin: 15px 0 10px 0;
                font-size: 10px;
                font-style: italic;
                padding: 5px;
                border-top: 1px dashed #ccc;
                border-bottom: 1px dashed #ccc;
            }
            .empty-row {
                height: 25px;
            }
            .empty-row td {
                border: 1px solid #dee2e6;
            }
            .batch-info {
                font-size: 9px;
                color: #555;
                padding-left: 15px;
                background-color: #f5f5f5;
                display: block;
                text-align: center;
            }
            .deliveries-should {
                margin-top: 15px;
                font-size: 10px;
                font-style: italic;
                padding: 5px;
                border-top: 1px solid #ccc;
            }
            .description-cell {
                text-align: center;
            }
            .description-content {
                display: inline-block;
                text-align: center;
                width: 100%;
            }
            .batch-info-center {
                font-size: 8px;
                color: #666;
                text-align: center;
                display: block;
                margin-top: 3px;
            }
            .footer-row {
                margin-top: 5px;
                width: 100%;
                font-size: 10px;
                display: table;
                table-layout: fixed;
                border-top: 1px solid #ccc;
                border-bottom: 1px solid #ccc;
                padding-top: 5px;
                padding-bottom: 5px;
            }
            .footer-column {
                display: table-cell;
                width: 33.33%;
                text-align: center;
                vertical-align: middle;
                padding: 0 5px;
                font-family: Arial, sans-serif;
            }
            .footer-left {
                text-align: left;
            }
            .footer-center {
                text-align: center;
            }
            .footer-right {
                text-align: right;
            }
            .fm-pur {
                font-weight: bold;
                font-size: 9px;
            }
            .fm-pur-right {
                font-weight: bold;
                font-size: 9px;
                color: #ff0000;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="logo-container">
                <img src="' . $logo_src . '" class="company-logo" alt="OCP Logo">
                <div class="company-info">
                    <div class="company-name">OCP CONSTRUCTION</div>
                    <div class="document-title">WITHDRAWAL SLIP (PROJECT)</div>
                </div>
            </div>
        </div>

        <div class="info-container">
            <div class="info-left">
                <span class="info-label">Date:</span>
                <span class="info-value date-value">' . date('F d, Y', strtotime($ws['ws_date'])) . '</span>
            </div>
            <div class="info-right">
                <span class="info-label">WS Control No:</span>
                <span class="info-value ws-value">' . htmlspecialchars($ws['ws_number']) . '</span>
            </div>
        </div>

        <div class="info-container">
            <div class="info-left">
                <span class="info-label">Project/Area:</span>
                <span class="info-value date-value">' . htmlspecialchars($pr['project_name'] ?? 'N/A') . '</span>
            </div>
            <div class="info-right">
                <span class="info-label"></span>
                <span class="info-value"></span>
            </div>
        </div>

        <!-- Items Table with correct column headers -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="15%">ITEM CODE</th>
                    <th width="35%">DESCRIPTION</th>
                    <th width="10%">QUANTITY</th>
                    <th width="10%">UNIT</th>
                    <th width="25%">PURPOSE</th>
                </tr>
            </thead>
            <tbody>';
    
    // Add items to the table
    $item_count = count($items);
    if ($item_count > 0) {
        $counter = 1;
        foreach ($items as $item) {
            $html .= '
                <tr>
                    <td class="text-center">' . $counter . '</td>
                    <td class="text-center">' . htmlspecialchars($item['item_code'] ?? 'N/A') . '</td>
                    <td class="description-cell">';
            
            // Main description
            $html .= '<div class="description-content">' . htmlspecialchars($item['item_name'] ?? 'N/A') . '</div>';
            
            $html .= '</td>
                    <td class="text-center">' . number_format($item['quantity'], 2) . '</td>
                    <td class="text-center">' . htmlspecialchars($item['unit'] ?? 'pc') . '</td>
                    <td class="text-center">&nbsp;</td> <!-- Empty purpose column, centered -->
                </tr>';
            $counter++;
        }
        
        // Add empty rows to reach at least 10 rows total
        $empty_rows_needed = max(0, 10 - $item_count);
        for ($i = 1; $i <= $empty_rows_needed; $i++) {
            $html .= '
                <tr class="empty-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>';
        }
    } else {
        // If no items, add 8 empty rows
        for ($i = 1; $i <= 8; $i++) {
            $html .= '
                <tr class="empty-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>';
        }
    }

    $html .= '
            </tbody>
        </table>';

    // Get warehouse manager for checked by
    $warehouseManagerStmt = $pdo->prepare("
        SELECT firstname, middlename, lastname, suffix, position
        FROM users 
        WHERE department = 'Warehouse' AND accounttype = 'Admin'
        ORDER BY id ASC
        LIMIT 1
    ");
    $warehouseManagerStmt->execute();
    $warehouse_manager = $warehouseManagerStmt->fetch(PDO::FETCH_ASSOC);
    $warehouse_manager_name = $warehouse_manager ? formatUserName($warehouse_manager) : 'WAREHOUSE MANAGER';

    // Format names for signatures
    $prepared_by_name = strtoupper(formatUserName($ws));
    $checked_by_name = strtoupper($warehouse_manager_name);
    $approved_by_name = $approved_by ? strtoupper(formatUserName($approved_by)) : '_____________________';
    $approved_by_title = $approved_by ? strtoupper($approved_by['position'] ?? 'CEO') : 'CEO/GENERAL MANAGER';
    
    // Add signature row with 3 columns below the table
    $html .= '
        <!-- Signature Row with 3 Columns -->
        <div class="signature-row">
            <!-- Approved By Column -->
            <div class="signature-column">
                <div class="signature-label">Approved by:</div>
                <div class="signature-line">
                    <div class="signature-name"> DENVER JAY P. PACLIBON </div>
                </div>
                <div class="signature-title"> ASST. PROJECT MANAGER </div>
            </div>
            
            <!-- Prepared By Column -->
            <div class="signature-column">
                <div class="signature-label">Prepared by:</div>
                <div class="signature-line">
                    <div class="signature-name"> LESTER R. ALIPIO </div>
                </div>
                <div class="signature-title"> PURCHASER </div>
            </div>
            
            <!-- Checked By Column -->
            <div class="signature-column">
                <div class="signature-label">Checked by:</div>
                <div class="signature-line">
                    <div class="signature-name">' . $checked_by_name . '</div>
                </div>
                <div class="signature-title"> WAREHOUSE IN-CHARGE </div>
            </div>
        </div>

        <!-- Footer Row with FM-PUR Information -->
        <div class="footer-row">
            <div class="footer-column footer-left">
                <span class="fm-pur">FM-PUR - 10</span>
            </div>
            <div class="footer-column footer-center">
                <span class="fm-pur">00</span>
            </div>
            <div class="footer-column footer-right">
                <span class="fm-pur-right">02/13/2026</span>
            </div>
        </div>

        <div class="sign-container">
        </div>
        
    </body>
    </html>';

    // Create PDF options
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('chroot', __DIR__);

    // Create DOMPDF instance
    $dompdf = new Dompdf($options);

    // Set paper size to Legal (8.5 x 14 inches)
    $dompdf->setPaper('legal', 'portrait');

    // Load HTML content
    $dompdf->loadHtml($html);

    // Render PDF
    $dompdf->render();

    // Output PDF - Open in browser instead of downloading
    $dompdf->stream('WS_' . htmlspecialchars($ws['ws_number']) . '.pdf', [
        'Attachment' => false, // Changed to false to open in browser
        'compress' => true
    ]);

    exit();

} catch (Exception $e) {
    $_SESSION['swal_data'] = array(
        'title' => 'Error!',
        'text' => 'Failed to generate PDF: ' . $e->getMessage(),
        'icon' => 'error'
    );
    header('Location: purchase_request.php');
    exit();
}

?>