/* ═══════════════════════════════════════════════════════════
   game.js — Modo IA · Flujo: diálogo → respuesta → feedback
   ═══════════════════════════════════════════════════════════ */

const TOTAL = SCENARIOS.length;

let state        = { technical_accuracy: 0, clarity: 0, completeness: 0 };
let currentIndex = 0;
let lastFeedback = null;
let typewriterTimer = null;
let currentUsesLeft = typeof USES_LEFT !== 'undefined' ? USES_LEFT : null;

// ── Puntuación ───────────────────────────────────────────────
function getMainScenarios() {
  return SCENARIOS.filter(s => s.phase === 'main');
}

function calcMaxScore() {
  return getMainScenarios().length * 3;
}

function calcGlobal() {
  const raw = state.technical_accuracy * 0.45
            + state.clarity             * 0.35
            + state.completeness        * 0.20;
  const max = calcMaxScore();
  if (max === 0) return 0;
  return Math.max(0, Math.round((raw / max) * 100));
}

function updateScoreBar() {
  const g    = calcGlobal();
  const fill = document.getElementById('score-fill');
  const val  = document.getElementById('val-global');
  if (fill) fill.style.height = g + '%';
  if (val)  val.textContent   = g;
}

// ── Dots de progreso ─────────────────────────────────────────
function buildDots() {
  const container = document.getElementById('dots');
  if (!container) return;
  container.innerHTML = '';
  SCENARIOS.forEach((_, i) => {
    const d = document.createElement('div');
    d.className = 'dot'
      + (i < currentIndex   ? ' done'    : '')
      + (i === currentIndex ? ' current' : '');
    container.appendChild(d);
  });
}

// ── Typewriter ───────────────────────────────────────────────
function typewrite(el, text, speed = 22, onDone = null) {
  if (typewriterTimer) clearInterval(typewriterTimer);
  el.textContent = '';
  el.classList.add('typing');
  let i = 0;
  typewriterTimer = setInterval(() => {
    el.textContent += text[i];
    i++;
    if (i >= text.length) {
      clearInterval(typewriterTimer);
      typewriterTimer = null;
      el.classList.remove('typing');
      if (onDone) onDone();
    }
  }, speed);
}

function skipTypewriter(el, text, onDone) {
  if (typewriterTimer) {
    clearInterval(typewriterTimer);
    typewriterTimer = null;
    el.classList.remove('typing');
    el.textContent = text;
    if (onDone) onDone();
  }
}

// ── ESTADO 1: Mostrar diálogo de bienvenida ──────────────────
function render() {
  buildDots();
  updateScoreBar();

  if (currentIndex === 0) {
    showDialogue('Bienvenido. Comenzamos con la entrevista. Responde con tus propias palabras.');
  } else {
    goToAnswer();
  }
}

function showDialogue(text) {
  hideAll();
  const box = document.getElementById('box-dialogue');
  const el  = document.getElementById('dialogue-text');
  const btn = document.getElementById('btn-next-dialogue');
  box.style.display = '';
  btn.classList.remove('visible');

  typewrite(el, text, 22, () => btn.classList.add('visible'));
  el.onclick = () => skipTypewriter(el, text, () => btn.classList.add('visible'));
}

// ── ESTADO 2: Mostrar pregunta + textarea ────────────────────
function goToAnswer() {
  const s = SCENARIOS[currentIndex];
  hideAll();

  const boxAnswer = document.getElementById('box-answer');
  const strip     = document.getElementById('question-strip');
  const textarea  = document.getElementById('answer-input');
  const charCount = document.getElementById('char-count');

  boxAnswer.style.display = '';
  strip.textContent       = s.question;
  textarea.value          = '';
  if (charCount) charCount.textContent = '0 / 600';
  textarea.disabled       = false;
  document.getElementById('confirm-btn').disabled = false;
  document.getElementById('ai-thinking').style.display = 'none';

  updateUsesDisplay(currentUsesLeft);

  const feedbackEl = document.getElementById('ai-feedback-inline');
  if (feedbackEl) feedbackEl.remove();

  setTimeout(() => textarea.focus(), 60);

  // ── Cambiar manos al escribir ──
  const handsImg = document.getElementById('hands-img');
  let typingTimer = null;

  textarea.addEventListener('input', () => {
    if (handsImg) handsImg.src = '../assets/img/hands/explicando.png';
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
      if (handsImg) handsImg.src = '../assets/img/hands/entrelazadas.png';
    }, 1000);
  });
}

