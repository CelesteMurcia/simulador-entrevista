/* ═══════════════════════════════════════════════
   profile.js  –  Lógica de la página de perfil
   ═══════════════════════════════════════════════ */

/* ── Subir avatar ── */
async function uploadAvatar(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];

  /* Preview inmediato */
  const reader = new FileReader();
  reader.onload = (e) => applyAvatarStyle(e.target.result);
  reader.readAsDataURL(file);

  /* Spinner */
  const icon = document.getElementById('avatar-upload-icon');
  if (icon) icon.className = 'fa-solid fa-spinner fa-spin';

  const formData = new FormData();
  formData.append('avatar', file);

  try {
    const res  = await fetch('../actions/upload_avatar.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error desconocido');
    applyAvatarStyle(data.url + '&t=' + Date.now());
    showToast('Foto actualizada', 'success');
  } catch (e) {
    showToast(e.message || 'Error al subir la imagen', 'error');
  } finally {
    if (icon) icon.className = 'fa-solid fa-camera';
    input.value = '';
  }
}

function applyAvatarStyle(src) {
  const el = document.getElementById('avatar-el');
  if (!el) return;
  el.style.backgroundImage    = `url(${src})`;
  el.style.backgroundSize     = 'cover';
  el.style.backgroundPosition = 'center';
  el.style.backgroundColor    = 'transparent';
  el.textContent = '';
}

/* ── Toast ── */
function showToast(msg, type = 'success') {
  let t = document.getElementById('profile-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'profile-toast';
    t.style.cssText = `
      position:fixed;bottom:28px;right:28px;z-index:300;
      background:var(--bg-card);border:1px solid var(--border-bright);
      border-radius:var(--radius);padding:12px 18px;font-size:13px;color:var(--text);
      display:flex;align-items:center;gap:10px;box-shadow:var(--shadow-lg);
      transform:translateY(20px);opacity:0;pointer-events:none;
      transition:all 0.25s cubic-bezier(0.34,1.3,0.64,1);
    `;
    t.innerHTML = '<i></i><span></span>';
    document.body.appendChild(t);
  }

  t.querySelector('i').className   = 'fa-solid ' + (type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark');
  t.querySelector('i').style.color = type === 'success' ? '#34d399' : '#f87171';
  t.querySelector('span').textContent = msg;
  t.style.transform = 'translateY(0)';
  t.style.opacity   = '1';

  clearTimeout(t._timer);
  t._timer = setTimeout(() => {
    t.style.transform = 'translateY(20px)';
    t.style.opacity   = '0';
  }, 2800);
}

/* ── Animar XP bar al cargar ── */
window.addEventListener('load', () => {
  const bar = document.querySelector('.xp-bar-fill');
  if (!bar) return;
  const target = bar.style.width;
  bar.style.width = '0%';
  requestAnimationFrame(() => {
    setTimeout(() => { bar.style.width = target; }, 100);
  });
});