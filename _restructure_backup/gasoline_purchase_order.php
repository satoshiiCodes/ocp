<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Get user details and their role
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.firstname, u.middlename, u.lastname, u.suffix, u.accounttype, u.position FROM users u WHERE u.id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user can approve POs (Admin with CEO position)
$can_approve_po = ($user['accounttype'] === 'Admin' && $user['position'] === 'CEO');

// Check if user can delete POs (Admin with CEO position)
$can_delete_po = ($user['accounttype'] === 'Admin' && $user['position'] === 'CEO');

// Check if user can complete POs (Admin with Purchaser position)
$can_complete_po = ($user['accounttype'] === 'Admin' && $user['position'] === 'Purchaser');

// Check if user can update invoice (Admin with Purchaser position)
$can_update_invoice = ($user['accounttype'] === 'Admin' && $user['position'] === 'Purchaser');

// Format the display name
$display_name = $user['firstname'];
if (!empty($user['middlename'])) {
    $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
}
$display_name .= ' ' . $user['lastname'];
if (!empty($user['suffix'])) {
    $display_name .= ' ' . $user['suffix'];
}

// Helper function to get driver/operator display name (for driver_operator column)
function getDriverOperatorName($item, $pdo) {
    // If manual driver name is provided, return it (will go to manual_driver_name column instead)
    if (!empty($item['manual_driver_name'])) {
        return null; // Return null for driver_operator column, manual_driver_name will be used
    }
    
    // If driver_operator_id is provided, get employee name
    if (!empty($item['driver_operator_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM employee WHERE id = :id");
            $stmt->bindParam(':id', $item['driver_operator_id']);
            $stmt->execute();
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                $name = $employee['firstname'];
                if (!empty($employee['middlename'])) {
                    $name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
                }
                $name .= ' ' . $employee['lastname'];
                if (!empty($employee['suffix'])) {
                    $name .= ' ' . $employee['suffix'];
                }
                return $name;
            }
        } catch (PDOException $e) {
            return null;
        }
    }
    
    return null;
}

// Process actions
$swal_data = array(); // For SweetAlert2 data
$edit_po_data = null; // For storing PO data when editing

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

// Fetch data for dropdowns and tables
try {
    // Gasoline types
    $gasolineTypes = ['Unleaded', 'Premium', 'Diesel'];
    
    // Get suppliers
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM gasoline_suppliers WHERE supplier_type = 'fuel' OR supplier_type IS NULL ORDER BY supplier_name");
    $suppliersStmt->execute();
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vehicles
    $vehiclesStmt = $pdo->prepare("SELECT id, vehicle_name, plate_number FROM vehicles WHERE fuel_type IN ('gasoline', 'diesel', 'petrol') ORDER BY vehicle_name");
    $vehiclesStmt->execute();
    $vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get equipment
    $equipmentStmt = $pdo->prepare("SELECT id, equipment_name FROM equipment WHERE fuel_type IN ('gasoline', 'diesel', 'petrol') ORDER BY equipment_name");
    $equipmentStmt->execute();
    $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get employees for driver/operator dropdown - ONLY SHOW DRIVERS
    $employeesStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix 
        FROM employee 
        WHERE (status = 'active' OR status IS NULL)
        AND position = 'Driver'
        ORDER BY firstname, lastname
    ");
    $employeesStmt->execute();
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tanks for issuing gasoline
    $tanksStmt = $pdo->prepare("SELECT id, tank_name, location FROM gasoline_tanks ORDER BY tank_name");
    $tanksStmt->execute();
    $tanks = $tanksStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get users for prepared_by and approved_by
    $usersStmt = $pdo->prepare("SELECT id, CONCAT(firstname, ' ', lastname) as fullname FROM users ORDER BY firstname");
    $usersStmt->execute();
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    $purchase_orders = $poStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get PO status counts for statistics
    $statusStmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM gasoline_purchase_orders 
        GROUP BY status
    ");
    $statusStmt->execute();
    $status_counts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total value of pending POs
    $pendingValueStmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_pending 
        FROM gasoline_purchase_orders 
        WHERE status IN ('pending', 'approved', 'ordered')
    ");
    $pendingValueStmt->execute();
    $pending_value = $pendingValueStmt->fetch(PDO::FETCH_ASSOC);
    
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
    $pending_items = $pendingItemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $swal_data = array(
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error'
    );
}