// ── ESTADO 3: Mostrar feedback ───────────────────────────────
function showFeedbackState(result) {
  hideAll();
  const box  = document.getElementById('box-feedback');
  const el   = document.getElementById('feedback-text');
  const btn  = document.getElementById('btn-next-feedback');
  const meta = document.getElementById('feedback-metrics');

  box.style.display = '';
  btn.classList.remove('visible');

  const isMain = SCENARIOS[currentIndex].phase === 'main';

  const speakerMap = {
    good:    '😄 Entrevistador',
    neutral: '😐 Entrevistador',
    bad:     '😒 Entrevistador',
  };

  document.getElementById('feedback-speaker').textContent =
    speakerMap[result.quality] ?? 'Entrevistador';

  meta.innerHTML = isMain ? buildMetricsHTML(result) : '';

  const text = result.feedback ?? '';
  typewrite(el, text, 28, () => btn.classList.add('visible'));
  el.onclick = () => skipTypewriter(el, text, () => btn.classList.add('visible'));
}

function buildMetricsHTML(result) {
  const metrics = [
    { label: 'Técnica',     val: result.technical_accuracy },
    { label: 'Claridad',    val: result.clarity },
    { label: 'Completitud', val: result.completeness },
  ];
  return metrics.map(m => {
    const cls  = m.val >= 3 ? 'good' : m.val >= 2 ? 'neutral' : 'bad';
    const icon = m.val >= 3 ? '✓'   : m.val >= 2 ? '→'       : '✗';
    return `<div class="metric-chip ${cls}">
      <span class="metric-icon">${icon}</span>
      ${m.label}: <strong>${m.val}/3</strong>
    </div>`;
  }).join('');
}

// ── Usos restantes ───────────────────────────────────────────
function updateUsesDisplay(remaining) {
  const el = document.getElementById('uses-left');
  if (!el) return;

  // Si viene un valor nuevo de la API, guardarlo
  if (remaining !== null && remaining !== undefined) {
    currentUsesLeft = remaining;
  }

  const val = currentUsesLeft;

  if (val === null || val === undefined) {
    el.textContent = '';
    el.style.display = 'none';
    return;
  }

  el.style.display = 'inline';

  if (val === -1 || (typeof IS_ADMIN !== 'undefined' && IS_ADMIN)) {
    el.textContent = '∞ evaluaciones (admin)';
    el.style.color = 'var(--c-accent, #7c3aed)';
    return;
  }

  el.textContent = `${val} evaluación${val !== 1 ? 'es' : ''} restante${val !== 1 ? 's' : ''} hoy`;
  el.style.color = val <= 3 ? '#f87171' : 'var(--text-muted)';
}

// ── Expresión del entrevistador ──────────────────────────────
function setInterviewerMood(quality) {
  const map = { good: 'iv-happy', neutral: 'iv-neutral', bad: 'iv-bad', scared: 'iv-scared', angry: 'iv-angry', thinking: 'iv-thinking' };
  ['iv-neutral', 'iv-happy', 'iv-bad', 'iv-scared', 'iv-angry', 'iv-thinking' ].forEach(id => {
    document.getElementById(id)?.classList.remove('active');
  });
  document.getElementById(map[quality] ?? 'iv-neutral')?.classList.add('active');

  setTimeout(() => {
    ['iv-neutral', 'iv-happy', 'iv-bad', 'iv-scared', 'iv-angry', 'iv-thinking'].forEach(id =>
      document.getElementById(id)?.classList.remove('active')
    );
    document.getElementById('iv-neutral')?.classList.add('active');
  }, 3000);
}

// ── Siguiente escenario ──────────────────────────────────────
function nextScenario() {
  currentIndex++;
  if (currentIndex < TOTAL) {
    goToAnswer();
  } else {
    showResults();
  }
}

