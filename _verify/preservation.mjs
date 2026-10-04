// Content preservation: every string literal and identifier in a page's inline
// <script> blocks must still be present after the restructure, either in the
// generated script or in the PHP fragments captured into includes/partials/.
//
// Compared against the pre-restructure sources in _restructure_backup.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const BACKUP = path.join(ROOT, '_restructure_backup');
const errors = [];

const REPORT_GENERATORS = new Set([
  'cash_on_hand_report_pdf.php', 'expenses_report_pdf.php', 'generate_gas_po_pdf.php',
  'generate_payroll_pdf.php', 'generate_payslip_pdf.php', 'generate_po_pdf.php',
  'generate_po_supplier_pdf.php', 'generate_pr_pdf.php', 'generate_pr_supplier_pdf.php',
  'generate_spare_parts_po_pdf.php', 'generate_spare_parts_pr_pdf.php',
  'generate_spare_parts_ws_pdf.php', 'generate_ws_pdf.php', 'job_order_slip_PDF.php',
  'fuel_report_pdf.php',
]);

const SEG_RE = /<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g;
const SCRIPT_RE = /<script\b((?:(?!\bsrc\s*=)[^>])*)>([\s\S]*?)<\/script>/g;

// Content that deliberately moved into assets/js/app.js.
const APP_OWNED = new Set([
  'action/logout.php', 'actions/logout.php', 'sidebarToggle', 'sb-sidenav-toggled',
  'logoutLink', 'Logout', 'logout', 'Simple', 'sidebar', 'toggle', 'functionality',
  'Are you sure?', 'You want to logout from the system.', 'Yes, logout!',
  '#3085d6', '#d33', 'body', 'classList', 'sidenav', 'toggled', 'sure', 'want',
  'system', 'showCancelButton', 'confirmButtonColor', 'cancelButtonColor',
  'location', 'href', 'then', 'result', 'isConfirmed', 'action', 'api', 'actions',
]);

