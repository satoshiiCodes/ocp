<?php
/**
 * actions/spare_parts_inventory-actions.php
 *
 * Every action for spare_parts_inventory lives in this one file: the initial stock for a
 * part, and the delete and edit behind the Recent Parts Movements table's Actions column.
 *
 * The page pulls this file in at the top, so it runs in the page's scope: the
 * database handle, the session and $_POST behave exactly as they did when this
 * code sat inline. A rejected submission leaves the message variables set and the
 * markup below shows them; a success redirects.
 *
 * The initial-stock block below is lifted verbatim from spare_parts_inventory: the
 * queries, the messages and the validation are unchanged. The delete and edit blocks are
 * modelled on inventory.php's stock-movement ones, which is the page this page's table
 * follows; only the table, its columns and the part names differ.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_SPARE_PARTS_INVENTORY_ACTIONS_RAN')) {
    return;
}
define('OCP_SPARE_PARTS_INVENTORY_ACTIONS_RAN', true);

// The page's own login guard and its database handle are what authorise everything
// below: this file is pulled in after both, in the page's scope. A direct hit on this
// file has neither, so nothing is done there.
if (!isset($_SESSION['user_id']) || !isset($pdo)) {
    return;
}

// The message variables the page's markup reads. Both are filled in below.
$message = '';
$message_type = '';
$swal_data = []; // For SweetAlert2 data

// -------------------------------------------------------------------- helpers
// Function to generate batch number
function generateBatchNumber($pdo) {
    $prefix = 'BATCH';
    $year = date('Y');
    $month = date('m');
    
    // Get the latest batch number for this year and month
    $stmt = $pdo->prepare("SELECT batch_number FROM spare_parts_batches 
                          WHERE batch_number LIKE :pattern 
                          ORDER BY id DESC LIMIT 1");
    $pattern = $prefix . '-' . $year . $month . '%';
    $stmt->bindParam(':pattern', $pattern);
    $stmt->execute();
    $lastBatch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastBatch) {
        $lastNumber = intval(substr($lastBatch['batch_number'], -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . '-' . $year . $month . '-' . $newNumber;
}

// -------------------------------------------------------------------- spare_parts_inventory

/**
 * Moves a part's stock by a signed amount, in the two places stock is recorded.
 *
 *   $delta > 0  adds stock      $delta < 0  removes stock
 *
 * `spare_parts_inventory.quantity` is what "Current Parts Inventory Summary" shows and what
 * its low-stock badge is computed from, so it has to follow every movement. The batches are
 * the FIFO record, and their total matches the summary for every part today, so they are
 * adjusted too or editing a movement would leave the two disagreeing.
 *
 * A batch is only touched when the movement names one. `initial_part` writes its movement
 * and its batch in that order but never links them, so an "in" movement with no batch_id has
 * no batch it can be shown to belong to; guessing one would change an unrelated batch, so
 * the inventory figure is adjusted and the batch is left alone.
 *
 * The result is floored at zero: a move cannot take out more than the part holds, and a
 * negative figure on the summary would only be a different kind of wrong. The caller is
 * expected to be inside a transaction.
 */