// ── Ocultar todos los estados ────────────────────────────────
function hideAll() {
  ['box-dialogue', 'box-answer', 'box-feedback'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
}

// ── Enviar respuesta ─────────────────────────────────────────
function handleEnter(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    submitAnswer();
  }
}

function updateCharCount() {
  const ta = document.getElementById('answer-input');
  const el = document.getElementById('char-count');
  if (ta && el) el.textContent = ta.value.length + ' / 600';
}

async function submitAnswer() {
  const answer = document.getElementById('answer-input').value.trim();
  if (answer.length < 5) return;

  setLoading(true);

  try {
    const result = await callGemini(SCENARIOS[currentIndex].question, answer);
    applyResult(result);
  } catch (err) {
    setLoading(false);
    showInlineError(err.message);
  }
}

function showInlineError(msg) {
  const box = document.getElementById('box-answer');
  if (!box) return;
  let errEl = document.getElementById('ai-feedback-inline');
  if (!errEl) {
    errEl = document.createElement('div');
    errEl.id = 'ai-feedback-inline';
    errEl.style.cssText = 'margin-top:8px;padding:10px 14px;border-radius:10px;font-size:13px;'
      + 'background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);color:#f87171';
    box.querySelector('.answer-box').appendChild(errEl);
  }
  errEl.textContent = '⚠ ' + msg;
}

// ── Llamada al proxy PHP ─────────────────────────────────────
async function callGemini(question, answer) {
  const scenario = SCENARIOS[currentIndex];

  const res = await fetch('../api/gemini_proxy.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      question,
      answer,
      evaluation_hint: scenario.evaluation_hint ?? '',
      username:        USERNAME,
    })
  });

  if (res.status === 429) {
    const data = await res.json().catch(() => ({}));
    throw new Error(data.error || 'Límite diario alcanzado. Vuelve mañana.');
  }
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.error || 'Error ' + res.status);
  }
  return await res.json();
}

// ── Aplicar resultado ────────────────────────────────────────
function applyResult(result) {
  if (SCENARIOS[currentIndex].phase === 'main') {
    state.technical_accuracy += result.technical_accuracy;
    state.clarity            += result.clarity;
    state.completeness       += result.completeness;
  }
  lastFeedback = result;
 
  if (typeof result.usos_restantes !== 'undefined') {
    updateUsesDisplay(result.usos_restantes);
  }
 
  setInterviewerMood(result.quality);
  updateScoreBar();
  setLoading(false);
  showFeedbackState(result);
}

// ── Estado de carga ──────────────────────────────────────────
function setLoading(loading) {
  const btn      = document.getElementById('confirm-btn');
  const thinking = document.getElementById('ai-thinking');
  const input    = document.getElementById('answer-input');
  if (btn)      btn.disabled           = loading;
  if (input)    input.disabled         = loading;
  if (thinking) thinking.style.display = loading ? 'flex' : 'none';
}

// ── Guardar progreso ─────────────────────────────────────────
async function saveProgress() {
  try {
    await fetch('../actions/save_level.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type:      GAME_PARAMS.type,
        category:  GAME_PARAMS.category,
        interview: GAME_PARAMS.interview,
        level:     CURRENT_LEVEL,
        score_technical_accuracy: state.technical_accuracy,
        score_clarity:            state.clarity,
        score_completeness:       state.completeness,
        score_global:             calcGlobal(),
      })
    });
  } catch (e) {
    console.warn('No se pudo guardar el progreso:', e);
  }
}

