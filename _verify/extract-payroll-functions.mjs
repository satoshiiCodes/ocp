// Extracts payroll's helper functions into their own include.
//
//   node extract-payroll-functions.mjs [--apply]
//
// The POST dispatcher calls these helpers, and several of them call each other, so
// they must be loaded before whichever file runs first. Putting them in one include
// that both the page and the actions file require keeps them defined for either
// entry point without duplicating them.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const lines = fs.readFileSync(`${ROOT}\\payroll.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const FROM = 343;
const TO = 1017;

const at = (n) => lines[n - 1] ?? '';
if (!/^\/\*\*/.test(at(FROM))) {
  console.error(`ABORT: line ${FROM} is not a docblock: ${JSON.stringify(at(FROM))}`);
  process.exit(1);
}
if (at(TO).trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(at(TO))}`);
  process.exit(1);
}

// count the functions being carried over, as a sanity check
const body = lines.slice(FROM - 1, TO);
const fns = body.filter(l => /^function \w+\(/.test(l)).map(l => l.replace(/^function (\w+)\(.*/, '$1'));
console.log(`carrying ${fns.length} function(s): ${fns.join(', ')}`);

const out = `<?php
/**
 * includes/payroll-functions.php
 *
 * The helpers payroll.php and its actions file share: the overtime and attendance
 * calculations, the CSV/Excel parsers, and the routine that writes parsed
 * attendance into the database.
 *
 * They live in their own file because both entry points need them. The POST
 * dispatcher calls parseCSVFile(), parseExcelFile(), parseAttendanceData(),
 * convertExcelTime(), formatEmployeeName(), calculateOvertime(),
 * calculateAttendanceStatus() and saveAttendanceToDatabase(); the rendering below
 * the dispatcher calls formatTimeTo12Hour(). Several of them call each other, so
 * they have to be loaded before whichever runs first - which is why both
 * actions/payroll-actions.php and payroll.php require this file.
 *
 * The bodies below are lifted verbatim from payroll.php; nothing was changed.
 */

if (defined('OCP_PAYROLL_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_PAYROLL_FUNCTIONS_LOADED', true);

${body.join('\n')}
`;

console.log(`payroll-functions.php: ${out.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\includes\\payroll-functions.php`, out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
