// Finds PHP reads of page-side state that are still inside the scripts.
//
//   node audit-script-page-state.mjs
//
// A script under assets/js/*.js.php is fetched as its own request. There it has no page
// scope, and any PHP that reads the page or the session answers null or empty - yet the
// answer is baked into the JavaScript it prints. On spare_parts.php that is what produced
// an alert showing nothing but its OK button: the script echoed $_SESSION['alert']['title']
// and friends, which the page had already consumed and unset.
//
// Covers the three shapes seen so far: ocp_island_get($__ocp_data, ...), a bare
// $__ocp_data, and a direct $_SESSION / $_POST / $_GET read.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const dir = path.join(ROOT, 'assets/js');
const findings = [];

const isSetup = (line) => /^\s*(\*|\/\*|\/\/)/.test(line)
  || /^\s*(if \(!isset\(\$__ocp_data\)\)|\$__ocp_data = \[\];|require_once|function ocp_island_get)/.test(line)
  || /^\s*(session_start\(\)|ini_set\(|error_reporting\(|header\('Content-Type: application\/javascript)/.test(line)
  || /^\s*\$GLOBALS\['OCP_SCRIPT_STANDALONE'\]/.test(line)
  || /^\s*(if \(session_status\(\)|session_id\()/.test(line);

for (const file of fs.readdirSync(dir).filter(f => f.endsWith('.js.php')).sort()) {
  const lines = fs.readFileSync(path.join(dir, file), 'utf8').split(/\r?\n/);
  lines.forEach((line, i) => {
    if (isSetup(line)) return;
    const hits = [];
    if (/ocp_island_get\s*\(/.test(line)) hits.push('ocp_island_get');
    else if (/\$__ocp_data/.test(line)) hits.push('$__ocp_data');
    if (/\$_SESSION\s*\[/.test(line)) hits.push('$_SESSION');
    if (/\$_POST\s*\[/.test(line)) hits.push('$_POST');
    if (/\$_GET\s*\[/.test(line)) hits.push('$_GET');
    if (/\$_SERVER\s*\[/.test(line) && !/\$_SERVER\['SCRIPT_FILENAME'\]/.test(line)) hits.push('$_SERVER');
    if (hits.length) findings.push({ file, line: i + 1, what: hits.join('+'), text: line.trim().slice(0, 96) });
  });
}

if (!findings.length) {
  console.log('OK: no script reads page or session state through PHP');
} else {
  console.log(`${findings.length} PHP read(s) of page state left in scripts:`);
  for (const f of findings) {
    console.log(`  ${f.file.padEnd(32)} ${String(f.line).padStart(5)}  [${f.what}]  ${f.text}`);
  }
}
