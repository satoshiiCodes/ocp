<?php
/**
 * api/heavy_equipment-endpoint.php
 *
 * Every read for heavy_equipment.php lives in this one file: the equipment
 * listing, and the single record the edit modal opens with.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Requested on its own, with no page behind it, there is no connection and no
 * session: the guard below then returns the empty result instead of erroring.
 *
 * Returns
 *   equipment        array  every piece of equipment, newest first
 *   edit_equipment   array  the record the edit modal shows, when one was asked for
 *   show_edit_modal  bool   whether the edit modal should open
 */

$ocp_endpoint = [
    'equipment' => [],
    'edit_equipment' => null,
    'show_edit_modal' => false,
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- the equipment listing ---------------------------------------------------
try {
    $equipmentStmt = $pdo->prepare("SELECT * FROM equipment ORDER BY created_at DESC");
    $equipmentStmt->execute();
    $ocp_endpoint['equipment'] = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Database Error',
        'message' => 'Error fetching equipment: ' . $e->getMessage(),
    ];
}

// --- one record, so the edit modal can open filled in ------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['edit_request_id'])) {
    try {
        if (!is_numeric($_POST['edit_request_id'])) {
            throw new Exception('Invalid equipment ID');
        }

        $editStmt = $pdo->prepare("SELECT * FROM equipment WHERE id = :id");
        $editStmt->bindParam(':id', $_POST['edit_request_id'], PDO::PARAM_INT);
        $editStmt->execute();
        $edit_equipment = $editStmt->fetch(PDO::FETCH_ASSOC);

        if (!$edit_equipment) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Error',
                'message' => 'Equipment not found.',
            ];
        } else {
            $ocp_endpoint['edit_equipment'] = $edit_equipment;
            $ocp_endpoint['show_edit_modal'] = true;
        }
    } catch (PDOException $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Database Error',
            'message' => 'Error fetching equipment details: ' . $e->getMessage(),
        ];
    } catch (Exception $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Error',
            'message' => 'Error: ' . $e->getMessage(),
        ];
    }
}

return $ocp_endpoint;
