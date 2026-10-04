<?php
// generate_gas_po_pdf.php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'includes/db_config.php';

// Get PO ID from query string
$po_id = $_GET['id'] ?? 0;

if (!$po_id) {
    die("PO ID is required");
}

try {
    // Get PO details - FIXED: Join with users table for BOTH prepared_by AND approved_by
    $poStmt = $pdo->prepare("
        SELECT 
            po.*, 
            s.supplier_name, 
            CONCAT(u1.firstname, ' ', u1.lastname) as preparer_name,
            CONCAT(u2.firstname, ' ', u2.lastname) as approver_name
        FROM gasoline_purchase_orders po
        LEFT JOIN gasoline_suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u1 ON po.prepared_by = u1.id
        LEFT JOIN users u2 ON po.approved_by = u2.id
        WHERE po.id = :id
    ");
    $poStmt->bindParam(':id', $po_id);
    $poStmt->execute();
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$po) {
        die("Purchase Order not found");
    }
    
    // Get PO items
    $itemsStmt = $pdo->prepare("
        SELECT 
            poi.*,
            s.supplier_name as item_supplier_name,
            v.vehicle_name,
            v.plate_number,
            e.equipment_name,
            CONCAT(emp.firstname, ' ', emp.lastname) as driver_name
        FROM gasoline_po_items poi
        LEFT JOIN gasoline_suppliers s ON poi.supplier_id = s.id
        LEFT JOIN vehicles v ON poi.vehicle_id = v.id
        LEFT JOIN equipment e ON poi.equipment_id = e.id
        LEFT JOIN employee emp ON poi.driver_operator_id = emp.id
        WHERE poi.po_id = :po_id
        ORDER BY poi.id
    ");
    $itemsStmt->bindParam(':po_id', $po_id);
    $itemsStmt->execute();
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals and get driver/operator name
    $total_quantity = 0;
    $total_amount = 0;
    $driver_operator_name = '';
    
    // Get the first driver/operator name from items
    if (!empty($items)) {
        $first_item = $items[0];
        if (!empty($first_item['driver_operator_id'])) {
            $driver_operator_name = htmlspecialchars($first_item['driver_name'] ?? '');
        } else {
            $driver_operator_name = htmlspecialchars($first_item['manual_driver_name'] ?? '');
        }
    }
    
    foreach ($items as $item) {
        $total_quantity += $item['quantity_liters'];
        $item_total = $item['quantity_liters'] * ($item['price_per_liter'] ?? 0);
        $total_amount += $item_total;
    }
    
    // Format the date to mm-dd-yyyy
    $formatted_po_date = '';
    if (!empty($po['po_date'])) {
        $date_obj = new DateTime($po['po_date']);
        $formatted_po_date = $date_obj->format('m-d-Y');
    }
    
    // Get names for PDF
    $prepared_by_name = !empty($po['preparer_name']) ? htmlspecialchars($po['preparer_name']) : 'N/A';
    $approved_by_name = !empty($po['approver_name']) ? htmlspecialchars($po['approver_name']) : 'N/A';
    
    // Get signature data
    $signature_data_purchaser = $po['completion_signature'] ?? '';
    $signature_data = $po['approval_signature'] ?? '';
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Define logo path
$logo_path = __DIR__ . '/../img/logo/OCP.png';

$logo_src = '';
if (file_exists($logo_path)) {
    $image_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($image_data);
    $logo_src = $logo_base64;
} else {
    $alternative_paths = [
        __DIR__ . '/img/logo/OCP.png',
        $_SERVER['DOCUMENT_ROOT'] . '/img/logo/OCP.png',
        'img/logo/OCP.png'
    ];
    
    foreach ($alternative_paths as $alt_path) {
        if (file_exists($alt_path)) {
            $image_data = file_get_contents($alt_path);
            $logo_base64 = 'data:image/png;base64,' . base64_encode($image_data);
            $logo_src = $logo_base64;
            break;
        }
    }
    
    if (!$logo_src) {
        error_log("Logo not found at: " . $logo_path);
    }
}

// Count number of items to determine if we need a blank row
$item_count = count($items);
$show_blank_row = ($item_count == 1);

// Generate HTML for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Order ' . htmlspecialchars($po['po_number']) . '</title>
    <style>
        @page {
            size: legal;
            margin: 2mm 2mm 2mm 30mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }

        .po-border {
            border: 2px solid #000000;
            padding: 0 10px 0 10px;
            position: relative;
        }
        
        .header {
            position: relative;
            text-align: center;
            min-height: 80px;
            margin-top: -10px;
            margin-bottom: 13px;
        }
        
        .logo-container {
            position: absolute;
            left: 10px;
            top: 0;
            width: 100px;
            height: 80px;
            text-align: center;
            margin-top: 15px;
            margin-left: 150px;
        }
        
        .logo-img {
            max-width: 100%;
            max-height: 60px;
            width: auto;
            height: auto;
        }
        
        .header-content {
            display: inline-block;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }
        
        .company-name {
            font-size: 14px;
            font-weight: bold;
            margin-top: 25px;
        }
        
        .document-title {
            font-size: 14px;
            font-weight: bold;
        }
        
        .po-info-row {
            margin-bottom: 1px;
            clear: both;
        }
        
        .po-date-container {
            display: inline-block;
            width: 50%;
            text-align: left;
            font-size: 11px;
        }
        
        .po-control-container {
            display: inline-block;
            width: 50%;
            text-align: right;
            font-size: 11px;
        }
        
        .po-info-value {
            border-bottom: 1px solid #000;
            padding: 0 5px;
            min-width: 150px;
            display: inline-block;
            text-align: center;
            font-weight: bold;
        }
        
        .po-number-red {
            color: #dc3545;
            font-weight: bold;
        }
        
        .info-section {
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .deliver-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0 5px 0;
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
        
        .deliver-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .empty-row {
            height: 25px;
        }
        
        .empty-row td {
            border: 1px solid #dee2e6;
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
        
        .page-break {
            page-break-before: always;
        }
        
        .name-container {
            display: inline-block;
            width: 50%;
            text-align: left;
            font-size: 11px;
        }
        
        .name-label {
            display: inline-block;
        }
        
        .name-underline {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 250px;
            margin-left: 5px;
            padding-left: 5px;
            text-align: center;
        }
        
        .driver-name {
            font-weight: bold;
            display: inline-block;
            width: 100%;
            text-align: center;
        }
        
        .withdrawal-message {
            font-style: italic;
            font-size: 9px;
            margin-bottom: 10px;
            margin-top: 10px;
        }
        
        .not-valid-message {
            font-size: 9px;
            text-align: left;
            font-style: italic;
            margin-bottom: 20px;
            margin-top: 10px;
        }
        
        .signature-container {
            width: 100%;
        }

        .label-container {
            width: 100%;
            margin-top: 10px;
        }
        
        .signature-left {
            display: inline-block;
            width: 50%;
            text-align: left;
            font-size: 9px;
        }

        .purchaser-left {
            display: inline-block;
            width: 50%;
            text-align: left;
            font-size: 8px;
        }
        
        .signature-right {
            display: inline-block;
            width: 49%;
            text-align: right;
            font-size: 9px;
            position: relative;
        }

        .manager-right {
            display: inline-block;
            width: 49%;
            text-align: right;
            font-size: 8px;
        }
        
        .signature-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 200px;
            margin-left: 5px;
            text-align: center;
            padding: 0 10px;
        }
        
        /* Signature image styling */
        .signature-image {
            display: inline-block;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .signature-image img {
            max-height: 60px;
            background: transparent;
        }
        
        .signature-wrapper-purchaser {
            margin-top: -35px;
            margin-bottom: -35px;
            text-align: center;
        }

        .signature-wrapper {
            margin-top: -35px;
            margin-bottom: -35px;
        }

        .purchaser-label {
            display: inline-block;
            min-width: 200px;
            margin: -15px 0 0 -15px;
            text-align: right;
            padding: 0 10px;
        }

        .manager-label {
            display: inline-block;
            min-width: 200px;
            margin: -15px 0 0 5px;
            text-align: center;
            padding: 0 10px;
        }
        
        .prepared-by-name {
            display: inline-block;
            font-weight: bold;
        }
        
        .approver-name {
            display: inline-block;
            font-weight: bold;
        }

        .prepared-by-label {
            display: inline-block;
        }
        
        .approver-label {
            display: inline-block;
        }
        
        .footer-container {
            width: 100%;
            font-size: 8px;
            margin-top: -15px;
        }
        
        .footer-left {
            display: inline-block;
            width: 33%;
            text-align: left;
            color: #dc3545;
        }
        
        .footer-center {
            display: inline-block;
            width: 33%;
            text-align: center;
            color: #dc3545;
        }
        
        .footer-right {
            display: inline-block;
            width: 33%;
            text-align: right;
            color: #dc3545;
        }

        .div-dot-line {
            border-top: 1px solid #dc3545;
            margin: 4px 0 4px 0;
        }
        
        .debug-info {
            position: absolute;
            top: 0;
            right: 0;
            background: yellow;
            padding: 5px;
            font-size: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="po-border">
        <div class="debug-info">
            Logo exists: ' . ($logo_src ? 'YES' : 'NO') . '<br>
            Path: ' . $logo_path . '
        </div>
        
        <div class="header">';

// Add logo if available
if ($logo_src) {
    $html .= '
            <div class="logo-container">
                <img src="' . $logo_src . '" class="logo-img" alt="OCP Construction Logo">
            </div>';
}

$html .= '
            <div class="header-content">
                <div class="company-name">OCP CONSTRUCTION</div>
                <div class="document-title">FUEL WITHDRAWAL SLIP</div>
            </div>
        </div>
        
        <div class="po-info-row">
            <div class="po-date-container">
                PO Date: <span class="po-info-value">' . htmlspecialchars($formatted_po_date) . '</span>
            </div><div class="po-control-container">
                PO Control No: <span class="po-info-value"><span class="po-number-red">' . htmlspecialchars($po['po_number']) . '</span></span>
            </div>
        </div>
        
        <div class="po-info-row">
            <div class="name-container">
                <span class="name-label">Name:</span>
                <span class="name-underline">
                    <span class="driver-name">' . $driver_operator_name . '</span>
                </span>
            </div>
        </div>
        
        <div class="withdrawal-message">
            Please allow the above-mentioned name to withdraw from your gasoline station the listed here under items.
        </div>
        
        <table class="deliver-table">
            <thead>
                <tr>
                    <th width="15%">QTY</th>
                    <th width="15%">UNIT</th>
                    <th width="40%">DESCRIPTION</th>
                    <th width="15%">UNIT PRICE</th>
                    <th width="15%">TOTAL</th>
                </tr>
            </thead>
            <tbody>';
            
            // Add data rows
            foreach ($items as $item) {
                $item_total = $item['quantity_liters'] * ($item['price_per_liter'] ?? 0);
                
                $html .= '
                <tr>
                    <td class="text-right">' . number_format($item['quantity_liters'], 2) . '</td>
                    <td>Liter</td>
                    <td class="text-left">' . htmlspecialchars($item['gasoline_type']) . '</td>
                    <td class="text-right">' . ($item['price_per_liter'] !== null ? 'Php ' . number_format($item['price_per_liter'], 2) : 'N/A') . '</td>
                    <td class="text-right">' . ($item['price_per_liter'] !== null ? 'Php ' . number_format($item_total, 2) : 'N/A') . '</td>
                </tr>';
            }
            
            // Add one blank row only if there\'s exactly 1 item
            if ($show_blank_row) {
                $html .= '
                <tr class="empty-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>';
            }
            
            $html .= '
            </tbody>
        </table>
        
        <div class="not-valid-message">
            Not valid unless signed by authorized officer.
        </div>
        
        <div class="signature-container">
            <div class="signature-left">
                <div class="signature-wrapper-purchaser">
                    ' . (!empty($signature_data_purchaser) ? '<div class="signature-image"><img src="' . $signature_data_purchaser . '"></div>' : '') . '
                </div>
                Prepared by: 
                <span class="signature-line">
                    <span class="prepared-by-name">LESTER R. ALIPIO</span>
                </span>
            </div>
            
            <div class="signature-right">
                <div class="signature-wrapper">
                    ' . (!empty($signature_data) ? '<div class="signature-image"><img src="' . $signature_data . '"></div>' : '') . '
                </div>
                Approved by: 
                <span class="signature-line">
                    <span class="approver-name">' . $approved_by_name . '</span>
                </span>
            </div>
        </div>

        <div class="label-container">
            <div class="purchaser-left">
                <span class="purchaser-label">
                    <span class="prepared-by-label">PURCHASER</span>
                </span>
            </div>
            
            <div class="manager-right">
                <span class="manager-label">
                    <span class="approver-label">MANAGER</span>
                </span>
            </div>
        </div>
        
        <div class="footer-container">
            <div class="footer-left">
                FM-PUR - 11
            </div>
            <div class="footer-center">
                REV-01
            </div>
            <div class="footer-right">
                1/25/2026
            </div>
        </div>
    </div>

    <div class="div-dot-line"></div>

    <div class="po-border">
        <div class="debug-info">
            Logo exists: ' . ($logo_src ? 'YES' : 'NO') . '<br>
            Path: ' . $logo_path . '
        </div>
        
        <div class="header">';

// Add logo if available
if ($logo_src) {
    $html .= '
            <div class="logo-container">
                <img src="' . $logo_src . '" class="logo-img" alt="OCP Construction Logo">
            </div>';
}

$html .= '
            <div class="header-content">
                <div class="company-name">OCP CONSTRUCTION</div>
                <div class="document-title">FUEL WITHDRAWAL SLIP</div>
            </div>
        </div>
        
        <div class="po-info-row">
            <div class="po-date-container">
                PO Date: <span class="po-info-value">' . htmlspecialchars($formatted_po_date) . '</span>
            </div><div class="po-control-container">
                PO Control No: <span class="po-info-value"><span class="po-number-red">' . htmlspecialchars($po['po_number']) . '</span></span>
            </div>
        </div>
        
        <div class="po-info-row">
            <div class="name-container">
                <span class="name-label">Name:</span>
                <span class="name-underline">
                    <span class="driver-name">' . $driver_operator_name . '</span>
                </span>
            </div>
        </div>
        
        <div class="withdrawal-message">
            Please allow the above-mentioned name to withdraw from your gasoline station the listed here under items.
        </div>
        
        <table class="deliver-table">
            <thead>
                <tr>
                    <th width="15%">QTY</th>
                    <th width="15%">UNIT</th>
                    <th width="40%">DESCRIPTION</th>
                    <th width="15%">UNIT PRICE</th>
                    <th width="15%">TOTAL</th>
                </tr>
            </thead>
            <tbody>';
            
            // Add data rows (second copy)
            foreach ($items as $item) {
                $item_total = $item['quantity_liters'] * ($item['price_per_liter'] ?? 0);
                
                $html .= '
                <tr>
                    <td class="text-right">' . number_format($item['quantity_liters'], 2) . '</td>
                    <td>Liter</td>
                    <td class="text-left">' . htmlspecialchars($item['gasoline_type']) . '</td>
                    <td class="text-right">' . ($item['price_per_liter'] !== null ? 'Php ' . number_format($item['price_per_liter'], 2) : 'N/A') . '</td>
                    <td class="text-right">' . ($item['price_per_liter'] !== null ? 'Php ' . number_format($item_total, 2) : 'N/A') . '</td>
                </tr>';
            }
            
            // Add one blank row only if there\'s exactly 1 item (second copy)
            if ($show_blank_row) {
                $html .= '
                <tr class="empty-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>';
            }
            
            $html .= '
            </tbody>
        </table>
        
        <div class="not-valid-message">
            Not valid unless signed by authorized officer.
        </div>
        
        <div class="signature-container">
            <div class="signature-left">
                <div class="signature-wrapper-purchaser">
                    ' . (!empty($signature_data_purchaser) ? '<div class="signature-image"><img src="' . $signature_data_purchaser . '"></div>' : '') . '
                </div>
                Prepared by: 
                <span class="signature-line">
                    <span class="prepared-by-name">LESTER R. ALIPIO</span>
                </span>
            </div>
            
            <div class="signature-right">
                <div class="signature-wrapper">
                    ' . (!empty($signature_data) ? '<div class="signature-image"><img src="' . $signature_data . '"></div>' : '') . '
                </div>
                Approved by: 
                <span class="signature-line">
                    <span class="approver-name">' . $approved_by_name . '</span>
                </span>
            </div>
        </div>

        <div class="label-container">
            <div class="purchaser-left">
                <span class="purchaser-label">
                    <span class="prepared-by-label">PURCHASER</span>
                </span>
            </div>
            
            <div class="manager-right">
                <span class="manager-label">
                    <span class="approver-label">MANAGER</span>
                </span>
            </div>
        </div>
        
        <div class="footer-container">
            <div class="footer-left">
                FM-PUR - 11
            </div>
            <div class="footer-center">
                REV-01
            </div>
            <div class="footer-right">
                1/25/2026
            </div>
        </div>
    </div>

    <div class="div-dot-line"></div>

</body>
</html>';

// Load DOMPDF library
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Configure DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultPaperSize', 'legal');
$options->set('defaultFont', 'Arial');
$options->set('isPhpEnabled', true);

$dompdf = new Dompdf($options);

// Set paper orientation and size
$dompdf->setPaper('legal', 'portrait');

// Load HTML content
$dompdf->loadHtml($html, 'UTF-8');

// Render PDF
$dompdf->render();

// Output PDF - Set 'Attachment' to false to display in browser instead of downloading
$dompdf->stream('PO_' . $po['po_number'] . '.pdf', [
    'Attachment' => false
]);
?>