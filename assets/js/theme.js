/*
 * Colour theme for the public site and admin (loaded in <head>, before the stylesheets apply).
 *   Preference: 'system' (default: follow the device), 'light' or 'dark', saved per visitor in
 *   localStorage 'rento-theme'. Sets data-theme and data-bs-theme on <html> (tokens.css, Bootstrap).
 *   Buttons with [data-theme-toggle] cycle System -> Light -> Dark and say what they will do next.
 */
(function () {
  'use strict';
  var KEY = 'rento-theme';
  var ORDER = ['system', 'light', 'dark'];
  var LABELS = { system: 'System', light: 'Light', dark: 'Dark' };
  var ICONS = { system: 'fa-circle-half-stroke', light: 'fa-sun', dark: 'fa-moon' };
  var root = document.documentElement;
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function read() {
    try { var v = localStorage.getItem(KEY); return ORDER.indexOf(v) > 0 ? v : 'system'; } catch (e) { return 'system'; }
  }
  function apply(pref) {
    var dark = pref === 'dark' || (pref === 'system' && !!media && media.matches);
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    root.setAttribute('data-theme-pref', pref);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta && meta.dataset.dark) meta.setAttribute('content', dark ? meta.dataset.dark : meta.dataset.light);
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      var next = ORDER[(ORDER.indexOf(pref) + 1) % ORDER.length];
      btn.setAttribute('aria-label', 'Colour theme: ' + LABELS[pref] + '. Switch to ' + LABELS[next]);
      btn.setAttribute('title', 'Theme: ' + LABELS[pref]);
      var icon = btn.querySelector('i');
      if (icon) icon.className = 'fa ' + ICONS[pref];
    });
  }
  function set(pref) {
    try { if (pref === 'system') localStorage.removeItem(KEY); else localStorage.setItem(KEY, pref); } catch (e) { /* private mode */ }
    apply(pref);
  }

  apply(read());
  if (media) {
    var onChange = function () { if (read() === 'system') apply('system'); };
    if (media.addEventListener) media.addEventListener('change', onChange); else if (media.addListener) media.addListener(onChange);
  }
  document.addEventListener('DOMContentLoaded', function () {
    apply(read()); // label the buttons
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-theme-toggle]');
      if (!btn) return;
      set(ORDER[(ORDER.indexOf(read()) + 1) % ORDER.length]);
    });
  });
  window.RentoTheme = { get: read, set: set };
})();
