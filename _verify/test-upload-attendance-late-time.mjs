// Verifies the "Late Time (minutes)" field on upload_attendance.php.
//
//   node test-upload-attendance-late-time.mjs
//
// The field is filled from the check-in and break-in times by the page's script. It stayed
// empty because the script threw on load - before those listeners were attached - while
// reading the missing-employees block: the page hands that over as JSON text, and on an
// ordinary load the text is "null", which is truthy, so .employees was undefined.
//
// Times: check-in is late after 08:15, break-in after 13:15.
import { withBrowser } from './browser.mjs';

const problems = [];
const log = [];

await withBrowser(async ({ go, ev, errors }) => {
  await go('upload_attendance.php');

  if (errors.length) {
    problems.push(`the script threw on load: ${errors[0]}`);
    log.push(`page errors on load: ${errors.length} - ${errors[0]}`);
  } else {
    log.push('the script loads without throwing');
  }

  // open the Add Attendance Record modal
  await ev(`
    (() => {
      const b = [...document.querySelectorAll('button')].find(x => /add attendance/i.test(x.textContent));
      if (b) b.click();
      return !!b;
    })()
  `);
  await new Promise(r => setTimeout(r, 900));

  const cases = [
    { checkIn: '08:15', breakIn: '', expected: 0, why: 'exactly on time is not late' },
    { checkIn: '09:30', breakIn: '', expected: 75, why: '75 minutes after 08:15' },
    { checkIn: '08:15', breakIn: '13:45', expected: 30, why: '30 minutes after 13:15' },
    { checkIn: '09:30', breakIn: '13:45', expected: 105, why: 'both late, added together' },
    { checkIn: '07:00', breakIn: '12:00', expected: 0, why: 'early is never late' },
  ];

  for (const c of cases) {
    const got = await ev(`
      (() => {
        const set = (id, v) => {
          const el = document.getElementById(id);
          if (!el) return false;
          el.value = v;
          el.dispatchEvent(new Event('input', { bubbles: true }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
          return true;
        };
        const okIn = set('modal_check_in', ${JSON.stringify(c.checkIn)});
        const okBr = set('modal_break_in', ${JSON.stringify(c.breakIn)});
        const late = document.getElementById('modal_late_time');
        const info = document.getElementById('modal_calculation_info');
        return { okIn, okBr, late: late ? late.value : null, info: info ? info.innerHTML.length : 0 };
      })()
    `);
    const value = Number(got && got.late);
    const ok = got && got.okIn && got.okBr && value === c.expected;
    log.push(`check-in ${c.checkIn || '--:--'} / break-in ${c.breakIn || '--:--'} -> Late ${JSON.stringify(got ? got.late : null)} (expected ${c.expected})`);
    if (!ok) problems.push(`Late Time was ${got ? got.late : 'missing'} for ${c.checkIn}/${c.breakIn}, expected ${c.expected}`);

    // the "no records" notice must not be what fired, and the field must be explained
    if (c.expected > 0 && got && got.info === 0) problems.push(`no calculation info shown for ${c.checkIn}/${c.breakIn}`);
  }
}, { alerts: false });

for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
