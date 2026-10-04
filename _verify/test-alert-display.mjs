// Checks the display half of the alert path on its own: put a message where the page reads
// it, load the page, and require a SweetAlert that actually has a title and text.
//
//   node test-alert-display.mjs
//
// The actions half (storing the message and redirecting) is tested separately; this isolates
// whether the page publishes it and the script shows it. It exists because
// spare_parts.php showed an alert containing nothing but its OK button - a script echoing
// session values that the page had already consumed.
import { withBrowser, php, db } from './browser.mjs';

const problems = [];
const log = [];

// writes a message the way each page's handler stores it, then reports what the page shows
//
// Only pages that report by storing a message and redirecting belong here: seeding the
// session is exactly what their handler does. A page that reports in place - it sets
// $swal_data and re-renders within the same request - never reads the session, so seeding it
// proves nothing; those are covered by test-alert-inplace.mjs, which posts the real form.
const pages = [
  { page: 'spare_parts.php', key: 'alert', value: { type: 'success', title: 'Success!', message: 'Spare part deleted successfully!' } },
  { page: 'purchase_request_spare_parts.php', key: 'swal_data', value: { title: 'Error!', text: 'Please select at least one item.', icon: 'error' } },
  { page: 'items_categories.php', key: 'sweetalert', value: { title: 'Success!', text: 'Category added successfully!', icon: 'success' } },
  { page: 'warehouses.php', key: 'swal_data', value: { title: 'Success!', text: 'Warehouse added successfully!', icon: 'success' } },
  { page: 'vehicles.php', key: 'alert', value: { type: 'success', title: 'Success!', message: 'Vehicle added successfully!' } },
  // reports by redirect for delete and for a successful edit
  { page: 'spare_parts_inventory.php', key: 'swal_data', value: { title: 'Success!', text: 'Parts movement deleted successfully!', icon: 'success' } },
];

await withBrowser(async ({ go, goForAlert, ev, SID, base }) => {
  for (const p of pages) {
    // seed the session file the way the app's own handler would
    php('seed-session.php', [SID, p.key, JSON.stringify(p.value)]);

    // Wait for the alert rather than navigating and reading once: the page shows it from
    // DOMContentLoaded and how long that takes varies, so a single read made this flaky -
    // it passed and failed on the same code.
    const list = await goForAlert(p.page, 15000);
    const alerts = Array.isArray(list) ? list : [];
    const shown = alerts.map(a => { try { return JSON.parse(a); } catch { return null; } }).filter(Boolean);

    if (!shown.length) {
      log.push(`${p.page.padEnd(34)} NO alert at all`);
      problems.push(`${p.page}: no alert shown for a seeded message`);
      continue;
    }
    const o = shown[0];
    const hasContent = Boolean((o.title || o.html) && (o.text || o.html || o.title));
    log.push(`${p.page.padEnd(34)} title=${JSON.stringify(o.title ?? '')} text=${JSON.stringify(String(o.text ?? '').slice(0, 34))} icon=${JSON.stringify(o.icon ?? '')}`);
    if (!hasContent) problems.push(`${p.page}: the alert has no title/text (it showed only the OK button)`);

    // the message must be consumed - a later load must be quiet, so this one waits the window out
    const again = await go(p.page, 5000);
    if (Array.isArray(again) && again.length) problems.push(`${p.page}: the message came back on a later load`);
    void ev; void base;
  }
}, { alerts: true });

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
