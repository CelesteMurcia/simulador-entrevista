<?php
require_once '../middleware/auth.php';
$pageStyles = ['../assets/css/game/menu.css'];
include '../includes/header.php'; 

?>
  <main class="menu-main">

    <div class="menu-hero">
      <h1>¿Qué tipo de entrevista quieres practicar?</h1>
      <p>Selecciona una categoría para ver las entrevistas disponibles y sus niveles.</p>
    </div>

    <div class="type-grid">

      <!-- Técnica — disponible -->
      <a href="levels.php?type=technical" class="type-card available">
        <div class="type-card-icon">
          <i class="fa-solid fa-laptop-code"></i>
        </div>
        <h2>Técnica</h2>
        <p>Conocimiento específico: programación, bases de datos, redes y ciberseguridad.</p>
        <div class="type-card-tags">
          <span class="tag"><i class="fa-solid fa-code"></i> Programación</span>
          <span class="tag"><i class="fa-solid fa-shield-halved"></i> Ciberseguridad</span>
        </div>
        <div class="type-card-footer">
          <span class="badge badge-success">Disponible</span>
          <i class="fa-solid fa-arrow-right type-arrow"></i>
        </div>
      </a>

      <!-- Conductual — próximamente -->
      <div class="type-card locked">
        <div class="type-card-icon">
          <i class="fa-solid fa-comments"></i>
        </div>
        <h2>Conductual</h2>
        <p>Comportamiento, trabajo en equipo, manejo de conflictos y habilidades blandas.</p>
        <div class="type-card-tags">
          <span class="tag muted"><i class="fa-solid fa-users"></i> Trabajo en equipo</span>
          <span class="tag muted"><i class="fa-solid fa-handshake"></i> Soft skills</span>
        </div>
        <div class="type-card-footer">
          <span class="badge badge-locked"><i class="fa-solid fa-lock"></i> Próximamente</span>
        </div>
      </div>

      <!-- De sistema — próximamente -->
      <div class="type-card locked">
        <div class="type-card-icon">
          <i class="fa-solid fa-sitemap"></i>
        </div>
        <h2>De sistema</h2>
        <p>Diseño de arquitectura, escalabilidad y toma de decisiones técnicas de alto nivel.</p>
        <div class="type-card-tags">
          <span class="tag muted"><i class="fa-solid fa-server"></i> Arquitectura</span>
          <span class="tag muted"><i class="fa-solid fa-chart-line"></i> Escalabilidad</span>
        </div>
        <div class="type-card-footer">
          <span class="badge badge-locked"><i class="fa-solid fa-lock"></i> Próximamente</span>
        </div>
      </div>

      <!-- Creativa — próximamente -->
      <div class="type-card locked">
        <div class="type-card-icon">
          <i class="fa-solid fa-brain"></i>
        </div>
        <h2>Creativa</h2>
        <p>Problemas abiertos y preguntas que evalúan pensamiento creativo y lateral.</p>
        <div class="type-card-tags">
          <span class="tag muted"><i class="fa-solid fa-pen-nib"></i> Diseño</span>
          <span class="tag muted"><i class="fa-solid fa-puzzle-piece"></i> Casos prácticos</span>
        </div>
        <div class="type-card-footer">
          <span class="badge badge-locked"><i class="fa-solid fa-lock"></i> Próximamente</span>
        </div>
      </div>

    </div>
  </main>

</body>
</html>