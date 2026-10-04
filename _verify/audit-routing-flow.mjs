// Checks the routing flows end to end: every move must land inside its flow, and no request
// may sit on a stage its own flow does not contain.
//
//   node audit-routing-flow.mjs
//
// The flows are declared per document_type in pr_view_routing.php and the moves between them
// are hard-coded in actions/pr_view_routing-actions.php. This reads each move from its
// `$next_stage = '...'` assignment together with the document_type branch it sits in, then:
//   - checks that move against that flow
//   - checks every request in the database against its own flow, so one left on an
//     impossible stage by an older version is reported rather than going unnoticed
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const ROOT = 'E:\\laragon\\www\\OCP';
const actions = fs.readFileSync(path.join(ROOT, 'actions', 'pr_view_routing-actions.php'), 'utf8').split(/\r?\n/);
const page = fs.readFileSync(path.join(ROOT, 'pr_view_routing.php'), 'utf8');

// ---- the flows the page declares; the trailing else-branch is the supplier flow
const flows = {};
for (const m of page.matchAll(/if \(\$document_type === '(\w+)'\) \{[\s\S]*?\$stages = \[([\s\S]*?)\];/g)) {
  flows[m[1]] = [...m[2].matchAll(/'(\w+)' => \['label'/g)].map(x => x[1]);
}
const em = /\} else \{\s*\/\/ Supplier request\s*\$stages = \[([\s\S]*?)\];/.exec(page);
if (em) flows.supplier = [...em[1].matchAll(/'(\w+)' => \['label'/g)].map(x => x[1]);

// ---- the moves, each with the document_type branch enclosing it
const moves = [];
let current = null;
let caseStart = 0;
for (let i = 0; i < actions.length; i++) {
  const cm = /case '([a-z_]+)':/.exec(actions[i]);
  if (cm) { current = cm[1]; caseStart = i; }
  if (!current) continue;
  const nm = /\$next_stage\s*=\s*'(\w+)'/.exec(actions[i]);
  if (!nm) continue;
  let docType = null;
  for (let j = i; j >= caseStart; j--) {
    const dm = /(?:if|elseif) \(\$document_type === '(\w+)'\)/.exec(actions[j]);
    if (dm) { docType = dm[1]; break; }
  }
  moves.push({ action: current, docType, to: nm[1] });
}

const problems = [];

// The walk below prints a line per move and per request, which is long, so the answer goes
// first. Printing only the detail made the suite summary read as a bare label rather than a
// verdict.
const moveProblems = moves.filter(mv => mv.docType && flows[mv.docType] && !flows[mv.docType].includes(mv.to));
console.log(moveProblems.length
  ? `${moveProblems.length} of ${moves.length} move(s) land outside their flow`
  : `OK: all ${moves.length} moves land inside their flow`);
console.log('');
console.log('  flows declared by the page:');
for (const [name, list] of Object.entries(flows)) {
  console.log(`    ${name.padEnd(10)} ${String(list.length).padStart(2)} stages: ${list.join(' -> ')}`);
}

console.log('\n  moves, and the flow each must land in:');
for (const mv of moves) {
  const flow = mv.docType && flows[mv.docType] ? flows[mv.docType] : null;
  const ok = !flow || flow.includes(mv.to);
  const label = `${mv.action}${mv.docType ? ' [' + mv.docType + ']' : ''}`;
  console.log(`    ${label.padEnd(38)} -> ${mv.to.padEnd(24)} ${flow ? (ok ? 'in its flow' : 'NOT IN ITS FLOW') : 'shared by every flow'}`);
  if (flow && !ok) problems.push(`${mv.action} moves a ${mv.docType} request to "${mv.to}", which its flow does not contain`);
}

// ---- every request must sit on a stage of its own flow
const rows = (sql) => execFileSync('php', [path.join(ROOT, '_verify', 'rows.php'), sql], { encoding: 'utf8' })
  .split('\n').map(l => l.trim()).filter(l => l.startsWith('{')).map(l => JSON.parse(l));

const requests = rows(`
  SELECT pr.pr_number, pr.request_type,
         COALESCE(NULLIF(pr.document_type, ''), 'pr_po') AS doc_type,
         COALESCE((SELECT stage FROM pr_routing r WHERE r.pr_id = pr.id ORDER BY r.created_at DESC, r.id DESC LIMIT 1), 'requestor') AS stage
  FROM purchase_requests pr`);

const offFlow = [];
for (const r of requests) {
  const flowName = r.request_type === 'project' ? r.doc_type : 'supplier';
  const flow = flows[flowName] || [];
  // "rejected" is an end state no flow lists as a step
  if (flow.length && !flow.includes(r.stage) && r.stage !== 'rejected') {
    offFlow.push(`${r.pr_number} (${flowName}) sits on "${r.stage}", which its flow does not contain`);
  }
}
console.log(`\n  requests checked: ${requests.length}`);
if (offFlow.length) {
  console.log(`  ${offFlow.length} on a stage their flow does not contain:`);
  for (const o of offFlow.slice(0, 10)) console.log(`    ${o}`);
  if (offFlow.length > 10) console.log(`    ... and ${offFlow.length - 10} more`);
  problems.push(...offFlow);
} else {
  console.log('  every request sits on a stage of its own flow');
}

if (!problems.length) {
  console.log('\nOK: every move lands inside its flow, and every request is on a stage of its flow');
} else {
  console.log(`\n${problems.length} problem(s)`);
}
process.exit(problems.length ? 1 : 0);

