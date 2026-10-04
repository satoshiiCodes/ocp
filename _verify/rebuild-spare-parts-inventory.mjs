// Rebuilds the tail of spare_parts_inventory.php, which was truncated by an editing accident.
//
//   node rebuild-spare-parts-inventory.mjs [--apply]
//
// The surviving head (lines 1-149) plus the opening of the summary-table loop are intact.
// Everything after it went: the summary rows, the batches table, the movements table with its
// Actions column, the three movement modals, the initial-parts modal, the footer and the
// script tags.
//
// The markup comes from _restructure_backup/spare_parts_inventory.php, which is the same
// markup byte for byte - the restructure moved the queries out of the page but left the HTML
// alone - with two deliberate differences applied here:
//
//   * the summary row's Avg Price/Unit and Total Value are the batch-derived figures the
//     endpoint now publishes (avg_price_per_unit, stock_value), not the stored price;
//   * the movements table gains the Actions column the page is meant to have.
//
// The head is untouched, so the island, the includes and everything above the loop stay as
// they are.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const pageFile = `${ROOT}\\spare_parts_inventory.php`;
const backupFile = `${ROOT}\\_restructure_backup\\spare_parts_inventory.php`;

const pageRaw = fs.readFileSync(pageFile, 'utf8');
const pageEol = pageRaw.includes('\r\n') ? '\r\n' : '\n';
const pageLines = pageRaw.replace(/\r\n/g, '\n').split('\n');
const backup = fs.readFileSync(backupFile, 'utf8').replace(/\r\n/g, '\n').split('\n');

// ---- find the end of what survives: the summary loop's opening line
const loopAt = pageLines.findIndex(l => /<\?php foreach \(\$parts_inventory as \$item\):/.test(l));
if (loopAt < 0) { console.log('  could not find the summary loop in the page'); process.exit(1); }
const head = pageLines.slice(0, loopAt + 1);
console.log(`  keeping lines 1-${loopAt + 1} of the page (through the summary loop opening)`);

// ---- the summary loop body, from the backup, with the two figures swapped
const bLoopStart = backup.findIndex(l => /<\?php foreach \(\$parts_inventory as \$item\):/.test(l));
const bLoopEnd = backup.findIndex((l, i) => i > bLoopStart && /<\?php endforeach; \?>/.test(l));
if (bLoopStart < 0 || bLoopEnd < 0) { console.log('  could not find the summary loop in the backup'); process.exit(1); }

const summary = backup.slice(bLoopStart + 1, bLoopEnd + 1).map(line => {
  let out = line;
  // the average and the value now come from the batches, and the row must multiply out
  out = out.replace(
    /^\s*\$total_value = \$item\['quantity'\] \* \$item\['price_per_unit'\];\s*$/,
    "                                                // Quantity, average price and total value all come from the\n"
    + "                                                // batches the stock is held in, so the three figures on a row\n"
    + "                                                // multiply out. A part held in no batches falls back to its\n"
    + "                                                // inventory row, which the endpoint has already applied.\n"
    + "                                                $avg_price = (float) ($item['avg_price_per_unit'] ?? $item['price_per_unit']);\n"
    + "                                                $total_value = (float) ($item['stock_value'] ?? 0);");
  out = out.replace(
    /<td>â‚±<\?php echo number_format\(\$item\['price_per_unit'\], 2\); \?><\/td>/,
    "<td>₱<?php echo number_format($avg_price, 2); ?></td>");
  return out;
});
console.log(`  summary loop body: ${summary.length} lines from the backup`);

// ---- from the end of the summary table to the initial-parts modal, from the backup.
// The batches table is there; the movements table is rebuilt below, because the Actions
// column it needs was added after the restructure and so is not in the backup.
const bAfterSummary = bLoopEnd + 1;
const bModalStart = backup.findIndex((l, i) => i > bAfterSummary && /<!-- Initial Parts Modal -->/.test(l));
if (bModalStart < 0) { console.log('  could not find the initial-parts modal in the backup'); process.exit(1); }
const middle = backup.slice(bAfterSummary, bModalStart);
console.log(`  batches table: ${middle.length} lines from the backup`);

