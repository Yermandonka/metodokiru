<?php
require_once __DIR__ . '/includes/web_layout.php';
web_head('Programas | Método Kiru');
web_menu();
?>

  <!-- HERO -->
  <header class="subpage-hero">

    <nav>
      <div class="logo">Método Kiru</div>
      <button class="menu-btn">☰</button>
    </nav>

    <div class="hero-content">

      <h1>Nuestros Programas</h1>

      <p class="subtitle">
        Dos formas de acompañar a tu hijo. Elige el nivel de apoyo que mejor se adapte a sus necesidades.
      </p>

      <section class="programas-comparacion">

        <div class="programa-card">
          <div class="programa-header">
            <h3>Programa Base</h3>
            <p>Refuerzo académico</p>
          </div>

          <ul>
            <li class="si">Sesiones a domicilio</li>
            <li class="si">Apoyo académico y Campus Virtual</li>
            <li class="no">Mentoría personal</li>
            <li class="no">Proyecto personal</li>
            <li class="no">Sistema SkillCoins</li>
            <li class="no">Reunión semanal con padres</li>
          </ul>

          <div class="beneficio">
            <strong>Beneficio principal</strong>
            <p>
              Mejorar resultados académicos, resolver dificultades concretas
              y ganar confianza en el estudio.
            </p>
          </div>
        </div>

        <div class="programa-card destacado">
          <div class="etiqueta">
            Más completo
          </div>

          <div class="programa-header">
            <h3>Método Kiru</h3>
            <p>Desarrollo académico y personal</p>
          </div>

          <ul>
            <li class="si">Sesiones a domicilio</li>
            <li class="si">Apoyo académico y Campus Virtual</li>
            <li class="si">Mentoría personal</li>
            <li class="si">Proyecto personal</li>
            <li class="si">Sistema SkillCoins</li>
            <li class="si">Reunión semanal con padres + Cuaderno Familiar</li>
          </ul>

          <div class="beneficio">
            <strong>Beneficio principal</strong>
            <p>
              Transformación hacia un alumno con mejores hábitos, mayor
              rendimiento académico y menos conflictos familiares.
            </p>
          </div>
        </div>

      </section>

      <div class="program-note">

        <p>
          <strong>* Programa Base sujeto a disponibilidad de plazas y zona geográfica.</strong>
        </p>

        <p>
          Si no hubiera disponibilidad, te propondremos la alternativa más adecuada para vuestra situación.
        </p>

      </div>

      <div class="hero-buttons">

        <a href="<?= url('tarifas.php') ?>" class="primary">
          Ver tarifas
        </a>

        <a href="<?= CALENDLY_URL ?>"
           target="_blank"
           class="secondary">
          Reservar llamada gratuita
        </a>

      </div>

    </div>

  </header>

<?php web_foot(); ?>
