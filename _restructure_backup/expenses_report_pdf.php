<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Require Composer's autoloader for Dompdf
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Set timezone to Manila/Philippines
date_default_timezone_set('Asia/Manila');

// Function to get current cash on hand balance
function getCurrentCashBalance($pdo) {
    try {
        $stmt = $pdo->query("SELECT balance FROM cash_on_hand ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? floatval($result['balance']) : 0.00;
    } catch(PDOException $e) {
        error_log("Error getting cash balance: " . $e->getMessage());
        return 0.00;
    }
}

// Get filter parameters from URL
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

try {
    // Build query with filters - Updated to include person_name field
    $query = "
        SELECT e.*, 
               et.expense_name as expense_type_name,
               emp.firstname as emp_firstname, emp.lastname as emp_lastname,
               ceo.firstname as ceo_firstname, ceo.middlename as ceo_middlename, ceo.lastname as ceo_lastname, ceo.suffix as ceo_suffix
        FROM expenses e 
        LEFT JOIN expenses_type et ON e.expense_type_id = et.id
        LEFT JOIN employee emp ON e.employee_id = emp.id
        LEFT JOIN users ceo ON e.ceo_id = ceo.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filter_type)) {
        $query .= " AND e.expense_type_id = :expense_type_id";
        $params[':expense_type_id'] = $filter_type;
    }
    
    if (!empty($filter_start_date)) {
        $query .= " AND DATE(e.expense_date) >= :start_date";
        $params[':start_date'] = $filter_start_date;
    }
    
    if (!empty($filter_end_date)) {
        $query .= " AND DATE(e.expense_date) <= :end_date";
        $params[':end_date'] = $filter_end_date;
    }
    
    $query .= " ORDER BY e.expense_date DESC, e.created_at DESC";
    
    $expensesStmt = $pdo->prepare($query);
    $expensesStmt->execute($params);
    $expenses = $expensesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total amount
    $total_amount = 0;
    foreach ($expenses as $expense) {
        $total_amount += $expense['amount'];
    }
    
    // Get current cash on hand balance
    $current_cash_balance = getCurrentCashBalance($pdo);
    
    // Get user info for report generation
    $user_id = $_SESSION['user_id'];
    $userStmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = :id");
    $userStmt->bindParam(':id', $user_id);
    $userStmt->execute();
    $current_user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get expense type name for filter display if filter_type is set
    $filter_type_name = '';
    if (!empty($filter_type)) {
        $typeStmt = $pdo->prepare("SELECT expense_name FROM expenses_type WHERE id = :id");
        $typeStmt->bindParam(':id', $filter_type);
        $typeStmt->execute();
        $typeData = $typeStmt->fetch(PDO::FETCH_ASSOC);
        $filter_type_name = $typeData['expense_name'] ?? '';
    }
    
} catch(PDOException $e) {
    $expenses = [];
    $total_amount = 0;
    $current_cash_balance = 0;
    $filter_type_name = '';
}

// Get current Manila date and time for display
$manila_date_time = date('F j, Y, g:i a');
$manila_date_only = date('Y-m-d H:i:s');

// Set target total rows (including data rows + blank rows)
$target_total_rows = 10; // You can adjust this number (e.g., 15, 20, 25, 30)

// Calculate how many blank rows to add
$data_row_count = count($expenses);
$blank_rows_needed = max(0, $target_total_rows - $data_row_count);

// Read and encode the logo image
$logo_path = 'img/logo/OCP.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = base64_encode($logo_data);
}

