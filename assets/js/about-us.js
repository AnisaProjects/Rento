/*
 * about-us.js
 * Page-only interactions for the About Us page:
 *  - fades/slides in any element tagged `.reveal` as it scrolls into view
 *  - animates the "120+" stat counting up from 0 once it's visible
 * Include this AFTER assets/js/site-interactions.js, right before </body>.
 */
(function () {
  "use strict";

  /* ---------- Scroll reveal ---------- */
  var revealGroups = [
    document.querySelectorAll(".about2-route li"),
    document.querySelectorAll(".about2-checklist li")
  ];

  // Give each item in a staggered group an index so CSS can delay them.
  revealGroups.forEach(function (group) {
    group.forEach(function (el, i) {
      el.style.setProperty("--reveal-index", i);
    });
  });

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

  /* ---------- Count-up counter ---------- */
  var counters = document.querySelectorAll("[data-count-target]");

  function animateCounter(el) {
    var target = parseInt(el.getAttribute("data-count-target"), 10) || 0;
    var duration = 1200;
    var start = null;

    function step(timestamp) {
      if (!start) start = timestamp;
      var progress = Math.min((timestamp - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
      el.textContent = Math.floor(eased * target);
      if (progress < 1) {
        window.requestAnimationFrame(step);
      } else {
        el.textContent = target;
      }
    }
    window.requestAnimationFrame(step);
  }

  if (counters.length) {
    if ("IntersectionObserver" in window) {
      var counterObserver = new IntersectionObserver(
        function (entries, observer) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              animateCounter(entry.target);
              observer.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.6 }
      );
      counters.forEach(function (el) { counterObserver.observe(el); });
    } else {
      counters.forEach(function (el) {
        el.textContent = el.getAttribute("data-count-target");
      });
    }
  }
})();