// Verifies the Actions column on spare_parts_inventory.php's "Recent Parts Movements"
// table, in a real browser.
//
//   node test-spare-parts-inventory-movements.mjs
//
//   1. the Actions column exists and every movement row carries the three buttons
//   2. View   -> api/spare_parts_inventory-endpoint.php?id=<id> and a filled modal
//   3. Edit   -> the same lookup and the movement id in the edit form
//   4. Delete -> the confirmation, carrying the movement id
//   5. Close / Cancel really dismiss each modal, and nothing is written
//   6. the script shows the island's message and reopens a refused edit, and the page
//      raises no JavaScript errors anywhere
//
// Nothing here edits or deletes a row. The delete button is only ever opened and
// dismissed - it is never submitted - and the one form submission is an edit aimed at a
// movement id that does not exist, so the update cannot touch anything: the actions file
// looks the row up first and answers "not found" without running any UPDATE. The row
// count of spare_parts_movements is taken before and after and must be unchanged.
import { withBrowser, db } from './browser.mjs';

const problems = [];
const log = [];

// The row the lookup checks read; it is only ever read.
const SAMPLE_ID = db('SELECT id FROM spare_parts_movements ORDER BY id LIMIT 1').trim();
// A movement id that cannot collide with a real row.
const MISSING_ID = '999999999';

