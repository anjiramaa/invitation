// assets/js/app.js
// Version: 2025-10-15_v1
// Global JS: ensures module JS runs after DOM loaded.
// Use this for simple helpers: toggle sidebar on mobile, confirm dialogs, etc.

document.addEventListener('DOMContentLoaded', function () {
  // Mobile sidebar toggle
  var toggleBtn = document.getElementById('mobile-sidebar-toggle');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var sb = document.getElementById('sidebar');
      if (!sb) return;
      if (sb.style.display === 'block') sb.style.display = 'none';
      else sb.style.display = 'block';
    });
  }

  // Generic confirm for links with data-confirm
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      var msg = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  // small helper: highlight nav items already done in server-side via class "active"
  // add other global JS helpers here as needed
});