// Build the HTML content for PDF - REMOVED SIGNATURE COLUMN
$html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Report - OCP Construction</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            padding: 20px;
            background: #fff;
        }
        .logo-header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #000000;
            padding-bottom: 25px;
            margin-bottom: 20px;
            position: relative;
        }
        .logo-container {
            position: absolute;
            margin-top: -75px;
            margin-left: 450px;
        }
        .logo-container img {
            max-width: 90px;
            height: auto;
        }
        .header {
            flex: 1;
            text-align: center;
        }
        .header h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
            font-weight: bold;
        }
        .header h3 {
            font-size: 14px;
            margin: 0 0 5px 0;
            font-weight: normal;
        }
        .header p {
            font-size: 10px;
            margin: 2px 0;
            color: #666;
        }
        .filter-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 10px;
        }
        .filter-info strong {
            color: #333;
        }
        .summary-section {
            margin-bottom: 20px;
            width: 100%;
            display: table;
            border-collapse: collapse;
        }
        .summary-row {
            display: table-row;
        }
        .summary-box {
            display: table-cell;
            width: 33.33%;
            padding: 12px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
        }
        .summary-box .label {
            font-size: 11px;
            margin-bottom: 5px;
            font-weight: normal;
        }
        .summary-box .value {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
        }
        .summary-box .entries {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        .summary-box .cash-balance {
            font-size: 18px;
            font-weight: bold;
            color: #17a2b8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9px;
        }
        th {
            background-color: #343a40;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #454d55;
        }
        td {
            padding: 8px 5px;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .amount {
            text-align: right;
            font-weight: bold;
        }
        .total-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .total-row td {
            border-top: 2px solid #333;
        }
        .description-cell {
            max-width: 250px;
            word-wrap: break-word;
        }
        .manila-time {
            font-size: 9px;
            color: #666;
            font-style: italic;
        }
        .person-name-tag {
            color: #d51f1f;
        }
        .signature-container {
            width: 100%;
            page-break-inside: avoid;
            margin-top: 30px;
        }
        .signature-row {
            width: 100%;
            display: table;
            table-layout: fixed;
            border-collapse: collapse;
        }
        .signature-column {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .signature-label {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 25px;
        }
        .signature-name {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .signature-underline {
            border-bottom: 1px solid #000;
            width: 70%;
            margin: 0 auto 5px auto;
            padding-bottom: 5px;
        }
        .signature-title {
            font-size: 10px;
            margin-bottom: 5px;
            color: #333;
        }
        .signature-date {
            font-size: 10px;
            font-style: italic;
        }
        .signature-date-label {
            font-size: 10px;
        }
        .blank-row {
            background-color: #ffffff;
        }
        .blank-row td {
            padding: 8px 5px;
            color: #999;
            font-style: italic;
        }
        .blank-row-placeholder {
            color: #ccc;
            font-style: italic;
        }
        .footer-info {
            width: 100%;
            margin-top: 25px;
            font-size: 9px;
            position: relative;
            bottom: 0;
        }
        .footer-row {
            width: 100%;
            display: table;
            table-layout: fixed;
            border-collapse: collapse;
        }
        .footer-column1 {
            display: table-cell;
            text-align: left;
            vertical-align: top;
            padding: 5px 10px;
            color: red;
            font-weight: normal;
        }
        .footer-column2 {
            display: table-cell;
            text-align: center;
            vertical-align: top;
            padding: 5px 10px;
            color: red;
            font-weight: normal;
        }
        .footer-column3 {
            display: table-cell;
            text-align: right;
            vertical-align: top;
            padding: 5px 10px;
            color: red;
            font-weight: normal;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    
    <div class="logo-header-container">
        <div class="header">
            <h1>OCP CONSTRUCTION</h1>
            <h3>Expenses Report</h3>
            <p>Generated on: ' . $manila_date_time . '</p>
        </div>
        <div class="logo-container">
            <img src="data:image/png;base64,' . $logo_base64 . '" alt="OCP Logo" />
        </div>
    </div>';
    
    // Add filter information if filters are applied
    if (!empty($filter_type) || !empty($filter_start_date) || !empty($filter_end_date)) {
        $html .= '<div class="filter-info">';
        $html .= '<strong>Applied Filters:</strong> ';
        $filter_texts = [];
        if (!empty($filter_type) && !empty($filter_type_name)) {
            $filter_texts[] = 'Type: ' . htmlspecialchars($filter_type_name);
        }
        if (!empty($filter_start_date)) {
            $filter_texts[] = 'From: ' . date('F j, Y', strtotime($filter_start_date));
        }
        if (!empty($filter_end_date)) {
            $filter_texts[] = 'To: ' . date('F j, Y', strtotime($filter_end_date));
        }
        $html .= implode(' | ', $filter_texts);
        $html .= '</div>';
    }
    
    // Summary section - Now with 3 columns including Current Cash on Hand
    $html .= '
    <div class="summary-section">
        <div class="summary-row">
            <div class="summary-box">
                <div class="label">Total Expenses Amount</div>
                <div class="value">' . number_format($total_amount, 2) . '</div>
            </div>
            <div class="summary-box">
                <div class="label">Total Number of Entries</div>
                <div class="entries">' . count($expenses) . '</div>
            </div>
            <div class="summary-box">
                <div class="label">Current Cash on Hand</div>
                <div class="cash-balance">' . number_format($current_cash_balance, 2) . '</div>
            </div>
        </div>
    </div>';
    
    // Expenses table - REMOVED SIGNATURE COLUMN
    if (!empty($expenses)) {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th width="12%">Date</th>
                    <th width="12%">Expense Type</th>
                    <th width="12%">Amount</th>
                    <th width="40%">Description</th>
                    <th width="24%">Person</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($expenses as $expense) {
            $html .= '<tr>';
            $html .= '<td>' . date('m-d-Y', strtotime($expense['expense_date'])) . '</td>';
            $html .= '<td>' . htmlspecialchars($expense['expense_type_name'] ?? 'N/A') . '</td>';
            $html .= '<td class="amount">' . number_format($expense['amount'], 2) . '</td>';
            $html .= '<td class="description-cell">' . htmlspecialchars($expense['description'] ?? 'N/A') . '</td>';
            $html .= '<td>';
            
            // Determine which person to display (person_name takes precedence if set, otherwise employee/ceo)
            if (!empty($expense['person_name'])) {
                // Manually entered person name
                $html .= '<span class="person-name-tag">' . htmlspecialchars($expense['person_name']) . '</span>';
            } elseif (!empty($expense['emp_firstname'])) {
                // Employee from dropdown
                $html .= htmlspecialchars($expense['emp_firstname'] . ' ' . $expense['emp_lastname']);
            } elseif (!empty($expense['ceo_firstname'])) {
                // CEO from dropdown
                $ceo_name = $expense['ceo_firstname'];
                if (!empty($expense['ceo_middlename'])) {
                    $ceo_name .= ' ' . substr($expense['ceo_middlename'], 0, 1) . '.';
                }
                $ceo_name .= ' ' . $expense['ceo_lastname'];
                if (!empty($expense['ceo_suffix'])) {
                    $ceo_name .= ' ' . $expense['ceo_suffix'];
                }
                $html .= htmlspecialchars($ceo_name);
            } else {
                $html .= '<span style="color: #999;">—</span>';
            }
            
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        // Add dynamic blank rows based on how many are needed
        if ($blank_rows_needed > 0) {
            for ($i = 1; $i <= $blank_rows_needed; $i++) {
                $html .= '<tr class="blank-row">';
                $html .= '<td>&nbsp;</td>'; // Date
                $html .= '<td>&nbsp;</td>'; // Expense Type
                $html .= '<td class="amount">&nbsp;</td>'; // Amount
                $html .= '<td class="description-cell blank-row-placeholder">';
                // Add placeholder text only on first blank row as indicator
                if ($i == 1 && $blank_rows_needed > 0) {
                    $html .= '';
                } else {
                    $html .= '&nbsp;';
                }
                $html .= '</td>';
                $html .= '<td>&nbsp;</td>'; // Person
                $html .= '</tr>';
            }
        }
        
        // Total row - adjusted colspan since signature column is removed
        $html .= '<tr class="total-row">';
        $html .= '<td colspan="2" style="text-align: right;"><strong>GRAND TOTAL:</strong></td>';
        $html .= '<td class="amount"><strong>' . number_format($total_amount, 2) . '</strong></td>';
        $html .= '<td colspan="2"></td>';
        $html .= '</tr>';
        
        $html .= '
            </tbody>
        </table>';
    } else {
        // If no data, show all rows as blank (up to target total)
        $html .= '
        <table>
            <thead>
                <tr>
                    <th width="12%">Date</th>
                    <th width="12%">Expense Type</th>
                    <th width="12%">Amount</th>
                    <th width="40%">Description</th>
                    <th width="24%">Person</th>
                </tr>
            </thead>
            <tbody>';
        
        // Add target total rows as blank when no data
        for ($i = 1; $i <= $target_total_rows; $i++) {
            $html .= '<tr class="blank-row">';
            $html .= '<td>&nbsp;</td>'; // Date
            $html .= '<td>&nbsp;</td>'; // Expense Type
            $html .= '<td class="amount">&nbsp;</td>'; // Amount
            $html .= '<td class="description-cell blank-row-placeholder">';
            // Add placeholder text only on first blank row as indicator
            if ($i == 1) {
                $html .= '<span style="color: #ccc;">(Blank rows for manual entry)</span>';
            } else {
                $html .= '&nbsp;';
            }
            $html .= '</td>';
            $html .= '<td>&nbsp;</td>'; // Person
            $html .= '</tr>';
        }
        
        // Total row with zero amount
        $html .= '<tr class="total-row">';
        $html .= '<td colspan="2" style="text-align: right;"><strong>GRAND TOTAL:</strong></td>';
        $html .= '<td class="amount"><strong>0.00</strong></td>';
        $html .= '<td colspan="2"></td>';
        $html .= '</tr>';
        
        $html .= '
            </tbody>
        </table>';
    }
    
    // Signature row - Prepared by, Reviewed by, Acknowledged by with proper formatting
    $current_date = date('F j, Y');
    
    $html .= '
    <div class="signature-container">
        <div class="signature-row">
            <!-- Prepared by Column -->
            <div class="signature-column">
                <div class="signature-label">Prepared by:</div>
                <div class="signature-underline"></div>
                <div class="signature-name">CAMILLE D. ILAC</div>
                <div class="signature-title">Petty Cash Custodian</div>
                <div class="signature-date">Date: ____________</div>
            </div>
            
            <!-- Reviewed by Column -->
            <div class="signature-column">
                <div class="signature-label">Reviewed by:</div>
                <div class="signature-underline"></div>
                <div class="signature-name">DENVER JAY P. PACLIBON</div>
                <div class="signature-title">Asst. Project Manager</div>
                <div class="signature-date">Date: ____________</div>
            </div>
            
            <!-- Acknowledged by Column -->
            <div class="signature-column">
                <div class="signature-label">Acknowledged by:</div>
                <div class="signature-underline"></div>
                <div class="signature-name">ZENAIDA P. PACLIBON</div>
                <div class="signature-title">Finance Manager</div>
                <div class="signature-date">Date: ____________</div>
            </div>
        </div>
    </div>
    
    <!-- Footer with FM-PUR-09 information -->
    <div class="footer-info">
        <div class="footer-row">
            <div class="footer-column1">FM-PUR-09</div>
            <div class="footer-column2">REV-01</div>
            <div class="footer-column3">02-28-26</div>
        </div>
    </div>
    
</body>
</html>';

// Create PDF options
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

// Initialize Dompdf
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Set paper size to Long Bond Paper (8.5 x 13 inches)
// Dompdf uses points but we'll set in inches for clarity
$dompdf->setPaper('legal', 'landscape'); // Legal is 8.5 x 14 inches, close to long bond

// Render PDF
$dompdf->render();

// Generate filename with date and filters (using Manila time for filename)
$filename = 'Expenses_Report_';
if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $filename .= $filter_start_date . '_to_' . $filter_end_date;
} elseif (!empty($filter_start_date)) {
    $filename .= 'from_' . $filter_start_date;
} elseif (!empty($filter_end_date)) {
    $filename .= 'until_' . $filter_end_date;
} else {
    $filename .= 'All_Records';
}
$filename .= '_' . date('Y-m-d_His') . '.pdf';

// Stream PDF to browser
$dompdf->stream($filename, array('Attachment' => false));
exit();
?>