function ocpApplyPartStockDelta(PDO $pdo, array $movement, $delta)
{
    $delta = (float) $delta;
    if ($delta == 0.0) {
        return;
    }

    // --- the figure the summary shows -------------------------------------
    $stmt = $pdo->prepare("UPDATE spare_parts_inventory
                           SET quantity = GREATEST(quantity + :delta, 0)
                           WHERE part_id = :part_id");
    $stmt->bindValue(':delta', $delta);
    $stmt->bindValue(':part_id', (int) $movement['part_id'], PDO::PARAM_INT);
    $stmt->execute();

    // --- the FIFO batch the movement names --------------------------------
    if (!empty($movement['batch_id'])) {
        $batchStmt = $pdo->prepare("UPDATE spare_parts_batches
                                    SET quantity = GREATEST(quantity + :delta, 0)
                                    WHERE id = :batch_id");
        $batchStmt->bindValue(':delta', $delta);
        $batchStmt->bindValue(':batch_id', (int) $movement['batch_id'], PDO::PARAM_INT);
        $batchStmt->execute();
    }
}

/** What one movement did to stock, as a signed number. */
function ocpMovementStockEffect(array $movement, $quantity)
{
    return ($movement['movement_type'] === 'in' ? 1 : -1) * (float) $quantity;
}

// ---- action: delete a parts movement --------------------------------------
// Reported the way inventory.php reports a stock-movement delete: the message goes into
// the session and the page is reloaded, so the message survives the refresh that shows
// the row gone. The page's preamble reads the session back for the island, and the
// script shows it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_part_movement'])) {
    $ocp_movement_id = (int) ($_POST['movement_id'] ?? 0);

    try {
        $ocp_check_stmt = $pdo->prepare("SELECT id, part_id, quantity, movement_type, batch_id FROM spare_parts_movements WHERE id = :id");
        $ocp_check_stmt->bindValue(':id', $ocp_movement_id, PDO::PARAM_INT);
        $ocp_check_stmt->execute();
        $ocp_movement = $ocp_check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($ocp_movement) {
            // Removing a movement has to undo what it did to stock, or the summary keeps
            // counting litres that the movement no longer accounts for. Both the figure the
            // summary shows and the batch are put back, together, so a failure cannot leave
            // them disagreeing.
            try {
                $pdo->beginTransaction();

                ocpApplyPartStockDelta($pdo, $ocp_movement, -ocpMovementStockEffect($ocp_movement, $ocp_movement['quantity']));

                $ocp_delete_stmt = $pdo->prepare("DELETE FROM spare_parts_movements WHERE id = :id");
                $ocp_delete_stmt->bindValue(':id', $ocp_movement_id, PDO::PARAM_INT);
                $ocp_delete_stmt->execute();

                $pdo->commit();
            } catch (Throwable $ocp_stock_error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $ocp_stock_error;
            }

            $_SESSION['swal_data'] = [
                'title' => 'Success!',
                'text' => 'Parts movement deleted successfully! The quantity was taken out of the parts inventory.',
                'icon' => 'success'
            ];
        } else {
            $_SESSION['swal_data'] = [
                'title' => 'Error!',
                'text' => 'Parts movement not found.',
                'icon' => 'error'
            ];
        }
    } catch (PDOException $e) {
        $_SESSION['swal_data'] = [
            'title' => 'Database Error!',
            'text' => 'Database error: ' . $e->getMessage(),
            'icon' => 'error'
        ];
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ---- action: edit a parts movement ---------------------------------------
// A rejected edit reports through $swal_data, which stays in this request and reaches the
// script through the page's island, and $_POST is left untouched: the page publishes the
// posted values there too, so the script can reopen the edit modal with what the user
// typed instead of an empty form. A successful edit reports through the session and
// redirects, like the delete above. Only these five columns are editable; the movement's
// part, direction, source and batch are not, so the stock figures they stand for cannot
// be broken from here.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_part_movement'])) {
    $ocp_movement_id = (int) ($_POST['movement_id'] ?? 0);
    $ocp_movement_date = trim($_POST['movement_date'] ?? '');
    $ocp_quantity = trim($_POST['quantity'] ?? '');
    $ocp_price_per_unit = trim($_POST['price_per_unit'] ?? '');
    $ocp_technician = trim($_POST['technician'] ?? '');
    $ocp_purpose = trim($_POST['purpose'] ?? '');

    // Validation. Every failure leaves the row exactly as it was.
    $ocp_edit_error = '';
    if ($ocp_movement_id <= 0) {
        $ocp_edit_error = 'No parts movement was selected.';
    } elseif (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ocp_movement_date, $ocp_date_parts)
        || !checkdate((int) $ocp_date_parts[2], (int) $ocp_date_parts[3], (int) $ocp_date_parts[1])) {
        $ocp_edit_error = 'Please give the movement date as a real date (YYYY-MM-DD).';
    } elseif (!is_numeric($ocp_quantity) || $ocp_quantity <= 0) {
        $ocp_edit_error = 'Please give a quantity greater than zero.';
    } elseif (!is_numeric($ocp_price_per_unit) || $ocp_price_per_unit < 0) {
        $ocp_edit_error = 'Please give a price per unit of zero or more.';
    } elseif ($ocp_technician !== '' && !ctype_digit($ocp_technician)) {
        $ocp_edit_error = 'Please choose the technician from the list.';
    }
    unset($ocp_date_parts);

    if ($ocp_edit_error !== '') {
        $swal_data = [
            'title' => 'Error!',
            'text' => $ocp_edit_error,
            'icon' => 'error'
        ];
    } else {
        try {
            $ocp_check_stmt = $pdo->prepare("SELECT id, part_id, quantity, movement_type, batch_id FROM spare_parts_movements WHERE id = :id");
            $ocp_check_stmt->bindValue(':id', $ocp_movement_id, PDO::PARAM_INT);
            $ocp_check_stmt->execute();
            $ocp_movement = $ocp_check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($ocp_movement) {
                // An empty technician means "none": the column is nullable and the
                // technician_id has a foreign key, so it is bound as NULL, not as 0.
                $ocp_technician_id = ($ocp_technician === '') ? null : (int) $ocp_technician;

                // Changing the quantity changes what the movement did to stock, so the
                // summary has to move by the same difference: the new effect less the old
                // one. Left as it was, editing 1 to 5 kept the summary at 1.
                $ocp_stock_delta = ocpMovementStockEffect($ocp_movement, $ocp_quantity)
                    - ocpMovementStockEffect($ocp_movement, $ocp_movement['quantity']);

                try {
                    $pdo->beginTransaction();

                    ocpApplyPartStockDelta($pdo, $ocp_movement, $ocp_stock_delta);

                    $ocp_update_stmt = $pdo->prepare("UPDATE spare_parts_movements
                                                SET movement_date = :movement_date,
                                                    quantity = :quantity,
                                                    price_per_unit = :price_per_unit,
                                                    technician = :technician,
                                                    purpose = :purpose
                                                WHERE id = :id");
                    $ocp_update_stmt->bindValue(':movement_date', $ocp_movement_date);
                    $ocp_update_stmt->bindValue(':quantity', $ocp_quantity);
                    $ocp_update_stmt->bindValue(':price_per_unit', $ocp_price_per_unit);
                    $ocp_update_stmt->bindValue(':technician', $ocp_technician_id, $ocp_technician_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $ocp_update_stmt->bindValue(':purpose', $ocp_purpose);
                    $ocp_update_stmt->bindValue(':id', $ocp_movement_id, PDO::PARAM_INT);
                    $ocp_update_stmt->execute();

                    $pdo->commit();
                } catch (Throwable $ocp_stock_error) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $ocp_stock_error;
                }

                $_SESSION['swal_data'] = [
                    'title' => 'Success!',
                    'text' => 'Parts movement updated successfully! The parts inventory quantity was adjusted by ' . number_format(abs($ocp_stock_delta), 2) . '.',
                    'icon' => 'success'
                ];

                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }

            $swal_data = [
                'title' => 'Error!',
                'text' => 'Parts movement not found.',
                'icon' => 'error'
            ];
        } catch (PDOException $e) {
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    }
}

// ---- action: initial stock for a part -------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'initial_part') {
        // Process initial parts
        $part_id = $_POST['part_id'];
        $quantity = $_POST['quantity'];
        $price_per_unit = $_POST['price_per_unit'];
        $date_added = $_POST['date_added'];
        
        try {
            // The three writes below are one act of stock-taking and must stand or fall
            // together. Run as three loose statements they did not: a run that inserted the
            // batch but not the movement - or the batch and the movement but not the summary
            // - left stock in the batches that nothing accounts for. That is where part 13's
            // spare 5 came from, which made its batch total 21 against a summary of 15 and
            // dragged its average price away from the truth.
            $pdo->beginTransaction();

            // Insert initial parts record
            $insertStmt = $pdo->prepare("INSERT INTO spare_parts_movements (part_id, quantity, price_per_unit, movement_type, movement_date, notes) 
                                        VALUES (:part_id, :quantity, :price_per_unit, 'in', :date_added, 'Initial stock')");
            $insertStmt->bindParam(':part_id', $part_id);
            $insertStmt->bindParam(':quantity', $quantity);
            $insertStmt->bindParam(':price_per_unit', $price_per_unit);
            $insertStmt->bindParam(':date_added', $date_added);

            if ($insertStmt->execute()) {
                $movementId = (int) $pdo->lastInsertId();

                // Insert into spare_parts_batches for FIFO tracking, linked to the movement
                // that brought it in. Without the link a batch cannot be told apart from an
                // orphan, and reversing a movement cannot find the batch to reverse.
                $batchStmt = $pdo->prepare("INSERT INTO spare_parts_batches (part_id, quantity, price_per_unit, date_received, notes) 
                                          VALUES (:part_id, :quantity, :price_per_unit, :date_added, 'Initial stock')");
                $batchStmt->bindParam(':part_id', $part_id);
                $batchStmt->bindParam(':quantity', $quantity);
                $batchStmt->bindParam(':price_per_unit', $price_per_unit);
                $batchStmt->bindParam(':date_added', $date_added);
                $batchStmt->execute();

                $batchId = (int) $pdo->lastInsertId();

                $linkStmt = $pdo->prepare("UPDATE spare_parts_movements SET batch_id = :batch_id WHERE id = :id");
                $linkStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
                $linkStmt->bindValue(':id', $movementId, PDO::PARAM_INT);
                $linkStmt->execute();

                // Update inventory levels
                $updateStmt = $pdo->prepare("INSERT INTO spare_parts_inventory (part_id, quantity, price_per_unit) 
                                            VALUES (:part_id, :quantity, :price_per_unit)
                                            ON DUPLICATE KEY UPDATE 
                                            quantity = quantity + :quantity,
                                            price_per_unit = :price_per_unit");
                $updateStmt->bindParam(':part_id', $part_id);
                $updateStmt->bindParam(':quantity', $quantity);
                $updateStmt->bindParam(':price_per_unit', $price_per_unit);
                $updateStmt->execute();

                $pdo->commit();

                $swal_data = [
                    'title' => 'Success!',
                    'text' => 'Initial parts added successfully!',
                    'icon' => 'success'
                ];
            } else {
                $pdo->rollBack();
                $swal_data = [
                    'title' => 'Error!',
                    'text' => 'Error adding initial parts. Please try again.',
                    'icon' => 'error'
                ];
            }
        } catch(Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    }
}
