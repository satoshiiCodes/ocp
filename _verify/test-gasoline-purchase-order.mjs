// Verifies the five reported problems on gasoline_purchase_order.php.
//
//   node test-gasoline-purchase-order.mjs
//
// All five had one cause: assets/js/app.js assigned window.$ unconditionally, and this page
// loads jQuery and calls $(...).modal(), $(this).find(...), $(...).val(...). $ stopped being
// jQuery, so the page's DOMContentLoaded handler threw on its first jQuery call and every
// handler after it in that file was never attached. What is checked here:
//
//   1. the table's action buttons open their modals / follow their link
//   2. selecting a Vehicle prevents selecting an Equipment, and the other way round
//   3. Driver/Operator "other" reveals the manual entry field
//   4. the total amount is computed from quantity x price
//   5. Add Item adds a row and Remove Last Item takes one away
//
// Nothing is submitted, so no purchase order is created, approved or deleted.
import { withBrowser, db } from './browser.mjs';

const problems = [];
const log = [];

await withBrowser(async ({ go, ev, errors, become }) => {
  // ------------------------------------------------ the create modal
  await go('gasoline_purchase_order.php');
  if (errors.length) problems.push(`the script threw on load: ${errors[0]}`);
  await ev("document.querySelector(\"[data-bs-target='#createPOModal']\").click()");
  await new Promise(r => setTimeout(r, 1500));

  // 4. total amount
  const total = await ev(`
    (() => {
      const row = document.querySelector('.item-row');
      const qty = row.querySelector('.item-quantity');
      const price = row.querySelector('.item-price');
      if (!qty || !price) return { error: 'no quantity/price field in the row' };
      qty.value = '10'; price.value = '2.5';
      qty.dispatchEvent(new Event('input', { bubbles: true }));
      qty.dispatchEvent(new Event('change', { bubbles: true }));
      price.dispatchEvent(new Event('input', { bubbles: true }));
      price.dispatchEvent(new Event('change', { bubbles: true }));
      const itemTotal = row.querySelector('.item-total');
      return {
        itemTotal: itemTotal ? itemTotal.value : null,
        grand: document.getElementById('total_amount').value,
      };
    })()
  `);
  log.push(`total amount for 10 x 2.50: line=${JSON.stringify(total.itemTotal)} grand=${JSON.stringify(total.grand)}`);
  if (total.error) problems.push(total.error);
  else {
    if (Number(total.itemTotal) !== 25) problems.push(`the line total is ${total.itemTotal}, expected 25.00`);
    if (Number(total.grand) !== 25) problems.push(`the grand total is ${total.grand}, expected 25.00`);
  }

  // 3. driver "other"
  const driver = await ev(`
    (() => {
      const sel = document.querySelector('.item-driver-select');
      if (!sel) return { error: 'no driver/operator select' };
      const hasOther = [...sel.options].some(o => o.value === 'other');
      if (!hasOther) return { error: 'the driver select has no "other" option' };
      sel.value = 'other';
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      const manual = document.querySelector('.item-row .item-manual-driver');
      const holder = manual ? manual.closest('[id^="manual-driver-row"]') : null;
      const shown = holder && getComputedStyle(holder).display !== 'none';
      // and it must hide again when a real driver is chosen
      const real = [...sel.options].find(o => o.value && o.value !== 'other');
      sel.value = real ? real.value : '';
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      const hiddenAgain = holder && getComputedStyle(holder).display === 'none';
      return { shown: !!shown, hiddenAgain: !!hiddenAgain };
    })()
  `);
  log.push(`driver "other": manual field shown=${driver.shown} hidden again on a real driver=${driver.hiddenAgain}`);
  if (driver.error) problems.push(driver.error);
  else {
    if (!driver.shown) problems.push('choosing "other" did not reveal the manual driver field');
    if (!driver.hiddenAgain) problems.push('the manual driver field stayed visible after a real driver was chosen');
  }

  // 2. vehicle <-> equipment
  const excl = await ev(`
    (() => {
      const row = document.querySelector('.item-row');
      const v = row.querySelector('.item-vehicle');
      const e = row.querySelector('.item-equipment');
      if (!v || !e) return { error: 'no vehicle/equipment select' };
      const vOpt = [...v.options].filter(o => o.value)[0];
      const eOpt = [...e.options].filter(o => o.value)[0];
      if (!vOpt || !eOpt) return { error: 'vehicle or equipment has no options to choose' };

      v.value = vOpt.value;
      v.dispatchEvent(new Event('change', { bubbles: true }));
      const afterVehicle = { equipmentDisabled: e.disabled, equipmentValue: e.value };

      // clear the vehicle so the equipment can be tried the other way round
      v.value = '';
      v.dispatchEvent(new Event('change', { bubbles: true }));
      e.value = eOpt.value;
      e.dispatchEvent(new Event('change', { bubbles: true }));
      const afterEquipment = { vehicleDisabled: v.disabled, vehicleValue: v.value };

      return { afterVehicle, afterEquipment };
    })()
  `);
  log.push(`vehicle<->equipment: after vehicle ${JSON.stringify(excl.afterVehicle)}, after equipment ${JSON.stringify(excl.afterEquipment)}`);
  if (excl.error) problems.push(excl.error);
  else {
    if (!excl.afterVehicle.equipmentDisabled) problems.push('choosing a vehicle did not disable the equipment field');
    if (excl.afterVehicle.equipmentValue) problems.push('choosing a vehicle left an equipment selected');
    if (!excl.afterEquipment.vehicleDisabled) problems.push('choosing an equipment did not disable the vehicle field');
    if (excl.afterEquipment.vehicleValue) problems.push('choosing an equipment left a vehicle selected');
  }

  // 5. add / remove item
  const before = await ev("document.querySelectorAll('.item-row').length");
  await ev("document.getElementById('addItemBtn').click()");
  await new Promise(r => setTimeout(r, 800));
  const added = await ev("document.querySelectorAll('.item-row').length");
  await ev("document.getElementById('removeItemBtn').click()");
  await new Promise(r => setTimeout(r, 800));
  const removed = await ev("document.querySelectorAll('.item-row').length");
  log.push(`add / remove item: ${before} -> ${added} -> ${removed}`);
  if (added !== before + 1) problems.push(`Add Item gave ${added} rows, expected ${before + 1}`);
  if (removed !== before) problems.push(`Remove Last Item left ${removed} rows, expected ${before}`);

  // ------------------------------------------------ the table's action buttons
  // These are gated on the viewer's role: approve needs an Admin whose position is CEO,
  // complete and update-invoice need a Purchaser, delete needs a role the page allows. Each
  // is therefore exercised as a user who actually sees it.
  const poCount = Number(db('SELECT COUNT(*) FROM gasoline_purchase_orders'));
  const roles = {
    ceo: Number(db("SELECT id FROM users WHERE accounttype = 'Admin' AND position = 'CEO' LIMIT 1")),
    purchaser: Number(db("SELECT id FROM users WHERE accounttype = 'Admin' AND position = 'Purchaser' LIMIT 1")),
  };
  log.push(`sessions: CEO user ${roles.ceo || '(none)'}, Purchaser user ${roles.purchaser || '(none)'}`);

  const cases = [
    ['view', '.view-po-btn', null, 'viewPOModal'],
    ['delete', '.delete-po-btn', 'ceo', 'deletePOModal'],
    ['approve', '.approve-po-btn', 'ceo', 'approvePOModal'],
    ['complete', '.complete-po-btn', 'purchaser', 'completePOModal'],
    ['update invoice', '.update-invoice-btn', 'purchaser', 'updateInvoiceModal'],
  ];

  for (const [label, sel, role, modalId] of cases) {
    if (role && roles[role]) await become(roles[role]);
    await go('gasoline_purchase_order.php');
    const present = await ev(`document.querySelectorAll(${JSON.stringify(sel)}).length`);
    if (!present) {
      log.push(`${label.padEnd(14)} not offered to this role - skipped`);
      if (!role) problems.push(`${label}: no ${sel} button on the page`);
      continue;
    }
    await ev(`(() => { window.__alerts = []; return 'ok'; })()`);
    await ev(`document.querySelector(${JSON.stringify(sel)}).click()`);
    await new Promise(r => setTimeout(r, 1600));
    const state = await ev(`
      (() => ({
        modals: [...document.querySelectorAll('.modal')].filter(m => m.classList.contains('show')).map(m => m.id).join(','),
        url: location.search,
      }))()
    `);
    log.push(`${label.padEnd(14)} ${String(present).padStart(2)} button(s) -> modal="${state.modals}" url="${state.url}"`);
    if (!new RegExp(modalId).test(state.modals)) problems.push(`${label} did not open ${modalId} (got "${state.modals}")`);
    // the modal must actually hold the order it was opened for
    const filled = await ev(`
      (() => {
        const m = document.getElementById(${JSON.stringify(modalId)});
        if (!m) return null;
        const idField = m.querySelector('input[type=hidden][name*="id"], input[type=hidden][id*="id"]');
        return { id: idField ? idField.value : null, textChars: m.innerText.trim().length };
      })()
    `);
    log.push(`${' '.repeat(14)} ${JSON.stringify(filled)}`);
    // the view modal renders the order as text and carries no id input, so only the modals
    // that act on the order are required to have one
    if (label !== 'view' && filled && !filled.id) problems.push(`${label}: the modal has no id field filled in`);
    if (filled && filled.textChars < 20) problems.push(`${label}: the modal is empty`);
    if (state.url) await go('gasoline_purchase_order.php');
  }

  // the edit button is a link, not a modal; it must carry the id
  await become(Number(db("SELECT id FROM users WHERE accounttype = 'Admin' LIMIT 1")));
  await go('gasoline_purchase_order.php');
  const editHref = await ev(`(() => { const a = document.querySelector('.edit-po-btn'); return a ? a.getAttribute('href') : null; })()`);
  log.push(`edit link: ${JSON.stringify(editHref)}`);
  if (editHref && !/\?edit_po=\d+/.test(editHref)) problems.push(`the edit link is "${editHref}", expected ?edit_po=<id>`);

  // the edit view must load the order into the create modal
  if (editHref) {
    const id = /edit_po=(\d+)/.exec(editHref)[1];
    await go(`gasoline_purchase_order.php?edit_po=${id}`);
    const editState = await ev(`
      (() => {
        const modal = document.getElementById('createPOModal');
        const rows = document.querySelectorAll('.item-row').length;
        const first = document.querySelector('.item-row');
        return {
          open: modal ? modal.classList.contains('show') : false,
          rows,
          qty: first ? (first.querySelector('.item-quantity') || {}).value : null,
          price: first ? (first.querySelector('.item-price') || {}).value : null,
          grand: (document.getElementById('total_amount') || {}).value,
        };
      })()
    `);
    log.push(`edit view of order ${id}: ${JSON.stringify(editState)}`);
    if (!editState.open) problems.push('?edit_po= did not open the create modal');
    if (editState.rows < 1) problems.push('?edit_po= did not load the order\'s items');
    if (!(Number(editState.grand) > 0)) problems.push(`?edit_po= left the total at ${editState.grand}`);
  }

  const after = Number(db('SELECT COUNT(*) FROM gasoline_purchase_orders'));
  if (after !== poCount) problems.push(`the checks changed the purchase-order count (${poCount} -> ${after})`);
  else log.push(`gasoline_purchase_orders unchanged (${poCount} rows)`);
}, { alerts: true });

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
