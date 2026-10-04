// Independent check of the requested layout. Reads the tree only; changes nothing.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const errors = [];
const info = [];

const read = (p) => fs.readFileSync(path.join(ROOT, p), 'utf8');
const exists = (p) => fs.existsSync(path.join(ROOT, p));
const rootPhp = fs.readdirSync(ROOT, { withFileTypes: true })
  .filter(d => d.isFile() && d.name.endsWith('.php')).map(d => d.name).sort();

const REPORT_GENERATORS = new Set([
  'cash_on_hand_report_pdf.php', 'expenses_report_pdf.php', 'generate_gas_po_pdf.php',
  'generate_payroll_pdf.php', 'generate_payslip_pdf.php', 'generate_po_pdf.php',
  'generate_po_supplier_pdf.php', 'generate_pr_pdf.php', 'generate_pr_supplier_pdf.php',
  'generate_spare_parts_po_pdf.php', 'generate_spare_parts_pr_pdf.php',
  'generate_spare_parts_ws_pdf.php', 'generate_ws_pdf.php', 'job_order_slip_PDF.php',
  'fuel_report_pdf.php',
]);

/* ------------------------------------------------------- 1. required layout */
for (const d of ['actions', 'api', 'assets/css', 'assets/js', 'assets/images', 'config', 'includes']) {
  if (!exists(d)) errors.push(`missing directory: ${d}`);
}
for (const f of ['index.php', 'config/db_config.php', 'includes/top_bar.php',
  'includes/side_menu.php', 'includes/footer.php', 'includes/page_data.php']) {
  if (!exists(f)) errors.push(`missing file: ${f}`);
}
for (const gone of ['action', 'css', 'js', 'img', 'includes/db_config.php',
  'get_movement_details.php', 'get_wage_history.php', 'assets/demo', 'assets/img']) {
  if (exists(gone)) errors.push(`superseded path still present: ${gone}`);
}

/* ------------------------------------------ 2. one centralized DB connection */
for (const f of ['config/db_config.php']) {
  const src = read(f);
  if (!$ok(src)) errors.push(`${f}: does not look like a PDO connection`);
  function $ok(s) { return /\$pdo\s*=\s*new\s+PDO/.test(s); }
}

function checkDbConfig(relPath, label) {
  const src = read(relPath);
  if (/includes\/db_config\.php/.test(src)) errors.push(`${label}: still references includes/db_config.php`);
  if (!/db_config\.php/.test(src)) { errors.push(`${label}: no db_config reference`); return; }
  // every connection must point at config/db_config.php
  const refs = [...src.matchAll(/['"]([^'"]*db_config\.php)['"]/g)].map(m => m[1]);
  for (const r of refs) {
    if (!/(^|\/)config\/db_config\.php$/.test(r) && !/__DIR__\s*\.\s*'\/\.\.\/config\/db_config\.php'/.test(r)) {
      errors.push(`${label}: db_config path is "${r}"`);
    }
  }
}

/* ---------------------------------------------- 3. per-page assets and paths */
const endpointOnly = new Set(['get_movement_details.php', 'get_wage_history.php']);
let pagesChecked = 0;

