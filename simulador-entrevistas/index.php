<?php
$dataPath   = __DIR__ . '/data';
$types      = 0;
$interviews = 0;
$levels     = 0;

if (is_dir($dataPath)) {
    foreach (glob($dataPath . '/*', GLOB_ONLYDIR) as $typeDir) {
        $types++;
        foreach (glob($typeDir . '/*', GLOB_ONLYDIR) as $categoryDir) {
            foreach (glob($categoryDir . '/*', GLOB_ONLYDIR) as $interviewDir) {
                $interviews++;
                $levels += count(glob($interviewDir . '/*.json'));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/pages/auth.css">
</head>
<body>

  <div class="page">

    <!-- Panel izquierdo — branding -->
    <div class="brand-panel">
      <div class="brand-inner">
        <div class="logo">
          <i class="fa-solid fa-bolt logo-icon-fa"></i>
          <span class="logo-text">KangeeQuest</span>
        </div>

        <div class="brand-copy">
          <h1>Practica.<br>Mejora.<br>Consigue el trabajo.</h1>
          <p>Simulaciones de entrevistas técnicas con niveles, puntuación y un entrevistador que no tiene paciencia para respuestas vagas.</p>
        </div>

        <div class="stats-row">
          <div class="stat">
            <span class="stat-value"><?= $types ?></span>
            <span class="stat-label">Tipos</span>
          </div>
          <div class="stat">
            <span class="stat-value"><?= $interviews ?></span>
            <span class="stat-label">Entrevistas</span>
          </div>
          <div class="stat">
            <span class="stat-value"><?= $levels ?></span>
            <span class="stat-label">Niveles</span>
          </div>
        </div>

        <div class="brand-decoration">
          <div class="deco-card deco-1">
            <i class="fa-solid fa-code deco-icon-fa"></i>
            <div>
              <div class="deco-title">Programador</div>
              <div class="deco-sub">3 niveles · 21 escenarios</div>
            </div>
            <span class="deco-badge">Disponible</span>
          </div>
          <div class="deco-card deco-2">
            <i class="fa-solid fa-shield-halved deco-icon-fa"></i>
            <div>
              <div class="deco-title">Ciberseguridad</div>
              <div class="deco-sub">3 niveles · 21 escenarios</div>
            </div>
            <span class="deco-badge">Disponible</span>
          </div>
          <div class="deco-card deco-3">
            <i class="fa-solid fa-network-wired deco-icon-fa"></i>
            <div>
              <div class="deco-title">Redes</div>
              <div class="deco-sub">Próximamente</div>
            </div>
            <span class="deco-badge locked"><i class="fa-solid fa-lock"></i></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Panel derecho — formulario -->
    <div class="form-panel">
      <div class="form-inner">

        <!-- Tabs -->
        <div class="tabs">
          <button class="tab active" id="tab-login" onclick="switchTab('login')">
            Iniciar sesión
          </button>
          <button class="tab" id="tab-register" onclick="switchTab('register')">
            Crear cuenta
          </button>
          <div class="tab-indicator" id="tab-indicator"></div>
        </div>

        <!-- Mensaje de error/éxito -->
        <div class="alert" id="alert" style="display:none"></div>

        <!-- Formulario Login -->
        <form id="form-login" class="auth-form" novalidate>
          <div class="form-group">
            <label for="login-email">Correo electrónico</label>
            <div class="input-with-icon">
              <i class="fa-solid fa-envelope input-icon"></i>
              <input type="email" id="login-email" name="email"
                placeholder="juanete@gmail.com" autocomplete="email">
            </div>
            <span class="field-error" id="err-login-email"></span>
          </div>

          <div class="form-group">
            <label for="login-password">Contraseña</label>
            <div class="input-with-icon input-with-toggle">
              <i class="fa-solid fa-lock input-icon"></i>
              <input type="password" id="login-password" name="password"
                placeholder="Tu contraseña" autocomplete="current-password">
              <button type="button" class="toggle-pass" onclick="togglePass('login-password', this)" title="Ver contraseña">
                <i class="fa-solid fa-eye"></i>
              </button>
            </div>
            <span class="field-error" id="err-login-password"></span>
          </div>

          <button type="submit" class="btn-submit" id="btn-login">
            <span class="btn-text">Entrar →</span>
            <span class="btn-loader" style="display:none">Cargando...</span>
          </button>
        </form>

        <!-- Formulario Registro -->
        <form id="form-register" class="auth-form" style="display:none" novalidate>
          <div class="form-group">
            <label for="reg-username">Nombre de usuario</label>
            <div class="input-with-icon">
              <i class="fa-solid fa-user input-icon"></i>
              <input type="text" id="reg-username" name="username"
                placeholder="juaneteinsano67" autocomplete="username"
                maxlength="50">
            </div>
            <span class="field-error" id="err-reg-username"></span>
          </div>

          <div class="form-group">
            <label for="reg-email">Correo electrónico</label>
            <div class="input-with-icon">
              <i class="fa-solid fa-envelope input-icon"></i>
              <input type="email" id="reg-email" name="email"
                placeholder="juanetitosicsseven@gmail.com" autocomplete="email">
            </div>
            <span class="field-error" id="err-reg-email"></span>
          </div>

          <div class="form-group">
            <label for="reg-password">Contraseña</label>
            <div class="input-with-icon input-with-toggle">
              <i class="fa-solid fa-lock input-icon"></i>
              <input type="password" id="reg-password" name="password"
                placeholder="Mínimo 8 caracteres" autocomplete="new-password"
                maxlength="72">
              <button type="button" class="toggle-pass" onclick="togglePass('reg-password', this)" title="Ver contraseña">
                <i class="fa-solid fa-eye"></i>
              </button>
            </div>
            <span class="field-error" id="err-reg-password"></span>
          </div>

          <div class="form-group">
            <label for="reg-password2">Confirmar contraseña</label>
            <div class="input-with-icon input-with-toggle">
              <i class="fa-solid fa-lock input-icon"></i>
              <input type="password" id="reg-password2" name="password2"
                placeholder="Repite tu contraseña" autocomplete="new-password">
              <button type="button" class="toggle-pass" onclick="togglePass('reg-password2', this)" title="Ver contraseña">
                <i class="fa-solid fa-eye"></i>
              </button>
            </div>
            <span class="field-error" id="err-reg-password2"></span>
          </div>

          <button type="submit" class="btn-submit" id="btn-register">
            <span class="btn-text">Crear cuenta →</span>
            <span class="btn-loader" style="display:none">Creando cuenta...</span>
          </button>
        </form>

      </div>
    </div>

  </div>

  <script src="assets/js/pages/auth.js"></script>
</body>
</html>