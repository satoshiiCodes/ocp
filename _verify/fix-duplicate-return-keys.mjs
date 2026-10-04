// Removes dead duplicate keys from the endpoints' return arrays.
//
//   node fix-duplicate-return-keys.mjs [--apply]
//
// An endpoint's array can end up listing the same key twice - once as the empty default
// and once with the real value, e.g.
//
//     'swal_data' => [],
//     ...
//     'swal_data' => $swal_data ?? [],
//
// The later entry wins, so the first is dead. It also reads as a live default to anyone
// skimming, and _verify/audit-isset-seeds.mjs rightly flags the empty array as a value
// that would make isset() true.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const dir = path.join(ROOT, 'api');

let total = 0;
for (const file of fs.readdirSync(dir).filter(f => f.endsWith('-endpoint.php')).sort()) {
  const full = path.join(dir, file);
  const raw = fs.readFileSync(full, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  const lines = raw.replace(/\r\n/g, '\n').split('\n');

  // find the return array literal: "$ocp_endpoint = [" ... "];"
  const start = lines.findIndex(l => /^\$ocp_endpoint = \[$/.test(l));
  if (start < 0) continue;
  let end = start;
  for (let i = start + 1; i < lines.length; i++) { if (/^\];$/.test(lines[i])) { end = i; break; } }

  // keys in order, with their line
  const seen = new Map();
  const drop = new Set();
  for (let i = start + 1; i < end; i++) {
    const m = /^\s*'([a-z_][a-z0-9_]*)' =>/.exec(lines[i]);
    if (!m) continue;
    const key = m[1];
    if (seen.has(key)) drop.add(seen.get(key));   // the earlier occurrence is dead
    seen.set(key, i);
  }
  if (!drop.size) continue;

  const kept = lines.filter((_, i) => !drop.has(i));
  for (const i of [...drop].sort((a, b) => a - b)) {
    const m = /^\s*'([a-z_][a-z0-9_]*)' =>/.exec(lines[i]);
    console.log(`  ${file}: dropped duplicate '${m[1]}' (line ${i + 1})`);
  }
  if (APPLY) fs.writeFileSync(full, eol === '\r\n' ? kept.join('\r\n') : kept.join('\n'));
  total += drop.size;
}
console.log(`${total} duplicate key(s) ${APPLY ? 'removed' : '(dry run)'}`);
