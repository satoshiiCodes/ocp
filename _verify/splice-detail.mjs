// A reusable splicer for the "detail page" shape:
//
//   node splice-detail.mjs <page> <fromLine> <toLine> <ACTIONS_CONST> [--apply]
//
// Lines fromLine..toLine (the inline controller: a redirect guard plus the page's
// read queries) become a require of the page's actions file and a require of its
// endpoint file. Both ranges are verified before anything is written, so a wrong
// range fails loudly instead of cutting into the markup.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const args = process.argv.slice(2).filter(a => a !== '--apply');
const [page, fromArg, toArg, guardConst] = args;

if (!page || !fromArg || !toArg || !guardConst) {
  console.error('usage: node splice-detail.mjs <page> <fromLine> <toLine> <GUARD_CONST> [--apply]');
  process.exit(2);
}

const ROOT = 'E:\\laragon\\www\\OCP';
const file = `${ROOT}\\${page}.php`;
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
const lines = raw.replace(/\r\n/g, '\n').split('\n');

const FROM = Number(fromArg);
const TO = Number(toArg);

const at = (n) => lines[n - 1] ?? '';
if (!/^\s*\/\//.test(at(FROM))) {
  console.error(`ABORT: line ${FROM} is not a comment: ${JSON.stringify(at(FROM))}`);
  process.exit(1);
}
if (at(TO).trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(at(TO))}`);
  process.exit(1);
}
if (!/^\?>/.test(at(TO + 1))) {
  console.error(`ABORT: line ${TO + 1} is not the closing PHP tag: ${JSON.stringify(at(TO + 1))}`);
  process.exit(1);
}

const block = [
  `// All of this page's actions live in one file. This page has no forms of its own:`,
  `// it is opened with the record to show, and without one the actions file sends the`,
  `// request back to the list.`,
  `if (!defined('${guardConst}')) {`,
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

const out = [...lines.slice(0, FROM - 1), ...block, ...lines.slice(TO)];
console.log(`${page}.php: ${lines.length} -> ${out.length} lines (replaced ${FROM}-${TO})`);
if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? out.join('\r\n') : out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
