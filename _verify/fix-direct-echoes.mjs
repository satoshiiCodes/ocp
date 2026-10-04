// Replaces direct $swal_data echoes in the reverted scripts with island reads.
//
//   node fix-direct-echoes.mjs [--apply]
//
// These four were restored to their working state, but they still echo the page's
// $swal_data straight into the JavaScript. On the page that works; requested on its own
// the variable is null, so the message comes out empty and the two contexts differ.
// Reading the island works in both, which is what the other scripts do.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const MAP = {
  'gasoline_inventory.js.php': ['GASOLINE_INVENTORY_DATA', { title: 'swalDataTitle', text: 'swalDataText', icon: 'swalDataIcon' }],
  'inventory.js.php': ['INVENTORY_DATA', { title: 'swalDataTitle', text: 'swalDataText', icon: 'swalDataIcon' }],
  'pr_spare_view_routing.js.php': ['PR_SPARE_VIEW_ROUTING_DATA', { title: 'swalDataTitle', text: 'swalDataText', icon: 'swalDataIcon' }],
  'spare_parts_inventory.js.php': ['SPARE_PARTS_INVENTORY_DATA', { title: 'swalDataTitle', text: 'swalDataText', icon: 'swalDataIcon' }],
};

for (const [file, [obj, keys]] of Object.entries(MAP)) {
  const full = `${ROOT}\\assets\\js\\${file}`;
  const raw = fs.readFileSync(full, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');
  let n = 0;
  for (const [field, key] of Object.entries(keys)) {
    const re = new RegExp(`'<\\?php echo \\$swal_data\\['${field}'\\]; \\?>'`, 'g');
    const hit = re.test(code);
    if (hit) { code = code.replace(re, `${obj}.${key} || ''`); n++; }
  }
  // ensure the island handle exists
  const constName = obj;
  if (!code.includes(`const ${constName} = window`)) {
    const m = /^(\?>)\n/m.exec(code);
    if (m) {
      const at = m.index + m[0].length;
      code = code.slice(0, at)
        + `// The page's data island. This script is its own request, so the page's\n`
        + `// PHP variables are NOT in scope: everything it needs is read from the\n`
        + `// island the page rendered.\n`
        + `const ${constName} = window.OCP_PAGE_${constName.replace(/_DATA$/, '')} || {};\n`
        + code.slice(at);
    }
  }
  if (APPLY) fs.writeFileSync(full, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log(`  ${file}: ${n} echo(es) converted`);
}
console.log(APPLY ? 'APPLIED' : 'DRY RUN');
