// Proves the edit modals on item_names and expenses_type actually save.
//
//   node test-modal-edit-submit.mjs
//
// Both edit forms contain a hidden input named "action". A form control with that name
// shadows the form's own `action` property, so the page script's fetch(this.action) sent
// the input element instead of the URL: the request never left the browser and the user
// saw "An error occurred while updating the item". This drives a real browser, clicks the
// modal's own submit button, and requires (a) a request to the right URL, (b) success, and
// (c) the change persisted - then puts the row back.
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PHP_PORT = '8343';
const CDP_PORT = '9224';
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const profile = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'ocp-cdp-modal');
const sleep = (ms) => new Promise(r => setTimeout(r, ms));

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const db = (s) => php('query.php', [s]).trim();
const SID = JSON.parse(php('sessions.php'))[0].sid;

// The edit has to write a real value to prove the save works, so the rows it touches are
// snapshotted first and put back at the end - including when the test fails part way.
const SNAPSHOT = path.join(ROOT, '_verify', 'row-snapshot.json');
const snapshot = [];
const snap = (table, id) => {
  const row = JSON.parse(php('row.php', [table, String(id)]) || 'null');
  if (row) snapshot.push({ table, id: String(id), row });
};
const unsnap = () => {
  for (const e of snapshot) {
    const cols = Object.keys(e.row).filter(c => c !== 'id');
    const sets = cols.map(c => {
      const v = e.row[c];
      return v === null || v === undefined
        ? `\`${c}\` = NULL`
        : `\`${c}\` = '${String(v).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
    }).join(', ');
    db(`UPDATE \`${e.table}\` SET ${sets} WHERE id = ${Number(e.id)}`);
  }
  snapshot.length = 0;
};
process.on('exit', () => { try { unsnap(); } catch { /* best effort */ } });

const problems = [];
const log = [];

const app = spawn('php', ['-S', `127.0.0.1:${PHP_PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await sleep(1500);
fs.rmSync(profile, { recursive: true, force: true });
const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${CDP_PORT}`, `--user-data-dir=${profile}`,
  '--no-first-run', '--no-default-browser-check', '--disable-gpu', 'about:blank'], { stdio: 'ignore' });

let target = null;
for (let i = 0; i < 40 && !target; i++) {
  await sleep(400);
  try { target = (await (await fetch(`http://127.0.0.1:${CDP_PORT}/json/list`)).json()).find(t => t.type === 'page'); } catch { /* wait */ }
}
if (!target) { console.log('  no Chrome available'); chrome.kill(); app.kill(); process.exit(1); }

const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
let nextId = 1; const pending = new Map();
let seen = [];
ws.onmessage = (ev) => {
  const m = JSON.parse(ev.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); return; }
  if (m.method === 'Network.requestWillBeSent' && /-actions\.php/.test(m.params.request.url)) {
    seen.push({ url: m.params.request.url, method: m.params.request.method });
  }
};
const send = (method, params = {}) => new Promise((r) => { const n = nextId++; pending.set(n, r); ws.send(JSON.stringify({ id: n, method, params })); });
const ev = async (expression) => (await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result?.result?.value;

await send('Runtime.enable'); await send('Network.enable'); await send('Page.enable');
await send('Network.setCookie', { name: 'PHPSESSID', value: SID, domain: '127.0.0.1', path: '/' });

// ---------------------------------------------------------------- item_names
{
  // The modal's own id field says which row it is editing, so the assertion follows that
  // rather than assuming the first row of the table.
  const probeId = db('SELECT id FROM item_names ORDER BY id LIMIT 1');
  void probeId;
  const marker = `edited ${Date.now()}`;

  await send('Page.navigate', { url: `http://127.0.0.1:${PHP_PORT}/item_names.php` });
  await sleep(3000);

  const opened = await ev(`
    (() => {
      const btn = document.querySelector("[data-bs-target='#editItemModal']");
      if (!btn) return 'no edit button';
      btn.click();
      return 'clicked';
    })()
  `);
  await sleep(900);
  if (opened !== 'clicked') problems.push(`item_names: could not open the modal (${opened})`);

  // type a new name, then press the modal's own Update button
  await ev(`document.getElementById('edit_item_name').value = ${JSON.stringify(marker)}`);
  const typed = await ev("document.getElementById('edit_item_name').value");
  if (typed !== marker) problems.push(`item_names: could not type into the field (holds ${JSON.stringify(typed)})`);

  await ev(`
    window.__ocpAlerts = [];
    const orig = window.Swal.fire.bind(window.Swal);
    window.Swal.fire = function (a) { window.__ocpAlerts.push(JSON.stringify(a)); return orig(a); };
    'hooked'
  `);
  const id = await ev("document.getElementById('edit_item_id').value");
  snap('item_names', id);
  const was = db(`SELECT item_name FROM item_names WHERE id = ${id}`);
  seen = [];
  await ev("document.querySelector('#editItemForm button[type=submit]').click()");
  await sleep(1800);

  const alerts = await ev('window.__ocpAlerts');
  const saidSuccess = Array.isArray(alerts) && alerts.some(a => /"icon":"success"/.test(a));
  if (!saidSuccess) problems.push(`item_names: the page reported ${JSON.stringify(alerts)}`);

  if (!seen.length) problems.push('item_names: pressing Update sent no request at all');
  else if (!/item_names-actions\.php$/.test(seen[0].url)) problems.push(`item_names: the request went to ${seen[0].url}`);
  else log.push(`item_names: Update posted to ${seen[0].url.replace(`http://127.0.0.1:${PHP_PORT}`, '')}`);

  const now = db(`SELECT item_name FROM item_names WHERE id = ${id}`);
  if (now !== marker) problems.push(`item_names: row ${id} did not change (${was} -> ${now}, wanted ${marker})`);
  else log.push(`item_names: row ${id} saved ("${was}" -> "${marker}")`);

}

// ---------------------------------------------------------------- expenses_type
{
  const marker = `edited ${Date.now()}`;

  await send('Page.navigate', { url: `http://127.0.0.1:${PHP_PORT}/expenses_type.php` });
  await sleep(3000);

  const opened = await ev(`
    (() => {
      const btn = document.querySelector("[data-bs-target='#editExpenseModal']");
      if (!btn) return 'no edit button';
      btn.click();
      return 'clicked';
    })()
  `);
  await sleep(900);
  if (opened !== 'clicked') problems.push(`expenses_type: could not open the modal (${opened})`);

  await ev(`document.getElementById('edit_expense_name').value = ${JSON.stringify(marker)}`);
  await ev(`
    window.__ocpAlerts = [];
    const orig = window.Swal.fire.bind(window.Swal);
    window.Swal.fire = function (a) { window.__ocpAlerts.push(JSON.stringify(a)); return orig(a); };
    'hooked'
  `);
  const id = await ev("document.getElementById('edit_expense_id').value");
  snap('expenses_type', id);
  const was = db(`SELECT expense_name FROM expenses_type WHERE id = ${id}`);
  seen = [];
  await ev("document.querySelector('#editExpenseForm button[type=submit]').click()");
  await sleep(1800);

  const alerts = await ev('window.__ocpAlerts');
  if (!(Array.isArray(alerts) && alerts.some(a => /"icon":"success"/.test(a)))) {
    problems.push(`expenses_type: the page reported ${JSON.stringify(alerts)}`);
  }

  if (!seen.length) problems.push('expenses_type: pressing Update sent no request at all');
  else if (!/expenses_type-actions\.php$/.test(seen[0].url)) problems.push(`expenses_type: the request went to ${seen[0].url}`);
  else log.push(`expenses_type: Update posted to ${seen[0].url.replace(`http://127.0.0.1:${PHP_PORT}`, '')}`);

  const now = db(`SELECT expense_name FROM expenses_type WHERE id = ${id}`);
  if (now !== marker) problems.push(`expenses_type: row ${id} did not change (${was} -> ${now}, wanted ${marker})`);
  else log.push(`expenses_type: row ${id} saved ("${was}" -> "${marker}")`);

}

// ---------------------------------------------------------------- restored?
{
  const a = db('SELECT item_name FROM item_names ORDER BY id LIMIT 1');
  const b = db('SELECT expense_name FROM expenses_type ORDER BY id LIMIT 1');
  log.push(`restored: item_names "${a}", expenses_type "${b}"`);
}

ws.close(); chrome.kill(); app.kill();

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
