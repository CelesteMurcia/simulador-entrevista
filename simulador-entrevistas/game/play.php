<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';
require_once '../config/gemini.php';

$type      = isset($_GET['type'])      ? preg_replace('/[^a-z_]/', '', $_GET['type'])      : 'technical';
$category  = isset($_GET['category'])  ? preg_replace('/[^a-z_]/', '', $_GET['category'])  : 'programmer';
$interview = isset($_GET['interview']) ? preg_replace('/[^a-z_]/', '', $_GET['interview']) : 'web';
$level     = isset($_GET['level'])     ? preg_replace('/[^a-z_]/', '', $_GET['level'])     : 'easy';

// ── Cargar entrevista y escenarios desde la DB ────────────
try {
    $pdo = getDB();

    // Traer la entrevista
    $stmt = $pdo->prepare('
        SELECT id, title, interviewer_style
        FROM interviews
        WHERE type = ? AND category = ? AND slug = ? AND level = ? AND active = 1
        LIMIT 1
    ');
    $stmt->execute([$type, $category, $interview, $level]);
    $interviewRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$interviewRow) {
        die('Error: no se encontró la entrevista en la base de datos.');
    }

    $stmtUser = $pdo->prepare('SELECT role, gemini_uses_today, gemini_last_use FROM users WHERE id = ?');
    $stmtUser->execute([$currentUserId]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    $isAdmin      = ($currentUser['role'] ?? '') === 'admin';
    $today        = date('Y-m-d');
    $usesToday    = ($currentUser['gemini_last_use'] === $today) ? (int)$currentUser['gemini_uses_today'] : 0;
    $usesLeft     = $isAdmin ? -1 : max(0, GEMINI_DAILY_LIMIT - $usesToday);

    // Traer los escenarios ordenados por posición
    $stmt = $pdo->prepare('
        SELECT position, phase, question,        
              evaluation_hint
        FROM scenarios
        WHERE interview_id = ?
        ORDER BY position ASC
    ');
    $stmt->execute([$interviewRow['id']]);
    $scenarioRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($scenarioRows)) {
        die('Error: la entrevista no tiene escenarios cargados.');
    }

} catch (PDOException $e) {
    die('Error de base de datos: ' . $e->getMessage());
}

// ── Construir el array de escenarios para el JS ───────────
// Mantiene la misma forma que espera game.js
$scenarios = array_map(function ($row) {
    return [
        'phase'            => $row['phase'],           
        'question'         => $row['question'],
        'evaluation_hint'  => $row['evaluation_hint'],
    ];
}, $scenarioRows);

// ── ¿Existe el siguiente nivel? ───────────────────────────
$levelOrder = ['easy' => 1, 'medium' => 2, 'hard' => 3];
$levelOrderFlip = array_flip($levelOrder); // 1=>'easy', 2=>'medium', 3=>'hard'
$currentLevelNum = $levelOrder[$level] ?? 1;
$nextLevelSlug   = $levelOrderFlip[$currentLevelNum + 1] ?? null;

$hasNextLevel = false;
if ($nextLevelSlug) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM interviews
        WHERE type = ? AND category = ? AND slug = ? AND level = ? AND active = 1
    ');
    $stmt->execute([$type, $category, $interview, $nextLevelSlug]);
    $hasNextLevel = (bool) $stmt->fetchColumn();
}

