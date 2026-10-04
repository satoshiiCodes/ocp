// Proves the routing action works for the user who raised the request, on both routing
// pages, and leaves the data exactly as it found it.
//
//   node test-routing-action.mjs
//
// Before the fix, POSTing forward_to_warehouse as the requestor answered
// "Failed to process action: You are not authorized to perform this action." with a PHP
// warning naming $current_stage - the variable the authorization reads, which only the
// page's endpoint assigned, and the page loads the endpoint after the actions file.
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const one = (s) => php('query.php', [s]).trim();
const rows = (s) => php('rows.php', [s]).split('\n').map(l => l.trim()).filter(l => l.startsWith('{')).map(l => JSON.parse(l));

const problems = [];
const log = [];

const cases = [
  {
    label: 'pr_view_routing.php',
    page: 'pr_view_routing.php',
    requestTable: 'purchase_requests',
    routingTable: 'pr_routing',
    historyTable: 'pr_routing_history',
    stageColumn: 'pr_id',
  },
  {
    label: 'pr_spare_view_routing.php',
    page: 'pr_spare_view_routing.php',
    requestTable: 'spare_parts_pr',
    routingTable: 'spare_parts_pr_routing',
    historyTable: 'spare_parts_pr_routing_history',
    stageColumn: 'pr_id',
  },
];

for (const c of cases) {
  const target = rows(`SELECT p.id, p.pr_number, p.requested_by, p.status
                       FROM ${c.requestTable} p
                       LEFT JOIN ${c.routingTable} r ON r.${c.stageColumn} = p.id
                       WHERE r.id IS NULL AND p.status = 'pending'
                       ORDER BY p.id DESC LIMIT 1`)[0];
  if (!target) { log.push(`${c.label}: no unrouted pending request to test with`); continue; }

  const before = {
    status: one(`SELECT status FROM ${c.requestTable} WHERE id = ${target.id}`),
    routing: Number(one(`SELECT COUNT(*) FROM ${c.routingTable} WHERE ${c.stageColumn} = ${target.id}`)),
    history: Number(one(`SELECT COUNT(*) FROM ${c.historyTable} WHERE ${c.stageColumn} = ${target.id}`)),
  };

  // a session belonging to the user who raised it
  const sid = execFileSync('php', [path.join(ROOT, '_verify', 'make-session.php'), String(target.requested_by)], { encoding: 'utf8' }).trim();
  if (!sid) { problems.push(`${c.label}: could not open a session for user ${target.requested_by}`); continue; }

  const phpPort = c.page.includes('spare') ? '8472' : '8471';
  const { spawn } = await import('node:child_process');
  const server = spawn('php', ['-S', `127.0.0.1:${phpPort}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 1600));

  const body = new URLSearchParams({ action: 'forward_to_warehouse', remarks: 'routing test' });
  const res = await fetch(`http://127.0.0.1:${phpPort}/${c.page}?id=${target.id}`, {
    method: 'POST', body, headers: { Cookie: `PHPSESSID=${sid}` }, redirect: 'manual',
  });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  server.kill();

  // the failure this test exists to catch
  const refused = /not authorized to perform this action/i.test(text);
  const warned = /Undefined variable \$(current_stage|pr)\b/.test(text);
  if (refused) problems.push(`${c.label}: the requestor was refused - the original bug`);
  if (warned) problems.push(`${c.label}: ${(/Undefined variable \$\w+/.exec(text) || [''])[0]}`);

  const after = {
    status: one(`SELECT status FROM ${c.requestTable} WHERE id = ${target.id}`),
    routing: Number(one(`SELECT COUNT(*) FROM ${c.routingTable} WHERE ${c.stageColumn} = ${target.id}`)),
    history: Number(one(`SELECT COUNT(*) FROM ${c.historyTable} WHERE ${c.stageColumn} = ${target.id}`)),
  };

  const routed = after.routing === before.routing + 1 && after.history === before.history + 1 && after.status === 'processing';
  log.push(`${c.label}  ${target.pr_number} as user ${target.requested_by}: `
    + (routed
      ? `routed (status ${before.status} -> ${after.status}, routing +${after.routing - before.routing}, history +${after.history - before.history})`
      : `NOT routed (${JSON.stringify(before)} -> ${JSON.stringify(after)})`));
  if (!routed && !refused) problems.push(`${c.label}: the action did not route the request`);

  // put it back exactly as it was
  one(`DELETE FROM ${c.routingTable} WHERE ${c.stageColumn} = ${target.id} AND remarks = 'routing test'`);
  one(`DELETE FROM ${c.historyTable} WHERE ${c.stageColumn} = ${target.id} AND remarks = 'routing test'`);
  one(`UPDATE ${c.requestTable} SET status = '${before.status.replace(/'/g, "''")}' WHERE id = ${target.id}`);

  const restored = one(`SELECT status FROM ${c.requestTable} WHERE id = ${target.id}`) === before.status
    && Number(one(`SELECT COUNT(*) FROM ${c.routingTable} WHERE ${c.stageColumn} = ${target.id}`)) === before.routing
    && Number(one(`SELECT COUNT(*) FROM ${c.historyTable} WHERE ${c.stageColumn} = ${target.id}`)) === before.history;
  if (!restored) problems.push(`${c.label}: the request was not restored`);
  else log.push(`${' '.repeat(c.label.length)}  restored to ${JSON.stringify(before)}`);
}

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
