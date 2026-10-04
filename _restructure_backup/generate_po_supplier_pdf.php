<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if PR ID is provided
if (!isset($_GET['pr_id']) || empty($_GET['pr_id'])) {
    die('Invalid PR ID');
}

$pr_id = $_GET['pr_id'];

// Database connection
require_once 'includes/db_config.php';

// Fetch PR details
try {
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
               u.firstname, u.middlename, u.lastname, u.suffix, u.department, u.position,
               s.supplier_name, s.address as supplier_address
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN suppliers s ON pr.supplier_id = s.id
        WHERE pr.id = ?
    ");
    $prStmt->execute([$pr_id]);
    $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pr) {
        die('Purchase Request not found.');
    }
    
    // Verify this is a supplier PR
    if ($pr['request_type'] !== 'supplier') {
        die('This is not a supplier purchase request.');
    }
    
    // Fetch PO for this PR
    $poStmt = $pdo->prepare("
        SELECT po.*
        FROM purchase_orders po
        WHERE po.pr_id = ?
        ORDER BY po.created_at DESC
        LIMIT 1
    ");
    $poStmt->execute([$pr_id]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$po) {
        die('No Purchase Order found for this PR.');
    }
    
    // Fetch PO items with received_quantity and warehouse details
    $itemsStmt = $pdo->prepare("
        SELECT poi.*, 
               i.item_code, i.item_name,
               w.warehouse_name,
               s.supplier_name, s.address as supplier_address
        FROM po_items poi
        LEFT JOIN item_names i ON poi.item_id = i.id
        LEFT JOIN warehouses w ON poi.warehouse_id = w.id
        LEFT JOIN suppliers s ON poi.supplier_id = s.id
        WHERE poi.po_id = ?
        ORDER BY poi.id
    ");
    $itemsStmt->execute([$po['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($items) === 0) {
        die('No items found in this Purchase Order.');
    }
    
    // Get supplier from the PR (not from items)
    $supplier_name = $pr['supplier_name'] ?? 'N/A';
    $supplier_address = $pr['supplier_address'] ?? 'N/A';
    
    // Get warehouse for delivery - take the first item's warehouse
    $deliver_to = isset($items[0]['warehouse_name']) && !empty($items[0]['warehouse_name']) 
        ? $items[0]['warehouse_name'] 
        : 'WAREHOUSE';
    
    // Calculate total cost based on received_quantity
    $total_cost = 0;
    foreach ($items as $item) {
        $unit_cost = $item['unit_cost'] ?? 0;
        $received_qty = $item['received_quantity'] ?? $item['quantity']; // Use received_quantity if available
        $total_cost += ($received_qty * $unit_cost);
    }
    
} catch (PDOException $e) {
    die('Error fetching data: ' . $e->getMessage());
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

// Function to get unit from item
function getItemUnit($item) {
    // Check if unit exists in item data
    if (isset($item['unit']) && !empty($item['unit'])) {
        return $item['unit'];
    }
    
    // Default units based on common item types
    $item_name = strtolower($item['item_name'] ?? '');
    
    if (strpos($item_name, 'bag') !== false || strpos($item_name, 'cement') !== false) {
        return 'bag';
    } elseif (strpos($item_name, 'liter') !== false || strpos($item_name, 'ltr') !== false || 
              strpos($item_name, 'paint') !== false || strpos($item_name, 'oil') !== false) {
        return 'liter';
    } elseif (strpos($item_name, 'gallon') !== false) {
        return 'gallon';
    } elseif (strpos($item_name, 'kg') !== false || strpos($item_name, 'kilo') !== false) {
        return 'kg';
    } elseif (strpos($item_name, 'meter') !== false || strpos($item_name, 'mtr') !== false) {
        return 'meter';
    } elseif (strpos($item_name, 'roll') !== false) {
        return 'roll';
    } elseif (strpos($item_name, 'sheet') !== false || strpos($item_name, 'plywood') !== false) {
        return 'sheet';
    } elseif (strpos($item_name, 'box') !== false) {
        return 'box';
    } elseif (strpos($item_name, 'bundle') !== false) {
        return 'bundle';
    } elseif (strpos($item_name, 'set') !== false) {
        return 'set';
    } elseif (strpos($item_name, 'pair') !== false) {
        return 'pair';
    } else {
        return 'pc';
    }
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
$options->set('chroot', __DIR__);

// Create DOMPDF instance
$dompdf = new Dompdf($options);

// Set paper size to Legal (8.5 x 14 inches)
$dompdf->setPaper('legal', 'portrait');

// Get absolute path to logo
$logo_path = __DIR__ . '/img/logo/OCP.png';

// Check if logo exists
$logo_src = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = base64_encode($logo_data);
    $logo_src = 'data:image/png;base64,' . $logo_base64;
} else {
    $logo_src = 'https://via.placeholder.com/80x80/2c3e50/ffffff?text=OCP';
}

// HTML content for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - ' . htmlspecialchars($po['po_number']) . '</title>
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
        .date-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
            margin-left: 24px;
        }
        .po-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
            color: #ff0000;
        }
        .supplier-value {
            font-weight: normal;
            border-bottom: 1px solid #000000ff;
            padding-bottom: 2px;
            text-align: center;
        }
        .deliver-to-value {
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
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <img src="' . $logo_src . '" class="company-logo" alt="OCP Logo">
            <div class="company-info">
                <div class="company-name">OCP CONSTRUCTION</div>
                <div class="document-title">PURCHASE ORDER (STOCK)</div>
            </div>
        </div>
    </div>

    <div class="info-container">
        <div class="info-left">
            <span class="info-label">Date:</span>
            <span class="info-value date-value">' . date('F d, Y', strtotime($po['po_date'])) . '</span>
        </div>
        <div class="info-right">
            <span class="info-label">PO Number:</span>
            <span class="info-value po-value">' . htmlspecialchars($po['po_number']) . '</span>
        </div>
    </div>
    <div class="info-container">
        <div class="info-left">
            <span class="info-label">Supplier:</span>
            <span class="info-value supplier-value">' . htmlspecialchars($supplier_name) . '</span>
        </div>
        <div class="info-right">
            <span class="info-label">Deliver to:</span>
            <span class="info-value deliver-to-value">' . htmlspecialchars($deliver_to) . '</span>
        </div>
    </div>
    <div class="info-container">
        <div class="info-left">
            <span class="info-label">Address:</span>
            <span class="info-value address-value">' . htmlspecialchars($supplier_address) . '</span>
        </div>
        <div class="info-right">
            <span class="info-label">Name:</span>
            <span class="info-value name-value">' . htmlspecialchars(formatUserName($pr)) . '</span>
        </div>
    </div>
    
    <div class="info-container">
        <div class="info-left">
            <span class="info-label">PR Number:</span>
            <span class="info-value supplier-value">' . htmlspecialchars($pr['pr_number']) . '</span>
        </div>
        <div class="info-right">
            <span class="info-label">Expected Delivery:</span>
            <span class="info-value deliver-to-value">' . (!empty($po['expected_delivery']) ? date('F d, Y', strtotime($po['expected_delivery'])) : 'ASAP') . '</span>
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
        // Use received_quantity if available, otherwise use quantity
        $received_qty = $item['received_quantity'] ?? $item['quantity'];
        $item_total = $received_qty * $unit_cost;
        $unit = getItemUnit($item);
        
        $html .= '
            <tr>
                <td class="text-right">' . number_format($received_qty, 2) . '</td>
                <td>' . htmlspecialchars($unit) . '</td>
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

// Show the total cost (using received_quantity for calculation)
$html .= '
            <tr>
                <td colspan="4" class="text-right text-bold">TOTAL:</td>
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
            <span class="info-value prepared-value">LESTER R. ALIPIO</span>
        </div>
        <div class="info-right">
            <span class="approved-label">Approved by:</span>
            <span class="info-value approved-value">OSCAR C. PACLIBON</span>
        </div>
    </div>

    <div class="sign-container">
        <div class="info-left">
            <span class="prepared-label"></span>
            <span class="info-value-label1">PURCHASER</span>
        </div>
        <div class="info-right">
            <span class="approved-label"></span>
            <span class="info-value-label2">GENERAL MANAGER</span>
        </div>
    </div>

</body>
</html>';

// Load HTML content
$dompdf->loadHtml($html);

// Render PDF
$dompdf->render();

// Output PDF - Open in browser instead of downloading
$dompdf->stream('PO_Supplier_' . htmlspecialchars($po['po_number']) . '.pdf', [
    'Attachment' => false,
    'compress' => true
]);

exit();