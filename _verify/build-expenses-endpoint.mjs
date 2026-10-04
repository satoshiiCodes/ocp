// Rebuilds api/expenses-endpoint.php from expenses.php's read block, lifted verbatim.
//
//   node build-expenses-endpoint.mjs [--apply]
//
// The block spans the four reads the page does: the expense types, the filtered
// expense listing with its total, the running cash balance, the employees and the
// Admin/CEO users. Lifting it verbatim keeps the query, the filters and the total
// exactly as they were; the assignments are then pointed at the returned array.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const lines = fs.readFileSync(`${ROOT}\\expenses.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const FROM = 451;   // "// Get all expense types from database"
const TO = 546;     // the closing brace of the CEO query

const at = (n) => lines[n - 1] ?? '';
if (!/^\/\/ Get all expense types from database$/.test(at(FROM))) {
  console.error(`ABORT: line ${FROM} is not the expense-types comment: ${JSON.stringify(at(FROM))}`);
  process.exit(1);
}
if (at(TO).trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(at(TO))}`);
  process.exit(1);
}
if (!/^\/\/ Get user details$/.test(at(TO + 2))) {
  console.error(`ABORT: line ${TO + 2} is not the user-details comment: ${JSON.stringify(at(TO + 2))}`);
  process.exit(1);
}

const body = lines.slice(FROM - 1, TO).join('\n');

// the variables this block produces, which the page unpacks
const names = ['expense_types_from_db', 'expenses', 'total_amount', 'current_cash_balance', 'employees', 'ceo_users'];

let code = body;
let total = 0;
for (const n of names) {
  const init = new RegExp(`^(\\s*)\\$${n} = (\\[\\]|0|array\\(\\));$`, 'gm');
  const assign = new RegExp(`^(\\s*)\\$${n} = (\\$[\\w$]+(?:->fetch(?:All)?\\([^;]*?\\)|->fetchAll\\([^;]*?\\)|\\w*\\([^;]*?\\));)$`, 'gm');
  let c = 0;
  code = code.replace(init, (m, pad, val) => { c++; return `${pad}$ocp_endpoint['${n}'] = ${val};`; });
  code = code.replace(assign, (m, pad, rhs) => { c++; return `${pad}$ocp_endpoint['${n}'] = ${rhs}`; });
  console.log(`  ${n.padEnd(22)} ${c}`);
  total += c;
}
// the running total inside the loop
code = code.replace(/^(\s*)\$total_amount \+= \$expense\['amount'\];$/m, "$1$ocp_endpoint['total_amount'] += $expense['amount'];");

const left = [...code.matchAll(/^\s*\$(expense_types_from_db|expenses|total_amount|current_cash_balance|employees|ceo_users) = /gm)];
console.log(`  unconverted assignments: ${left.length}`);
for (const m of left) console.log(`    ${m[0].trim()}`);

const out = `<?php
/**
 * api/expenses-endpoint.php
 *
 * Every read for expenses.php lives in this one file: the expense types, the filtered
 * expense listing and its total, the running cash balance, the employees for the
 * form's dropdown, the Admin/CEO users for its approval dropdown, and the signed-in
 * user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the page's
 * fetching is in one place. It runs in the page's scope and returns an array of the
 * variables the markup needs; the page unpacks that array. Nothing is printed here, so
 * this file cannot disturb the page's output.
 *
 * The filters come from the query string, exactly as before. It also requires the
 * shared helper include, because getCurrentCashBalance() is one of the reads.
 *
 * Returns
${names.map(n => ` *   ${n}`).join('\n')}
 *   display_name
 */

$ocp_endpoint = [
${names.map(n => `    '${n}' => ${n === 'total_amount' || n === 'current_cash_balance' ? '0' : '[]'},`).join('\n')}
    'display_name' => '',
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// The helper the page and the actions file both call.
require_once __DIR__ . '/../includes/expenses-functions.php';

${code}

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
`;

console.log(`\napi/expenses-endpoint.php: ${out.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\api\\expenses-endpoint.php`, out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