// Generate next PO number in format "000001" with proper duplicate check
function generatePONumber() {
    global $pdo;
    
    try {
        // Get the maximum PO number from the database
        $stmt = $pdo->prepare("SELECT po_number FROM gasoline_purchase_orders ORDER BY CAST(po_number AS UNSIGNED) DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['po_number'])) {
            // Extract numeric value from po_number (remove leading zeros)
            $last_number = intval($result['po_number']);
            $next_number = $last_number + 1;
        } else {
            // If no PO exists yet, start from 1
            $next_number = 1;
        }
        
        // Format as "000001" with leading zeros (6 digits)
        $po_number = str_pad($next_number, 6, '0', STR_PAD_LEFT);
        
        // Double-check that this PO number doesn't already exist (in case of race condition)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM gasoline_purchase_orders WHERE po_number = :po_number");
        $checkStmt->bindParam(':po_number', $po_number);
        $checkStmt->execute();
        $exists = $checkStmt->fetchColumn();
        
        // If it somehow exists, increment until we find a unique number
        while ($exists > 0) {
            $next_number++;
            $po_number = str_pad($next_number, 6, '0', STR_PAD_LEFT);
            $checkStmt->bindParam(':po_number', $po_number);
            $checkStmt->execute();
            $exists = $checkStmt->fetchColumn();
        }
        
        return $po_number;
        
    } catch(PDOException $e) {
        // Fallback in case of error - generate a random 6-digit number
        return str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}

// Helper function to format employee name with position
function formatEmployeeNameWithPosition($employee) {
    $name = $employee['firstname'];
    if (!empty($employee['middlename'])) {
        $name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
    }
    $name .= ' ' . $employee['lastname'];
    if (!empty($employee['suffix'])) {
        $name .= ' ' . $employee['suffix'];
    }
    return $name . ' (Driver)';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Gasoline Purchase Orders - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <!-- Include signature pad library -->
        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
        <style>
            .table-responsive {
                overflow-x: auto;
            }
            .dataTable-top {
                padding: 8px 0;
            }
            .dataTable-bottom {
                padding: 8px 0;
            }
            .po-table th {
                background-color: #f8f9fa;
                font-weight: 600;
            }
            .status-badge {
                font-size: 0.85em;
                padding: 0.35em 0.65em;
            }
            .status-pending { background-color: #ffc107; color: #000; }
            .status-approved { background-color: #17a2b8; color: #fff; }
            .status-ordered { background-color: #007bff; color: #fff; }
            .status-delivered { background-color: #28a745; color: #fff; }
            .status-completed { background-color: #28a745; color: #fff; }
            .status-cancelled { background-color: #dc3545; color: #fff; }
            .card-title {
                font-size: 1.1rem;
                font-weight: 600;
            }
            .action-btn-group {
                display: flex;
                flex-direction: column;
                gap: 5px;
                justify-content: center;
                align-items: center;
            }
            .action-btn-group .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
                line-height: 1.5;
                margin: 0;
                width: 100%;
            }
            .action-btn-group .btn-full-width {
                width: 100%;
            }
            .action-btn-group .btn-icon-only {
                width: 100%;
                justify-content: center;
            }
            .item-row {
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
                margin-bottom: 15px;
                background-color: #f8f9fa;
            }
            .item-total {
                font-weight: bold;
                color: #28a745;
            }
            .po-details-card {
                border-left: 4px solid #007bff;
            }
            .po-items-card {
                border-left: 4px solid #28a745;
            }
            .stat-card {
                transition: transform 0.2s ease-in-out;
                height: 100%;
            }
            .stat-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            .stat-icon {
                font-size: 2rem;
                margin-bottom: 1rem;
            }
            .pending-items-card {
                border-left: 4px solid #fd7e14;
            }
            .gasoline-type-badge {
                font-size: 0.75em;
                margin-right: 3px;
                margin-bottom: 3px;
            }
            /* Tooltip styles */
            .tooltip-inner {
                max-width: 200px;
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
            /* Row for icon-only buttons */
            .action-icons-row {
                display: flex;
                gap: 5px;
                justify-content: center;
                width: 100%;
            }
            .action-icons-row .btn {
                flex: 1;
                min-width: 0;
            }
            /* Styles for disabled form controls that still submit values */
            .disabled-submit {
                background-color: #e9ecef;
                opacity: 1;
                pointer-events: none;
                cursor: not-allowed;
                border-color: #ced4da;
            }
            .disabled-submit:focus {
                border-color: #ced4da;
                box-shadow: none;
            }
            .disabled-submit + label {
                color: #6c757d;
            }
            /* Style for readonly select that needs to show it's disabled */
            select.disabled-submit {
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                background-image: none;
            }
            /* Label for waiting approval */
            .waiting-approval-label {
                background-color: #fff3cd;
                color: #856404;
                border: 1px solid #ffeeba;
                padding: 10px 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                font-size: 1rem;
            }
            .waiting-approval-label i {
                font-size: 1.5rem;
                margin-right: 10px;
            }
            .pending-approval-tag {
                font-size: 0.7rem;
                background-color: #ffc107;
                color: #000;
                padding: 2px 5px;
                border-radius: 3px;
                margin-left: 5px;
                white-space: nowrap;
            }
            .ceo-only {
                font-size: 0.8rem;
                color: #6c757d;
                margin-top: 5px;
                font-style: italic;
            }
            .invoice-badge {
                font-size: 0.75rem;
                background-color: #e9ecef;
                color: #495057;
                padding: 2px 5px;
                border-radius: 3px;
                margin-left: 5px;
                white-space: nowrap;
            }
            /* Signature pad styles */
            .signature-pad-container {
                border: 2px solid #dee2e6;
                border-radius: 8px;
                background: #fff;
                margin-bottom: 15px;
            }
            .signature-pad {
                width: 100%;
                height: 200px;
                border: 1px solid #ced4da;
                border-radius: 4px;
                touch-action: none;
            }
            .signature-actions {
                margin-top: 10px;
                display: flex;
                gap: 10px;
                justify-content: center;
            }
        </style>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Gasoline Purchase Orders</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Purchase Orders</li>
                        </ol>
                        
                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card bg-warning text-dark stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h5 class="card-title">Pending</h5>
                                        <h2 class="mb-0">
                                            <?php 
                                                $pending_count = 0;
                                                foreach ($status_counts as $stat) {
                                                    if ($stat['status'] === 'pending') {
                                                        $pending_count = $stat['count'];
                                                        break;
                                                    }
                                                }
                                                echo $pending_count;
                                            ?>
                                        </h2>
                                        <p class="card-text">Pending purchase orders</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card bg-info text-white stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <h5 class="card-title">Approved</h5>
                                        <h2 class="mb-0">
                                            <?php 
                                                $approved_count = 0;
                                                foreach ($status_counts as $stat) {
                                                    if ($stat['status'] === 'approved') {
                                                        $approved_count = $stat['count'];
                                                        break;
                                                    }
                                                }
                                                echo $approved_count;
                                            ?>
                                        </h2>
                                        <p class="card-text">Approved purchase orders</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Completed status card -->
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card bg-success text-white stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon">
                                            <i class="fas fa-check-double"></i>
                                        </div>
                                        <h5 class="card-title">Completed</h5>
                                        <h2 class="mb-0">
                                            <?php 
                                                $completed_count = 0;
                                                foreach ($status_counts as $stat) {
                                                    if ($stat['status'] === 'completed') {
                                                        $completed_count = $stat['count'];
                                                        break;
                                                    }
                                                }
                                                echo $completed_count;
                                            ?>
                                        </h2>
                                        <p class="card-text">Completed purchase orders</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Canceled status card -->
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card bg-danger text-white stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <h5 class="card-title">Canceled</h5>
                                        <h2 class="mb-0">
                                            <?php 
                                                $canceled_count = 0;
                                                foreach ($status_counts as $stat) {
                                                    if ($stat['status'] === 'cancelled') {
                                                        $canceled_count = $stat['count'];
                                                        break;
                                                    }
                                                }
                                                echo $canceled_count;
                                            ?>
                                        </h2>
                                        <p class="card-text">Canceled purchase orders</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Waiting for Approval Label (visible to all users) -->
                        <?php if ($pending_count > 0): ?>
                        <div class="waiting-approval-label">
                            <i class="fas fa-hourglass-half"></i>
                            <div>
                                <strong><?php echo $pending_count; ?> purchase order(s)</strong> waiting for CEO approval.
                                <?php if ($can_approve_po): ?>
                                <span class="badge bg-warning ms-2">You are the CEO - please review pending orders</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Pending Items for Issuance -->
                        <?php if (!empty($pending_items)): ?>
                        <div class="card mb-4 pending-items-card">
                            <div class="card-header">
                                <i class="fas fa-gas-pump me-1"></i>
                                Pending Gasoline Issuance
                                <span class="badge bg-warning float-end"><?php echo count($pending_items); ?> items</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="pendingItemsTable">
                                        <thead>
                                            <tr>
                                                <th>PO Number</th>
                                                <th>Gasoline Type</th>
                                                <th>Supplier</th>
                                                <th>Vehicle/Equipment</th>
                                                <th>Driver/Operator</th>
                                                <th>Purpose</th>
                                                <th>Quantity (L)</th>
                                                <th>Price/Liter</th>
                                                <th>Total</th>
                                                <th>Date Issued</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_items as $item): 
                                                $total_value = $item['quantity_liters'] * ($item['price_per_liter'] ?? 0);
                                                
                                                // Determine vehicle/equipment info
                                                $vehicle_equipment = '';
                                                if ($item['vehicle_id']) {
                                                    $vehicle_equipment = htmlspecialchars($item['vehicle_name'] ?? '') . ' (' . htmlspecialchars($item['plate_number'] ?? '') . ')';
                                                } elseif ($item['equipment_id']) {
                                                    $vehicle_equipment = htmlspecialchars($item['equipment_name'] ?? '');
                                                }
                                                
                                                // Get driver/operator name
                                                $driver_operator_name = '';
                                                if (!empty($item['manual_driver_name'])) {
                                                    $driver_operator_name = htmlspecialchars($item['manual_driver_name']) . ' (Manual Entry)';
                                                } elseif (!empty($item['employee_fullname'])) {
                                                    $driver_operator_name = htmlspecialchars($item['employee_fullname']);
                                                    if (!empty($item['employee_id'])) {
                                                        $driver_operator_name .= ' (' . htmlspecialchars($item['employee_id']) . ')';
                                                    }
                                                } else {
                                                    $driver_operator_name = 'Not specified';
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['po_number'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($item['gasoline_type'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($item['supplier_name'] ?? ''); ?></td>
                                                <td><?php echo $vehicle_equipment; ?></td>
                                                <td><?php echo $driver_operator_name; ?></td>
                                                <td><?php echo htmlspecialchars($item['purpose'] ?? ''); ?></td>
                                                <td class="text-end"><?php echo number_format($item['quantity_liters'] ?? 0, 2); ?></td>
                                                <td class="text-end">
                                                    <?php if (!empty($item['price_per_liter'])): ?>
                                                        ₱<?php echo number_format($item['price_per_liter'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if (!empty($item['price_per_liter'])): ?>
                                                        ₱<?php echo number_format($total_value, 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['date_issued'] ?? ''); ?></td>
                                                <td>
                                                    <div class="action-btn-group">
                                                        <button class="btn btn-sm btn-success issue-gasoline-btn" 
                                                                data-item-id="<?php echo $item['id']; ?>"
                                                                data-gasoline-type="<?php echo htmlspecialchars($item['gasoline_type'] ?? ''); ?>"
                                                                data-quantity="<?php echo $item['quantity_liters']; ?>"
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-title="Issue Gasoline">
                                                            <i class="fas fa-gas-pump"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Purchase Orders Table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-file-invoice me-1"></i>
                                    Gasoline Purchase Orders
                                    <?php if ($pending_count > 0): ?>
                                    <span class="badge bg-warning ms-2"><?php echo $pending_count; ?> waiting for approval</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                        <i class="fas fa-plus-circle me-1"></i> Create New PO
                                    </button>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <?php if (!empty($purchase_orders)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover po-table" id="poTable">
                                        <thead>
                                            <tr>
                                                <th>PO Number</th>
                                                <th>PO Date</th>
                                                <th>Supplier</th>
                                                <th>Gasoline Type(s)</th>
                                                <th>Quantity (L)</th>
                                                <th>Total Amount</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($purchase_orders as $po): 
                                                $status_class = 'status-' . ($po['status'] ?? 'pending');
                                                $status_text = ucfirst($po['status'] ?? 'pending');
                                                $total_amount = !empty($po['total_amount']) ? $po['total_amount'] : ($po['total_amount_calc'] ?? 0);
                                                
                                                // Format gasoline types for display
                                                $gasoline_types = isset($po['gasoline_types']) && $po['gasoline_types'] !== null ? $po['gasoline_types'] : 'N/A';
                                                $total_quantity = isset($po['total_quantity_liters']) ? number_format($po['total_quantity_liters'], 2) : '0.00';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($po['po_number'] ?? ''); ?></strong>
                                                    <?php if (($po['status'] ?? '') === 'pending'): ?>
                                                    <span class="pending-approval-tag" data-bs-toggle="tooltip" data-bs-title="Waiting for CEO approval">
                                                        <i class="fas fa-clock"></i> Pending Approval
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($po['invoice_number'])): ?>
                                                    <br><span class="invoice-badge"><i class="fas fa-receipt me-1"></i>Invoice: <?php echo htmlspecialchars($po['invoice_number']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($po['po_date'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($po['supplier_name'] ?? ''); ?></td>
                                                <td class="text-wrap">
                                                <?php if ($gasoline_types !== 'N/A'): ?>
                                                    <?php 
                                                    $types = explode(', ', $gasoline_types);
                                                    foreach ($types as $type):
                                                        $badge_color = '';
                                                        $type_lower = strtolower(trim($type));
                                                        
                                                        if (strpos($type_lower, 'diesel') !== false) {
                                                            $badge_color = 'bg-warning text-dark'; // Yellow background, dark text for Diesel
                                                        } elseif (strpos($type_lower, 'premium') !== false) {
                                                            $badge_color = 'bg-danger'; // Red background for Premium
                                                        } elseif (strpos($type_lower, 'unleaded') !== false) {
                                                            $badge_color = 'bg-success'; // Green background for Unleaded
                                                        } else {
                                                            $badge_color = 'bg-secondary'; // Default gray for other types
                                                        }
                                                    ?>
                                                    <span class="badge gasoline-type-badge <?php echo $badge_color; ?>">
                                                        <?php echo htmlspecialchars(trim($type)); ?>
                                                    </span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No items</span>
                                                <?php endif; ?>
                                                </td>
                                                <td class="text-end"><?php echo $total_quantity; ?> L</td>
                                                <td class="text-end">
                                                    <?php if ($total_amount > 0): ?>
                                                        ₱<?php echo number_format($total_amount, 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No price set</span>
                                                    <?php endif; ?>
                                                  </td>
                                                <td class="text-center">
                                                    <span class="badge status-badge <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                  </td>
                                                  <td>
                                                    <div class="action-btn-group">
                                                        <!-- COMPLETE PO BUTTON (for Approved POs only) - Only visible to Admin with Purchaser position -->
                                                        <?php if (($po['status'] ?? '') === 'approved' && $can_complete_po): ?>
                                                        <button class="btn btn-sm btn-success complete-po-btn btn-full-width" 
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-title="Mark as Completed"
                                                                data-po-id="<?php echo $po['id']; ?>"
                                                                data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>">
                                                            <i class="fas fa-check-double me-1"></i> Complete
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- UPDATE INVOICE BUTTON (for Completed POs only) - Only visible to Admin with Purchaser position -->
                                                        <?php if (($po['status'] ?? '') === 'completed' && $can_update_invoice): ?>
                                                        <button class="btn btn-sm btn-success update-invoice-btn btn-full-width" 
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-title="Update Invoice Number"
                                                                data-po-id="<?php echo $po['id']; ?>"
                                                                data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>"
                                                                data-invoice="<?php echo htmlspecialchars($po['invoice_number'] ?? ''); ?>">
                                                            <i class="fas fa-receipt me-1"></i> Update Invoice
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- APPROVE PO BUTTON -->
                                                        <?php if (($po['status'] ?? '') === 'pending' && $can_approve_po): ?>
                                                        <button class="btn btn-sm btn-info approve-po-btn btn-full-width" 
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-title="Approve PO"
                                                                data-po-id="<?php echo $po['id']; ?>"
                                                                data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>">
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- ROW FOR ICON-ONLY BUTTONS -->
                                                        <div class="action-icons-row">

                                                            <!-- GENERATE PDF BUTTON - Only show if status is 'approved' or 'completed' -->
                                                            <?php if (($po['status'] ?? '') === 'approved' || ($po['status'] ?? '') === 'completed'): ?>
                                                            <a href="generate_gas_po_pdf.php?id=<?php echo $po['id']; ?>" 
                                                               class="btn btn-sm btn-danger btn-icon-only" 
                                                               data-bs-toggle="tooltip" 
                                                               data-bs-title="Generate PDF"
                                                               target="_blank">
                                                                <i class="fas fa-file-pdf"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                            
                                                            <!-- VIEW DETAILS BUTTON -->
                                                            <button class="btn btn-sm btn-info view-po-btn btn-icon-only" 
                                                                    data-bs-toggle="tooltip" 
                                                                    data-bs-title="View Details"
                                                                    data-po-id="<?php echo $po['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>

                                                            <!-- EDIT PO BUTTON -->
                                                            <?php if (($po['status'] ?? '') === 'pending' || ($po['status'] ?? '') === 'approved'): ?>
                                                            <a href="?edit_po=<?php echo $po['id']; ?>" class="btn btn-sm btn-warning edit-po-btn btn-icon-only" 
                                                                    data-bs-toggle="tooltip" 
                                                                    data-bs-title="Edit PO">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                            
                                                            <!-- DELETE PO BUTTON -->
                                                            <?php if (in_array($po['status'] ?? '', ['pending', 'cancelled', 'delivered']) && $can_delete_po): ?>
                                                            <button class="btn btn-sm btn-danger delete-po-btn btn-icon-only" 
                                                                    data-bs-toggle="tooltip" 
                                                                    data-bs-title="Delete PO"
                                                                    data-po-id="<?php echo $po['id']; ?>"
                                                                    data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                   </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No purchase orders found. <a href="#" data-bs-toggle="modal" data-bs-target="#createPOModal">Create your first PO</a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Create PO Modal -->
        <div class="modal fade" id="createPOModal" tabindex="-1" aria-labelledby="createPOModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createPOModalLabel"><?php echo $edit_po_data ? 'Edit Purchase Order' : 'Create New Gasoline Purchase Order'; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="createPOForm">
                        <input type="hidden" name="action" value="<?php echo $edit_po_data ? 'update_po' : 'create_po'; ?>">
                        <?php if ($edit_po_data): ?>
                        <input type="hidden" name="po_id" value="<?php echo $edit_po_data['id']; ?>">
                        <?php endif; ?>
                        <div class="modal-body">
                            <?php if (!$can_approve_po): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Note:</strong> This purchase order will be created with status "Pending" and will require CEO approval.
                            </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="po_number" name="po_number" 
                                            value="<?php echo htmlspecialchars($edit_po_data['po_number'] ?? generatePONumber()); ?>" 
                                            required readonly style="background-color: #e9ecef;">
                                        <label for="po_number">PO Number <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="po_date" name="po_date" 
                                            value="<?php echo htmlspecialchars($edit_po_data['po_date'] ?? date('Y-m-d')); ?>" required>
                                        <label for="po_date">PO Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <?php if (!$can_approve_po): ?>
                                            <!-- For non-CEO users: use hidden input to ensure value is submitted -->
                                            <input type="hidden" name="status" value="pending">
                                            <select class="form-control" id="status" disabled style="background-color: #e9ecef;">
                                                <option value="pending" selected>Pending (Waiting for CEO Approval)</option>
                                            </select>
                                        <?php else: ?>
                                            <!-- For CEO users: normal select -->
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="pending" <?php echo ($edit_po_data && ($edit_po_data['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            </select>
                                        <?php endif; ?>
                                        <label for="status">Status <span class="text-danger">*</span></label>
                                    </div>
                                    <?php if (!$can_approve_po): ?>
                                    <small class="text-muted">This PO will need CEO approval</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Only Prepared By remains -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="prepared_by" name="prepared_by" required readonly style="background-color: #e9ecef; pointer-events: none;">
                                            <option value="">Select Preparer</option>
                                            <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                <?php if ($edit_po_data && ($user['id'] == ($edit_po_data['prepared_by'] ?? null))): ?>
                                                    selected
                                                <?php elseif (!$edit_po_data && $user['id'] == $_SESSION['user_id']): ?>
                                                    selected
                                                <?php endif; ?>>
                                                <?php echo htmlspecialchars($user['fullname']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="prepared_by">Prepared By <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            <h5>PO Items (Gasoline Distribution Details)</h5>
                            <p class="text-muted mb-3">Note: The main supplier for this PO will be determined from the first item's supplier selection.</p>
                            <div id="poItems">
                                <!-- Item template will be added here by JavaScript -->
                                <?php if ($edit_po_data && !empty($edit_po_data['items'])): ?>
                                    <?php foreach ($edit_po_data['items'] as $index => $item): ?>
                                    <div class="item-row">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-floating mb-3">
                                                    <select class="form-select item-gasoline-type" name="item_gasoline_type[]" required>
                                                        <option value="">Select Gasoline Type</option>
                                                        <?php foreach ($gasolineTypes as $type): ?>
                                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($item['gasoline_type'] ?? '') == $type ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($type); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Gasoline Type <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-floating mb-3">
                                                    <select class="form-select item-supplier" name="item_supplier_id[]" required>
                                                        <option value="">Select Supplier</option>
                                                        <?php foreach ($suppliers as $supplier): ?>
                                                        <option value="<?php echo $supplier['id']; ?>" <?php echo ($item['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Supplier <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-floating mb-3">
                                                    <select class="form-select item-vehicle" name="item_vehicle_id[]">
                                                        <option value="">Select Vehicle (Optional)</option>
                                                        <?php foreach ($vehicles as $vehicle): ?>
                                                        <option value="<?php echo $vehicle['id']; ?>" <?php echo ($item['vehicle_id'] ?? '') == $vehicle['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Vehicle</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-floating mb-3">
                                                    <select class="form-select item-equipment" name="item_equipment_id[]">
                                                        <option value="">Select Equipment (Optional)</option>
                                                        <?php foreach ($equipment as $eq): ?>
                                                        <option value="<?php echo $eq['id']; ?>" <?php echo ($item['equipment_id'] ?? '') == $eq['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Equipment</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-floating mb-3">
                                                    <select class="form-select item-driver-select" name="item_driver_operator_id[]" data-index="<?php echo $index; ?>">
                                                        <option value="">Select Driver/Operator</option>
                                                        <?php foreach ($employees as $employee): 
                                                            $employee_name = formatEmployeeNameWithPosition($employee);
                                                        ?>
                                                        <option value="<?php echo $employee['id']; ?>" <?php echo ($item['driver_operator_id'] == $employee['id'] && empty($item['manual_driver_name'])) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($employee_name); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                        <option value="other" <?php echo (!empty($item['manual_driver_name'])) ? 'selected' : ''; ?>>Other (Manual Entry)</option>
                                                    </select>
                                                    <label>Driver/Operator <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control item-purpose" name="item_purpose[]" 
                                                        value="<?php echo htmlspecialchars($item['purpose'] ?? ''); ?>" required>
                                                    <label>Purpose <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Manual Driver Name Field (full width - col-md-12) -->
                                        <div class="row" id="manual-driver-row-<?php echo $index; ?>" style="<?php echo (!empty($item['manual_driver_name']) ? 'display: flex;' : 'display: none;'); ?>">
                                            <div class="col-md-12">
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control item-manual-driver" name="item_manual_driver_name[]" 
                                                        value="<?php echo htmlspecialchars($item['manual_driver_name'] ?? ''); ?>" 
                                                        placeholder="Enter driver/operator name">
                                                    <label>Manual Driver/Operator Name</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-floating mb-3">
                                                    <input type="number" class="form-control item-quantity" name="item_quantity_liters[]" 
                                                        step="0.01" min="0.01" placeholder="Quantity" 
                                                        value="<?php echo htmlspecialchars($item['quantity_liters'] ?? ''); ?>" required>
                                                    <label>Quantity (Liters) <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-floating mb-3">
                                                    <input type="number" class="form-control item-price" name="item_price_per_liter[]" 
                                                        step="0.01" min="0" placeholder="Price"
                                                        value="<?php echo htmlspecialchars($item['price_per_liter'] ?? ''); ?>">
                                                    <label>Price per Liter (₱) <span class="text-muted">Optional</span></label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-floating mb-3">
                                                    <input type="number" class="form-control item-odometer" name="item_odometer_reading[]"
                                                        value="<?php echo htmlspecialchars($item['odometer_reading'] ?? ''); ?>">
                                                    <label>Odometer Reading (Optional)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-floating mb-3">
                                                    <input type="date" class="form-control item-date" name="item_date_issued[]" 
                                                        value="<?php echo htmlspecialchars($item['date_issued'] ?? ''); ?>" required>
                                                    <label>Date Issued <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 offset-md-8">
                                                <div class="form-floating mb-3">
                                                    <?php 
                                                    $item_total = ($item['quantity_liters'] ?? 0) * (($item['price_per_liter'] ?? 0));
                                                    ?>
                                                    <input type="text" class="form-control item-total" readonly placeholder="Total"
                                                        value="<?php echo number_format($item_total, 2); ?>">
                                                    <label>Total Amount (₱)</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <div class="item-row">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <select class="form-select item-gasoline-type" name="item_gasoline_type[]" required>
                                                    <option value="">Select Gasoline Type</option>
                                                    <?php foreach ($gasolineTypes as $type): ?>
                                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Gasoline Type <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <select class="form-select item-supplier" name="item_supplier_id[]" required>
                                                    <option value="">Select Supplier</option>
                                                    <?php foreach ($suppliers as $supplier): ?>
                                                    <option value="<?php echo $supplier['id']; ?>">
                                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Supplier <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <select class="form-select item-vehicle" name="item_vehicle_id[]">
                                                    <option value="">Select Vehicle (Optional)</option>
                                                    <?php foreach ($vehicles as $vehicle): ?>
                                                    <option value="<?php echo $vehicle['id']; ?>">
                                                        <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Vehicle</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <select class="form-select item-equipment" name="item_equipment_id[]">
                                                    <option value="">Select Equipment (Optional)</option>
                                                    <?php foreach ($equipment as $eq): ?>
                                                    <option value="<?php echo $eq['id']; ?>">
                                                        <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Equipment</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select item-driver-select" name="item_driver_operator_id[]" data-index="0">
                                                    <option value="">Select Driver/Operator</option>
                                                    <?php foreach ($employees as $employee): 
                                                        $employee_name = formatEmployeeNameWithPosition($employee);
                                                    ?>
                                                    <option value="<?php echo $employee['id']; ?>">
                                                        <?php echo htmlspecialchars($employee_name); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                    <option value="other">Other (Manual Entry)</option>
                                                </select>
                                                <label>Driver/Operator <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control item-purpose" name="item_purpose[]" required>
                                                <label>Purpose <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Manual Driver Name Field (full width - col-md-12) -->
                                    <div class="row" id="manual-driver-row-0" style="display: none;">
                                        <div class="col-md-12">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control item-manual-driver" name="item_manual_driver_name[]" 
                                                       placeholder="Enter driver/operator name">
                                                <label>Manual Driver/Operator Name</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control item-quantity" name="item_quantity_liters[]" 
                                                    step="0.01" min="0.01" placeholder="Quantity" required>
                                                <label>Quantity (Liters) <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control item-price" name="item_price_per_liter[]" 
                                                    step="0.01" min="0" placeholder="Price">
                                                <label>Price per Liter (₱) <span class="text-muted">Optional</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control item-odometer" name="item_odometer_reading[]">
                                                <label>Odometer Reading (Optional)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="date" class="form-control item-date" name="item_date_issued[]" 
                                                    value="<?php echo date('Y-m-d'); ?>" required>
                                                <label>Date Issued <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 offset-md-8">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control item-total" readonly placeholder="Total">
                                                <label>Total Amount (₱)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-success" id="addItemBtn">
                                    <i class="fas fa-plus me-1"></i> Add Item
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" id="removeItemBtn">
                                    <i class="fas fa-minus me-1"></i> Remove Last Item
                                </button>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 offset-md-8">
                                    <div class="form-floating mb-3">
                                        <?php
                                        $grand_total = 0;
                                        if ($edit_po_data && !empty($edit_po_data['items'])) {
                                            foreach ($edit_po_data['items'] as $item) {
                                                $grand_total += ($item['quantity_liters'] ?? 0) * (($item['price_per_liter'] ?? 0));
                                            }
                                        }
                                        ?>
                                        <input type="number" class="form-control" id="total_amount" name="total_amount" 
                                            value="<?php echo number_format($grand_total, 2); ?>" readonly>
                                        <label for="total_amount">Grand Total Amount (₱)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary"><?php echo $edit_po_data ? 'Update Purchase Order' : 'Create Purchase Order'; ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Complete PO Modal (with Invoice Number and Signature) -->
        <div class="modal fade" id="completePOModal" tabindex="-1" aria-labelledby="completePOModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="completePOModalLabel">Complete Purchase Order - Signature Required</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="completePOForm">
                        <input type="hidden" name="action" value="complete_po">
                        <input type="hidden" id="complete_po_id" name="po_id">
                        <input type="hidden" id="completion_signature_data" name="completion_signature_data">
                        <div class="modal-body">
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                You are about to mark as completed: <strong id="complete_po_number_display"></strong>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="complete_invoice_number" name="invoice_number" 
                                       placeholder="Enter Invoice Number (Optional)">
                                <label for="complete_invoice_number">Invoice Number <span class="text-muted">(Optional)</span></label>
                            </div>
                            
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Please sign below to authorize the completion of this purchase order.
                            </div>
                            
                            <!-- Signature Pad for Completion -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Purchaser's Signature</label>
                                <div class="signature-pad-container">
                                    <canvas id="completionSignatureCanvas" class="signature-pad" width="500" height="200"></canvas>
                                </div>
                                <div class="signature-actions">
                                    <button type="button" class="btn btn-sm btn-secondary" id="clearCompletionSignatureBtn">
                                        <i class="fas fa-eraser me-1"></i> Clear Signature
                                    </button>
                                </div>
                                <small class="text-muted">Draw your signature in the box above</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Your signature will be saved and displayed on the PO PDF document.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success" id="submitCompleteBtn">Complete Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Update Invoice Modal (for Completed POs) -->
        <div class="modal fade" id="updateInvoiceModal" tabindex="-1" aria-labelledby="updateInvoiceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateInvoiceModalLabel">Update Invoice Number</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="updateInvoiceForm">
                        <input type="hidden" name="action" value="update_invoice">
                        <input type="hidden" id="update_invoice_po_id" name="po_id">
                        <div class="modal-body">
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Updating invoice for: <strong id="update_invoice_po_number_display"></strong>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="update_invoice_number" name="invoice_number" 
                                       placeholder="Enter Invoice Number">
                                <label for="update_invoice_number">Invoice Number <span class="text-muted">(Optional)</span></label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-1"></i>
                                You can update the invoice number for this completed purchase order at any time.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success">Update Invoice</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Cancel PO Modal -->
        <div class="modal fade" id="cancelPOModal" tabindex="-1" aria-labelledby="cancelPOModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelPOModalLabel">Cancel Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="cancel_po">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="cancel_po_id" name="po_id" required>
                                    <option value="">Select PO</option>
                                    <?php foreach ($purchase_orders as $po): ?>
                                    <?php if (($po['status'] ?? '') !== 'delivered' && ($po['status'] ?? '') !== 'cancelled' && ($po['status'] ?? '') !== 'completed'): ?>
                                    <option value="<?php echo $po['id']; ?>">
                                        <?php echo htmlspecialchars(($po['po_number'] ?? '') . ' - ' . ($po['supplier_name'] ?? '')); ?>
                                    </option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <label for="cancel_po_id">Purchase Order <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="cancel_reason" name="cancel_reason" style="height: 100px" required></textarea>
                                <label for="cancel_reason">Reason for Cancellation <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger">Cancel Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Approve PO Modal with Signature -->
        <div class="modal fade" id="approvePOModal" tabindex="-1" aria-labelledby="approvePOModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="approvePOModalLabel">Approve Purchase Order - Signature Required</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="approvePOForm">
                        <input type="hidden" name="action" value="approve_po">
                        <input type="hidden" id="approve_po_id" name="po_id">
                        <input type="hidden" id="signature_data" name="signature_data">
                        <input type="hidden" id="signature_type" name="signature_type" value="draw">
                        <div class="modal-body">
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                You are about to approve: <strong id="approve_po_number_display"></strong>
                            </div>
                            
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Please sign below to authorize this purchase order.
                            </div>
                            
                            <!-- Signature Pad -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">CEO Signature</label>
                                <div class="signature-pad-container">
                                    <canvas id="signatureCanvas" class="signature-pad" width="500" height="200"></canvas>
                                </div>
                                <div class="signature-actions">
                                    <button type="button" class="btn btn-sm btn-secondary" id="clearSignatureBtn">
                                        <i class="fas fa-eraser me-1"></i> Clear Signature
                                    </button>
                                </div>
                                <small class="text-muted">Draw your signature in the box above</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Your signature will be saved and displayed on the PO PDF document.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-info" id="submitApproveBtn">Approve Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- View PO Details Modal -->
        <div class="modal fade" id="viewPOModal" tabindex="-1" aria-labelledby="viewPOModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewPOModalLabel">Purchase Order Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="poDetailsContent">
                        <!-- Content will be loaded via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="printPODetailsBtn">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Issue Gasoline Modal -->
        <div class="modal fade" id="issueGasolineModal" tabindex="-1" aria-labelledby="issueGasolineModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="issueGasolineModalLabel">Issue Gasoline</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="issueGasolineForm">
                        <input type="hidden" name="action" value="issue_gasoline">
                        <input type="hidden" id="issue_po_item_id" name="po_item_id">
                        <div class="modal-body">
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Item Details:</strong>
                                <div id="itemDetails"></div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" id="issue_tank_id" name="tank_id" required>
                                    <option value="">Select Source Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="issue_tank_id">From Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="issue_date" name="issue_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="issue_date">Issue Date <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                This will issue gasoline from the selected tank and record the movement.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success">Issue Gasoline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Delete PO Modal -->
        <div class="modal fade" id="deletePOModal" tabindex="-1" aria-labelledby="deletePOModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deletePOModalLabel">Delete Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="deletePOForm">
                        <input type="hidden" name="action" value="delete_po">
                        <input type="hidden" id="delete_po_id" name="po_id">
                        <input type="hidden" id="delete_po_number" name="po_number">
                        <div class="modal-body">
                            <div class="alert alert-danger mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>Warning:</strong> You are about to delete: <strong id="delete_po_number_display"></strong>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="delete_reason" name="delete_reason" style="height: 100px" required></textarea>
                                <label for="delete_reason">Reason for Deletion <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Note:</strong> This action cannot be undone. Only pending, cancelled, or delivered POs can be deleted.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const poTable = document.getElementById('poTable');
                if (poTable) {
                    new simpleDatatables.DataTable(poTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "{select} entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                const pendingItemsTable = document.getElementById('pendingItemsTable');
                if (pendingItemsTable) {
                    new simpleDatatables.DataTable(pendingItemsTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "{select} entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // Initialize Bootstrap tooltips
                const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
                
                // Show SweetAlert2 notifications
                <?php if (!empty($swal_data)): ?>
                    Swal.fire({
                        title: <?php echo json_encode($swal_data['title'] ?? ''); ?>,
                        text: <?php echo json_encode($swal_data['text'] ?? ''); ?>,
                        icon: <?php echo json_encode($swal_data['icon'] ?? ''); ?>,
                        confirmButtonText: 'OK'
                    });
                <?php endif; ?>
                
                // Auto-open edit modal if editing
                <?php if ($edit_po_data): ?>
                    $(document).ready(function() {
                        $('#createPOModal').modal('show');
                    });
                <?php endif; ?>
                
                // Calculate item total and update total amount
                function calculateItemTotal() {
                    let grandTotal = 0;
                    
                    // Calculate each item total
                    $('.item-row').each(function() {
                        const quantity = parseFloat($(this).find('.item-quantity').val()) || 0;
                        const price = parseFloat($(this).find('.item-price').val()) || 0;
                        const itemTotal = quantity * price;
                        
                        $(this).find('.item-total').val(itemTotal.toFixed(2));
                        grandTotal += itemTotal;
                    });
                    
                    // Update grand total
                    $('#total_amount').val(grandTotal.toFixed(2));
                }
                
                // Function to handle driver/operator selection change
                function handleDriverSelectChange(selectElement) {
                    const itemRow = $(selectElement).closest('.item-row');
                    const index = $(selectElement).data('index');
                    const manualDriverRow = itemRow.find('#manual-driver-row-' + index);
                    const selectedValue = $(selectElement).val();
                    
                    if (selectedValue === 'other') {
                        // Show manual driver input
                        manualDriverRow.show();
                    } else {
                        // Hide manual driver input and clear the value
                        manualDriverRow.hide();
                        manualDriverRow.find('.item-manual-driver').val('');
                    }
                }
                
                // Fix: Ensure selectedValue is defined
                $(document).on('change', '.item-driver-select', function() {
                    handleDriverSelectChange(this);
                });
                
                // Add item row - FIXED VERSION with full width manual driver field
                $('#addItemBtn').click(function() {
                    const itemCount = $('.item-row').length;
                    const newIndex = itemCount;
                    // Create a new item row using a template with proper PHP values
                    const itemTemplate = `
                    <div class="item-row">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <select class="form-select item-gasoline-type" name="item_gasoline_type[]" required>
                                        <option value="">Select Gasoline Type</option>
                                        <?php foreach ($gasolineTypes as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Gasoline Type <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <select class="form-select item-supplier" name="item_supplier_id[]" required>
                                        <option value="">Select Supplier</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>">
                                            <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Supplier <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <select class="form-select item-vehicle" name="item_vehicle_id[]">
                                        <option value="">Select Vehicle (Optional)</option>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo $vehicle['id']; ?>">
                                            <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Vehicle</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <select class="form-select item-equipment" name="item_equipment_id[]">
                                        <option value="">Select Equipment (Optional)</option>
                                        <?php foreach ($equipment as $eq): ?>
                                        <option value="<?php echo $eq['id']; ?>">
                                            <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Equipment</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select item-driver-select" name="item_driver_operator_id[]" data-index="${newIndex}">
                                        <option value="">Select Driver/Operator</option>
                                        <?php foreach ($employees as $employee): 
                                            $employee_name = formatEmployeeNameWithPosition($employee);
                                        ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee_name); ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <option value="other">Other (Manual Entry)</option>
                                    </select>
                                    <label>Driver/Operator <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control item-purpose" name="item_purpose[]" required>
                                    <label>Purpose <span class="text-danger">*</span></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row" id="manual-driver-row-${newIndex}" style="display: none;">
                            <div class="col-md-12">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control item-manual-driver" name="item_manual_driver_name[]" 
                                           placeholder="Enter driver/operator name">
                                    <label>Manual Driver/Operator Name</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control item-quantity" name="item_quantity_liters[]" 
                                           step="0.01" min="0.01" placeholder="Quantity" required>
                                    <label>Quantity (Liters) <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control item-price" name="item_price_per_liter[]" 
                                           step="0.01" min="0" placeholder="Price">
                                    <label>Price per Liter (₱) <span class="text-muted">Optional</span></label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control item-odometer" name="item_odometer_reading[]">
                                    <label>Odometer Reading (Optional)</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control item-date" name="item_date_issued[]" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                    <label>Date Issued <span class="text-danger">*</span></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 offset-md-8">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control item-total" readonly placeholder="Total">
                                    <label>Total Amount (₱)</label>
                                </div>
                            </div>
                        </div>
                    </div>`;
                    
                    $('#poItems').append(itemTemplate);
                    
                    // Disable equipment when vehicle is selected and vice versa
                    const newItem = $('.item-row').last();
                    newItem.find('.item-vehicle').off('change').on('change', function() {
                        if ($(this).val()) {
                            newItem.find('.item-equipment').prop('disabled', true).val('');
                        } else {
                            newItem.find('.item-equipment').prop('disabled', false);
                        }
                    });
                    
                    newItem.find('.item-equipment').off('change').on('change', function() {
                        if ($(this).val()) {
                            newItem.find('.item-vehicle').prop('disabled', true).val('');
                        } else {
                            newItem.find('.item-vehicle').prop('disabled', false);
                        }
                    });
                    
                    calculateItemTotal();
                });
                
                // Remove last item row
                $('#removeItemBtn').click(function() {
                    if ($('.item-row').length > 1) {
                        $('.item-row').last().remove();
                        // Renumber remaining items if needed
                        $('.item-row').each(function(index) {
                            $(this).find('.item-driver-select').attr('data-index', index);
                            const manualRow = $(this).find('[id^="manual-driver-row-"]');
                            if (manualRow.length) {
                                manualRow.attr('id', 'manual-driver-row-' + index);
                            }
                        });
                        calculateItemTotal();
                    }
                });
                
                // Calculate totals when quantity or price changes
                $(document).on('input', '.item-quantity, .item-price', function() {
                    calculateItemTotal();
                });
                
                // Disable equipment when vehicle is selected and vice versa in existing items
                $('.item-vehicle').each(function() {
                    $(this).off('change').on('change', function() {
                        if ($(this).val()) {
                            $(this).closest('.item-row').find('.item-equipment').prop('disabled', true).val('');
                        } else {
                            $(this).closest('.item-row').find('.item-equipment').prop('disabled', false);
                        }
                    });
                });
                
                $('.item-equipment').each(function() {
                    $(this).off('change').on('change', function() {
                        if ($(this).val()) {
                            $(this).closest('.item-row').find('.item-vehicle').prop('disabled', true).val('');
                        } else {
                            $(this).closest('.item-row').find('.item-vehicle').prop('disabled', false);
                        }
                    });
                });
                
                // Trigger driver select change for existing rows that have manual names
                $('.item-row').each(function() {
                    const driverSelect = $(this).find('.item-driver-select');
                    const manualDriverRow = $(this).find('[id^="manual-driver-row-"]');
                    if (driverSelect.val() === 'other') {
                        manualDriverRow.show();
                    } else {
                        manualDriverRow.hide();
                    }
                });
                
                // View PO details
                $(document).on('click', '.view-po-btn', function() {
                    const poId = $(this).data('po-id');
                    
                    // Show loading indicator
                    $('#poDetailsContent').html(`
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading PO details...</p>
                        </div>
                    `);
                    
                    // Show modal first
                    $('#viewPOModal').modal('show');
                    
                    // Load content via AJAX
                    $.ajax({
                        url: 'action/get_gasoline_po_details.php',
                        method: 'POST',
                        data: { po_id: poId },
                        dataType: 'html',
                        success: function(response) {
                            $('#poDetailsContent').html(response);
                        },
                        error: function(xhr, status, error) {
                            $('#poDetailsContent').html(`
                                <div class="alert alert-danger">
                                    <h5>Error Loading PO Details</h5>
                                    <p>Failed to load purchase order details. Please try again.</p>
                                    <p>Error: ${error}</p>
                                </div>
                            `);
                        }
                    });
                });
                
                // Complete PO button - setup signature pad when modal opens
                let completionSignaturePad = null;
                let completionCanvas = null;
                
                $(document).on('click', '.complete-po-btn', function() {
                    const poId = $(this).data('po-id');
                    const poNumber = $(this).data('po-number');
                    
                    $('#complete_po_id').val(poId);
                    $('#complete_po_number_display').text(poNumber);
                    
                    // Setup signature pad when modal is shown
                    $('#completePOModal').one('shown.bs.modal', function() {
                        completionCanvas = document.getElementById('completionSignatureCanvas');
                        if (completionCanvas) {
                            // Set canvas dimensions
                            completionCanvas.width = completionCanvas.offsetWidth;
                            completionCanvas.height = 200;
                            
                            // Initialize signature pad
                            completionSignaturePad = new SignaturePad(completionCanvas, {
                                backgroundColor: 'rgba(0, 0, 0, 0)',
                                penColor: 'rgb(0, 0, 0)',
                                minWidth: 1,
                                maxWidth: 2
                            });
                            
                            // Clear any existing signature
                            completionSignaturePad.clear();
                        }
                    });
                    
                    $('#completePOModal').modal('show');
                });
                
                // Clear completion signature button
                $('#clearCompletionSignatureBtn').click(function() {
                    if (completionSignaturePad) {
                        completionSignaturePad.clear();
                    }
                });
                
                // Handle complete form submission
                $('#submitCompleteBtn').click(function(e) {
                    e.preventDefault();
                    
                    if (!completionSignaturePad) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Signature pad not initialized. Please try again.'
                        });
                        return;
                    }
                    
                    if (completionSignaturePad.isEmpty()) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Signature Required',
                            text: 'Please provide your signature before completing this purchase order.'
                        });
                        return;
                    }
                    
                    // Get signature as base64
                    const signatureData = completionSignaturePad.toDataURL();
                    $('#completion_signature_data').val(signatureData);
                    
                    Swal.fire({
                        title: 'Confirm Completion',
                        text: 'Are you sure you want to mark this purchase order as completed?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, complete it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#completePOForm').off('submit').submit();
                        }
                    });
                });
                
                // Update Invoice button
                $(document).on('click', '.update-invoice-btn', function() {
                    const poId = $(this).data('po-id');
                    const poNumber = $(this).data('po-number');
                    const currentInvoice = $(this).data('invoice') || '';
                    
                    $('#update_invoice_po_id').val(poId);
                    $('#update_invoice_po_number_display').text(poNumber);
                    $('#update_invoice_number').val(currentInvoice);
                    $('#updateInvoiceModal').modal('show');
                });
                
                // Signature Pad Setup for Approval
                let signaturePad = null;
                let canvas = null;
                
                // Approve PO button - setup signature pad when modal opens
                $(document).on('click', '.approve-po-btn', function() {
                    const poId = $(this).data('po-id');
                    const poNumber = $(this).data('po-number');
                    
                    $('#approve_po_id').val(poId);
                    $('#approve_po_number_display').text(poNumber);
                    
                    // Setup signature pad when modal is shown
                    $('#approvePOModal').one('shown.bs.modal', function() {
                        canvas = document.getElementById('signatureCanvas');
                        if (canvas) {
                            // Set canvas dimensions
                            canvas.width = canvas.offsetWidth;
                            canvas.height = 200;
                            
                            // Initialize signature pad
                            signaturePad = new SignaturePad(canvas, {
                                backgroundColor: 'rgba(0, 0, 0, 0)',
                                penColor: 'rgb(0, 0, 0)',
                                minWidth: 1,
                                maxWidth: 2
                            });
                            
                            // Clear any existing signature
                            signaturePad.clear();
                        }
                    });
                    
                    $('#approvePOModal').modal('show');
                });
                
                // Clear signature button
                $('#clearSignatureBtn').click(function() {
                    if (signaturePad) {
                        signaturePad.clear();
                    }
                });
                
                // Handle approve form submission
                $('#submitApproveBtn').click(function(e) {
                    e.preventDefault();
                    
                    if (!signaturePad) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Signature pad not initialized. Please try again.'
                        });
                        return;
                    }
                    
                    if (signaturePad.isEmpty()) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Signature Required',
                            text: 'Please provide your signature before approving this purchase order.'
                        });
                        return;
                    }
                    
                    // Get signature as base64
                    const signatureData = signaturePad.toDataURL();
                    $('#signature_data').val(signatureData);
                    
                    Swal.fire({
                        title: 'Confirm Approval',
                        text: 'Are you sure you want to approve this purchase order with your signature?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#17a2b8',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, approve it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#approvePOForm').off('submit').submit();
                        }
                    });
                });
                
                // Delete PO button
                $(document).on('click', '.delete-po-btn', function() {
                    const poId = $(this).data('po-id');
                    const poNumber = $(this).data('po-number');
                    
                    $('#delete_po_id').val(poId);
                    $('#delete_po_number').val(poNumber);
                    $('#delete_po_number_display').text(poNumber);
                    $('#deletePOModal').modal('show');
                });
                
                // Set up print button for PO details
                $('#printPODetailsBtn').click(function() {
                    const poId = $('#poDetailsContent').data('po-id');
                    if (poId) {
                        window.open('reports/po_print.php?id=' + poId, '_blank');
                    }
                });
                
                // Deliver PO button
                $(document).on('click', '.deliver-po-btn', function() {
                    const poId = $(this).data('po-id');
                    $('#delivery_po_id').val(poId);
                    $('#deliveryModal').modal('show');
                });
                
                // Issue gasoline button
                $(document).on('click', '.issue-gasoline-btn', function() {
                    const itemId = $(this).data('item-id');
                    const gasolineType = $(this).data('gasoline-type');
                    const quantity = $(this).data('quantity');
                    
                    $('#issue_po_item_id').val(itemId);
                    $('#itemDetails').html(`
                        <div>Gasoline Type: ${gasolineType}</div>
                        <div>Quantity: ${quantity} Liters</div>
                    `);
                    
                    $('#issueGasolineModal').modal('show');
                });
                
                // Validate create/update PO form
                $('#createPOForm').submit(function(e) {
                    // Check if user is trying to change status to 'approved' without permission
                    const selectedStatus = $('#status').val();
                    const canApprove = <?php echo $can_approve_po ? 'true' : 'false'; ?>;
                    
                    if (!canApprove && selectedStatus === 'approved') {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Access Denied',
                            text: 'Only Admin with CEO position can approve purchase orders.'
                        });
                        return false;
                    }
                    
                    // Check if at least one item is added
                    const itemCount = $('.item-row').filter(function() {
                        return $(this).find('.item-gasoline-type').val() !== '';
                    }).length;
                    
                    if (itemCount === 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please add at least one item to the purchase order.'
                        });
                        return false;
                    }
                    
                    // Validate all items have required fields
                    let valid = true;
                    let errorMessage = '';
                    let hasSupplier = false;
                    
                    $('.item-row').each(function(index) {
                        const type = $(this).find('.item-gasoline-type').val();
                        const supplier = $(this).find('.item-supplier').val();
                        const driverSelect = $(this).find('.item-driver-select').val();
                        const manualDriverName = $(this).find('.item-manual-driver').val();
                        const purpose = $(this).find('.item-purpose').val();
                        const quantity = $(this).find('.item-quantity').val();
                        const dateIssued = $(this).find('.item-date').val();
                        
                        if (type || supplier || driverSelect || purpose || quantity || dateIssued) {
                            if (!type) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Gasoline Type is required';
                                return false;
                            }
                            if (!supplier) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Supplier is required';
                                return false;
                            } else {
                                hasSupplier = true;
                            }
                            // Check driver/operator validation
                            if (!driverSelect) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Driver/Operator selection is required';
                                return false;
                            }
                            if (driverSelect === 'other' && (!manualDriverName || manualDriverName.trim() === '')) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Manual driver/operator name is required when "Other" is selected';
                                return false;
                            }
                            if (!purpose) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Purpose is required';
                                return false;
                            }
                            if (!quantity || parseFloat(quantity) <= 0) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Valid Quantity is required';
                                return false;
                            }
                            if (!dateIssued) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Date Issued is required';
                                return false;
                            }
                        }
                    });
                    
                    // Check if at least one item has a supplier selected
                    if (!hasSupplier) {
                        valid = false;
                        errorMessage = 'At least one item must have a supplier selected.';
                    }
                    
                    if (!valid) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: errorMessage
                        });
                        return false;
                    }
                });
                
                // Validate update invoice form
                $('#updateInvoiceForm').submit(function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: 'Confirm Update',
                        text: 'Are you sure you want to update the invoice number?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, update it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#updateInvoiceForm').off('submit').submit();
                        }
                    });
                });
                
                // Validate delete PO form
                $('#deletePOForm').submit(function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: 'Confirm Deletion',
                        text: 'Are you sure you want to delete this purchase order? This action cannot be undone!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#deletePOForm').off('submit').submit();
                        }
                    });
                });
                
                // Validate issue gasoline form
                $('#issueGasolineForm').submit(function(e) {
                    const tankId = $('#issue_tank_id').val();
                    
                    if (!tankId) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please select a source tank.'
                        });
                        return false;
                    }
                });
                
                // Calculate initial total
                calculateItemTotal();
                
                // Handle modal close for Create/Edit PO modal
                $('#createPOModal').on('hidden.bs.modal', function () {
                    <?php if ($edit_po_data): ?>
                        window.location.href = 'gasoline_purchase_order.php';
                    <?php endif; ?>
                });
                
                // Handle modal close for other modals
                $('#cancelPOModal, #approvePOModal, #viewPOModal, #issueGasolineModal, #deletePOModal, #completePOModal, #updateInvoiceModal').on('hidden.bs.modal', function () {
                    $(this).find('form')[0].reset();
                    if (signaturePad) {
                        signaturePad.clear();
                    }
                    if (completionSignaturePad) {
                        completionSignaturePad.clear();
                    }
                });
            });
            
            // Logout function with SweetAlert2
            document.getElementById('logoutLink').addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You want to logout from the system.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, logout!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'action/logout.php';
                    }
                });
            });
            
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
        </script>
    </body>
</html>