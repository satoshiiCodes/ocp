<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config/db_config.php';

// Include DOMPDF library
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if PR ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('PR ID is required');
}

$pr_id = $_GET['id'];

// Function to format user name (from first file)
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

// Function to format employee name (from first file)
function formatEmployeeName($firstname, $middlename, $lastname, $suffix) {
    $nameParts = [];
    if (!empty($firstname)) $nameParts[] = $firstname;
    if (!empty($middlename)) $nameParts[] = substr($middlename, 0, 1) . '.';
    if (!empty($lastname)) $nameParts[] = $lastname;
    if (!empty($suffix)) $nameParts[] = $suffix;
    
    return !empty($nameParts) ? implode(' ', $nameParts) : 'N/A';
}

// Fetch PR details
try {
    // First, check if there's a job order for this PR
    $joStmt = $pdo->prepare("
        SELECT * FROM spare_parts_job_orders 
        WHERE pr_id = ?
    ");
    $joStmt->execute([$pr_id]);
    $job_order = $joStmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch PR details with driver/operator information
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
               u.firstname, u.middlename, u.lastname, u.suffix, u.department,
               v.vehicle_name, v.plate_number,
               e.equipment_name,
               d.firstname as driver_firstname, 
               d.middlename as driver_middlename, 
               d.lastname as driver_lastname, 
               d.suffix as driver_suffix
        FROM spare_parts_pr pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN vehicles v ON pr.vehicle_id = v.id
        LEFT JOIN equipment e ON pr.equipment_id = e.id
        LEFT JOIN employee d ON pr.driver_id = d.id
        WHERE pr.id = ? AND pr.request_type = 'issue'
    ");
    $prStmt->execute([$pr_id]);
    $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pr) {
        die('Spare Parts Issue Purchase Request not found.');
    }
    
    // Initialize items array
    $items = [];
    $total_quantity = 0;
    
    // If job order exists, fetch items from job_order_items
    if ($job_order) {
        $itemsStmt = $pdo->prepare("
            SELECT joi.*, 
                   sp.part_number, sp.part_name,
                   sp.unit_of_measure,
                   spc.category_name,
                   jo.job_order_number, jo.technician, jo.purpose
            FROM spare_parts_job_order_items joi
            LEFT JOIN spare_parts sp ON joi.part_id = sp.id
            LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
            LEFT JOIN spare_parts_job_orders jo ON joi.job_order_id = jo.id
            WHERE joi.job_order_id = ?
        ");
        $itemsStmt->execute([$job_order['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Use job order technician if available
        if (!empty($job_order['technician'])) {
            $pr['technician'] = $job_order['technician'];
        }
    } 
    
    // If no job order items found or no job order, fetch from PR items
    if (empty($items)) {
        $itemsStmt = $pdo->prepare("
            SELECT pri.*, 
                   sp.part_number, sp.part_name,
                   sp.unit_of_measure,
                   spc.category_name,
                   NULL as job_order_id, NULL as status, NULL as purpose
            FROM spare_parts_pr_items pri
            LEFT JOIN spare_parts sp ON pri.part_id = sp.id
            LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
            WHERE pri.pr_id = ?
        ");
        $itemsStmt->execute([$pr_id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($items)) {
        die('No items found for this purchase request.');
    }
    
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
    
    // Get current date
    $current_date = date('m-d-Y');
    
    // Format requester name
    $requester_name = formatEmployeeName(
        $pr['firstname'] ?? '',
        $pr['middlename'] ?? '',
        $pr['lastname'] ?? '',
        $pr['suffix'] ?? ''
    );
    
    // Format driver/operator name
    $driver_name = formatEmployeeName(
        $pr['driver_firstname'] ?? '',
        $pr['driver_middlename'] ?? '',
        $pr['driver_lastname'] ?? '',
        $pr['driver_suffix'] ?? ''
    );
    
    // Format vehicle/equipment display - FIXED: Should display like "Dumtruck (HDN 1026)"
    $vehicle_equipment = 'N/A';
    if (!empty($pr['vehicle_name'])) {
        $vehicle_equipment = $pr['vehicle_name'];
        if (!empty($pr['plate_number'])) {
            $vehicle_equipment .= ' (' . $pr['plate_number'] . ')';
        }
    } elseif (!empty($pr['equipment_name'])) {
        $vehicle_equipment = $pr['equipment_name'];
    }
    
    // Get job order number if exists
    $job_order_number = $job_order ? $job_order['job_order_number'] : $pr['pr_number'];
    
    // Get technician from various sources
    $technician = $pr['technician'] ?? ($job_order['technician'] ?? 'N/A');
    
    // Get purpose
    $purpose = $pr['purpose'] ?? ($job_order['purpose'] ?? 'N/A');
    
} catch (PDOException $e) {
    die('Error fetching details: ' . $e->getMessage());
}

// Calculate total quantity
foreach ($items as $item) {
    $total_quantity += $item['quantity'];
}

// Generate HTML content with same template as generate_spare_parts_ws_pdf.php
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Job Order Slip - ' . htmlspecialchars($job_order_number) . '</title>
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
        .info-prob {
            margin-top: 5px;
            width: 100%;
            font-size: 10px;
            color: #ff0000;
        }
        .info-diagnosis {
            margin-top: 5px;
            width: 100%;
            font-size: 10px;
            margin-bottom: 70px;
            color: #ff0000;
        }
        .info-duration {
            margin-top: 5px;
            width: 100%;
            font-size: 10px;
            padding-top: 3px;
            padding-bottom: 3px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
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
        .signature-title-prepby {
            font-size: 9px;
            color: #555;
            margin-left: 155px;
        }
        .signature-title {
            font-size: 9px;
            color: #555;
            text-align: center;
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
            margin-top: 3px;
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
        .info-badge {
            background-color: ' . ($job_order ? '#e8f5e8' : '#fff3cd') . ';
            border: 1px solid ' . ($job_order ? '#c3e6cb' : '#ffeeba') . ';
            color: ' . ($job_order ? '#155724' : '#856404') . ';
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
        }
        .prepared-by-row {
            margin-top: 30px;
            margin-bottom: 5px;
            padding-bottom: 5px;
            width: 100%;
            font-size: 11px;
            text-align: left;
            border-bottom: 1px solid #000;
        }
        .prepared-by-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            width: 300px;
            margin-left: 10px;
            margin-right: 10px;
            vertical-align: middle;
            text-align: center;
            font-weight: bold;
        }
        .prepared-by-label {
            font-weight: bold;
            display: inline-block;
        }
    </style>
</head>
<body>';

$html .= '
    <div class="header">
        <div class="logo-container">
            <img src="' . $logo_src . '" class="company-logo" alt="OCP Logo">
            <div class="company-info">
                <div class="company-name">OCP CONSTRUCTION</div>
                <div class="document-title">JOB ORDER SLIP</div>';
$html .= '
            </div>
        </div>
    </div>';
    
$html .= '
    <div class="info-container">
        <div class="info-left">
            <span class="info-label">Date:</span>
            <span class="info-value date-value">' . $current_date . '</span>
        </div>
        <div class="info-right">
            <span class="info-label">JO Control No:</span>
            <span class="info-value ws-value">' . htmlspecialchars($job_order_number) . '</span>
        </div>
    </div>
    <div class="info-container">
        <div class="info-left">
            <span class="info-label">Vehicle/Equipment:</span>
            <span class="info-value date-value">' . htmlspecialchars($vehicle_equipment) . '</span>
        </div>
        <div class="info-right">
            <span class="info-label">Driver/Operator:</span>
            <span class="info-value date-value">' . htmlspecialchars($driver_name) . '</span>
        </div>
    </div>
    <div class="info-prob">
        <div class="info-left">
            <span class="info-label">PROBLEMS:</span>
        </div>
    </div>

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
        
        $counter = 1;
        $item_count = count($items);
        
        if ($item_count > 0) {
            foreach ($items as $item) {
                $html .= '
                <tr>
                    <td class="text-center">' . $counter . '</td>
                    <td class="text-center">' . htmlspecialchars($item['part_number'] ?? 'N/A') . '</td>
                    <td class="description-cell">
                        <div class="description-content">' . htmlspecialchars($item['part_name'] ?? 'N/A') . '</div>';
                        
                $html .= '</td>
                    <td class="text-center">' . number_format($item['quantity'], 2) . '</td>
                    <td class="text-center">' . htmlspecialchars($item['unit_of_measure'] ?? 'pc') . '</td>';
                    
                    // Add purpose column from job orders table
                    if ($job_order) {
                        // Get purpose from job_order (already fetched)
                        $job_order_purpose = $job_order['purpose'] ?? 'N/A';
                        $html .= '<td class="text-center">' . htmlspecialchars($job_order_purpose) . '</td>';
                    } else {
                        $html .= '<td class="text-center">' . htmlspecialchars($purpose) . '</td>';
                    }
                    
                $html .= '
                </tr>';
                $counter++;
            }
            
            // Add empty rows to reach at least 5 rows total
            $empty_rows_needed = max(0, 5 - $item_count);
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
            // If no items, add 5 empty rows
            for ($i = 1; $i <= 5; $i++) {
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
    
    // Add remarks section
    $remarks = $pr['remarks'] ?? ($job_order['remarks'] ?? 'No remarks');
    if (!empty($remarks)) {
        $html .= '
    <div class="remarks-box">
        <strong>Remarks:</strong><br>
        ' . nl2br(htmlspecialchars($remarks)) . '
    </div>';
    }

    // Add "Prepared by:" line below the table (FIX #1)
    $html .= '
    <!-- Prepared by Line -->
    <div class="prepared-by-row">
        <span class="prepared-by-label">Prepared by:</span>
        <span class="prepared-by-line">' . strtoupper($requester_name) . '</span>
        <div class="signature-title-prepby"> PRINTED NAME AND SIGNATURE </div>
    </div>

    <div class="info-diagnosis">
        <div class="info-left">
            <span class="info-label">DIAGNOSIS:</span>
        </div>
    </div>

    <div class="info-duration">
        <div class="info-left">
            <span class="info-label">DURATION:</span>
        </div>
    </div>


    ';

    // Add signature row with 3 columns (same as withdrawal slip)
    $html .= '
    <!-- Signature Row with 3 Columns -->
    <div class="signature-row">
        <!-- Approved By Column -->
        <div class="signature-column">
            <div class="signature-label">Recommended By:</div>
            <div class="signature-line">
                <div class="signature-name"> WILMER VALENCIA </div>
            </div>
            <div class="signature-title"> CHIEF MECHANIC </div>
        </div>
        
        
        
        <!-- Received By Column (changed from Checked By to Received By for JO) -->
        <div class="signature-column">
            
        </div>

        <!-- Prepared By Column -->
        <div class="signature-column">
            <div class="signature-label">Approved By:</div>
            <div class="signature-line">
                <div class="signature-name">OSCAR C. PACLIBON</div>
            </div>
            <div class="signature-title"> MANAGER </div>
        </div>
    </div>


    <div class="sign-container">
    </div>
    
</body>
</html>';

// Configure DOMPDF options - same as first file
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('chroot', __DIR__);

// Create DOMPDF instance
$dompdf = new Dompdf($options);

// Set paper size to Legal (8.5 x 14 inches) - same as first file
$dompdf->setPaper('legal', 'portrait');

// Load HTML content
$dompdf->loadHtml($html);

// Render PDF
$dompdf->render();

// Generate PDF filename
$filename = 'JO_' . htmlspecialchars($job_order_number) . '.pdf';

// Output PDF - Open in browser instead of downloading (same as first file)
$dompdf->stream($filename, [
    'Attachment' => false,
    'compress' => true
]);

exit();
?>