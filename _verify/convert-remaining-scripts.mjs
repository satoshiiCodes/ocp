// Converts the last seven server-rendered scripts' PHP conditionals to client-side reads.
//
//   node convert-remaining-scripts.mjs [--apply] [<slug> ...]
//
// A script under assets/js/*.js.php is fetched as its OWN request, so none of its page's
// variables are in scope. These seven still contain PHP that tests and echoes them, so a
// message was dropped, a modal never reopened, or a value came out empty when the script
// was requested on its own. Each page already publishes the answers in its data island.
//
// The shapes are recognised rather than listed one by one, so a chain whose arms are all
// $_POST['action'] values becomes a switch, and a chain over $swal_data['icon'] becomes a
// test of the page's isError / isSuccess flag - whatever the surrounding spacing is.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const only = process.argv.slice(2).filter(a => a !== '--apply');

const constOf = (slug) => slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase() + '_DATA';
const islandOf = (slug) => 'window.OCP_PAGE_' + slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase();

// ---------------------------------------------------------------- guard shapes

/** Turn one PHP condition into a JavaScript test on the page's island, or null. */
function testFor(cond, CONST) {
  const c = cond.trim();
  if (/^!empty\(\$swal_data\)$/.test(c)) return `${CONST}.hasMessage`;
  if (/^isset\(\$_SESSION\['alert'\]\)$/.test(c)) return `${CONST}.hasMessage`;
  if (/^!empty\(\$swal_message\)$/.test(c)) return `${CONST}.hasMessage`;
  if (/^!empty\(\$message\)$/.test(c)) return `${CONST}.hasMessage`;
  if (/^isset\(\$alert\)$/.test(c)) return `${CONST}.hasAlert`;

  // an action post: the page supplies the action that was posted
  if (/\$_POST\['action'\]/.test(c) && /\$swal_data\['icon'\]/.test(c)) return `${CONST}.isError && ${CONST}.postedAction`;
  if (/\$_SERVER\['REQUEST_METHOD'\]/.test(c) && /\$swal_data\['icon'\]/.test(c) && /'error'/.test(c)) return `${CONST}.isError && ${CONST}.postedAction`;
  if (/\$_SERVER\['REQUEST_METHOD'\]/.test(c) && /isset\(\$swal_data\['icon'\]\)/.test(c)) return `${CONST}.isError && ${CONST}.postedAction`;
  if (/\$_POST\['action'\]/.test(c) && /isset\(\$_POST\['action'\]\)/.test(c)) return `${CONST}.isError && ${CONST}.postedAction`;

  if (/^\$swal_data\['icon'\] === 'success'$/.test(c)) return `${CONST}.isSuccess`;
  if (/^\$swal_data\['icon'\] === 'error'$/.test(c)) return `${CONST}.isError`;
  if (/^\$swal_data\['icon'\] === 'success' && \$_SERVER\['REQUEST_METHOD'\] === 'POST'$/.test(c)) return `${CONST}.isSuccessOnPost`;
  if (/^\$_SESSION\['alert'\]\['type'\] === 'success'$/.test(c)) return `${CONST}.isSuccess`;
  if (/^isset\(\$_SESSION\['alert'\]\) && \$_SESSION\['alert'\]\['type'\] === 'success'$/.test(c)) return `${CONST}.isSuccess`;
  if (/^isset\(\$_POST\['edit_movement'\]\)$/.test(c)) return null;   // handled as a switch arm
  if (/^\$is_issue_materials$/.test(c)) return `${CONST}.isMaterialRequest`;
  if (/^\$_SERVER\['REQUEST_METHOD'\] === 'POST'$/.test(c)) return `${CONST}.wasPost`;
  if (/^isset\(\$_POST\['edit_id'\]\)$/.test(c)) return `${CONST}.postedEditId !== ''`;
  if (/^!isset\(\$_POST\['delete_id'\]\)$/.test(c)) return `${CONST}.postedDeleteId === ''`;
  if (/^\$_POST\['action'\] === '([a-z_]+)'$/.test(c)) return null;   // a switch arm
  return null;
}

/** The action value a switch arm tests, or undefined. */
function actionOf(cond) {
  const m = /^\$_POST\['action'\] === '([a-z_]+)'$/.exec(cond.trim());
  return m ? m[1] : undefined;
}

// ---------------------------------------------------------------- the converter

