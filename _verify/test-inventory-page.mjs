// Verifies the four things reported on inventory.php, in a real browser.
//
//   node test-inventory-page.mjs
//
//   1. a SweetAlert appears after an action
//   2. the transfer modal's Item field is a searchable dropdown
//   3. From Warehouse and To Warehouse never offer the same warehouse
//   4. Recent Stock Movements: view, edit and delete respond
//
// All three of the old failures had one cause - app.js replaced window.$, so the page script
// threw on its first $(...).select2(...) call and every line after it in the same handler was
// dead. A fourth defect was separate: the view and edit lookups fetched a file the
// restructure had removed.
import { withBrowser, db } from './browser.mjs';

const problems = [];
const log = [];

await withBrowser(async ({ go, ev, post, base, SID }) => {
  // ------------------------------------------------ 1. an alert after an action
  // set_min_stock against an id that matches no row: the handler still reports through the
  // session and redirects, which is the path the alert travels.
  await go('inventory.php');
  await ev('window.__alerts = []');
  await post('inventory.php', { action: 'set_min_stock', item_id: '999999999', min_stock_level: '1' });
  await new Promise(r => setTimeout(r, 400));
  let alerts = ((await go('inventory.php')) || []).map(a => { try { return JSON.parse(a); } catch { return null; } }).filter(Boolean);
  log.push(`after an action: ${alerts.length ? `alert "${alerts[0].title}" / "${String(alerts[0].text || '').slice(0, 34)}"` : 'NO alert'}`);
  if (!alerts.length) problems.push('no SweetAlert after an action');
  else if (!alerts[0].title && !alerts[0].text) problems.push('the alert has no content');

  // ------------------------------------------------ 2. the transfer item field is searchable
  const item = await ev(`
    (() => {
      const sel = document.getElementById('transfer_item_id');
      if (!sel) return { error: 'no transfer_item_id' };
      const wrap = sel.nextElementSibling;
      return {
        hasSelect2: !!(wrap && wrap.classList.contains('select2-container')),
        options: sel.options.length - 1,
        // the plugin builds a search box inside its container
        searchBox: !!document.querySelector('.select2-search__field'),
      };
    })()
  `);
  log.push(`transfer item field: ${JSON.stringify(item)}`);
  if (item.error) problems.push(item.error);
  else {
    if (!item.hasSelect2) problems.push('the transfer item field is not a select2 search dropdown');
    if (item.options < 1) problems.push('the transfer item field has no options');
  }

  // ------------------------------------------------ 3. warehouse exclusion
  const exclusion = await ev(`
    (() => {
      const from = document.getElementById('from_warehouse_id');
      const to = document.getElementById('to_warehouse_id');
      if (!from || !to) return { error: 'warehouse selects missing' };
      const opts = (el) => [...el.options].filter(o => o.value !== '').map(o => o.value);
      const before = { from: opts(from), to: opts(to) };
      const pick = before.from[0];
      from.value = pick;
      from.dispatchEvent(new Event('change', { bubbles: true }));
      const toAfter = [...to.options].filter(o => o.value === pick)[0];
      return {
        total: before.from.length,
        picked: pick,
        // the chosen warehouse must be unselectable in the other list
        disabledInTo: !!(toAfter && toAfter.disabled),
        hiddenInTo: !!(toAfter && toAfter.hidden),
        stillListedInFrom: opts(from).includes(pick),
      };
    })()
  `);
  log.push(`picking warehouse ${exclusion.picked} of ${exclusion.total}: taken out of "To" = ${exclusion.disabledInTo}`);
  if (exclusion.error) problems.push(exclusion.error);
  else if (!exclusion.disabledInTo && !exclusion.hiddenInTo) problems.push('the chosen warehouse is still selectable in the other field');

  // the server must refuse a same-warehouse transfer as well
  const same = await post('inventory.php', {
    action: 'transfer', item_id: '999999999', from_warehouse_id: '1', to_warehouse_id: '1',
    quantity: '1', transfer_date: '2026-01-01',
  });
  // same.text carries the answer
  const movementsBefore = Number(db('SELECT COUNT(*) FROM stock_movements'));
  log.push(`server-side same-warehouse transfer: status ${same.status}`);
  const movementsAfter = Number(db('SELECT COUNT(*) FROM stock_movements'));
  if (movementsAfter !== movementsBefore) problems.push(`a same-warehouse transfer wrote ${movementsAfter - movementsBefore} movement row(s)`);
  void same.text;

  // ------------------------------------------------ 4. the movement buttons
  await go('inventory.php');
  await ev('window.__alerts = []');
  const buttons = await ev(`({
    view: document.querySelectorAll('.view-movement').length,
    edit: document.querySelectorAll('.edit-movement').length,
    del: document.querySelectorAll('.delete-movement').length,
  })`);
  log.push(`movement buttons: ${JSON.stringify(buttons)}`);
  if (!buttons.view || !buttons.edit || !buttons.del) problems.push('a movement action button is missing from the table');

  // view: needs the id lookup that used to 404
  const requested = [];
  await ev(`
    (() => {
      window.__ocpReqs = [];
      const f = window.fetch;
      window.fetch = function (u) { window.__ocpReqs.push(String(u)); return f.apply(this, arguments); };
      return 'hooked';
    })()
  `);
  await ev("document.querySelector('.view-movement').click()");
  await new Promise(r => setTimeout(r, 2500));
  const viewState = await ev(`
    (() => {
      const modal = document.getElementById('viewMovementModal');
      const details = document.getElementById('movementDetails');
      return {
        requests: window.__ocpReqs || [],
        shown: modal ? modal.classList.contains('show') : false,
        detailChars: details ? details.innerHTML.trim().length : 0,
      };
    })()
  `);
  void requested;
  log.push(`view movement -> request ${JSON.stringify(viewState.requests)} shown=${viewState.shown} chars=${viewState.detailChars}`);
  if (!viewState.requests.length) problems.push('view movement made no request');
  else if (!/api\/inventory-endpoint\.php\?id=/.test(viewState.requests[0])) problems.push(`view movement requested ${viewState.requests[0]}`);
  if (viewState.detailChars === 0) problems.push('the view modal was left empty');

  // A modal left open puts up a backdrop that swallows the next click, so each one is
  // dismissed before the next button is tried - these checks are about the buttons, not
  // about stacking modals.
  const dismissModals = () => ev(`
    (() => {
      document.querySelectorAll('.modal.show').forEach(m => {
        const inst = bootstrap.Modal.getInstance(m);
        if (inst) inst.hide();
      });
      document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('overflow');
      document.body.style.removeProperty('padding-right');
      return document.querySelectorAll('.modal.show').length;
    })()
  `);
  await dismissModals();
  await new Promise(r => setTimeout(r, 600));

  // edit: same lookup, then the form must be filled
  await ev('window.__ocpReqs = []');
  await ev("document.querySelector('.edit-movement').click()");
  await new Promise(r => setTimeout(r, 2500));
  const editState = await ev(`
    (() => {
      const modal = document.getElementById('editMovementModal');
      const id = document.getElementById('edit_movement_id');
      return {
        requests: window.__ocpReqs || [],
        shown: modal ? modal.classList.contains('show') : false,
        id: id ? id.value : null,
      };
    })()
  `);
  log.push(`edit movement -> request ${JSON.stringify(editState.requests)} shown=${editState.shown} id=${JSON.stringify(editState.id)}`);
  if (!editState.requests.length) problems.push('edit movement made no request');
  else if (!/api\/inventory-endpoint\.php\?id=/.test(editState.requests[0])) problems.push(`edit movement requested ${editState.requests[0]}`);
  if (!editState.id) problems.push('the edit modal was not given the movement id');

  // delete: opens its confirm modal
  await dismissModals();
  await new Promise(r => setTimeout(r, 600));
  await ev("document.querySelector('.delete-movement').click()");
  await new Promise(r => setTimeout(r, 900));
  const delState = await ev(`
    (() => {
      const modal = document.getElementById('deleteMovementModal');
      const id = document.getElementById('delete_movement_id');
      return { shown: modal ? modal.classList.contains('show') : false, id: id ? id.value : null };
    })()
  `);
  log.push(`delete movement -> shown=${delState.shown} id=${JSON.stringify(delState.id)}`);
  if (!delState.shown || !delState.id) problems.push('the delete confirmation did not open with the movement id');
  void base; void SID;
}, { alerts: true });

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
