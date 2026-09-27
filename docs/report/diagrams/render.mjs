// Renders the Mermaid diagram sources to PNG with headless Chrome:
//   node docs/report/diagrams/render.mjs [name.mmd ...]      (default: every .mmd in this folder)
// Output: docs/report/figures/diagrams/<name>.png at 2x scale. MERMAID_JS may point to a local copy of
// mermaid.min.js; otherwise Mermaid 11 is loaded from cdn.jsdelivr.net.
import { spawn } from 'node:child_process';
import { readFileSync, readdirSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const out = join(here, '..', 'figures', 'diagrams');
mkdirSync(out, { recursive: true });
const files = process.argv.slice(2).length ? process.argv.slice(2) : readdirSync(here).filter(f => f.endsWith('.mmd')).sort();
const lib = process.env.MERMAID_JS && existsSync(process.env.MERMAID_JS)
  ? `<script>${readFileSync(process.env.MERMAID_JS, 'utf8')}</script>`
  : '<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>';
const port = 9200 + Math.floor(Math.random() * 90);
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', ['--headless=new', '--disable-gpu', '--no-first-run', `--remote-debugging-port=${port}`, `--user-data-dir=/tmp/rento-mermaid-${port}`, 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let t; for (let i = 0; i < 60 && !t; i++) { await sleep(200); try { t = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find(x => x.type === 'page'); } catch {} }
const ws = new WebSocket(t.webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0; const pend = new Map(); ws.onmessage = e => { const m = JSON.parse(e.data); if (m.id && pend.has(m.id)) { pend.get(m.id)(m.result); pend.delete(m.id); } };
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
await send('Page.enable'); await send('Runtime.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 1800, height: 1200, deviceScaleFactor: 2, mobile: false });
const theme = { theme: 'base', themeVariables: { primaryColor: '#fff0eb', primaryBorderColor: '#d23f1c', primaryTextColor: '#101114', lineColor: '#4f5362',
  secondaryColor: '#f1f2f4', tertiaryColor: '#ffffff', fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '15px', actorBkg: '#fff0eb', actorBorder: '#d23f1c',
  noteBkgColor: '#fff5e0', noteBorderColor: '#d98a00', activationBkgColor: '#ffe1d8', activationBorderColor: '#d23f1c' },
  er: { layoutDirection: 'TB' }, flowchart: { htmlLabels: true, curve: 'basis' }, sequence: { mirrorActors: false, showSequenceNumbers: true } };
for (const f of files) {
  const src = readFileSync(join(here, f), 'utf8');
  const html = `<!doctype html><html><head><meta charset="utf-8">${lib}<style>body{margin:0;background:#fff;font-family:Arial}#d{display:inline-block;padding:24px}</style></head>
<body><div id="d"><pre class="mermaid">${src.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre></div>
<script>mermaid.initialize(Object.assign({startOnLoad:false, securityLevel:'loose'}, ${JSON.stringify(theme)}));
mermaid.run().then(()=>{document.title='done'}).catch(e=>{document.title='error: '+e.message});</script></body></html>`;
  const tmp = `/tmp/rento-mermaid-${basename(f)}.html`;
  writeFileSync(tmp, html);
  await send('Page.navigate', { url: 'file://' + tmp });
  let title = '';
  for (let i = 0; i < 100 && !title.startsWith('done') && !title.startsWith('error'); i++) { await sleep(150); title = (await send('Runtime.evaluate', { expression: 'document.title', returnByValue: true })).result.value; }
  if (!title.startsWith('done')) { console.log(`${f}: FAILED ${title}`); continue; }
  // Mermaid scales the SVG down to the page width; render it at its natural size instead.
  await send('Runtime.evaluate', { expression: `(()=>{const s=document.querySelector('#d svg'); const vb=s.viewBox.baseVal; s.style.maxWidth='none'; s.setAttribute('width', vb.width); s.setAttribute('height', vb.height); return 1})()` });
  await sleep(200);
  const box = (await send('Runtime.evaluate', { expression: 'JSON.stringify((()=>{const r=document.getElementById("d").getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height}})())', returnByValue: true })).result.value;
  const b = JSON.parse(box);
  const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: b.x, y: b.y, width: Math.ceil(b.w), height: Math.ceil(b.h), scale: 1 } });
  const png = join(out, basename(f, '.mmd') + '.png');
  writeFileSync(png, Buffer.from(shot.data, 'base64'));
  console.log(`${f}: ${Math.ceil(b.w)}x${Math.ceil(b.h)} -> ${png.replace(/.*docs\//, 'docs/')}`);
}
ws.close(); chrome.kill();
