// Proves audit-island-nesting.mjs bites, by putting employee_registration.php's island back
// inside the deductions modal's guard and requiring the audit to report it.
//
//   node control-island-nesting.mjs
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const ROOT = 'E:\\laragon\\www\\OCP';
const file = path.join(ROOT, 'employee_registration.php');
const audit = path.join(ROOT, '_verify', 'audit-island-nesting.mjs');

const run = () => {
  try { return execFileSync('node', [audit], { encoding: 'utf8' }); }
  catch (e) { return (e.stdout || '') + (e.stderr || ''); }
};

const original = fs.readFileSync(file, 'utf8');
const eol = original.includes('\r\n') ? '\r\n' : '\n';
const normalized = original.replace(/\r\n/g, '\n');
console.log(`  as things stand: ${run().split('\n')[0]}`);

// move the island back inside the guard: drop the endif that now precedes it
const broken = normalized.replace(
  '        </div>\n        <?php endif; ?>\n        \n        <?php\n        /* Data island consumed by assets/js/employee_registration.js.',
  '        </div>\n        \n        <?php\n        /* Data island consumed by assets/js/employee_registration.js.');

if (broken === normalized) {
  console.log('  X control not applied - the pattern did not match');
  process.exit(1);
}

fs.writeFileSync(file, eol === '\r\n' ? broken.replace(/\n/g, '\r\n') : broken);
const out = run();
console.log(`  with the island nested again: ${out.split('\n')[0]}`);
for (const l of out.split('\n').slice(1, 3)) if (l.trim()) console.log(`    ${l.trim()}`);
const caught = !/^OK:/.test(out.trim());

fs.writeFileSync(file, original);
console.log(`  restored: ${run().split('\n')[0]}`);

console.log(`\nproblems: ${caught ? 0 : 1}`);
process.exit(caught ? 0 : 1);
