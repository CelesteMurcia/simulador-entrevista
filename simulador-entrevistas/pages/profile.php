<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';

try {
    $pdo = getDB();

    // ── Datos del usuario ─────────────────────────────────
    $stmt = $pdo->prepare('SELECT id, email, username, avatar, role, plan, plan_started_at, plan_expires_at, created_at FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { header('Location: ../pages/login.php'); exit; }

    // ── Stats de juego ────────────────────────────────────
    $stmt = $pdo->prepare('SELECT * FROM user_game_stats WHERE user_id = ?');
    $stmt->execute([$currentUserId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'level_global' => 1, 'xp' => 0, 'total_interviews_completed' => 0,
        'average_score' => 0, 'best_score' => 0, 'streak_days' => 0,
        'last_interview_date' => null,
    ];

    // ── Historial de partidas ─────────────────────────────
    $stmt = $pdo->prepare('
        SELECT up.*, i.title, i.category, i.slug, i.level AS interview_level
        FROM user_progress up
        LEFT JOIN interviews i
          ON CONCAT(i.type, \'/\', i.category, \'/\', i.slug) = up.interview_id
          AND i.level = CASE up.level
            WHEN 1 THEN \'easy\'
            WHEN 2 THEN \'medium\'
            WHEN 3 THEN \'hard\'
          END
        WHERE up.user_id = ?
        ORDER BY up.finished_at DESC
        LIMIT 20
    ');
    $stmt->execute([$currentUserId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Niveles desbloqueados ─────────────────────────────
    $stmt = $pdo->prepare('SELECT interview_id, max_level FROM user_levels WHERE user_id = ?');
    $stmt->execute([$currentUserId]);
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── XP ───────────────────────────────────────────────
    $xpPerLevel  = 100;
    $xpIntoLevel = $stats['xp'] % $xpPerLevel;
    $xpPct       = round($xpIntoLevel / $xpPerLevel * 100);

    // ── Avatar ────────────────────────────────────────────
    $initial   = strtoupper(mb_substr($user['username'], 0, 1));
    $avatarUrl = null;
    if (!empty($user['avatar'])) {
        $avatarPath = __DIR__ . '/../assets/img/users/' . $user['avatar'];
        if (file_exists($avatarPath)) {
            $avatarUrl = '../assets/img/users/' . $user['avatar'] . '?v=' . filemtime($avatarPath);
        }
    }

    // ── Días miembro ──────────────────────────────────────
    $joinDate   = new DateTime($user['created_at']);
    $now        = new DateTime();
    $daysJoined = (int)$joinDate->diff($now)->days;

    // ── Plan ──────────────────────────────────────────────
    $userPlan    = 'free';
    $planExpired = false;
    if (!empty($user['plan'])) {
        if ($user['plan'] !== 'free' && !empty($user['plan_expires_at'])) {
            $expires     = new DateTime($user['plan_expires_at']);
            $planExpired = (new DateTime()) > $expires;
            $userPlan    = $planExpired ? 'free' : $user['plan'];
        } else {
            $userPlan = $user['plan'];
        }
    }

    $planMeta = [
        'free'  => ['label'=>'Gratuito', 'icon'=>'fa-circle',  'color'=>'var(--text-muted)',  'limit'=>'50 usos/día'],
        'pro'   => ['label'=>'Pro',      'icon'=>'fa-bolt',     'color'=>'var(--brand-light)', 'limit'=>'250 usos/día'],
        'elite' => ['label'=>'Elite',    'icon'=>'fa-crown',    'color'=>'#facc15',            'limit'=>'Ilimitados'],
    ];
    $pm = $planMeta[$userPlan] ?? $planMeta['free'];

    // Variables también para el header
    $isAdmin = $user['role'] === 'admin';

} catch (PDOException $e) {
    die('Error de base de datos.');
}

// ── helpers ───────────────────────────────────────────────
function levelLabel(int $l): string {
    return match($l) { 1 => 'Fácil', 2 => 'Medio', 3 => 'Difícil', default => "Nv.$l" };
}
function levelClass(int $l): string {
    return match($l) { 1 => 'easy', 2 => 'medium', 3 => 'hard', default => 'easy' };
}
function scoreColor(float $s): string {
    if ($s >= 80) return '#34d399';
    if ($s >= 55) return '#facc15';
    return '#f87171';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil — <?= htmlspecialchars($user['username']) ?></title>

  <!-- global.css, fuentes, Font Awesome, header.css y plans-modal.css
       los carga includes/header.php — aquí solo el CSS propio del perfil -->
  <link rel="stylesheet" href="../assets/css/pages/profile.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="profile-page">
  <div class="profile-body">

    <!-- ── Hero Card ── -->
    <div class="hero-card">
      <div class="avatar-wrap">
        <div class="avatar"
             onclick="document.getElementById('avatar-input').click()"
             id="avatar-el"
             <?php if ($avatarUrl): ?>
               style="background-image:url('<?= htmlspecialchars($avatarUrl) ?>');background-size:cover;background-position:center;background-color:transparent;"
             <?php endif; ?>>
          <?php if (!$avatarUrl): ?><?= htmlspecialchars($initial) ?><?php endif; ?>
        </div>
        <div class="avatar-upload" onclick="document.getElementById('avatar-input').click()" title="Cambiar foto">
          <i class="fa-solid fa-camera" id="avatar-upload-icon"></i>
        </div>
        <input type="file" id="avatar-input" accept="image/jpeg,image/png,image/webp,image/gif" onchange="uploadAvatar(this)">
      </div>

      <div class="hero-info">
        <div class="hero-username"><?= htmlspecialchars($user['username']) ?></div>
        <div class="hero-email"><?= htmlspecialchars($user['email']) ?></div>

        <div class="hero-badges">
          <span class="badge badge-role">
            <i class="fa-solid <?= $user['role'] === 'admin' ? 'fa-shield-halved' : 'fa-user' ?>"></i>
            <?= ucfirst($user['role']) ?>
          </span>
          <span class="badge badge-days">
            <i class="fa-solid fa-calendar-days"></i>
            <?= $daysJoined ?> día<?= $daysJoined !== 1 ? 's' : '' ?> en la plataforma
          </span>
          <?php if ($stats['streak_days'] > 0): ?>
          <span class="badge badge-streak">
            <i class="fa-solid fa-fire"></i>
            <?= $stats['streak_days'] ?> días seguidos
          </span>
          <?php endif; ?>
          <span class="badge badge-plan-<?= $userPlan ?>" onclick="openPlans()" style="cursor:pointer" title="Ver planes">
            <i class="fa-solid <?= $pm['icon'] ?>"></i>
            <?= $pm['label'] ?>
          </span>
        </div>

        <div class="xp-row">
          <span class="xp-label">Nv. <?= $stats['level_global'] ?></span>
          <div class="xp-bar-wrap">
            <div class="xp-bar-fill" style="width: <?= $xpPct ?>%"></div>
          </div>
          <span class="xp-pct"><?= $stats['xp'] ?> XP</span>
        </div>
      </div>

      <div class="hero-level">
        <div class="level-orb"><?= $stats['level_global'] ?></div>
        <div class="level-orb-label">Nivel</div>
      </div>
    </div>

    <!-- ── Stats Grid ── -->
    <div class="stats-grid">
      <div class="stat-card" style="--card-accent: var(--brand)">
        <div class="stat-card-icon"><i class="fa-solid fa-trophy"></i></div>
        <div class="stat-card-value"><?= $stats['total_interviews_completed'] ?></div>
        <div class="stat-card-label">Entrevistas completadas</div>
      </div>
      <div class="stat-card" style="--card-accent: #34d399">
        <div class="stat-card-icon" style="color:#34d399"><i class="fa-solid fa-chart-line"></i></div>
        <div class="stat-card-value"><?= number_format((float)$stats['average_score'], 1) ?></div>
        <div class="stat-card-label">Promedio global</div>
      </div>
      <div class="stat-card" style="--card-accent: #facc15">
        <div class="stat-card-icon" style="color:#facc15"><i class="fa-solid fa-star"></i></div>
        <div class="stat-card-value"><?= number_format((float)$stats['best_score'], 1) ?></div>
        <div class="stat-card-label">Mejor puntuación</div>
      </div>
      <div class="stat-card" style="--card-accent: var(--accent)">
        <div class="stat-card-icon" style="color:var(--accent-light)"><i class="fa-solid fa-fire-flame-curved"></i></div>
        <div class="stat-card-value"><?= $stats['streak_days'] ?></div>
        <div class="stat-card-label">Racha actual (días)</div>
      </div>
    </div>

    <!-- ── Historial ── -->
    <div class="section-block">
      <div class="section-title">
        <i class="fa-solid fa-clock-rotate-left"></i>
        Historial de partidas
      </div>
      <div class="history-card">
        <?php if (empty($history)): ?>
          <div class="history-empty">
            <i class="fa-solid fa-ghost"></i>
            <p>Todavía no has completado ninguna entrevista.</p>
          </div>
        <?php else: ?>
          <table class="history-table">
            <thead>
              <tr>
                <th>Entrevista</th>
                <th>Nivel</th>
                <th>Resultado</th>
                <th>Puntuación</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $row):
                $lvlClass = levelClass((int)$row['level']);
                $lvlLabel = levelLabel((int)$row['level']);
                $sc    = (float)$row['score_global'];
                $color = scoreColor($sc);
                $date  = new DateTime($row['finished_at']);
                $interviewName = $row['title']
                  ? htmlspecialchars($row['title'])
                  : htmlspecialchars($row['interview_id']);
              ?>
              <tr>
                <td>
                  <div class="history-title"><?= $interviewName ?></div>
                  <div class="history-sub">
                    Precisión <?= $row['score_technical_accuracy'] ?> &bull;
                    Claridad <?= $row['score_clarity'] ?> &bull;
                    Completitud <?= $row['score_completeness'] ?>
                  </div>
                </td>
                <td>
                  <span class="lvl-badge lvl-<?= $lvlClass ?>">
                    <?= match($lvlClass) {
                      'easy'   => '<i class="fa-solid fa-seedling"></i>',
                      'medium' => '<i class="fa-solid fa-fire"></i>',
                      'hard'   => '<i class="fa-solid fa-skull"></i>',
                    } ?>
                    <?= $lvlLabel ?>
                  </span>
                </td>
                <td>
                  <span class="passed-badge <?= $row['passed'] ? 'passed-yes' : 'passed-no' ?>">
                    <i class="fa-solid <?= $row['passed'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                    <?= $row['passed'] ? 'Aprobado' : 'Fallido' ?>
                  </span>
                </td>
                <td>
                  <div class="score-pill" style="color:<?= $color ?>">
                    <?= number_format($sc, 1) ?>
                    <span style="font-size:11px;color:var(--text-subtle);font-family:var(--font-body);font-weight:400">/100</span>
                  </div>
                </td>
                <td style="white-space:nowrap">
                  <?= $date->format('d/m/Y') ?><br>
                  <span style="font-size:11px;color:var(--text-subtle)"><?= $date->format('H:i') ?></span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Niveles desbloqueados ── -->
    <?php if (!empty($levels)): ?>
    <div class="section-block">
      <div class="section-title">
        <i class="fa-solid fa-lock-open"></i>
        Niveles desbloqueados
      </div>
      <div class="levels-grid">
        <?php foreach ($levels as $lv):
          $parts = explode('/', $lv['interview_id']);
          $slug  = $parts[2] ?? $lv['interview_id'];
          $maxLv = (int)$lv['max_level'];
        ?>
        <div class="level-card">
          <div class="level-card-icon"><i class="fa-solid fa-file-code"></i></div>
          <div class="level-card-info">
            <div class="level-card-name"><?= htmlspecialchars(ucfirst($slug)) ?></div>
            <div class="level-card-sub">Nivel máximo alcanzado: <?= $maxLv ?></div>
            <div class="level-stars" style="margin-top:5px">
              <?php for ($s = 1; $s <= 3; $s++): ?>
                <i class="fa-solid fa-star <?= $s <= $maxLv ? 'star-on' : 'star-off' ?>"></i>
              <?php endfor; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Plan actual ── -->
    <div class="section-block">
      <div class="section-title">
        <i class="fa-solid fa-credit-card"></i>
        Suscripción
      </div>
      <div class="plan-profile-card plan-profile-<?= $userPlan ?>">
        <div class="plan-profile-left">
          <div class="plan-profile-icon">
            <i class="fa-solid <?= $pm['icon'] ?>"></i>
          </div>
          <div>
            <div class="plan-profile-name"><?= $pm['label'] ?></div>
            <div class="plan-profile-sub">
              <?php if ($userPlan === 'free'): ?>
                Plan gratuito &bull; <?= $pm['limit'] ?> de IA
              <?php elseif (!empty($user['plan_expires_at'])): ?>
                <?php $exp = new DateTime($user['plan_expires_at']); ?>
                Activo hasta <?= $exp->format('d/m/Y') ?> &bull; <?= $pm['limit'] ?> de IA
              <?php endif; ?>
            </div>
          </div>
        </div>
        <button class="plan-profile-btn" onclick="openPlans()">
          <?= $userPlan === 'free'
            ? '<i class="fa-solid fa-arrow-up"></i> Mejorar plan'
            : '<i class="fa-solid fa-eye"></i> Ver planes' ?>
        </button>
      </div>
    </div>

    <!-- ── Danger zone ── -->
    <div class="section-block">
      <div class="section-title">
        <i class="fa-solid fa-triangle-exclamation" style="color:#f87171"></i>
        Sesión
      </div>
      <div class="danger-zone">
        <div class="danger-zone-text">
          <h4>Cerrar sesión</h4>
          <p>Se cerrará tu sesión en este dispositivo. Tus datos y progreso se conservan.</p>
        </div>
        <a href="../actions/logout.php" class="btn-logout">
          <i class="fa-solid fa-right-from-bracket"></i>
          Cerrar sesión
        </a>
      </div>
    </div>

  </div><!-- /profile-body -->
</div><!-- /profile-page -->

<!-- JS del perfil (avatar upload, toast, XP animation) -->
<script src="../assets/js/pages/profile.js"></script>

</body>
</html>