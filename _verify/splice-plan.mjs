// Plan-driven page splicer: builds the whole file in ONE ordered pass, so ranges
// never shift, and replaces the page's controller with the actions + endpoint
// requires.
//
//   node splice-plan.mjs <planFile.json> [--apply]
//
// The plan is an ordered list of steps. Each step is one of:
//   { "keep": [from, to], "expectFirst": "...", "expectLast": "..." }
//       copy those original lines through unchanged (the expectations are checked)
//   { "block": [ "line", "line", ... ] }
//       emit these lines instead
//   { "requires": true }
//       emit the standard actions + endpoint require block for the plan's page
//
// Every range is validated before a single byte is written, and any step that
// fails aborts without touching the file.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const planFile = process.argv.slice(2).find(a => !a.startsWith('--'));

const plan = JSON.parse(fs.readFileSync(planFile, 'utf8'));
const page = plan.page;
const guard = plan.guard || `OCP_${page.toUpperCase().replace(/[^A-Z0-9]/g, '_')}_ACTIONS_RAN`;

const file = `${ROOT}\\${page}.php`;
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
const lines = raw.replace(/\r\n/g, '\n').split('\n');

const at = (n) => lines[n - 1] ?? '';

// validate every step first
let covered = 0;
for (const [i, step] of plan.steps.entries()) {
  if (!step.keep) continue;
  const [a, b] = step.keep;
  if (!(a >= 1 && b <= lines.length && a <= b)) {
    console.error(`ABORT step ${i}: keep range ${a}-${b} is out of bounds (1..${lines.length})`);
    process.exit(1);
  }
  if (a <= covered) {
    console.error(`ABORT step ${i}: keep range ${a}-${b} overlaps or is out of order (already covered to ${covered})`);
    process.exit(1);
  }
  covered = b;
  if (step.expectFirst && !new RegExp(step.expectFirst).test(at(a))) {
    console.error(`ABORT step ${i}: line ${a} is not ${step.expectFirst}\n  got: ${JSON.stringify(at(a))}`);
    process.exit(1);
  }
  if (step.expectLast && !new RegExp(step.expectLast).test(at(b))) {
    console.error(`ABORT step ${i}: line ${b} is not ${step.expectLast}\n  got: ${JSON.stringify(at(b))}`);
    process.exit(1);
  }
  if (step.expectEmptyFirst && at(a).trim() !== '') {
    console.error(`ABORT step ${i}: line ${a} is not blank: ${JSON.stringify(at(a))}`);
    process.exit(1);
  }
}

const requireBlock = () => [
  `// All of this page's actions live in one file${plan.actionsNote || ''}.`,
  ...(plan.actionsNote2 ? [`// ${plan.actionsNote2}`] : []),
  `if (!defined('${guard}')) {`,
  `    require __DIR__ . '/actions/${page.replace(/\.php$/, '')}-actions.php';`,
  `}`,
  ``,
  `// All of this page's fetching lives in one file: it returns the variables the`,
  `// markup below needs, which are unpacked into this scope.`,
  `$ocp_endpoint = require __DIR__ . '/api/${page.replace(/\.php$/, '')}-endpoint.php';`,
  `foreach ($ocp_endpoint as $ocp_key => $ocp_value) {`,
  `    \${$ocp_key} = $ocp_value;`,
  `}`,
  `unset($ocp_endpoint, $ocp_key, $ocp_value);`,
];

const out = [];
for (const [i, step] of plan.steps.entries()) {
  if (step.keep) {
    for (let n = step.keep[0]; n <= step.keep[1]; n++) out.push(at(n));
  } else if (step.block) {
    out.push(...step.block);
  } else if (step.requires) {
    out.push(...requireBlock());
  } else {
    console.error(`ABORT step ${i}: neither keep, block nor requires`);
    process.exit(1);
  }
}

const coveredTo = plan.steps.filter(s => s.keep).reduce((m, s) => Math.max(m, s.keep[1]), 0);
if (coveredTo !== lines.length) {
  console.error(`ABORT: the plan covers up to line ${coveredTo} but the file has ${lines.length}`);
  process.exit(1);
}

console.log(`${page}.php: ${lines.length} -> ${out.length} lines`);
if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? out.join('\r\n') : out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
