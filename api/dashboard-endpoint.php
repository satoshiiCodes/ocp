<?php
/**
 * api/dashboard-endpoint.php
 *
 * Every read for dashboard.php lives in this one file. The dashboard is a set of
 * summary panels, and which ones it can fill depends on the viewer: the fetch block
 * below is gated by department and position, exactly as it was when it sat in the
 * page.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place.
 *
 * Requested on its own, with no page behind it, there is no connection and no role
 * flags: the guard below then returns the empty result instead of erroring. It runs in the page's scope and returns an array
 * of the variables the markup needs; the page unpacks that array. Nothing is printed
 * here, so this file cannot disturb the page's output.
 *
 * Reads from the page's scope:
 *   $pdo                 the connection, opened by the page
 *   $user                the signed-in user, with department and position
 *   $is_motorpool, $is_warehouse, $is_admin_hr_officer, $is_admin_accounting,
 *   $is_admin_purchaser  the role gates, computed by the page from $user
 *
 * The block is lifted verbatim from dashboard.php: the queries, the thresholds and
 * the role checks are unchanged. It is the page's whole controller, which is why the
 * returned list is long - each entry is a panel's data.
 *
 * Returns
 *   late_employees
 *   monthly_expenses
 *   expense_amounts
 *   expense_counts
 *   expense_types
 *   expense_type_names
 *   expense_type_amounts
 *   expense_type_counts
 *   expense_summary
 *   recent_expenses
 *   type_distribution
 *   all_expense_types
 *   low_stock_items
 *   no_stock_items
 *   inventory_summary
 *   spare_low_stock_items
 *   spare_no_stock_items
 *   spare_inventory_summary
 *   gasoline_po_data
 *   gasoline_po_counts
 *   gasoline_item_counts
 *   gasoline_amounts
 *   gasoline_types_data
 *   recent_gasoline_po
 *   supplier_summary
 *   late_threshold
 *   current_year
 *   month_index
 *   no_stock_count
 *   low_stock_count
 *   adequate_stock_count
 *   total_inventory_items
 *   spare_no_stock_count
 *   spare_low_stock_count
 *   spare_adequate_stock_count
 *   total_spare_inventory_items
 */
