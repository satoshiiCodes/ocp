// Verifies that jQuery survives app.js on the pages that use it.
//
//   node test-jquery-not-clobbered.mjs
//
// assets/js/app.js assigned window.$ unconditionally. It is loaded after jQuery on the three
// pages whose scripts call jQuery methods, so $ stopped being jQuery and the page scripts
// threw "$(...).select2 is not a function" at the top of their DOMContentLoaded handler. Every
// statement after that line in the same handler was dead: the SweetAlert, the DataTables, and
// the listeners on every button - which is what the reported "not working" buttons were.
//
// This checks, in a real browser, that on each page: $ is jQuery, the widgets initialise, the
// page script raises no error, and the handlers that come after the widget setup are attached.
import { withBrowser } from './browser.mjs';

const problems = [];
const log = [];

const pages = [
  {
    page: 'inventory.php',
    // select2 must have initialised the transfer-modal item field, and DataTables its tables
    check: `({ select2Containers: document.querySelectorAll('.select2-container').length,
               movements: document.querySelectorAll('.view-movement').length,
               dataTables: document.querySelectorAll('.datatable-wrapper').length })`,
    want: { select2AtLeast: 1, movementsAtLeast: 1, dataTablesAtLeast: 1 },
  },
  {
    page: 'spare_parts_inventory.php',
    check: `({ select2Containers: document.querySelectorAll('.select2-container').length,
               movements: document.querySelectorAll('#movementsTable tbody tr').length,
               dataTables: document.querySelectorAll('.datatable-wrapper').length })`,
    want: { select2AtLeast: 1, movementsAtLeast: 1, dataTablesAtLeast: 1 },
  },
  {
    page: 'gasoline_purchase_order.php',
    check: `({ select2Containers: document.querySelectorAll('.select2-container').length,
               movements: 0,
               dataTables: document.querySelectorAll('.datatable-wrapper').length })`,
    want: { select2AtLeast: 0, movementsAtLeast: 0, dataTablesAtLeast: 0 },
  },
];

await withBrowser(async ({ go, ev, errors }) => {
  for (const p of pages) {
    await go(p.page);
    const type = await ev('typeof $');
    const isJquery = await ev('typeof jQuery === "function" && typeof $.fn === "object"');
    const state = await ev(p.check);

    log.push(`${p.page.padEnd(30)} $=${type} jquery=${isJquery} ${JSON.stringify(state)}`);
    if (!isJquery) problems.push(`${p.page}: $ is not jQuery (${type}) - app.js clobbered it again`);
    if (errors.length) problems.push(`${p.page}: the script threw - ${errors[0]}`);
    if (state.select2Containers < p.want.select2AtLeast) problems.push(`${p.page}: select2 did not initialise (${state.select2Containers} containers)`);
    if (state.movements < p.want.movementsAtLeast) problems.push(`${p.page}: no movement rows/buttons rendered`);
    if (state.dataTables < p.want.dataTablesAtLeast) problems.push(`${p.page}: DataTables did not initialise`);
  }
}, { alerts: true });

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
