// Verifies the Employee picker on upload_attendance.php is a working search dropdown.
//
//   node test-upload-attendance-employee-search.mjs
//
// The Employee field in both the Add and the Edit modal was a plain <select> holding an
// option per employee. The widget in the page's script replaces it with a text input that
// filters as you type, keeping the select in the form so the POST is unchanged. This checks:
//   - the input and its option list exist in both modals
//   - typing filters the options
//   - choosing an option sets the value the form actually posts
//   - the edit modal shows the employee the record already holds
import { withBrowser } from './browser.mjs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const problems = [];
const log = [];

await withBrowser(async ({ go, ev, errors }) => {
  await go('upload_attendance.php');
  if (errors.length) problems.push(`the script threw on load: ${errors[0]}`);

  for (const id of ['modal_employee_id', 'edit_employee_id']) {
    const shape = await ev(`
      (() => {
        const sel = document.getElementById(${JSON.stringify(id)});
        if (!sel) return { error: 'select missing' };
        const wrap = sel.closest('.searchable-select');
        if (!wrap) return { error: 'no searchable wrapper' };
        const input = wrap.querySelector('.searchable-select-input');
        if (!input) return { error: 'no search input' };
        return {
          options: sel.options.length - 1,
          selectHidden: getComputedStyle(sel).display === 'none',
          inputVisible: input.offsetParent !== null || getComputedStyle(input).display !== 'none',
          postsName: sel.name,
        };
      })()
    `);
    log.push(`${id}: ${JSON.stringify(shape)}`);
    if (shape.error) { problems.push(`${id}: ${shape.error}`); continue; }
    if (!shape.selectHidden) problems.push(`${id}: the original select is still visible`);
    if (shape.postsName !== 'employee_id') problems.push(`${id}: the form would post "${shape.postsName}"`);
    if (shape.options < 1) problems.push(`${id}: no employee options`);
  }

  // the Add modal: type a term and require the list to narrow, then choose
  const search = await ev(`
    (() => {
      const sel = document.getElementById('modal_employee_id');
      const input = sel.closest('.searchable-select').querySelector('.searchable-select-input');
      const total = sel.options.length - 1;
      const target = sel.options[1].textContent.trim();
      const term = target.split(' ')[0].replace(',', '');
      input.focus();
      input.value = term;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      const list = sel.closest('.searchable-select').querySelector('.searchable-select-list');
      const items = list.querySelectorAll('.searchable-select-item');
      return { total, term, shown: items.length, first: items.length ? items[0].textContent.trim() : null };
    })()
  `);
  log.push(`typing "${search.term}" -> ${search.shown} of ${search.total} options`);
  if (!(search.shown >= 1 && search.shown < search.total)) {
    problems.push(`typing did not filter the list (${search.shown} of ${search.total})`);
  }

  const chosen = await ev(`
    (() => {
      const sel = document.getElementById('modal_employee_id');
      const wrap = sel.closest('.searchable-select');
      const list = wrap.querySelector('.searchable-select-list');
      const item = list.querySelector('.searchable-select-item');
      if (!item) return { error: 'nothing to choose' };
      const label = item.textContent.trim();
      const idx = parseInt(item.getAttribute('data-index'), 10);
      list.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
      // the widget listens for mousedown on the item itself
      item.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
      return { label, idx, value: sel.value, input: wrap.querySelector('.searchable-select-input').value };
    })()
  `);
  log.push(`choosing "${chosen.label}" -> select.value=${JSON.stringify(chosen.value)}`);
  if (!chosen.value) problems.push('choosing an option did not set the select value');
  else {
    const taken = await ev(`
      (() => {
        const fd = new FormData(document.getElementById('addAttendanceForm'));
        return fd.get('employee_id');
      })()
    `);
    log.push(`the Add form would post employee_id=${JSON.stringify(taken)}`);
    if (String(taken) !== String(chosen.value)) problems.push('the form does not post the chosen employee');
  }

  // the Edit modal must show the employee the record holds
  const rowJson = execFileSync('php', [path.join('E:\\laragon\\www\\OCP', '_verify', 'rows.php'),
    'SELECT a.id, a.employee_id FROM attendance a JOIN employee e ON e.id = a.employee_id ORDER BY a.id DESC LIMIT 1'],
    { encoding: 'utf8' }).trim();
  const record = rowJson ? JSON.parse(rowJson) : null;
  const attId = record && record.id;
  const empId = record && record.employee_id;
  if (attId && empId) {
    const prefilled = await ev(`
      (() => {
        if (typeof editAttendance !== 'function') return { error: 'editAttendance is not defined' };
        editAttendance(${JSON.stringify(JSON.stringify({ id: Number(attId), employee_id: Number(empId) }))});
        const sel = document.getElementById('edit_employee_id');
        const input = sel.closest('.searchable-select').querySelector('.searchable-select-input');
        const label = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent.trim() : '';
        return { value: sel.value, input: input.value, label };
      })()
    `);
    log.push(`edit modal prefill: value=${JSON.stringify(prefilled.value)} input=${JSON.stringify(prefilled.input)}`);
    if (prefilled.error) problems.push(`edit modal: ${prefilled.error}`);
    else if (String(prefilled.value) !== String(empId)) problems.push(`edit modal selected ${prefilled.value}, record holds ${empId}`);
    else if (prefilled.input !== prefilled.label) problems.push('the edit modal search box does not show the employee it holds');
  }
}, { alerts: false });

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