$meta = [
    'title'             => $interviewRow['title'],
    'interviewer_style' => $interviewRow['interviewer_style'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Entrevista — <?= htmlspecialchars($meta['title']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/game/play.css">
</head>
<body class="level-<?= $level ?>">

  <!-- ══ FONDO DE OFICINA ══ -->
  <div class="scene-bg">
    <img src="../assets/img/office_bg.png" style="width:100%;height:100%;object-fit:cover">
    <div class="bg-vignette"></div>
  </div>

  <!-- ══ ENTREVISTADOR ══ -->
  <div class="interviewer-wrap">
    <div class="interviewer-states" id="interviewer-states">
      <div class="interviewer-img interviewer-neutral active" id="iv-neutral"></div>
      <div class="interviewer-img interviewer-happy"   id="iv-happy"></div>
      <div class="interviewer-img interviewer-bad"     id="iv-bad"></div>
    </div>
  </div>

  <!-- ══ MANOS DEL JUGADOR ══ -->
  <div class="hands-wrap">
    <div class="hands-placeholder hands-<?= $level ?>"></div>
  </div>

  <!-- ══ BARRA DE PUNTUACIÓN VERTICAL ══ -->
  <div class="score-panel">
    <div class="score-frame">
      <div class="score-track">
        <div class="score-fill" id="score-fill"></div>
        <div class="score-marker" id="score-marker" style="bottom:70%">
          <span class="score-marker-line"></span>
          <span class="score-marker-label">70</span>
        </div>
      </div>
      <div class="score-value" id="val-global">0</div>
      <div class="score-label">Score</div>
    </div>
    <div class="dots-vertical" id="dots"></div>
  </div>

  <!-- ══ BOTÓN OPCIONES ══ -->
  <button class="options-btn" id="options-btn" onclick="toggleMenu()">
    <i class="fa-solid fa-bars"></i>
  </button>

  <!-- ══ PANEL LATERAL DE OPCIONES ══ -->
  <div class="side-menu" id="side-menu">
    <div class="side-menu-header">
      <span class="side-menu-title">Opciones</span>
      <button class="side-menu-close" onclick="toggleMenu()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <nav class="side-menu-nav">
      <a href="levels.php?type=<?= $type ?>&cat=<?= $category ?>" class="side-menu-item">
        <i class="fa-solid fa-arrow-left"></i> Volver al menú
      </a>
      <a href="menu.php" class="side-menu-item">
        <i class="fa-solid fa-house"></i> Inicio
      </a>
      <a href="../actions/logout.php" class="side-menu-item side-menu-danger">
        <i class="fa-solid fa-right-from-bracket"></i> Salir
      </a>
    </nav>
    <div class="side-menu-meta">
      <span class="meta-chip"><?= htmlspecialchars(ucfirst($category)) ?></span>
      <span class="meta-chip meta-chip-level"><?= htmlspecialchars(ucfirst($level)) ?></span>
    </div>
  </div>
  <div class="side-menu-overlay" id="side-overlay" onclick="toggleMenu()"></div>

  <!-- ══ ZONA DE DIÁLOGO ══ -->
  <div class="dialogue-zone" id="dialogue-zone">

    <div class="dialogue-box" id="box-dialogue">
      <div class="dialogue-speaker">Entrevistador</div>
      <div class="dialogue-text" id="dialogue-text"></div>
      <button class="dialogue-next-btn" id="btn-next-dialogue" onclick="goToAnswer()">
        Responder <i class="fa-solid fa-arrow-right"></i>
      </button>
    </div>

    <div class="answer-zone" id="box-answer" style="display:none">
      <div class="question-strip" id="question-strip"></div>
      <div class="answer-box">
        <textarea
          id="answer-input"
          placeholder="Escribe tu respuesta aquí..."
          maxlength="600"
          onkeydown="handleEnter(event)"
          oninput="updateCharCount()"
        ></textarea>
        <div class="answer-footer">
          <span class="char-count" id="char-count">0 / 600</span>
          <span id="uses-left" style="display:none;font-size:11px;"></span>
          <button class="confirm-btn" id="confirm-btn" onclick="submitAnswer()">
            Confirmar <i class="fa-solid fa-paper-plane"></i>
          </button>
        </div>
        <div class="ai-thinking" id="ai-thinking" style="display:none">
          <span class="thinking-dot"></span>
          <span class="thinking-dot"></span>
          <span class="thinking-dot"></span>
          <span>Evaluando respuesta...</span>
        </div>
      </div>
    </div>

    <div class="feedback-box" id="box-feedback" style="display:none">
      <div class="dialogue-speaker" id="feedback-speaker">Entrevistador</div>
      <div class="dialogue-text" id="feedback-text"></div>
      <div class="feedback-metrics" id="feedback-metrics"></div>
      <button class="dialogue-next-btn" id="btn-next-feedback" onclick="nextScenario()">
        Siguiente <i class="fa-solid fa-arrow-right"></i>
      </button>
    </div>

  </div>

  <!-- ══ PANTALLA DE RESULTADOS ══ -->
  <div class="results-screen" id="results-screen" style="display:none">
    <div class="results-card">
      <div class="results-icon" id="results-icon">🎉</div>
      <h2 class="results-title" id="results-title"></h2>
      <p class="results-subtitle" id="results-subtitle"></p>
      <div class="results-score" id="results-global"></div>
      <div class="results-breakdown">
        <div class="breakdown-item">
          <div class="breakdown-label">Precisión técnica</div>
          <div class="breakdown-bar-wrap">
            <div class="breakdown-bar" id="bar-ta"></div>
          </div>
          <div class="breakdown-val" id="r-technical-accuracy"></div>
        </div>
        <div class="breakdown-item">
          <div class="breakdown-label">Claridad</div>
          <div class="breakdown-bar-wrap">
            <div class="breakdown-bar" id="bar-cl"></div>
          </div>
          <div class="breakdown-val" id="r-clarity"></div>
        </div>
        <div class="breakdown-item">
          <div class="breakdown-label">Completitud</div>
          <div class="breakdown-bar-wrap">
            <div class="breakdown-bar" id="bar-co"></div>
          </div>
          <div class="breakdown-val" id="r-completeness"></div>
        </div>
      </div>
      <div class="results-buttons">
        <button class="btn-result btn-secondary" onclick="restart()">
          <i class="fa-solid fa-rotate-left"></i> Reintentar
        </button>
        <a href="levels.php?type=<?= $type ?>&cat=<?= $category ?>" class="btn-result btn-secondary">
          <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
        <button class="btn-result btn-primary" id="btn-next-level" style="display:none" onclick="nextLevel()">
          Siguiente nivel <i class="fa-solid fa-arrow-right"></i>
        </button>
      </div>
    </div>
  </div>

<script>
  const IS_ADMIN  = <?= json_encode($isAdmin) ?>;
  const USES_LEFT = <?= json_encode($usesLeft) ?>;  
  const INTERVIEW_DB_ID = <?= json_encode($interviewRow['id']) ?>;
  const HAS_NEXT_LEVEL = <?= json_encode($hasNextLevel) ?>;
  const SCENARIOS   = <?= json_encode($scenarios, JSON_UNESCAPED_UNICODE) ?>;
  const USERNAME    = <?= json_encode($currentUsername ?? 'candidato') ?>;
  const GAME_LEVEL  = <?= json_encode($level) ?>;
  const GAME_PARAMS = <?= json_encode([
    'type'      => $type,
    'category'  => $category,
    'interview' => $interview,
  ], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="../assets/js/game/game.js"></script>
