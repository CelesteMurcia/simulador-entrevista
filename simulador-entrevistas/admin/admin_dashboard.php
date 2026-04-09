<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$me || $me['role'] !== 'admin') {
        header('Location: ../pages/home.php'); exit;
    }
} catch (PDOException $e) { die('Error de base de datos.'); }

// ── Crear cuenta admin (POST) ─────────────────────────────
$createMsg = null; $createOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_admin') {
    $newEmail    = trim($_POST['email']    ?? '');
    $newUsername = trim($_POST['username'] ?? '');
    $newPassword = trim($_POST['password'] ?? '');
    if (!$newEmail || !$newUsername || !$newPassword) {
        $createMsg = 'Todos los campos son obligatorios.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $createMsg = 'El email no es válido.';
    } elseif (strlen($newPassword) < 8) {
        $createMsg = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            $hash  = password_hash($newPassword, PASSWORD_BCRYPT);
            $newId = sprintf('%s-%s-%s-%s-%s',
                bin2hex(random_bytes(4)), bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)), bin2hex(random_bytes(2)),
                bin2hex(random_bytes(6)));
            $pdo->prepare('INSERT INTO users (id, email, password_hash, username, role) VALUES (?, ?, ?, ?, \'admin\')')
                ->execute([$newId, $newEmail, $hash, $newUsername]);
            $pdo->prepare('INSERT INTO user_game_stats (user_id) VALUES (?)')->execute([$newId]);
            $createOk  = true;
            $createMsg = "Admin «{$newUsername}» creado correctamente.";
        } catch (PDOException $e) {
            $createMsg = $e->getCode() === '23000'
                ? 'El email o username ya existe.'
                : 'Error: ' . $e->getMessage();
        }
    }
}

// ── Queries ───────────────────────────────────────────────
$stats = $pdo->query('
    SELECT
        (SELECT COUNT(*) FROM users)                                        AS total_users,
        (SELECT COUNT(*) FROM users WHERE role = "admin")                   AS total_admins,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE())     AS new_today,
        (SELECT COUNT(*) FROM interviews)                                   AS total_interviews,
        (SELECT COUNT(*) FROM interviews WHERE active = 1)                  AS active_interviews,
        (SELECT COUNT(*) FROM scenarios)                                    AS total_scenarios,
        (SELECT COUNT(*) FROM user_progress)                                AS total_completions,
        (SELECT COUNT(*) FROM user_progress WHERE passed = 1)               AS total_passed,
        (SELECT COALESCE(AVG(score_global), 0) FROM user_progress)          AS avg_score_global,
        (SELECT COALESCE(SUM(gemini_uses), 0) FROM users)                   AS total_gemini_uses,
        (SELECT COALESCE(SUM(gemini_uses_today), 0) FROM users
         WHERE gemini_last_use = CURDATE())                                 AS gemini_uses_today
')->fetch(PDO::FETCH_ASSOC);

