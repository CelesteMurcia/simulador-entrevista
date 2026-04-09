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

// ── Modo: crear o editar ──────────────────────────────────
$editId    = isset($_GET['id']) ? (int) $_GET['id'] : null;
$interview = null;
$scenarios = [];

if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM interviews WHERE id = ?');
    $stmt->execute([$editId]);
    $interview = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$interview) { header('Location: admin.php'); exit; }

    $stmt = $pdo->prepare('SELECT * FROM scenarios WHERE interview_id = ? ORDER BY position ASC');
    $stmt->execute([$editId]);
    $scenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = $editId ? 'Editar entrevista' : 'Nueva entrevista';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> — Admin</title>
  <!-- Fuentes, FA, global.css y admin-header.css los carga admin_header.php -->
  <link rel="stylesheet" href="../assets/css/admin/admin_edit.css">
</head>
<body>
<div class="admin-wrap">

  <?php $adminActivePage = 'edit'; require_once 'admin_header.php'; ?>

  <div class="edit-body">

    <!-- Datos de la entrevista -->
    <div class="form-section">
      <div class="form-section-header">
        <i class="fa-solid fa-circle-info" style="color:var(--brand-light)"></i>
        <span class="form-section-title">Datos de la entrevista</span>
      </div>
      <div class="form-section-body">

        <div class="field-row triple">
          <div class="field">
            <label>Categoría</label>
            <select id="f-category">
              <?php
              $cats = ['programmer','cybersecurity','databases','networks','ai'];
              foreach ($cats as $c) {
                $sel = ($interview && $interview['category'] === $c) ? 'selected' : '';
                echo "<option value=\"$c\" $sel>" . ucfirst($c) . "</option>";
              }
              ?>
            </select>
          </div>
          <div class="field">
            <label>Slug</label>
            <input type="text" id="f-slug" placeholder="ej: web, sql-basico"
              value="<?= htmlspecialchars($interview['slug'] ?? '') ?>">
            <span class="field-hint">Identificador único, sin espacios ni mayúsculas</span>
          </div>
          <div class="field">
            <label>Nivel</label>
            <select id="f-level">
              <?php foreach (['easy','medium','hard'] as $l) {
                $sel = ($interview && $interview['level'] === $l) ? 'selected' : '';
                $labels = ['easy'=>'Fácil','medium'=>'Medio','hard'=>'Difícil'];
                echo "<option value=\"$l\" $sel>{$labels[$l]}</option>";
              } ?>
            </select>
          </div>
        </div>

        <div class="field-row">
          <div class="field full">
            <label>Título</label>
            <input type="text" id="f-title" placeholder="ej: Fundamentos Web"
              value="<?= htmlspecialchars($interview['title'] ?? '') ?>">
          </div>
        </div>

        <div class="field-row">
          <div class="field full">
            <label>Estilo del entrevistador</label>
            <textarea id="f-style" rows="2" placeholder="ej: profesional pero directo, con comentarios irónicos cuando la respuesta es mala"><?= htmlspecialchars($interview['interviewer_style'] ?? '') ?></textarea>
            <span class="field-hint">Se usa en el prompt de Gemini para darle personalidad al entrevistador</span>
          </div>
        </div>

      </div>
    </div>

    <!-- Preguntas -->
    <div class="form-section">
      <div class="form-section-header">
        <i class="fa-solid fa-list-ul" style="color:var(--brand-light)"></i>
        <span class="form-section-title">Preguntas</span>
        <span class="question-count-label" id="question-count">
          <?= count($scenarios) ?> pregunta<?= count($scenarios) !== 1 ? 's' : '' ?>
        </span>
      </div>

      <div class="questions-list" id="questions-list">
        <?php foreach ($scenarios as $i => $sc): ?>
          <div class="question-card" data-id="<?= $sc['id'] ?>" draggable="true">
            <div class="qcard-header" onclick="toggleCard(this)">
              <i class="fa-solid fa-grip-vertical drag-handle" onclick="event.stopPropagation()"></i>
              <span class="qcard-number"><?= $i + 1 ?></span>
              <span class="phase-tag phase-<?= $sc['phase'] ?>"><?= $sc['phase'] === 'intro' ? 'Intro' : 'Main' ?></span>
              <span class="qcard-preview <?= empty($sc['question']) ? 'empty' : '' ?>">
                <?= $sc['question'] ? htmlspecialchars(mb_substr($sc['question'], 0, 80)) . (mb_strlen($sc['question']) > 80 ? '…' : '') : 'Sin pregunta' ?>
              </span>
              <i class="fa-solid fa-chevron-down qcard-toggle"></i>
              <button class="qcard-del" onclick="event.stopPropagation(); deleteCard(this)" title="Eliminar">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
            <div class="qcard-body">
              <div class="qcard-body-inner">
                <div class="qfield">
                  <label>Fase</label>
                  <select class="q-phase" onchange="updatePhaseTag(this)">
                    <option value="intro" <?= $sc['phase']==='intro'?'selected':'' ?>>Intro — no cuenta para el score</option>
                    <option value="main"  <?= $sc['phase']==='main' ?'selected':'' ?>>Main — cuenta para el score</option>
                  </select>
                </div>
                <div class="qfield">
                  <label>Pregunta</label>
                  <textarea class="q-question" rows="2" placeholder="¿Qué es HTML y para qué se usa?" oninput="updatePreview(this)"><?= htmlspecialchars($sc['question']) ?></textarea>
                </div>
                <div class="qfield">
                  <label>Hint de evaluación <span class="hint-label-sub">(para Gemini)</span></label>
                  <textarea class="q-hint" rows="3" placeholder="Evaluar si explica X. No penalizar si no menciona Y..."><?= htmlspecialchars($sc['evaluation_hint']) ?></textarea>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <button class="btn-add-question" onclick="addQuestion()">
        <i class="fa-solid fa-plus"></i> Agregar pregunta
      </button>
    </div>

  </div><!-- /edit-body -->
