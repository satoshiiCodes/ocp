// Proves audit-json-string-islands.mjs actually bites.
//
//   node control-json-string-audit.mjs
//
// It rewrites the two files that had the real fault back to their broken form (the JSON
// string used directly), runs the audit, requires it to report both, then restores the
// files byte for byte. An audit that cannot fail on the bug it was written for is worthless.
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const ROOT = 'E:\\laragon\\www\\OCP';
const AUDIT = path.join(ROOT, '_verify', 'audit-json-string-islands.mjs');

const runAudit = () => {
  try {
    return execFileSync('node', [AUDIT], { encoding: 'utf8' });
  } catch (e) {
    return (e.stdout || '') + (e.stderr || '');
  }
};

const cases = [
  {
    file: path.join(ROOT, 'assets/js', 'purchase_request.js.php'),
    // both lines go, so the local really is the member - the state the fault was found in
    from: /const itemsRaw = PURCHASE_REQUEST_DATA\.items;\s*\n\s*const itemsData = typeof itemsRaw === 'string' \? \(JSON\.parse\(itemsRaw \|\| '\[\]'\)\) : \(itemsRaw \|\| \[\]\);/,
    to: 'const itemsData = PURCHASE_REQUEST_DATA.items;',
    label: 'purchase_request item list',
  },
  {
    file: path.join(ROOT, 'assets/js', 'upload_attendance.js.php'),
    from: /var missingEmployeesData =[\s\S]*?\(UPLOAD_ATTENDANCE_DATA\.missingEmployees \|\| null\);/,
    to: 'var missingEmployeesData = UPLOAD_ATTENDANCE_DATA.missingEmployees;',
    label: 'upload_attendance missing employees',
  },
  {
    file: path.join(ROOT, 'assets/js', 'pr_view_routing.js.php'),
    from: /const poItemsRaw = PR_VIEW_ROUTING_DATA\.poItemsDetails;\s*\n\s*const poItems = typeof poItemsRaw === 'string' \? JSON\.parse\(poItemsRaw \|\| '\{\}'\) : \(poItemsRaw \|\| \{\}\);/,
    to: 'const poItems = PR_VIEW_ROUTING_DATA.poItemsDetails;',
    label: 'pr_view_routing PO items',
  },
  {
    file: path.join(ROOT, 'assets/js', 'pr_view_routing.js.php'),
    from: /const wsItemsRaw = PR_VIEW_ROUTING_DATA\.wsItemsDetails;\s*\n\s*const wsItems = typeof wsItemsRaw === 'string' \? JSON\.parse\(wsItemsRaw \|\| '\{\}'\) : \(wsItemsRaw \|\| \{\}\);/,
    to: 'const wsItems = PR_VIEW_ROUTING_DATA.wsItemsDetails;',
    label: 'pr_view_routing WS items',
  },
];

const before = runAudit();
console.log(`  as things stand: ${before.split('\n')[0]}`);

let problems = 0;
for (const c of cases) {
  const original = fs.readFileSync(c.file, 'utf8');
  if (!c.from.test(original)) {
    console.log(`  X ${c.label}: could not find the fixed form to break - control not applied`);
    problems++;
    continue;
  }
  fs.writeFileSync(c.file, original.replace(c.from, c.to));
  const out = runAudit();
  const caught = !/^OK:/.test(out.trim());
  console.log(`  ${caught ? 'caught' : 'MISSED'}  ${c.label}`);
  if (!caught) problems++;
  else console.log(`           ${out.split('\n').slice(1, 3).map(l => l.trim()).join(' | ')}`);
  fs.writeFileSync(c.file, original);
}

const after = runAudit();
console.log(`  restored: ${after.split('\n')[0]}`);

console.log(`\nproblems: ${problems}`);
process.exit(problems ? 1 : 0);