await withBrowser(async ({ go, ev, errors, base, SID }) => {
  const rowsBefore = Number(db('SELECT COUNT(*) FROM spare_parts_movements'));
  const sampleBefore = db(`SELECT CONCAT_WS('|', quantity, price_per_unit, IFNULL(technician,'-'), movement_date, IFNULL(purpose,'-'), IFNULL(batch_id,'-')) FROM spare_parts_movements WHERE id = ${SAMPLE_ID}`).trim();
  log.push(`fixture: ${rowsBefore} movements, sample row ${SAMPLE_ID} = ${sampleBefore}`);

  await go('spare_parts_inventory.php');
  const pageErrors = () => (ev('window.__ocpErrors || []'));
  await ev('window.__ocpErrors = []; window.addEventListener("error", function (e) { window.__ocpErrors.push(String(e.message)); });');

  // ------------------------------------------------- 1. the column and its buttons
  const table = await ev(`
    (() => {
      const table = document.getElementById('movementsTable');
      if (!table) return { error: 'no movementsTable' };
      const headers = [...table.querySelectorAll('thead th')].map(th => th.textContent.trim());
      const rows = [...table.querySelectorAll('tbody tr')];
      return {
        headers,
        hasActionsHeader: headers.includes('Actions'),
        headerIndex: headers.indexOf('Actions'),
        rows: rows.length,
        view: document.querySelectorAll('.view-part-movement').length,
        edit: document.querySelectorAll('.edit-part-movement').length,
        del: document.querySelectorAll('.delete-part-movement').length,
        // one of each per row, and in the last cell of the row
        completeRows: rows.filter(r => r.querySelectorAll('.view-part-movement, .edit-part-movement, .delete-part-movement').length === 3).length,
        lastCell: rows.length ? rows[0].lastElementChild.querySelectorAll('button').length : 0,
        firstIds: [...document.querySelectorAll('.view-part-movement')].slice(0, 3).map(b => b.getAttribute('data-id')),
      };
    })()
  `);
  log.push(`table: ${JSON.stringify(table)}`);
  if (table.error) problems.push(table.error);
  else {
    if (!table.hasActionsHeader) problems.push('the movements table has no "Actions" header');
    if (table.headerIndex !== 0 && table.headerIndex !== table.headers.length - 1) {
      problems.push(`"Actions" is column ${table.headerIndex + 1} of ${table.headers.length}, not the last`);
    }
    if (!table.rows) problems.push('the movements table has no rows to act on');
    if (table.view !== table.rows || table.edit !== table.rows || table.del !== table.rows) {
      problems.push(`button counts (${table.view}/${table.edit}/${table.del}) do not match the ${table.rows} rows`);
    }
    if (table.completeRows !== table.rows) problems.push('a row does not carry all three buttons');
    if (table.lastCell !== 3) problems.push('the last cell of a row does not hold the three buttons');
    if (!table.firstIds.every(id => id && Number(id) > 0)) problems.push(`a button has no movement id: ${JSON.stringify(table.firstIds)}`);
  }
  if (table.rows && table.rows < 1) problems.push('no movement row to test against');

  // The endpoint lookups are captured the way the inventory check does it.
  await ev(`
    (() => {
      window.__ocpReqs = [];
      const f = window.fetch;
      window.fetch = function (u) { window.__ocpReqs.push(String(u)); return f.apply(this, arguments); };
      return 'hooked';
    })()
  `);

  // A modal left open puts up a backdrop that swallows the next click, so each is
  // dismissed before the next button is tried. The first one is dismissed by clicking the
  // modal's own Close button, which is what the check is about.
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

  // ------------------------------------------------- 2. view
  await ev("document.querySelector('.view-part-movement').click()");
  await new Promise(r => setTimeout(r, 2500));
  const view = await ev(`
    (() => {
      const modal = document.getElementById('viewPartMovementModal');
      const details = document.getElementById('partMovementDetails');
      return {
        requests: window.__ocpReqs || [],
        shown: modal ? modal.classList.contains('show') : false,
        text: details ? details.textContent.replace(/\\s+/g, ' ').trim() : '',
        rows: details ? details.querySelectorAll('.row').length : 0,
        expectedId: document.querySelector('.view-part-movement').getAttribute('data-id'),
      };
    })()
  `);
  log.push(`view -> ${JSON.stringify(view.requests)} shown=${view.shown} rows=${view.rows} text="${String(view.text || '').slice(0, 90)}"`);
  if (view.__error) problems.push(`the view check threw in the page: ${view.__error}`);
  if (!view.requests.length) problems.push('view made no request');
  else if (!/api\/spare_parts_inventory-endpoint\.php\?id=/.test(view.requests[0])) problems.push(`view requested ${view.requests[0]}`);
  else if (!view.requests[0].endsWith('?id=' + view.expectedId)) problems.push(`view requested ${view.requests[0]}, not the row's id ${view.expectedId}`);
  if (!view.shown) problems.push('the view modal did not open');
  if (!view.text.length) problems.push('the view modal was left empty');
  if (!/Quantity/.test(view.text) || !/Purpose/.test(view.text)) problems.push('the view modal is missing the movement fields');

  // its own Close button must dismiss it, leaving no backdrop behind
  await ev("document.querySelector('#viewPartMovementModal .modal-footer button').click()");
  await new Promise(r => setTimeout(r, 800));
  const viewClosed = await ev(`
    ({
      shown: document.querySelectorAll('#viewPartMovementModal.show').length,
      backdrops: document.querySelectorAll('.modal-backdrop').length,
      bodyOpen: document.body.classList.contains('modal-open'),
    })
  `);
  log.push(`view Close -> ${JSON.stringify(viewClosed)}`);
  if (viewClosed.shown) problems.push('the view modal stayed open after its Close button');
  if (viewClosed.backdrops) problems.push('a backdrop was left behind after closing the view modal');
  if (viewClosed.bodyOpen) problems.push('body kept modal-open after closing the view modal');

  // ------------------------------------------------- 3. edit
  await ev('window.__ocpReqs = []');
  await ev("document.querySelector('.edit-part-movement').click()");
  await new Promise(r => setTimeout(r, 2500));
  const edit = await ev(`
    (() => {
      const modal = document.getElementById('editPartMovementModal');
      const value = (id) => { const el = document.getElementById(id); return el ? el.value : null; };
      return {
        requests: window.__ocpReqs || [],
        shown: modal ? modal.classList.contains('show') : false,
        id: value('edit_part_movement_id'),
        date: value('edit_part_movement_date'),
        quantity: value('edit_part_quantity'),
        price: value('edit_part_price_per_unit'),
        purpose: value('edit_part_purpose'),
        partInfo: value('edit_part_info'),
        type: value('edit_part_type'),
        expectedId: document.querySelector('.edit-part-movement').getAttribute('data-id'),
        formName: document.getElementById('editPartMovementForm').getAttribute('method'),
        hiddenEdit: document.querySelector('#editPartMovementForm input[name="edit_part_movement"]').value,
        editable: {
          movement_date: !!document.querySelector('#editPartMovementForm [name="movement_date"]'),
          quantity: !!document.querySelector('#editPartMovementForm [name="quantity"]'),
          price_per_unit: !!document.querySelector('#editPartMovementForm [name="price_per_unit"]'),
          technician: !!document.querySelector('#editPartMovementForm [name="technician"]'),
          purpose: !!document.querySelector('#editPartMovementForm [name="purpose"]'),
        },
      };
    })()
  `);
  log.push(`edit -> ${JSON.stringify(edit.requests)} shown=${edit.shown} id=${JSON.stringify(edit.id)} date=${edit.date} qty=${edit.quantity} price=${edit.price} type=${edit.type} part="${edit.partInfo}"`);
  if (edit.__error) problems.push(`the edit check threw in the page: ${edit.__error}`);
  if (!edit.requests.length) problems.push('edit made no request');
  else if (!/api\/spare_parts_inventory-endpoint\.php\?id=/.test(edit.requests[0])) problems.push(`edit requested ${edit.requests[0]}`);
  if (!edit.shown) problems.push('the edit modal did not open');
  if (String(edit.id) !== String(edit.expectedId) || !edit.id) problems.push(`the edit form holds movement id ${JSON.stringify(edit.id)}, not ${edit.expectedId}`);
  if (!edit.date) problems.push('the edit form was not given the movement date');
  if (!edit.quantity) problems.push('the edit form was not given the quantity');
  if (edit.price === '' || edit.price === null) problems.push('the edit form was not given the price per unit');
  if (!edit.partInfo) problems.push('the edit form was not given the part');
  if (edit.hiddenEdit !== '1') problems.push('the edit form does not post edit_part_movement=1');
  const missingFields = Object.keys(edit.editable).filter(k => !edit.editable[k]);
  if (missingFields.length) problems.push(`the edit form has no ${missingFields.join(', ')} field`);

  // and its own Close button must dismiss it too
  await ev("document.querySelector('#editPartMovementModal .modal-footer button').click()");
  await new Promise(r => setTimeout(r, 800));
  const editClosed = await ev(`
    ({
      shown: document.querySelectorAll('#editPartMovementModal.show').length,
      backdrops: document.querySelectorAll('.modal-backdrop').length,
      bodyOpen: document.body.classList.contains('modal-open'),
    })
  `);
  log.push(`edit Close -> ${JSON.stringify(editClosed)}`);
  if (editClosed.shown) problems.push('the edit modal stayed open after its Close button');
  if (editClosed.backdrops) problems.push('a backdrop was left behind after closing the edit modal');
  if (editClosed.bodyOpen) problems.push('body kept modal-open after closing the edit modal');

  // ------------------------------------------------- 4. delete
  await dismissModals();
  await new Promise(r => setTimeout(r, 400));
  await ev('window.__ocpProbe = "alive"');
  await ev("document.querySelector('.delete-part-movement').click()");
  await new Promise(r => setTimeout(r, 900));
  const del = await ev(`
    (() => {
      const modal = document.getElementById('deletePartMovementModal');
      return {
        shown: modal ? modal.classList.contains('show') : false,
        id: document.getElementById('delete_part_movement_id').value,
        description: document.getElementById('delete_part_movement_description').textContent,
        expectedId: document.querySelector('.delete-part-movement').getAttribute('data-id'),
        expectedDescription: document.querySelector('.delete-part-movement').getAttribute('data-description'),
        hiddenDelete: document.querySelector('#deletePartMovementModal input[name="delete_part_movement"]').value,
        hasCancel: !!document.querySelector('#deletePartMovementModal .modal-footer button[data-bs-dismiss="modal"]'),
      };
    })()
  `);
  log.push(`delete -> shown=${del.shown} id=${JSON.stringify(del.id)} description="${del.description}" cancel=${del.hasCancel}`);
  if (del.__error) problems.push(`the delete check threw in the page: ${del.__error}`);
  if (!del.shown) problems.push('the delete confirmation did not open');
  if (String(del.id) !== String(del.expectedId) || !del.id) problems.push(`the delete confirmation holds id ${JSON.stringify(del.id)}, not ${del.expectedId}`);
  if (!del.description) problems.push('the delete confirmation has no description');
  if (del.hiddenDelete !== '1') problems.push('the delete form does not post delete_part_movement=1');
  if (!del.hasCancel) problems.push('the delete confirmation has no Cancel button');

  // ------------------------------------------------- 5. Cancel, and nothing wrote
  await ev(`document.querySelector('#deletePartMovementModal .modal-footer button[data-bs-dismiss="modal"]').click()`);
  await new Promise(r => setTimeout(r, 800));
  const delClosed = await ev(`
    ({
      shown: document.querySelectorAll('#deletePartMovementModal.show').length,
      backdrops: document.querySelectorAll('.modal-backdrop').length,
      probe: window.__ocpProbe || '',
      alerts: window.__alerts || [],
    })
  `);
  log.push(`delete Cancel -> shown=${delClosed.shown} backdrops=${delClosed.backdrops} probe=${JSON.stringify(delClosed.probe)}`);
  if (delClosed.shown) problems.push('the delete confirmation stayed open after Cancel');
  if (delClosed.backdrops) problems.push('a backdrop was left behind after cancelling the delete');
  if (delClosed.probe !== 'alive') problems.push('the page navigated away after the delete Cancel - the form was submitted');

  const rowsMidway = Number(db('SELECT COUNT(*) FROM spare_parts_movements'));
  if (rowsMidway !== rowsBefore) problems.push(`the row count changed to ${rowsMidway} while only opening modals`);

  // ------------------------------------------------- 6. a refused edit reopens the modal
  // The edit is aimed at an id that does not exist, so the actions file answers "not
  // found" before any UPDATE: this cannot change a row. The page publishes the posted
  // values and the script has to reopen the edit modal holding them.
  await go('spare_parts_inventory.php');
  await ev('window.__ocpErrors = []; window.addEventListener("error", function (e) { window.__ocpErrors.push(String(e.message)); });');
  await ev(`
    (() => {
      const form = document.getElementById('editPartMovementForm');
      document.getElementById('edit_part_movement_id').value = '${MISSING_ID}';
      document.getElementById('edit_part_movement_date').value = '2026-05-05';
      document.getElementById('edit_part_quantity').value = '7';
      document.getElementById('edit_part_price_per_unit').value = '12.34';
      document.getElementById('edit_part_purpose').value = 'typed by the check';
      form.submit();
      return 'submitted';
    })()
  `);
  await new Promise(r => setTimeout(r, 4000));
  const reopen = await ev(`
    (() => {
      const modal = document.getElementById('editPartMovementModal');
      const value = (id) => { const el = document.getElementById(id); return el ? el.value : null; };
      return {
        shown: modal ? modal.classList.contains('show') : false,
        id: value('edit_part_movement_id'),
        date: value('edit_part_movement_date'),
        quantity: value('edit_part_quantity'),
        price: value('edit_part_price_per_unit'),
        purpose: value('edit_part_purpose'),
        island: window.OCP_PAGE_SPARE_PARTS_INVENTORY || null,
        alerts: window.__alerts || [],
      };
    })()
  `);
  const reopenAlerts = (reopen.alerts || []).map(a => { try { return JSON.parse(a); } catch { return null; } }).filter(Boolean);
  log.push(`refused edit -> modal shown=${reopen.shown} id=${reopen.id} date=${reopen.date} qty=${reopen.quantity} price=${reopen.price} purpose="${reopen.purpose}" alert="${reopenAlerts.length ? reopenAlerts[0].title + ' / ' + reopenAlerts[0].text : 'NONE'}"`);
  if (reopen.__error) problems.push(`the refused-edit check threw in the page: ${reopen.__error}`);
  if (!reopen.island) problems.push('the page published no data island on the refused edit');
  else {
    if (!reopen.island.postedEditMovement) problems.push('the island does not flag the refused edit');
    if (!reopen.island.postedEditValues || reopen.island.postedEditValues.movement_id !== MISSING_ID) {
      problems.push(`the island does not carry the posted values: ${JSON.stringify(reopen.island.postedEditValues)}`);
    }
  }
  if (!reopen.shown) problems.push('the edit modal did not reopen after a refused edit');
  if (reopen.id !== MISSING_ID) problems.push(`the reopened modal holds id ${JSON.stringify(reopen.id)}`);
  if (reopen.quantity !== '7' || reopen.price !== '12.34' || reopen.purpose !== 'typed by the check' || reopen.date !== '2026-05-05') {
    problems.push(`the reopened modal does not hold what was typed: ${JSON.stringify({ d: reopen.date, q: reopen.quantity, p: reopen.price, why: reopen.purpose })}`);
  }
  if (!reopenAlerts.length) problems.push('the refused edit raised no alert');
  else if (reopenAlerts[0].icon !== 'error') problems.push(`the refused edit raised a "${reopenAlerts[0].icon}" alert`);

  // ------------------------------------------------- the page raised no JavaScript errors
  const jsErrors = await pageErrors();
  if (jsErrors && jsErrors.length) problems.push(`page JavaScript errors: ${JSON.stringify(jsErrors)}`);
  if (errors.length) problems.push(`uncaught exceptions: ${JSON.stringify(errors)}`);

  // ------------------------------------------------- and nothing was written
  const rowsAfter = Number(db('SELECT COUNT(*) FROM spare_parts_movements'));
  const sampleAfter = db(`SELECT CONCAT_WS('|', quantity, price_per_unit, IFNULL(technician,'-'), movement_date, IFNULL(purpose,'-'), IFNULL(batch_id,'-')) FROM spare_parts_movements WHERE id = ${SAMPLE_ID}`).trim();
  log.push(`rows ${rowsBefore} -> ${rowsAfter}; sample row ${SAMPLE_ID}: "${sampleBefore}" -> "${sampleAfter}"`);
  if (rowsAfter !== rowsBefore) problems.push(`spare_parts_movements went from ${rowsBefore} to ${rowsAfter} rows`);
  if (sampleAfter !== sampleBefore) problems.push(`row ${SAMPLE_ID} changed: "${sampleBefore}" -> "${sampleAfter}"`);

  // The endpoint answers a lookup on its own too, with the shape the script expects.
  const lookup = await fetch(`${base()}/api/spare_parts_inventory-endpoint.php?id=${SAMPLE_ID}`, { headers: { Cookie: `PHPSESSID=${SID}` } });
  const lookupJson = await lookup.json();
  log.push(`endpoint ?id=${SAMPLE_ID} -> ${lookup.status} success=${lookupJson.success} part=${lookupJson.movement ? lookupJson.movement.part_name : '-'}`);
  if (lookup.status !== 200 || !lookupJson.success || !lookupJson.movement) problems.push(`the endpoint did not answer with the movement: ${JSON.stringify(lookupJson).slice(0, 120)}`);
});

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
