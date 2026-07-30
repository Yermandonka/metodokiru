<?php
require_once __DIR__ . '/includes/web_layout.php';
web_head('Tarifas | Método Kiru');
web_menu('tarifas');
?>

  <!-- HERO -->
  <header class="subpage-hero">
    <?php web_nav() ?>

    <div class="hero-content">
      <h1>Tarifas</h1>

      <p class="subtitle">
        Elige la frecuencia de acompañamiento que mejor se adapte a las necesidades de tu hijo.
      </p>
    </div>
  </header>

  <!-- TARIFAS -->
  <section class="programs">

    <section class="tarifas-grid">

      <div class="tarifa-card">
        <h3>Programa Base</h3>

        <div class="precio-item">
          <span>1 sesión semanal</span>
          <strong>150 €/mes</strong>
        </div>

        <div class="precio-item">
          <span>2 sesiones semanales</span>
          <strong>300 €/mes</strong>
        </div>

        <div class="precio-item">
          <span>3 sesiones semanales</span>
          <strong>480 €/mes</strong>
        </div>
      </div>

      <div class="tarifa-card destacado">
        <div class="etiqueta">
          Método Kiru
        </div>

        <h3>Método Kiru</h3>

        <div class="precio-item">
          <span>1 sesión semanal</span>
          <strong>175 €/mes</strong>
        </div>

        <div class="precio-item">
          <span>2 sesiones semanales</span>
          <strong>325 €/mes</strong>
        </div>

        <div class="precio-item">
          <span>3 sesiones semanales</span>
          <strong>500 €/mes</strong>
        </div>
      </div>

    </section>

    <div class="program-note">

      <p>
        Los precios están expresados por mes y pueden estar sujetos a disponibilidad de plazas y zona geográfica.
      </p>

    </div>

    <div class="hero-buttons">

      <a href="<?= url('programas.php') ?>" class="primary">
        Ver qué incluye cada programa
      </a>

      <a href="<?= CALENDLY_URL ?>"
         target="_blank"
         class="secondary">
        Reservar llamada gratuita
      </a>

    </div>

  </section>

<?php web_foot(); ?>
