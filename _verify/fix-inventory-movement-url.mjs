// Repoints the movement-details lookups at the file that now answers them.
//
//   node fix-inventory-movement-url.mjs [--apply]
//
// inventory.js.php fetched 'get_movement_details.php?id=' for the view and edit movement
// modals. That script was folded into the page's endpoint and now exists only in
// _restructure_backup/, so both requests were 404s and neither modal could ever fill in.
// api/inventory-endpoint.php answers the same ?id= request as JSON (line 39 onward).
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\assets\\js\\inventory.js.php`;

const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

const before = code;
code = code.replace(
  /fetch\('get_movement_details\.php\?id=' \+ movementId\)/g,
  "fetch('api/inventory-endpoint.php?id=' + movementId)");

const changed = before === code ? 0 : (before.match(/get_movement_details\.php/g) || []).length;
console.log(`  ${changed} lookup(s) repointed at api/inventory-endpoint.php`);
const left = (code.match(/get_movement_details\.php/g) || []).length;
console.log(`  references to the removed file left: ${left}`);

if (APPLY && changed) {
  fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log('  APPLIED');
} else if (!changed) {
  console.log('  nothing to do');
} else {
  console.log('  DRY RUN');
}
