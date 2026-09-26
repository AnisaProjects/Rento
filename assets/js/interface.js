/*
 * Site-wide behaviour for the public pages (loaded by includes/scripts.php).
 * Carousels are set up on the pages that have them (index.php, vehical-details.php).
 */
(function ($) {
  'use strict';

  $(function () {
    // Header search: on small screens the magnifier shows/hides the search form.
    $('#search_toggle').on('click', function () {
      $('#header-search-form').slideToggle();
    });

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
