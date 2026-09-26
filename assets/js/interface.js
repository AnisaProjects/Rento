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
  });
})(jQuery);
