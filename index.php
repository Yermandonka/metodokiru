<?php
/**
 * Portada pública — réplica de lo que hoy se ve en www.metodokiru.es.
 * El acceso a la aplicación (campus) está en el Área de Familias / login.php.
 */
require_once __DIR__ . '/includes/web_layout.php';
web_head('Método Kiru', 'pagina-inicio');
web_menu('inicio');
?>

  <!-- HERO -->
  <header class="hero">

    <?php web_nav() ?>

    <div class="hero-content">

      <p class="eyebrow">
        Mentoría educativa a domicilio
      </p>

      <h1>
       Formamos alumnos autónomos y organizados.
      </h1>

      <p class="subtitle">
        Mentoría educativa a domicilio para estudiantes de 10 a 15 años. Desarrollamos hábitos, responsabilidad y confianza para que aprendan a estudiar con autonomía.
      </p>

      <div class="hero-buttons">

        <a href="<?= CALENDLY_URL ?>"
           target="_blank"
           class="primary">
          Reservar llamada gratuita
        </a>

        <a href="<?= url('programas.php') ?>"
           class="secondary">
          Ver programas
        </a>

      </div>

    </div>

  </header>

  <!-- MENSAJE PRINCIPAL -->

  <section class="quote">

    <h2>
      Menos discusiones por los deberes. Más autonomía, responsabilidad y confianza.
    </h2>

  </section>

  <!-- TESTIMONIOS -->

  <section class="testimonials">

    <h2>Lo que dicen las familias</h2>

    <blockquote>
      “Él está muy contento la verdad, le gusta trabajar contigo.”
      <span>— Madre, 12 años</span>
    </blockquote>

    <blockquote>
      “Sabemos que los resultados no son inmediatos, lo importante es el apoyo que les estás dando. Muchas gracias.”
      <span>— Madre, 10 años</span>
    </blockquote>

    <blockquote>
      “Fantásticos, mil gracias Sofía, qué suerte contar contigo.”
      <span>— Padre, 13 años</span>
    </blockquote>

  </section>

  <!-- CTA FINAL -->
  <section class="quote">

    <a href="<?= CALENDLY_URL ?>"
       target="_blank"
       class="primary btn-solo">
      Reservar llamada
    </a>

  </section>

<?php web_foot(); ?>