// The movements table body, from the backup, with an Actions cell added to each row: the
// page is meant to offer view, edit and delete on every movement. The buttons carry the ids
// and classes the page's own script binds to, and data-description is what the delete
// confirmation shows.
const bMovTable = middle.findIndex(l => /id="movementsTable"/.test(l));
if (bMovTable < 0) { console.log('  could not find the movements table in the backup'); process.exit(1); }
const bMovEnd = middle.findIndex((l, i) => i > bMovTable && /<\/table>/.test(l));
if (bMovEnd < 0) { console.log('  could not find the end of the movements table'); process.exit(1); }

const movementsTable = middle.slice(bMovTable, bMovEnd + 1).map(line => line);
// header: an Actions column last
for (let i = 0; i < movementsTable.length; i++) {
  if (/<th>Purpose<\/th>/.test(movementsTable[i])) {
    movementsTable[i] = movementsTable[i] + '\n' + movementsTable[i].replace('<th>Purpose</th>', '<th>Actions</th>');
  }
}
// each row: an Actions cell after the Purpose cell
const actionsCell = `                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info view-part-movement" data-id="__ID__" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning edit-part-movement" data-id="__ID__" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-part-movement" data-id="__ID__" data-description="" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>`;
for (let i = 0; i < movementsTable.length; i++) {
  if (/<td><\?php echo htmlspecialchars\(\$movement\['purpose'\] \?\? 'N\/A'\); \?><\/td>/.test(movementsTable[i])) {
    movementsTable[i] = movementsTable[i] + '\n'
      + actionsCell.replace(/__ID__/g, "<?php echo (int) $movement['id']; ?>").replace('data-description=""',
        'data-description="<?php echo htmlspecialchars(($movement[\'part_name\'] ?? \'\') . \' (\' . ($movement[\'part_number\'] ?? \'\') . \') \' . ($movement[\'movement_date\'] ?? \'\')); ?>"');
  }
}
const middleRebuilt = [...middle.slice(0, bMovTable), ...movementsTable, ...middle.slice(bMovEnd + 1)];
console.log(`  movements table: ${movementsTable.length} lines, with an Actions column added`);
console.log(`  batches + movements tables: ${middleRebuilt.length} lines`);

// ---- the modal and the closing markup, from the backup
const bModalEnd = backup.findIndex((l, i) => i > bModalStart && /^\s*<\/html>\s*$/.test(l));
if (bModalEnd < 0) { console.log('  could not find </html> in the backup'); process.exit(1); }
const initialModal = backup.slice(bModalStart, bModalEnd + 1);

