// Confirms the sweetalerts work on the pages that report in place rather than by
// redirecting, using submissions that are refused (so nothing is created).
//
//   node test-alert-inplace.mjs
import { withBrowser, db } from './browser.mjs';

const problems = [];
const log = [];

await withBrowser(async ({ go, ev, post }) => {
  const cases = [
    {
      label: 'employee_registration.php / update details',
      page: 'employee_registration.php',
      table: 'employee',
      // the update branch, with a required field blank: refused with an error alert
      fields: { update_employee_details: '1', employee_id: '', firstname: '', lastname: '', position: '', hire_date: '' },
      expectIcon: 'error',
    },
    {
      label: 'employee_registration.php / unknown action',
      page: 'employee_registration.php',
      table: 'employee',
      fields: { something_unknown: '1' },
      expectIcon: null,
    },
    {
      label: 'purchase_request_spare_parts.php / empty create',
      page: 'purchase_request_spare_parts.php',
      table: 'spare_parts_pr',
      fields: { request_type: 'stock', create_pr: '1' },
      expectIcon: null,
    },
  ];

  for (const c of cases) {
    const before = Number(db(`SELECT COUNT(*) FROM ${c.table}`));
    await go(c.page);

    // Submit a real form so the browser navigates to the answer and runs its scripts, which
    // is what a user's click does. A fetch would not run the answer's scripts.
    await ev(`
      (() => {
        window.__alerts = [];
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = ${JSON.stringify(c.page)};
        f.style.display = 'none';
        for (const [k, v] of Object.entries(${JSON.stringify(c.fields)})) {
          const i = document.createElement('input');
          i.name = k; i.value = v;
          f.appendChild(i);
        }
        document.body.appendChild(f);
        f.submit();
        return 'submitted';
      })()
    `);
    // Wait for the alert rather than sleeping a fixed time: the page's answer is a fresh
    // document whose script shows the message, and how long that takes varies. A fixed wait
    // made this flaky - the same code passed and failed.
    let alerts = [];
    const deadline = Date.now() + 15000;
    while (Date.now() < deadline) {
      await new Promise(r => setTimeout(r, 300));
      alerts = ((await ev('window.__alerts')) || []).map(a => { try { return JSON.parse(a); } catch { return null; } }).filter(Boolean);
      if (alerts.length) break;
    }
    const after = Number(db(`SELECT COUNT(*) FROM ${c.table}`));
    if (after !== before) problems.push(`${c.label}: the submission created data (${before} -> ${after})`);

    if (!alerts.length) {
      log.push(`${c.label.padEnd(46)} no alert (page title ${JSON.stringify(await ev('document.title'))})`);
      problems.push(`${c.label}: no alert`);
      continue;
    }
    const o = alerts[0];
    log.push(`${c.label.padEnd(46)} icon=${JSON.stringify(o.icon ?? '')} title=${JSON.stringify(o.title ?? '')} text=${JSON.stringify(String(o.text ?? '').slice(0, 34))}`);
    if (c.expectIcon && o.icon !== c.expectIcon) problems.push(`${c.label}: expected an ${c.expectIcon} alert`);
    if (!o.title && !o.text) problems.push(`${c.label}: the alert had no content`);
  }
});

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