for (const page of rootPhp) {
  const src = read(page);
  const isGenerator = REPORT_GENERATORS.has(page);
  const slug = page.replace(/\.php$/, '');
  pagesChecked++;

  // A page normally opens its own connection. A page whose fetching has moved into
  // its endpoint may instead delegate: the endpoint is required by the page, it opens
  // the connection, and the page needs none of its own. Both arrangements are fine;
  // what is not fine is neither.
  const slugForDb = page.replace(/\.php$/, '');
  const endpoint = exists(`api/${slugForDb}-endpoint.php`) ? read(`api/${slugForDb}-endpoint.php`) : '';
  const delegatesConnection = endpoint && /config\/db_config\.php/.test(endpoint)
    && new RegExp(`require\\s+__DIR__\\s*\\.\\s*'/api/${slugForDb}-endpoint\\.php'`).test(read(page));
  if (delegatesConnection) {
    info.push(`${page}: opens no connection of its own; api/${slugForDb}-endpoint.php does`);
  } else {
    checkDbConfig(page, page);
  }

  if (isGenerator) {
    // Report generators keep their original markup; nothing else may change.
    if (/assets\/css|assets\/js/.test(src)) errors.push(`${page}: report generator links page assets`);
    if (/<style\b/.test(src)) info.push(`${page}: keeps its own <style> block (original markup)`);
    continue;
  }

  // own stylesheet + script
  if (!src.includes(`assets/css/${slug}-style.css`)) errors.push(`${page}: missing assets/css/${slug}-style.css link`);
  else if (!exists(`assets/css/${slug}-style.css`)) errors.push(`${page}: assets/css/${slug}-style.css does not exist`);

  const ownScript = new RegExp(`src="(assets/js/${slug}(?:-\\d+)?\\.js(?:\\.php)?)"`);
  const m = ownScript.exec(src);
  if (!m) errors.push(`${page}: missing its own script tag`);
  else if (!exists(m[1])) errors.push(`${page}: ${m[1]} does not exist`);

  // shared chrome
  if (page !== 'index.php' && !src.includes('assets/js/app.js')) errors.push(`${page}: missing assets/js/app.js`);
  if (!src.includes('assets/css/app.css')) errors.push(`${page}: missing assets/css/app.css`);
  if (src.includes('includes/top_bar.php') && !src.includes('includes/page_data.php')
      && /ocp_page_data\(|\$__ocp_data/.test(src)) {
    errors.push(`${page}: renders a data island without requiring includes/page_data.php`);
  }

  // no leftover inline blocks
  if (/<style\b/.test(src)) errors.push(`${page}: still has an inline <style> block`);
  for (const sm of src.matchAll(/<script\b((?:(?!\bsrc\s*=)[^>])*)>([\s\S]*?)<\/script>/g)) {
    const body = sm[2];
    const inlineSnippet = body.length <= 120 && !/<\?php|<\?=/.test(body) && !sm[0].includes('\n');
    if (!inlineSnippet) errors.push(`${page}: still has an inline <script> block (${body.length} bytes)`);
  }

  // every referenced asset must exist
  for (const am of src.matchAll(/(?:href|src)="((?:assets|\.\.\/assets)\/[^"]+)"/g)) {
    const rel = am[1].replace(/^\.\.\//, '');
    if (!exists(rel)) errors.push(`${page}: referenced asset missing: ${am[1]}`);
  }

  // stale locations
  if (/(["'])action\//.test(src)) errors.push(`${page}: still points at action/`);
  if (/(["'])css\//.test(src)) errors.push(`${page}: still points at css/`);
  if (/(["'])img\//.test(src)) errors.push(`${page}: still points at img/`);
  if (/(["'])js\/scripts\.js/.test(src)) errors.push(`${page}: still points at js/scripts.js`);
  if (/(["'])assets\/img\//.test(src)) errors.push(`${page}: still points at assets/img/`);
}

/* ----------------------------------------------------- 4. includes and others */
for (const inc of ['top_bar.php', 'side_menu.php', 'footer.php']) {
  const src = read(`includes/${inc}`);
  if (/includes\/db_config\.php/.test(src)) errors.push(`includes/${inc}: stale db_config path`);
  if (inc !== 'footer.php' && !/config\/db_config\.php/.test(src)) errors.push(`includes/${inc}: no db_config`);
  for (const am of src.matchAll(/(?:href|src)="(assets\/[^"]+)"/g)) {
    if (!exists(am[1])) errors.push(`includes/${inc}: referenced asset missing: ${am[1]}`);
  }
}

// A <page>-functions.php holds helpers a page shares with its actions file. It must
// be required from both, or whichever entry point runs first will call an
// undefined function. It must also not open a connection of its own: it runs in the
// caller's scope.
for (const f of fs.readdirSync(path.join(ROOT, 'includes')).filter(n => /-functions\.php$/.test(n))) {
  const slug = f.replace(/-functions\.php$/, '');
  const src = read(`includes/${f}`);
  if (/\$pdo\s*=\s*new PDO|require.*config\/db_config\.php/.test(src)) {
    errors.push(`includes/${f}: opens its own connection; it should run in the caller's scope`);
  }
  if (!/function \w+\(/.test(src)) errors.push(`includes/${f}: defines no function`);
  const page = read(`${slug}.php`);
  const actions = exists(`actions/${slug}-actions.php`) ? read(`actions/${slug}-actions.php`) : '';
  const endpoint = exists(`api/${slug}-endpoint.php`) ? read(`api/${slug}-endpoint.php`) : '';
  // an actual require statement, not merely the name appearing in a comment
  const requiresIt = (text) => new RegExp(`require(_once)?\\s+[^;]*includes/${f.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`).test(text);

  // The page must reach the functions before its own code uses them. Requiring the
  // include directly does that; so does requiring the endpoint, because the endpoint
  // requires the include and the page pulls the endpoint in at the top.
  const requiresEndpoint = new RegExp(`require\\s+__DIR__\\s*\\.\\s*'/api/${slug}-endpoint\\.php'`).test(page);
  const pageReachesIt = requiresIt(page) || (requiresEndpoint && requiresIt(endpoint));
  if (!pageReachesIt) {
    errors.push(`${slug}.php: cannot reach includes/${f} - it requires neither the include nor an endpoint that requires it`);
  }
  if (actions && !requiresIt(actions)) {
    errors.push(`actions/${slug}-actions.php: does not require includes/${f}, so an action would call an undefined function`);
  }
}

const NO_DB = new Set(['logout.php', 'edit_gasoline_po.php']);
for (const f of fs.readdirSync(path.join(ROOT, 'actions'))) {
  // logout.php only clears the session and edit_gasoline_po.php is an empty
  // placeholder: neither needs the database.
  if (NO_DB.has(f)) continue;

  if (!/-actions\.php$/.test(f)) {
    // A standalone endpoint keeps its own connection.
    checkDbConfig(`actions/${f}`, `actions/${f}`);
    continue;
  }

  // A per-page actions file runs in its page's scope, so it inherits the page's
  // session and connection. The ones the page's JavaScript posts to directly
  // additionally carry a bootstrap so they work when called on their own.
  const src = read(`actions/${f}`);
  const slug = f.replace(/-actions\.php$/, '');
  const postedToDirectly = new RegExp(`actions/${slug}-actions\\.php`).test(
    fs.readdirSync(path.join(ROOT, 'assets/js'))
      .filter(j => j.startsWith(slug))
      .map(j => read(`assets/js/${j}`))
      .join('\n')
  );
  const hasBootstrap = /config\/db_config\.php/.test(src);
  if (postedToDirectly && !hasBootstrap) {
    errors.push(`actions/${f}: the page's JavaScript posts to it, but it cannot open a connection on its own`);
  }
  if (hasBootstrap && !/session_status\(\)/.test(src)) {
    errors.push(`actions/${f}: bootstraps a connection but never opens the session`);
  }
}
for (const f of fs.readdirSync(path.join(ROOT, 'api'))) {
  // Likewise, a per-page endpoint is pulled in by its page and returns data; it
  // never opens its own connection.
  if (/-endpoint\.php$/.test(f)) {
    const src = read(`api/${f}`);
    if (!/return \$ocp_endpoint;/.test(src)) errors.push(`api/${f}: does not return $ocp_endpoint`);
    continue;
  }
  checkDbConfig(`api/${f}`, `api/${f}`);
}

/* ------------------------- 4b. the per-page pair is actually wired into a page */
for (const page of rootPhp) {
  if (REPORT_GENERATORS.has(page) || endpointOnly.has(page)) continue;
  const src = read(page);
  const slug = page.replace(/\.php$/, '');
  const hasActions = exists(`actions/${slug}-actions.php`);
  const hasEndpoint = exists(`api/${slug}-endpoint.php`);
  // A page that still holds its own handling is not yet converted; only a page
  // that references one of the pair must reference it correctly.
  if (hasActions && src.includes(`actions/${slug}-actions.php`)
      && !new RegExp(`require(_once)?\\s+__DIR__\\s*\\.\\s*'/actions/${slug}-actions\\.php'`).test(src)) {
    errors.push(`${page}: references its actions file without an absolute require`);
  }
  if (hasEndpoint && src.includes(`api/${slug}-endpoint.php`)
      && !new RegExp(`require\\s+__DIR__\\s*\\.\\s*'/api/${slug}-endpoint\\.php'`).test(src)) {
    errors.push(`${page}: references its endpoint without an absolute require`);
  }
}

/* ------------------------------------------------- 5. generated script health */
let plainJs = 0, serverJs = 0;
for (const f of fs.readdirSync(path.join(ROOT, 'assets/js'))) {
  if (!f.endsWith('.js') && !f.endsWith('.js.php')) continue;
  const code = read(`assets/js/${f}`);
  if (f.endsWith('.js.php')) {
    serverJs++;
    const opens = (code.match(/<\?php|<\?=/g) || []).length;
    const closes = (code.match(/\?>/g) || []).length;
    if (opens !== closes) errors.push(`assets/js/${f}: unbalanced PHP tags (${opens}/${closes})`);
    if (!/ocp_js_raw|ocp_js_string/.test(code)) info.push(`assets/js/${f}: no island helpers used`);
  } else {
    plainJs++;
    if (/<\?php|<\?=/.test(code)) errors.push(`assets/js/${f}: plain JS file contains PHP`);
  }
}

/* --------------------------------------------------------------- 6. reporting */
console.log(`root pages                : ${rootPhp.length}`);
console.log(`  report generators       : ${[...REPORT_GENERATORS].filter(f => rootPhp.includes(f)).length}`);
console.log(`  application pages       : ${pagesChecked - [...REPORT_GENERATORS].filter(f => rootPhp.includes(f)).length}`);
console.log(`actions/ ${fs.readdirSync(path.join(ROOT, 'actions')).length}   api/ ${fs.readdirSync(path.join(ROOT, 'api')).length}   config/ 1   includes/ ${fs.readdirSync(path.join(ROOT, 'includes')).filter(f => f.endsWith('.php')).length}`);
console.log(`assets: css ${fs.readdirSync(path.join(ROOT, 'assets/css')).length} (incl. includes/)   js ${plainJs} plain + ${serverJs} server-rendered`);
console.log(`\nerrors: ${errors.length}`);
for (const e of errors) console.log('  X ' + e);
console.log(`info: ${info.length}`);
for (const i of info.slice(0, 12)) console.log('  - ' + i);
process.exit(errors.length ? 1 : 0);