$recentUsers = $pdo->query('
    SELECT u.id, u.username, u.email, u.role, u.created_at,
           COALESCE(g.total_interviews_completed, 0) AS interviews_done,
           COALESCE(g.average_score, 0)              AS avg_score,
           COALESCE(g.xp, 0)                         AS xp
    FROM users u
    LEFT JOIN user_game_stats g ON g.user_id = u.id
    ORDER BY u.created_at DESC LIMIT 10
')->fetchAll(PDO::FETCH_ASSOC);

$topInterviews = $pdo->query('
    SELECT interview_id, COUNT(*) AS plays,
           SUM(passed) AS passes, ROUND(AVG(score_global), 1) AS avg_score
    FROM user_progress
    GROUP BY interview_id ORDER BY plays DESC LIMIT 6
')->fetchAll(PDO::FETCH_ASSOC);

$activity = $pdo->query('
    SELECT DATE(finished_at) AS day, COUNT(*) AS completions
    FROM user_progress
    WHERE finished_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(finished_at) ORDER BY day ASC
')->fetchAll(PDO::FETCH_ASSOC);

$activityMap = [];
for ($i = 6; $i >= 0; $i--) {
    $activityMap[date('Y-m-d', strtotime("-{$i} days"))] = 0;
}
foreach ($activity as $row) { $activityMap[$row['day']] = (int)$row['completions']; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Dashboard</title>
  <!-- Fuentes, FA, global.css y admin-header.css los carga admin_header.php -->
  <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css">
</head>
<body>
<div class="admin-wrap">

  <?php $adminActivePage = 'dashboard'; require_once 'admin_header.php'; ?>

  <div class="admin-body">

    <div class="section-title"><i class="fa-solid fa-chart-line"></i> Resumen general</div>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
        <div class="stat-label">Usuarios totales</div>
        <div class="stat-value"><?= $stats['total_users'] ?></div>
        <div class="stat-sub"><?= $stats['new_today'] ?> registrados hoy</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="stat-label">Administradores</div>
        <div class="stat-value"><?= $stats['total_admins'] ?></div>
        <div class="stat-sub"><?= $stats['total_users'] - $stats['total_admins'] ?> usuarios normales</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple"><i class="fa-solid fa-file-alt"></i></div>
        <div class="stat-label">Entrevistas</div>
        <div class="stat-value"><?= $stats['total_interviews'] ?></div>
        <div class="stat-sub"><?= $stats['active_interviews'] ?> activas · <?= $stats['total_scenarios'] ?> escenarios</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-trophy"></i></div>
        <div class="stat-label">Completadas</div>
        <div class="stat-value"><?= $stats['total_completions'] ?></div>
        <div class="stat-sub"><?= $stats['total_passed'] ?> aprobadas</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon yellow"><i class="fa-solid fa-star"></i></div>
        <div class="stat-label">Score promedio</div>
        <div class="stat-value"><?= round($stats['avg_score_global']) ?></div>
        <div class="stat-sub">sobre 100 puntos</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon red"><i class="fa-solid fa-robot"></i></div>
        <div class="stat-label">Usos Gemini</div>
        <div class="stat-value"><?= number_format($stats['total_gemini_uses']) ?></div>
        <div class="stat-sub"><?= $stats['gemini_uses_today'] ?> hoy</div>
      </div>
    </div>

    <div class="section-title"><i class="fa-solid fa-chart-bar"></i> Actividad</div>
    <div class="two-col">
      <div class="panel">
        <div class="panel-title">Entrevistas completadas — últimos 7 días</div>
        <?php $maxVal = max(array_values($activityMap)) ?: 1; $days = ['Lu','Ma','Mi','Ju','Vi','Sa','Do']; ?>
        <div class="bar-chart">
          <?php foreach ($activityMap as $date => $count):
            $pct = round(($count / $maxVal) * 100);
            $dayName = $days[(int)date('N', strtotime($date)) - 1]; ?>
            <div class="bar-col">
              <div class="bar-val"><?= $count ?: '' ?></div>
              <div class="bar-wrap">
                <div class="bar <?= $count === 0 ? 'zero' : '' ?>" style="height:<?= max(4, $pct) ?>%"></div>
              </div>
              <div class="bar-day"><?= $dayName ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="panel">
        <div class="panel-title">Entrevistas más jugadas</div>
        <?php if (empty($topInterviews)): ?>
          <p style="font-size:13px;color:var(--text-muted)">Sin datos todavía.</p>
        <?php else: ?>
          <div class="top-list">
            <?php foreach ($topInterviews as $i => $iv): ?>
              <div class="top-item">
                <div class="top-rank">#<?= $i + 1 ?></div>
                <div class="top-name"><?= htmlspecialchars($iv['interview_id']) ?></div>
                <div class="top-meta"><?= $iv['plays'] ?> jugadas<br><?= $iv['avg_score'] ?> pts prom.</div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="section-title"><i class="fa-solid fa-users"></i> Usuarios recientes</div>
    <div class="panel" style="padding:0;margin-bottom:40px;overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Usuario</th><th>Email</th><th>Rol</th>
            <th>Entrevistas</th><th>Score prom.</th><th>XP</th><th>Registro</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($u['username']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <span class="role-badge <?= $u['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                <i class="fa-solid <?= $u['role'] === 'admin' ? 'fa-shield-halved' : 'fa-user' ?>"></i>
                <?= ucfirst($u['role']) ?>
              </span>
            </td>
            <td class="td-muted"><?= $u['interviews_done'] ?></td>
            <td class="td-muted"><?= round($u['avg_score'], 1) ?></td>
            <td class="td-muted"><?= $u['xp'] ?></td>
            <td class="td-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="section-title"><i class="fa-solid fa-user-shield"></i> Crear cuenta administrador</div>
    <div class="panel" style="margin-bottom:40px">
      <?php if ($createMsg): ?>
        <div class="alert <?= $createOk ? 'alert-ok' : 'alert-error' ?>">
          <i class="fa-solid <?= $createOk ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
          <?= htmlspecialchars($createMsg) ?>
        </div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="create_admin">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-input" placeholder="superadmin" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input" placeholder="admin@ejemplo.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Contraseña (mín. 8 caracteres)</label>
            <input type="password" name="password" class="form-input" placeholder="••••••••" required>
          </div>
          <button type="submit" class="btn-create">
            <i class="fa-solid fa-user-plus"></i> Crear admin
          </button>
        </div>
      </form>
    </div>

  </div>
</div>
</body>
</html>