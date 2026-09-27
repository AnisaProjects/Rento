/*
 * Rento admin: shell behaviour shared by every admin page.
 *  - sidebar collapse (remembered per browser) and icon tooltips while collapsed
 *  - Bootstrap tooltips and toasts
 *  - confirmation modal for forms with data-confirm (replaces window.confirm)
 *  - DataTables on <table data-a-table> (search, sort, paging, responsive)
 *  - show/hide password buttons
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var bs = window.bootstrap;

  function store(key, value) {
    try { localStorage.setItem(key, value); } catch (e) { /* private mode */ }
  }

  /* ---------- Sidebar collapse (desktop) ---------- */
  var collapseBtn = document.querySelector('[data-a-collapse]');
  var menuTips = [];

  function syncMenuTips() {
    menuTips.forEach(function (tip) { tip.dispose(); });
    menuTips = [];
    if (!root.classList.contains('a-collapsed')) return;
    document.querySelectorAll('.a-sidebar [data-a-tip]').forEach(function (el) {
      menuTips.push(new bs.Tooltip(el, { title: el.dataset.aTip, placement: 'right', trigger: 'hover focus' }));
    });
  }

  if (collapseBtn) {
    collapseBtn.setAttribute('aria-pressed', root.classList.contains('a-collapsed') ? 'true' : 'false');
    collapseBtn.addEventListener('click', function () {
      var collapsed = root.classList.toggle('a-collapsed');
      collapseBtn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
      collapseBtn.setAttribute('aria-label', collapsed ? 'Expand menu' : 'Collapse menu');
      store('rento-admin-collapsed', collapsed ? '1' : '0');
      syncMenuTips();
    });
    // A group opened while collapsed expands the sidebar so its links are visible.
    document.querySelectorAll('.a-menu__toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (root.classList.contains('a-collapsed') && window.matchMedia('(min-width: 992px)').matches) {
          collapseBtn.click();
        }
      });
    });
    syncMenuTips();
  }

  /* ---------- Tooltips & toasts ---------- */
  function initTooltips(scope) {
    (scope || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      bs.Tooltip.getOrCreateInstance(el);
    });
  }
  initTooltips();

  document.querySelectorAll('.r-toast').forEach(function (el) {
    bs.Toast.getOrCreateInstance(el).show();
  });

  /* ---------- Confirmation modal ---------- */
  var modalEl = document.getElementById('a-confirm');
  var modal = modalEl ? bs.Modal.getOrCreateInstance(modalEl) : null;
  var pendingForm = null;

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!modal || !form.matches('form[data-confirm]') || form.dataset.confirmed === '1') return;
    event.preventDefault();
    pendingForm = form;
    modalEl.querySelector('#a-confirm-title').textContent = form.dataset.confirmTitle || 'Are you sure?';
    modalEl.querySelector('[data-confirm-text]').textContent = form.dataset.confirm;
    var extra = modalEl.querySelector('[data-confirm-extra]');
    extra.innerHTML = '';
    var template = form.querySelector('template[data-confirm-template]');
    if (template) extra.appendChild(template.content.cloneNode(true));
    var ok = modalEl.querySelector('[data-confirm-ok]');
    ok.textContent = form.dataset.confirmOk || 'Yes, continue';
    ok.className = 'btn ' + ('confirmDanger' in form.dataset ? 'btn-danger' : 'btn-primary');
    var tip = form.querySelector('[data-bs-toggle="tooltip"]');
    if (tip && bs.Tooltip.getInstance(tip)) bs.Tooltip.getInstance(tip).hide();
    modal.show();
  });

  if (modalEl) {
    modalEl.querySelector('[data-confirm-ok]').addEventListener('click', function () {
      if (!pendingForm) return;
      // Copy any choices made in the modal (e.g. radio buttons) into the form.
      modalEl.querySelectorAll('[data-confirm-extra] input:checked, [data-confirm-extra] select, [data-confirm-extra] textarea').forEach(function (input) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = input.name;
        hidden.value = input.value;
        pendingForm.appendChild(hidden);
      });
      pendingForm.dataset.confirmed = '1';
      var btn = pendingForm.querySelector('[type="submit"]');
      if (pendingForm.requestSubmit && btn) pendingForm.requestSubmit(btn); else pendingForm.submit();
      modal.hide();
    });
    modalEl.addEventListener('hidden.bs.modal', function () { pendingForm = null; });
  }

  /* ---------- DataTables ---------- */
  if (window.DataTable) {
    document.querySelectorAll('table[data-a-table]').forEach(function (table) {
      var order = table.dataset.order ? JSON.parse(table.dataset.order) : [];
      var dt = new DataTable(table, {
        responsive: true,
        order: order,
        pageLength: parseInt(table.dataset.pageLength || '10', 10),
        lengthMenu: [10, 25, 50, 100],
        autoWidth: false,
        columnDefs: [{ targets: 'no-sort', orderable: false, searchable: false }],
        language: {
          search: '',
          searchPlaceholder: table.dataset.searchPlaceholder || 'Search…',
          lengthMenu: '_MENU_ per page',
          info: '_START_–_END_ of _TOTAL_',
          infoEmpty: 'Nothing to show',
          infoFiltered: '(filtered from _MAX_)',
          zeroRecords: 'No matching records',
          emptyTable: table.dataset.empty || 'Nothing here yet'
        },
        layout: {
          topStart: 'search',
          topEnd: 'pageLength',
          bottomStart: 'info',
          bottomEnd: 'paging'
        }
      });
      var search = table.closest('.dt-container') && table.closest('.dt-container').querySelector('.dt-search input');
      if (search) search.setAttribute('aria-label', table.dataset.searchPlaceholder || 'Search the table');
      dt.on('draw responsive-display', function () { initTooltips(table.closest('.dt-container')); });
    });
  }

  /* ---------- Show / hide password ---------- */
  document.querySelectorAll('[data-a-pass]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.aPass);
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.querySelector('i').className = 'fa ' + (show ? 'fa-eye-slash' : 'fa-eye');
    });
  });

  /* ---------- Vehicle form: "Use it" for the smart price suggestion ---------- */
  document.querySelectorAll('[data-use-suggestion]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById('priceperday');
      input.value = btn.dataset.useSuggestion;
      input.focus();
    });
  });
})();
