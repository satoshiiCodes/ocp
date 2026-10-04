// Proves audit-endpoint-trigger.mjs bites.
//
//   node control-endpoint-trigger.mjs
//
// The audit reports triggers that the page also posts. A trigger guarded against an action
// post is reported as guarded; the bug was that this one was not. This puts the original
// unguarded test back, requires the audit to say so, then restores the file byte for byte.
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const ROOT = 'E:\\laragon\\www\\OCP';
const file = path.join(ROOT, 'api', 'gasoline_purchase_order-endpoint.php');
const audit = path.join(ROOT, '_verify', 'audit-endpoint-trigger.mjs');

const run = () => {
  try { return execFileSync('node', [audit], { encoding: 'utf8' }); }
  catch (e) { return (e.stdout || '') + (e.stderr || ''); }
};

const original = fs.readFileSync(file, 'utf8');
const eol = original.includes('\r\n') ? '\r\n' : '\n';
const normalized = original.replace(/\r\n/g, '\n');

const fixed = "$ocp_is_po_lookup = array_key_exists('po_id', $_POST) && !array_key_exists('action', $_POST);";
const broken = "$ocp_is_po_lookup = array_key_exists('po_id', $_POST);";
if (!normalized.includes(fixed)) {
  console.log('  X control not applied - the fixed line was not found');
  process.exit(1);
}

console.log(`  as things stand: ${run().trim().split('\n')[0]}`);
const guarded = /guarded against an action post=true/.test(run());
console.log(`  the po_id trigger is reported as guarded: ${guarded}`);
if (!guarded) console.log('  X the audit does not recognise the guard');

fs.writeFileSync(file, (normalized.replace(fixed, broken)).replace(/\n/g, eol));
const out = run();
const nowGuarded = /guarded against an action post=true/.test(out);
console.log(`  with the original unguarded test: guarded=${nowGuarded}`);
const caught = !nowGuarded;

fs.writeFileSync(file, original);
const restored = fs.readFileSync(file, 'utf8') === original;
console.log(`  restored byte for byte: ${restored}`);

const problems = (!guarded ? 1 : 0) + (!caught ? 1 : 0) + (!restored ? 1 : 0);
console.log(`\nproblems: ${problems}`);
process.exit(problems ? 1 : 0);
