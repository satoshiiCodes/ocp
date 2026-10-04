// Replaces the stock-summary query in api/spare_parts_inventory-endpoint.php with one whose
// Quantity, Avg Price/Unit and Total Value all come from the same aggregation.
//
//   node fix-summary-query.mjs [--apply]
//
// "Avg Price/Unit" was the single price stored on the inventory row - the price of whichever
// delivery last wrote it, not an average of anything. It became the weighted average of the
// batches, but the Quantity beside it was still the stored figure, and on part 13 the two
// disagreed (summary 5, batches 20) so the row did not multiply out.
//
// All three now come from the batches - what the FIFO issue path actually draws from - with
// the stored row as the fallback for a part held in no batches, and the status badge computed
// from the same figure that is shown.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\api\\spare_parts_inventory-endpoint.php`;

const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
const lines = raw.replace(/\r\n/g, '\n').split('\n');

// find the comment that introduces the query and the statement's end
const start = lines.findIndex(l => /The stock summary, with each part's status/.test(l));
if (start < 0) { console.log('  could not find the summary query comment'); process.exit(1); }
let stmtStart = lines.findIndex((l, i) => i > start && /\$inventoryStmt = \$pdo->prepare\("/.test(l));
if (stmtStart < 0) { console.log('  could not find $inventoryStmt'); process.exit(1); }
let end = lines.findIndex((l, i) => i > stmtStart && /^\s*"\);$/.test(l));
if (end < 0) { console.log('  could not find the end of the statement'); process.exit(1); }

const replacement = `    // The stock summary, with each part's status against its minimum level.
    //
    // "Avg Price/Unit" used to be the single price stored on the inventory row - the price of
    // whichever delivery last wrote it, which is not an average of anything.
    //
    // Quantity, Avg Price/Unit and Total Value are now all derived from the same place: the
    // batches the stock is actually held in. The average is their value divided by their
    // quantity, so the row reads Quantity x Avg Price/Unit = Total Value by construction, and
    // an average can never disagree with the figure printed beside it.
    //
    // Deriving the quantity here also settles a real disagreement rather than hiding it: on
    // part 13 the inventory row said 5 where the batches held 20, and an average of the
    // batches shown against the stored 5 cannot multiply out. The batches are what the FIFO
    // issue path draws from, so they are the stock. A part held in no batches falls back to
    // its inventory row, and the status is computed from the same figure that is shown, so
    // the badge can never contradict the number.
    $inventoryStmt = $pdo->prepare("
        SELECT
            sp.id as part_id,
            sp.part_number,
            sp.part_name,
            sp.description,
            spc.category_name,
            sp.unit_of_measure,
            COALESCE(sp.min_stock_level, 0) as min_stock_level,
            COALESCE(spi.price_per_unit, 0) as price_per_unit,
            COALESCE(stock.quantity, spi.quantity, 0) as quantity,
            COALESCE(stock.value, spi.quantity * spi.price_per_unit, 0) as stock_value,
            COALESCE(stock.avg_price,
                     CASE WHEN COALESCE(spi.quantity, 0) > 0 THEN spi.price_per_unit ELSE 0 END,
                     0) as avg_price_per_unit,
            CASE
                WHEN COALESCE(stock.quantity, spi.quantity, 0) <= 0 THEN 'out-of-stock'
                WHEN COALESCE(stock.quantity, spi.quantity, 0) <= COALESCE(sp.min_stock_level, 0)
                     AND COALESCE(sp.min_stock_level, 0) > 0 THEN 'low-stock'
                ELSE 'normal'
            END AS stock_status
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        LEFT JOIN (
            SELECT part_id,
                   SUM(quantity) AS quantity,
                   SUM(quantity * price_per_unit) AS value,
                   SUM(quantity * price_per_unit) / SUM(quantity) AS avg_price
            FROM spare_parts_batches
            WHERE quantity > 0
            GROUP BY part_id
        ) stock ON stock.part_id = sp.id
        ORDER BY sp.part_name
    ");`;

const out = [...lines.slice(0, start), ...replacement.split('\n'), ...lines.slice(end + 1)].join('\n');
console.log(`  replacing lines ${start + 1}-${end + 1} (${end - start + 1} lines) with ${replacement.split('\n').length}`);
if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? out.replace(/\n/g, '\r\n') : out);
  console.log('  APPLIED');
} else {
  console.log('  DRY RUN');
}
