<?php
// fuel_report_pdf.php
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

// Initialize filter variables from GET parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$gasoline_type = isset($_GET['gasoline_type']) ? $_GET['gasoline_type'] : '';

// Build the WHERE clause based on filters
$where_conditions = [];
$params = [];

if (!empty($start_date)) {
    $where_conditions[] = "pi.date_issued >= :start_date";
    $params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_conditions[] = "pi.date_issued <= :end_date";
    $params[':end_date'] = $end_date;
}
if (!empty($gasoline_type)) {
    $where_conditions[] = "pi.gasoline_type = :gasoline_type";
    $params[':gasoline_type'] = $gasoline_type;
}

$where_clause = !empty($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch all records for PDF
try {
    $itemsQuery = "
        SELECT 
            pi.*,
            po.po_number,
            po.invoice_number,
            s.supplier_name,
            v.vehicle_name,
            v.plate_number,
            e.equipment_name,
            CONCAT(emp.firstname, ' ', emp.lastname) as driver_name
        FROM gasoline_po_items pi
        LEFT JOIN gasoline_purchase_orders po ON pi.po_id = po.id
        LEFT JOIN gasoline_suppliers s ON pi.supplier_id = s.id
        LEFT JOIN vehicles v ON pi.vehicle_id = v.id
        LEFT JOIN equipment e ON pi.equipment_id = e.id
        LEFT JOIN employee emp ON pi.driver_operator_id = emp.id
        $where_clause
        ORDER BY pi.date_issued DESC, pi.id DESC
    ";
    
    $itemsStmt = $pdo->prepare($itemsQuery);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $itemsStmt->bindValue($key, $value);
    }
    
    $itemsStmt->execute();
    $po_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate items by gasoline type
    $diesel_items = [];
    $premium_items = [];
    $unleaded_items = [];
    
    // Calculate summary statistics
    $total_quantity = 0;
    $total_amount = 0;
    $gasoline_type_totals = [];
    
    foreach ($po_items as $item) {
        $quantity = $item['quantity_liters'];
        $amount = $quantity * ($item['price_per_liter'] ?? 0);
        $total_quantity += $quantity;
        $total_amount += $amount;
        
        // Separate by gasoline type
        $type = strtolower($item['gasoline_type']);
        if (strpos($type, 'diesel') !== false) {
            $diesel_items[] = $item;
        } elseif (strpos($type, 'premium') !== false) {
            $premium_items[] = $item;
        } elseif (strpos($type, 'unleaded') !== false || strpos($type, 'regular') !== false) {
            $unleaded_items[] = $item;
        }
        
        // Calculate per gasoline type totals
        if (!isset($gasoline_type_totals[$item['gasoline_type']])) {
            $gasoline_type_totals[$item['gasoline_type']] = ['quantity' => 0, 'amount' => 0];
        }
        $gasoline_type_totals[$item['gasoline_type']]['quantity'] += $quantity;
        $gasoline_type_totals[$item['gasoline_type']]['amount'] += $amount;
    }
    
} catch(PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Build filter description for the report header
$filter_description = [];
if (!empty($start_date)) {
    $filter_description[] = "From: " . date('M d, Y', strtotime($start_date));
}
if (!empty($end_date)) {
    $filter_description[] = "To: " . date('M d, Y', strtotime($end_date));
}
if (!empty($gasoline_type)) {
    $filter_description[] = "Type: " . $gasoline_type;
}
$filter_text = !empty($filter_description) ? "Filters: " . implode(" | ", $filter_description) : "All Records";

// Get current Manila date and time for report generation info
date_default_timezone_set('Asia/Manila');
$report_date = date('F d, Y h:i A');

// HTML content for PDF
$html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fuel Report - OCP Construction</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            margin-top: -20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18pt;
        }
        .header h3 {
            margin: 5px 0;
            font-size: 14pt;
        }
        .header p {
            margin: 5px 0;
            font-size: 10pt;
            color: #777;
        }
        .filter-info {
            background-color: #f0f0f0;
            padding: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
            font-size: 10pt;
        }
        .fuel-type-section {
            margin-bottom: 25px;
        }
        .fuel-type-title {
            font-size: 14pt;
            font-weight: bold;
            margin: 15px 0 10px 0;
            padding: 5px 10px;
            background-color: #e9ecef;
            border-left: 5px solid #007bff;
        }
        .fuel-type-title.diesel {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }
        .fuel-type-title.premium {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
        .fuel-type-title.unleaded {
            border-left-color: #28a745;
            background-color: #d4edda;
        }
        .filter-summary-table {
            margin-bottom: 20px;
            width: 100%;
            border-collapse: collapse;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .filter-summary-table th {
            background-color: #343a40;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #454d55;
            font-size: 9pt;
        }
        .filter-summary-table td {
            padding: 8px 5px;
            border: 1px solid #dee2e6;
            font-size: 9pt;
        }
        .filter-summary-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .summary {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            padding: 5px;
            font-size: 10pt;
        }
        .summary .label {
            font-weight: bold;
            color: #495057;
        }
        .summary .value {
            font-weight: bold;
            color: #007bff;
        }
        .text-end {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .page-break {
            page-break-after: always;
        }
        .type-badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8pt;
            font-weight: bold;
            color: white;
            border-radius: 3px;
        }
        .type-diesel { 
            background-color: #ffc107; 
            color: #212529;
        }
        .type-premium { 
            background-color: #dc3545; 
            color: white;
        }
        .type-regular { 
            background-color: #28a745; 
            color: white;
        }
        .type-unleaded { 
            background-color: #28a745; 
            color: white;
        }
        .table-footer {
            background-color: #e9ecef;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>OCP CONSTRUCTION</h1>
        <h3>Fuel Consumption Report</h3>
        <p>Generated on: ' . $report_date . '</p>
    </div>

    <div class="filter-info">
        <strong>Report Filters:</strong> ' . htmlspecialchars($filter_text) . '
    </div>';

if (count($po_items) > 0) {
    
    // Function to generate table for a specific fuel type
    function generateFuelTypeTable($items, $type_name) {
        if (empty($items)) {
            return '';
        }
        
        $type_class = strtolower($type_name);
        $html = '<div class="fuel-type-section">';
        $html .= '<div class="fuel-type-title ' . $type_class . '">' . htmlspecialchars($type_name) . '</div>';
        
        $html .= '<table class="filter-summary-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice Number</th>
                    <th>Vehicle/Equipment</th>
                    <th>Type</th>
                    <th>Qty (L)</th>
                    <th>Price/L</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
        
        $type_total_quantity = 0;
        $type_total_amount = 0;
        
        foreach ($items as $item) {
            $total = $item['quantity_liters'] * ($item['price_per_liter'] ?? 0);
            $type_total_quantity += $item['quantity_liters'];
            $type_total_amount += $total;
            
            // Format date
            $formatted_date = !empty($item['date_issued']) ? date('m-d-Y', strtotime($item['date_issued'])) : '—';
            
            // Invoice number from gasoline_purchase_orders table
            $invoice_number = !empty($item['invoice_number']) ? $item['invoice_number'] : 'N/A';
            
            // Vehicle/Equipment info
            $vehicle_equipment = '';
            if (!empty($item['vehicle_name'])) {
                $vehicle_equipment = $item['vehicle_name'];
                if (!empty($item['plate_number'])) {
                    $vehicle_equipment .= ' (' . $item['plate_number'] . ')';
                }
            } elseif (!empty($item['equipment_name'])) {
                $vehicle_equipment = $item['equipment_name'];
            } else {
                $vehicle_equipment = '—';
            }
            
            // Determine type class
            $type_class_badge = 'type-badge';
            $type_lower = strtolower($item['gasoline_type']);
            if (strpos($type_lower, 'diesel') !== false) {
                $type_class_badge .= ' type-diesel';
            } elseif (strpos($type_lower, 'premium') !== false) {
                $type_class_badge .= ' type-premium';
            } elseif (strpos($type_lower, 'regular') !== false || strpos($type_lower, 'unleaded') !== false) {
                $type_class_badge .= ' type-unleaded';
            }
            
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($formatted_date) . '</td>';
            $html .= '<td>' . htmlspecialchars($invoice_number) . '</td>';
            $html .= '<td>' . htmlspecialchars($vehicle_equipment) . '</td>';
            $html .= '<td><span class="' . $type_class_badge . '">' . htmlspecialchars($item['gasoline_type']) . '</span></td>';
            $html .= '<td class="text-end">' . number_format($item['quantity_liters'], 2) . '</td>';
            $html .= '<td class="text-end">' . ($item['price_per_liter'] ? '' . number_format($item['price_per_liter'], 2) : '—') . '</td>';
            $html .= '<td class="text-end">' . number_format($total, 2) . '</td>';
            $html .= '</tr>';
        }
        
        // Add type total row
        $html .= '<tr class="table-footer">';
        $html .= '<td colspan="4" class="text-end"><strong> Total </strong>' . htmlspecialchars($type_name) . '</td>';
        $html .= '<td class="text-end"><strong>' . number_format($type_total_quantity, 2) . '</strong></td>';
        $html .= '<td></td>';
        $html .= '<td class="text-end"><strong>' . number_format($type_total_amount, 2) . '</strong></td>';
        $html .= '</tr>';
        
        $html .= '</tbody></table></div>';
        
        return $html;
    }
    
    // Generate tables for each fuel type
    $html .= generateFuelTypeTable($diesel_items, 'Diesel');
    $html .= generateFuelTypeTable($premium_items, 'Premium');
    $html .= generateFuelTypeTable($unleaded_items, 'Unleaded');
    
} else {
    $html .= '<div style="text-align: center; padding: 50px; color: #666;">No records found for the selected filters.</div>';
}

$html .= '
</body>
</html>';

// Initialize Dompdf with options
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultPaperSize', 'Letter');
$options->set('defaultPaperOrientation', 'portrait');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('legal', 'portrait'); // Legal size = 8.5 x 14 inches (long bond paper)
$dompdf->render();

// Generate filename with filters
$filename = 'Fuel_Report';
if (!empty($start_date)) {
    $filename .= '_' . $start_date;
}
if (!empty($end_date)) {
    $filename .= '_to_' . $end_date;
}
if (!empty($gasoline_type)) {
    $filename .= '_' . str_replace(' ', '_', $gasoline_type);
}
$filename .= '_' . date('Ymd_His') . '.pdf';

// Stream the PDF to browser
$dompdf->stream($filename, array('Attachment' => false));
exit();
?>