// Repairs the codemod's third substitution, which was wrong.
//
//   node fix-template-in-quotes.mjs [--apply]
//
// fix-script-island-patterns.mjs turned
//
//     '<?php echo ocp_js_string(ocp_island_get($__ocp_data, "x")); ?>'
//
// into
//
//     '${PAGE_DATA.x ?? ''}'
//
// but that is template syntax sitting inside ordinary single quotes, so it prints
// literally instead of reading the island. It should be a plain read.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const SCRIPTS = path.join(ROOT, 'assets/js');

let total = 0;
for (const file of fs.readdirSync(SCRIPTS).filter(f => f.endsWith('.js.php')).sort()) {
  const full = path.join(SCRIPTS, file);
  const raw = fs.readFileSync(full, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');
  const before = code;

  // inside a template literal: '${X ?? ''}' -> ${X ?? ''}
  // inside ordinary quotes:        '${X ?? ''}' -> X || ''
  const lines = code.split('\n');
  let changed = 0;
  for (let i = 0; i < lines.length; i++) {
    if (!/\$\{[A-Z_][A-Z0-9_]*_DATA\.[A-Za-z0-9_]+ \?\? ''\}/.test(lines[i])) continue;
    const inTemplate = /`/.test(lines[i].slice(0, lines[i].search(/\$\{/)));
    lines[i] = lines[i].replace(/'(\$\{([A-Z_][A-Z0-9_]*_DATA)\.([A-Za-z0-9_]+) \?\? ''\})'/g,
      (m, whole, obj, key) => (inTemplate ? whole : `${obj}.${key} || ''`));
    changed++;
  }
  code = lines.join('\n');
  if (code !== before) {
    if (APPLY) fs.writeFileSync(full, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
    console.log(`  ${file}: ${changed} line(s)`);
    total += changed;
  }
}
console.log(`${total} line(s) ${APPLY ? 'repaired' : '(dry run)'}`);
