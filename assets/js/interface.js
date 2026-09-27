/*
 * Site-wide behaviour for the public pages (loaded by includes/scripts.php).
 * Carousels are set up on the pages that have them (vehical-details.php).
 */
(function ($) {
  'use strict';

  $(function () {
    // Header gets a shadow once the page scrolls; the back-to-top button appears.
    var header = document.querySelector('.r-header');
    var backTop = document.getElementById('r-back-top');
    function onScroll() {
      var y = window.scrollY;
      if (header) header.classList.toggle('is-scrolled', y > 8);
      if (backTop) backTop.classList.toggle('is-visible', y > 600);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    if (backTop) {
      backTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }

    // Put the cursor in the search box when the header search opens.
    $('#r-search').on('shown.bs.collapse', function () {
      $('#header-search').trigger('focus');
    });

    // Home hero slideshow: autoplay unless the visitor prefers reduced motion; pause button.
    var heroEl = document.getElementById('r-hero-carousel');
    if (heroEl) {
      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var hero = new bootstrap.Carousel(heroEl, { interval: 6500, ride: reduce ? false : 'carousel', pause: 'hover', touch: true });
      var toggle = heroEl.querySelector('[data-r-carousel-toggle]');
      var paused = reduce;
      function setPaused(value) {
        paused = value;
        if (paused) { hero.pause(); } else { hero.cycle(); }
        toggle.setAttribute('aria-label', paused ? 'Play slideshow' : 'Pause slideshow');
        toggle.innerHTML = '<i class="fa ' + (paused ? 'fa-play' : 'fa-pause') + '" aria-hidden="true"></i>';
      }
      setPaused(reduce);
      toggle.addEventListener('click', function () { setPaused(!paused); });
      heroEl.addEventListener('slid.bs.carousel', function () { if (paused) hero.pause(); });
    }

    // Horizontal sliders: arrow buttons scroll one card; arrows hide when everything fits.
    document.querySelectorAll('[data-r-slider]').forEach(function (slider) {
      var track = slider.querySelector('.r-slider__track');
      var nav = slider.querySelector('.r-slider__nav');
      var prev = slider.querySelector('[data-r-slider-prev]');
      var next = slider.querySelector('[data-r-slider-next]');
      function step() {
        var item = track.querySelector('.r-slider__item');
        return item ? item.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || 0) : track.clientWidth;
      }
      function update() {
        var max = track.scrollWidth - track.clientWidth;
        nav.hidden = max <= 2;
        prev.disabled = track.scrollLeft <= 2;
        next.disabled = track.scrollLeft >= max - 2;
      }
      prev.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: 'smooth' }); });
      next.addEventListener('click', function () { track.scrollBy({ left: step(), behavior: 'smooth' }); });
      track.addEventListener('scroll', update, { passive: true });
      window.addEventListener('resize', update);
      update();
    });

    // Flash messages rendered as toasts.
    document.querySelectorAll('.r-toasts .toast').forEach(function (el) {
      bootstrap.Toast.getOrCreateInstance(el).show();
    });

    // Opening a modal (e.g. Login from the mobile menu) closes the off-canvas menu.
    document.addEventListener('show.bs.modal', function () {
      var open = document.querySelector('.offcanvas.show');
      if (open) bootstrap.Offcanvas.getOrCreateInstance(open).hide();
    });

    // A failed login / sign-up re-opens its form after the redirect.
    var reopen = document.getElementById('r-open-modal');
    if (reopen && document.getElementById(reopen.dataset.modal)) {
      bootstrap.Modal.getOrCreateInstance(document.getElementById(reopen.dataset.modal)).show();
    }

    // Show / hide password buttons.
    $(document).on('click', '.r-password__toggle', function () {
      var input = document.getElementById(this.getAttribute('aria-controls'));
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      this.setAttribute('aria-pressed', show ? 'true' : 'false');
      this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    // Fade sections in as they scroll into view.
    var reveal = document.querySelectorAll('.r-reveal');
    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12 });
      reveal.forEach(function (el) { observer.observe(el); });
    } else {
      reveal.forEach(function (el) { el.classList.add('is-visible'); });
    }

    // Pages at a deeper clean URL (car/...) have a <base>, which would turn plain "#..."
    // links into links to the home page. Keep them on the page instead.
    if (document.querySelector('base')) {
      $(document).on('click', 'a[href^="#"]:not([data-bs-toggle])', function (event) {
        var hash = this.getAttribute('href');
        var target = hash.length > 1 ? document.getElementById(hash.slice(1)) : null;
        event.preventDefault();
        if (target) {
          target.scrollIntoView({ behavior: 'smooth' });
        } else {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      });
    }
  });
})(jQuery);
