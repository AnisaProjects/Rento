/*
 * site-interactions.js
 * Site-wide behaviour — include this on EVERY page (put the <script> tag
 * in includes/header.php or includes/footer.php, not per-page), so the
 * header behaves the same everywhere instead of only on one page.
 *
 * What it does:
 *  1. Sticky header — adds/removes the `affix` class on scroll. Your
 *     style.css already has `.nav-stacked.affix` styling defined; this
 *     script is the missing piece that actually toggles it. If your
 *     header markup in header.php uses a different wrapper class than
 *     ".nav-stacked" or ".default-header", update the selector below.
 *  2. Back-to-top button — shows/hides #back-top based on scroll depth
 *     (style.css already has `.back-top.show`, same situation as above).
 */
(function () {
  "use strict";

  var STICKY_OFFSET = 80;
  var stickyTargets = document.querySelectorAll(".nav-stacked, .default-header");
  var backTop = document.getElementById("back-top");

  function onScroll() {
    var scrolled = window.scrollY > STICKY_OFFSET;

    stickyTargets.forEach(function (el) {
      el.classList.toggle("affix", scrolled);
    });

    if (backTop) {
      backTop.classList.toggle("show", window.scrollY > 400);
    }
  }

  window.addEventListener("scroll", onScroll, { passive: true });
  document.addEventListener("DOMContentLoaded", onScroll);
})();