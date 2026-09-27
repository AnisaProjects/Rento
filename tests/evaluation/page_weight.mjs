// Page weight: node tests/evaluation/page_weight.mjs <outDir> <width> <height> <cookie|-> <page>...
// Same method as the Phase 2 "before" measurement: cache disabled, page scrolled to load lazy images,
// counts requests by type and the transferred bytes (encodedDataLength). Writes <outDir>/summary.json.
// For each page: full-page screenshot, request counts by type, transferred bytes, JS errors, failed requests.
import { spawn } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
const [, , outDir, w, h, cookie, ...paths] = process.argv;
mkdirSync(outDir, { recursive: true });
const port = 9400 + Math.floor(Math.random() * 400);
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ['--headless=new', '--disable-gpu', '--no-first-run', '--hide-scrollbars', `--remote-debugging-port=${port}`, `--user-data-dir=${outDir}/.prof`, 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let target;
for (let i = 0; i < 60 && !target; i++) { await sleep(200); try { target = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find(t => t.type === 'page'); } catch {} }
const ws = new WebSocket(target.webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0; const pending = new Map(); let st;
const reset = () => st = { errors: [], reqs: new Map(), failed: [] };
ws.onmessage = e => { const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m.result || m.error); pending.delete(m.id); return; }
  const p = m.params;
  if (m.method === 'Runtime.exceptionThrown') st.errors.push((p.exceptionDetails.exception?.description || p.exceptionDetails.text).split('\n')[0]);
  if (m.method === 'Network.responseReceived') { const r = st.reqs.get(p.requestId) || {}; Object.assign(r, { url: p.response.url, type: p.type, status: p.response.status }); st.reqs.set(p.requestId, r); if (p.response.status >= 400) st.failed.push(p.response.status + ' ' + p.response.url); }
  if (m.method === 'Network.loadingFinished') { const r = st.reqs.get(p.requestId) || {}; r.bytes = p.encodedDataLength; st.reqs.set(p.requestId, r); }
  if (m.method === 'Network.loadingFailed' && !p.canceled) st.failed.push('failed ' + p.errorText);
};
const send = (method, params = {}) => new Promise(r => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
await send('Runtime.enable'); await send('Page.enable'); await send('Network.enable');
await send('Network.setCacheDisabled', { cacheDisabled: true });
await send('Emulation.setDeviceMetricsOverride', { width: +w, height: +h, deviceScaleFactor: 1, mobile: +w < 600 });
if (cookie !== '-') for (const c of cookie.split(',')) { const [name, value] = c.split('='); await send('Network.setCookie', { name, value, domain: 'localhost', path: '/' }); }
const summary = [];
for (const p of paths) {
  reset();
  await send('Page.navigate', { url: (process.env.BASE || 'http://localhost:8888/Rento/') + p });
  await sleep(2500);
  // Scroll through the page so lazy images and scroll-in animations run, then back to the top.
  await send('Runtime.evaluate', { expression: '(async()=>{for(let y=0;y<document.body.scrollHeight;y+=500){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,120));}window.scrollTo(0,0);})()', awaitPromise: true });
  await sleep(1500);
  const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
  const file = (p || 'home').replace(/[^a-z0-9]+/gi, '_') + '.png';
  writeFileSync(`${outDir}/${file}`, Buffer.from(shot.data, 'base64'));
  const top = await send('Page.captureScreenshot', { format: 'png' });
  writeFileSync(`${outDir}/${file.replace('.png', '_top.png')}`, Buffer.from(top.data, 'base64'));
  const reqs = [...st.reqs.values()].filter(r => r.url && !r.url.startsWith('data:'));
  const by = t => reqs.filter(r => r.type === t).length;
  const bytes = reqs.reduce((s, r) => s + (r.bytes || 0), 0);
  summary.push({ page: p, requests: reqs.length, css: by('Stylesheet'), js: by('Script'), img: by('Image'), font: by('Font'), kb: Math.round(bytes / 1024), errors: [...new Set(st.errors)], failed: [...new Set(st.failed)] });
}
writeFileSync(`${outDir}/summary.json`, JSON.stringify(summary, null, 1));
for (const s of summary) console.log(`${s.page.padEnd(30)} req=${String(s.requests).padStart(3)} css=${String(s.css).padStart(2)} js=${String(s.js).padStart(2)} img=${String(s.img).padStart(2)} font=${s.font} ${String(s.kb).padStart(5)}KB ${s.errors.length ? 'JS:' + s.errors.join(' | ').slice(0, 120) : ''} ${s.failed.length ? 'FAIL:' + s.failed.join(' | ').slice(0, 200) : ''}`);
ws.close(); chrome.kill();
