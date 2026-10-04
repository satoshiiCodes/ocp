<?php
/**
 * actions/gasoline_purchase_order-actions.php
 *
 * Every action for gasoline_purchase_order.php lives in this one file: creating a PO,
 * updating one, approving, completing, cancelling, deleting, updating an invoice, and
 * loading a PO into the form for editing.
 *
 * The page pulls this file in at the top, so it runs in the page's scope - which also
 * gives it the four role flags the handlers gate on ($can_approve_po, $can_delete_po,
 * $can_complete_po, $can_update_invoice), computed by the page from the signed-in user.
 *
 * The helpers it calls live in includes/gasoline_purchase_order-functions.php,
 * required here so they are defined whichever entry point runs first.
 *
 * The blocks below are lifted verbatim from gasoline_purchase_order.php: the queries,
 * the role checks and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' && !isset($_GET['edit_po'])) {
    return;
}
if (defined('OCP_GASOLINE_PURCHASE_ORDER_ACTIONS_RAN')) {
    return;
}
define('OCP_GASOLINE_PURCHASE_ORDER_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/gasoline_purchase_order-functions.php';

// The message state the markup reads, filled in below.
$swal_data = array();
$edit_po_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_po') {
        // Create new purchase order
        $po_number = $_POST['po_number'] ?? '';
        $po_date = $_POST['po_date'] ?? '';
        $status = $_POST['status'] ?? 'pending';
        $prepared_by = $_POST['prepared_by'] ?? '';
        $total_amount = 0;
        
        // We'll determine the main supplier from the first item's supplier
        $main_supplier_id = null;
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Get the main supplier from the first item (if items exist)
            if (isset($_POST['item_gasoline_type']) && is_array($_POST['item_gasoline_type'])) {
                foreach ($_POST['item_gasoline_type'] as $index => $gasoline_type) {
                    if (!empty($gasoline_type) && !empty($_POST['item_supplier_id'][$index])) {
                        $main_supplier_id = $_POST['item_supplier_id'][$index];
                        
                        // Validate supplier exists
                        $checkSupplierStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :supplier_id");
                        $checkSupplierStmt->bindParam(':supplier_id', $main_supplier_id);
                        $checkSupplierStmt->execute();
                        
                        if (!$checkSupplierStmt->fetch()) {
                            throw new Exception("Supplier ID $main_supplier_id does not exist in the suppliers table.");
                        }
                        break;
                    }
                }
            }
            
            if (!$main_supplier_id) {
                throw new Exception("At least one item must have a supplier selected.");
            }
            
            // Insert purchase order
            $poStmt = $pdo->prepare("INSERT INTO gasoline_purchase_orders (po_number, supplier_id, po_date, status, prepared_by, total_amount) 
                                   VALUES (:po_number, :supplier_id, :po_date, :status, :prepared_by, :total_amount)");
            $poStmt->bindParam(':po_number', $po_number);
            $poStmt->bindParam(':supplier_id', $main_supplier_id);
            $poStmt->bindParam(':po_date', $po_date);
            $poStmt->bindParam(':status', $status);
            $poStmt->bindParam(':prepared_by', $prepared_by);
            $poStmt->bindParam(':total_amount', $total_amount);
            $poStmt->execute();
            
            $po_id = $pdo->lastInsertId();
            
            // Insert PO items if provided
            if (isset($_POST['item_gasoline_type']) && is_array($_POST['item_gasoline_type'])) {
                $itemCount = count($_POST['item_gasoline_type']);
                
                for ($i = 0; $i < $itemCount; $i++) {
                    if (!empty($_POST['item_gasoline_type'][$i])) {
                        $gasoline_type = $_POST['item_gasoline_type'][$i] ?? '';
                        $supplier_id_item = $_POST['item_supplier_id'][$i] ?? '';
                        $vehicle_id = !empty($_POST['item_vehicle_id'][$i]) ? $_POST['item_vehicle_id'][$i] : null;
                        $equipment_id = !empty($_POST['item_equipment_id'][$i]) ? $_POST['item_equipment_id'][$i] : null;
                        $driver_operator_id = !empty($_POST['item_driver_operator_id'][$i]) ? $_POST['item_driver_operator_id'][$i] : null;
                        $manual_driver_name = !empty($_POST['item_manual_driver_name'][$i]) ? $_POST['item_manual_driver_name'][$i] : null;
                        $purpose = $_POST['item_purpose'][$i] ?? '';
                        $quantity_liters = $_POST['item_quantity_liters'][$i] ?? 0;
                        $price_per_liter = !empty($_POST['item_price_per_liter'][$i]) ? $_POST['item_price_per_liter'][$i] : null;
                        $odometer_reading = !empty($_POST['item_odometer_reading'][$i]) ? $_POST['item_odometer_reading'][$i] : null;
                        $date_issued = $_POST['item_date_issued'][$i] ?? '';
                        
                        // Validate required fields
                        if (empty($gasoline_type) || empty($supplier_id_item) || 
                            empty($purpose) || empty($quantity_liters) || empty($date_issued)) {
                            continue;
                        }
                        
                        // Handle driver_operator_id: if value is 'other' or not a valid ID, set to NULL
                        if ($driver_operator_id === 'other' || !is_numeric($driver_operator_id)) {
                            $driver_operator_id = null;
                        } else {
                            // Validate that the employee exists and has position 'Driver'
                            if ($driver_operator_id) {
                                $checkDriverStmt = $pdo->prepare("SELECT id FROM employee WHERE id = :id AND position = 'Driver'");
                                $checkDriverStmt->bindParam(':id', $driver_operator_id);
                                $checkDriverStmt->execute();
                                if (!$checkDriverStmt->fetch()) {
                                    $driver_operator_id = null;
                                }
                            }
                        }
                        
                        // Validate supplier exists for each item
                        $checkSupplierStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :supplier_id");
                        $checkSupplierStmt->bindParam(':supplier_id', $supplier_id_item);
                        $checkSupplierStmt->execute();
                        
                        if (!$checkSupplierStmt->fetch()) {
                            throw new Exception("Supplier ID $supplier_id_item does not exist in the suppliers table.");
                        }
                        
                        // Calculate item total - if price is null, total is 0
                        $item_total = $quantity_liters * ($price_per_liter ?? 0);
                        $total_amount += $item_total;
                        
                        $itemStmt = $pdo->prepare("INSERT INTO gasoline_po_items (po_id, gasoline_type, supplier_id, vehicle_id, equipment_id, driver_operator_id, manual_driver_name, purpose, quantity_liters, price_per_liter, odometer_reading, date_issued) 
                                                 VALUES (:po_id, :gasoline_type, :supplier_id, :vehicle_id, :equipment_id, :driver_operator_id, :manual_driver_name, :purpose, :quantity_liters, :price_per_liter, :odometer_reading, :date_issued)");
                        $itemStmt->bindParam(':po_id', $po_id);
                        $itemStmt->bindParam(':gasoline_type', $gasoline_type);
                        $itemStmt->bindParam(':supplier_id', $supplier_id_item);
                        $itemStmt->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_INT);
                        $itemStmt->bindParam(':equipment_id', $equipment_id, PDO::PARAM_INT);
                        $itemStmt->bindParam(':driver_operator_id', $driver_operator_id, PDO::PARAM_INT);
                        $itemStmt->bindParam(':manual_driver_name', $manual_driver_name);
                        $itemStmt->bindParam(':purpose', $purpose);
                        $itemStmt->bindParam(':quantity_liters', $quantity_liters);
                        $itemStmt->bindParam(':price_per_liter', $price_per_liter);
                        $itemStmt->bindParam(':odometer_reading', $odometer_reading);
                        $itemStmt->bindParam(':date_issued', $date_issued);
                        $itemStmt->execute();
                        
                        // If PO is approved, also insert into gasoline_movements
                        if ($status === 'approved') {
                            // Get the driver/operator name for display (for driver_operator column)
                            $driver_operator_name = null;
                            $manual_driver_name_save = null;
                            
                            if (!empty($manual_driver_name)) {
                                // Manual entry - save to manual_driver_name column
                                $manual_driver_name_save = $manual_driver_name;
                                $driver_operator_name = null;
                            } elseif (!empty($driver_operator_id)) {
                                // Get employee name for driver_operator column
                                $getDriverStmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM employee WHERE id = :id");
                                $getDriverStmt->bindParam(':id', $driver_operator_id);
                                $getDriverStmt->execute();
                                $driver_data = $getDriverStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($driver_data) {
                                    $driver_operator_name = $driver_data['firstname'];
                                    if (!empty($driver_data['middlename'])) {
                                        $driver_operator_name .= ' ' . substr($driver_data['middlename'], 0, 1) . '.';
                                    }
                                    $driver_operator_name .= ' ' . $driver_data['lastname'];
                                    if (!empty($driver_data['suffix'])) {
                                        $driver_operator_name .= ' ' . $driver_data['suffix'];
                                    }
                                }
                            }
                            
                            $movementStmt = $pdo->prepare("INSERT INTO gasoline_movements (
                                gasoline_type, 
                                movement_type, 
                                quantity_liters, 
                                price_per_liter, 
                                movement_date, 
                                supplier_id, 
                                purchase_order, 
                                po_id, 
                                vehicle_id, 
                                equipment_id, 
                                driver_operator_id,
                                manual_driver_name,
                                driver_operator,
                                purpose, 
                                odometer_reading
                            ) VALUES (
                                :gasoline_type, 
                                'in', 
                                :quantity_liters, 
                                :price_per_liter, 
                                :movement_date, 
                                :supplier_id, 
                                :purchase_order, 
                                :po_id, 
                                :vehicle_id, 
                                :equipment_id, 
                                :driver_operator_id, 
                                :manual_driver_name,
                                :driver_operator,
                                :purpose, 
                                :odometer_reading
                            )");
                            
                            $movementStmt->bindParam(':gasoline_type', $gasoline_type);
                            $movementStmt->bindParam(':quantity_liters', $quantity_liters);
                            $movementStmt->bindParam(':price_per_liter', $price_per_liter);
                            $movementStmt->bindParam(':movement_date', $po_date);
                            $movementStmt->bindParam(':supplier_id', $supplier_id_item);
                            $movementStmt->bindParam(':purchase_order', $po_number);
                            $movementStmt->bindParam(':po_id', $po_id);
                            $movementStmt->bindParam(':vehicle_id', $vehicle_id);
                            $movementStmt->bindParam(':equipment_id', $equipment_id);
                            $movementStmt->bindParam(':driver_operator_id', $driver_operator_id);
                            $movementStmt->bindParam(':manual_driver_name', $manual_driver_name_save);
                            $movementStmt->bindParam(':driver_operator', $driver_operator_name);
                            $movementStmt->bindParam(':purpose', $purpose);
                            $movementStmt->bindParam(':odometer_reading', $odometer_reading);
                            $movementStmt->execute();
                        }
                    }
                }
                
                // Update total amount
                $updateStmt = $pdo->prepare("UPDATE gasoline_purchase_orders SET total_amount = :total_amount WHERE id = :id");
                $updateStmt->bindParam(':total_amount', $total_amount);
                $updateStmt->bindParam(':id', $po_id);
                $updateStmt->execute();
            }
            
            // Commit transaction
            $pdo->commit();
            
            $swal_data = array(
                'title' => 'Success!',
                'text' => 'Purchase Order created successfully! ' . ($status === 'pending' ? 'Waiting for CEO approval.' : ''),
                'icon' => 'success'
            );
            
            // Redirect to prevent form resubmission
            $_SESSION['swal_data'] = $swal_data;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Error creating purchase order: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
        
    } elseif ($action === 'update_po') {
        // Update existing purchase order
        $po_id = $_POST['po_id'] ?? '';
        $po_date = $_POST['po_date'] ?? '';
        $status = $_POST['status'] ?? 'pending';
        $prepared_by = $_POST['prepared_by'] ?? '';
        $total_amount = 0;
        
        // We'll determine the main supplier from the first item's supplier
        $main_supplier_id = null;
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Get the main supplier from the first item (if items exist)
            if (isset($_POST['item_gasoline_type']) && is_array($_POST['item_gasoline_type'])) {
                foreach ($_POST['item_gasoline_type'] as $index => $gasoline_type) {
                    if (!empty($gasoline_type) && !empty($_POST['item_supplier_id'][$index])) {
                        $main_supplier_id = $_POST['item_supplier_id'][$index];
                        
                        // Validate supplier exists
                        $checkSupplierStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :supplier_id");
                        $checkSupplierStmt->bindParam(':supplier_id', $main_supplier_id);
                        $checkSupplierStmt->execute();
                        
                        if (!$checkSupplierStmt->fetch()) {
                            throw new Exception("Supplier ID $main_supplier_id does not exist in the suppliers table.");
                        }
                        break;
                    }
                }
            }
            
            if (!$main_supplier_id) {
                throw new Exception("At least one item must have a supplier selected.");
            }
            
            // First, check if PO is still editable
            $checkStmt = $pdo->prepare("SELECT status, po_number FROM gasoline_purchase_orders WHERE id = :id");
            $checkStmt->bindParam(':id', $po_id);
            $checkStmt->execute();
            $current_po = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_po) {
                throw new Exception("Purchase order not found.");
            }
            
            if (in_array($current_po['status'], ['delivered', 'cancelled'])) {
                throw new Exception("Cannot edit a PO that has been delivered or cancelled.");
            }
            
            $current_status = $current_po['status'];
            $current_po_number = $current_po['po_number'];
            
            // Check if user is trying to change status to 'approved' without permission
            if ($status === 'approved' && !$can_approve_po) {
                throw new Exception("Only Admin with CEO position can approve purchase orders.");
            }
            
            // Get existing items for comparison and to delete movements if needed
            $existingItemsStmt = $pdo->prepare("SELECT * FROM gasoline_po_items WHERE po_id = :po_id");
            $existingItemsStmt->bindParam(':po_id', $po_id);
            $existingItemsStmt->execute();
            $existing_items = $existingItemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If PO was approved, we need to delete existing movements before updating
            if ($current_status === 'approved') {
                $deleteMovementsStmt = $pdo->prepare("DELETE FROM gasoline_movements WHERE po_id = :po_id AND movement_type = 'in'");
                $deleteMovementsStmt->bindParam(':po_id', $po_id);
                $deleteMovementsStmt->execute();
            }
            
            // Delete existing items
            $deleteItemsStmt = $pdo->prepare("DELETE FROM gasoline_po_items WHERE po_id = :po_id");
            $deleteItemsStmt->bindParam(':po_id', $po_id);
            $deleteItemsStmt->execute();
            
            // Update purchase order
            $poStmt = $pdo->prepare("UPDATE gasoline_purchase_orders SET po_date = :po_date, supplier_id = :supplier_id, status = :status, prepared_by = :prepared_by, total_amount = :total_amount WHERE id = :id");
            $poStmt->bindParam(':id', $po_id);
            $poStmt->bindParam(':po_date', $po_date);
            $poStmt->bindParam(':supplier_id', $main_supplier_id);
            $poStmt->bindParam(':status', $status);
            $poStmt->bindParam(':prepared_by', $prepared_by);
            $poStmt->bindParam(':total_amount', $total_amount);
            $poStmt->execute();
            
            // Insert updated PO items if provided
            if (isset($_POST['item_gasoline_type']) && is_array($_POST['item_gasoline_type'])) {
                $itemCount = count($_POST['item_gasoline_type']);
                
                for ($i = 0; $i < $itemCount; $i++) {
                    if (!empty($_POST['item_gasoline_type'][$i])) {
                        $gasoline_type = $_POST['item_gasoline_type'][$i] ?? '';
                        $supplier_id_item = $_POST['item_supplier_id'][$i] ?? '';
                        $vehicle_id = !empty($_POST['item_vehicle_id'][$i]) ? $_POST['item_vehicle_id'][$i] : null;
                        $equipment_id = !empty($_POST['item_equipment_id'][$i]) ? $_POST['item_equipment_id'][$i] : null;
                        $driver_operator_id = !empty($_POST['item_driver_operator_id'][$i]) ? $_POST['item_driver_operator_id'][$i] : null;
                        $manual_driver_name = !empty($_POST['item_manual_driver_name'][$i]) ? $_POST['item_manual_driver_name'][$i] : null;
                        $purpose = $_POST['item_purpose'][$i] ?? '';
                        $quantity_liters = $_POST['item_quantity_liters'][$i] ?? 0;
                        $price_per_liter = !empty($_POST['item_price_per_liter'][$i]) ? $_POST['item_price_per_liter'][$i] : null;
                        $odometer_reading = !empty($_POST['item_odometer_reading'][$i]) ? $_POST['item_odometer_reading'][$i] : null;
                        $date_issued = $_POST['item_date_issued'][$i] ?? '';
                        
                        // Validate required fields
                        if (empty($gasoline_type) || empty($supplier_id_item) || 
                            empty($purpose) || empty($quantity_liters) || empty($date_issued)) {
                            continue;
                        }
                        
                        // Handle driver_operator_id: if value is 'other' or not a valid ID, set to NULL
                        if ($driver_operator_id === 'other' || !is_numeric($driver_operator_id)) {
                            $driver_operator_id = null;
                        } else {
                            // Validate that the employee exists and has position 'Driver'
                            if ($driver_operator_id) {
                                $checkDriverStmt = $pdo->prepare("SELECT id FROM employee WHERE id = :id AND position = 'Driver'");
                                $checkDriverStmt->bindParam(':id', $driver_operator_id);
                                $checkDriverStmt->execute();
                                if (!$checkDriverStmt->fetch()) {
                                    $driver_operator_id = null;
                                }
                            }
                        }
                        
                        // Validate supplier exists for each item
                        $checkSupplierStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :supplier_id");
                        $checkSupplierStmt->bindParam(':supplier_id', $supplier_id_item);
                        $checkSupplierStmt->execute();
                        
                        if (!$checkSupplierStmt->fetch()) {
                            throw new Exception("Supplier ID $supplier_id_item does not exist in the suppliers table.");
                        }
                        
                        // Calculate item total
                        $item_total = $quantity_liters * ($price_per_liter ?? 0);
                        $total_amount += $item_total;
                        
                        $itemStmt = $pdo->prepare("INSERT INTO gasoline_po_items (po_id, gasoline_type, supplier_id, vehicle_id, equipment_id, driver_operator_id, manual_driver_name, purpose, quantity_liters, price_per_liter, odometer_reading, date_issued) 
                                                 VALUES (:po_id, :gasoline_type, :supplier_id, :vehicle_id, :equipment_id, :driver_operator_id, :manual_driver_name, :purpose, :quantity_liters, :price_per_liter, :odometer_reading, :date_issued)");
                        $itemStmt->bindParam(':po_id', $po_id);
                        $itemStmt->bindParam(':gasoline_type', $gasoline_type);
                        $itemStmt->bindParam(':supplier_id', $supplier_id_item);
                        $itemStmt->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_INT);
                        $itemStmt->bindParam(':equipment_id', $equipment_id, PDO::PARAM_INT);
                        $itemStmt->bindParam(':driver_operator_id', $driver_operator_id, PDO::PARAM_INT);
                        $itemStmt->bindParam(':manual_driver_name', $manual_driver_name);
                        $itemStmt->bindParam(':purpose', $purpose);
                        $itemStmt->bindParam(':quantity_liters', $quantity_liters);
                        $itemStmt->bindParam(':price_per_liter', $price_per_liter);
                        $itemStmt->bindParam(':odometer_reading', $odometer_reading);
                        $itemStmt->bindParam(':date_issued', $date_issued);
                        $itemStmt->execute();
                        
                        // If PO is approved, also insert into gasoline_movements
                        if ($status === 'approved') {
                            // Get the driver/operator name for display (for driver_operator column)
                            $driver_operator_name = null;
                            $manual_driver_name_save = null;
                            
                            if (!empty($manual_driver_name)) {
                                // Manual entry - save to manual_driver_name column
                                $manual_driver_name_save = $manual_driver_name;
                                $driver_operator_name = null;
                            } elseif (!empty($driver_operator_id)) {
                                // Get employee name for driver_operator column
                                $getDriverStmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM employee WHERE id = :id");
                                $getDriverStmt->bindParam(':id', $driver_operator_id);
                                $getDriverStmt->execute();
                                $driver_data = $getDriverStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($driver_data) {
                                    $driver_operator_name = $driver_data['firstname'];
                                    if (!empty($driver_data['middlename'])) {
                                        $driver_operator_name .= ' ' . substr($driver_data['middlename'], 0, 1) . '.';
                                    }
                                    $driver_operator_name .= ' ' . $driver_data['lastname'];
                                    if (!empty($driver_data['suffix'])) {
                                        $driver_operator_name .= ' ' . $driver_data['suffix'];
                                    }
                                }
                            }
                            
                            $movementStmt = $pdo->prepare("INSERT INTO gasoline_movements (
                                gasoline_type, 
                                movement_type, 
                                quantity_liters, 
                                price_per_liter, 
                                movement_date, 
                                supplier_id, 
                                purchase_order, 
                                po_id, 
                                vehicle_id, 
                                equipment_id, 
                                driver_operator_id,
                                manual_driver_name,
                                driver_operator,
                                purpose, 
                                odometer_reading
                            ) VALUES (
                                :gasoline_type, 
                                'in', 
                                :quantity_liters, 
                                :price_per_liter, 
                                :movement_date, 
                                :supplier_id, 
                                :purchase_order, 
                                :po_id, 
                                :vehicle_id, 
                                :equipment_id, 
                                :driver_operator_id, 
                                :manual_driver_name,
                                :driver_operator,
                                :purpose, 
                                :odometer_reading
                            )");
                            
                            $movementStmt->bindParam(':gasoline_type', $gasoline_type);
                            $movementStmt->bindParam(':quantity_liters', $quantity_liters);
                            $movementStmt->bindParam(':price_per_liter', $price_per_liter);
                            $movementStmt->bindParam(':movement_date', $po_date);
                            $movementStmt->bindParam(':supplier_id', $supplier_id_item);
                            $movementStmt->bindParam(':purchase_order', $current_po_number);
                            $movementStmt->bindParam(':po_id', $po_id);
                            $movementStmt->bindParam(':vehicle_id', $vehicle_id);
                            $movementStmt->bindParam(':equipment_id', $equipment_id);
                            $movementStmt->bindParam(':driver_operator_id', $driver_operator_id);
                            $movementStmt->bindParam(':manual_driver_name', $manual_driver_name_save);
                            $movementStmt->bindParam(':driver_operator', $driver_operator_name);
                            $movementStmt->bindParam(':purpose', $purpose);
                            $movementStmt->bindParam(':odometer_reading', $odometer_reading);
                            $movementStmt->execute();
                        }
                    }
                }
                
                // Update total amount
                $updateStmt = $pdo->prepare("UPDATE gasoline_purchase_orders SET total_amount = :total_amount WHERE id = :id");
                $updateStmt->bindParam(':total_amount', $total_amount);
                $updateStmt->bindParam(':id', $po_id);
                $updateStmt->execute();
            }
            
            // Commit transaction
            $pdo->commit();
            
            $swal_data = array(
                'title' => 'Success!',
                'text' => 'Purchase Order updated successfully!' . ($status === 'pending' ? ' Waiting for CEO approval.' : ''),
                'icon' => 'success'
            );
            
            // Redirect to prevent form resubmission
            $_SESSION['swal_data'] = $swal_data;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Error updating purchase order: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
        
    } elseif ($action === 'update_status') {
        // Update PO status
        $po_id = $_POST['po_id'] ?? '';
        $status = $_POST['status'] ?? '';
        
        // Check if user can approve POs
        if ($status === 'approved' && !$can_approve_po) {
            $swal_data = array(
                'title' => 'Access Denied!',
                'text' => 'Only Admin with CEO position can approve purchase orders.',
                'icon' => 'error'
            );
        } else {
            try {
                // Get current PO details
                $currentStmt = $pdo->prepare("SELECT status, po_number, po_date FROM gasoline_purchase_orders WHERE id = :id");
                $currentStmt->bindParam(':id', $po_id);
                $currentStmt->execute();
                $current_po = $currentStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_po) {
                    throw new Exception("Purchase order not found.");
                }
                
                // Begin transaction
                $pdo->beginTransaction();
                
                // If changing from approved to another status, delete movements
                if ($current_po['status'] === 'approved' && $status !== 'approved') {
                    $deleteMovementsStmt = $pdo->prepare("DELETE FROM gasoline_movements WHERE po_id = :po_id AND movement_type = 'in'");
                    $deleteMovementsStmt->bindParam(':po_id', $po_id);
                    $deleteMovementsStmt->execute();
                }
                
                // If changing to approved, add movements
                if ($status === 'approved' && $current_po['status'] !== 'approved') {
                    // Get PO items
                    $itemsStmt = $pdo->prepare("SELECT * FROM gasoline_po_items WHERE po_id = :po_id");
                    $itemsStmt->bindParam(':po_id', $po_id);
                    $itemsStmt->execute();
                    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($items as $item) {
                        // Validate supplier exists before inserting movement
                        $checkSupplierStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :supplier_id");
                        $checkSupplierStmt->bindParam(':supplier_id', $item['supplier_id']);
                        $checkSupplierStmt->execute();
                        
                        if (!$checkSupplierStmt->fetch()) {
                            throw new Exception("Supplier ID " . $item['supplier_id'] . " does not exist in the suppliers table for item: " . $item['gasoline_type']);
                        }
                        
                        // Get the driver/operator name for display (for driver_operator column)
                        $driver_operator_name = null;
                        $manual_driver_name_save = null;
                        
                        if (!empty($item['manual_driver_name'])) {
                            // Manual entry - save to manual_driver_name column
                            $manual_driver_name_save = $item['manual_driver_name'];
                            $driver_operator_name = null;
                        } elseif (!empty($item['driver_operator_id'])) {
                            // Get employee name for driver_operator column
                            $getDriverStmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM employee WHERE id = :id");
                            $getDriverStmt->bindParam(':id', $item['driver_operator_id']);
                            $getDriverStmt->execute();
                            $driver_data = $getDriverStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($driver_data) {
                                $driver_operator_name = $driver_data['firstname'];
                                if (!empty($driver_data['middlename'])) {
                                    $driver_operator_name .= ' ' . substr($driver_data['middlename'], 0, 1) . '.';
                                }
                                $driver_operator_name .= ' ' . $driver_data['lastname'];
                                if (!empty($driver_data['suffix'])) {
                                    $driver_operator_name .= ' ' . $driver_data['suffix'];
                                }
                            }
                        }
                        
                        $movementStmt = $pdo->prepare("INSERT INTO gasoline_movements (
                            gasoline_type, 
                            movement_type, 
                            quantity_liters, 
                            price_per_liter, 
                            movement_date, 
                            supplier_id, 
                            purchase_order, 
                            po_id, 
                            vehicle_id, 
                            equipment_id, 
                            driver_operator_id,
                            manual_driver_name,
                            driver_operator,
                            purpose, 
                            odometer_reading
                        ) VALUES (
                            :gasoline_type, 
                            'in', 
                            :quantity_liters, 
                            :price_per_liter, 
                            :movement_date, 
                            :supplier_id, 
                            :purchase_order, 
                            :po_id, 
                            :vehicle_id, 
                            :equipment_id, 
                            :driver_operator_id, 
                            :manual_driver_name,
                            :driver_operator,
                            :purpose, 
                            :odometer_reading
                        )");
                        
                        $movementStmt->bindParam(':gasoline_type', $item['gasoline_type']);
                        $movementStmt->bindParam(':quantity_liters', $item['quantity_liters']);
                        $movementStmt->bindParam(':price_per_liter', $item['price_per_liter']);
                        $movementStmt->bindParam(':movement_date', $current_po['po_date']);
                        $movementStmt->bindParam(':supplier_id', $item['supplier_id']);
                        $movementStmt->bindParam(':purchase_order', $current_po['po_number']);
                        $movementStmt->bindParam(':po_id', $po_id);
                        $movementStmt->bindParam(':vehicle_id', $item['vehicle_id']);
                        $movementStmt->bindParam(':equipment_id', $item['equipment_id']);
                        $movementStmt->bindParam(':driver_operator_id', $item['driver_operator_id']);
                        $movementStmt->bindParam(':manual_driver_name', $manual_driver_name_save);
                        $movementStmt->bindParam(':driver_operator', $driver_operator_name);
                        $movementStmt->bindParam(':purpose', $item['purpose']);
                        $movementStmt->bindParam(':odometer_reading', $item['odometer_reading']);
                        $movementStmt->execute();
                    }
                }
                
                // Update PO status
                $updateStmt = $pdo->prepare("UPDATE gasoline_purchase_orders SET status = :status WHERE id = :id");
                $updateStmt->bindParam(':status', $status);
                $updateStmt->bindParam(':id', $po_id);
                
                if ($updateStmt->execute()) {
                    $pdo->commit();
                    $swal_data = array(
                        'title' => 'Success!',
                        'text' => 'Purchase Order status updated successfully!',
                        'icon' => 'success'
                    );
                } else {
                    $pdo->rollBack();
                    $swal_data = array(
                        'title' => 'Error!',
                        'text' => 'Error updating purchase order status.',
                        'icon' => 'error'
                    );
                }
            } catch(Exception $e) {
                $pdo->rollBack();
                $swal_data = array(
                    'title' => 'Database Error!',
                    'text' => 'Database error: ' . $e->getMessage(),
                    'icon' => 'error'
                );
            }
        }
        
    } elseif ($action === 'add_delivery') {
        // Add delivery to PO
        $po_id = $_POST['po_id'] ?? '';
        $delivery_date = $_POST['delivery_date'] ?? '';
        $delivery_notes = $_POST['delivery_notes'] ?? '';
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Get PO details
            $poDetailsStmt = $pdo->prepare("SELECT po_number, supplier_id FROM gasoline_purchase_orders WHERE id = :po_id");
            $poDetailsStmt->bindParam(':po_id', $po_id);
            $poDetailsStmt->execute();
            $po_details = $poDetailsStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$po_details) {
                throw new Exception("Purchase order not found.");
            }
            
            // Validate main supplier exists
            $checkSupplierStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :supplier_id");
            $checkSupplierStmt->bindParam(':supplier_id', $po_details['supplier_id']);
            $checkSupplierStmt->execute();
            
            if (!$checkSupplierStmt->fetch()) {
                throw new Exception("Main supplier ID " . $po_details['supplier_id'] . " does not exist in the suppliers table.");
            }
            
            // Get PO items
            $itemsStmt = $pdo->prepare("SELECT * FROM gasoline_po_items WHERE po_id = :po_id");
            $itemsStmt->bindParam(':po_id', $po_id);
            $itemsStmt->execute();
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($items)) {
                throw new Exception("No items found for this purchase order.");
            }
            
            // Process each item as delivered
            foreach ($items as $item) {
                // Validate supplier for each item
                $checkItemSupplierStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :supplier_id");
                $checkItemSupplierStmt->bindParam(':supplier_id', $item['supplier_id']);
                $checkItemSupplierStmt->execute();
                
                if (!$checkItemSupplierStmt->fetch()) {
                    throw new Exception("Supplier ID " . $item['supplier_id'] . " does not exist in the suppliers table for item: " . $item['gasoline_type']);
                }
                
                // Get the main tank for this gasoline type (simplified - you might want to improve this logic)
                $tankStmt = $pdo->prepare("SELECT id FROM gasoline_tanks ORDER BY id LIMIT 1");
                $tankStmt->execute();
                $tank = $tankStmt->fetch(PDO::FETCH_ASSOC);
                $tank_id = $tank['id'] ?? 1;
                
                // Get the driver/operator name for display (for driver_operator column)
                $driver_operator_name = null;
                $manual_driver_name_save = null;
                
                if (!empty($item['manual_driver_name'])) {
                    // Manual entry - save to manual_driver_name column
                    $manual_driver_name_save = $item['manual_driver_name'];
                    $driver_operator_name = null;
                } elseif (!empty($item['driver_operator_id'])) {
                    // Get employee name for driver_operator column
                    $getDriverStmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM employee WHERE id = :id");
                    $getDriverStmt->bindParam(':id', $item['driver_operator_id']);
                    $getDriverStmt->execute();
                    $driver_data = $getDriverStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($driver_data) {
                        $driver_operator_name = $driver_data['firstname'];
                        if (!empty($driver_data['middlename'])) {
                            $driver_operator_name .= ' ' . substr($driver_data['middlename'], 0, 1) . '.';
                        }
                        $driver_operator_name .= ' ' . $driver_data['lastname'];
                        if (!empty($driver_data['suffix'])) {
                            $driver_operator_name .= ' ' . $driver_data['suffix'];
                        }
                    }
                }
                
                // Insert into gasoline_movements
                $movementStmt = $pdo->prepare("INSERT INTO gasoline_movements (gasoline_type, supplier_id, tank_id, quantity_liters, price_per_liter, movement_type, movement_date, purchase_order, po_id, vehicle_id, equipment_id, driver_operator_id, manual_driver_name, driver_operator, purpose, odometer_reading) 
                                             VALUES (:gasoline_type, :supplier_id, :tank_id, :quantity_liters, :price_per_liter, 'in', :delivery_date, :purchase_order, :po_id, :vehicle_id, :equipment_id, :driver_operator_id, :manual_driver_name, :driver_operator, :purpose, :odometer_reading)");
                $movementStmt->bindParam(':gasoline_type', $item['gasoline_type']);
                $movementStmt->bindParam(':supplier_id', $item['supplier_id']);
                $movementStmt->bindParam(':tank_id', $tank_id);
                $movementStmt->bindParam(':quantity_liters', $item['quantity_liters']);
                $movementStmt->bindParam(':price_per_liter', $item['price_per_liter']);
                $movementStmt->bindParam(':delivery_date', $delivery_date);
                $movementStmt->bindParam(':purchase_order', $po_details['po_number']);
                $movementStmt->bindParam(':po_id', $po_id);
                $movementStmt->bindParam(':vehicle_id', $item['vehicle_id']);
                $movementStmt->bindParam(':equipment_id', $item['equipment_id']);
                $movementStmt->bindParam(':driver_operator_id', $item['driver_operator_id']);
                $movementStmt->bindParam(':manual_driver_name', $manual_driver_name_save);
                $movementStmt->bindParam(':driver_operator', $driver_operator_name);
                $movementStmt->bindParam(':purpose', $item['purpose']);
                $movementStmt->bindParam(':odometer_reading', $item['odometer_reading']);
                
                if (!$movementStmt->execute()) {
                    $errorInfo = $movementStmt->errorInfo();
                    throw new Exception("Failed to insert movement: " . $errorInfo[2]);
                }
                
                // Insert into gasoline_batches for FIFO tracking
                $batchStmt = $pdo->prepare("INSERT INTO gasoline_batches (gasoline_type, tank_id, quantity_liters, price_per_liter, date_received, purchase_order, supplier_id, po_id) 
                                          VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter, :delivery_date, :purchase_order, :supplier_id, :po_id)");
                $batchStmt->bindParam(':gasoline_type', $item['gasoline_type']);
                $batchStmt->bindParam(':tank_id', $tank_id);
                $batchStmt->bindParam(':quantity_liters', $item['quantity_liters']);
                $batchStmt->bindParam(':price_per_liter', $item['price_per_liter']);
                $batchStmt->bindParam(':delivery_date', $delivery_date);
                $batchStmt->bindParam(':purchase_order', $po_details['po_number']);
                $batchStmt->bindParam(':supplier_id', $item['supplier_id']);
                $batchStmt->bindParam(':po_id', $po_id);
                $batchStmt->execute();
                
                // Update gasoline inventory levels
                $updateStmt = $pdo->prepare("INSERT INTO gasoline_inventory (gasoline_type, tank_id, quantity_liters, price_per_liter) 
                                            VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter)
                                            ON DUPLICATE KEY UPDATE 
                                            quantity_liters = quantity_liters + :quantity_liters,
                                            price_per_liter = (SELECT 
                                                SUM(quantity_liters * price_per_liter) / SUM(quantity_liters) 
                                                FROM gasoline_batches 
                                                WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0)");
                $updateStmt->bindParam(':gasoline_type', $item['gasoline_type']);
                $updateStmt->bindParam(':tank_id', $tank_id);
                $updateStmt->bindParam(':quantity_liters', $item['quantity_liters']);
                $updateStmt->bindParam(':price_per_liter', $item['price_per_liter']);
                $updateStmt->execute();
            }
            
            // Update PO status to delivered
            $updatePOStmt = $pdo->prepare("UPDATE gasoline_purchase_orders SET status = 'delivered', delivery_date = :delivery_date WHERE id = :id");
            $updatePOStmt->bindParam(':delivery_date', $delivery_date);
            $updatePOStmt->bindParam(':id', $po_id);
            $updatePOStmt->execute();
            
            // Commit transaction
            $pdo->commit();
            
            $swal_data = array(
                'title' => 'Success!',
                'text' => 'Delivery recorded and inventory updated successfully!',
                'icon' => 'success'
            );
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Database Error!',
                'text' => 'Error recording delivery: ' . $e->getMessage(),
                'icon' => 'error'
            );
        } catch(Exception $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Error: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
        
    } elseif ($action === 'cancel_po') {
        // Cancel purchase order
        $po_id = $_POST['po_id'] ?? '';
        $cancel_reason = $_POST['cancel_reason'] ?? '';
        
        try {
            // Get current status
            $currentStmt = $pdo->prepare("SELECT status FROM gasoline_purchase_orders WHERE id = :id");
            $currentStmt->bindParam(':id', $po_id);
            $currentStmt->execute();
            $current_status = $currentStmt->fetchColumn();
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // If PO was approved, delete movements
            if ($current_status === 'approved') {
                $deleteMovementsStmt = $pdo->prepare("DELETE FROM gasoline_movements WHERE po_id = :po_id AND movement_type = 'in'");
                $deleteMovementsStmt->bindParam(':po_id', $po_id);
                $deleteMovementsStmt->execute();
            }
            
            // Update PO status to cancelled
            $updateStmt = $pdo->prepare("UPDATE gasoline_purchase_orders SET status = 'cancelled' WHERE id = :id");
            $updateStmt->bindParam(':id', $po_id);
            
            if ($updateStmt->execute()) {
                $pdo->commit();
                $swal_data = array(
                    'title' => 'Success!',
                    'text' => 'Purchase Order cancelled successfully!',
                    'icon' => 'success'
                );
            } else {
                $pdo->rollBack();
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Error cancelling purchase order.',
                    'icon' => 'error'
                );
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
        
    } elseif ($action === 'issue_gasoline') {
        // Issue gasoline from PO directly
        $po_item_id = $_POST['po_item_id'] ?? '';
        $tank_id = $_POST['tank_id'] ?? '';
        $issue_date = $_POST['issue_date'] ?? '';
        
        try {
            // Get PO item details
            $itemStmt = $pdo->prepare("SELECT poi.*, po.po_number, po.supplier_id 
                                      FROM gasoline_po_items poi
                                      JOIN gasoline_purchase_orders po ON poi.po_id = po.id
                                      WHERE poi.id = :po_item_id");
            $itemStmt->bindParam(':po_item_id', $po_item_id);
            $itemStmt->execute();
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception("PO item not found");
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Check if enough gasoline is available in tank
            $checkStmt = $pdo->prepare("SELECT quantity_liters FROM gasoline_inventory 
                                      WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id");
            $checkStmt->bindParam(':gasoline_type', $item['gasoline_type']);
            $checkStmt->bindParam(':tank_id', $tank_id);
            $checkStmt->execute();
            $current_stock = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_stock || $current_stock['quantity_liters'] < $item['quantity_liters']) {
                throw new Exception("Insufficient gasoline in the selected tank");
            }
            
            // Get the driver/operator name for display (for driver_operator column)
            $driver_operator_name = null;
            $manual_driver_name_save = null;
            
            if (!empty($item['manual_driver_name'])) {
                // Manual entry - save to manual_driver_name column
                $manual_driver_name_save = $item['manual_driver_name'];
                $driver_operator_name = null;
            } elseif (!empty($item['driver_operator_id'])) {
                // Get employee name for driver_operator column
                $getDriverStmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM employee WHERE id = :id");
                $getDriverStmt->bindParam(':id', $item['driver_operator_id']);
                $getDriverStmt->execute();
                $driver_data = $getDriverStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($driver_data) {
                    $driver_operator_name = $driver_data['firstname'];
                    if (!empty($driver_data['middlename'])) {
                        $driver_operator_name .= ' ' . substr($driver_data['middlename'], 0, 1) . '.';
                    }
                    $driver_operator_name .= ' ' . $driver_data['lastname'];
                    if (!empty($driver_data['suffix'])) {
                        $driver_operator_name .= ' ' . $driver_data['suffix'];
                    }
                }
            }
            
            // Record gasoline out movement
            $outStmt = $pdo->prepare("INSERT INTO gasoline_movements (gasoline_type, tank_id, quantity_liters, price_per_liter, movement_type, movement_date, vehicle_id, equipment_id, driver_operator_id, manual_driver_name, driver_operator, purpose, odometer_reading, po_item_id) 
                                    VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter, 'out', :issue_date, :vehicle_id, :equipment_id, :driver_operator_id, :manual_driver_name, :driver_operator, :purpose, :odometer_reading, :po_item_id)");
            $outStmt->bindParam(':gasoline_type', $item['gasoline_type']);
            $outStmt->bindParam(':tank_id', $tank_id);
            $outStmt->bindParam(':quantity_liters', $item['quantity_liters']);
            $outStmt->bindParam(':price_per_liter', $item['price_per_liter']);
            $outStmt->bindParam(':issue_date', $issue_date);
            $outStmt->bindParam(':vehicle_id', $item['vehicle_id']);
            $outStmt->bindParam(':equipment_id', $item['equipment_id']);
            $outStmt->bindParam(':driver_operator_id', $item['driver_operator_id']);
            $outStmt->bindParam(':manual_driver_name', $manual_driver_name_save);
            $outStmt->bindParam(':driver_operator', $driver_operator_name);
            $outStmt->bindParam(':purpose', $item['purpose']);
            $outStmt->bindParam(':odometer_reading', $item['odometer_reading']);
            $outStmt->bindParam(':po_item_id', $po_item_id);
            $outStmt->execute();
            
            // Get the oldest batches first (FIFO)
            $batchStmt = $pdo->prepare("SELECT id, quantity_liters, price_per_liter FROM gasoline_batches 
                                      WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0 
                                      ORDER BY date_received ASC, id ASC");
            $batchStmt->bindParam(':gasoline_type', $item['gasoline_type']);
            $batchStmt->bindParam(':tank_id', $tank_id);
            $batchStmt->execute();
            $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $remaining_quantity = $item['quantity_liters'];
            
            foreach ($batches as $batch) {
                if ($remaining_quantity <= 0) break;
                
                $batch_quantity_used = min($remaining_quantity, $batch['quantity_liters']);
                
                // Update the batch quantity
                $updateBatchStmt = $pdo->prepare("UPDATE gasoline_batches SET quantity_liters = quantity_liters - :quantity_used 
                                                 WHERE id = :batch_id");
                $updateBatchStmt->bindParam(':quantity_used', $batch_quantity_used);
                $updateBatchStmt->bindParam(':batch_id', $batch['id']);
                $updateBatchStmt->execute();
                
                $remaining_quantity -= $batch_quantity_used;
            }
            
            // Calculate new weighted average price
            $avgPriceStmt = $pdo->prepare("
                SELECT 
                    SUM(quantity_liters * price_per_liter) / SUM(quantity_liters) as avg_price
                FROM gasoline_batches 
                WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0
            ");
            $avgPriceStmt->bindParam(':gasoline_type', $item['gasoline_type']);
            $avgPriceStmt->bindParam(':tank_id', $tank_id);
            $avgPriceStmt->execute();
            $avgPriceData = $avgPriceStmt->fetch(PDO::FETCH_ASSOC);
            
            $weighted_avg_price = $avgPriceData['avg_price'] ?? 0;
            
            // Update inventory levels
            $updateStmt = $pdo->prepare("UPDATE gasoline_inventory SET quantity_liters = quantity_liters - :quantity_liters, price_per_liter = :price_per_liter
                                        WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id");
            $updateStmt->bindParam(':gasoline_type', $item['gasoline_type']);
            $updateStmt->bindParam(':tank_id', $tank_id);
            $updateStmt->bindParam(':quantity_liters', $item['quantity_liters']);
            $updateStmt->bindParam(':price_per_liter', $weighted_avg_price);
            $updateStmt->execute();
            
            // Mark PO item as issued
            $updateItemStmt = $pdo->prepare("UPDATE gasoline_po_items SET issued = 1, issue_date = :issue_date WHERE id = :id");
            $updateItemStmt->bindParam(':issue_date', $issue_date);
            $updateItemStmt->bindParam(':id', $po_item_id);
            $updateItemStmt->execute();
            
            // Commit transaction
            $pdo->commit();
            
            $swal_data = array(
                'title' => 'Success!',
                'text' => 'Gasoline issued successfully from purchase order!',
                'icon' => 'success'
            );
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Error issuing gasoline: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
        
    } elseif ($action === 'approve_po') {
        // Approve purchase order with signature
        $po_id = $_POST['po_id'] ?? '';
        $approved_by = $_SESSION['user_id'];
        $signature_data = $_POST['signature_data'] ?? '';
        $signature_type = $_POST['signature_type'] ?? 'draw';
        
        // Check if user can approve POs
        if (!$can_approve_po) {
            $swal_data = array(
                'title' => 'Access Denied!',
                'text' => 'Only Admin with CEO position can approve purchase orders.',
                'icon' => 'error'
            );
        } else {
            if (empty($signature_data)) {
                $swal_data = array(
                    'title' => 'Signature Required!',
                    'text' => 'Please provide your signature to approve this purchase order.',
                    'icon' => 'error'
                );
            } else {
                try {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Get PO details
                    $poDetailsStmt = $pdo->prepare("SELECT po_number, po_date FROM gasoline_purchase_orders WHERE id = :id");
                    $poDetailsStmt->bindParam(':id', $po_id);
                    $poDetailsStmt->execute();
                    $po_details = $poDetailsStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$po_details) {
                        throw new Exception("Purchase order not found.");
                    }
                    
                    // Get all PO items for this PO
                    $itemsStmt = $pdo->prepare("SELECT * FROM gasoline_po_items WHERE po_id = :po_id");
                    $itemsStmt->bindParam(':po_id', $po_id);
                    $itemsStmt->execute();
                    $po_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($po_items)) {
                        throw new Exception("No items found for this purchase order.");
                    }
                    
                    // Insert each PO item into gasoline_movements table
                    foreach ($po_items as $item) {
                        // Validate supplier exists before inserting movement
                        $checkSupplierStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :supplier_id");
                        $checkSupplierStmt->bindParam(':supplier_id', $item['supplier_id']);
                        $checkSupplierStmt->execute();
                        
                        if (!$checkSupplierStmt->fetch()) {
                            throw new Exception("Supplier ID " . $item['supplier_id'] . " does not exist in the suppliers table for item: " . $item['gasoline_type']);
                        }
                        
                        // Get the driver/operator name for display (for driver_operator column)
                        $driver_operator_name = null;
                        $manual_driver_name_save = null;
                        
                        if (!empty($item['manual_driver_name'])) {
                            // Manual entry - save to manual_driver_name column
                            $manual_driver_name_save = $item['manual_driver_name'];
                            $driver_operator_name = null;
                        } elseif (!empty($item['driver_operator_id'])) {
                            // Get employee name for driver_operator column
                            $getDriverStmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM employee WHERE id = :id");
                            $getDriverStmt->bindParam(':id', $item['driver_operator_id']);
                            $getDriverStmt->execute();
                            $driver_data = $getDriverStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($driver_data) {
                                $driver_operator_name = $driver_data['firstname'];
                                if (!empty($driver_data['middlename'])) {
                                    $driver_operator_name .= ' ' . substr($driver_data['middlename'], 0, 1) . '.';
                                }
                                $driver_operator_name .= ' ' . $driver_data['lastname'];
                                if (!empty($driver_data['suffix'])) {
                                    $driver_operator_name .= ' ' . $driver_data['suffix'];
                                }
                            }
                        }
                        
                        $movementStmt = $pdo->prepare("INSERT INTO gasoline_movements (
                            gasoline_type, 
                            movement_type, 
                            quantity_liters, 
                            price_per_liter, 
                            movement_date, 
                            supplier_id, 
                            purchase_order, 
                            po_id, 
                            vehicle_id, 
                            equipment_id, 
                            driver_operator_id,
                            manual_driver_name,
                            driver_operator,
                            purpose, 
                            odometer_reading
                        ) VALUES (
                            :gasoline_type, 
                            'in', 
                            :quantity_liters, 
                            :price_per_liter, 
                            :movement_date, 
                            :supplier_id, 
                            :purchase_order, 
                            :po_id, 
                            :vehicle_id, 
                            :equipment_id, 
                            :driver_operator_id, 
                            :manual_driver_name,
                            :driver_operator,
                            :purpose, 
                            :odometer_reading
                        )");
                        
                        $movementStmt->bindParam(':gasoline_type', $item['gasoline_type']);
                        $movementStmt->bindParam(':quantity_liters', $item['quantity_liters']);
                        $movementStmt->bindParam(':price_per_liter', $item['price_per_liter']);
                        $movementStmt->bindParam(':movement_date', $po_details['po_date']);
                        $movementStmt->bindParam(':supplier_id', $item['supplier_id']);
                        $movementStmt->bindParam(':purchase_order', $po_details['po_number']);
                        $movementStmt->bindParam(':po_id', $po_id);
                        $movementStmt->bindParam(':vehicle_id', $item['vehicle_id']);
                        $movementStmt->bindParam(':equipment_id', $item['equipment_id']);
                        $movementStmt->bindParam(':driver_operator_id', $item['driver_operator_id']);
                        $movementStmt->bindParam(':manual_driver_name', $manual_driver_name_save);
                        $movementStmt->bindParam(':driver_operator', $driver_operator_name);
                        $movementStmt->bindParam(':purpose', $item['purpose']);
                        $movementStmt->bindParam(':odometer_reading', $item['odometer_reading']);
                        $movementStmt->execute();
                    }
                    
                    // Update PO status to approved and save signature
                    $updateStmt = $pdo->prepare("UPDATE gasoline_purchase_orders 
                                                SET status = 'approved', 
                                                    approved_by = :approved_by,
                                                    approval_signature = :signature,
                                                    approval_date = NOW()
                                                WHERE id = :id AND status = 'pending'");
                    $updateStmt->bindParam(':approved_by', $approved_by);
                    $updateStmt->bindParam(':signature', $signature_data);
                    $updateStmt->bindParam(':id', $po_id);
                    
                    if ($updateStmt->execute() && $updateStmt->rowCount() > 0) {
                        // Commit transaction
                        $pdo->commit();
                        
                        $swal_data = array(
                            'title' => 'Success!',
                            'text' => 'Purchase Order approved successfully with signature and items recorded in movements!',
                            'icon' => 'success'
                        );
                    } else {
                        $pdo->rollBack();
                        $swal_data = array(
                            'title' => 'Error!',
                            'text' => 'Error approving purchase order. It may have already been approved or is not in pending status.',
                            'icon' => 'error'
                        );
                    }
                } catch(PDOException $e) {
                    $pdo->rollBack();
                    $swal_data = array(
                        'title' => 'Database Error!',
                        'text' => 'Database error: ' . $e->getMessage(),
                        'icon' => 'error'
                    );
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $swal_data = array(
                        'title' => 'Error!',
                        'text' => 'Error: ' . $e->getMessage(),
                        'icon' => 'error'
                    );
                }
            }
        }
        
    } elseif ($action === 'complete_po') {
        // Complete purchase order (mark as completed with invoice number and signature)
        $po_id = $_POST['po_id'] ?? '';
        $invoice_number = $_POST['invoice_number'] ?? '';
        $signature_data = $_POST['completion_signature_data'] ?? '';
        
        // Check if user can complete POs (Admin with Purchaser position)
        if (!$can_complete_po) {
            $swal_data = array(
                'title' => 'Access Denied!',
                'text' => 'Only Admin with Purchaser position can complete purchase orders.',
                'icon' => 'error'
            );
        } else {
            if (empty($signature_data)) {
                $swal_data = array(
                    'title' => 'Signature Required!',
                    'text' => 'Please provide your signature to complete this purchase order.',
                    'icon' => 'error'
                );
            } else {
                try {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Get current PO details
                    $currentStmt = $pdo->prepare("SELECT status, po_number FROM gasoline_purchase_orders WHERE id = :id");
                    $currentStmt->bindParam(':id', $po_id);
                    $currentStmt->execute();
                    $current_po = $currentStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$current_po) {
                        throw new Exception("Purchase order not found.");
                    }
                    
                    // Check if PO is approved
                    if ($current_po['status'] !== 'approved') {
                        throw new Exception("Only approved purchase orders can be marked as completed.");
                    }
                    
                    // Update PO status to completed and add invoice number and signature
                    $updateStmt = $pdo->prepare("UPDATE gasoline_purchase_orders 
                                                SET status = 'completed', 
                                                    invoice_number = :invoice_number,
                                                    completion_signature = :signature,
                                                    completed_date = NOW() 
                                                WHERE id = :id");
                    $updateStmt->bindParam(':invoice_number', $invoice_number);
                    $updateStmt->bindParam(':signature', $signature_data);
                    $updateStmt->bindParam(':id', $po_id);
                    
                    if ($updateStmt->execute()) {
                        $pdo->commit();
                        $swal_data = array(
                            'title' => 'Success!',
                            'text' => 'Purchase Order marked as completed successfully!' . ($invoice_number ? ' Invoice #: ' . $invoice_number : ''),
                            'icon' => 'success'
                        );
                    } else {
                        $pdo->rollBack();
                        $swal_data = array(
                            'title' => 'Error!',
                            'text' => 'Error completing purchase order.',
                            'icon' => 'error'
                        );
                    }
                } catch(PDOException $e) {
                    $pdo->rollBack();
                    $swal_data = array(
                        'title' => 'Database Error!',
                        'text' => 'Database error: ' . $e->getMessage(),
                        'icon' => 'error'
                    );
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $swal_data = array(
                        'title' => 'Error!',
                        'text' => 'Error: ' . $e->getMessage(),
                        'icon' => 'error'
                    );
                }
            }
        }
        
    } elseif ($action === 'update_invoice') {
        // Update invoice number for completed PO
        $po_id = $_POST['po_id'] ?? '';
        $invoice_number = $_POST['invoice_number'] ?? '';
        
        // Check if user can update invoice (Admin with Purchaser position)
        if (!$can_update_invoice) {
            $swal_data = array(
                'title' => 'Access Denied!',
                'text' => 'Only Admin with Purchaser position can update invoice numbers.',
                'icon' => 'error'
            );
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Get current PO details
                $currentStmt = $pdo->prepare("SELECT status, po_number FROM gasoline_purchase_orders WHERE id = :id");
                $currentStmt->bindParam(':id', $po_id);
                $currentStmt->execute();
                $current_po = $currentStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_po) {
                    throw new Exception("Purchase order not found.");
                }
                
                // Check if PO is completed
                if ($current_po['status'] !== 'completed') {
                    throw new Exception("Only completed purchase orders can have their invoice number updated.");
                }
                
                // Update invoice number
                $updateStmt = $pdo->prepare("UPDATE gasoline_purchase_orders 
                                            SET invoice_number = :invoice_number 
                                            WHERE id = :id");
                $updateStmt->bindParam(':invoice_number', $invoice_number);
                $updateStmt->bindParam(':id', $po_id);
                
                if ($updateStmt->execute()) {
                    $pdo->commit();
                    $swal_data = array(
                        'title' => 'Success!',
                        'text' => 'Invoice number updated successfully!' . ($invoice_number ? ' Invoice #: ' . $invoice_number : ''),
                        'icon' => 'success'
                    );
                } else {
                    $pdo->rollBack();
                    $swal_data = array(
                        'title' => 'Error!',
                        'text' => 'Error updating invoice number.',
                        'icon' => 'error'
                    );
                }
            } catch(PDOException $e) {
                $pdo->rollBack();
                $swal_data = array(
                    'title' => 'Database Error!',
                    'text' => 'Database error: ' . $e->getMessage(),
                    'icon' => 'error'
                );
            } catch(Exception $e) {
                $pdo->rollBack();
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Error: ' . $e->getMessage(),
                    'icon' => 'error'
                );
            }
        }
        
    } elseif ($action === 'delete_po') {
        // Delete purchase order
        $po_id = $_POST['po_id'] ?? '';
        $delete_reason = $_POST['delete_reason'] ?? '';
        
        // Check if user has permission to delete (Admin with CEO position only)
        if (!$can_delete_po) {
            $swal_data = array(
                'title' => 'Access Denied!',
                'text' => 'Only Admin with CEO position can delete purchase orders.',
                'icon' => 'error'
            );
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Get PO details first for logging purposes
                $checkStmt = $pdo->prepare("SELECT po_number, status FROM gasoline_purchase_orders WHERE id = :id");
                $checkStmt->bindParam(':id', $po_id);
                $checkStmt->execute();
                $po_details = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$po_details) {
                    throw new Exception("Purchase order not found.");
                }
                
                // Check if PO can be deleted (only pending, cancelled, or delivered POs can be deleted)
                $allowed_statuses = ['pending', 'cancelled', 'delivered'];
                if (!in_array($po_details['status'], $allowed_statuses)) {
                    throw new Exception("Cannot delete a purchase order with status '" . $po_details['status'] . "'. Only pending, cancelled, or delivered POs can be deleted.");
                }
                
                // If PO was approved, delete movements
                if ($po_details['status'] === 'approved') {
                    $deleteMovementsStmt = $pdo->prepare("DELETE FROM gasoline_movements WHERE po_id = :po_id AND movement_type = 'in'");
                    $deleteMovementsStmt->bindParam(':po_id', $po_id);
                    $deleteMovementsStmt->execute();
                }
                
                // Delete PO items first (foreign key constraint)
                $deleteItemsStmt = $pdo->prepare("DELETE FROM gasoline_po_items WHERE po_id = :po_id");
                $deleteItemsStmt->bindParam(':po_id', $po_id);
                $deleteItemsStmt->execute();
                
                // Delete the PO
                $deleteStmt = $pdo->prepare("DELETE FROM gasoline_purchase_orders WHERE id = :id");
                $deleteStmt->bindParam(':id', $po_id);
                
                if ($deleteStmt->execute() && $deleteStmt->rowCount() > 0) {
                    // Commit transaction
                    $pdo->commit();
                    
                    $swal_data = array(
                        'title' => 'Success!',
                        'text' => 'Purchase Order deleted successfully!',
                        'icon' => 'success'
                    );
                } else {
                    $pdo->rollBack();
                    $swal_data = array(
                        'title' => 'Error!',
                        'text' => 'Error deleting purchase order.',
                        'icon' => 'error'
                    );
                }
            } catch(PDOException $e) {
                $pdo->rollBack();
                $swal_data = array(
                    'title' => 'Database Error!',
                    'text' => 'Database error: ' . $e->getMessage(),
                    'icon' => 'error'
                );
            } catch(Exception $e) {
                $pdo->rollBack();
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Error: ' . $e->getMessage(),
                    'icon' => 'error'
                );
            }
        }
    }
}

// Check for GET request to load PO for editing
if (isset($_GET['edit_po'])) {
    $po_id = $_GET['edit_po'];
    
    try {
        // Get PO details
        $poStmt = $pdo->prepare("SELECT * FROM gasoline_purchase_orders WHERE id = :id");
        $poStmt->bindParam(':id', $po_id);
        $poStmt->execute();
        $edit_po_data = $poStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($edit_po_data) {
            // Get PO items
            $itemsStmt = $pdo->prepare("SELECT * FROM gasoline_po_items WHERE po_id = :po_id");
            $itemsStmt->bindParam(':po_id', $po_id);
            $itemsStmt->execute();
            $edit_po_data['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        $swal_data = array(
            'title' => 'Error!',
            'text' => 'Error loading PO for editing: ' . $e->getMessage(),
            'icon' => 'error'
        );
    }
}

// Check for session swal data (from redirect)
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

