// Generic one-pass page splicer.
//
//   node splice2.mjs <page> <plan.json> [--apply]
//
// The plan describes the regions in order; each is either kept from the original
// (by line range) or replaced by a named block. Building the whole file in one
// ordered pass means the ranges never shift.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const args = process.argv.slice(2).filter(a => a !== '--apply');
const [page, planFile] = args;
const ROOT = 'E:\\laragon\\www\\OCP';

const plan = JSON.parse(fs.readFileSync(planFile, 'utf8'));
const file = `${ROOT}\\${page}.php`;
const lines = fs.readFileSync(file, 'utf8').split('\n');

// sanity: every "keep" range must be valid, every "replace" range must start
// with the text the plan expects
for (const step of plan.steps) {
  if (step.keep) {
    const [a, b] = step.keep;
    if (a < 1 || b > lines.length || a > b) {
      console.error(`ABORT: keep range ${a}-${b} is out of bounds (1..${lines.length})`);
      process.exit(1);
    }
    if (step.expectFirst && !new RegExp(step.expectFirst).test(lines[a - 1])) {
      console.error(`ABORT: line ${a} is not ${step.expectFirst}: ${JSON.stringify(lines[a - 1])}`);
      process.exit(1);
    }
    if (step.expectLast && !new RegExp(step.expectLast).test(lines[b - 1])) {
      console.error(`ABORT: line ${b} is not ${step.expectLast}: ${JSON.stringify(lines[b - 1])}`);
      process.exit(1);
    }
  }
}

const out = [];
for (const step of plan.steps) {
  if (step.keep) {
    for (let i = step.keep[0]; i <= step.keep[1]; i++) out.push(lines[i - 1]);
  } else if (step.block) {
    out.push(...step.block);
  }
}

console.log(`${page}.php: ${lines.length} -> ${out.length} lines`);
if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
