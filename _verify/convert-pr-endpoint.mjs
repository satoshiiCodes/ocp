// Converts the listing block's plain assignments into $ocp_endpoint entries, skipping
// the AJAX branch above (which uses $items for its own JSON response).
import fs from 'node:fs';
const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\api\\purchase_request-endpoint.php';
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

const names = ['purchase_requests', 'items', 'projects', 'warehouses', 'suppliers', 'status_counts', 'swal_data'];
// the listing starts after the AJAX branch's exit
const cut = code.indexOf('// The listing the page renders.');
if (cut < 0) { console.error('ABORT: no listing marker'); process.exit(1); }
let head = code.slice(0, cut);
let tail = code.slice(cut);

let total = 0;
for (const n of names) {
  const init = new RegExp(`^(\\s*)\\$${n} = (\\[\\]|array\\(\\)|null);$`, 'gm');
  const assign = new RegExp(`^(\\s*)\\$${n} = (\\$[\\w$]+->fetch(?:All)?\\([^;]*?\\);|array\\([\\s\\S]*?^\\s*\\);)$`, 'gm');
  let c = 0;
  tail = tail.replace(init, (m, pad, val) => { c++; return `${pad}$ocp_endpoint['${n}'] = ${val};`; });
  tail = tail.replace(assign, (m, pad, rhs) => { c++; return `${pad}$ocp_endpoint['${n}'] = ${rhs}`; });
  console.log(`  ${n.padEnd(20)} ${c}`);
  total += c;
}
const left = [...tail.matchAll(/^\s*\$(purchase_requests|items|projects|warehouses|suppliers|status_counts|swal_data) = /gm)];
console.log(`  unconverted in the listing: ${left.length}`);
for (const m of left) console.log(`    ${m[0].trim()}`);

if (APPLY) {
  const out = head + tail;
  fs.writeFileSync(file, eol === '\r\n' ? out.replace(/\n/g, '\r\n') : out);
  console.log('APPLIED');
} else console.log('DRY RUN');
