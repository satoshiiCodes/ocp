// Verifies the Excel upload path on upload_attendance.php end to end.
//
//   node test-upload-attendance-excel.mjs
//
// The reported failure was a fatal error on any spreadsheet upload:
//     Fatal error: Uncaught Error: Class "IOFactory" not found
//                in includes/upload_attendance-functions.php:257
// because the `use PhpOffice\PhpSpreadsheet\IOFactory;` that upload_attendance.php had was
// not carried into the extracted helper.
//
// The file uploaded here names employees that do not exist (ids 99999, 99998), and
// saveAttendanceToDatabase() skips those with a `continue`, so the upload writes nothing -
// the test asserts that by comparing row counts.
import { withBrowser, db, php } from './browser.mjs';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const problems = [];
const log = [];

// build the spreadsheet with the app's own writer
const xlsx = path.join(os.tmpdir(), 'ocp-verify-attendance.xlsx');
execFileSync('php', [path.join('E:\\laragon\\www\\OCP', '_verify', 'make-xlsx.php'), xlsx], { encoding: 'utf8' });
if (!fs.existsSync(xlsx)) { console.log('  could not build the test spreadsheet'); process.exit(1); }
log.push(`spreadsheet built: ${path.basename(xlsx)} (${fs.statSync(xlsx).size} bytes)`);

const table = 'attendance';
const before = Number(db(`SELECT COUNT(*) FROM ${table}`));

await withBrowser(async ({ go, ev, send }) => {
  await go('upload_attendance.php');
  await ev('window.__alerts = []');

  // put the file into the page's real file input, then submit the page's own form
  const doc = await send('DOM.getDocument', { depth: -1 });
  const input = await send('DOM.querySelector', { nodeId: doc.result.root.nodeId, selector: 'input[type=file]' });
  if (!input.result || !input.result.nodeId) {
    problems.push('no file input on the page');
    return;
  }
  await send('DOM.setFileInputFiles', { nodeId: input.result.nodeId, files: [xlsx] });
  log.push('file attached to the upload input');

  // submit the form that owns it
  await ev(`
    (() => {
      const input = document.querySelector('input[type=file]');
      const form = input && input.closest('form');
      if (!form) return 'no form';
      form.submit();
      return 'submitted';
    })()
  `);
  await new Promise(r => setTimeout(r, 5000));

  const body = await ev('document.body.innerText.slice(0, 400)');
  const fatal = typeof body === 'string' && /Fatal error|Uncaught Error|IOFactory/i.test(body);
  if (fatal) problems.push(`the page shows a fatal error: ${String(body).replace(/\s+/g, ' ').slice(0, 160)}`);
  log.push(`answer: ${String(body || '').replace(/\s+/g, ' ').slice(0, 130)}`);

  const alerts = (await ev('window.__alerts')) || [];
  const shown = alerts.map(a => { try { return JSON.parse(a); } catch { return null; } }).filter(Boolean);
  log.push(shown.length ? `alert: ${JSON.stringify(shown[0]).slice(0, 140)}` : 'no alert');
});

const after = Number(db(`SELECT COUNT(*) FROM ${table}`));
if (after !== before) problems.push(`the upload wrote ${after - before} row(s) to ${table}`);
else log.push(`${table} unchanged (${before} rows)`);

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
fs.rmSync(xlsx, { force: true });
void php;
process.exit(problems.length ? 1 : 0);