</div><!-- /admin-wrap -->

<!-- ── Footer fijo con Cancelar y Guardar ── -->
<div class="form-footer">
  <span class="footer-left" id="footer-status">
    <?= $editId ? 'Editando: ' . htmlspecialchars($interview['title']) : 'Nueva entrevista' ?>
  </span>
  <div class="footer-right">
    <a href="admin.php" class="btn-cancel">
      <i class="fa-solid fa-xmark"></i> Cancelar
    </a>
    <button class="btn-save" id="btn-save" onclick="saveAll()">
      <i class="fa-solid fa-floppy-disk"></i>
      <?= $editId ? 'Guardar cambios' : 'Crear entrevista' ?>
    </button>
  </div>
</div>

<div class="toast" id="toast">
  <i class="fa-solid fa-circle-check"></i>
  <span id="toast-msg"></span>
</div>

<script>
const EDIT_ID = <?= json_encode($editId) ?>;
let dragSrc   = null;

function updatePreview(textarea) {
  const card    = textarea.closest('.question-card');
  const preview = card.querySelector('.qcard-preview');
  const val     = textarea.value.trim();
  preview.textContent = val ? (val.length > 80 ? val.substring(0, 80) + '…' : val) : 'Sin pregunta';
  preview.classList.toggle('empty', !val);
}

function toggleCard(header) {
  header.closest('.question-card').classList.toggle('open');
}

function updatePhaseTag(select) {
  const card = select.closest('.question-card');
  const tag  = card.querySelector('.phase-tag');
  tag.className   = 'phase-tag phase-' + select.value;
  tag.textContent = select.value === 'intro' ? 'Intro' : 'Main';
}

function renumber() {
  document.querySelectorAll('.question-card').forEach((card, i) => {
    card.querySelector('.qcard-number').textContent = i + 1;
  });
  updateCount();
}

function updateCount() {
  const n = document.querySelectorAll('.question-card').length;
  document.getElementById('question-count').textContent = n + ' pregunta' + (n !== 1 ? 's' : '');
}

