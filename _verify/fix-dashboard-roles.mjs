// Converts dashboard.js.php's role-gated chart chain to client-side tests.
//
//   node fix-dashboard-roles.mjs [--apply]
//
// The chain decides which charts a viewer gets: Motorpool, Warehouse, HR, Accounting,
// Purchaser, then everyone else. Evaluated in the script's own request, none of the role
// flags exist, so only the `else` arm rendered and most viewers lost their charts. The
// page now publishes the flags.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\assets\\js\\dashboard.js.php`;
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

const pairs = [
  [/^([ \t]*)<\?php if \(\$is_motorpool\): \?>$/m, '$1if (DASHBOARD_DATA.isMotorpool) {'],
  [/^([ \t]*)<\?php elseif \(\$is_warehouse\): \?>$/m, '$1} else if (DASHBOARD_DATA.isWarehouse) {'],
  [/^([ \t]*)<\?php elseif \(\$is_admin_hr_officer\): \?>$/m, '$1} else if (DASHBOARD_DATA.isHrOfficer) {'],
  [/^([ \t]*)<\?php elseif \(\$is_admin_accounting\): \?>$/m, '$1} else if (DASHBOARD_DATA.isAccounting) {'],
  [/^([ \t]*)<\?php elseif \(\$is_admin_purchaser\): \?>$/m, '$1} else if (DASHBOARD_DATA.isPurchaser) {'],
  [/^([ \t]*)<\?php else: \?>$/m, '$1} else {'],
  [/^([ \t]*)<\?php endif; \?>$/m, '$1}'],
];

let n = 0;
for (const [re, rep] of pairs) {
  if (re.test(code)) { code = code.replace(re, rep); n++; }
  else console.log(`  NOT FOUND: ${re}`);
}
console.log(`${n} of ${pairs.length} branch(es) converted`);
if (n !== pairs.length) { console.error('ABORT: not every branch was found'); process.exit(1); }

const left = code.split('\n').filter(l => /<\?php (if|elseif|else|endif)/.test(l));
if (left.length) {
  console.error(`ABORT: ${left.length} PHP conditional(s) left`);
  for (const l of left.slice(0, 5)) console.error(`  ${l.trim().slice(0, 80)}`);
  process.exit(1);
}
if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log('APPLIED');
} else console.log('DRY RUN');
