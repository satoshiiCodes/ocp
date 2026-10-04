<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config/db_config.php';

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
    
    // Total amount, and the part left out of it.
    //
    // An expense on an approval-required type is not settled until all three signatures are in,
    // so it is listed but not counted. pending_amount records what was excluded, so the grand
    // total can say so rather than appearing to disagree with the rows above it.
    $total_amount = 0;
    $pending_amount = 0;
    $pending_count = 0;
    foreach ($expenses as $expense) {
        $ocp_status = $expense['approval_status'] ?? 'not_required';
        if ($ocp_status === 'pending' || $ocp_status === 'partially_signed') {
            $pending_amount += $expense['amount'];
            $pending_count++;
            continue;
        }
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
$manila_date_time = date('m-d-Y, g:i a');
$manila_date_only = date('Y-m-d H:i:s');

// Set target total rows (including data rows + blank rows)
$target_total_rows = 10; // You can adjust this number (e.g., 15, 20, 25, 30)

// Calculate how many blank rows to add
$data_row_count = count($expenses);
$blank_rows_needed = max(0, $target_total_rows - $data_row_count);

// Read and encode the logo image
$logo_path = __DIR__ . '/assets/images/logo/OCP.png';
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
        /* The signature area: the printed name with the handwritten signature over it, the way a
           signed voucher looks - the signature crosses the name rather than sitting above it.
           The name is drawn after the image in the document order, so it stays legible on top.

           Centring: a CSS comment follows, so this note is here instead.

           The signature is placed by left:50% on the column axis with a negative margin of half
           its own width, and its width is computed per column from the printed name (see
           ocp_report_text_width). That puts the centre of the signature on the same axis as the
           centred name however long the name is. The image is also cropped to its ink first
           (ocp_report_trim_signature), because the pad stores a full canvas: centring that
           uncropped would centre the empty padding and leave the handwriting looking shifted.

           Vertical: the top offset is what sets the overlap, and it is positive - a positive
           offset moves the image DOWN. At 20px the signature sat centred on the name baseline and
           read low, so its centre has been raised by about 11pt since (20px -> 4px). */
        .signature-pad-area {
            position: relative;
            height: 46px;
            width: 100%;
        }
        .signature-pad-area .signature-image {
            position: absolute;
            /* left:50% plus the negative margin set inline per column puts the centre of the image
               on the column axis, which is where the centred name sits. A translateX(-50%) would
               be the usual way to express this, but this Dompdf does not apply transforms. */
            left: 50%;
            top: 4px;
            height: 44px;
        }
        .signature-pad-area .signature-name {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            margin-top: 0;
        }
        /* The rule the name sits on, printed under both. */
        .signature-rule-under {
            border-bottom: 1px solid #000;
            width: 80%;
            margin: 0 auto 4px auto;
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
        /* The captured signature image. Bounded in height so a tall pad does not push the
           block onto a second page, and inline so it sits on the line above the name. */
        .signature-image {
            display: block;
            height: 40px;
            max-width: 80%;
            margin: 0 auto 2px auto;
        }
        .signature-pending {
            font-size: 9px;
            color: #888;
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
            $filter_texts[] = 'From: ' . date('m-d-Y', strtotime($filter_start_date));
        }
        if (!empty($filter_end_date)) {
            $filter_texts[] = 'To: ' . date('m-d-Y', strtotime($filter_end_date));
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
        $html .= '<td colspan="2" style="font-size: 9px; color: #666;">'
               . ($pending_amount > 0
                    ? 'excludes ' . number_format($pending_amount, 2) . ' awaiting approval (' . $pending_count . ')'
                    : '')
               . '</td>';
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
    
    // Signature row: Prepared by, Reviewed by, Acknowledged by.
    //
    // This report covers every expense in the selected range, so each column shows the most
    // recent signature of that kind among the expenses listed - the person who last signed in
    // that role for this period. A step with no signature keeps the blank rule, so the page can
    // still be printed and signed on paper.
    //
    // The names and signatures were hardcoded placeholders before this.
    $current_date = date('m-d-Y');

    // Latest signature per step, across the expenses on the report.
    $ocp_latest = ['reviewed' => null, 'prepared' => null, 'acknowledged' => null];
    foreach ($expenses as $exp_row) {
        foreach ($ocp_latest as $step => $current) {
            $atField  = $step . '_at';
            $sigField = $step . '_signature';
            if (!empty($exp_row[$atField]) && !empty($exp_row[$sigField])
                && ($current === null || strtotime($exp_row[$atField]) > strtotime($current[$atField]))) {
                $ocp_latest[$step] = $exp_row;
            }
        }
    }

    // id => name / position, for whoever signed each step.
    $ocp_signers = [];
    $signer_ids = [];
    foreach (['reviewed_by', 'prepared_by', 'acknowledged_by'] as $f) {
        foreach ($expenses as $exp_row) {
            if (!empty($exp_row[$f])) { $signer_ids[] = (int) $exp_row[$f]; }
        }
    }
    $signer_ids = array_values(array_unique($signer_ids));
    if ($signer_ids) {
        $in = implode(',', array_fill(0, count($signer_ids), '?'));
        $signerStmt = $pdo->prepare("SELECT id, firstname, middlename, lastname, suffix, position FROM users WHERE id IN ($in)");
        $signerStmt->execute($signer_ids);
        foreach ($signerStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $n = $u['firstname'];
            if (!empty($u['middlename'])) { $n .= ' ' . substr($u['middlename'], 0, 1) . '.'; }
            $n .= ' ' . $u['lastname'];
            if (!empty($u['suffix'])) { $n .= ' ' . $u['suffix']; }
            $ocp_signers[(int) $u['id']] = ['name' => $n, 'position' => $u['position']];
        }
    }

    // --- helpers for placing the signature on the name -----------------------
    //
    // Two measurements are needed to centre a signature on its printed name:
    //
    //   how wide the name renders, and where the signature's ink actually is inside its canvas.
    //
    // The pad stores a full canvas, so a signature drawn in the middle of it carries transparent
    // padding on both sides. Drawing that canvas centred on the name centres the PADDING, not the
    // ink, which is why the signature looked shifted. Cropping to the ink and sizing the result to
    // the name's own width fixes it for any signer.

    if (!function_exists('ocp_report_text_width')) {
        /**
         * Rendered width in points of a string in the report's bold font.
         *
         * Reads the same metrics file Dompdf uses. The report sets Arial, which Dompdf resolves to
         * DejaVu Sans Bold, so these advances are the ones that end up on the page. Falls back to a
         * rough average if the file cannot be read, which only affects the centring slightly.
         */
        function ocp_report_text_width(string $text, float $pt, string $baseDir): float
        {
            static $advances = null;
            if ($advances === null) {
                $advances = [];
                $metrics = $baseDir . '/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ufm.json';
                if (is_readable($metrics)) {
                    $json = json_decode((string) file_get_contents($metrics), true);
                    if (isset($json['C']) && is_array($json['C'])) {
                        $advances = $json['C'];
                    }
                }
            }
            if (!$advances) {
                return mb_strlen($text) * 0.6 * $pt;
            }
            $units = 0.0;
            foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                $units += (float) ($advances[(string) mb_ord($ch)] ?? 0);
            }
            return $units / 1000 * $pt;
        }
    }

    if (!function_exists('ocp_report_trim_signature')) {
        /**
         * Crops a stored signature data URL to its ink, so that centring the image centres the
         * handwriting rather than the empty canvas around it.
         *
         * Returns the original string when the image cannot be read, in which case the caller falls
         * back to the plain centred placement.
         */
        function ocp_report_trim_signature(string $dataUrl): string
        {
            if (!function_exists('imagecreatefromstring')) { return $dataUrl; }
            if (strpos($dataUrl, 'data:image') !== 0) { return $dataUrl; }
            $comma = strpos($dataUrl, ',');
            if ($comma === false) { return $dataUrl; }
            $raw = base64_decode(substr($dataUrl, $comma + 1), true);
            if ($raw === false || $raw === '') { return $dataUrl; }

            $im = @imagecreatefromstring($raw);
            if (!$im) { return $dataUrl; }

            $w = imagesx($im);
            $h = imagesy($im);
            if ($w < 2 || $h < 2) { imagesavealpha($im, true); }

            // Alpha-aware ink bounds: a pixel counts as ink when it is not fully transparent.
            $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $rgba = imagecolorat($im, $x, $y);
                    $alpha = ($rgba >> 24) & 0x7F;
                    if ($alpha < 120) {           // 127 is fully transparent
                        if ($x < $minX) { $minX = $x; }
                        if ($x > $maxX) { $maxX = $x; }
                        if ($y < $minY) { $minY = $y; }
                        if ($y > $maxY) { $maxY = $y; }
                    }
                }
            }

            if ($maxX < 0 || $maxY < 0) { imagedestroy($im); return $dataUrl; }

            $cw = $maxX - $minX + 1;
            $ch = $maxY - $minY + 1;
            $out = imagecreatetruecolor($cw, $ch);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            imagecopy($out, $im, 0, 0, $minX, $minY, $cw, $ch);

            ob_start();
            imagepng($out);
            $png = ob_get_clean();
            imagedestroy($out);
            imagedestroy($im);

            if ($png === false || $png === '') { return $dataUrl; }
            return 'data:image/png;base64,' . base64_encode($png);
        }
    }

    // The caption's size in the report: font-size 10px renders as 7.5pt.
    $ocp_name_pt = 7.5;
    $ocp_col_share = 0.30;   // each column is a third of the table; capped below

    // The signature lines, in the order the printed form shows them.
    //
    // The form is the FM-PUR-09 petty cash voucher. Its three lines are Prepared by, Reviewed by and
    // Acknowledged by, and the roles that sign them are Accounting, then the CEO twice - which is
    // also the order they are signed in.
    //
    // The step key in each pair is the column prefix in the database, and those prefixes are not in
    // the same order as the form: the Prepared by line is the reviewed_* column. The pairing is
    // therefore written out explicitly rather than derived from the names.
    $ocp_signature_columns = [
        ['Prepared by',     'reviewed'],       // Accounting signs this line
        ['Reviewed by',     'prepared'],       // CEO signs this line
        ['Acknowledged by', 'acknowledged'],   // CEO signs this line
    ];

    $sig_html = '
    <div class="signature-container">
        <div class="signature-row">';
    foreach ($ocp_signature_columns as [$label, $step]) {
        $src = $ocp_latest[$step];
        $sig = $src ? ($src[$step . '_signature'] ?? '') : '';
        $signer = $src ? ($ocp_signers[(int) ($src[$step . '_by'] ?? 0)] ?? null) : null;

        // How wide this name prints. The signature is sized and placed from it, so both are
        // centred on the same point whatever the signer is called.
        $nameText = $signer ? $signer['name'] : '';
        $nameW = $nameText !== ''
            ? ocp_report_text_width($nameText, $ocp_name_pt, __DIR__)
            : 0.0;

        // The image spans a little wider than the name, the way a signature written on a line
        // spills past the printed name. Bounded so a very long name cannot overflow its column
        // and a very short one does not produce a tiny signature.
        $imgW = $nameW > 0 ? min($nameW * 1.3, 240.0) : 0.0;
        $imgW = max($imgW, 90.0);

        $sig_html .= '
            <div class="signature-column">
                <div class="signature-label">' . $label . ':</div>';

        // The name is printed whether or not anyone has signed, so the line reads correctly on an
        // unsigned copy too; the signature, when there is one, is drawn over it.
        $sig_html .= '<div class="signature-pad-area">';
        if (!empty($sig) && strpos($sig, 'data:image') === 0) {
            // Cropped to its ink, then sized and centred through the inline style: the width comes
            // from the name's own measured width, and a negative margin of half that width pulls
            // the image back so its centre lands on the column axis, which is where the centred
            // name sits. Written inline because the width differs per column.
            //
            // The basis is the signature LINE, not the printed name: the rule under each name is
            // what a signature is written on, and its centre is not the name's centre.
            //
            // Measured from the rendered PDF, the three lines run
            //   53.6..302.4 (centre 178.0), 379.6..628.4 (centre 504.0), 705.6..954.4 (centre 830.0)
            // while the names sit nearer 184, 507 and 833.
            //
            // The image is the cropped handwriting, so its centre is the ink centre, and the line is
            // 80% of the column inset by 10% - the same axis left:50% puts the image centre on. So no
            // extra nudge is needed: measured, a nudge of 0 lands all three signatures exactly on
            // their line centres, while the 6.0 that was here put them 4.5pt right of it.
            $ocp_nudge = 0.0;
            $trimmed = ocp_report_trim_signature($sig);
            $sig_html .= '<img class="signature-image" alt="signature"'
                       . ' style="width:' . round($imgW, 2) . 'px;'
                       . 'margin-left:' . round(-$imgW / 2 + $ocp_nudge, 2) . 'px;"'
                       . ' src="' . $trimmed . '">';
        }
        $sig_html .= '<div class="signature-name">'
                   . ($signer ? htmlspecialchars($signer['name']) : '&nbsp;')
                   . '</div>';
        $sig_html .= '</div>';

        // The rule the name sits on.
        $sig_html .= '<div class="signature-rule-under"></div>';

        $sig_html .= '
                <div class="signature-title">' . ($signer ? htmlspecialchars($signer['position']) : '&nbsp;') . '</div>
                <div class="signature-date">Date: ' . (($src && !empty($src[$step . '_at'])) ? date('m-d-Y', strtotime($src[$step . '_at'])) : '____________') . '</div>
            </div>';
    }
    $sig_html .= '
        </div>
    </div>';

    $html .= $sig_html;

    $html .= '
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