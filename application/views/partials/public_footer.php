<?php
// application/views/partials/public_footer.php
// Version: 2025-10-15_v1
defined('BASEPATH') OR exit('No direct script access allowed');
?>
  </main>
  <footer class="bg-white border-t mt-10">
    <div class="max-w-6xl mx-auto px-4 py-6 text-sm text-slate-600">
      &copy; <?= date('Y') ?> Invitation. All rights reserved.
    </div>
  </footer>

  <script src="<?= base_url('assets/js/app.js') ?>"></script>
  <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
    <div style="position:fixed;right:12px;bottom:12px;background:#111827;color:#fff;padding:8px;border-radius:6px;font-size:12px;opacity:.9">DEV MODE</div>
  <?php endif; ?>
</body>
</html>
