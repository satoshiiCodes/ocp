<?php
// generate_spare_parts_ws_pdf.php
session_start();
require_once 'includes/db_config.php';
require_once 'vendor/autoload.php'; // Make sure Dompdf is installed via composer

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get PR ID from URL
$pr_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pr_id <= 0) {
    die('Invalid Withdrawal Slip ID');
}

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

try {
    // First, get the withdrawal slip details from the PR
    $wsStmt = $pdo->prepare("
        SELECT 
            ws.*,
            -- Requester/Prepared By info (from users table)
            req.firstname as req_firstname,
            req.middlename as req_middlename,
            req.lastname as req_lastname,
            req.suffix as req_suffix,
            req.department as req_department,
            -- Employee (Receiver) info (from employee table)
            emp.firstname as emp_firstname,
            emp.middlename as emp_middlename,
            emp.lastname as emp_lastname,
            emp.suffix as emp_suffix,
            emp.position as emp_position,
            emp.employee_id as emp_employee_id,
            -- PR info
            pr.pr_number,
            pr.request_type,
            pr.status as pr_status,
            pr.remarks as pr_remarks
        FROM spare_parts_withdrawal_slips ws
        LEFT JOIN users req ON ws.requested_by = req.id
        LEFT JOIN employee emp ON ws.employee_id = emp.id
        LEFT JOIN spare_parts_pr pr ON ws.pr_id = pr.id
        WHERE ws.pr_id = ? AND pr.request_type = 'issue_materials'
    ");
    $wsStmt->execute([$pr_id]);
    $withdrawal_slip = $wsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$withdrawal_slip) {
        die('Withdrawal Slip not found for this PR ID');
    }

    $withdrawal_slip_id = $withdrawal_slip['id'];

    // Fetch withdrawal slip items with part details including unit_of_measure
    $itemsStmt = $pdo->prepare("
        SELECT 
            wsi.*,
            sp.part_number as item_code,
            sp.part_name as item_name,
            sp.unit_of_measure,
            spc.category_name,
            pri.quantity as original_quantity,
            pri.unit_cost,
            ws.purpose
        FROM spare_parts_withdrawal_slip_items wsi
        INNER JOIN spare_parts_pr_items pri ON wsi.pr_item_id = pri.id
        INNER JOIN spare_parts sp ON wsi.part_id = sp.id
        LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_withdrawal_slips ws ON wsi.withdrawal_slip_id = ws.id
        WHERE wsi.withdrawal_slip_id = ?
        ORDER BY spc.category_name, sp.part_name
    ");
    $itemsStmt->execute([$withdrawal_slip_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format employee name
    function formatEmployeeName($firstname, $middlename, $lastname, $suffix) {
        $nameParts = [];
        if (!empty($firstname)) $nameParts[] = $firstname;
        if (!empty($middlename)) $nameParts[] = substr($middlename, 0, 1) . '.';
        if (!empty($lastname)) $nameParts[] = $lastname;
        if (!empty($suffix)) $nameParts[] = $suffix;
        
        return !empty($nameParts) ? implode(' ', $nameParts) : 'N/A';
    }
    
    $received_by = formatEmployeeName(
        $withdrawal_slip['emp_firstname'] ?? '', 
        $withdrawal_slip['emp_middlename'] ?? '', 
        $withdrawal_slip['emp_lastname'] ?? '', 
        $withdrawal_slip['emp_suffix'] ?? ''
    );

    // Format the employee name with proper format (Josue B. Barangan III)
    $employee_display = formatEmployeeName(
        $withdrawal_slip['emp_firstname'] ?? '',
        $withdrawal_slip['emp_middlename'] ?? '',
        $withdrawal_slip['emp_lastname'] ?? '',
        $withdrawal_slip['emp_suffix'] ?? ''
    );

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

    // Get warehouse manager for checked by
    $warehouseManagerStmt = $pdo->prepare("
        SELECT firstname, middlename, lastname, suffix, position
        FROM users 
        WHERE department = 'Warehouse' AND accounttype = 'Admin'
        ORDER BY id ASC
        LIMIT 1
    ");

    // Format dates
    $withdrawal_date = !empty($withdrawal_slip['withdrawal_date']) 
        ? date('F d, Y', strtotime($withdrawal_slip['withdrawal_date'])) 
        : date('F d, Y');

    // Get purpose from withdrawal slip
    $purpose = $withdrawal_slip['purpose'] ?? '';

    // Generate HTML content with template from generate_ws_pdf.php (same styling)
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Withdrawal Slip - ' . htmlspecialchars($withdrawal_slip['withdrawal_slip_number']) . '</title>
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
    <body>';

    // Add watermark if not approved or released
    if (!in_array(strtolower($withdrawal_slip['status']), ['approved', 'released'])) {
        $html .= '<div class="watermark">' . strtoupper($withdrawal_slip['status'] ?? 'DRAFT') . '</div>';
    }

    $html .= '
        <div class="header">
            <div class="logo-container">
                <img src="' . $logo_src . '" class="company-logo" alt="OCP Logo">
                <div class="company-info">
                    <div class="company-name">OCP CONSTRUCTION</div>
                    <div class="document-title">WITHDRAWAL SLIP (MATERIALS)</div>
                </div>
            </div>
        </div>

        <div class="info-container">
            <div class="info-left">
                <span class="info-label">Date:</span>
                <span class="info-value date-value">' . $withdrawal_date . '</span>
            </div>
            <div class="info-right">
                <span class="info-label">WS Control No:</span>
                <span class="info-value ws-value">' . htmlspecialchars($withdrawal_slip['withdrawal_slip_number']) . '</span>
            </div>
        </div>

        <div class="info-container">
            <div class="info-left">
                <span class="info-label">Employee:</span>
                <span class="info-value date-value">' . htmlspecialchars($employee_display) . '</span>
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
                    <td class="text-center">' . htmlspecialchars($item['unit_of_measure'] ?? 'pc') . '</td>
                    <td class="text-center">' . htmlspecialchars($purpose) . '</td>
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
                    <div class="signature-name">LESTER R. ALIPIO</div>
                </div>
                <div class="signature-title"> PURCHASER </div>
            </div>
            
            <!-- Checked By Column -->
            <div class="signature-column">
                <div class="signature-label">Checked by:</div>
                <div class="signature-line">
                    <div class="signature-name">Christopher D. Balmores</div>
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
                <span class="fm-pur-right">' . date('m/d/Y') . '</span>
            </div>
        </div>

        <div class="sign-container">
        </div>
        
    </body>
    </html>';

    // Create PDF options - exactly like the first file
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

    // Generate PDF filename
    $filename = 'WS_' . htmlspecialchars($withdrawal_slip['withdrawal_slip_number']) . '.pdf';

    // Output PDF - Open in browser instead of downloading
    $dompdf->stream($filename, [
        'Attachment' => false, // Changed to false to open in browser
        'compress' => true
    ]);

    exit();

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}
?>