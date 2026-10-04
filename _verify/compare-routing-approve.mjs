// Compares each routing "approve" case in the pre-restructure original against the current
// actions file, so a difference in the algorithm is named rather than guessed at.
//
//   node compare-routing-approve.mjs
//
// The original had one long switch; the restructure moved it into actions/pr_view_routing-actions.php
// (and lifted the helpers into includes/pr_view_routing-functions.php). The bodies were meant to
// move verbatim. Anything that differs is either a deliberate change or a lost instruction.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const backup = fs.readFileSync(path.join(ROOT, '_restructure_backup', 'pr_view_routing.php'), 'utf8').replace(/\r\n/g, '\n').split('\n');
const current = fs.readFileSync(path.join(ROOT, 'actions', 'pr_view_routing-actions.php'), 'utf8').replace(/\r\n/g, '\n').split('\n');

/** Pulls `case '<name>':` up to the matching `break;` at the same depth. */
function caseBody(lines, name) {
  const start = lines.findIndex(l => new RegExp(`^\\s*case '${name}':`).test(l));
  if (start < 0) return null;
  for (let i = start + 1; i < lines.length; i++) {
    if (/^\s*case '/.test(lines[i]) || /^\s*\}\s*\/\/\s*end/.test(lines[i])) return lines.slice(start, i);
  }
  return lines.slice(start, start + 400);
}

/** Normalises a case for comparison: trims each line and drops comments and blank lines. */
const norm = (body) => (body || [])
  .map(l => l.trim())
  .filter(l => l !== '' && !/^(\/\/|\*|\/\*)/.test(l))
  .map(l => l.replace(/\s+/g, ' '));

const names = ['approve_warehouse', 'approve_purchasing', 'approve_accounting', 'approve_approver', 'approve_purchasing_final'];

let differences = 0;
for (const name of names) {
  const a = norm(caseBody(backup, name));
  const b = norm(caseBody(current, name));
  if (!a.length) { console.log(`  ${name}: NOT FOUND in the backup`); continue; }
  if (!b.length) { console.log(`  ${name}: NOT FOUND in the current actions file`); differences++; continue; }

  // lines present in one and not the other, matched as whole normalised lines
  const setA = new Set(a);
  const setB = new Set(b);
  const onlyBackup = a.filter(l => !setB.has(l));
  const onlyCurrent = b.filter(l => !setA.has(l));

  if (!onlyBackup.length && !onlyCurrent.length) {
    console.log(`  ${name.padEnd(26)} identical (${a.length} significant lines)`);
    continue;
  }

  differences++;
  console.log(`  ${name.padEnd(26)} DIFFERS - backup ${a.length} lines, current ${b.length}`);
  for (const l of onlyBackup.slice(0, 12)) console.log(`      only in the original: ${l.slice(0, 104)}`);
  for (const l of onlyCurrent.slice(0, 12)) console.log(`      only in the current : ${l.slice(0, 104)}`);
}

console.log(`\n  cases compared: ${names.length}, differing: ${differences}`);
process.exit(differences ? 1 : 0);
