<?php
/**
 * api/gasoline_purchase_order-endpoint.php
 *
 * Every read for gasoline_purchase_order.php lives in this one file: the suppliers,
 * vehicles, equipment, employees, tanks and users for its dropdowns, the PO listing,
 * the status counts and the pending summary - plus the single-PO lookup its
 * JavaScript posts for.
 *
 * The page pulls this in for its listing: it runs in the page's scope and returns an
 * array of the variables the markup needs, which the page unpacks. The lookup is a
 * separate HTTP request from the page's own script, so it is detected here by its
 * "po_id" parameter and answers JSON instead - it lived in api/get_gasoline_po_details.php,
 * which this file replaces.
 *
 * Requires the shared helper include, because generatePONumber() is one of those
 * helpers and the template calls it.
 *
 * Returns
 *   suppliers
 *   vehicles
 *   equipment
 *   employees
 *   tanks
 *   users
 *   purchase_orders
 *   status_counts
 *   pending_value
 *   pending_items
 *   swal_data
 *   edit_po_data
 */

// ---------------------------------------------------------------------------
// The single-PO lookup the page's JavaScript posts for. Lifted from
// api/get_gasoline_po_details.php, which this file replaces.
//
// The trigger has to exclude the page's own form posts. Every form on this page carries a
// po_id - approve_po, delete_po, complete_po, update_invoice, cancel_po, issue_gasoline -
// so "a post carrying po_id" matched all of them: the endpoint answered the lookup instead
// of falling through to the listing, the handlers below never ran, and the browser rendered
// the lookup's answer as the page. That is why approving dumped a PO-details fragment on
// the screen and deleting printed {"error":"Purchase Order not found"} - the delete had
// already removed the row, so the lookup could no longer find it.
//
// A post is the lookup only when it carries po_id and no action. One carrying po_id empty
// is still the lookup and gets its "required" answer, exactly as before; a post with no
// po_id at all falls through to the listing, as before.
// ---------------------------------------------------------------------------
$ocp_is_po_lookup = array_key_exists('po_id', $_POST) && !array_key_exists('action', $_POST);
if ($ocp_is_po_lookup) {
    $ocp_dir = __DIR__;
    for ($ocp_i = 0; $ocp_i < 4 && !is_file($ocp_dir . '/config/db_config.php'); $ocp_i++) {
        $ocp_parent = dirname($ocp_dir);
        if ($ocp_parent === $ocp_dir) {
            break;
        }
        $ocp_dir = $ocp_parent;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($pdo)) {
        require_once $ocp_dir . '/config/db_config.php';
    }
    unset($ocp_dir, $ocp_i, $ocp_parent);

    header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection

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
    $status_class = 'badge-neutral';
    $status_text = ucfirst($po_details['status']);
    
    switch ($po_details['status']) {
        case 'pending':
            $status_class = 'badge-warning';
            break;
        case 'approved':
            $status_class = 'badge-info';
            break;
        case 'ordered':
            $status_class = 'badge-primary';
            break;
        case 'delivered':
            $status_class = 'badge-success';
            break;
        case 'cancelled':
            $status_class = 'badge-danger';
            break;
    }
    
    // Format dates
    $po_date_formatted = date('m-d-Y', strtotime($po_details['po_date']));
    $delivery_date_formatted = $po_details['delivery_date'] ? date('m-d-Y', strtotime($po_details['delivery_date'])) : 'Not yet delivered';
    
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
        <!-- PO Header. A plain flex row rather than the old grid: spanning two columns left the
             status badge stranded on a line of its own in the middle of the dialog. -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div class="min-w-0">
                <h4 class="mb-1">Purchase Order: <strong><?php echo htmlspecialchars($po_details['po_number']); ?></strong></h4>
                <p class="text-muted mb-0">Date: <?php echo $po_date_formatted; ?></p>
            </div>
            <div class="shrink-0">
                <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
        </div>
        
        <!-- PO Information -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 mb-6">
            <div class="min-w-0">
                <div>
                    <h6 class="mb-2 font-semibold text-slate-800"><i class="fas fa-info-circle mr-2"></i>PO Information</h6>
                    <div>
                        <table class="table table-sm">
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
            
            <div class="min-w-0">
                <div>
                    <h6 class="mb-2 font-semibold text-slate-800"><i class="fas fa-users mr-2"></i>Personnel</h6>
                    <div>
                        <table class="table table-sm">
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
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <h6 class="mb-0"><i class="fas fa-gas-pump mr-2"></i>PO Items (Gasoline Distribution)</h6>
                <span class="badge badge-neutral"><?php echo count($po_items); ?> items</span>
            </div>
            <div>
                <?php if (!empty($po_items)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="">
                            <tr>
                                <th>#</th>
                                <th>Gasoline Type</th>
                                <th>Supplier</th>
                                <th>Vehicle/Equipment</th>
                                <th>Driver/Operator</th>
                                <th>Purpose</th>
                                <th class="text-right">Quantity (L)</th>
                                <th class="text-right">Price/Liter</th>
                                <th class="text-right">Total</th>
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
                                        $item_status_class = 'badge-warning';
                                        break;
                                    case 'approved':
                                        $item_status_class = 'badge-info';
                                        break;
                                    case 'ordered':
                                        $item_status_class = 'badge-primary';
                                        break;
                                    case 'delivered':
                                        $item_status_class = 'badge-success';
                                        break;
                                    case 'cancelled':
                                        $item_status_class = 'badge-danger';
                                        break;
                                    default:
                                        $item_status_class = 'badge-neutral';
                                }
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($item['gasoline_type']); ?></td>
                                <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                                <td><?php echo $vehicle_equipment; ?></td>
                                <td><?php echo $driver_name; ?></td>
                                <td><?php echo htmlspecialchars($item['purpose']); ?></td>
                                <td class="text-right"><?php echo number_format($item['quantity_liters'], 2); ?></td>
                                <td class="text-right">
                                    <?php if ($item['price_per_liter'] !== null): ?>
                                        ₱<?php echo number_format($item['price_per_liter'], 2); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
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
                        <tfoot class="">
                            <tr>
                                <th colspan="6" class="text-right">Totals:</th>
                                <th class="text-right"><?php echo number_format($total_quantity, 2); ?> L</th>
                                <th></th>
                                <th class="text-right">₱<?php echo number_format($total_amount, 2); ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-6">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No items found for this purchase order.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Summary -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div class="min-w-0">
                <h6 class="mb-2 font-semibold text-slate-800"><i class="fas fa-file-alt mr-2"></i>Summary</h6>
                <div>
                    <table class="table table-sm">
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
            
            <div class="min-w-0">
                <h6 class="mb-2 font-semibold text-slate-800"><i class="fas fa-history mr-2"></i>Status History</h6>
                <div>
                    <ul class="list-none mb-0">
                        <li class="mb-2">
                            <i class="fas fa-circle text-success mr-2"></i>
                            Created on <?php echo date('m-d-Y', strtotime($po_details['po_date'])); ?>
                        </li>
                        <li class="mb-2">
                            <?php if ($po_details['status'] !== 'pending'): ?>
                            <i class="fas fa-circle text-primary mr-2"></i>
                            Updated to <?php echo ucfirst($po_details['status']); ?>
                            <?php if ($po_details['approved_by'] && $po_details['status'] === 'approved'): ?>
                                by <?php echo htmlspecialchars($po_details['approver_name']); ?>
                            <?php endif; ?>
                            <?php else: ?>
                            <i class="fas fa-circle text-warning mr-2"></i>
                            Currently Pending
                            <?php endif; ?>
                        </li>
                        <?php if ($po_details['delivery_date']): ?>
                        <li class="mb-2">
                            <i class="fas fa-circle text-info mr-2"></i>
                            Delivered on <?php echo date('m-d-Y', strtotime($po_details['delivery_date'])); ?>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($po_details['completed_date'])): ?>
                        <li class="mb-2">
                            <i class="fas fa-circle text-success mr-2"></i>
                            Completed on <?php echo date('m-d-Y', strtotime($po_details['completed_date'])); ?>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Notes (if any) -->
        <?php if (!empty($po_details['notes'])): ?>
        <div class="mt-6">
            <h6 class="mb-2 font-semibold text-slate-800"><i class="fas fa-sticky-note mr-2"></i>Notes</h6>
            <div>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($po_details['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
        <!-- Closes [data-po-id]. It has to sit inside the branch that is always emitted: it used
             to be the last line of the notes block, so on a PO with no notes the wrapper was
             never closed and the modal got a stray close tag. -->
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

    exit();
}

$ocp_endpoint = [
    'suppliers' => [],
    'vehicles' => [],
    'equipment' => [],
    'employees' => [],
    'tanks' => [],
    'users' => [],
    'purchase_orders' => [],
    'status_counts' => [],
    'pending_value' => null,
    'pending_items' => [],
    // Seeded from the actions file, so a message or an edit form it set is not wiped
    // out by the endpoint running after it.
    'swal_data' => $swal_data ?? [],
    'edit_po_data' => $edit_po_data ?? null,
    // display_name is deliberately NOT returned. The page builds it from the user row before
    // this file is included, and the page unpacks every key here over its own scope - so a
    // placeholder of '' silently replaced the real name, and the sidebar's "Logged in as:" came
    // out blank on this page only.
];

// Standalone guard: the read block runs in the page's scope, where the connection is
// already open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/gasoline_purchase_order-functions.php';

// Fetch data for dropdowns and tables
try {
    // Gasoline types
    $gasolineTypes = ['Unleaded', 'Premium', 'Diesel'];
    
    // Get suppliers
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM gasoline_suppliers WHERE supplier_type = 'fuel' OR supplier_type IS NULL ORDER BY supplier_name");
    $suppliersStmt->execute();
    $ocp_endpoint['suppliers'] = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vehicles
    $vehiclesStmt = $pdo->prepare("SELECT id, vehicle_name, plate_number FROM vehicles WHERE fuel_type IN ('gasoline', 'diesel', 'petrol') ORDER BY vehicle_name");
    $vehiclesStmt->execute();
    $ocp_endpoint['vehicles'] = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get equipment
    $equipmentStmt = $pdo->prepare("SELECT id, equipment_name FROM equipment WHERE fuel_type IN ('gasoline', 'diesel', 'petrol') ORDER BY equipment_name");
    $equipmentStmt->execute();
    $ocp_endpoint['equipment'] = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get employees for driver/operator dropdown - ONLY SHOW DRIVERS
    $employeesStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix 
        FROM employee 
        WHERE (status = 'active' OR status IS NULL)
        AND position = 'Driver'
        ORDER BY firstname, lastname
    ");
    $employeesStmt->execute();
    $ocp_endpoint['employees'] = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tanks for issuing gasoline
    $tanksStmt = $pdo->prepare("SELECT id, tank_name, location FROM gasoline_tanks ORDER BY tank_name");
    $tanksStmt->execute();
    $ocp_endpoint['tanks'] = $tanksStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get users for prepared_by and approved_by
    $usersStmt = $pdo->prepare("SELECT id, CONCAT(firstname, ' ', lastname) as fullname FROM users ORDER BY firstname");
    $usersStmt->execute();
    $ocp_endpoint['users'] = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all purchase orders with gasoline type and quantity from items
    $poStmt = $pdo->prepare("
        SELECT 
            po.*, 
            s.supplier_name, 
            CONCAT(u1.firstname, ' ', u1.lastname) as preparer_name,
            CONCAT(u2.firstname, ' ', u2.lastname) as approver_name,
            GROUP_CONCAT(DISTINCT poi.gasoline_type ORDER BY poi.gasoline_type SEPARATOR ', ') as gasoline_types,
            SUM(poi.quantity_liters) as total_quantity_liters,
            COUNT(poi.id) as item_count,
            COALESCE(po.total_amount, SUM(poi.quantity_liters * poi.price_per_liter)) as total_amount_calc
        FROM gasoline_purchase_orders po
        LEFT JOIN gasoline_suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u1 ON po.prepared_by = u1.id
        LEFT JOIN users u2 ON po.approved_by = u2.id
        LEFT JOIN gasoline_po_items poi ON po.id = poi.po_id
        GROUP BY po.id, po.po_number, po.po_date, s.supplier_name, preparer_name, approver_name, po.total_amount
        ORDER BY po.po_date DESC, po.id DESC
    ");
    $poStmt->execute();
    $ocp_endpoint['purchase_orders'] = $poStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get PO status counts for statistics
    $statusStmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM gasoline_purchase_orders 
        GROUP BY status
    ");
    $statusStmt->execute();
    $ocp_endpoint['status_counts'] = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total value of pending POs
    $pendingValueStmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_pending 
        FROM gasoline_purchase_orders 
        WHERE status IN ('pending', 'approved', 'ordered')
    ");
    $pendingValueStmt->execute();
    $ocp_endpoint['pending_value'] = $pendingValueStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get PO items that haven't been issued yet
    $pendingItemsStmt = $pdo->prepare("
        SELECT poi.*, po.po_number, s.supplier_name,
               v.vehicle_name, v.plate_number,
               e.equipment_name,
               CONCAT(emp.firstname, ' ', emp.lastname) as employee_fullname,
               emp.employee_id
        FROM gasoline_po_items poi
        JOIN gasoline_purchase_orders po ON poi.po_id = po.id
        LEFT JOIN gasoline_suppliers s ON poi.supplier_id = s.id
        LEFT JOIN vehicles v ON poi.vehicle_id = v.id
        LEFT JOIN equipment e ON poi.equipment_id = e.id
        LEFT JOIN employee emp ON poi.driver_operator_id = emp.id
        WHERE poi.issued = 0 AND po.status = 'delivered'
        ORDER BY poi.date_issued DESC
    ");
    $pendingItemsStmt->execute();
    $ocp_endpoint['pending_items'] = $pendingItemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $ocp_endpoint['swal_data'] = array(
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error'
    );
}

return $ocp_endpoint;
