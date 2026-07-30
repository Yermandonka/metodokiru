<?php
require_once __DIR__ . '/includes/web_layout.php';
web_head('Preguntas frecuentes — Método Kiru');
web_menu('faq');
?>

  <header class="subpage-hero">
    <?php web_nav() ?>

    <div class="subpage-content">
      <p class="eyebrow">Preguntas frecuentes</p>
      <h1>Todo lo que necesitas saber</h1>
      <p class="subtitle">
        Transparencia, claridad y acompañamiento desde el primer momento.
      </p>
    </div>
  </header>

  <section class="faq-section">

    <div class="faq-item">
      <button class="faq-question">
        ¿Qué es exactamente Método Kiru?
        <span>+</span>
      </button>
      <div class="faq-answer">
        <p>
          Método Kiru es una mentoría educativa individualizada para familias
          con hijos de 10 a 15 años, centrada en hábitos de estudio,
          autonomía, responsabilidad y desarrollo académico a largo plazo.
        </p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question">
        ¿Es apoyo escolar tradicional?
        <span>+</span>
      </button>
      <div class="faq-answer">
        <p>
          No. Método Kiru va más allá del refuerzo académico. Trabajamos
          organización, constancia, liderazgo personal, motivación y relación
          con el aprendizaje.
        </p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question">
        ¿Es terapia psicológica?
        <span>+</span>
      </button>
      <div class="faq-answer">
        <p>
          No. Método Kiru no es terapia psicológica ni intervención clínica.
          Es un acompañamiento educativo estructurado con metodología propia.
        </p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question">
        ¿En qué consiste el seguimiento familiar?
        <span>+</span>
      </button>
      <div class="faq-answer">
        <p>
          Incluye una llamada online semanal de 20 minutos con los padres,
          una estructura definida de seguimiento, material propio y la guía
          para familias <strong>Brújula Familiar</strong>, diseñada para
          acompañar el proceso desde casa.
        </p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question">
        ¿Dónde trabajamos?
        <span>+</span>
      </button>
      <div class="faq-answer">
        <p>
          Actualmente trabajamos principalmente en domicilio en Pozuelo,
          Aravaca, Valdemarín y Majadahonda.
        </p>
      </div>
    </div>

  </section>

<?php web_foot(); ?>
