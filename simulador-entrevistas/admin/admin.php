<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';

// ── Solo admins ───────────────────────────────────────────
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['role'] !== 'admin') {
        header('Location: ../pages/home.php');
        exit;
    }
} catch (PDOException $e) {
    die('Error de base de datos.');
}

// ── Traer entrevistas con conteo de escenarios ────────────
$interviews = $pdo->query('
    SELECT i.*,
           (SELECT COUNT(*) FROM scenarios s WHERE s.interview_id = i.id) AS scenario_count
    FROM interviews i
    ORDER BY i.category, i.slug, FIELD(i.level,"easy","medium","hard")
')->fetchAll(PDO::FETCH_ASSOC);

// ── Agrupar por categoría/slug ────────────────────────────
$grouped = [];
foreach ($interviews as $row) {
    $grouped[$row['category']][$row['slug']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Entrevistas</title>
  <!-- Fuentes, FA y global.css los carga admin_header.php -->
  <link rel="stylesheet" href="../assets/css/admin/admin.css">
</head>
<body>
<div class="admin-wrap">

  <?php $adminActivePage = 'interviews'; require_once 'admin_header.php'; ?>

  <div class="admin-body">

    <!-- Header de sección -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:28px;">
      <div>
        <h1 style="font-family:var(--font-head); font-size:22px; font-weight:800; color:var(--text); margin:0; letter-spacing:-0.5px;">Entrevistas</h1>
        <p style="font-size:13px; color:var(--text-muted); margin:4px 0 0;">Gestiona las entrevistas y sus preguntas.</p>
      </div>
      <a href="admin_edit.php" class="btn-hdr primary">
        <i class="fa-solid fa-plus"></i> <span>Nueva entrevista</span>
      </a>
    </div>

    <!-- Stats -->
    <?php
      $totalInterviews = count($interviews);
      $activeCount     = count(array_filter($interviews, fn($i) => $i['active']));
      $categories      = count($grouped);
      $totalScenarios  = array_sum(array_column($interviews, 'scenario_count'));
    ?>
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-label">Entrevistas</div>
        <div class="stat-value accent"><?= $totalInterviews ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Activas</div>
        <div class="stat-value"><?= $activeCount ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Categorías</div>
        <div class="stat-value"><?= $categories ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Escenarios</div>
        <div class="stat-value"><?= $totalScenarios ?></div>
      </div>
    </div>

    <!-- Entrevistas agrupadas -->
    <?php if (empty($grouped)): ?>
      <div class="empty-state">
        <i class="fa-solid fa-folder-open"></i>
        <p>No hay entrevistas todavía. Crea la primera.</p>
      </div>
    <?php else: ?>
      <?php foreach ($grouped as $category => $slugs): ?>
        <div class="category-section">
          <div class="category-title">
            <i class="fa-solid fa-folder"></i>
            <?= htmlspecialchars(ucfirst($category)) ?>
          </div>

          <?php foreach ($slugs as $slug => $levels): ?>
            <div class="interview-group">
              <div class="interview-group-header">
                <div class="interview-group-name"><?= htmlspecialchars($levels[0]['title']) ?></div>
                <span class="interview-slug-label"><?= htmlspecialchars($slug) ?></span>
                <span class="level-meta"><?= count($levels) ?> nivel<?= count($levels) !== 1 ? 'es' : '' ?></span>
              </div>

              <?php foreach ($levels as $iv): ?>
                <div class="level-row">
                  <span class="level-badge level-<?= $iv['level'] ?>">
                    <?= match($iv['level']) {
                      'easy'   => '<i class="fa-solid fa-seedling"></i> Fácil',
                      'medium' => '<i class="fa-solid fa-fire"></i> Medio',
                      'hard'   => '<i class="fa-solid fa-skull"></i> Difícil',
                    } ?>
                  </span>
                  <div class="level-title"><?= htmlspecialchars($iv['title']) ?></div>
                  <span class="level-meta">
                    <i class="fa-solid fa-list-ul" style="opacity:.5;margin-right:4px"></i>
                    <?= $iv['scenario_count'] ?> escenarios
                  </span>
                  <span
                    class="status-pill <?= $iv['active'] ? 'active' : 'inactive' ?>"
                    onclick="toggleActive(<?= (int)$iv['id'] ?>, this)"
                  >
                    <i class="fa-solid <?= $iv['active'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                    <?= $iv['active'] ? 'Activa' : 'Inactiva' ?>
                  </span>
                  <div class="row-actions">
                    <a href="admin_edit.php?id=<?= (int)$iv['id'] ?>" class="btn-icon" title="Editar">
                      <i class="fa-solid fa-pen"></i>
                    </a>
                    <button
                      class="btn-icon danger"
                      title="Eliminar"
                      onclick="confirmDelete(<?= (int)$iv['id'] ?>, '<?= htmlspecialchars(addslashes($iv['title'])) ?> (<?= $iv['level'] ?>)')"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</div>

<!-- Modal eliminar -->
<div class="modal-overlay" id="delete-modal">
  <div class="modal">
    <div class="modal-title">¿Eliminar entrevista?</div>
    <p class="modal-subtitle" id="delete-modal-subtitle"></p>
    <p style="font-size:13px;color:var(--text-muted)">
      Esta acción eliminará también todos los escenarios asociados y no se puede deshacer.
    </p>
    <div class="modal-actions">
      <button class="btn-modal cancel" onclick="closeModal()">Cancelar</button>
      <button class="btn-modal confirm-delete" id="confirm-delete-btn">
        <i class="fa-solid fa-trash"></i> Eliminar
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast">
  <i class="fa-solid fa-circle-check"></i>
  <span id="toast-msg"></span>
</div>

<script>
async function toggleActive(id, el) {
  try {
    const res  = await fetch('../actions/admin_toggle.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    const isActive = data.active === 1;
    el.className = 'status-pill ' + (isActive ? 'active' : 'inactive');
    el.innerHTML = `<i class="fa-solid ${isActive ? 'fa-circle-check' : 'fa-circle-xmark'}"></i> ${isActive ? 'Activa' : 'Inactiva'}`;
    showToast(isActive ? 'Entrevista activada' : 'Entrevista desactivada', 'success');
  } catch (e) { showToast('Error al cambiar estado', 'error'); }
}

function confirmDelete(id, name) {
  const btn = document.getElementById('confirm-delete-btn');
  btn.dataset.deleteId = id;
  document.getElementById('delete-modal-subtitle').textContent = name;
  document.getElementById('delete-modal').classList.add('open');
}

function closeModal() {
  document.getElementById('delete-modal').classList.remove('open');
}

document.getElementById('confirm-delete-btn').addEventListener('click', async function () {
  const id = parseInt(this.dataset.deleteId, 10);
  if (!id) return;
  closeModal();
  try {
    const res  = await fetch('../actions/admin_delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    showToast('Entrevista eliminada', 'success');
    setTimeout(() => location.reload(), 900);
  } catch (e) {
    showToast('Error al eliminar: ' + e.message, 'error');
  }
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.className = 'toast ' + type;
  t.querySelector('i').className = 'fa-solid ' + (type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark');
  document.getElementById('toast-msg').textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}
</script>
</body>
</html>