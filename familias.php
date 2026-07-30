<?php
require_once __DIR__ . '/includes/web_layout.php';
web_head('Área familias — Método Kiru');
web_menu('familias');
?>

  <header class="subpage-hero">
    <?php web_nav() ?>

    <div class="subpage-content">
      <p class="eyebrow">Área familias</p>

      <h1>
        Un espacio pensado para acompañar cada proceso
      </h1>

      <p class="subtitle">
        Reservas, seguimiento, recursos y acompañamiento familiar
        en un mismo lugar.
      </p>
    </div>
  </header>

  <section class="family-dashboard">

    <a href="<?= url('login.php') ?>" class="family-card">
      <p class="card-label">Campus Virtual</p>
      <h2>Iniciar sesión</h2>
      <span>Acceder →</span>
    </a>

    <a href="<?= CALENDLY_URL ?>"
       target="_blank"
       class="family-card">
      <p class="card-label">Reservas</p>
      <h2>Reservar sesión</h2>
      <span>Acceder →</span>
    </a>

    <a href="https://buy.stripe.com/9B6fZiflpeo09VxauafMA00"
       target="_blank"
       class="family-card">
      <p class="card-label">Evaluación</p>
      <h2>Evaluación inicial</h2>
      <span>Acceder →</span>
    </a>

    <a href="https://buy.stripe.com/fZu7sM0qvgw85Fh6dUfMA01"
       target="_blank"
       class="family-card">
      <p class="card-label">Programa</p>
      <h2>Programa Kiru</h2>
      <span>Acceder →</span>
    </a>

    <a href="https://wa.me/34651382833?text=Hola%2C%20me%20gustar%C3%ADa%20informaci%C3%B3n.%20%C2%BFTe%20puedo%20llamar%3F"
       target="_blank"
       class="family-card">
      <p class="card-label">Contacto</p>
      <h2>WhatsApp directo</h2>
      <span>Acceder →</span>
    </a>

  </section>

<?php web_foot(); ?>
