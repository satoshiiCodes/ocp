// Finds the island read that is still done in PHP.
//
//   node audit-script-island-reads.mjs
//
// A script under assets/js/*.js.php is fetched as its own request, where $__ocp_data does
// not exist. So `ocp_island_get($__ocp_data, "k")` always answers null there and every
// value read that way is dead. The value has to be published in the island and read in
// the browser, e.g. const D = window.OCP_PAGE_X || {}; D.k.
//
// The earlier echo audit looked for island reads used as OUTPUT (markup, messages). This
// one looks for the reads themselves, including the ones assigned to a variable at the
// top of a script - which is how purchase_request.js lost its whole item list.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const dir = path.join(ROOT, 'assets/js');
const findings = [];

for (const file of fs.readdirSync(dir).filter(f => f.endsWith('.js.php')).sort()) {
  const src = fs.readFileSync(path.join(dir, file), 'utf8');
  const lines = src.split(/\r?\n/);
  lines.forEach((line, i) => {
    if (!/ocp_island_get\s*\(/.test(line) && !/\$__ocp_data/.test(line)) return;
    // the header comment, the bootstrap, and the helper's own definition are all fine
    if (/^\s*(\*|\/\*|\/\/)/.test(line)) return;
    if (/^\s*(if \(!isset\(\$__ocp_data\)\)|require_once|function ocp_island_get|\$__ocp_data = \[\];)/.test(line)) return;
    if (/^\s*\$GLOBALS\['OCP_SCRIPT_STANDALONE'\]/.test(line)) return;
    if (/^\s*ini_set\(|^\s*error_reporting\(|^\s*header\('Content-Type: application\/javascript/.test(line)) return;
    findings.push({ file, line: i + 1, text: line.trim().slice(0, 100) });
  });
}

const slug = (f) => f.replace(/\.js\.php$/, '');
if (!findings.length) {
  console.log('OK: no script reads the island through PHP');
} else {
  console.log(`${findings.length} PHP island read(s) left in scripts - each is null at runtime:`);
  for (const f of findings) console.log(`  ${f.file.padEnd(34)} ${String(f.line).padStart(5)}  ${f.text}`);
}
