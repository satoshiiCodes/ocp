
<?php

    // Database configuration
   $host = 'localhost';
    $dbname = 'db_skyline';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        $response = [
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    /* ------------------------------------------------------------------ dates
     *
     * Dates are shown to the user as mm-dd-yyyy throughout, while staying stored and posted as
     * Y-m-d. These two helpers are the single place that conversion happens, so a date cannot be
     * displayed in one format on one page and another elsewhere.
     *
     * They are defined here because every page includes this file; defining them per page meant
     * some pages had them and others printed the database value straight out.
     *
     * Guarded because two pages (pr_view_routing.php, pr_spare_view_routing.php) carry their own
     * private copies of these names from before this existed.
     */
    if (!function_exists('ocp_date_mdy')) {
        /**
         * A date as mm-dd-yyyy. Returns a dash for empty or placeholder values so a table cell
         * does not come out blank.
         */
        function ocp_date_mdy($value, $dash = '—')
        {
            if ($value === null || $value === '' || $value === '0000-00-00'
                || $value === '0000-00-00 00:00:00' || $value === '1970-01-01') {
                return $dash;
            }

            // Already mm-dd-yyyy: leave it alone rather than shifting it by another conversion.
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', (string) $value)) {
                return (string) $value;
            }

            try {
                return (new DateTime((string) $value))->format('m-d-Y');
            } catch (Exception $e) {
                return $dash;
            }
        }
    }

    if (!function_exists('ocp_datetime_mdy')) {
        /**
         * A timestamp as mm-dd-yyyy hh:mm AM/PM. Keeps the clock, because these are "created at"
         * style values where the time is part of the information.
         */
        function ocp_datetime_mdy($value, $dash = '—')
        {
            if ($value === null || $value === '' || $value === '0000-00-00'
                || $value === '0000-00-00 00:00:00' || $value === '1970-01-01') {
                return $dash;
            }

            // Already converted. Re-parsing mm-dd-yyyy would read it as dd-mm-yyyy and move the
            // day and month; a few call sites had already formatted before this helper existed.
            if (preg_match('/^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/', (string) $value)) {
                return (string) $value;
            }

            try {
                return (new DateTime((string) $value))->format('m-d-Y h:i A');
            } catch (Exception $e) {
                return $dash;
            }
        }
    }
?>