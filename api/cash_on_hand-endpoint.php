<?php
/**
 * api/cash_on_hand-endpoint.php
 *
 * Every read for cash_on_hand.php lives in this one file: the transaction listing
 * under the chosen filters, the latest balance, the in/out totals, and the
 * signed-in user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * The filters come from the query string, exactly as before.
 *
 * Returns
 *   transactions    array  the filtered transactions, newest first
 *   current_balance float  the balance on the most recent entry
 *   total_in        float  the amount taken in across the filtered rows
 *   total_out       float  the amount paid out across the filtered rows
 *   display_name    string the signed-in user's name, for the side menu
 */

$ocp_endpoint = [
    'transactions' => [],
    'current_balance' => 0,
    'total_in' => 0,
    'total_out' => 0,
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

try {
    // The filters, from the query string
    $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
    $filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    $query = "
        SELECT c.*, 
               u.firstname as user_firstname, u.lastname as user_lastname
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

    $query .= " ORDER BY c.id DESC";

    $transactionsStmt = $pdo->prepare($query);
    $transactionsStmt->execute($params);
    $ocp_endpoint['transactions'] = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // The balance on the most recent entry
    $balanceStmt = $pdo->prepare("SELECT balance FROM cash_on_hand ORDER BY id DESC LIMIT 1");
    $balanceStmt->execute();
    $currentBalanceData = $balanceStmt->fetch(PDO::FETCH_ASSOC);
    $ocp_endpoint['current_balance'] = $currentBalanceData ? $currentBalanceData['balance'] : 0;

    // Totals across the filtered rows
    $total_in = 0;
    $total_out = 0;
    foreach ($ocp_endpoint['transactions'] as $transaction) {
        if ($transaction['transaction_type'] === 'in') {
            $total_in += $transaction['amount'];
        } else {
            $total_out += $transaction['amount'];
        }
    }
    $ocp_endpoint['total_in'] = $total_in;
    $ocp_endpoint['total_out'] = $total_out;
    unset($total_in, $total_out, $transaction, $currentBalanceData, $params, $query);
} catch (PDOException $e) {
    $_SESSION['swal_message'] = 'Error fetching transactions: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
}

// --- the signed-in user, whose name the side menu prints ---------------------
$ocp_user_id = $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $ocp_user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $display_name = $user['firstname'];
    if (!empty($user['middlename'])) {
        $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    $display_name .= ' ' . $user['lastname'];
    if (!empty($user['suffix'])) {
        $display_name .= ' ' . $user['suffix'];
    }
    $ocp_endpoint['display_name'] = $display_name;
}
unset($ocp_user_id, $user, $display_name);

return $ocp_endpoint;
