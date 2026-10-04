// Verifies the PO lookup still answers JSON for the View Details modal, after narrowing its
// trigger so the page's own form posts fall through to the listing.
//
//   node test-gpo-lookup.mjs
//
// The endpoint treated any POST carrying po_id as the lookup. Every form on the page carries
// po_id, so the form posts were answered by the lookup instead: approving rendered the
// PO-details fragment in place of the page, and deleting printed {"error":"Purchase Order
// not found"} because the row had just been removed and the lookup could no longer find it.
// The lookup must keep working - it is what fills the View Details modal.
import { withBrowser, db } from './browser.mjs';

const problems = [];
const log = [];

await withBrowser(async ({ go, ev, become, base, SID }) => {
  const ceoId = Number(db("SELECT id FROM users WHERE accounttype = 'Admin' AND position = 'CEO' LIMIT 1"));
  if (ceoId) await become(ceoId);

  // the endpoint answers the lookup with a PO-details fragment, which is what the modal shows
  const id = Number(db('SELECT id FROM gasoline_purchase_orders ORDER BY id DESC LIMIT 1'));
  const lookup = await fetch(`${base()}/api/gasoline_purchase_order-endpoint.php`, {
    method: 'POST',
    body: new URLSearchParams({ po_id: String(id) }),
    headers: { Cookie: `PHPSESSID=${SID}` },
  });
  const body = Buffer.from(await lookup.arrayBuffer()).toString('utf8');
  log.push(`lookup for PO ${id} -> ${lookup.status}, ${body.length} bytes, has data-po-id=${/data-po-id/.test(body)}`);
  if (lookup.status !== 200) problems.push(`the lookup answered ${lookup.status}`);
  if (!/data-po-id/.test(body)) problems.push('the lookup no longer returns the PO details fragment');

  // an unknown id must still be reported as not found
  const missing = await fetch(`${base()}/api/gasoline_purchase_order-endpoint.php`, {
    method: 'POST',
    body: new URLSearchParams({ po_id: '999999999' }),
    headers: { Cookie: `PHPSESSID=${SID}` },
  });
  const missingBody = Buffer.from(await missing.arrayBuffer()).toString('utf8');
  log.push(`lookup for an unknown id -> ${missing.status}, ${missingBody.trim().slice(0, 60)}`);
  if (!/not found/i.test(missingBody)) problems.push('an unknown id is no longer reported as not found');

  // and the modal itself must fill
  await go('gasoline_purchase_order.php');
  await ev("document.querySelector('.view-po-btn').click()");
  await new Promise(r => setTimeout(r, 2500));
  const modal = await ev(`
    (() => {
      const m = document.getElementById('viewPOModal');
      const c = document.getElementById('poDetailsContent');
      return {
        shown: m ? m.classList.contains('show') : false,
        chars: c ? c.innerHTML.trim().length : 0,
        hasPoNumber: c ? /Purchase Order:/.test(c.innerHTML) : false,
      };
    })()
  `);
  log.push(`View Details modal: ${JSON.stringify(modal)}`);
  if (!modal.shown) problems.push('the view modal did not open');
  if (modal.chars < 100) problems.push('the view modal was left empty');
  if (!modal.hasPoNumber) problems.push('the view modal does not show a purchase order');
}, { alerts: true });

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
