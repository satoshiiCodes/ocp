// Splices one page's inline controller out, replacing it with a require of the
// page's actions file and a require of its endpoint file.
//
//   node splice-page.mjs <page> <fromLine> <toLine> [--apply]
//
// It refuses to touch the page unless line `fromLine` starts with the expected
// comment and line `toLine` is a lone closing brace, so a wrong range fails
// loudly instead of corrupting the file.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const args = process.argv.slice(2).filter(a => a !== '--apply');
const [page, fromArg, toArg] = args;

if (!page || !fromArg || !toArg) {
  console.error('usage: node splice-page.mjs <page> <fromLine> <toLine> [--apply]');
  process.exit(2);
}

const ROOT = 'E:\\laragon\\www\\OCP';
const file = `${ROOT}\\${page}.php`;
const lines = fs.readFileSync(file, 'utf8').split('\n');
const FROM = Number(fromArg);
const TO = Number(toArg);
const upper = page.toUpperCase().replace(/[^A-Z0-9]/g, '_');

const first = lines[FROM - 1] ?? '';
const last = lines[TO - 1] ?? '';

if (last.trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(last)}`);
  process.exit(1);
}
// The start line must be something a controller block can begin with, so a wrong
// range fails loudly instead of cutting into the markup.
if (!/^\s*(\/\/|\/\*|\$|if\s*\(|foreach\s*\(|while\s*\(|\?>)/.test(first)) {
  console.error(`ABORT: line ${FROM} is not a comment, assignment or control statement: ${JSON.stringify(first)}`);
  process.exit(1);
}

const replacement = [
  `// All of this page's actions live in one file. The page's forms post back here,`,
  `// so it is pulled in before anything is read or rendered.`,
  `if (!defined('OCP_${upper}_ACTIONS_RAN')) {`,
  `    require __DIR__ . '/actions/${page}-actions.php';`,
  `}`,
  ``,
  `// All of this page's fetching lives in one file: it returns the variables the`,
  `// markup below needs, which are unpacked into this scope.`,
  `$ocp_endpoint = require __DIR__ . '/api/${page}-endpoint.php';`,
  `foreach ($ocp_endpoint as $ocp_key => $ocp_value) {`,
  `    \${$ocp_key} = $ocp_value;`,
  `}`,
  `unset($ocp_endpoint, $ocp_key, $ocp_value);`,
];

const out = [...lines.slice(0, FROM - 1), ...replacement, ...lines.slice(TO)];
console.log(`${page}: replacing lines ${FROM}-${TO} (${TO - FROM + 1} lines) with ${replacement.length}`);
console.log(`  total: ${lines.length} -> ${out.length} lines`);

if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('  APPLIED');
} else {
  console.log('  DRY RUN');
}
