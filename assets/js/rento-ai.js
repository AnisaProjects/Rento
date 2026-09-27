/*
 * Rento trip understanding (Find My Car, car listing smart search).
 * Reads a plain-language description ("family trip to Pokhara under Rs 3000") and
 * works out a car type, a daily budget and the number of travellers. It is a
 * transparent keyword parser, not a language model: it only fills in the same
 * filters a visitor could set by hand.
 */
(function (window) {
  'use strict';

  var TYPE_RULES = [
    { type: 'SUV', why: 'rough roads', words: ['suv', 'mountain', 'mountains', 'hill', 'hills', 'off road', 'offroad', 'off-road', 'rough', 'mustang', 'jomsom', 'manang', 'trek', 'trekking', 'adventure', 'snow', 'monsoon', 'village', 'dirt', 'jungle', 'chitwan', 'group', 'luggage'] },
    { type: 'Sedan', why: 'comfortable highway drives', words: ['sedan', 'highway', 'long drive', 'long trip', 'business', 'meeting', 'comfort', 'comfortable', 'airport', 'wedding', 'family', 'pokhara', 'lumbini', 'quiet'] },
    { type: 'Hatchback', why: 'easy city driving', words: ['hatchback', 'city', 'commute', 'daily', 'kathmandu', 'lalitpur', 'patan', 'bhaktapur', 'parking', 'small', 'cheap', 'solo', 'couple', 'errand', 'errands', 'shopping', 'fuel efficient', 'mileage'] }
  ];

  function hasWord(text, word) {
    return new RegExp('(^|[^a-z])' + word.replace(/[-\s]/g, '[-\\s]?') + '([^a-z]|$)').test(text);
  }

  function parseAmount(raw) {
    var m = raw.replace(/,/g, '').match(/^(\d+(?:\.\d+)?)\s*(k|thousand)?$/);
    if (!m) return null;
    var n = parseFloat(m[1]) * (m[2] ? 1000 : 1);
    return n >= 100 && n <= 100000 ? Math.round(n) : null;
  }

  // Returns { type, typeWhy, budget, people, fuel, notes[] } (fields null when not found).
  function understand(input) {
    var text = ' ' + String(input || '').toLowerCase() + ' ';
    var result = { type: null, typeWhy: null, budget: null, people: null, fuel: null, notes: [] };

    var people = text.match(/(\d{1,2})\s*(people|persons|person|passengers|pax|seats|of us|adults|friends|members)/);
    if (people) result.people = parseInt(people[1], 10);
    if (!result.people && /(family of|group of)\s*(\d{1,2})/.test(text)) result.people = parseInt(text.match(/(family of|group of)\s*(\d{1,2})/)[2], 10);

    var money = text.match(/(?:rs\.?|npr|रु|budget|under|below|less than|max(?:imum)?|upto|up to|within)\s*(?:rs\.?|npr)?\s*(\d[\d,]*(?:\.\d+)?\s*(?:k|thousand)?)/) ||
      text.match(/(\d[\d,]*(?:\.\d+)?\s*(?:k|thousand)?)\s*(?:rs|npr|rupees)/);
    if (money) result.budget = parseAmount(money[1].trim());
    if (!result.budget && /(cheap|cheapest|budget|low cost|affordable|economy)/.test(text)) { result.budget = 1000; result.notes.push('a low budget'); }
    if (!result.budget && /(luxury|premium|best car|top end)/.test(text)) { result.budget = 5000; result.notes.push('a premium car'); }

    if (/electric/.test(text) || hasWord(text, 'ev')) result.fuel = 'Electric';
    else if (/(cng)/.test(text)) result.fuel = 'CNG';
    else if (/(diesel)/.test(text)) result.fuel = 'Diesel';
    else if (/(petrol)/.test(text)) result.fuel = 'Petrol';

    var best = null;
    TYPE_RULES.forEach(function (rule) {
      var hits = rule.words.filter(function (w) { return hasWord(text, w); }).length;
      if (hits && (!best || hits > best.hits)) best = { rule: rule, hits: hits };
    });
    if (result.people && result.people > 5) {
      result.type = 'SUV';
      result.typeWhy = result.people + ' travellers';
    } else if (best) {
      result.type = best.rule.type;
      // Named outright ("an SUV"), so no reason is needed.
      result.typeWhy = hasWord(text, best.rule.type.toLowerCase()) ? null : best.rule.why;
    }
    return result;
  }

  // Human-readable chips for what was understood.
  function describe(result, formatPrice) {
    var chips = [];
    if (result.type) chips.push({ icon: 'fa-car', text: result.type + (result.typeWhy ? ' for ' + result.typeWhy : '') });
    if (result.budget) chips.push({ icon: 'fa-tag', text: 'Up to ' + formatPrice(result.budget) + '/day' });
    if (result.people && !/travellers/.test(result.typeWhy || '')) chips.push({ icon: 'fa-user-group', text: result.people + (result.people === 1 ? ' traveller' : ' travellers') });
    if (result.fuel) chips.push({ icon: 'fa-gas-pump', text: result.fuel });
    if (result.brand) chips.push({ icon: 'fa-certificate', text: result.brand.name });
    return chips;
  }

  function formatPrice(n) {
    return 'Rs ' + Math.round(n).toLocaleString('en-US');
  }

  function renderChips(container, result) {
    var chips = describe(result, formatPrice);
    container.innerHTML = '';
    if (!chips.length) {
      container.hidden = true;
      return;
    }
    container.hidden = false;
    var label = document.createElement('span');
    label.className = 'r-ai-understood__label';
    label.innerHTML = '<i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Understood';
    container.appendChild(label);
    chips.forEach(function (chip) {
      var el = document.createElement('span');
      el.className = 'r-ai-chip';
      el.innerHTML = '<i class="fa ' + chip.icon + '" aria-hidden="true"></i>';
      el.appendChild(document.createTextNode(chip.text));
      container.appendChild(el);
    });
  }

  // "Thinking" sequence shown before a form is submitted (skipped for reduced motion).
  function think(overlay, steps, done) {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!overlay || reduce) { done(); return; }
    var items = overlay.querySelectorAll('[data-step]');
    overlay.hidden = false;
    document.body.classList.add('r-ai-busy');
    var i = 0;
    (function next() {
      if (i > 0) items[i - 1].classList.add('is-done');
      if (i < items.length) {
        items[i].classList.add('is-active');
        i++;
        setTimeout(next, steps || 420);
      } else {
        setTimeout(done, 200);
      }
    })();
  }

  // Car listing smart search: turn the description into the listing's GET filters.
  // Falls back to a plain keyword search (q) when nothing is recognised.
  function smartSearch(form) {
    var input = form.querySelector('input[name="q"]');
    var out = form.querySelector('.r-ai-understood');
    var read = function (name) { try { return JSON.parse(form.dataset[name] || '[]'); } catch (e) { return []; } };
    var brands = read('brands');
    var fuels = read('fuels');
    var budgets = read('budgets');

    function parse() {
      var result = understand(input.value);
      var text = ' ' + input.value.toLowerCase() + ' ';
      result.brand = null;
      brands.forEach(function (b) {
        if (!result.brand && hasWord(text, String(b[1]).toLowerCase().split(' ')[0])) result.brand = { id: b[0], name: b[1] };
      });
      if (result.fuel) {
        result.fuel = fuels.filter(function (f) { return f.toLowerCase() === result.fuel.toLowerCase(); })[0] || null;
      }
      if (result.budget) {
        result.max = budgets.filter(function (b) { return b >= result.budget; })[0] || null;
      }
      return result;
    }

    input.addEventListener('input', function () { renderChips(out, parse()); });

    form.addEventListener('submit', function (event) {
      var r = parse();
      var params = new URLSearchParams();
      if (r.type) params.set('type', r.type);
      if (r.brand) params.set('brand', r.brand.id);
      if (r.fuel) params.set('fueltype', r.fuel);
      if (r.max) params.set('max', r.max);
      // The description itself: the listing ranks the cars by match score with it.
      if (params.toString()) params.set('trip', input.value.trim());
      if (!params.toString()) return; // plain keyword search
      event.preventDefault();
      var go = function () { window.location.href = form.getAttribute('action') + '?' + params.toString(); };
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { go(); return; }
      form.classList.add('is-thinking');
      var listing = document.querySelector('.r-listing');
      if (listing) listing.classList.add('is-thinking');
      setTimeout(go, 700);
    });
  }

  // Loaded at the end of <body>, so the form already exists.
  var smartForm = window.document && document.getElementById('smart-search');
  if (smartForm) smartSearch(smartForm);

  window.RentoAI = { understand: understand, renderChips: renderChips, think: think, formatPrice: formatPrice };
})(window);
