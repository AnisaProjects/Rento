// Captures the report screenshots with headless Chrome:
//   CUSTOMER_COOKIE=RENTOSESSID=... ADMIN_COOKIE=RENTOADMINSESSID=... node docs/report/screenshots/capture.mjs
// Each entry: [file, path, width, height, scheme, cookie, full-page?, script to run first]
import { spawn } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const out = dirname(fileURLToPath(import.meta.url));
const B = (process.env.BASE || 'http://localhost:8888/Rento').replace(/\/$/, '');
const CU = process.env.CUSTOMER_COOKIE || '', AD = process.env.ADMIN_COOKIE || '';
const reveal = `document.querySelectorAll('.r-reveal').forEach(e=>e.classList.add('is-visible'));`;
const pickDates = `document.querySelector('[data-pcal-next]')?.click(); await sleep(400);
  const d=[...document.querySelectorAll('.r-pcal__day[data-date]:not(:disabled):not([aria-disabled="true"])')]; d[7]?.click(); await sleep(250); d[9]?.click(); await sleep(1500);
  document.querySelector('.r-book')?.scrollIntoView({block:'start'}); window.scrollBy(0,-90);`;
const findCar = `const t=document.getElementById('trip'); t.value='Mountain roads to Mustang, 5 people'; t.dispatchEvent(new Event('input'));
  await sleep(300); t.form.submit();`;
const shots = [
  ['01_home_desktop.png', '/', 1366, 900, 'light', '', false, reveal],
  ['02_home_mobile.png', '/', 390, 844, 'light', '', false, reveal],
  ['03_listing_desktop.png', '/car-listing?sort=price_asc', 1366, 900, 'light', '', false, `window.scrollTo(0, 360);`],
  ['04_listing_mobile.png', '/car-listing', 390, 844, 'light', '', false, `window.scrollTo(0, 520);`],
  ['05_car_price_calendar_desktop.png', '/car/toyota-fortuner-7', 1366, 900, 'light', CU, false, pickDates],
  ['06_car_price_calendar_mobile.png', '/car/toyota-fortuner-7', 390, 844, 'light', CU, false, pickDates],
  ['07_find_my_car_results_desktop.png', '/car-recommendation', 1366, 900, 'light', '', false, findCar, `await sleep(500); document.getElementById('results')?.scrollIntoView(); ${reveal}`],
  ['08_find_my_car_mobile.png', '/car-recommendation', 390, 844, 'light', '', false, findCar, `await sleep(500); document.getElementById('results')?.scrollIntoView(); ${reveal}`],
  ['09_my_bookings_desktop.png', '/my-booking', 1366, 900, 'light', CU, false, ''],
  ['10_invoice_desktop.png', '/invoice?booking=' + (process.env.BOOKING || ''), 1366, 900, 'light', CU, true, ''],
  ['11_package_festival_prices_desktop.png', '/package/kathmandu-pokhara-lakeside-getaway-9', 1366, 900, 'light', '', false, `document.querySelector('.r-festival-prices')?.scrollIntoView({block:'center'});`],
  ['12_faqs_mobile.png', '/faqs', 390, 844, 'light', '', false, ''],
  ['13_home_dark_desktop.png', '/', 1366, 900, 'dark', '', false, reveal],
  ['14_car_dark_mobile.png', '/car/toyota-fortuner-7', 390, 844, 'dark', CU, false, pickDates],
  ['20_admin_dashboard.png', '/admin/dashboard.php', 1366, 900, 'light', AD, false, ''],
  ['21_admin_ai_insights.png', '/admin/insights.php', 1366, 900, 'light', AD, true, ''],
  ['22_admin_smart_pricing_model_health.png', '/admin/pricing.php', 1366, 900, 'light', AD, true, ''],
  ['23_admin_festivals.png', '/admin/festivals.php', 1366, 900, 'light', AD, false, ''],
  ['24_admin_vehicle_form.png', '/admin/edit-vehicle.php?id=7', 1366, 900, 'light', AD, false, `document.getElementById('priceperday')?.scrollIntoView({block:'center'});`],
  ['25_admin_bookings.png', '/admin/manage-bookings.php', 1366, 900, 'light', AD, false, ''],
  ['26_admin_system_check.png', '/admin/system-check.php', 1366, 900, 'light', AD, false, ''],
  ['27_admin_email_settings.png', '/admin/email-settings.php', 1366, 900, 'light', AD, false, ''],
  ['28_admin_legal_draft.png', '/admin/manage-pages.php?type=terms', 1366, 900, 'light', AD, false, `document.querySelector('details').open=true;`],
  ['29_admin_dashboard_dark_mobile.png', '/admin/dashboard.php', 390, 844, 'dark', AD, false, ''],
];
const only = process.env.ONLY ? process.env.ONLY.split(',') : null;
const port = 9100 + Math.floor(Math.random() * 90);
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', ['--headless=new', '--disable-gpu', '--no-first-run', '--hide-scrollbars', `--remote-debugging-port=${port}`, `--user-data-dir=/tmp/rento-shots-${port}`, 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let t; for (let i = 0; i < 60 && !t; i++) { await sleep(200); try { t = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find(x => x.type === 'page'); } catch {} }
const ws = new WebSocket(t.webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0; const pend = new Map(); ws.onmessage = e => { const m = JSON.parse(e.data); if (m.id && pend.has(m.id)) { pend.get(m.id)(m.result); pend.delete(m.id); } };
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
const run = expr => send('Runtime.evaluate', { expression: `(async()=>{const sleep=ms=>new Promise(r=>setTimeout(r,ms)); ${expr}; return 1})()`, awaitPromise: true });
await send('Page.enable'); await send('Network.enable'); await send('Runtime.enable');
for (const [file, path, w, h, scheme, cookie, full, before, after] of shots) {
  if (only && !only.includes(file)) continue;
  await send('Network.clearBrowserCookies');
  if (cookie) { const [n, v] = cookie.split('='); await send('Network.setCookie', { name: n, value: v, domain: 'localhost', path: '/' }); }
  await send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: w < 600 ? 2 : 1, mobile: w < 600 });
  await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: scheme }, { name: 'prefers-reduced-motion', value: 'reduce' }] });
  await send('Page.navigate', { url: B + path }); await sleep(2500);
  if (before) { await run(before); await sleep(before.includes('submit()') ? 4000 : 900); }
  if (after) { await run(after); await sleep(900); }
  const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: !!full });
  writeFileSync(join(out, file), Buffer.from(shot.data, 'base64'));
  console.log(file);
}
ws.close(); chrome.kill();
