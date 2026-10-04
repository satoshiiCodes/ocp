<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once '../includes/db_config.php';

// Check if PO ID is provided
if (!isset($_POST['po_id']) || empty($_POST['po_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Purchase Order ID is required']);
    exit();
}

$po_id = $_POST['po_id'];

try {
    // Get PO details
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
    $po_details = $poStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$po_details) {
        http_response_code(404);
        echo json_encode(['error' => 'Purchase Order not found']);
        exit();
    }
    
    // Get PO items with driver/operator details from employee table
    $itemsStmt = $pdo->prepare("
        SELECT 
            poi.*,
            s.supplier_name,
            v.vehicle_name,
            v.plate_number,
            e.equipment_name,
            emp.firstname,
            emp.middlename,
            emp.lastname,
            emp.suffix,
            emp.position,
            emp.employee_id
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
    $po_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_quantity = 0;
    $total_amount = 0;
    $total_price_per_liter = 0;
    $items_with_price = 0;
    
    foreach ($po_items as $item) {
        $total_quantity += $item['quantity_liters'];
        $total_amount += $item['quantity_liters'] * ($item['price_per_liter'] ?? 0);
        
        // Calculate total price per liter
        if ($item['price_per_liter'] !== null) {
            $total_price_per_liter += $item['price_per_liter'];
            $items_with_price++;
        }
    }
    
    // Calculate average price per liter
    $average_price_per_liter = $items_with_price > 0 ? $total_price_per_liter / $items_with_price : 0;
    
    // Determine status color for PO
    $status_class = 'bg-secondary';
    $status_text = ucfirst($po_details['status']);
    
    switch ($po_details['status']) {
        case 'pending':
            $status_class = 'bg-warning text-dark';
            break;
        case 'approved':
            $status_class = 'bg-info';
            break;
        case 'ordered':
            $status_class = 'bg-primary';
            break;
        case 'delivered':
            $status_class = 'bg-success';
            break;
        case 'cancelled':
            $status_class = 'bg-danger';
            break;
    }
    
    // Format dates
    $po_date_formatted = date('F j, Y', strtotime($po_details['po_date']));
    $delivery_date_formatted = $po_details['delivery_date'] ? date('F j, Y', strtotime($po_details['delivery_date'])) : 'Not yet delivered';
    
    /**
     * Format employee name or return manual driver name
     * Returns just the name without any extra text
     */
    function formatDriverName($item) {
        // Check if manual_driver_name exists and driver_operator_id is NULL/empty
        if ((empty($item['driver_operator_id']) || $item['driver_operator_id'] === null) && !empty($item['manual_driver_name'])) {
            return htmlspecialchars($item['manual_driver_name']);
        }
        
        // If we have employee data, format the name
        if (!empty($item['firstname']) || !empty($item['lastname'])) {
            $name = '';
            
            // First name
            if (!empty($item['firstname'])) {
                $name .= $item['firstname'];
            }
            
            // Middle initial
            if (!empty($item['middlename'])) {
                $name .= ' ' . substr($item['middlename'], 0, 1) . '.';
            }
            
            // Last name
            if (!empty($item['lastname'])) {
                $name .= ' ' . $item['lastname'];
            }
            
            // Suffix
            if (!empty($item['suffix'])) {
                $name .= ' ' . $item['suffix'];
            }
            
            return trim($name);
        }
        
        // Fallback if nothing is available
        return 'Not specified';
    }
    
    // Start HTML output
    ob_start();
    ?>
    
    <div data-po-id="<?php echo htmlspecialchars($po_details['id']); ?>">
        <!-- PO Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h4 class="mb-1">Purchase Order: <strong><?php echo htmlspecialchars($po_details['po_number']); ?></strong></h4>
                <p class="text-muted mb-0">Date: <?php echo $po_date_formatted; ?></p>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge <?php echo $status_class; ?> fs-6"><?php echo $status_text; ?></span>
            </div>
        </div>
        
        <!-- PO Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100 po-details-card">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>PO Information</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <th style="width: 40%;">PO Number:</th>
                                <td><?php echo htmlspecialchars($po_details['po_number']); ?></td>
                            </tr>
                            <tr>
                                <th>Invoice Number:</th>
                                <td>
                                    <?php if (!empty($po_details['invoice_number'])): ?>
                                        <?php echo htmlspecialchars($po_details['invoice_number']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                
    
                            </tr>
                            <tr>
                                <th>PO Date:</th>
                                <td><?php echo $po_date_formatted; ?></td>
                            </tr>
                            <tr>
                                <th>Supplier:</th>
                                <td><?php echo htmlspecialchars($po_details['supplier_name']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Personnel</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <th style="width: 40%;">Prepared By:</th>
                                <td><?php echo htmlspecialchars($po_details['preparer_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Approved By:</th>
                                <td><?php echo $po_details['approver_name'] ? htmlspecialchars($po_details['approver_name']) : 'Not yet approved'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- PO Items -->
        <div class="card mb-4 po-items-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-gas-pump me-2"></i>PO Items (Gasoline Distribution)</h6>
                <span class="badge bg-secondary float-end"><?php echo count($po_items); ?> items</span>
            </div>
            <div class="card-body">
                <?php if (!empty($po_items)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Gasoline Type</th>
                                <th>Supplier</th>
                                <th>Vehicle/Equipment</th>
                                <th>Driver/Operator</th>
                                <th>Purpose</th>
                                <th class="text-end">Quantity (L)</th>
                                <th class="text-end">Price/Liter</th>
                                <th class="text-end">Total</th>
                                <th>Date Issued</th>
                                <th>Odometer</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($po_items as $index => $item): 
                                $item_total = $item['quantity_liters'] * ($item['price_per_liter'] ?? 0);
                                
                                // Determine vehicle/equipment info
                                $vehicle_equipment = '';
                                if ($item['vehicle_id']) {
                                    $vehicle_equipment = htmlspecialchars($item['vehicle_name'] ?? '') . ' (' . htmlspecialchars($item['plate_number'] ?? '') . ')';
                                } elseif ($item['equipment_id']) {
                                    $vehicle_equipment = htmlspecialchars($item['equipment_name'] ?? '');
                                } else {
                                    $vehicle_equipment = 'Not assigned';
                                }
                                
                                // Format driver/operator display (returns just the name)
                                $driver_name = formatDriverName($item);
                                
                                // Item status - Use the PO status from gasoline_purchase_orders table
                                $item_status_class = '';
                                $item_status_text = ucfirst($po_details['status']);
                                
                                switch ($po_details['status']) {
                                    case 'pending':
                                        $item_status_class = 'bg-warning text-dark';
                                        break;
                                    case 'approved':
                                        $item_status_class = 'bg-info';
                                        break;
                                    case 'ordered':
                                        $item_status_class = 'bg-primary';
                                        break;
                                    case 'delivered':
                                        $item_status_class = 'bg-success';
                                        break;
                                    case 'cancelled':
                                        $item_status_class = 'bg-danger';
                                        break;
                                    default:
                                        $item_status_class = 'bg-secondary';
                                }
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($item['gasoline_type']); ?></td>
                                <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                                <td><?php echo $vehicle_equipment; ?></td>
                                <td><?php echo $driver_name; ?></td>
                                <td><?php echo htmlspecialchars($item['purpose']); ?></td>
                                <td class="text-end"><?php echo number_format($item['quantity_liters'], 2); ?></td>
                                <td class="text-end">
                                    <?php if ($item['price_per_liter'] !== null): ?>
                                        ₱<?php echo number_format($item['price_per_liter'], 2); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($item['price_per_liter'] !== null): ?>
                                        ₱<?php echo number_format($item_total, 2); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['date_issued']); ?></td>
                                <td>
                                    <?php if ($item['odometer_reading']): ?>
                                        <?php echo number_format($item['odometer_reading']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $item_status_class; ?>"><?php echo $item_status_text; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="6" class="text-end">Totals:</th>
                                <th class="text-end"><?php echo number_format($total_quantity, 2); ?> L</th>
                                <th></th>
                                <th class="text-end">₱<?php echo number_format($total_amount, 2); ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No items found for this purchase order.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Summary -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Summary</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <th style="width: 40%;">Total Quantity:</th>
                                <td><?php echo number_format($total_quantity, 2); ?> Liters</td>
                            </tr>
                            <tr>
                                <th>Total Amount:</th>
                                <td><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Average Price/Liter:</th>
                                <td>₱<?php echo number_format($average_price_per_liter, 2); ?></td>
                            </tr>
                            <?php if (!empty($po_details['invoice_number'])): ?>
                            <tr>
                                <th>Invoice Number:</th>
                                <td><strong><?php echo htmlspecialchars($po_details['invoice_number']); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Status History</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-circle text-success me-2"></i>
                                Created on <?php echo date('M j, Y', strtotime($po_details['po_date'])); ?>
                            </li>
                            <li class="mb-2">
                                <?php if ($po_details['status'] !== 'pending'): ?>
                                <i class="fas fa-circle text-primary me-2"></i>
                                Updated to <?php echo ucfirst($po_details['status']); ?>
                                <?php if ($po_details['approved_by'] && $po_details['status'] === 'approved'): ?>
                                    by <?php echo htmlspecialchars($po_details['approver_name']); ?>
                                <?php endif; ?>
                                <?php else: ?>
                                <i class="fas fa-circle text-warning me-2"></i>
                                Currently Pending
                                <?php endif; ?>
                            </li>
                            <?php if ($po_details['delivery_date']): ?>
                            <li class="mb-2">
                                <i class="fas fa-circle text-info me-2"></i>
                                Delivered on <?php echo date('M j, Y', strtotime($po_details['delivery_date'])); ?>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($po_details['completed_date'])): ?>
                            <li class="mb-2">
                                <i class="fas fa-circle text-success me-2"></i>
                                Completed on <?php echo date('M j, Y', strtotime($po_details['completed_date'])); ?>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notes (if any) -->
        <?php if (!empty($po_details['notes'])): ?>
        <div class="card mt-4">
            <div class="card-header bg-transparent">
                <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h6>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($po_details['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php
    $html = ob_get_clean();
    
    // Return the HTML
    echo $html;
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    exit();
}
?>