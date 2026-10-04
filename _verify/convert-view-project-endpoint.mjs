// Turns the lifted query blocks in api/view_project-endpoint.php into assignments
// on the returned array.
//
// The page assigned to plain variables ($project, $vehicles, ...). The endpoint
// returns them instead, so each base assignment becomes an $ocp_endpoint entry and
// the page unpacks it back into the same names.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\api\\view_project-endpoint.php';
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

// the variables the page unpacks from the endpoint
const names = [
  'project', 'stock_movements', 'stock_table_exists', 'subcon_materials',
  'eligible_workers', 'project_workers', 'vehicles', 'equipment', 'project_rentals',
];

let total = 0;
for (const n of names) {
  // "  $vehicles = [];"            -> "  $ocp_endpoint['vehicles'] = [];"
  const init = new RegExp(`^(\\s*)\\$${n} = (\\[\\]|false|true|0|null);$`, 'gm');
  // "$x = $yStmt->fetch..."        -> "$ocp_endpoint['x'] = $yStmt->fetch..."
  const assign = new RegExp(`^(\\s*)\\$${n} = (\\$[\\w$]+->fetch(?:All)?\\([^;]*?\\);)$`, 'gm');
  let c = 0;
  code = code.replace(init, (m, pad, val) => { c++; return `${pad}$ocp_endpoint['${n}'] = ${val};`; });
  code = code.replace(assign, (m, pad, rhs) => { c++; return `${pad}$ocp_endpoint['${n}'] = ${rhs}`; });
  console.log(`  ${n.padEnd(20)} ${c} assignment(s)`);
  total += c;
}

// anything still assigning to a bare name would silently not reach the page
const missed = [...code.matchAll(/^\s*\$(project|stock_movements|stock_table_exists|subcon_materials|eligible_workers|project_workers|vehicles|equipment|project_rentals) = /gm)];
console.log(`  unconverted assignments left: ${missed.length}`);
for (const m of missed) console.log(`    ${JSON.stringify(m[0].trim())}`);

// the guard's redirect must still work when the endpoint is the entry point
if (!/header\('Location: projects\.php'\)/.test(code)) {
  console.error('  WARNING: the redirect guard is missing');
}

console.log(`converted ${total} assignments`);
if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
