// One-off fixup for the server-rendered scripts (assets/js/*.js.php).
//
//   1. island reads go through ocp_island_get() so a missing member answers null
//      instead of emitting a notice into the JavaScript
//   2. the bootstrap marks a direct request, silences notices and sends the
//      JavaScript content type, so a standalone hit returns valid empty JS
//
//   node patch-php-js.mjs           dry run
//   node patch-php-js.mjs --apply   write the changes
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const DIR = path.join(ROOT, 'assets/js');

const OLD_BLOCK = [
  '/*',
  ' * When the page printed this file, $__ocp_data is already the data island.',
  ' * A direct request (cache miss) gets an empty island and the script degrades',
  ' * quietly instead of failing on an undefined variable.',
  ' */',
  'if (!isset($__ocp_data)) {',
  '    $__ocp_data = [];',
  "    if (isset($_SERVER['SCRIPT_FILENAME'])",
  "        && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {",
  "        header('Content-Type: application/javascript; charset=utf-8');",
  '    }',
  '}',
].join('\n');

const NEW_BLOCK = [
  '/*',
  ' * Printed as part of its page, $__ocp_data is already the data island and',
  ' * every page variable this script reads is in scope.',
  ' *',
  ' * Requested on its own (a direct hit or a cache miss) there is no page, no',
  ' * island and none of those variables, so notices are silenced, the island',
  ' * starts empty, and missing members answer null. The response still parses as',
  ' * JavaScript; it simply does nothing.',
  ' */',
  'if (!isset($__ocp_data)) {',
  '    $__ocp_data = [];',
  '    if (isset($_SERVER[\'SCRIPT_FILENAME\'])',
  '        && realpath($_SERVER[\'SCRIPT_FILENAME\']) === realpath(__FILE__)) {',
  '        $GLOBALS[\'OCP_SCRIPT_STANDALONE\'] = true;',
  '        header(\'Content-Type: application/javascript; charset=utf-8\');',
  '        ini_set(\'display_errors\', \'0\');',
  '        error_reporting(0);',
  '    }',
  '}',
].join('\n');

const files = fs.readdirSync(DIR).filter(f => f.endsWith('.js.php')).sort();
const report = { files: files.length, readsRewritten: 0, bootstrapsPatched: 0, problems: [], otherArgs: new Set() };

for (const f of files) {
  const p = path.join(DIR, f);
  let code = fs.readFileSync(p, 'utf8');
  const before = code;

  // 1. island reads: wrap the subscript so a missing key is handled centrally
  const readRe = /(ocp_js_(?:raw|string)\()\s*\$__ocp_data\[("(?:[^"\\]|\\.)*")\]\s*\)/g;
  code = code.replace(readRe, (m, call, key) => {
    report.readsRewritten++;
    return `${call}ocp_island_get($__ocp_data, ${key}))`;
  });

  // 2. bootstrap
  if (code.includes(OLD_BLOCK)) {
    code = code.replace(OLD_BLOCK, NEW_BLOCK);
    report.bootstrapsPatched++;
  } else if (!code.includes("OCP_SCRIPT_STANDALONE")) {
    report.problems.push(`${f}: bootstrap pattern not found`);
  }

  // Every call must now either read through ocp_island_get() or pass a value
  // that is not an island subscript (a plain variable, a captured fragment).
  for (const m of code.matchAll(/ocp_js_(?:raw|string)\(([^\n]{0,70})/g)) {
    const arg = m[1];
    if (/ocp_island_get\(/.test(arg)) continue;
    if (/\$__ocp_data\[/.test(arg)) report.problems.push(`${f}: island read not rewritten -> ${arg.trim()}`);
    else report.otherArgs.add(arg.trim().slice(0, 40));
  }
  if (code !== before) {
    if (APPLY) fs.writeFileSync(p, code);
  }
}

console.log(`${APPLY ? 'APPLIED' : 'DRY RUN'}`);
console.log(`  server-rendered scripts    : ${report.files}`);
console.log(`  island reads rewritten     : ${report.readsRewritten}`);
console.log(`  bootstraps patched         : ${report.bootstrapsPatched}`);
if (report.otherArgs.size) {
  console.log(`  other helper arguments (fine) : ${report.otherArgs.size}`);
  for (const a of [...report.otherArgs].slice(0, 6)) console.log(`    ${a}`);
}
if (report.problems.length) {
  console.log(`  problems: ${report.problems.length}`);
  for (const p of report.problems) console.log('    X ' + p);
}
