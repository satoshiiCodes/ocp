// Inserts the standard standalone guard at the top of the dashboard endpoint.
//
// The endpoint reads $pdo and the role flags from the page's scope. Requested on its
// own there is no page, so the block would run with nothing and print a PHP error.
// Like every other per-page endpoint, it answers with its empty result instead.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\api\\dashboard-endpoint.php`;
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

const MARK = '// Standalone guard: see the note above.';
if (code.includes(MARK)) {
  console.log('already guarded');
  process.exit(0);
}

const anchor = /^(\s*\/\/ Initialize all variables with default values)$/m;
const m = anchor.exec(code);
if (!m) {
  console.error('ABORT: could not find where the block begins');
  process.exit(1);
}

const guard = `// Standalone guard: see the note above. Requested on its own there is no page, no
// connection and no role flags, so it answers with the empty result rather than
// running the block below against nothing.
if (!isset($pdo) || !isset($is_motorpool)) {
    return [
${[...code.matchAll(/^    '([a-z_]+)' => \$/gm)].map(x => `        '${x[1]}' => null,`).join('\n')}
    ];
}

`;

code = code.slice(0, m.index) + guard + code.slice(m.index);

// document it in the header
code = code.replace(
  / \* page's fetching is in one place\./,
  ` * page's fetching is in one place.\n *\n * Requested on its own, with no page behind it, there is no connection and no role\n * flags: the guard below then returns the empty result instead of erroring.`
);

const guarded = [...code.matchAll(/^        '([a-z_]+)' => null,$/gm)].length;
console.log(`standalone guard covers ${guarded} keys`);
if (guarded < 30) {
  console.error('ABORT: the guard looks too small; the return list may have been mis-parsed');
  process.exit(1);
}

if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
