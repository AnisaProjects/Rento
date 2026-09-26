/*
 * about-us.js
 * Page-only interaction for the About Us page: fades/slides in any
 * element tagged `.reveal` as it scrolls into view.
 * Include this AFTER assets/js/site-interactions.js, right before </body>.
 */
(function () {
  "use strict";

  var revealEls = document.querySelectorAll(".reveal");

  if ("IntersectionObserver" in window) {
    var revealObserver = new IntersectionObserver(
      function (entries, observer) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15 }
    );
    revealEls.forEach(function (el) { revealObserver.observe(el); });
  } else {
    // No IntersectionObserver support: just show everything immediately.
    revealEls.forEach(function (el) { el.classList.add("is-visible"); });
  }
})();