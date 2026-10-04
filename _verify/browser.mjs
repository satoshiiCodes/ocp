// A tiny reusable headless-Chrome driver for these page checks.
//
//   import { withBrowser } from './browser.mjs';
//   await withBrowser(async ({ page, go, ev, alerts, errors }) => { ... });
//
// Ports and the profile directory are per-run so several checks can run without colliding.
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

export const ROOT = 'E:\\laragon\\www\\OCP';
export const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

export const php = (file, args = []) =>
  execFileSync('php', [path.join(ROOT, '_verify', file), ...args], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
export const db = (sql) => php('query.php', [sql]).trim();
export const sessionId = () => JSON.parse(php('sessions.php'))[0].sid;
export const sleep = (ms) => new Promise(r => setTimeout(r, ms));

let nextPort = 8400;
export const freePort = () => String(nextPort++);

/**
 * Starts the PHP server and headless Chrome, hands the caller a small API, then tears
 * both down. Alerts are captured from before the document runs, because most of these
 * pages fire their SweetAlert on DOMContentLoaded.
 */
export async function withBrowser(fn, { alerts: wantAlerts = true } = {}) {
  const phpPort = freePort();
  const cdpPort = freePort();
  const profile = path.join(os.tmpdir(), `ocp-cdp-${cdpPort}`);
  const SID = sessionId();

  const app = spawn('php', ['-S', `127.0.0.1:${phpPort}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
  await sleep(1500);
  // Chrome holds a lock on its profile for a moment after exit, so this must never fail the
  // run: a stale directory is harmless, a thrown error here hides the result of the test.
  try {
    fs.rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
  } catch { /* a previous run's profile is still locked; use a fresh one */ }
  const chrome = spawn(CHROME, [
    '--headless=new', `--remote-debugging-port=${cdpPort}`, `--user-data-dir=${profile}`,
    '--no-first-run', '--no-default-browser-check', '--disable-gpu', 'about:blank',
  ], { stdio: 'ignore' });

  let target = null;
  for (let i = 0; i < 40 && !target; i++) {
    await sleep(400);
    try {
      target = (await (await fetch(`http://127.0.0.1:${cdpPort}/json/list`)).json()).find(t => t.type === 'page');
    } catch { /* not up yet */ }
  }
  if (!target) { chrome.kill(); app.kill(); throw new Error('could not reach Chrome DevTools'); }

  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });

  let nextId = 1;
  const pending = new Map();
  const errors = [];
  const requests = [];
  ws.onmessage = (e) => {
    const m = JSON.parse(e.data);
    if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); return; }
    if (m.method === 'Runtime.exceptionThrown') {
      const d = m.params.exceptionDetails;
      errors.push(`${d.text} ${d.exception?.description || ''}`.replace(/\s+/g, ' ').slice(0, 220));
    }
    if (m.method === 'Network.requestWillBeSent' && /-actions\.php|-endpoint\.php/.test(m.params.request.url)) {
      requests.push({ url: m.params.request.url, method: m.params.request.method, postData: m.params.request.postData || '' });
    }
  };

  const send = (method, params = {}) => new Promise((r) => {
    const id = nextId++;
    // A CDP call can go unanswered when the page navigates out from under it - a logout
    // redirect does exactly that. Unbounded, one lost reply hangs the whole run and says
    // nothing about why.
    const timer = setTimeout(() => {
      if (pending.has(id)) {
        pending.delete(id);
        console.log(`  ! DevTools did not answer ${method} within 20s`);
        r({ __timeout: method });
      }
    }, 20000);
    pending.set(id, (msg) => { clearTimeout(timer); r(msg); });
    ws.send(JSON.stringify({ id, method, params }));
  });
  const ev = async (expression) => {
    const r = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (r && r.__timeout) return { __error: `DevTools did not answer within 20s` };
    const ex = r.result?.exceptionDetails;
    if (ex) return { __error: `${ex.text} ${ex.exception?.description || ''}`.slice(0, 200) };
    return r.result?.result?.value;
  };

  await send('Runtime.enable');
  await send('Network.enable');
  await send('Page.enable');
  if (wantAlerts) {
    // Wrapped in a function: a top-level `const` here would be a global binding, and
    // re-injecting it for the next document threw "Identifier already declared".
    //
    // Swal is patched the instant it appears, not on a timer. SweetAlert2 arrives from a CDN
    // and the page's own script calls Swal.fire as soon as it runs, so a polling hook can
    // lose the race - the popup appears, window.__alerts stays empty, and the check reports a
    // page that plainly works as broken. It did exactly that about half the time. Defining a
    // setter on window.Swal means the wrapper is in place before any caller can reach it.
    await send('Page.addScriptToEvaluateOnNewDocument', {
      source: `(function () {
        window.__alerts = [];
        window.__hooked = false;
        var wrap = function (swal) {
          if (!swal || typeof swal.fire !== 'function' || swal.fire.__ocpWrapped) return swal;
          var original = swal.fire.bind(swal);
          var wrapper = function (a) { window.__alerts.push(JSON.stringify(a)); return original(a); };
          wrapper.__ocpWrapped = true;
          swal.fire = wrapper;
          window.__hooked = true;
          return swal;
        };
        // intercept the moment the CDN script assigns window.Swal
        try {
          var held = window.Swal;
          Object.defineProperty(window, 'Swal', {
            configurable: true,
            get: function () { return held; },
            set: function (v) { held = wrap(v); }
          });
          if (held) wrap(held);
        } catch (e) { /* a non-configurable Swal would be unusual; the timer below covers it */ }
        // and a timer as a backstop, for a Swal that was assigned some other way
        setInterval(function () { wrap(window.Swal); }, 20);
      })();`,
    });
  }
  await send('Network.setCookie', { name: 'PHPSESSID', value: SID, domain: '127.0.0.1', path: '/' });

  const api = {
    phpPort, cdpPort, SID, send, ev, errors, requests,
    base: () => `http://127.0.0.1:${phpPort}`,
    go: async (page, settle = 3400) => {
      errors.length = 0;
      await send('Page.navigate', { url: `http://127.0.0.1:${phpPort}/${page}` });
      await sleep(settle);
      return ev('window.__alerts || []');
    },
    /**
     * Navigates and waits for a SweetAlert to have been shown, rather than sleeping a fixed
     * time and hoping. The pages show their alert from DOMContentLoaded, and how long that
     * takes varies with the page: a fixed wait made these checks flaky, passing and failing
     * on the same code.
     *
     * The wait for the new document comes first. Page.navigate resolves as soon as the
     * navigation is accepted, so polling straight away can read the PREVIOUS document - and if
     * that document had already recorded an alert, the check returned it and reported the page
     * under test as fine without ever loading it. The readiness token below is installed into
     * every new document, so seeing it proves the document being polled is the new one.
     *
     * Returns the alerts seen, which may be empty if none arrived before the deadline.
     */
    goForAlert: async (page, timeoutMs = 12000) => {
      errors.length = 0;
      const token = `ocp-ready-${Date.now()}-${Math.random().toString(36).slice(2)}`;
      const { identifier } = await send('Page.addScriptToEvaluateOnNewDocument', {
        source: `window.__ocpReady = ${JSON.stringify(token)};`,
      });
      try {
        await send('Page.navigate', { url: `http://127.0.0.1:${phpPort}/${page}` });
        const deadline = Date.now() + timeoutMs;

        // 1. wait until the document being evaluated is the new one
        let ready = false;
        while (Date.now() < deadline) {
          await sleep(150);
          const probe = await ev('window.__ocpReady === ' + JSON.stringify(token));
          if (probe === true) { ready = true; break; }
          if (probe && probe.__error) { console.log(`  ! readiness probe failed: ${probe.__error}`); break; }
        }
        if (!ready) {
          console.log(`  ! the new document never became ready within ${timeoutMs}ms`);
          return [];
        }

        // 2. then wait for the alert, which the page raises after its script runs
        let seen = [];
        while (Date.now() < deadline) {
          await sleep(300);
          const got = await ev('window.__alerts || []');
          if (got && got.__error) { console.log(`  ! reading window.__alerts failed: ${got.__error}`); break; }
          if (Array.isArray(got) && got.length) { seen = got; break; }
        }
        // a short grace period, so a second alert from the same load is caught too
        await sleep(400);
        const after = await ev('window.__alerts || []');
        return Array.isArray(after) && after.length ? after : seen;
      } finally {
        // the token must not outlive this call, or a later navigation would look ready at once
        await send('Page.removeScriptToEvaluateOnNewDocument', { identifier }).catch(() => {});
      }
    },
    // Some buttons are gated on the viewer's role, so a session for that user is needed.
    // make-session.php writes a session file in the shape the app's own login writes.
    become: async (userId) => {
      const sid = execFileSync('php', [path.join(ROOT, '_verify', 'make-session.php'), String(userId)],
        { encoding: 'utf8' }).trim();
      if (!sid) throw new Error(`could not open a session for user ${userId}`);
      await send('Network.setCookie', { name: 'PHPSESSID', value: sid, domain: '127.0.0.1', path: '/' });
      return sid;
    },
    alerts: () => ev('window.__alerts || []'),
    clearAlerts: () => ev('window.__alerts = []'),
    // posts a form the way the page does, without following the redirect. The body is read
    // here and kept as `text`, because a response left unread keeps the connection paused
    // (Node's HTTP client then throws on a later request) and a consumed one cannot be read
    // again by the caller.
    post: async (page, fields) => {
      const body = new URLSearchParams();
      for (const [k, v] of Object.entries(fields)) body.append(k, v);
      const res = await fetch(`http://127.0.0.1:${phpPort}/${page}`, {
        method: 'POST', body, headers: { Cookie: `PHPSESSID=${SID}` }, redirect: 'manual',
      });
      let text = '';
      try { text = Buffer.from(await res.arrayBuffer()).toString('utf8'); } catch { /* already drained */ }
      return { status: res.status, location: res.headers.get('location'), text };
    },
    close: () => {
      try { ws.close(); } catch { /* already closed */ }
      chrome.kill();
      app.kill();
    },
  };

  try {
    return await fn(api);
  } finally {
    api.close();
  }
}
