// Audits a page's endpoints for the mistake that broke employee_registration: seeding
// a returned variable with a value that makes the markup's guard take the wrong branch.
//
// The subtlety that a first version of this check got wrong: for the guard
// isset($x), a seed of null is fine, because isset() reports null as unset. Only ''
// and 0 and [] and false are truthy to isset() while being empty to a human reader.
//
//   node audit-isset-seeds.mjs
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const pages = fs.readdirSync(ROOT).filter(f => /\.php$/.test(f) && !/_pdf\.php$|_PDF\.php$/.test(f));

// Seeds that isset() considers SET, paired with the guard they can mislead.
const MISLEADING = /^(''|""|0|\[\]|array\(\)|false)$/;

const findings = [];
const nullSeeds = [];
const guardedSeeds = [];
for (const page of pages) {
  const slug = page.replace(/\.php$/, '');
  const pageSrc = fs.readFileSync(path.join(ROOT, page), 'utf8');
  const tested = new Set([...pageSrc.matchAll(/isset\(\$([a-z_][a-z0-9_]*)\)/g)].map(m => m[1]));
  if (!tested.size) continue;

  for (const cand of [`api/${slug}-endpoint.php`, `actions/${slug}-actions.php`]) {
    const full = path.join(ROOT, cand);
    if (!fs.existsSync(full)) continue;
    const src = fs.readFileSync(full, 'utf8');
    for (const name of tested) {
      const re = new RegExp(`'${name}'\\s*=>\\s*([^,\\n]+),`, 'g');
      for (const m of src.matchAll(re)) {
        const seed = m[1].trim();
        if (seed === 'null') { nullSeeds.push(`${page} ${cand} $${name}`); continue; }
        if (!MISLEADING.test(seed)) continue;
        // A '' seed is only safe when the page skips that key while unpacking, which is
        // how fuel_report.php handles its error_message. Check for that guard.
        const guarded = new RegExp(`\\$ocp_key === '${name}'`).test(pageSrc);
        if (guarded) guardedSeeds.push(`${page} $${name} = ${seed} (skipped while unpacking)`);
        else findings.push({ page, where: cand, name, seed });
      }
    }
  }
}

if (!findings.length) {
  console.log('OK: no endpoint seeds a variable with a value that isset() treats as set');
} else {
  console.log(`${findings.length} case(s) where isset() would see the variable as set:`);
  for (const f of findings) console.log(`  ${f.page.padEnd(34)} ${f.where.padEnd(40)} $${f.name} = ${f.seed}`);
}
if (guardedSeeds.length) {
  console.log(`\n${guardedSeeds.length} seed(s) made safe by a guard while unpacking:`);
  for (const s of guardedSeeds) console.log(`  ${s}`);
}
if (nullSeeds.length) {
  console.log(`\n${nullSeeds.length} seed(s) of null - isset() reports these as unset, so they are safe:`);
  for (const s of nullSeeds) console.log(`  ${s}`);
}
