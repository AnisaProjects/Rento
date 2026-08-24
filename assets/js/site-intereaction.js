/*
 * site-interactions.js
 * Site-wide behaviour. Include this ONCE, in includes/footer.php (already
 * loaded on every page), so the header behaves the same everywhere instead
 * of only on whichever page happens to link this file directly.
 *
 * What it does:
 *  Sticky header — adds/removes the `affix` class on scroll. style.css
 *  already has `.nav-stacked.affix` styling defined; this script is the
 *  missing piece that actually toggles it. If your header markup in
 *  header.php uses a different wrapper class than ".nav-stacked" or
 *  ".default-header", update the selector below to match.
 *
 * NOTE: back-to-top is intentionally NOT handled here — footer.php already
 * has its own script for that (#backTop), so this file doesn't duplicate it.
 */
(function () {
  "use strict";

  var STICKY_OFFSET = 80;
  var stickyTargets = document.querySelectorAll(".nav-stacked, .default-header");

  function onScroll() {
    var scrolled = window.scrollY > STICKY_OFFSET;
    stickyTargets.forEach(function (el) {
      el.classList.toggle("affix", scrolled);
    });
  }

  window.addEventListener("scroll", onScroll, { passive: true });
  document.addEventListener("DOMContentLoaded", onScroll);
})();
