<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';
$pageStyles = ['../assets/css/game/levels.css'];
include '../includes/header.php';

// ── Parámetros ────────────────────────────────────────────
$type = isset($_GET['type']) ? preg_replace('/[^a-z_]/', '', $_GET['type']) : 'technical';

// ── Leer estructura desde la DB ───────────────────────────
// Agrupa: category → slug → level → { id, title }
$structure = [];

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('
        SELECT id, category, slug, level, title
        FROM interviews
        WHERE type = ? AND active = 1
        ORDER BY category ASC, slug ASC, FIELD(level, "easy", "medium", "hard")
    ');
    $stmt->execute([$type]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $cat   = $row['category'];
        $slug  = $row['slug'];
        $lvl   = $row['level'];

        $structure[$cat][$slug][$lvl] = [
            'id'    => $row['id'],
            'title' => $row['title'],
        ];
    }
} catch (PDOException $e) {
    // Si falla la DB mostramos pantalla vacía, no rompemos
    $structure = [];
}

if (empty($structure)) {
    // No hay nada para este type, volver al menú
    header('Location: menu.php');
    exit;
}

// ── Niveles desbloqueados del usuario ─────────────────────
$unlockedLevels = [];
try {
    $stmt = $pdo->prepare('SELECT interview_id, max_level FROM user_levels WHERE user_id = ?');
    $stmt->execute([$currentUserId]);
    foreach ($stmt->fetchAll() as $row) {
        $unlockedLevels[$row['interview_id']] = (int) $row['max_level'];
    }
} catch (PDOException $e) {
    // nivel 1 por defecto
}

// ── Categoría activa ──────────────────────────────────────
$activeCategory = $_GET['cat'] ?? array_key_first($structure);
if (!isset($structure[$activeCategory])) {
    $activeCategory = array_key_first($structure);
}

// ── Helpers ───────────────────────────────────────────────
function slugToLabel(string $slug): string {
    return ucwords(str_replace(['-', '_'], ' ', $slug));
}

function isLevelUnlocked(string $interviewId, string $level, array $unlocked): bool {
    $order      = ['easy' => 1, 'medium' => 2, 'hard' => 3];
    $required   = $order[$level] ?? 1;
    $maxReached = $unlocked[$interviewId] ?? 1;
    return $required <= $maxReached;
}

$levelLabels = [
    'easy'   => ['label' => 'Fácil',   'icon' => 'fa-seedling', 'color' => 'easy'],
    'medium' => ['label' => 'Medio',   'icon' => 'fa-fire',     'color' => 'medium'],
    'hard'   => ['label' => 'Difícil', 'icon' => 'fa-skull',    'color' => 'hard'],
];

$categoryIcons = [
    'programmer'    => 'fa-code',
    'cybersecurity' => 'fa-shield-halved',
    'databases'     => 'fa-database',
    'networks'      => 'fa-network-wired',
    'ai'            => 'fa-robot',
];
?>

  <div class="levels-layout">

    <!-- Sidebar izquierda — categorías -->
    <aside class="categories-sidebar">
      <div class="sidebar-title">Categorías</div>
      <nav class="categories-nav">
        <?php foreach ($structure as $categorySlug => $interviews): ?>
          <a
            href="?type=<?= $type ?>&cat=<?= $categorySlug ?>"
            class="category-item <?= $categorySlug === $activeCategory ? 'active' : '' ?>"
          >
            <i class="fa-solid <?= $categoryIcons[$categorySlug] ?? 'fa-folder' ?>"></i>
            <span><?= htmlspecialchars(slugToLabel($categorySlug)) ?></span>
            <span class="cat-count"><?= count($interviews) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <!-- Panel derecho — entrevistas -->
    <main class="interviews-panel">

      <?php if (isset($structure[$activeCategory])): ?>

        <div class="panel-header">
          <h2><?= htmlspecialchars(slugToLabel($activeCategory)) ?></h2>
          <p class="panel-subtitle">
            <?php $count = count($structure[$activeCategory]); ?>
            <?= $count ?> entrevista<?= $count !== 1 ? 's' : '' ?> disponible<?= $count !== 1 ? 's' : '' ?>
          </p>
        </div>

        <div class="interviews-grid">
          <?php foreach ($structure[$activeCategory] as $interviewSlug => $levels): ?>
            <?php
              // interview_id sigue siendo el mismo string que usa user_levels
              $interviewId = "$type/$activeCategory/$interviewSlug";
              $firstLevel  = array_key_first($levels);
              $firstTitle  = $levels[$firstLevel]['title'];
            ?>

            <div class="interview-card">

              <div class="interview-card-header">
                <div class="interview-icon">
                  <i class="fa-solid <?= $categoryIcons[$activeCategory] ?? 'fa-file-code' ?>"></i>
                </div>
                <div>
                  <h3><?= htmlspecialchars($firstTitle) ?></h3>
                  <span class="interview-slug"><?= htmlspecialchars(slugToLabel($interviewSlug)) ?></span>
                </div>
              </div>

              <div class="levels-list">
                <?php foreach ($levelLabels as $levelKey => $levelInfo): ?>
                  <?php if (!isset($levels[$levelKey])) continue; ?>
                  <?php $unlocked = isLevelUnlocked($interviewId, $levelKey, $unlockedLevels); ?>
                  <div
                    class="level-row <?= $unlocked ? 'unlocked' : 'locked' ?>"
                    <?php if ($unlocked): ?>
                      data-interview="<?= $interviewId ?>"
                      data-level="<?= $levelKey ?>"
                      onclick="startInterview(this)"
                    <?php endif; ?>
                  >
                    <div class="level-info">
                      <span class="level-icon level-<?= $levelInfo['color'] ?>">
                        <i class="fa-solid <?= $levelInfo['icon'] ?>"></i>
                      </span>
                      <span class="level-label"><?= $levelInfo['label'] ?></span>
                    </div>
                    <div class="level-action">
                      <?php if ($unlocked): ?>
                        <span class="btn-play">Jugar <i class="fa-solid fa-play"></i></span>
                      <?php else: ?>
                        <span class="locked-label"><i class="fa-solid fa-lock"></i> Bloqueado</span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

            </div>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <div class="empty-state">
          <i class="fa-solid fa-folder-open"></i>
          <p>No hay entrevistas en esta categoría todavía.</p>
        </div>
      <?php endif; ?>

    </main>
  </div>

  <script>
    function startInterview(row) {
      const interview = row.dataset.interview;
      const level     = row.dataset.level;
      const parts     = interview.split('/');

      const params = new URLSearchParams({
        type:      parts[0],
        category:  parts[1],
        interview: parts[2],
        level:     level,
      });

      window.location.href = 'play.php?' + params.toString();
    }
  </script>
