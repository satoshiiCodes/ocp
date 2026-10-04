// Checks that every script which reads an island handle actually declares it.
//
//   node audit-flag-handles.mjs
//
// The scripts read their page's island as `const PAGE_DATA = window.OCP_PAGE_PAGE || {}`.
// A script that uses PAGE_DATA.something without that line throws on the first property
// access: the whole script dies, so nothing on the page works. spare_parts.js was in
// exactly that state - vehicle-delete, alerts and every other handler were unreachable.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const dir = path.join(ROOT, 'assets/js');
const findings = [];

for (const file of fs.readdirSync(dir).filter(f => f.endsWith('.js.php') || f.endsWith('.js')).sort()) {
  const src = fs.readFileSync(path.join(dir, file), 'utf8');
  // handles are the ALL_CAPS names ending in _DATA that the script dereferences
  const used = new Set([...src.matchAll(/([A-Z][A-Z0-9_]*_DATA)\s*\./g)].map(m => m[1]));
  for (const name of used) {
    const declared = new RegExp(`(?:const|let|var)\\s+${name}\\s*=`).test(src);
    if (!declared) findings.push({ file, name });
  }
}

if (!findings.length) {
  console.log('OK: every island handle a script reads is declared in that script');
} else {
  console.log(`${findings.length} script(s) read an island handle they never declare:`);
  for (const f of findings) console.log(`  ${f.file.padEnd(34)} ${f.name}`);
  console.log('\neach one throws ReferenceError on first use, so the whole script is dead');
}
