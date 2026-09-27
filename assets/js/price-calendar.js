/*
 * Price calendar on the car page (vehical-details.php).
 * Reads api/price-calendar.php (the smart-pricing forecast) and shows two months:
 *   - each day coloured cheaper / normal / higher than the car's usual price, booked days crossed out
 *   - hover, focus or tap a day: its price and the reasons
 *   - with the booking form: first tap = pickup, second = return (fills #fromdate / #todate)
 *   - a "Save Rs X by picking up <day>" hint, only when a same-length trip starting up to 3 days
 *     earlier or later is really cheaper, fully available and priced from the forecast
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-price-calendar]');
  if (!root || !window.fetch) return;

  var carId = root.dataset.car;
  var api = root.dataset.api;
  var base = Number(root.dataset.base);
  var from = document.getElementById('fromdate');
  var to = document.getElementById('todate');
  var grid = root.querySelector('[data-pcal-grid]');
  var title = root.querySelector('[data-pcal-title]');
  var detail = root.querySelector('[data-pcal-detail]');
  var hint = document.querySelector('[data-pcal-hint]');
  var prevBtn = root.querySelector('[data-pcal-prev]');
  var nextBtn = root.querySelector('[data-pcal-next]');
  var days = {};        // 'YYYY-MM-DD' -> day from the API
  var months = [];      // loaded months, 'YYYY-MM'
  var shown = 0;        // index into months
  var picking = 'from'; // next tap sets the pickup or the return date

  var fmt = function (n) { return 'Rs ' + Math.round(n).toLocaleString('en-US'); };
  var pct = function (p) { return (p > 0 ? '+' : '') + (Math.round(p * 10) / 10) + '%'; };
  var iso = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
  var parse = function (s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
  var longDate = function (s) { return parse(s).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }); };

  function level(day) {
    if (!day.available) return 'booked';
    if (day.estimate) return 'normal';
    if (day.price < base * 0.98) return 'cheap';
    if (day.price > base * 1.02) return 'high';
    return 'normal';
  }

  function describe(day) {
    if (!day) return '';
    var text = longDate(day.date) + ': ' + (day.available ? fmt(day.price) + (day.change_pct ? ' (' + pct(day.change_pct) + ')' : '') : 'already booked');
    if (day.available && day.reasons.length) text += ' · ' + day.reasons.join(', ');
    if (day.estimate) text += ' · usual price (beyond the price forecast)';
    return text;
  }

  function load(month) {
    return fetch(api + '?car=' + carId + '&month=' + month, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        data.days.forEach(function (d) { days[d.date] = d; });
        months.push(month);
      });
  }

  function render() {
    var month = months[shown];
    if (!month) return;
    var first = parse(month + '-01');
    title.textContent = first.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
    prevBtn.disabled = shown === 0;
    nextBtn.disabled = shown >= months.length - 1;
    grid.innerHTML = '';
    var lead = (first.getDay() + 6) % 7; // Monday first
    for (var i = 0; i < lead; i++) grid.appendChild(document.createElement('span'));
    var last = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    for (var dayNum = 1; dayNum <= last; dayNum++) {
      var date = iso(new Date(first.getFullYear(), first.getMonth(), dayNum));
      var day = days[date];
      var cell = document.createElement('button');
      cell.type = 'button';
      cell.className = 'r-pcal__day';
      cell.dataset.date = date;
      if (!day) {
        cell.disabled = true;
        cell.classList.add('is-past');
        cell.innerHTML = '<span class="r-pcal__num">' + dayNum + '</span>';
        cell.setAttribute('aria-label', longDate(date) + ': not available');
      } else {
        cell.classList.add('is-' + level(day));
        cell.innerHTML = '<span class="r-pcal__num">' + dayNum + '</span><span class="r-pcal__price">' + (day.available ? (day.price >= 10000 ? (Math.floor(day.price / 100) / 10) + 'k' : day.price) : '—') + '</span>';
        cell.setAttribute('aria-label', describe(day));
        cell.title = describe(day);
        if (!day.available) cell.setAttribute('aria-disabled', 'true');
      }
      grid.appendChild(cell);
    }
    markRange();
  }

  function markRange() {
    var a = from && from.value, b = to && to.value;
    grid.querySelectorAll('.r-pcal__day').forEach(function (cell) {
      var d = cell.dataset.date;
      cell.classList.toggle('is-start', d === a);
      cell.classList.toggle('is-end', d === b);
      cell.classList.toggle('is-in-range', !!(a && b && d > a && d < b));
      cell.setAttribute('aria-pressed', d === a || d === b ? 'true' : 'false');
    });
  }

  function show(date) {
    detail.textContent = describe(days[date]) || '';
  }

  grid.addEventListener('mouseover', function (e) { var c = e.target.closest('.r-pcal__day'); if (c && !c.disabled) show(c.dataset.date); });
  grid.addEventListener('focusin', function (e) { var c = e.target.closest('.r-pcal__day'); if (c) show(c.dataset.date); });
  grid.addEventListener('click', function (e) {
    var cell = e.target.closest('.r-pcal__day');
    if (!cell || cell.disabled) return;
    var date = cell.dataset.date;
    show(date);
    if (!from || !to || cell.getAttribute('aria-disabled') === 'true') return;
    if (picking === 'from' || date < from.value) {
      from.value = date;
      to.value = date;
      picking = 'to';
    } else {
      to.value = date;
      picking = 'from';
    }
    from.dispatchEvent(new Event('change'));
    to.dispatchEvent(new Event('change'));
    markRange();
  });
  prevBtn.addEventListener('click', function () { if (shown > 0) { shown--; render(); } });
  nextBtn.addEventListener('click', function () { if (shown < months.length - 1) { shown++; render(); } });

  // "Save Rs X by picking up <day>": the same number of days, pickup moved up to 3 days.
  function trip(start, length) {
    var total = 0;
    var d = parse(start);
    for (var i = 0; i < length; i++, d.setDate(d.getDate() + 1)) {
      var day = days[iso(d)];
      if (!day || !day.available || day.estimate) return null;
      total += day.price;
    }
    return total;
  }
  function updateHint() {
    if (!hint) return;
    hint.hidden = true;
    if (!from || !from.value || !to.value || to.value < from.value) return;
    var length = Math.round((parse(to.value) - parse(from.value)) / 86400000) + 1;
    var current = trip(from.value, length);
    if (current === null) return;
    var best = null;
    var today = iso(new Date());
    for (var shift = -3; shift <= 3; shift++) {
      if (!shift) continue;
      var s = parse(from.value);
      s.setDate(s.getDate() + shift);
      var start = iso(s);
      if (start < today) continue;
      var total = trip(start, length);
      if (total !== null && (!best || total < best.total)) best = { start: start, total: total };
    }
    var saving = best ? current - best.total : 0;
    if (!best || saving < Math.max(100, current * 0.03)) return;
    var end = parse(best.start);
    end.setDate(end.getDate() + length - 1);
    hint.querySelector('[data-pcal-hint-text]').textContent = 'Save ' + fmt(saving) + ' by picking up on ' + longDate(best.start)
      + ' (same ' + length + (length === 1 ? ' day' : ' days') + ', ' + fmt(best.total) + ' in total).';
    var apply = hint.querySelector('[data-pcal-hint-apply]');
    apply.onclick = function () {
      from.value = best.start;
      to.value = iso(end);
      from.dispatchEvent(new Event('change'));
      to.dispatchEvent(new Event('change'));
      markRange();
    };
    hint.hidden = false;
  }
  if (from) from.addEventListener('change', function () { markRange(); updateHint(); });
  if (to) to.addEventListener('change', function () { markRange(); updateHint(); });

  // This month and the next two (the forecast covers about 60 days).
  var now = new Date();
  var wanted = [0, 1, 2].map(function (n) { return iso(new Date(now.getFullYear(), now.getMonth() + n, 1)).slice(0, 7); });
  wanted.reduce(function (p, m) { return p.then(function () { return load(m); }); }, Promise.resolve()).then(function () {
    months.sort();
    var pickMonth = from && from.value ? from.value.slice(0, 7) : null;
    if (pickMonth && months.indexOf(pickMonth) > 0) shown = months.indexOf(pickMonth);
    root.hidden = months.length === 0;
    render();
    updateHint();
  }).catch(function () { root.hidden = true; });
})();
