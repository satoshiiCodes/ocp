// Gives every api/<page>-endpoint.php a standalone guard.
//
// An endpoint runs in its page's scope, where $pdo and the session are already
// open. Requested directly there is no page, so it would raise "Undefined
// variable $pdo" and print a PHP error. This mirrors what the generated page
// scripts already do: with no page behind it, the endpoint answers with its empty
// result rather than an error, so a direct request stays harmless.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP\\api';
const APPLY = process.argv.includes('--apply');

const MARK = '// Standalone guard: see the note above.';

const guard = (name) => `
${MARK}
if (!isset($pdo)) {
    return $ocp_endpoint;
}
`;

const files = fs.readdirSync(ROOT).filter(f => f.endsWith('-endpoint.php')).sort();
let patched = 0;
let already = 0;

for (const f of files) {
  const file = path.join(ROOT, f);
  const raw = fs.readFileSync(file, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');

  if (code.includes(MARK)) { already++; continue; }

  // insert right after the $ocp_endpoint array literal's closing "];"
  const m = /\$ocp_endpoint = \[[\s\S]*?\n\];\n/.exec(code);
  if (!m) {
    console.error(`  ABORT: ${f}: could not find the $ocp_endpoint array`);
    process.exit(1);
  }
  const at = m.index + m[0].length;

  // the note also goes in the header, so the behaviour is documented
  code = code.slice(0, at) + guard(f) + code.slice(at);

  // document it in the file header
  code = code.replace(
    / \* is printed here, so this file cannot disturb the page's output\./,
    ` * is printed here, so this file cannot disturb the page's output.\n *\n * Requested on its own, with no page behind it, there is no connection and no\n * session: the guard below then returns the empty result instead of erroring.`
  );

  console.log(`  ${f}: guard added`);
  patched++;
  if (APPLY) fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
}

console.log(`patched ${patched}, already had one ${already}, of ${files.length}`);
console.log(APPLY ? 'APPLIED' : 'DRY RUN');
