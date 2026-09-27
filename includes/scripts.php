<?php
// Shared scripts for every public page, included just before </body>. Set before including:
//   $pageUsesCarousel  true on pages with an Owl carousel (optional)
//   $pageScripts       extra script URLs loaded last (optional)
?>
<?php echo flash_render_toasts(); ?>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($pageUsesCarousel)) { ?>
<script src="assets/js/owl.carousel.min.js"></script>
<?php } ?>
<script src="assets/js/interface.js"></script>
<?php foreach ($pageScripts ?? [] as $src) { ?>
<script src="<?php echo htmlentities($src); ?>"></script>
<?php } ?>
