// Replaces pr_spare_view_routing.js.php's last three direct PHP echoes with island reads.
//
//   node fix-psvr-echoes.mjs [--apply]
//
// They are evaluated in the script's own request, where $is_issue_materials and
// $technician_formatted do not exist - so the button read "for " with the material type
// missing, and the modal's technician line came out blank. The page publishes both.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\assets\\js\\pr_spare_view_routing.js.php`;
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

const pairs = [
  ["<?php echo $is_issue_materials ? 'Materials' : 'Spare Parts'; ?>",
    "${PR_SPARE_VIEW_ROUTING_DATA.isIssueMaterials || 'Spare Parts'}"],
  ["document.getElementById('modal-technician').textContent = '<?php echo $technician_formatted; ?>';",
    "document.getElementById('modal-technician').textContent = PR_SPARE_VIEW_ROUTING_DATA.technicianFormatted || 'N/A';"],
];

let n = 0;
for (const [from, to] of pairs) {
  const c = code.split(from).length - 1;
  if (c) { code = code.split(from).join(to); n += c; }
  else console.log(`  NOT FOUND: ${from.slice(0, 60)}`);
}
console.log(`${n} echo(es) converted`);
if (n !== 3) { console.error('ABORT: expected 3'); process.exit(1); }
if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log('APPLIED');
} else console.log('DRY RUN');