/** PHP escapes and JSON escapes mean the same text. */
function norm(s) {
  let out = s.replace(/\\u0027/g, "'").replace(/\\u0022/g, '"')
    .replace(/\\n|\\r|\\t/g, '\n').replace(/\\(['"`\\/])/g, '$1');
  if (out.includes('\n')) out = out.replace(/\s+/g, ' ');
  return out.trim();
}

const read = (p) => fs.readFileSync(p, 'utf8');
const exists = (p) => fs.existsSync(p);

// Files deliberately folded into a page's single actions file, recorded in
// _verify/folded-actions.json rather than guessed at from file names.
const FOLDED = new Map();
{
  const manifest = JSON.parse(read(path.join(ROOT, '_verify', 'folded-actions.json')));
  for (const e of manifest.folded) FOLDED.set(e.from, e);
}

const pages = fs.readdirSync(ROOT, { withFileTypes: true })
  .filter(d => d.isFile() && d.name.endsWith('.php')).map(d => d.name)
  .filter(f => !REPORT_GENERATORS.has(f)).sort();

let checked = 0;
let preserved = 0;

for (const page of pages) {
  const backupFile = path.join(BACKUP, page);
  if (!exists(backupFile)) { errors.push(`${page}: no backup to compare against`); continue; }
  const before = read(backupFile);
  const slug = page.replace(/\.php$/, '');

  // source inline script bodies
  const sources = [];
  for (const m of before.matchAll(SCRIPT_RE)) {
    const body = m[2];
    if (body.length <= 120 && !/<\?php|<\?=/.test(body) && !m[0].includes('\n')) continue;
    sources.push(body);
  }
  if (!sources.length) continue;
  checked++;

  // Words that appeared ONLY in JavaScript comments count as neither a string literal
  // nor a working identifier: a comment explains code that has been replaced, so its
  // prose is expected to go with it. Without this, rewriting a guarded block makes its
  // old comment ("... a backup for direct POST", "... an error during edit") look like
  // lost content.
  const commentOnly = new Set();
  {
    const inStrings = new Set();
    for (const body of sources) {
      for (const m of body.matchAll(/'[^'\n]{2,80}'|"[^"\n]{2,80}"|`[^`]{2,80}`/g)) {
        for (const w of m[0].slice(1, -1).matchAll(/(?<![\w$\\])[A-Za-z_$][\w$]{3,}/g)) inStrings.add(w[0]);
      }
    }
    const inCode = new Set();
    for (const body of sources) {
      // strip comments, then collect identifiers from what is left
      const code = body.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/^[ \t]*\/\/.*$/gm, ' ');
      for (const w of code.matchAll(/(?<![\w$\\])[A-Za-z_$][\w$]{3,}/g)) inCode.add(w[0]);
    }
    // A word counts as prose only if it appeared in a comment AND nowhere else in the
    // block. A DOM id or a variable that merely also appears in a comment is real
    // content, and losing it must still fail.
    const inComments = new Set();
    for (const body of sources) {
      for (const c of body.matchAll(/\/\*[\s\S]*?\*\/|^[ \t]*\/\/.*$/gm)) {
        for (const w of c[0].matchAll(/(?<![\w$\\])[A-Za-z_$][\w$]{3,}/g)) inComments.add(w[0]);
      }
    }
    for (const w of inComments) if (!inCode.has(w) && !inStrings.has(w)) commentOnly.add(w);
  }

  // generated files, in order. A page whose second block was merged into the
  // first, so that it has a single script file, reuses the first target.
  const targets = [];
  for (let i = 1; i <= sources.length; i++) {
    const base = i === 1 ? `${slug}.js` : `${slug}-${i}.js`;
    let found = null;
    for (const cand of [base, `${base}.php`]) {
      const p = path.join(ROOT, 'assets/js', cand);
      if (exists(p)) { found = p; break; }
    }
    if (!found && i > 1) {
      for (const cand of [`${slug}.js`, `${slug}.js.php`]) {
        const p = path.join(ROOT, 'assets/js', cand);
        if (exists(p)) { found = p; break; }
      }
    }
    if (found) targets.push(found);
  }
  if (!targets.length) {
    errors.push(`${page}: ${sources.length} source script block(s) but no generated file`);
    continue;
  }

  // corpus = generated scripts + the captured partials for this page, plus the
  // page's own actions and endpoint files. An action that used to live in its own
  // actions/xxx.php file, and the identifiers that named it, legitimately live in
  // the page's single actions file now, so their text has to be findable there.
  //
  // Also include the page's own data island. Markup that the page renders for the
  // script (a dropdown's <option> list, say) lives there now, and identifiers the
  // original inline block got from that markup - "unit" out of data-unit, for
  // instance - have to stay findable.
  const parts = targets.map(read);
  parts.push(read(page));
  const partialDir = path.join(ROOT, 'includes/partials', slug);
  if (exists(partialDir)) for (const f of fs.readdirSync(partialDir)) parts.push(read(path.join(partialDir, f)));
  for (const cand of [`actions/${slug}-actions.php`, `api/${slug}-endpoint.php`]) {
    const p = path.join(ROOT, cand);
    if (exists(p)) parts.push(read(p));
  }
  const corpus = norm(parts.join('\n'));

  const missing = [];

  // A literal that named one of the page's own actions files, e.g.
  // 'action/delete_expense_type.php', now names the page's single actions file.
  // That is the point of this restructure, so the old name is expected to be gone.
  // The rename is only forgiven when it is recorded in _verify/folded-actions.json
  // AND the file it moved into really carries that operation on that table, so a
  // lost or gutted handler still fails this check.
  const foldedAway = (value) => {
    // A URL may carry a query string, e.g. 'action/get_pr_details.php?id='. The
    // manifest records the file, so the lookup is on the path portion.
    const entry = FOLDED.get(value) || FOLDED.get(value.split('?')[0]);
    if (!entry) return false;
    if (exists(path.join(ROOT, value.split('?')[0]))) return false;   // still its own file
    const target = path.join(ROOT, entry.to);
    if (!exists(target)) return false;
    const src = read(target);
    // a read that moved into a per-page endpoint: the target must carry the query
    if (entry.kind === 'endpoint') {
      return typeof entry.read === 'string' && entry.read.length > 0 && src.includes(entry.read);
    }
    // an action: the target must perform that operation on that table
    return new RegExp(`${entry.op.replace(' ', '\\s+')}\\s+${entry.table}\\b`, 'i').test(src);
  };

  sources.forEach((body) => {
    // placeholder for each PHP island so a literal split by PHP stays one string
    const js = body.replace(SEG_RE, '\u0001');
    for (const m of js.matchAll(/'[^'\n]{2,80}'|"[^"\n]{2,80}"|`[^`]{2,80}`/g)) {
      const value = norm(m[0].slice(1, -1).replace(/[\u0001\s]+$/, ''));
      if (value.length < 2 || APP_OWNED.has(value)) continue;
      if (foldedAway(value)) continue;
      const want = norm(value.replace(/^action\/(.+)$/,
        (mm, rest) => (/(get_items|get_pr_details|get_pr_edit_data|get_spare_parts_pr_details|get_suppliers|get_user|get_gasoline_po_details)\.php/.test(rest) ? 'api/' : 'actions/') + rest));
      if (corpus.includes(want)) continue;
      // A URL that named one of the page's own files. When the manifest records that
      // file as folded away and the file is really gone, the request is now served by
      // the page's endpoint: the old URL is expected to be absent, provided the new
      // one - the page's endpoint, with the same query - appears in the page's files.
      //
      // The literal may be a bare filename ('get_movement_details.php?id=') or carry a
      // directory ('api/get_movement_details.php?id='), so the manifest is matched on
      // the filename.
      //
      // Whether the new URL actually resolves is checked elsewhere: layout.mjs
      // requires every file a page references to exist, and runtime.mjs fetches each
      // endpoint. This check is only about not losing the old name silently.
      const oldPath = value.split('?')[0];
      const query = value.slice(oldPath.length);
      const candidates = [...FOLDED.values()].filter(x => x.from === oldPath || x.from.endsWith('/' + oldPath));
      const owned = candidates.filter(x => x.to === `api/${slug}-endpoint.php` || x.to === `actions/${slug}-actions.php`);
      // The manifest records the same fold under both the old action/ path and the
      // newer actions/ or api/ one, so the candidates are deduplicated by target: two
      // entries pointing at one file are one fold, not an ambiguity.
      const targets = [...new Set((owned.length ? owned : candidates).map(x => x.to))];
      const foldedEntry = targets.length === 1 ? { to: targets[0] } : null;
      // If the file the manifest says was folded away is back, the old name is not a
      // rename at all and must be reported.
      const restored = candidates.some(x => exists(path.join(ROOT, x.from)) || exists(path.join(ROOT, oldPath)));
      if (foldedEntry && !restored) {
        const target = foldedEntry.to.startsWith('api/') ? `api/${slug}-endpoint.php${query}` : foldedEntry.to;
        if (corpus.includes(norm(target))) continue;
      }
      if (/^<option value="[^"]*">[^<]*<\/option>$/.test(value) && corpus.includes('<option value="">')) continue;
      missing.push(`text ${JSON.stringify(value.slice(0, 50))}`);
    }
    for (const m of js.matchAll(/(?<![\w$\\])[A-Za-z_$][\w$]{3,}/g)) {
      const id = m[0];
      if (APP_OWNED.has(id) || corpus.includes(id)) continue;
      if (foldedAway(`actions/${id}.php`)) continue;
      missing.push(`identifier ${id}`);
    }
  });

  if (missing.length) errors.push(`${page}: ${missing.length} item(s) missing, e.g. ${missing.slice(0, 3).join(', ')}`);
  else preserved++;
}

console.log(`pages with inline scripts : ${checked}`);
console.log(`fully preserved           : ${preserved}`);
console.log(`errors: ${errors.length}`);
for (const e of errors.slice(0, 30)) console.log('  X ' + e);
process.exit(errors.length ? 1 : 0);
