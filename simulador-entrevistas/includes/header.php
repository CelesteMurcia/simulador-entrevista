<?php
if (!isset($isAdmin, $avatarUrl, $initial, $userPlan)) {
    require_once __DIR__ . '/../middleware/auth.php';
    require_once __DIR__ . '/../config/db.php';

    $isAdmin   = false;
    $avatarUrl = null;
    $initial   = '?';
    $userPlan  = 'free';

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT role, username, avatar, plan, plan_expires_at FROM users WHERE id = ?');
        $stmt->execute([$currentUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $isAdmin = $row['role'] === 'admin';
            $initial = strtoupper(mb_substr($row['username'], 0, 1));

            if (!empty($row['avatar'])) {
                $path = __DIR__ . '/../assets/img/users/' . $row['avatar'];
                if (file_exists($path)) {
                    $avatarUrl = '../assets/img/users/' . $row['avatar'] . '?v=' . filemtime($path);
                }
            }

            // Plan vigente
            if (!empty($row['plan'])) {
                if ($row['plan'] !== 'free' && !empty($row['plan_expires_at'])) {
                    $expires  = new DateTime($row['plan_expires_at']);
                    $userPlan = (new DateTime()) <= $expires ? $row['plan'] : 'free';
                } else {
                    $userPlan = $row['plan'];
                }
            }
        }
    } catch (PDOException $e) {}
}

// Labels e iconos de plan
$planLabels = ['free' => 'Gratuito', 'pro' => 'Pro', 'elite' => 'Elite'];
$planIcons  = ['free' => 'fa-circle', 'pro' => 'fa-bolt', 'elite' => 'fa-crown'];
$planLabel  = $planLabels[$userPlan] ?? 'Gratuito';
$planIcon   = $planIcons[$userPlan]  ?? 'fa-circle';
?>
<?php if (!defined('HEADER_ASSETS_LOADED')): define('HEADER_ASSETS_LOADED', true); ?>
<!-- ── Fuentes ── -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cabinet+Grotesk:wght@400;500;700;800;900&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<!-- ── Font Awesome ── -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/devicon.min.css">
<!-- ── CSS globales ── -->
<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/pages/header.css">
<link rel="stylesheet" href="../assets/css/pages/footer.css">
<!-- ── CSS home ── -->
<link rel="stylesheet" href="../assets/css/pages/hero.css">
<link rel="stylesheet" href="../assets/css/pages/about.css">
<link rel="stylesheet" href="../assets/css/pages/benefits.css">
<link rel="stylesheet" href="../assets/css/pages/inspiration.css">
<link rel="stylesheet" href="../assets/css/pages/opinions.css">
<!-- ── CSS del modal de planes ── -->
<link rel="stylesheet" href="../assets/css/pages/plans_modal.css">
<!-- ── CSS específico de la página ── -->
<?php if (!empty($pageStyles)): ?>
  <?php foreach ($pageStyles as $style): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($style) ?>">
  <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<nav>
  <a href="../pages/home.php#inicio" class="nav-logo">●</a>

  <ul class="nav-links">
    <li><a href="../pages/home.php#beneficios">Beneficios</a></li>
    <li><a href="../pages/home.php#inspiracion">Inspiración</a></li>
    <li><a href="../pages/home.php#nosotros">Sobre Nosotros</a></li>
    <li><a href="../pages/home.php#testimonios">Testimonios</a></li>
  </ul>

  <div class="nav-actions">
    <?php if ($isAdmin): ?>
      <a href="../admin/admin_dashboard.php" class="btn-admin">
        <i class="fa-solid fa-shield-halved"></i> Admin
      </a>
    <?php endif; ?>

    <a href="../game/menu.php" class="btn-comenzar">COMENZAR</a>

    <!-- Badge de plan -->
    <button class="btn-plan plan-<?= htmlspecialchars($userPlan) ?>"
            onclick="openPlans()"
            title="Ver planes">
      <i class="fa-solid <?= htmlspecialchars($planIcon) ?>"></i>
      Plan: <?= htmlspecialchars($planLabel) ?>
    </button>

    <!-- Avatar / perfil -->
    <a href="../pages/profile.php"
       class="nav-profile <?= $avatarUrl ? 'has-avatar' : 'no-avatar' ?>"
       title="Mi perfil">
      <?php if ($avatarUrl): ?>
        <img src="<?= htmlspecialchars($avatarUrl) ?>"
             alt="Avatar de <?= htmlspecialchars($row['username'] ?? '') ?>">
      <?php else: ?>
        <?= htmlspecialchars($initial) ?>
      <?php endif; ?>
    </a>
  </div>
</nav>

<!-- ── Modal de planes (compartido en todas las páginas) ── -->
<?php include __DIR__ . '/plans_modal.php'; ?>

<!-- ── JS del header (modal + billing toggle) ── -->
<script src="../assets/js/pages/header.js"></script>