// The three movement modals, built to the ids the page's script binds to and the field names
// the actions file reads. They are inserted before the initial-parts modal.
const techOptions = fs.readFileSync(`${ROOT}\\assets\\js\\spare_parts_inventory.js.php`, 'utf8');
void techOptions;
const movementModals = `
        <!-- View Parts Movement Modal -->
        <div class="modal fade" id="viewPartMovementModal" tabindex="-1" aria-labelledby="viewPartMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewPartMovementModalLabel"><i class="fas fa-eye me-1"></i> Parts Movement Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="partMovementDetails"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Parts Movement Modal -->
        <div class="modal fade" id="editPartMovementModal" tabindex="-1" aria-labelledby="editPartMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPartMovementModalLabel"><i class="fas fa-edit me-1"></i> Edit Parts Movement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="editPartMovementForm">
                        <input type="hidden" name="edit_part_movement" value="1">
                        <input type="hidden" name="movement_id" id="edit_part_movement_id">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Part</label>
                                <input type="text" class="form-control-plaintext" id="edit_part_info" readonly>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Type</label>
                                    <input type="text" class="form-control-plaintext" id="edit_part_type" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Source / Destination</label>
                                    <input type="text" class="form-control-plaintext" id="edit_part_source_target" readonly>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_part_movement_date" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_part_movement_date" name="movement_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_part_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_part_quantity" name="quantity" min="0.01" step="0.01" required>
                                <div class="form-text">Changing this moves the parts inventory quantity by the same difference.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_part_price_per_unit" class="form-label">Price per Unit (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_part_price_per_unit" name="price_per_unit" min="0" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_part_technician" class="form-label">Technician</label>
                                <select class="form-select" id="edit_part_technician" name="technician">
                                    <option value="">None</option>
                                    <?php foreach (($employees ?? []) as $employee): ?>
                                        <option value="<?php echo (int) $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['firstname'] . ' ' . $employee['lastname']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit_part_purpose" class="form-label">Purpose</label>
                                <input type="text" class="form-control" id="edit_part_purpose" name="purpose">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Parts Movement Modal -->
        <div class="modal fade" id="deletePartMovementModal" tabindex="-1" aria-labelledby="deletePartMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deletePartMovementModalLabel"><i class="fas fa-trash me-1"></i> Delete Parts Movement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="deletePartMovementForm">
                        <input type="hidden" name="delete_part_movement" value="1">
                        <input type="hidden" name="movement_id" id="delete_part_movement_id">
                        <div class="modal-body">
                            <p>Delete this movement?</p>
                            <p class="fw-bold" id="delete_part_movement_description"></p>
                            <p class="text-muted mb-0">The quantity it moved is taken back out of the parts inventory.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Movement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
`;
const tailRebuilt = movementModals.split('\n').concat(initialModal);
console.log(`  modal, movement modals, footer and closing markup: ${tailRebuilt.length} lines`);

const rebuilt = [...head, ...summary, ...middleRebuilt, ...tailRebuilt].join('\n');
const problems = [];

// ---- the movements table must have the Actions column, with the three buttons
if (!/<th>Actions<\/th>/.test(rebuilt)) problems.push('the movements table has no Actions column');
for (const cls of ['view-part-movement', 'edit-part-movement', 'delete-part-movement']) {
  if (!rebuilt.includes(cls)) problems.push(`the movements table has no .${cls} button`);
}
for (const id of ['viewPartMovementModal', 'editPartMovementModal', 'deletePartMovementModal', 'initialPartsModal']) {
  if (!rebuilt.includes(`id="${id}"`)) problems.push(`the page has no #${id}`);
}
// ---- the ids the page's own script binds to
const script = fs.readFileSync(`${ROOT}\\assets\\js\\spare_parts_inventory.js.php`, 'utf8');
// ids that come from the shared partials rather than this page
const fromPartials = new Set(['logoutLink', 'sidebarToggle']);
for (const m of script.matchAll(/getElementById\(['"]([A-Za-z0-9_-]+)['"]\)/g)) {
  if (fromPartials.has(m[1])) continue;
  if (!rebuilt.includes(`id="${m[1]}"`)) problems.push(`the script looks up #${m[1]}, which the page does not define`);
}
// ---- the summary must show the batch-derived average
if (!/\$avg_price = \(float\) \(\$item\['avg_price_per_unit'\]/.test(rebuilt)) problems.push('the summary row does not use avg_price_per_unit');
if (problems.length) {
  console.log(`\n  ${problems.length} problem(s) with the rebuild:`);
  for (const p of [...new Set(problems)]) console.log(`    X ${p}`);
  process.exit(1);
}

console.log(`\n  rebuilt file: ${rebuilt.split('\n').length} lines`);
if (APPLY) {
  fs.writeFileSync(pageFile, pageEol === '\r\n' ? rebuilt.replace(/\n/g, '\r\n') : rebuilt);
  console.log('  APPLIED');
} else {
  console.log('  DRY RUN');
}
