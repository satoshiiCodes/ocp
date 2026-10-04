<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config/db_config.php';

// Load DOMPDF library
require_once 'vendor/autoload.php'; // Adjust path as needed

use Dompdf\Dompdf;
use Dompdf\Options;

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Format the display name
$display_name = $user['firstname'];

if (!empty($user['middlename'])) {
    $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
}

$display_name .= ' ' . $user['lastname'];

if (!empty($user['suffix'])) {
    $display_name .= ' ' . $user['suffix'];
}

// Get filter parameters
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

try {
    // Build query for transactions
    $query = "
        SELECT c.*, 
               u.firstname as user_firstname, 
               u.lastname as user_lastname,
               u.middlename as user_middlename
        FROM cash_on_hand c
        LEFT JOIN users u ON c.created_by = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filter_type)) {
        $query .= " AND c.transaction_type = :transaction_type";
        $params[':transaction_type'] = $filter_type;
    }
    
    if (!empty($filter_start_date)) {
        $query .= " AND DATE(c.transaction_date) >= :start_date";
        $params[':start_date'] = $filter_start_date;
    }
    
    if (!empty($filter_end_date)) {
        $query .= " AND DATE(c.transaction_date) <= :end_date";
        $params[':end_date'] = $filter_end_date;
    }
    
    $query .= " ORDER BY c.id ASC"; // ASC for proper running balance display
    
    $transactionsStmt = $pdo->prepare($query);
    $transactionsStmt->execute($params);
    $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary data
    $summaryQuery = "
        SELECT 
            SUM(CASE WHEN transaction_type = 'in' THEN amount ELSE 0 END) as total_in,
            SUM(CASE WHEN transaction_type = 'out' THEN amount ELSE 0 END) as total_out,
            COUNT(*) as total_transactions
        FROM cash_on_hand c
        WHERE 1=1
    ";
    
    if (!empty($filter_type)) {
        $summaryQuery .= " AND c.transaction_type = :transaction_type";
    }
    
    if (!empty($filter_start_date)) {
        $summaryQuery .= " AND DATE(c.transaction_date) >= :start_date";
    }
    
    if (!empty($filter_end_date)) {
        $summaryQuery .= " AND DATE(c.transaction_date) <= :end_date";
    }
    
    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    $total_in = $summary['total_in'] ?? 0;
    $total_out = $summary['total_out'] ?? 0;
    $net_balance = $total_in - $total_out;
    
    // Get current overall balance
    $balanceStmt = $pdo->prepare("SELECT balance FROM cash_on_hand ORDER BY id DESC LIMIT 1");
    $balanceStmt->execute();
    $currentBalanceData = $balanceStmt->fetch(PDO::FETCH_ASSOC);
    $current_balance = $currentBalanceData ? $currentBalanceData['balance'] : 0;
    
    // Read and encode the logo image
    $logo_path = __DIR__ . '/assets/images/logo/OCP.png';
    $logo_base64 = '';
    if (file_exists($logo_path)) {
        $logo_data = file_get_contents($logo_path);
        $logo_base64 = base64_encode($logo_data);
    }
    
    // Prepare HTML content for PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Cash on Hand Report</title>
        <style>
            @page {
                size: legal landscape;
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 10pt;
                line-height: 1.3;
                margin: -20px;
            }
            .logo-header-container {
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-bottom: 2px solid #000000;
            }
            .logo-container {
                flex: 0 0 auto;
            }
            .logo-container img {
                max-width: 150px;
                margin: -140px 0 0 610px;
                height: auto;
            }
            .header {
                flex: 1;
                text-align: center;
            }
            .header h1 {
                font-size: 18pt;
                margin: 0 0 5px 0;
                font-weight: bold;
            }
            .header h3 {
                font-size: 14pt;
                margin: 0 0 5px 0;
                font-weight: normal;
            }
            .header p {
                font-size: 10pt;
                margin: 2px 0;
                color: #666;
            }
            .filter-info {
                margin-bottom: 15px;
                padding: 8px;
                background: #e9ecef;
                border-left: 4px solid #007bff;
                font-size: 10pt;
                clear: both;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 9pt;
                clear: both;
            }
            th {
                background: #313131;
                color: white;
                font-weight: bold;
                padding: 8px 5px;
                text-align: center;
                border: 1px solid #dee2e6;
            }
            td {
                padding: 6px 5px;
                border: 1px solid #9d9d9d;
                padding: 8px 15px;
                text-align: left;
            }
            td.amount, td.balance {
                text-align: right;
            }
            .badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 8pt;
                font-weight: bold;
                text-align: center;
            }
            .badge-income {
                background: #28a745;
                color: white;
            }
            .badge-expense {
                background: #dc3545;
                color: white;
            }
            .text-muted {
                color: #6c757d;
            }
            @media print {
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="logo-header-container">
            <div class="header">
                <h1>OCP CONSTRUCTION</h1>
                <h3>Cash on Hand Report</h3>
                <p>Generated on: ' . date('m-d-Y h:i A') . '</p>
            </div>
            <div class="logo-container">
                <img src="data:image/png;base64,' . $logo_base64 . '" alt="OCP Logo" />
            </div>
        </div>';
    
    if (!empty($filter_start_date) || !empty($filter_end_date) || !empty($filter_type)) {
        $html .= '<div class="filter-info">';
        $html .= '<strong>Active Filters:</strong> ';
        $filters = [];
        if (!empty($filter_type)) {
            $filters[] = 'Type: ' . ($filter_type == 'in' ? 'Money In' : 'Money Out');
        }
        if (!empty($filter_start_date)) {
            $filters[] = 'From: ' . date('m-d-Y', strtotime($filter_start_date));
        }
        if (!empty($filter_end_date)) {
            $filters[] = 'To: ' . date('m-d-Y', strtotime($filter_end_date));
        }
        $html .= implode(' | ', $filters);
        $html .= '</div>';
    }
    
    if (!empty($transactions)) {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th width="10%">Date</th>
                    <th width="10%">Type</th>
                    <th width="15%">Amount</th>
                    <th width="15%">Previous Balance</th>
                    <th width="15%">New Balance</th>
                    <th width="35%">Description</th>
                </tr>
            </thead>
            <tbody>';
        
        $page_total_in = 0;
        $page_total_out = 0;
        
        foreach ($transactions as $transaction) {
            $badge_class = $transaction['transaction_type'] === 'in' ? 'badge-income' : 'badge-expense';
            $badge_text = $transaction['transaction_type'] === 'in' ? 'MONEY IN' : 'MONEY OUT';
            
            // Update page totals
            if ($transaction['transaction_type'] === 'in') {
                $page_total_in += $transaction['amount'];
            } else {
                $page_total_out += $transaction['amount'];
            }
            
            $html .= '
                <tr>
                    <td>' . date('m-d-Y', strtotime($transaction['transaction_date'])) . '</td>
                    <td><span class="badge ' . $badge_class . '">' . $badge_text . '</span></td>
                    <td class="amount">' . ($transaction['transaction_type'] === 'in' ? '+' : '-') . ' ' . number_format($transaction['amount'], 2) . '</td>
                    <td class="amount">' . number_format($transaction['previous_balance'], 2) . '</td>
                    <td class="amount">' . number_format($transaction['balance'], 2) . '</td>
                    <td>' . htmlspecialchars($transaction['description']) . '</td>
                </tr>';
        }
        
        // Add summary row
        $html .= '
            <tr style="background: #f2f2f2; font-weight: bold;">
                <td colspan="4" style="text-align: right;">Cash On Hand:</td>
                <td class="amount">' . number_format($page_total_in - $page_total_out, 2) . '</td>
                <td></td>
            </tr>';
        
        $html .= '
            </tbody>
        </table>';
        
        
    } else {
        $html .= '<p style="text-align: center; padding: 30px; background: #f8f9fa;">No transactions found for the selected criteria.</p>';
    }
    
    $html .= '
    </body>
    </html>';
    
    // Initialize DOMPDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultPaperSize', 'legal');
    $options->set('defaultPaperOrientation', 'landscape');
    $options->set('dpi', 150);
    $options->set('fontDir', __DIR__ . '/fonts/');
    $options->set('fontCache', __DIR__ . '/fonts/');
    $options->set('chroot', __DIR__);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('legal', 'landscape');
    $dompdf->render();
    
    // Generate filename
    $filename = 'Cash_on_Hand_Report_';
    $filename .= date('Ymd_His');
    if (!empty($filter_type)) {
        $filename .= '_' . $filter_type;
    }
    if (!empty($filter_start_date)) {
        $filename .= '_from_' . $filter_start_date;
    }
    if (!empty($filter_end_date)) {
        $filename .= '_to_' . $filter_end_date;
    }
    $filename .= '.pdf';
    
    // Output PDF
    $dompdf->stream($filename, array('Attachment' => false));
    exit();
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
    exit();
} catch (Exception $e) {
    echo "Error generating PDF: " . $e->getMessage();
    exit();
}
?>