function addQuestion() {
  const list = document.getElementById('questions-list');
  const n    = list.querySelectorAll('.question-card').length + 1;

  const card = document.createElement('div');
  card.className  = 'question-card open';
  card.draggable  = true;
  card.dataset.id = 'new';
  card.innerHTML  = `
    <div class="qcard-header" onclick="toggleCard(this)">
      <i class="fa-solid fa-grip-vertical drag-handle" onclick="event.stopPropagation()"></i>
      <span class="qcard-number">${n}</span>
      <span class="phase-tag phase-main">Main</span>
      <span class="qcard-preview empty">Sin pregunta</span>
      <i class="fa-solid fa-chevron-down qcard-toggle"></i>
      <button class="qcard-del" onclick="event.stopPropagation(); deleteCard(this)" title="Eliminar">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="qcard-body" style="display:flex">
      <div class="qcard-body-inner">
        <div class="qfield">
          <label>Fase</label>
          <select class="q-phase" onchange="updatePhaseTag(this)">
            <option value="intro">Intro — no cuenta para el score</option>
            <option value="main" selected>Main — cuenta para el score</option>
          </select>
        </div>
        <div class="qfield">
          <label>Pregunta</label>
          <textarea class="q-question" rows="2" placeholder="¿Qué pregunta harías?" oninput="updatePreview(this)"></textarea>
        </div>
        <div class="qfield">
          <label>Hint de evaluación <span class="hint-label-sub">(para Gemini)</span></label>
          <textarea class="q-hint" rows="3" placeholder="Evaluar si explica X. No penalizar si no menciona Y..."></textarea>
        </div>
      </div>
    </div>`;

  attachDragEvents(card);
  list.appendChild(card);
  renumber();
  card.querySelector('.q-question').focus();
}

function deleteCard(btn) {
  const card = btn.closest('.question-card');
  if (document.querySelectorAll('.question-card').length <= 1) {
    showToast('Debe haber al menos una pregunta', 'error');
    return;
  }
  card.style.transition = 'opacity 0.2s, transform 0.2s';
  card.style.opacity    = '0';
  card.style.transform  = 'translateX(10px)';
  setTimeout(() => { card.remove(); renumber(); }, 200);
}

function attachDragEvents(card) {
  card.addEventListener('dragstart', e => {
    dragSrc = card;
    card.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  card.addEventListener('dragend', () => {
    card.classList.remove('dragging');
    document.querySelectorAll('.question-card').forEach(c => c.classList.remove('drag-over'));
    renumber();
  });
  card.addEventListener('dragover', e => {
    e.preventDefault();
    if (card !== dragSrc) card.classList.add('drag-over');
  });
  card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
  card.addEventListener('drop', e => {
    e.preventDefault();
    card.classList.remove('drag-over');
    if (dragSrc && dragSrc !== card) {
      const list  = document.getElementById('questions-list');
      const cards = [...list.querySelectorAll('.question-card')];
      const srcI  = cards.indexOf(dragSrc);
      const dstI  = cards.indexOf(card);
      if (srcI < dstI) card.after(dragSrc);
      else             card.before(dragSrc);
    }
  });
}

document.querySelectorAll('.question-card').forEach(attachDragEvents);

async function saveAll() {
  const btn = document.getElementById('btn-save');

  const interview = {
    category:          document.getElementById('f-category').value.trim(),
    slug:              document.getElementById('f-slug').value.trim().toLowerCase().replace(/\s+/g, '-'),
    level:             document.getElementById('f-level').value,
    title:             document.getElementById('f-title').value.trim(),
    interviewer_style: document.getElementById('f-style').value.trim(),
  };

  if (!interview.slug || !interview.title) {
    showToast('Completa el slug y el título', 'error');
    return;
  }

  const questions = [];
  let valid = true;
  document.querySelectorAll('.question-card').forEach((card, i) => {
    const q = card.querySelector('.q-question').value.trim();
    const h = card.querySelector('.q-hint').value.trim();
    const p = card.querySelector('.q-phase').value;
    if (!q) { valid = false; }
    questions.push({ position: i + 1, phase: p, question: q, evaluation_hint: h });
  });

  if (!valid) { showToast('Todas las preguntas deben tener texto', 'error'); return; }
  if (questions.length === 0) { showToast('Agrega al menos una pregunta', 'error'); return; }

  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';

  try {
    const res  = await fetch('../actions/admin_save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: EDIT_ID, interview, questions })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);

    showToast(EDIT_ID ? 'Cambios guardados' : 'Entrevista creada', 'success');

    if (!EDIT_ID && data.id) {
      setTimeout(() => { window.location.href = 'admin_edit.php?id=' + data.id; }, 900);
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar cambios';
    }
  } catch (e) {
    showToast(e.message || 'Error al guardar', 'error');
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ' + (EDIT_ID ? 'Guardar cambios' : 'Crear entrevista');
  }
}

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