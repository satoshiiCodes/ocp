// Repoints every logout link at the file that actually exists.
//
//   node fix-logout-paths.mjs [--apply]
//
// The restructure moved action/logout.php to actions/logout.php, and three different
// spellings were left behind. A relative URL in a script resolves against the PAGE, not the
// script, and every page is at the site root - so the only correct path from a page is
// 'actions/logout.php':
//
//   action/logout.php      the pre-restructure directory - a 404
//   ../actions/logout.php  one level above the document - a 404
//   actions/logout.php     correct
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const findings = [];

const walk = (dir) => {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === '_restructure_backup' || entry.name === 'vendor' || entry.name === '_verify' || entry.name === 'backups') continue;
      walk(full);
    } else if (/\.(js|php)$/.test(entry.name)) {
      const raw = fs.readFileSync(full, 'utf8');
      if (!/logout\.php/.test(raw)) continue;
      const eol = raw.includes('\r\n') ? '\r\n' : '\n';
      let code = raw.replace(/\r\n/g, '\n');
      const before = code;

      // any spelling that is not the root-relative one
      code = code.replace(/(['"])(?:\.\.\/)+actions\/logout\.php\1/g, "'actions/logout.php'");
      code = code.replace(/(['"])action\/logout\.php\1/g, "'actions/logout.php'");

      if (code !== before) {
        findings.push({ file: path.relative(ROOT, full), fixed: true });
        if (APPLY) fs.writeFileSync(full, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
      }
    }
  }
};
walk(ROOT);

const wrong = [];
const check = (dir) => {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (['_restructure_backup', 'vendor', '_verify', 'backups'].includes(entry.name)) continue;
      check(full);
    } else if (/\.(js|php)$/.test(entry.name)) {
      fs.readFileSync(full, 'utf8').split(/\r?\n/).forEach((line, i) => {
        const m = /(['"])([^'"]*logout\.php)\1/.exec(line);
        if (m && m[2] !== 'actions/logout.php') wrong.push(`${path.relative(ROOT, full)}:${i + 1}  ${m[2]}`);
      });
    }
  }
};
check(ROOT);

console.log(`${findings.length} file(s) with a logout path to correct${APPLY ? ' - APPLIED' : ' (dry run)'}:`);
for (const f of findings) console.log(`  ${f.file}`);
if (wrong.length) {
  console.log(`\nstill not the root-relative path:`);
  for (const w of wrong) console.log(`  ${w}`);
} else {
  console.log('\nOK: every logout link is "actions/logout.php"');
}