function convert(lines, slug, CONST) {
  const out = [];
  let i = 0;

  while (i < lines.length) {
    const open = /^([ \t]*)<\?php if \((.*)\): \?>$/.exec(lines[i]);
    if (!open) {
      out.push(lines[i]);
      i++;
      continue;
    }
    const indent = open[1];

    // gather this chain's arms, honouring nesting
    const arms = [{ cond: open[2], body: [] }];
    let j = i + 1;
    let nest = 0;
    while (j < lines.length) {
      const l = lines[j];
      const anyOpen = /<\?php if \(/.test(l);
      const anyEnd = /<\?php endif; \?>/.test(l);
      const sibElseIf = new RegExp(`^${indent}<\\?php elseif \\((.*)\\): \\?>$`).exec(l);
      const sibElse = new RegExp(`^${indent}<\\?php else\\s*:\\s*\\?>$`).exec(l);
      const sibEnd = new RegExp(`^${indent}<\\?php endif; \\?>$`).test(l);
      if (nest === 0 && sibElseIf) { arms.push({ cond: sibElseIf[1], body: [] }); j++; continue; }
      if (nest === 0 && sibElse) { arms.push({ cond: null, body: [] }); j++; continue; }
      if (nest === 0 && sibEnd) { j++; break; }
      if (anyOpen) nest++;
      if (anyEnd) nest--;
      arms[arms.length - 1].body.push(l);
      j++;
    }

    // shape 1: every arm but possibly the last tests an action value -> a switch
    const actionValues = arms.map(a => (a.cond === null ? undefined : actionOf(a.cond)));
    const isSwitch = arms.length > 1 && actionValues.slice(0, -1).every(v => v !== undefined)
      && (actionValues[actionValues.length - 1] !== undefined || arms[arms.length - 1].cond === null
        || /isset\(\$_POST\['edit_movement'\]\)/.test(arms[arms.length - 1].cond || ''));

    if (isSwitch) {
      out.push(`${indent}// Which modal to reopen comes from the page's island: this script is its own`);
      out.push(`${indent}// request, so $_POST is not in scope here.`);
      out.push(`${indent}switch (${CONST}.postedAction) {`);
      arms.forEach((arm, k) => {
        const key = actionValues[k] !== undefined ? actionValues[k] : '__edit_movement__';
        out.push(`${indent}    case '${key}':`);
        for (const b of convert(arm.body, slug, CONST)) out.push(b.trim() ? indent + '        ' + b.trim() : '');
        out.push(`${indent}        break;`);
      });
      out.push(`${indent}}`);
      i = j;
      continue;
    }

    // shape 2: ordinary branches
    const tests = arms.map(a => (a.cond === null ? null : testFor(a.cond, CONST)));
    if (tests.every(t => t === null)) {
      // Nothing mapped: leave the block as it is rather than emitting a comment that
      // would leave the PHP orphaned.
      unconverted.push(`if (${arms[0].cond})`);
      for (let k = i; k < j; k++) out.push(lines[k]);
      i = j;
      continue;
    }

    out.push(`${indent}// Tested here in the browser: this script is its own request, so the`);
    out.push(`${indent}// page's variables are not in scope.`);
    arms.forEach((arm, k) => {
      const head = k === 0
        ? `if (${tests[0]}) {`
        : arm.cond === null ? '} else {'
          : `} else if (${tests[k]}) {`;
      out.push(`${indent}${head}`);
      out.push(...convert(arm.body, slug, CONST));
    });
    out.push(`${indent}}`);
    i = j;
  }
  return out;
}

// ---------------------------------------------------------------- echoes

const ECHO_KEYS = {
  '$swal_data[\'title\']': 'swalDataTitle',
  '$swal_data[\'text\']': 'swalDataText',
  '$swal_data[\'icon\']': 'swalDataIcon',
  '$swal_data[\'html\']': 'swalData',
  '$is_issue_materials ? \'Materials\' : \'Spare Parts\'': 'isIssueMaterials',
  '$technician_formatted': 'technicianFormatted',
  '$pr[\'supplier_name\'] ?? \'N/A\'': 'prSupplierName',
  '$pr[\'vehicle_name\'] ?? $pr[\'equipment_name\'] ?? \'N/A\'': 'prVehicleName',
};

function convertEchoes(code, CONST) {
  let n = 0;
  for (const [php, key] of Object.entries(ECHO_KEYS)) {
    const esc = php.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    // inside a template literal -> interpolation; otherwise -> a plain read
    const before = code;
    code = code.replace(new RegExp(`'<\\?php echo \\${esc}; \\?>'`, 'g'), `${CONST}.${key} || ''`);
    code = code.replace(new RegExp(`<\\?php echo \\${esc}; \\?>`, 'g'), '${' + `${CONST}.${key} || ''` + '}');
    if (code !== before) n++;
  }
  return { code, n };
}

const unconverted = [];
let processed = 0;
for (const file of fs.readdirSync(`${ROOT}\\assets\\js`).filter(f => f.endsWith('.js.php')).sort()) {
  const slug = file.replace(/\.js\.php$/, '');
  const full = `${ROOT}\\assets\\js\\${file}`;
  const raw = fs.readFileSync(full, 'utf8');
  // note: the echo pass must still run on a file whose conditionals are already gone
  if (!/<\?php/.test(raw)) continue;
  if (only.length && !only.includes(slug)) continue;

  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  const CONST = constOf(slug);
  let code = raw.replace(/\r\n/g, '\n');

  const { code: afterEchoes } = convertEchoes(code, CONST);
  code = afterEchoes;

  const lines = code.split('\n');
  const result = convert(lines, slug, CONST).join('\n');

  const left = result.split('\n').filter(l => /<\?php (if|elseif|else|endif)/.test(l));
  console.log(`  ${file}: ${left.length} left${left.length ? ' -> ' + left[0].trim().slice(0, 70) : ''}`);
  if (APPLY && left.length === 0) fs.writeFileSync(full, eol === '\r\n' ? result.replace(/\n/g, '\r\n') : result);
  processed++;
}
console.log(`${processed} file(s)${APPLY ? ' written' : ' (dry run)'}`);
