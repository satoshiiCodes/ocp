<?php
/**
 * actions/items_categories-actions.php
 *
 * Every action for items_categories.php lives in this one file.
 *
 * The page pulls this file in at the top, so it runs in the page's scope: the
 * database handle, the session flash messages and $_POST all behave exactly as
 * they did when this code sat inline in the page.
 *
 * Actions handled
 *   delete  (POST delete_id)   remove a category
 *   edit    (POST edit_id)     rename / re-describe a category
 *   add     (POST otherwise)   create a category
 *
 * Every branch finishes with a redirect back to the page, carrying a SweetAlert
 * message in the session, exactly as before.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_ITEMS_CATEGORIES_ACTIONS_RAN')) {
    return;
}
define('OCP_ITEMS_CATEGORIES_ACTIONS_RAN', true);

/** Stores a SweetAlert message and returns to the page. */
$ocp_redirect = static function (string $title, string $text, string $icon): void {
    $_SESSION['sweetalert'] = [
        'title' => $title,
        'text' => $text,
        'icon' => $icon,
    ];
    header('Location: items_categories.php');
    exit();
};

// ------------------------------------------------------------------- delete
if (isset($_POST['delete_id'])) {
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM items_categories WHERE id = :id");
        $deleteStmt->bindParam(':id', $_POST['delete_id']);

        if ($deleteStmt->execute()) {
            $ocp_redirect('Success!', 'Category deleted successfully!', 'success');
        }
        $ocp_redirect('Error!', 'Error deleting category. Please try again.', 'error');
    } catch (PDOException $e) {
        $ocp_redirect('Database Error!', 'Database error: ' . $e->getMessage(), 'error');
    }
}

// --------------------------------------------------------------------- edit
if (isset($_POST['edit_id'])) {
    $edit_id = $_POST['edit_id'];
    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($category_name === '') {
        $ocp_redirect('Validation Error!', 'Category name is required.', 'error');
    }

    try {
        // The name must stay unique across the other categories
        $checkStmt = $pdo->prepare("SELECT id FROM items_categories WHERE category_name = :category_name AND id != :id");
        $checkStmt->bindParam(':category_name', $category_name);
        $checkStmt->bindParam(':id', $edit_id);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            $ocp_redirect('Validation Error!', 'Category name already exists. Please use a different name.', 'error');
        }

        $updateStmt = $pdo->prepare("UPDATE items_categories SET category_name = :category_name, description = :description WHERE id = :id");
        $updateStmt->bindParam(':category_name', $category_name);
        $updateStmt->bindParam(':description', $description);
        $updateStmt->bindParam(':id', $edit_id);

        if ($updateStmt->execute()) {
            $ocp_redirect('Success!', 'Category updated successfully!', 'success');
        }
        $ocp_redirect('Error!', 'Error updating category. Please try again.', 'error');
    } catch (PDOException $e) {
        $ocp_redirect('Database Error!', 'Database error: ' . $e->getMessage(), 'error');
    }
}

// ---------------------------------------------------------------------- add
$category_name = trim($_POST['category_name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($category_name === '') {
    $ocp_redirect('Validation Error!', 'Category name is required.', 'error');
}

try {
    // The name must not already be taken
    $checkStmt = $pdo->prepare("SELECT id FROM items_categories WHERE category_name = :category_name");
    $checkStmt->bindParam(':category_name', $category_name);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        $ocp_redirect('Validation Error!', 'Category name already exists. Please use a different name.', 'error');
    }

    $insertStmt = $pdo->prepare("INSERT INTO items_categories (category_name, description) VALUES (:category_name, :description)");
    $insertStmt->bindParam(':category_name', $category_name);
    $insertStmt->bindParam(':description', $description);

    if ($insertStmt->execute()) {
        $ocp_redirect('Success!', 'Category added successfully!', 'success');
    }
    $ocp_redirect('Error!', 'Error adding category. Please try again.', 'error');
} catch (PDOException $e) {
    $ocp_redirect('Database Error!', 'Database error: ' . $e->getMessage(), 'error');
}