// Standalone guard: see the note above. Requested on its own there is no page, no
// connection and no role flags, so it answers with the empty result rather than
// running the block below against nothing.
if (!isset($pdo) || !isset($is_motorpool)) {
    return [
        'late_employees' => null,
        'monthly_expenses' => null,
        'expense_amounts' => null,
        'expense_counts' => null,
        'expense_types' => null,
        'expense_type_names' => null,
        'expense_type_amounts' => null,
        'expense_type_counts' => null,
        'expense_summary' => null,
        'recent_expenses' => null,
        'type_distribution' => null,
        'all_expense_types' => null,
        'low_stock_items' => null,
        'no_stock_items' => null,
        'inventory_summary' => null,
        'spare_low_stock_items' => null,
        'spare_no_stock_items' => null,
        'spare_inventory_summary' => null,
        'gasoline_po_data' => null,
        'gasoline_po_counts' => null,
        'gasoline_item_counts' => null,
        'gasoline_amounts' => null,
        'gasoline_types_data' => null,
        'recent_gasoline_po' => null,
        'supplier_summary' => null,
        'late_threshold' => null,
        'current_year' => null,
        'month_index' => null,
        'no_stock_count' => null,
        'low_stock_count' => null,
        'adequate_stock_count' => null,
        'total_inventory_items' => null,
        'spare_no_stock_count' => null,
        'spare_low_stock_count' => null,
        'spare_adequate_stock_count' => null,
        'total_spare_inventory_items' => null,
    ];
}


    // Initialize all variables with default values
    $late_employees = [];
    $monthly_expenses = [];
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $months_full = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $expense_amounts = array_fill(0, 12, 0);
    $expense_counts = array_fill(0, 12, 0);
    $expense_types = [];
    $expense_type_names = [];
    $expense_type_amounts = [];
    $expense_type_counts = [];
    $expense_summary = ['total_transactions' => 0, 'total_amount' => 0, 'average_amount' => 0, 'min_amount' => 0, 'max_amount' => 0];
    $recent_expenses = [];
    $type_distribution = [];
    $all_expense_types = [];
    $low_stock_items = [];
    $no_stock_items = [];
    $inventory_summary = ['no_stock_count' => 0, 'low_stock_count' => 0, 'adequate_stock_count' => 0, 'total_items' => 0];
    $spare_low_stock_items = [];
    $spare_no_stock_items = [];
    $spare_inventory_summary = ['no_stock_count' => 0, 'low_stock_count' => 0, 'adequate_stock_count' => 0, 'total_items' => 0];
    $gasoline_po_data = [];
    $gasoline_po_counts = array_fill(0, 12, 0);
    $gasoline_item_counts = array_fill(0, 12, 0);
    $gasoline_amounts = array_fill(0, 12, 0);
    $gasoline_types_data = [];
    $recent_gasoline_po = [];
    $supplier_summary = [];
    
    // Only fetch non-Motorpool data if user is not from Motorpool AND not from Warehouse AND not Admin HR Officer AND not Admin Accounting AND not Admin Purchaser
    if (!$is_motorpool && !$is_warehouse && !$is_admin_hr_officer && !$is_admin_accounting && !$is_admin_purchaser) {
        // Get late employees data
        $late_threshold = '07:30:00';
        $late_stmt = $pdo->prepare("
            SELECT 
                employee_name,
                department,
                COUNT(*) as late_count
            FROM attendance 
            WHERE check_in > :threshold 
            AND check_in IS NOT NULL
            GROUP BY employee_id, employee_name, department
            ORDER BY late_count DESC
            LIMIT 5
        ");
        $late_stmt->bindParam(':threshold', $late_threshold);
        $late_stmt->execute();
        $late_employees = $late_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get expenses data for chart
        $current_year = date('Y');
        $expenses_stmt = $pdo->prepare("
            SELECT 
                MONTH(expense_date) as month,
                SUM(amount) as total_amount,
                COUNT(*) as expense_count
            FROM expenses 
            WHERE YEAR(expense_date) = :year
            GROUP BY MONTH(expense_date)
            ORDER BY month ASC
        ");
        $expenses_stmt->bindParam(':year', $current_year);
        $expenses_stmt->execute();
        $monthly_expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fill in the actual data for expenses
        foreach ($monthly_expenses as $expense) {
            $month_index = $expense['month'] - 1;
            $expense_amounts[$month_index] = floatval($expense['total_amount']);
            $expense_counts[$month_index] = intval($expense['expense_count']);
        }
        
        // Get expense types summary
        $expense_types_stmt = $pdo->prepare("
            SELECT 
                et.expense_name,
                et.description as type_description,
                COUNT(e.id) as transaction_count,
                COALESCE(SUM(e.amount), 0) as total_amount,
                COALESCE(SUM(e.amount), 0) as amount
            FROM expenses_type et
            LEFT JOIN expenses e ON et.id = e.expense_type_id
            GROUP BY et.id, et.expense_name, et.description
            ORDER BY total_amount DESC
        ");
        $expense_types_stmt->execute();
        $expense_types = $expense_types_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $expense_type_names = array_column($expense_types, 'expense_name');
        $expense_type_amounts = array_column($expense_types, 'amount');
        $expense_type_counts = array_column($expense_types, 'transaction_count');
        
        // Get total expenses summary
        $total_expenses_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount,
                MIN(amount) as min_amount,
                MAX(amount) as max_amount
            FROM expenses
            WHERE YEAR(expense_date) = :year
        ");
        $total_expenses_stmt->bindParam(':year', $current_year);
        $total_expenses_stmt->execute();
        $expense_summary = $total_expenses_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get recent expenses
        $recent_expenses_stmt = $pdo->prepare("
            SELECT 
                e.id,
                e.amount,
                e.expense_date,
                e.description,
                et.expense_name as expense_type
            FROM expenses e
            LEFT JOIN expenses_type et ON e.expense_type_id = et.id
            ORDER BY e.expense_date DESC
            LIMIT 5
        ");
        $recent_expenses_stmt->execute();
        $recent_expenses = $recent_expenses_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get expense type distribution for pie chart
        $type_distribution_stmt = $pdo->prepare("
            SELECT 
                et.expense_name,
                COUNT(e.id) as count,
                COALESCE(SUM(e.amount), 0) as total
            FROM expenses_type et
            LEFT JOIN expenses e ON et.id = e.expense_type_id
            GROUP BY et.id, et.expense_name
            HAVING COUNT(e.id) > 0
            ORDER BY total DESC
            LIMIT 5
        ");
        $type_distribution_stmt->execute();
        $type_distribution = $type_distribution_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all expense types
        $all_types_stmt = $pdo->prepare("
            SELECT id, expense_name, description 
            FROM expenses_type 
            ORDER BY expense_name ASC
        ");
        $all_types_stmt->execute();
        $all_expense_types = $all_types_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get inventory data for low stocks and no stocks
        $low_stock_stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.item_code,
                i.item_name,
                i.min_stock_level,
                inv.quantity,
                inv.warehouse_id,
                w.warehouse_name
            FROM inventory inv
            INNER JOIN item_names i ON inv.item_id = i.id
            LEFT JOIN warehouses w ON inv.warehouse_id = w.id
            WHERE inv.quantity <= i.min_stock_level 
            AND inv.quantity > 0
            ORDER BY inv.quantity ASC
            LIMIT 10
        ");
        $low_stock_stmt->execute();
        $low_stock_items = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $no_stock_stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.item_code,
                i.item_name,
                i.min_stock_level,
                inv.quantity,
                inv.warehouse_id,
                w.warehouse_name
            FROM inventory inv
            INNER JOIN item_names i ON inv.item_id = i.id
            LEFT JOIN warehouses w ON inv.warehouse_id = w.id
            WHERE inv.quantity = 0
            ORDER BY i.item_name ASC
            LIMIT 10
        ");
        $no_stock_stmt->execute();
        $no_stock_items = $no_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $inventory_summary_stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN inv.quantity = 0 THEN 1 ELSE 0 END) as no_stock_count,
                SUM(CASE WHEN inv.quantity <= i.min_stock_level AND inv.quantity > 0 THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN inv.quantity > i.min_stock_level THEN 1 ELSE 0 END) as adequate_stock_count,
                COUNT(*) as total_items
            FROM inventory inv
            INNER JOIN item_names i ON inv.item_id = i.id
        ");
        $inventory_summary_stmt->execute();
        $inventory_summary = $inventory_summary_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get gasoline purchase orders data
        $gasoline_po_stmt = $pdo->prepare("
            SELECT 
                MONTH(gpo.po_date) as month,
                YEAR(gpo.po_date) as year,
                COUNT(DISTINCT gpo.id) as po_count,
                COUNT(gpi.id) as item_count,
                COALESCE(SUM(gpo.total_amount), 0) as total_amount
            FROM gasoline_purchase_orders gpo
            LEFT JOIN gasoline_po_items gpi ON gpo.id = gpi.po_id
            WHERE gpo.status = 'completed'
            AND YEAR(gpo.po_date) = :year
            GROUP BY MONTH(gpo.po_date), YEAR(gpo.po_date)
            ORDER BY month ASC
        ");
        $gasoline_po_stmt->bindParam(':year', $current_year);
        $gasoline_po_stmt->execute();
        $gasoline_po_data = $gasoline_po_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($gasoline_po_data as $data) {
            $month_index = $data['month'] - 1;
            $gasoline_po_counts[$month_index] = intval($data['po_count']);
            $gasoline_item_counts[$month_index] = intval($data['item_count']);
            $gasoline_amounts[$month_index] = floatval($data['total_amount']);
        }
        
        $gasoline_types_stmt = $pdo->prepare("
            SELECT 
                gpi.gasoline_type,
                COUNT(DISTINCT gpo.id) as po_count,
                COUNT(gpi.id) as item_count,
                COALESCE(SUM(gpo.total_amount), 0) as total_amount
            FROM gasoline_purchase_orders gpo
            INNER JOIN gasoline_po_items gpi ON gpo.id = gpi.po_id
            WHERE gpo.status = 'completed'
            AND YEAR(gpo.po_date) = :year
            GROUP BY gpi.gasoline_type
            ORDER BY total_amount DESC
        ");
        $gasoline_types_stmt->bindParam(':year', $current_year);
        $gasoline_types_stmt->execute();
        $gasoline_types_data = $gasoline_types_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $recent_gasoline_po_stmt = $pdo->prepare("
            SELECT 
                gpo.id,
                gpo.po_number,
                gpo.po_date,
                gpo.completed_date,
                gpo.total_amount,
                COUNT(gpi.id) as item_count,
                GROUP_CONCAT(DISTINCT gpi.gasoline_type SEPARATOR ', ') as gasoline_types
            FROM gasoline_purchase_orders gpo
            LEFT JOIN gasoline_po_items gpi ON gpo.id = gpi.po_id
            WHERE gpo.status = 'completed'
            GROUP BY gpo.id, gpo.po_number, gpo.po_date, gpo.completed_date, gpo.total_amount
            ORDER BY gpo.completed_date DESC
            LIMIT 5
        ");
        $recent_gasoline_po_stmt->execute();
        $recent_gasoline_po = $recent_gasoline_po_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $supplier_summary_stmt = $pdo->prepare("
            SELECT 
                gpo.supplier_id,
                COUNT(DISTINCT gpo.id) as po_count,
                COALESCE(SUM(gpo.total_amount), 0) as total_amount
            FROM gasoline_purchase_orders gpo
            WHERE gpo.status = 'completed'
            AND YEAR(gpo.po_date) = :year
            GROUP BY gpo.supplier_id
            ORDER BY total_amount DESC
            LIMIT 5
        ");
        $supplier_summary_stmt->bindParam(':year', $current_year);
        $supplier_summary_stmt->execute();
        $supplier_summary = $supplier_summary_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For Admin HR Officer, only get late employees data
    if ($is_admin_hr_officer) {
        $late_threshold = '07:30:00';
        $late_stmt = $pdo->prepare("
            SELECT 
                employee_name,
                department,
                COUNT(*) as late_count
            FROM attendance 
            WHERE check_in > :threshold 
            AND check_in IS NOT NULL
            GROUP BY employee_id, employee_name, department
            ORDER BY late_count DESC
            LIMIT 5
        ");
        $late_stmt->bindParam(':threshold', $late_threshold);
        $late_stmt->execute();
        $late_employees = $late_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For Admin Accounting, get expenses data only
    if ($is_admin_accounting) {
        $current_year = date('Y');
        $expenses_stmt = $pdo->prepare("
            SELECT 
                MONTH(expense_date) as month,
                SUM(amount) as total_amount,
                COUNT(*) as expense_count
            FROM expenses 
            WHERE YEAR(expense_date) = :year
            GROUP BY MONTH(expense_date)
            ORDER BY month ASC
        ");
        $expenses_stmt->bindParam(':year', $current_year);
        $expenses_stmt->execute();
        $monthly_expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($monthly_expenses as $expense) {
            $month_index = $expense['month'] - 1;
            $expense_amounts[$month_index] = floatval($expense['total_amount']);
            $expense_counts[$month_index] = intval($expense['expense_count']);
        }
        
        // Get expense types summary for accounting
        $expense_types_stmt = $pdo->prepare("
            SELECT 
                et.expense_name,
                et.description as type_description,
                COUNT(e.id) as transaction_count,
                COALESCE(SUM(e.amount), 0) as total_amount,
                COALESCE(SUM(e.amount), 0) as amount
            FROM expenses_type et
            LEFT JOIN expenses e ON et.id = e.expense_type_id
            GROUP BY et.id, et.expense_name, et.description
            ORDER BY total_amount DESC
        ");
        $expense_types_stmt->execute();
        $expense_types = $expense_types_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $expense_type_names = array_column($expense_types, 'expense_name');
        $expense_type_amounts = array_column($expense_types, 'amount');
        $expense_type_counts = array_column($expense_types, 'transaction_count');
        
        $total_expenses_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount,
                MIN(amount) as min_amount,
                MAX(amount) as max_amount
            FROM expenses
            WHERE YEAR(expense_date) = :year
        ");
        $total_expenses_stmt->bindParam(':year', $current_year);
        $total_expenses_stmt->execute();
        $expense_summary = $total_expenses_stmt->fetch(PDO::FETCH_ASSOC);
        
        $recent_expenses_stmt = $pdo->prepare("
            SELECT 
                e.id,
                e.amount,
                e.expense_date,
                e.description,
                et.expense_name as expense_type
            FROM expenses e
            LEFT JOIN expenses_type et ON e.expense_type_id = et.id
            ORDER BY e.expense_date DESC
            LIMIT 5
        ");
        $recent_expenses_stmt->execute();
        $recent_expenses = $recent_expenses_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $type_distribution_stmt = $pdo->prepare("
            SELECT 
                et.expense_name,
                COUNT(e.id) as count,
                COALESCE(SUM(e.amount), 0) as total
            FROM expenses_type et
            LEFT JOIN expenses e ON et.id = e.expense_type_id
            GROUP BY et.id, et.expense_name
            HAVING COUNT(e.id) > 0
            ORDER BY total DESC
            LIMIT 5
        ");
        $type_distribution_stmt->execute();
        $type_distribution = $type_distribution_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $all_types_stmt = $pdo->prepare("
            SELECT id, expense_name, description 
            FROM expenses_type 
            ORDER BY expense_name ASC
        ");
        $all_types_stmt->execute();
        $all_expense_types = $all_types_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For Admin Purchaser, fetch inventory and gasoline data
    if ($is_admin_purchaser) {
        $current_year = date('Y');
        // Get inventory data for low stocks and no stocks
        $low_stock_stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.item_code,
                i.item_name,
                i.min_stock_level,
                inv.quantity,
                inv.warehouse_id,
                w.warehouse_name
            FROM inventory inv
            INNER JOIN item_names i ON inv.item_id = i.id
            LEFT JOIN warehouses w ON inv.warehouse_id = w.id
            WHERE inv.quantity <= i.min_stock_level 
            AND inv.quantity > 0
            ORDER BY inv.quantity ASC
            LIMIT 10
        ");
        $low_stock_stmt->execute();
        $low_stock_items = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $no_stock_stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.item_code,
                i.item_name,
                i.min_stock_level,
                inv.quantity,
                inv.warehouse_id,
                w.warehouse_name
            FROM inventory inv
            INNER JOIN item_names i ON inv.item_id = i.id
            LEFT JOIN warehouses w ON inv.warehouse_id = w.id
            WHERE inv.quantity = 0
            ORDER BY i.item_name ASC
            LIMIT 10
        ");
        $no_stock_stmt->execute();
        $no_stock_items = $no_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $inventory_summary_stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN inv.quantity = 0 THEN 1 ELSE 0 END) as no_stock_count,
                SUM(CASE WHEN inv.quantity <= i.min_stock_level AND inv.quantity > 0 THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN inv.quantity > i.min_stock_level THEN 1 ELSE 0 END) as adequate_stock_count,
                COUNT(*) as total_items
            FROM inventory inv
            INNER JOIN item_names i ON inv.item_id = i.id
        ");
        $inventory_summary_stmt->execute();
        $inventory_summary = $inventory_summary_stmt->fetch(PDO::FETCH_ASSOC);
        
        $no_stock_count = $inventory_summary['no_stock_count'] ?? 0;
        $low_stock_count = $inventory_summary['low_stock_count'] ?? 0;
        $adequate_stock_count = $inventory_summary['adequate_stock_count'] ?? 0;
        $total_inventory_items = $inventory_summary['total_items'] ?? 0;
        
        // Get gasoline purchase orders data
        $gasoline_po_stmt = $pdo->prepare("
            SELECT 
                MONTH(gpo.po_date) as month,
                YEAR(gpo.po_date) as year,
                COUNT(DISTINCT gpo.id) as po_count,
                COUNT(gpi.id) as item_count,
                COALESCE(SUM(gpo.total_amount), 0) as total_amount
            FROM gasoline_purchase_orders gpo
            LEFT JOIN gasoline_po_items gpi ON gpo.id = gpi.po_id
            WHERE gpo.status = 'completed'
            AND YEAR(gpo.po_date) = :year
            GROUP BY MONTH(gpo.po_date), YEAR(gpo.po_date)
            ORDER BY month ASC
        ");
        $gasoline_po_stmt->bindParam(':year', $current_year);
        $gasoline_po_stmt->execute();
        $gasoline_po_data = $gasoline_po_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($gasoline_po_data as $data) {
            $month_index = $data['month'] - 1;
            $gasoline_po_counts[$month_index] = intval($data['po_count']);
            $gasoline_item_counts[$month_index] = intval($data['item_count']);
            $gasoline_amounts[$month_index] = floatval($data['total_amount']);
        }
        
        $gasoline_types_stmt = $pdo->prepare("
            SELECT 
                gpi.gasoline_type,
                COUNT(DISTINCT gpo.id) as po_count,
                COUNT(gpi.id) as item_count,
                COALESCE(SUM(gpo.total_amount), 0) as total_amount
            FROM gasoline_purchase_orders gpo
            INNER JOIN gasoline_po_items gpi ON gpo.id = gpi.po_id
            WHERE gpo.status = 'completed'
            AND YEAR(gpo.po_date) = :year
            GROUP BY gpi.gasoline_type
            ORDER BY total_amount DESC
        ");
        $gasoline_types_stmt->bindParam(':year', $current_year);
        $gasoline_types_stmt->execute();
        $gasoline_types_data = $gasoline_types_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $recent_gasoline_po_stmt = $pdo->prepare("
            SELECT 
                gpo.id,
                gpo.po_number,
                gpo.po_date,
                gpo.completed_date,
                gpo.total_amount,
                COUNT(gpi.id) as item_count,
                GROUP_CONCAT(DISTINCT gpi.gasoline_type SEPARATOR ', ') as gasoline_types
            FROM gasoline_purchase_orders gpo
            LEFT JOIN gasoline_po_items gpi ON gpo.id = gpi.po_id
            WHERE gpo.status = 'completed'
            GROUP BY gpo.id, gpo.po_number, gpo.po_date, gpo.completed_date, gpo.total_amount
            ORDER BY gpo.completed_date DESC
            LIMIT 5
        ");
        $recent_gasoline_po_stmt->execute();
        $recent_gasoline_po = $recent_gasoline_po_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $supplier_summary_stmt = $pdo->prepare("
            SELECT 
                gpo.supplier_id,
                COUNT(DISTINCT gpo.id) as po_count,
                COALESCE(SUM(gpo.total_amount), 0) as total_amount
            FROM gasoline_purchase_orders gpo
            WHERE gpo.status = 'completed'
            AND YEAR(gpo.po_date) = :year
            GROUP BY gpo.supplier_id
            ORDER BY total_amount DESC
            LIMIT 5
        ");
        $supplier_summary_stmt->bindParam(':year', $current_year);
        $supplier_summary_stmt->execute();
        $supplier_summary = $supplier_summary_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ALWAYS fetch spare parts inventory data (for Motorpool and Purchaser)
    $spare_low_stock_stmt = $pdo->prepare("
        SELECT 
            sp.id,
            sp.part_number,
            sp.part_name,
            sp.min_stock_level,
            spi.quantity,
            spc.category_name
        FROM spare_parts_inventory spi
        INNER JOIN spare_parts sp ON spi.part_id = sp.id
        LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
        WHERE spi.quantity <= sp.min_stock_level 
        AND spi.quantity > 0
        ORDER BY spi.quantity ASC
        LIMIT 10
    ");
    $spare_low_stock_stmt->execute();
    $spare_low_stock_items = $spare_low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $spare_no_stock_stmt = $pdo->prepare("
        SELECT 
            sp.id,
            sp.part_number,
            sp.part_name,
            sp.min_stock_level,
            spi.quantity,
            spc.category_name
        FROM spare_parts_inventory spi
        INNER JOIN spare_parts sp ON spi.part_id = sp.id
        LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
        WHERE spi.quantity = 0
        ORDER BY sp.part_name ASC
        LIMIT 10
    ");
    $spare_no_stock_stmt->execute();
    $spare_no_stock_items = $spare_no_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $spare_inventory_summary_stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN spi.quantity = 0 THEN 1 ELSE 0 END) as no_stock_count,
            SUM(CASE WHEN spi.quantity <= sp.min_stock_level AND spi.quantity > 0 THEN 1 ELSE 0 END) as low_stock_count,
            SUM(CASE WHEN spi.quantity > sp.min_stock_level THEN 1 ELSE 0 END) as adequate_stock_count,
            COUNT(*) as total_items
        FROM spare_parts_inventory spi
        INNER JOIN spare_parts sp ON spi.part_id = sp.id
    ");
    $spare_inventory_summary_stmt->execute();
    $spare_inventory_summary = $spare_inventory_summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    $spare_no_stock_count = $spare_inventory_summary['no_stock_count'] ?? 0;
    $spare_low_stock_count = $spare_inventory_summary['low_stock_count'] ?? 0;
    $spare_adequate_stock_count = $spare_inventory_summary['adequate_stock_count'] ?? 0;
    $total_spare_inventory_items = $spare_inventory_summary['total_items'] ?? 0;
    
    // For non-Motorpool users, also get the no_stock_count and low_stock_count variables for inventory
    if (!$is_motorpool && !$is_warehouse && !$is_admin_hr_officer && !$is_admin_accounting && !$is_admin_purchaser) {
        $no_stock_count = $inventory_summary['no_stock_count'] ?? 0;
        $low_stock_count = $inventory_summary['low_stock_count'] ?? 0;
        $adequate_stock_count = $inventory_summary['adequate_stock_count'] ?? 0;
        $total_inventory_items = $inventory_summary['total_items'] ?? 0;
    }
    
    // For Warehouse users, fetch only warehouse inventory data
    if ($is_warehouse) {
        // Get inventory data for low stocks and no stocks (warehouse only)
        $low_stock_stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.item_code,
                i.item_name,
                i.min_stock_level,
                inv.quantity,
                inv.warehouse_id,
                w.warehouse_name
            FROM inventory inv
            INNER JOIN item_names i ON inv.item_id = i.id
            LEFT JOIN warehouses w ON inv.warehouse_id = w.id
            WHERE inv.quantity <= i.min_stock_level 
            AND inv.quantity > 0
            ORDER BY inv.quantity ASC
            LIMIT 10
        ");
        $low_stock_stmt->execute();
        $low_stock_items = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $no_stock_stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.item_code,
                i.item_name,
                i.min_stock_level,
                inv.quantity,
                inv.warehouse_id,
                w.warehouse_name
            FROM inventory inv
            INNER JOIN item_names i ON inv.item_id = i.id
            LEFT JOIN warehouses w ON inv.warehouse_id = w.id
            WHERE inv.quantity = 0
            ORDER BY i.item_name ASC
            LIMIT 10
        ");
        $no_stock_stmt->execute();
        $no_stock_items = $no_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $inventory_summary_stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN inv.quantity = 0 THEN 1 ELSE 0 END) as no_stock_count,
                SUM(CASE WHEN inv.quantity <= i.min_stock_level AND inv.quantity > 0 THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN inv.quantity > i.min_stock_level THEN 1 ELSE 0 END) as adequate_stock_count,
                COUNT(*) as total_items
            FROM inventory inv
            INNER JOIN item_names i ON inv.item_id = i.id
        ");
        $inventory_summary_stmt->execute();
        $inventory_summary = $inventory_summary_stmt->fetch(PDO::FETCH_ASSOC);
        
        $no_stock_count = $inventory_summary['no_stock_count'] ?? 0;
        $low_stock_count = $inventory_summary['low_stock_count'] ?? 0;
        $adequate_stock_count = $inventory_summary['adequate_stock_count'] ?? 0;
        $total_inventory_items = $inventory_summary['total_items'] ?? 0;
    }

// Hand back every variable the block defined. The list is the block's own variable
// set, so nothing it produced is left behind. The standalone guard at the top of
// this file returns the same array, filled with nulls.
$ocp_endpoint = [
    'late_employees' => $late_employees ?? null,
    'monthly_expenses' => $monthly_expenses ?? null,
    'expense_amounts' => $expense_amounts ?? null,
    'expense_counts' => $expense_counts ?? null,
    'expense_types' => $expense_types ?? null,
    'expense_type_names' => $expense_type_names ?? null,
    'expense_type_amounts' => $expense_type_amounts ?? null,
    'expense_type_counts' => $expense_type_counts ?? null,
    'expense_summary' => $expense_summary ?? null,
    'recent_expenses' => $recent_expenses ?? null,
    'type_distribution' => $type_distribution ?? null,
    'all_expense_types' => $all_expense_types ?? null,
    'low_stock_items' => $low_stock_items ?? null,
    'no_stock_items' => $no_stock_items ?? null,
    'inventory_summary' => $inventory_summary ?? null,
    'spare_low_stock_items' => $spare_low_stock_items ?? null,
    'spare_no_stock_items' => $spare_no_stock_items ?? null,
    'spare_inventory_summary' => $spare_inventory_summary ?? null,
    'gasoline_po_data' => $gasoline_po_data ?? null,
    'gasoline_po_counts' => $gasoline_po_counts ?? null,
    'gasoline_item_counts' => $gasoline_item_counts ?? null,
    'gasoline_amounts' => $gasoline_amounts ?? null,
    'gasoline_types_data' => $gasoline_types_data ?? null,
    'recent_gasoline_po' => $recent_gasoline_po ?? null,
    'supplier_summary' => $supplier_summary ?? null,
    'late_threshold' => $late_threshold ?? null,
    'current_year' => $current_year ?? null,
    'month_index' => $month_index ?? null,
    'no_stock_count' => $no_stock_count ?? null,
    'low_stock_count' => $low_stock_count ?? null,
    'adequate_stock_count' => $adequate_stock_count ?? null,
    'total_inventory_items' => $total_inventory_items ?? null,
    'spare_no_stock_count' => $spare_no_stock_count ?? null,
    'spare_low_stock_count' => $spare_low_stock_count ?? null,
    'spare_adequate_stock_count' => $spare_adequate_stock_count ?? null,
    'total_spare_inventory_items' => $total_spare_inventory_items ?? null,
];

return $ocp_endpoint;