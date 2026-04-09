<?php
$adminActivePage = $adminActivePage ?? '';
?>
<!-- ── Recursos del admin (solo se cargan una vez) ── -->
<?php if (!defined('ADMIN_ASSETS_LOADED')): define('ADMIN_ASSETS_LOADED', true); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cabinet+Grotesk:wght@500;700;800&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/admin/admin_header.css">
<?php endif; ?>

<header class="admin-header">
  <div class="admin-header-left">
    <a href="admin_dashboard.php" class="admin-brand">
      <i class="fa-solid fa-bolt"></i>
      kanjiiiiii
      <span class="admin-brand-badge">Admin</span>
    </a>
    <nav class="admin-nav">
      <a href="admin_dashboard.php"
         class="<?= $adminActivePage === 'dashboard'  ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-line"></i> <span>Dashboard</span>
      </a>
      <a href="admin.php"
         class="<?= ($adminActivePage === 'interviews' || $adminActivePage === 'edit') ? 'active' : '' ?>">
        <i class="fa-solid fa-file-lines"></i> <span>Entrevistas</span>
      </a>
    </nav>
  </div>

  <div class="admin-header-right">
    <?php if ($adminActivePage === 'edit'): ?>
      <!-- En la pantalla de edición: botón Cancelar vuelve al listado -->
      <a href="admin.php" class="btn-hdr">
        <i class="fa-solid fa-arrow-left"></i> <span>Regresar</span>
      </a>
    <?php else: ?>
      <a href="../game/menu.php" class="btn-hdr">
        <i class="fa-solid fa-gamepad"></i> <span>Juego</span>
      </a>
      <a href="../actions/logout.php" class="btn-hdr">
        <i class="fa-solid fa-right-from-bracket"></i> <span>Salir</span>
      </a>
    <?php endif; ?>
  </div>
</header>