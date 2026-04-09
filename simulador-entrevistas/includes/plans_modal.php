<?php
$userPlan = $userPlan ?? 'free';
?>
<div class="plans-overlay" id="plans-overlay" onclick="if(event.target===this)closePlans()">
  <div class="plans-modal">

    <button class="plans-close" onclick="closePlans()" aria-label="Cerrar">
      <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="plans-header">
      <h2>Elige tu plan</h2>
      <p>Invierte en tu carrera. Cancela cuando quieras.</p>
    </div>

    <div class="billing-toggle">
      <span id="lbl-monthly" class="active">Mensual</span>
      <button class="toggle-switch" id="billing-toggle" onclick="toggleBilling()" aria-label="Cambiar ciclo de facturación"></button>
      <span id="lbl-annual">Anual</span>
      <span class="save-badge">Ahorra 20%</span>
    </div>

    <div class="plans-grid">

      <!-- ── FREE ── -->
      <div class="plan-card plan-free-card <?= $userPlan === 'free' ? 'current' : '' ?>">
        <div>
          <div class="plan-badge-wrap">
            <span class="plan-name-badge badge-free">Gratuito</span>
            <?php if ($userPlan === 'free'): ?>
              <span class="badge-current"><i class="fa-solid fa-circle-check"></i> Tu plan</span>
            <?php endif; ?>
          </div>
          <div class="plan-price" style="margin-top:14px">
            <span class="amount">$0</span>
            <span class="period">/ mes</span>
          </div>
          <p class="plan-desc" style="margin-top:8px">Para empezar a practicar sin compromiso.</p>
        </div>
        <ul class="plan-features">
          <li><i class="fa-solid fa-check feat-on"></i> Acceso a todas las entrevistas</li>
          <li><i class="fa-solid fa-check feat-on"></i> <span><strong>50 usos diarios</strong> de IA</span></li>
          <li><i class="fa-solid fa-check feat-on"></i> Historial de partidas</li>
          <li><i class="fa-solid fa-check feat-on"></i> Personalización de perfil</li>
          <li><i class="fa-solid fa-xmark feat-off"></i> Guías de estudio personalizadas</li>
          <li><i class="fa-solid fa-xmark feat-off"></i> Análisis avanzado de respuestas</li>
          <li><i class="fa-solid fa-xmark feat-off"></i> Entrevistas exclusivas</li>
        </ul>
        <button class="plan-cta cta-current" disabled>
          <i class="fa-solid fa-circle-check"></i>
          <?= $userPlan === 'free' ? 'Plan actual' : 'Plan base' ?>
        </button>
      </div>

      <!-- ── PRO ── -->
      <div class="plan-card popular <?= $userPlan === 'pro' ? 'current' : '' ?>">
        <div>
          <div class="plan-badge-wrap">
            <span class="plan-name-badge badge-pro">Pro</span>
            <?php if ($userPlan === 'pro'): ?>
              <span class="badge-current"><i class="fa-solid fa-circle-check"></i> Tu plan</span>
            <?php else: ?>
              <span class="badge-popular"><i class="fa-solid fa-fire"></i> Popular</span>
            <?php endif; ?>
          </div>
          <div class="plan-price" style="margin-top:14px">
            <span class="amount" data-monthly="9" data-annual="7" id="pro-price">$9</span>
            <span class="period">/ mes</span>
          </div>
          <p class="plan-price-annual" id="pro-annual-note" style="display:none">$84 facturado anualmente</p>
          <p class="plan-desc" style="margin-top:8px">Para quienes practican con constancia y quieren mejorar más rápido.</p>
        </div>
        <ul class="plan-features">
          <li><i class="fa-solid fa-check feat-on"></i> Todo lo del plan Gratuito</li>
          <li><i class="fa-solid fa-check feat-on"></i> <span><strong>250 usos diarios</strong> de IA</span></li>
          <li><i class="fa-solid fa-check feat-on"></i> Guías de estudio personalizadas</li>
          <li><i class="fa-solid fa-check feat-on"></i> Análisis avanzado de respuestas</li>
          <li><i class="fa-solid fa-check feat-on"></i> Insignias y logros exclusivos</li>
          <li><i class="fa-solid fa-xmark feat-off"></i> Usos ilimitados de IA</li>
          <li><i class="fa-solid fa-xmark feat-off"></i> Entrevistas exclusivas Elite</li>
        </ul>
        <button class="plan-cta cta-soon" disabled>
          <i class="fa-solid fa-clock"></i> Próximamente
        </button>
      </div>

      <!-- ── ELITE ── -->
      <div class="plan-card elite-card <?= $userPlan === 'elite' ? 'current' : '' ?>">
        <div>
          <div class="plan-badge-wrap">
            <span class="plan-name-badge badge-elite"><i class="fa-solid fa-crown"></i> Elite</span>
            <?php if ($userPlan === 'elite'): ?>
              <span class="badge-current"><i class="fa-solid fa-circle-check"></i> Tu plan</span>
            <?php endif; ?>
          </div>
          <div class="plan-price" style="margin-top:14px">
            <span class="amount" data-monthly="19" data-annual="15" id="elite-price">$19</span>
            <span class="period">/ mes</span>
          </div>
          <p class="plan-price-annual" id="elite-annual-note" style="display:none">$180 facturado anualmente</p>
          <p class="plan-desc" style="margin-top:8px">Para los más comprometidos. Sin límites, sin excusas.</p>
        </div>
        <ul class="plan-features">
          <li><i class="fa-solid fa-check feat-on"></i> Todo lo del plan Pro</li>
          <li><i class="fa-solid fa-check feat-on"></i> <span><strong>Usos ilimitados</strong> de IA</span></li>
          <li><i class="fa-solid fa-check feat-on"></i> Entrevistas exclusivas Elite</li>
          <li><i class="fa-solid fa-check feat-on"></i> Rutas de aprendizaje guiadas</li>
          <li><i class="fa-solid fa-check feat-on"></i> Soporte prioritario</li>
          <li><i class="fa-solid fa-check feat-on"></i> Acceso anticipado a novedades</li>
          <li><i class="fa-solid fa-check feat-on"></i> Badge exclusivo en perfil</li>
        </ul>
        <button class="plan-cta cta-soon" disabled>
          <i class="fa-solid fa-clock"></i> Próximamente
        </button>
      </div>

    </div><!-- /plans-grid -->
  </div><!-- /plans-modal -->
</div><!-- /plans-overlay -->