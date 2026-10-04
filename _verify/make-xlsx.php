<?php
// Writes a .xlsx attendance file in the layout the parser expects, so the Excel upload path
// can be exercised for real.
//
//   php _verify/make-xlsx.php <path>
//
// parseAttendanceData() requires (see includes/upload_attendance-functions.php):
//   - at least 6 rows
//   - a header row containing 'Employee ID' in the first cell and 'Name' somewhere
//   - a 'Made Date:' row giving the month
//   - day-of-month numbers from the fourth header cell on, the times underneath
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$path = $argv[1] ?? (__DIR__ . '/tmp-attendance.xlsx');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// row 1: title, row 2: blank, row 3: 'Made Date:' marker, row 4: header, then data
$sheet->fromArray([
    ['Daily Time Record', '', '', '', ''],
    ['', '', '', '', ''],
    ['Made Date: January 2026', '', '', '', ''],
    ['Employee ID', 'Name', 'Department', 1, 2],
    ['99999', 'Verify, Probe', 'Admin', '', ''],
    ['99998', 'Verify, Second', 'Admin', '', ''],
], null, 'A1');

// the two day-columns carry the times: day 1 a late check-in, day 2 a late break-in
$sheet->setCellValue('D5', "09:30\n12:00\n13:45\n18:00");
$sheet->setCellValue('E5', "08:00\n12:00\n13:45\n17:00");

(new Xlsx($spreadsheet))->save($path);
echo $path;
