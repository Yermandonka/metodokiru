<?php
require_once __DIR__ . '/includes/web_layout.php';
web_head('Conócenos — Método Kiru');
web_menu();
?>

  <header class="subpage-hero">
    <nav>
      <?php web_brand() ?>
      <div class="nav-actions">
        <a href="<?= WHATSAPP_URL ?>"
           target="_blank"
           class="mini-btn">
          WhatsApp
        </a>
        <?php web_menu_btn() ?>
      </div>
    </nav>

    <div class="subpage-content">
      <p class="eyebrow">Conócenos</p>

      <h1>
        Detrás de Método Kiru
      </h1>

      <p class="subtitle">
        Una forma diferente de acompañar el aprendizaje, basada en la comprensión, la autonomía y la confianza.
      </p>
    </div>
  </header>

  <section class="family-dashboard">

    <div class="family-card">

      <p class="card-label">Fundadora</p>

      <h2>Sofía Muñoz</h2>

      <span>
        Soy estudiante de Economía y Psicología. Tengo 6 años de experiencia en clases particulares y un enfoque que aprendí trabajando con adolescentes en Estados Unidos.
      </span>

    </div>

    <a href="<?= WHATSAPP_URL ?>"
       target="_blank"
       class="family-card">

      <p class="card-label">Contacto</p>

      <h2>¿Hablamos?</h2>

      <span>
        Estaré encantada de resolver tus dudas y ayudarte a encontrar la mejor opción para tu hijo o hija.
        <br><br>
        WhatsApp →
      </span>

    </a>

  </section>

<?php web_foot(); ?>
