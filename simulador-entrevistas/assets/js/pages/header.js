/* ═══════════════════════════════════════════════
   header.js  –  Modal de planes + lógica nav
   Incluir en todas las páginas que tengan el header
   ═══════════════════════════════════════════════ */

/* ── Modal de planes ── */
function openPlans() {
  const overlay = document.getElementById('plans-overlay');
  if (overlay) overlay.classList.add('open');
}

function closePlans() {
  const overlay = document.getElementById('plans-overlay');
  if (overlay) overlay.classList.remove('open');
}

/* Cerrar con Escape */
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closePlans();
});

/* ── Toggle facturación mensual / anual ── */
let isAnnual = false;

function toggleBilling() {
  isAnnual = !isAnnual;

  const btn     = document.getElementById('billing-toggle');
  const lblMon  = document.getElementById('lbl-monthly');
  const lblAnn  = document.getElementById('lbl-annual');

  if (btn)    btn.classList.toggle('annual', isAnnual);
  if (lblMon) lblMon.classList.toggle('active', !isAnnual);
  if (lblAnn) lblAnn.classList.toggle('active',  isAnnual);

  /* Actualizar precios */
  ['pro', 'elite'].forEach((plan) => {
    const priceEl = document.getElementById(plan + '-price');
    const noteEl  = document.getElementById(plan + '-annual-note');
    if (!priceEl) return;
    priceEl.textContent    = '$' + (isAnnual ? priceEl.dataset.annual : priceEl.dataset.monthly);
    if (noteEl) noteEl.style.display = isAnnual ? 'block' : 'none';
  });
}