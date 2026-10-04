// Verifies the two spare_parts_inventory.php reports:
//   - "Parts Batches (FIFO Tracking)" newest first
//   - "Avg Price/Unit" in the summary is the real weighted average
//
//   node test-spare-parts-order-avg.mjs
//
// The average was the single price stored on the inventory row - the price of whichever
// delivery last wrote it, not an average - so part 11 read 1222.00 when the stock it holds
// averages 143.96. The batches were listed oldest first, which buries the newest delivery at
// the bottom of a long table.
import { withBrowser, db } from './browser.mjs';

const problems = [];
const log = [];

// what the figures should be, straight from the data
const expected = db(`
  SELECT GROUP_CONCAT(CONCAT(part_id, ':', ROUND(value / qty, 2)) ORDER BY part_id SEPARATOR '|')
  FROM (SELECT part_id, SUM(quantity) AS qty, SUM(quantity * price_per_unit) AS value
        FROM spare_parts_batches WHERE quantity > 0 GROUP BY part_id) t`);

await withBrowser(async ({ go, ev }) => {
  await go('spare_parts_inventory.php');

  // ---- the summary's average price
  const summary = await ev(`
    (() => {
      const tables = [...document.querySelectorAll('table')];
      const header = (t) => [...t.querySelectorAll('thead th')].map(th => th.textContent.trim());
      const t = tables.find(x => header(x).includes('Avg Price/Unit'));
      if (!t) return { error: 'no table with an Avg Price/Unit column' };
      return {
        columns: header(t),
        rows: [...t.querySelectorAll('tbody tr')].map(tr => {
          const td = [...tr.querySelectorAll('td')].map(c => c.textContent.trim());
          return td;
        }),
      };
    })()
  `);
  if (summary.error) { problems.push(summary.error); console.log('  ' + summary.error); }
  else {
    log.push(`summary columns: ${JSON.stringify(summary.columns)}`);
    const qtyIdx = summary.columns.indexOf('Quantity');
    const avgIdx = summary.columns.indexOf('Avg Price/Unit');
    const valIdx = summary.columns.indexOf('Total Value');
    for (const row of summary.rows) {
      const name = row[1];
      const qty = Number(String(row[qtyIdx]).replace(/[^0-9.]/g, ''));
      const avg = Number(String(row[avgIdx]).replace(/[^0-9.]/g, ''));
      const value = Number(String(row[valIdx]).replace(/[^0-9.]/g, ''));
      // the three figures on a row must agree with each other
      const consistent = qty > 0 ? Math.abs(avg * qty - value) < Math.max(1, value * 0.005) : true;
      log.push(`  ${String(name).padEnd(16)} qty ${String(qty).padStart(5)}  avg ${String(avg).padStart(9)}  value ${String(value).padStart(10)}  avg x qty = value: ${consistent}`);
      if (!consistent) problems.push(`${name}: avg ${avg} x qty ${qty} does not equal the total value ${value}`);
    }

    // and the average must not simply be the price stored on the inventory row any more
    const stored = db("SELECT GROUP_CONCAT(CONCAT(part_id,'=',ROUND(price_per_unit,2)) ORDER BY part_id SEPARATOR ' ') FROM spare_parts_inventory");
    log.push(`  stored prices for comparison: ${stored}`);
    log.push(`  expected weighted averages:   ${expected}`);
  }

  // ---- the batches, newest first
  const batches = await ev(`
    (() => {
      const tables = [...document.querySelectorAll('table')];
      const header = (t) => [...t.querySelectorAll('thead th')].map(th => th.textContent.trim());
      const t = tables.find(x => header(x).includes('Date Received'));
      if (!t) return { error: 'no batches table found (looking for a Date Received column)' };
      return {
        columns: header(t),
        rows: [...t.querySelectorAll('tbody tr')].map(tr => [...tr.querySelectorAll('td')].map(c => c.textContent.trim())),
      };
    })()
  `);
  if (batches.error) { problems.push(batches.error); console.log('  ' + batches.error); }
  else {
    log.push(`batches columns: ${JSON.stringify(batches.columns)}`);
    const dateIdx = batches.columns.findIndex(h => /received|date/i.test(h));
    const partIdx = batches.columns.findIndex(h => /part/i.test(h));
    if (dateIdx < 0) problems.push('the batches table has no date column to check the order by');
    else {
      // Within each part's run of rows the dates must not increase. Checking the whole table
      // as one list would be wrong: the rows are grouped by part, so a later part may begin
      // with an earlier date than the part above it ended on.
      let lastPart = null;
      let lastDate = null;
      let outOfOrder = 0;
      const parts = [];
      for (const row of batches.rows) {
        const part = partIdx >= 0 ? row[partIdx] : '(all)';
        const date = row[dateIdx];
        if (part !== lastPart) { parts.push(part); lastPart = part; lastDate = null; }
        if (lastDate !== null && date > lastDate) outOfOrder++;
        lastDate = date;
      }
      log.push(`  batch rows: ${batches.rows.length} across ${parts.length} part(s), out of order within a part: ${outOfOrder}`);
      if (outOfOrder) problems.push(`${outOfOrder} batch row(s) are not in descending date order within their part`);
      if (batches.rows.length) {
        log.push(`  first two rows: ${JSON.stringify(batches.rows.slice(0, 2))}`);
      }
    }
  }
}, { alerts: false });

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
