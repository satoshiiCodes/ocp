<?php
// generate_gas_po_pdf.php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/db_config.php';

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

if (!function_exists('ocp_po_centre_signature')) {
    /**
     * The signature <img>, shifted sideways so the handwriting is centred on its signature line.
     *
     * This is the only change to the original: the markup, the printed size, the fonts and the rest
     * of the layout are untouched. The image is the stored canvas, exactly as before.
     *
     * The thing to centre on is the rule the signature is written on, not the printed name. The line
     * and the name do not share a centre: measured from the rendered PDF, the prepared-by line runs
     * 138.3..303.3 (centre 220.8) while its name centre is 234.9, and the approved-by line runs
     * 429.8..594.8 (centre 512.3) while its name centre is 518.7.
     *
     * Two corrections are therefore needed:
     *
     *   $baseShiftPt - how far the column lays the image from its line centre. Measured with no
     *     margin at all, the image centres sit at 219.9pt (preparer) and 471.5pt (approver), so the
     *     shifts onto the lines are +0.9pt and +40.8pt. A property of the column, hence passed in
     *     per call site.
     *
     *   the ink offset - the handwriting is not centred in the stored canvas, differing by between 2
     *     and 12 points per signature, computed from each image.
     *
     * A horizontal margin on an inline-block moves its centre by half the margin, which was measured
     * rather than assumed (a 100pt margin moved the centre exactly 50pt), so the value written is
     * twice the distance the image has to travel.
     */
    function ocp_po_centre_signature(string $dataUrl, float $baseShiftPt): string
    {
        // Right by the column difference, left by however far the ink sits right of canvas centre.
        $shift = $baseShiftPt - (ocp_po_ink_offset_pt($dataUrl) ?? 0.0);

        $style = abs($shift) > 0.05
            ? ' style="margin-left:' . round($shift * 2, 2) . 'pt;"'
            : '';
        return '<img src="' . $dataUrl . '"' . $style . '>';
    }
}

if (!function_exists('ocp_po_ink_offset_pt')) {
    /**
     * How far the ink centre sits from the canvas centre, in points, so it can be corrected.
     *
     * The scale comes from the rendered page: a 763px canvas prints 171.7pt wide and a 629px canvas
     * 141.5pt, both 0.225pt per pixel. That was measured from the PDF rather than assumed, so the
     * offset is in the same units as the margin that corrects it.
     *
     * Returns null when the image cannot be read or holds no ink, in which case the caller leaves it
     * where it is rather than moving it on a guess.
     */
    function ocp_po_ink_offset_pt(string $dataUrl): ?float
    {
        if (!function_exists('imagecreatefromstring')) { return null; }
        if (strpos($dataUrl, 'data:image') !== 0) { return null; }

        $comma = strpos($dataUrl, ',');
        if ($comma === false) { return null; }
        $raw = base64_decode(substr($dataUrl, $comma + 1), true);
        if ($raw === false || $raw === '') { return null; }

        $im = @imagecreatefromstring($raw);
        if (!$im) { return null; }

        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 2 || $h < 2) { imagedestroy($im); return null; }

        // Ink bounds: any pixel that is not fully transparent counts as ink.
        $minX = $w; $maxX = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) < 120) {
                    if ($x < $minX) { $minX = $x; }
                    if ($x > $maxX) { $maxX = $x; }
                }
            }
        }
        imagedestroy($im);
        if ($maxX < 0) { return null; }

        // 0.225pt per canvas pixel, measured from the rendered page.
        $inkCentre = ($minX + $maxX) / 2;
        return ($inkCentre - $w / 2) * 0.225;
    }
}

// Define logo path
$logo_path = __DIR__ . '/assets/images/logo/OCP.png';

$logo_src = '';
if (file_exists($logo_path)) {
    $image_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($image_data);
    $logo_src = $logo_base64;
} else {
    $alternative_paths = [
        __DIR__ . '/assets/images/logo/OCP.png',
        $_SERVER['DOCUMENT_ROOT'] . '/assets/images/logo/OCP.png',
        'assets/images/logo/OCP.png'
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
        
        .label-container {
            width: 100%;
            margin-top: 10px;
        }

        .purchaser-left {
            display: inline-block;
            width: 50%;
            text-align: left;
            font-size: 8px;
        }

        .manager-right {
            display: inline-block;
            width: 49%;
            text-align: right;
            font-size: 8px;
        }
        
        /* The signature block: two columns, each with the printed name on a line and the image
           above it. These are the original rules, restored. */
        .signature-container {
            width: 100%;
        }

        .signature-left {
            display: inline-block;
            width: 50%;
            text-align: left;
            font-size: 9px;
        }

        .signature-right {
            display: inline-block;
            width: 49%;
            text-align: right;
            font-size: 9px;
            position: relative;
        }

        .signature-image {
            display: inline-block;
            text-align: center;
            margin-bottom: 5px;
        }

        /* The original sizing, unchanged: the image is drawn at its natural size, capped in height.
           Nothing here sets a width, because a fixed width was what made the signature print
           bigger - the stored canvases are 629 or 763 pixels wide, so forcing 150px scaled them up
           or down against what they were before. */
        .signature-image img {
            max-height: 60px;
            background: transparent;
        }

        /* Horizontal placement is set per signature, inline, from the measured ink offset - see
           ocp_po_centre_signature. Nothing fixed can work here: the ink sits a different distance
           off centre in every stored signature, so the shift has to be computed per image. */

        .signature-wrapper-purchaser {
            margin-top: -35px;
            margin-bottom: -35px;
            text-align: center;
        }

        .signature-wrapper {
            margin-top: -35px;
            margin-bottom: -35px;
            /* The preparer wrapper has this and the approver one did not, so the approver image was
               laid out from the left of its wrapper rather than centred in it - which is why a
               sideways margin could not move it onto the name. */
            text-align: center;
        }

        .signature-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 200px;
            margin-left: 5px;
            text-align: center;
            padding: 0 10px;
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
                    ' . (!empty($signature_data_purchaser) ? '<div class="signature-image">' . ocp_po_centre_signature($signature_data_purchaser, 0.9) . '</div>' : '') . '
                </div>
                Prepared by: 
                <span class="signature-line">
                    <span class="prepared-by-name">LESTER R. ALIPIO</span>
                </span>
            </div>
            
            <div class="signature-right">
                <div class="signature-wrapper">
                    ' . (!empty($signature_data) ? '<div class="signature-image">' . ocp_po_centre_signature($signature_data, 40.8) . '</div>' : '') . '
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
                    ' . (!empty($signature_data_purchaser) ? '<div class="signature-image">' . ocp_po_centre_signature($signature_data_purchaser, 0.9) . '</div>' : '') . '
                </div>
                Prepared by: 
                <span class="signature-line">
                    <span class="prepared-by-name">LESTER R. ALIPIO</span>
                </span>
            </div>
            
            <div class="signature-right">
                <div class="signature-wrapper">
                    ' . (!empty($signature_data) ? '<div class="signature-image">' . ocp_po_centre_signature($signature_data, 40.8) . '</div>' : '') . '
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
