// assets/js/pages/auth.js

// ── Toggle entre login y registro ────────────────────────
function switchTab(tab) {
  const isLogin    = tab === 'login';
  const indicator  = document.getElementById('tab-indicator');
  const tabLogin   = document.getElementById('tab-login');
  const tabReg     = document.getElementById('tab-register');
  const formLogin  = document.getElementById('form-login');
  const formReg    = document.getElementById('form-register');

  tabLogin.classList.toggle('active', isLogin);
  tabReg.classList.toggle('active', !isLogin);
  indicator.classList.toggle('right', !isLogin);

  formLogin.style.display  = isLogin  ? 'flex' : 'none';
  formReg.style.display    = !isLogin ? 'flex' : 'none';

  hideAlert();
  clearErrors();
}

// ── Mostrar/ocultar contraseña ────────────────────────────
function togglePass(inputId, btn) {
  const input = document.getElementById(inputId);
  const isPass = input.type === 'password';
  input.type = isPass ? 'text' : 'password';
  const icon = btn.querySelector('i');
  if (icon) {
    icon.className = isPass ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
  }
}

// ── Alert global ──────────────────────────────────────────
function showAlert(message, type = 'error') {
  const el = document.getElementById('alert');
  el.textContent  = message;
  el.className    = `alert ${type}`;
  el.style.display = 'block';
}

function hideAlert() {
  const el = document.getElementById('alert');
  el.style.display = 'none';
}

// ── Errores por campo ─────────────────────────────────────
function setError(id, message) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = message;
  // Marcar el input como inválido
  const input = el.previousElementSibling?.querySelector('input')
              || el.previousElementSibling;
  if (input?.tagName === 'INPUT') input.classList.add('invalid');
}

function clearError(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = '';
  const input = el.previousElementSibling?.querySelector('input')
              || el.previousElementSibling;
  if (input?.tagName === 'INPUT') input.classList.remove('invalid');
}

function clearErrors() {
  document.querySelectorAll('.field-error').forEach(el => {
    el.textContent = '';
  });
  document.querySelectorAll('input.invalid').forEach(el => {
    el.classList.remove('invalid');
  });
}

// ── Estado del botón ──────────────────────────────────────
function setLoading(btnId, loading) {
  const btn    = document.getElementById(btnId);
  const text   = btn.querySelector('.btn-text');
  const loader = btn.querySelector('.btn-loader');
  btn.disabled       = loading;
  text.style.display  = loading ? 'none'   : 'inline';
  loader.style.display = loading ? 'inline' : 'none';
}

// ── Validaciones ──────────────────────────────────────────
function validateEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validateLoginForm() {
  clearErrors();
  let valid = true;

  const email    = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;

  if (!email) {
    setError('err-login-email', 'El correo es obligatorio');
    valid = false;
  } else if (!validateEmail(email)) {
    setError('err-login-email', 'Ingresa un correo válido');
    valid = false;
  }

  if (!password) {
    setError('err-login-password', 'La contraseña es obligatoria');
    valid = false;
  }

  return valid;
}

function validateRegisterForm() {
  clearErrors();
  let valid = true;

  const username  = document.getElementById('reg-username').value.trim();
  const email     = document.getElementById('reg-email').value.trim();
  const password  = document.getElementById('reg-password').value;
  const password2 = document.getElementById('reg-password2').value;

  if (!username) {
    setError('err-reg-username', 'El nombre de usuario es obligatorio');
    valid = false;
  } else if (username.length < 3) {
    setError('err-reg-username', 'Mínimo 3 caracteres');
    valid = false;
  } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
    setError('err-reg-username', 'Solo letras, números y guión bajo');
    valid = false;
  }

  if (!email) {
    setError('err-reg-email', 'El correo es obligatorio');
    valid = false;
  } else if (!validateEmail(email)) {
    setError('err-reg-email', 'Ingresa un correo válido');
    valid = false;
  }

  if (!password) {
    setError('err-reg-password', 'La contraseña es obligatoria');
    valid = false;
  } else if (password.length < 8) {
    setError('err-reg-password', 'Mínimo 8 caracteres');
    valid = false;
  }

  if (!password2) {
    setError('err-reg-password2', 'Confirma tu contraseña');
    valid = false;
  } else if (password !== password2) {
    setError('err-reg-password2', 'Las contraseñas no coinciden');
    valid = false;
  }

  return valid;
}

// ── Submit login ──────────────────────────────────────────
document.getElementById('form-login').addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert();

  if (!validateLoginForm()) return;

  setLoading('btn-login', true);

  try {
    const res = await fetch('actions/login.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email:    document.getElementById('login-email').value.trim(),
        password: document.getElementById('login-password').value
      })
    });

    const data = await res.json();

    if (data.success) {
      // Redirigir a home
      window.location.href = 'pages/home.php';
    } else {
      showAlert(data.error || 'Correo o contraseña incorrectos');
    }
  } catch (err) {
    showAlert('Error de conexión. Intenta de nuevo.');
  } finally {
    setLoading('btn-login', false);
  }
});

// ── Submit registro ───────────────────────────────────────
document.getElementById('form-register').addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert();

  if (!validateRegisterForm()) return;

  setLoading('btn-register', true);

  try {
    const res = await fetch('actions/register.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username:  document.getElementById('reg-username').value.trim(),
        email:     document.getElementById('reg-email').value.trim(),
        password:  document.getElementById('reg-password').value
      })
    });

    const data = await res.json();

    if (data.success) {
      // Cuenta creada — redirigir directo
      window.location.href = 'pages/home.php';
    } else {
      showAlert(data.error || 'No se pudo crear la cuenta. Intenta de nuevo.');
    }
  } catch (err) {
    showAlert('Error de conexión. Intenta de nuevo.');
  } finally {
    setLoading('btn-register', false);
  }
});