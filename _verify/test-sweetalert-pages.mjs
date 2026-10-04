// Verifies the SweetAlert behaviour the user reported, page by page.
//
//   node test-sweetalert-pages.mjs
//
//   items_categories / spare_parts_categories : an add shows a success alert
//   warehouses                                : an add shows a success alert
//   vehicles                                  : opening the page does NOT
//   spare_parts                               : opening the page does NOT
//
// A row added for the test is removed again.
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PHP_PORT = '8356', CDP_PORT = '9229';
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const profile = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'ocp-cdp-swal');
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const db = (s) => php('query.php', [s]).trim();
const SID = JSON.parse(php('sessions.php'))[0].sid;

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
  try { target = (await (await fetch(`http://127.0.0.1:${CDP_PORT}/json/list`)).json()).find(x => x.type === 'page'); } catch { /* wait */ }
}
if (!target) { console.log('  no Chrome'); chrome.kill(); app.kill(); process.exit(1); }

const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
let n = 1; const pend = new Map(); const errors = [];
ws.onmessage = (e) => {
  const m = JSON.parse(e.data);
  if (m.id && pend.has(m.id)) { pend.get(m.id)(m); pend.delete(m.id); return; }
  if (m.method === 'Runtime.exceptionThrown') errors.push((m.params.exceptionDetails.text + ' ' + (m.params.exceptionDetails.exception?.description || '')).replace(/\s+/g, ' ').slice(0, 160));
};
const send = (method, params = {}) => new Promise((r) => { const i = n++; pend.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
const ev = async (x) => (await send('Runtime.evaluate', { expression: x, returnByValue: true, awaitPromise: true })).result?.result?.value;

// The alert fires on DOMContentLoaded, so the hook has to be installed before the document
// runs - installing it after navigation misses it entirely.
await send('Page.addScriptToEvaluateOnNewDocument', {
  source: `
    window.__alerts = [];
    Object.defineProperty(window, '__hookInstalled', { value: false, writable: true });
    const install = () => {
      if (window.Swal && !window.__hookInstalled) {
        const orig = window.Swal.fire.bind(window.Swal);
        window.Swal.fire = function (a) { window.__alerts.push(JSON.stringify(a).slice(0, 140)); return orig(a); };
        window.__hookInstalled = true;
      }
    };
    install();
    document.addEventListener('DOMContentLoaded', install);
    setInterval(install, 50);
  `,
});

// Wait for the condition rather than sleeping a fixed time, which made this flaky: the page
// shows its alert from DOMContentLoaded and how long that takes varies. `quiet` inverts it -
// the full window is waited out, because "no alert" is only meaningful once the page has
// settled.
const visit = async (page, quiet = false) => {
  errors.length = 0;
  await send('Page.navigate', { url: `http://127.0.0.1:${PHP_PORT}/${page}` });
  const deadline = Date.now() + (quiet ? 5000 : 12000);
  while (Date.now() < deadline) {
    await sleep(400);
    const got = await ev('window.__alerts || []');
    if (!quiet && Array.isArray(got) && got.length) return got;
  }
  return ev('window.__alerts || []');
};

await send('Runtime.enable'); await send('Page.enable');
await send('Network.setCookie', { name: 'PHPSESSID', value: SID, domain: '127.0.0.1', path: '/' });

// ------------------------------------------------ opening a page must be quiet
for (const page of ['vehicles.php', 'spare_parts.php']) {
  const alerts = await visit(page, true);
  const quiet = Array.isArray(alerts) && alerts.length === 0;
  log.push(`${page.padEnd(24)} opens with ${quiet ? 'no alert' : `an alert: ${JSON.stringify(alerts)}`}`);
  if (!quiet) problems.push(`${page}: an alert appeared on a plain load`);
  if (errors.length) problems.push(`${page}: ${errors[0]}`);
  // and a second visit must stay quiet too (the alert must not linger)
  const again = await visit(page, true);
  if (Array.isArray(again) && again.length) problems.push(`${page}: the alert came back on a later load`);
}

// ------------------------------------------------------------ an add must alert
const adds = [
  {
    page: 'items_categories.php', table: 'items_categories', column: 'category_name',
    marker: `Verify Cat ${Date.now()}`, url: 'items_categories.php',
  },
  {
    page: 'spare_parts_categories.php', table: 'spare_parts_categories', column: 'category_name',
    marker: `Verify SPCat ${Date.now()}`, url: 'spare_parts_categories.php',
  },
  {
    page: 'warehouses.php', table: 'warehouses', column: 'warehouse_name',
    marker: `Verify WH ${Date.now()}`, url: 'warehouses.php',
  },
];

for (const c of adds) {
  const before = Number(db(`SELECT COUNT(*) FROM ${c.table}`));
  // post the add form the way the page does, then load the page it lands on
  const body = new URLSearchParams();
  // The add branch is the POST that carries neither delete_id nor edit_id, so the
  // fields below are the whole submission.
  if (c.table === 'warehouses') {
    body.append('warehouse_name', c.marker);
    body.append('location', 'Verify Location');
    body.append('capacity', '1');
    body.append('manager', '');
    body.append('phone', '');
  } else {
    body.append(c.column, c.marker);
    body.append('description', 'verify');
  }
  const res = await fetch(`http://127.0.0.1:${PHP_PORT}/${c.url}`, {
    method: 'POST', body, headers: { Cookie: `PHPSESSID=${SID}` }, redirect: 'manual',
  });
  await sleep(400);

  const alerts = await visit(c.url);
  const ok = Array.isArray(alerts) && alerts.some(a => /"icon":"success"/.test(a));
  log.push(`${c.page.padEnd(24)} after an add: ${ok ? 'success alert shown' : `NO success alert (${JSON.stringify(alerts)})`}`);
  if (!ok) problems.push(`${c.page}: adding produced no success alert`);

  const after = Number(db(`SELECT COUNT(*) FROM ${c.table}`));
  if (after !== before + 1) problems.push(`${c.table}: the test row was not added (${before} -> ${after})`);
  db(`DELETE FROM ${c.table} WHERE ${c.column} = '${c.marker.replace(/'/g, "''")}'`);
  const cleaned = Number(db(`SELECT COUNT(*) FROM ${c.table}`));
  if (cleaned !== before) problems.push(`${c.table}: cleanup left a row (${before} -> ${cleaned})`);
}

ws.close(); chrome.kill(); app.kill();

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);