// ── Pantalla de resultados ───────────────────────────────────
function showResults() {
  const dialogueZone  = document.getElementById('dialogue-zone');
  const scorePanel    = document.getElementById('score-panel');
  const resultsScreen = document.getElementById('results-screen');
  if (dialogueZone)  dialogueZone.style.display  = 'none';
  if (scorePanel)    scorePanel.style.display    = 'none';
  if (resultsScreen) resultsScreen.style.display = 'flex';

  const global = calcGlobal();
  const passed = global >= 70;

  const icon     = document.getElementById('results-icon');
  const title    = document.getElementById('results-title');
  const subtitle = document.getElementById('results-subtitle');
  const globalEl = document.getElementById('results-global');
  if (icon)     icon.textContent     = passed ? '🎉' : '💪';
  if (title)    title.textContent    = passed ? '¡Entrevista superada!' : 'Sigue practicando';
  if (subtitle) subtitle.textContent = passed
    ? 'Desbloqueaste el siguiente nivel.'
    : 'No alcanzaste los 70 puntos mínimos. ¡Inténtalo de nuevo!';
  if (globalEl) globalEl.textContent = global + ' / 100';

  const maxRaw = calcMaxScore();
  [
    { id: 'r-technical-accuracy', bar: 'bar-ta', val: state.technical_accuracy },
    { id: 'r-clarity',            bar: 'bar-cl', val: state.clarity },
    { id: 'r-completeness',       bar: 'bar-co', val: state.completeness },
  ].forEach(m => {
    const valEl = document.getElementById(m.id);
    if (valEl) valEl.textContent = m.val;
    const pct = Math.max(0, Math.round((m.val / maxRaw) * 100));
    setTimeout(() => {
      const bar = document.getElementById(m.bar);
      if (bar) bar.style.width = pct + '%';
    }, 100);
  });

  saveProgress();

  if (passed && typeof HAS_NEXT_LEVEL !== 'undefined' && HAS_NEXT_LEVEL) {
    const btnNext = document.getElementById('btn-next-level');
    if (btnNext) btnNext.style.display = 'flex';
  }
}

// ── Menú lateral ─────────────────────────────────────────────
function toggleMenu() {
  document.getElementById('side-menu').classList.toggle('open');
  document.getElementById('side-overlay').classList.toggle('open');
}

// ── Reintentar ───────────────────────────────────────────────
function restart() {
  state        = { technical_accuracy: 0, clarity: 0, completeness: 0 };
  currentIndex = 0;
  lastFeedback = null;

  const resultsScreen = document.getElementById('results-screen');
  const dialogueZone  = document.getElementById('dialogue-zone');
  const scorePanel    = document.getElementById('score-panel');
  if (resultsScreen) resultsScreen.style.display = 'none';
  if (dialogueZone)  dialogueZone.style.display  = '';
  if (scorePanel)    scorePanel.style.display    = '';

  const feedbackText = document.getElementById('feedback-text');
  const feedbackMeta = document.getElementById('feedback-metrics');
  if (feedbackText) feedbackText.textContent = '';
  if (feedbackMeta) feedbackMeta.innerHTML   = '';

  const btnNext   = document.getElementById('btn-next-level');
  const scoreFill = document.getElementById('score-fill');
  const valGlobal = document.getElementById('val-global');
  if (btnNext)   btnNext.style.display  = 'none';
  if (scoreFill) scoreFill.style.height = '0%';
  if (valGlobal) valGlobal.textContent  = '0';

  buildDots();
  render();
}

// ── Siguiente nivel ──────────────────────────────────────────
const LEVEL_ORDER    = ['easy', 'medium', 'hard'];
const CURRENT_LEVEL  = typeof GAME_LEVEL  !== 'undefined' ? GAME_LEVEL  : 'easy';
const CURRENT_PARAMS = typeof GAME_PARAMS !== 'undefined' ? GAME_PARAMS : {};

function nextLevel() {
  const idx  = LEVEL_ORDER.indexOf(CURRENT_LEVEL);
  const next = LEVEL_ORDER[idx + 1];
  if (!next) return;
  const p = new URLSearchParams({ ...CURRENT_PARAMS, level: next });
  window.location.href = 'play.php?' + p.toString();
}

// ── Enter para avanzar diálogos ──────────────────────────────
function isVisible(id) {
  const el = document.getElementById(id);
  return el && el.style.display !== 'none';
}

document.addEventListener('keydown', e => {
  if (e.key !== 'Enter') return;
  if (document.activeElement === document.getElementById('answer-input')) return;

  if (isVisible('box-dialogue')) {
    document.getElementById('btn-next-dialogue').click();
  } else if (isVisible('box-feedback')) {
    document.getElementById('btn-next-feedback').click();
  }
});

// ── Arrancar ─────────────────────────────────────────────────
render();
