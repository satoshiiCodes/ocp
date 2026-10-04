// Extracts upload_attendance's helper functions into their own include, mirroring
// what was done for payroll.
//
//   node extract-upload-attendance-functions.mjs [--apply]
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const lines = fs.readFileSync(`${ROOT}\\upload_attendance.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const FROM = 345;   // the docblock that precedes extractAttendanceTimes
const TO = 1043;

const at = (n) => lines[n - 1] ?? '';
if (!/^\/\*\*$/.test(at(FROM))) {
  console.error(`ABORT: line ${FROM} is not the docblock: ${JSON.stringify(at(FROM))}`);
  process.exit(1);
}
if (at(TO).trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(at(TO))}`);
  process.exit(1);
}

const body = lines.slice(FROM - 1, TO);
const fns = body.filter(l => /^function \w+\(/.test(l)).map(l => l.replace(/^function (\w+)\(.*/, '$1'));
console.log(`carrying ${fns.length} function(s): ${fns.join(', ')}`);

const full = lines.slice(FROM - 1, TO);

const out = `<?php
/**
 * includes/upload_attendance-functions.php
 *
 * The helpers upload_attendance.php and its actions file share: the attendance time
 * parsing and status calculations, the CSV/Excel parsers, and the routine that
 * writes parsed attendance into the database.
 *
 * They live in their own file because both entry points need them. The POST
 * dispatcher calls formatEmployeeName(), parseCSVFile(), parseExcelFile() and
 * saveAttendanceToDatabase(); the rendering below the dispatcher calls
 * formatTimeTo12Hour(). Several call each other, so they must be loaded before
 * whichever runs first - which is why both actions/upload_attendance-actions.php
 * and upload_attendance.php require this file.
 *
 * The bodies below are lifted verbatim from upload_attendance.php; nothing changed.
 * They intentionally duplicate the same-named helpers in payroll.php: the two pages
 * are never loaded in the same request, and merging them would be a behaviour
 * change rather than a restructure.
 */

if (defined('OCP_UPLOAD_ATTENDANCE_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_UPLOAD_ATTENDANCE_FUNCTIONS_LOADED', true);

${full.join('\n')}
`;

console.log(`upload_attendance-functions.php: ${out.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\includes\\upload_attendance-functions.php`, out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
