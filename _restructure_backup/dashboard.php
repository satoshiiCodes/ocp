<?php
    session_start();
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
    
    // Database connection
    require_once 'includes/db_config.php';
    
    // Get user details including department and position
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix, department, position FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if user is from Motorpool department
    $is_motorpool = ($user['department'] == 'Motorpool');
    // Check if user is from Warehouse department
    $is_warehouse = ($user['department'] == 'Warehouse');
    // Check if user is Admin HR Officer
    $is_admin_hr_officer = ($user['department'] == 'Admin' && $user['position'] == 'HR Officer');
    // Check if user is Admin Accounting
    $is_admin_accounting = ($user['department'] == 'Admin' && $user['position'] == 'Accounting');
    // Check if user is Admin Purchaser
    $is_admin_purchaser = ($user['department'] == 'Admin' && $user['position'] == 'Purchaser');
    
    // Format the display name
    $display_name = $user['firstname'];
    
    if (!empty($user['middlename'])) {
        $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    
    $display_name .= ' ' . $user['lastname'];
    
    if (!empty($user['suffix'])) {
        $display_name .= ' ' . $user['suffix'];
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
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Dashboard - OCP Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <link rel="icon" type="image/png" href="img/logo/OCP.png">
        <style>
            .badge-critical {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
                padding: 5px 10px;
                border-radius: 20px;
            }
            .badge-warning {
                background: linear-gradient(135deg, #fccb90 0%, #d57eeb 100%);
                color: white;
                padding: 5px 10px;
                border-radius: 20px;
            }
            .badge-monitor {
                background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
                color: white;
                padding: 5px 10px;
                border-radius: 20px;
            }
            .threshold-info {
                background-color: #f8f9fa;
                border-left: 4px solid #dc3545;
                padding: 10px 15px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .summary-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 20px;
                transition: transform 0.3s;
            }
            .summary-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            }
            .summary-card h3 {
                font-size: 2rem;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .summary-card p {
                margin-bottom: 0;
                opacity: 0.9;
            }
            .expense-type-badge {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 0.85rem;
                font-weight: 500;
                background-color: #e9ecef;
            }
            /* Make pie chart container smaller */
            .small-pie-chart-container {
                max-width: 300px;
                margin: 0 auto 20px auto;
            }
            /* Gasoline card styles */
            .gasoline-summary-card {
                background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
                color: white;
                border-radius: 10px;
                padding: 15px;
                margin-bottom: 15px;
                transition: transform 0.3s;
            }
            .gasoline-summary-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            }
            .gasoline-summary-card h4 {
                font-size: 1.5rem;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .gasoline-summary-card p {
                margin-bottom: 0;
                opacity: 0.9;
                font-size: 0.9rem;
            }
            .gasoline-type-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 0.8rem;
                background-color: #e9ecef;
                margin: 2px;
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
                        <h1 class="mt-4">Dashboard</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                        
                        <?php if ($is_motorpool): ?>
                            <!-- Motorpool User: Only show Motorpool Inventory Status -->
                            <div class="row">
                                <div class="col-xl-12">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-tools me-1"></i>
                                            Motorpool Inventory Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-xl-12">
                                                    <!-- Smaller pie chart container -->
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="sparePartsPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="col-xl-12 mt-4">
                                                    <div class="row">
                                                        <div class="col-xl-12">
                                                            <h6 class="fw-bold">Low Stock Spare Parts (<?php echo count($spare_low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_low_stock_items)): ?>
                                                                            <?php foreach ($spare_low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge bg-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="5" class="text-center">No low stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-12 mt-3">
                                                            <h6 class="fw-bold">No Stock Spare Parts (<?php echo count($spare_no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_no_stock_items)): ?>
                                                                            <?php foreach ($spare_no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge bg-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No out of stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($is_warehouse): ?>
                            <!-- Warehouse User: Only show Warehouse Status - Low Stock | No Stock Items -->
                            <div class="row">
                                <div class="col-xl-12">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-boxes me-1"></i>
                                            Warehouse Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-xl-12">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="inventoryPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="col-xl-12 mt-4">
                                                    <div class="row">
                                                        <div class="col-xl-12">
                                                            <h6 class="fw-bold">No Stock Items (<?php echo count($no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($no_stock_items)): ?>
                                                                            <?php foreach ($no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge bg-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="3" class="text-center">No out of stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-12 mt-3">
                                                            <h6 class="fw-bold">Low Stock Items (<?php echo count($low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($low_stock_items)): ?>
                                                                            <?php foreach ($low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge bg-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No low stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($is_admin_hr_officer): ?>
                            <!-- Admin HR Officer: Only show Top 5 Employees with Most Late Attendances -->
                            <div class="row">
                                <div class="col-xl-12">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-table me-1"></i>
                                            Top 5 Employees with Most Late Attendances
                                        </div>
                                        <div class="card-body">
                                            <table id="lateEmployeesTable" class="table table-bordered table-striped">
                                                <thead>
                                                     <tr>
                                                        <th>Employee Name</th>
                                                        <th>Department</th>
                                                        <th>Times Late</th>
                                                     </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($late_employees)): ?>
                                                        <?php foreach ($late_employees as $employee): ?>
                                                            <tr>
                                                                <td><strong><?php echo htmlspecialchars($employee['employee_name']); ?></strong></td>
                                                                <td><?php echo htmlspecialchars($employee['department'] ?? 'Not Assigned'); ?></td>
                                                                <td>
                                                                    <span class="badge bg-danger"><?php echo $employee['late_count']; ?> times</span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="3" class="text-center">No late attendance records found after 7:30 AM</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($is_admin_accounting): ?>
                            <!-- Admin Accounting User: Only show Monthly Expenses Overview -->
                            <div class="row">
                                <div class="col-xl-12">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-chart-bar me-1"></i>
                                            Monthly Expenses Overview - <?php echo date('Y'); ?>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="expensesChart" width="100%" height="40"></canvas>
                                            
                                            <div class="row mt-4">
                                                <div class="col-xl-12">
                                                    <h6 class="fw-bold">Monthly Breakdown</h6>
                                                    <div style="max-height: 200px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Month</th>
                                                                    <th>Amount (₱)</th>
                                                                    <th>Transactions</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $has_expense_data = false;
                                                                for ($i = 0; $i < 12; $i++): 
                                                                    if ($expense_amounts[$i] > 0 || $expense_counts[$i] > 0):
                                                                        $has_expense_data = true;
                                                                ?>
                                                                    <tr>
                                                                        <td><strong><?php echo $months_full[$i]; ?></strong></td>
                                                                        <td>₱<?php echo number_format($expense_amounts[$i], 2); ?></td>
                                                                        <td><?php echo $expense_counts[$i]; ?></td>
                                                                    </tr>
                                                                <?php 
                                                                    endif;
                                                                endfor; 
                                                                if (!$has_expense_data):
                                                                ?>
                                                                    <tr>
                                                                        <td colspan="3" class="text-center">No expense records found for <?php echo date('Y'); ?></td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-3">
                                                <div class="col-xl-12">
                                                    <h6 class="fw-bold">Expense Types Breakdown</h6>
                                                    <div style="max-height: 200px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Expense Type</th>
                                                                    <th>Transactions</th>
                                                                    <th>Total Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (!empty($expense_types)): ?>
                                                                    <?php foreach ($expense_types as $type): ?>
                                                                        <?php if ($type['transaction_count'] > 0): ?>
                                                                        <tr>
                                                                            <td><strong><?php echo htmlspecialchars($type['expense_name']); ?></strong></td>
                                                                            <td><?php echo $type['transaction_count']; ?></td>
                                                                            <td>₱<?php echo number_format($type['total_amount'], 2); ?></td>
                                                                        </tr>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="3" class="text-center">No expense types found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($is_admin_purchaser): ?>
                            <!-- Admin Purchaser: Show Motorpool Inventory, Warehouse Inventory, and Gasoline PO -->
                            <div class="row">
                                <div class="col-xl-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-tools me-1"></i>
                                            Motorpool Inventory Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-xl-12">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="sparePartsPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="col-xl-12 mt-4">
                                                    <div class="row">
                                                        <div class="col-xl-12">
                                                            <h6 class="fw-bold">Low Stock Spare Parts (<?php echo count($spare_low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_low_stock_items)): ?>
                                                                            <?php foreach ($spare_low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge bg-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="5" class="text-center">No low stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-12 mt-3">
                                                            <h6 class="fw-bold">No Stock Spare Parts (<?php echo count($spare_no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_no_stock_items)): ?>
                                                                            <?php foreach ($spare_no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge bg-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No out of stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-xl-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-boxes me-1"></i>
                                            Warehouse Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-xl-12">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="inventoryPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="col-xl-12 mt-4">
                                                    <div class="row">
                                                        <div class="col-xl-12">
                                                            <h6 class="fw-bold">No Stock Items (<?php echo count($no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($no_stock_items)): ?>
                                                                            <?php foreach ($no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge bg-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="3" class="text-center">No out of stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-12 mt-3">
                                                            <h6 class="fw-bold">Low Stock Items (<?php echo count($low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($low_stock_items)): ?>
                                                                            <?php foreach ($low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge bg-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No low stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-xl-12">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-gas-pump me-1"></i>
                                            Gasoline PO - Completed Orders Per Month (<?php echo $current_year; ?>)
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-xl-12">
                                                    <canvas id="gasolineChart" width="100%" height="40"></canvas>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-4">
                                                <div class="col-xl-12">
                                                    <h6 class="fw-bold">Monthly Breakdown</h6>
                                                    <div style="max-height: 250px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Month</th>
                                                                    <th>PO Count</th>
                                                                    <th>Item Count</th>
                                                                    <th>Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $has_data = false;
                                                                for ($i = 0; $i < 12; $i++): 
                                                                    if ($gasoline_po_counts[$i] > 0 || $gasoline_amounts[$i] > 0):
                                                                        $has_data = true;
                                                                ?>
                                                                    <tr>
                                                                        <td><strong><?php echo $months_full[$i]; ?></strong></td>
                                                                        <td><?php echo $gasoline_po_counts[$i]; ?></td>
                                                                        <td><?php echo $gasoline_item_counts[$i]; ?></td>
                                                                        <td>₱<?php echo number_format($gasoline_amounts[$i], 2); ?></td>
                                                                    </tr>
                                                                <?php 
                                                                    endif;
                                                                endfor; 
                                                                if (!$has_data):
                                                                ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-center">No completed gasoline purchase orders found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="col-xl-12 mt-3">
                                                    <h6 class="fw-bold">Gasoline Type Breakdown</h6>
                                                    <div style="max-height: 250px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Gasoline Type</th>
                                                                    <th>PO Count</th>
                                                                    <th>Item Count</th>
                                                                    <th>Total Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (!empty($gasoline_types_data)): ?>
                                                                    <?php foreach ($gasoline_types_data as $type): ?>
                                                                        <tr>
                                                                            <td><strong><?php echo htmlspecialchars($type['gasoline_type']); ?></strong></td>
                                                                            <td><?php echo $type['po_count']; ?></td>
                                                                            <td><?php echo $type['item_count']; ?></td>
                                                                            <td>₱<?php echo number_format($type['total_amount'], 2); ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-center">No completed gasoline purchase orders found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Non-Motorpool & Non-Warehouse & Non-Admin HR Officer & Non-Admin Accounting & Non-Admin Purchaser User: Show all sections -->
                            <!-- Spare Parts Inventory Low Stock and No Stock Chart -->
                            <div class="row">
                                <div class="col-xl-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-tools me-1"></i>
                                            Motorpool Inventory Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-xl-12">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="sparePartsPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="col-xl-12 mt-4">
                                                    <div class="row">
                                                        <div class="col-xl-12">
                                                            <h6 class="fw-bold">Low Stock Spare Parts (<?php echo count($spare_low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_low_stock_items)): ?>
                                                                            <?php foreach ($spare_low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge bg-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="5" class="text-center">No low stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-12 mt-3">
                                                            <h6 class="fw-bold">No Stock Spare Parts (<?php echo count($spare_no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_no_stock_items)): ?>
                                                                            <?php foreach ($spare_no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge bg-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No out of stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Inventory Low Stock and No Stock Chart -->
                                <div class="col-xl-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-boxes me-1"></i>
                                            Warehouse Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-xl-12">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="inventoryPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="col-xl-12 mt-4">
                                                    <div class="row">
                                                        <div class="col-xl-12">
                                                            <h6 class="fw-bold">No Stock Items (<?php echo count($no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($no_stock_items)): ?>
                                                                            <?php foreach ($no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge bg-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="3" class="text-center">No out of stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-12 mt-3">
                                                            <h6 class="fw-bold">Low Stock Items (<?php echo count($low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($low_stock_items)): ?>
                                                                            <?php foreach ($low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge bg-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No low stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gasoline Purchase Orders with Completed Status -->
                            <div class="row">
                                <div class="col-xl-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-gas-pump me-1"></i>
                                            Gasoline PO - Completed Orders Per Month (<?php echo $current_year; ?>)
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-xl-12">
                                                    <canvas id="gasolineChart" width="100%" height="40"></canvas>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-4">
                                                <div class="col-xl-12">
                                                    <h6 class="fw-bold">Monthly Breakdown</h6>
                                                    <div style="max-height: 250px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Month</th>
                                                                    <th>PO Count</th>
                                                                    <th>Item Count</th>
                                                                    <th>Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $has_data = false;
                                                                for ($i = 0; $i < 12; $i++): 
                                                                    if ($gasoline_po_counts[$i] > 0 || $gasoline_amounts[$i] > 0):
                                                                        $has_data = true;
                                                                ?>
                                                                    <tr>
                                                                        <td><strong><?php echo $months_full[$i]; ?></strong></td>
                                                                        <td><?php echo $gasoline_po_counts[$i]; ?></td>
                                                                        <td><?php echo $gasoline_item_counts[$i]; ?></td>
                                                                        <td>₱<?php echo number_format($gasoline_amounts[$i], 2); ?></td>
                                                                    </tr>
                                                                <?php 
                                                                    endif;
                                                                endfor; 
                                                                if (!$has_data):
                                                                ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-center">No completed gasoline purchase orders found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="col-xl-12 mt-3">
                                                    <h6 class="fw-bold">Gasoline Type Breakdown</h6>
                                                    <div style="max-height: 250px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Gasoline Type</th>
                                                                    <th>PO Count</th>
                                                                    <th>Item Count</th>
                                                                    <th>Total Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (!empty($gasoline_types_data)): ?>
                                                                    <?php foreach ($gasoline_types_data as $type): ?>
                                                                        <tr>
                                                                            <td><strong><?php echo htmlspecialchars($type['gasoline_type']); ?></strong></td>
                                                                            <td><?php echo $type['po_count']; ?></td>
                                                                            <td><?php echo $type['item_count']; ?></td>
                                                                            <td>₱<?php echo number_format($type['total_amount'], 2); ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-center">No completed gasoline purchase orders found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Monthly Expenses Chart -->
                                <div class="col-xl-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-chart-bar me-1"></i>
                                            Monthly Expenses Overview - <?php echo $current_year; ?>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="expensesChart" width="100%" height="40"></canvas>
                                            
                                            <div class="row mt-4">
                                                <div class="col-xl-12">
                                                    <h6 class="fw-bold">Monthly Breakdown</h6>
                                                    <div style="max-height: 200px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Month</th>
                                                                    <th>Amount (₱)</th>
                                                                    <th>Transactions</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $has_expense_data = false;
                                                                for ($i = 0; $i < 12; $i++): 
                                                                    if ($expense_amounts[$i] > 0 || $expense_counts[$i] > 0):
                                                                        $has_expense_data = true;
                                                                ?>
                                                                    <tr>
                                                                        <td><strong><?php echo $months_full[$i]; ?></strong></td>
                                                                        <td>₱<?php echo number_format($expense_amounts[$i], 2); ?></td>
                                                                        <td><?php echo $expense_counts[$i]; ?></td>
                                                                    </tr>
                                                                <?php 
                                                                    endif;
                                                                endfor; 
                                                                if (!$has_expense_data):
                                                                ?>
                                                                    <tr>
                                                                        <td colspan="3" class="text-center">No expense records found for <?php echo $current_year; ?></td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-3">
                                                <div class="col-xl-12">
                                                    <h6 class="fw-bold">Expense Types Breakdown</h6>
                                                    <div style="max-height: 200px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Expense Type</th>
                                                                    <th>Transactions</th>
                                                                    <th>Total Amount (₱)</th>
                                                                  </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (!empty($expense_types)): ?>
                                                                    <?php foreach ($expense_types as $type): ?>
                                                                        <?php if ($type['transaction_count'] > 0): ?>
                                                                            <td><strong><?php echo htmlspecialchars($type['expense_name']); ?></strong></td>
                                                                            <td><?php echo $type['transaction_count']; ?></td>
                                                                            <td>₱<?php echo number_format($type['total_amount'], 2); ?></td>
                                                                        </tr>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="3" class="text-center">No expense types found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Late Employees Table -->
                            <div class="row">
                                <div class="col-xl-12">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-table me-1"></i>
                                            Top 5 Employees with Most Late Attendances
                                        </div>
                                        <div class="card-body">
                                            <table id="lateEmployeesTable" class="table table-bordered table-striped">
                                                <thead>
                                                        <th>Employee Name</th>
                                                        <th>Department</th>
                                                        <th>Times Late</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($late_employees)): ?>
                                                        <?php foreach ($late_employees as $employee): ?>
                                                            <tr>
                                                                <td><strong><?php echo htmlspecialchars($employee['employee_name']); ?></strong></td>
                                                                <td><?php echo htmlspecialchars($employee['department'] ?? 'Not Assigned'); ?></td>
                                                                <td>
                                                                    <span class="badge bg-danger"><?php echo $employee['late_count']; ?> times</span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="3" class="text-center">No late attendance records found after 7:30 AM</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Logout function
            document.getElementById('logoutLink').addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = 'action/logout.php';
                }
            });
            
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
            
            <?php if ($is_motorpool): ?>
            // Create Spare Parts Inventory Pie Chart (for Motorpool only)
            const sparePartsCtx = document.getElementById('sparePartsPieChart').getContext('2d');
            new Chart(sparePartsCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [<?php echo $spare_no_stock_count; ?>, <?php echo $spare_low_stock_count; ?>, <?php echo $spare_adequate_stock_count; ?>],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' spare parts (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            <?php elseif ($is_warehouse): ?>
            // Create Inventory Pie Chart (for Warehouse only)
            const inventoryCtx = document.getElementById('inventoryPieChart').getContext('2d');
            new Chart(inventoryCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [<?php echo $no_stock_count; ?>, <?php echo $low_stock_count; ?>, <?php echo $adequate_stock_count; ?>],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' items (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            <?php elseif ($is_admin_hr_officer): ?>
            // No charts to initialize for Admin HR Officer
            <?php elseif ($is_admin_accounting): ?>
            // Create Monthly Expenses Chart for Accounting
            const ctx1 = document.getElementById('expensesChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Expense Amount (₱)',
                        data: <?php echo json_encode($expense_amounts); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 1
                    }, {
                        label: 'Number of Transactions',
                        data: <?php echo json_encode($expense_counts); ?>,
                        type: 'line',
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        order: 0
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: ''
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value + ' transactions';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.raw;
                                    if (label.includes('Amount')) {
                                        return label + ': ₱' + value.toFixed(2);
                                    } else {
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            <?php elseif ($is_admin_purchaser): ?>
            // Create Inventory Pie Chart for Purchaser
            const inventoryCtx = document.getElementById('inventoryPieChart').getContext('2d');
            new Chart(inventoryCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [<?php echo $no_stock_count; ?>, <?php echo $low_stock_count; ?>, <?php echo $adequate_stock_count; ?>],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' items (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Spare Parts Inventory Pie Chart for Purchaser
            const sparePartsCtx = document.getElementById('sparePartsPieChart').getContext('2d');
            new Chart(sparePartsCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [<?php echo $spare_no_stock_count; ?>, <?php echo $spare_low_stock_count; ?>, <?php echo $spare_adequate_stock_count; ?>],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' spare parts (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Gasoline Chart for Purchaser
            const gasCtx = document.getElementById('gasolineChart').getContext('2d');
            new Chart(gasCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Total Amount (₱)',
                        data: <?php echo json_encode($gasoline_amounts); ?>,
                        backgroundColor: 'rgba(255, 159, 64, 0.7)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 1
                    }, {
                        label: 'Number of POs',
                        data: <?php echo json_encode($gasoline_po_counts); ?>,
                        type: 'line',
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        order: 0
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Number of POs'
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value + ' POs';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.raw;
                                    if (label.includes('Amount')) {
                                        return label + ': ₱' + value.toFixed(2);
                                    } else {
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            <?php else: ?>
            // Create Inventory Pie Chart
            const inventoryCtx = document.getElementById('inventoryPieChart').getContext('2d');
            new Chart(inventoryCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [<?php echo $no_stock_count; ?>, <?php echo $low_stock_count; ?>, <?php echo $adequate_stock_count; ?>],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' items (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Spare Parts Inventory Pie Chart
            const sparePartsCtx = document.getElementById('sparePartsPieChart').getContext('2d');
            new Chart(sparePartsCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [<?php echo $spare_no_stock_count; ?>, <?php echo $spare_low_stock_count; ?>, <?php echo $spare_adequate_stock_count; ?>],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' spare parts (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Monthly Expenses Chart
            const ctx1 = document.getElementById('expensesChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Expense Amount (₱)',
                        data: <?php echo json_encode($expense_amounts); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 1
                    }, {
                        label: 'Number of Transactions',
                        data: <?php echo json_encode($expense_counts); ?>,
                        type: 'line',
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        order: 0
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: ''
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value + ' transactions';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.raw;
                                    if (label.includes('Amount')) {
                                        return label + ': ₱' + value.toFixed(2);
                                    } else {
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Gasoline Chart
            const gasCtx = document.getElementById('gasolineChart').getContext('2d');
            new Chart(gasCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Total Amount (₱)',
                        data: <?php echo json_encode($gasoline_amounts); ?>,
                        backgroundColor: 'rgba(255, 159, 64, 0.7)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 1
                    }, {
                        label: 'Number of POs',
                        data: <?php echo json_encode($gasoline_po_counts); ?>,
                        type: 'line',
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        order: 0
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Number of POs'
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value + ' POs';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.raw;
                                    if (label.includes('Amount')) {
                                        return label + ': ₱' + value.toFixed(2);
                                    } else {
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        </script>
    </body>
</html>