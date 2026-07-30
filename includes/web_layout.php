<?php
/**
 * Plantilla de la web pública (réplica de www.metodokiru.es).
 * Helpers para no repetir la cabecera, el menú y el pie en cada página.
 */
require_once __DIR__ . '/functions.php';

const CALENDLY_URL = 'https://calendly.com/m-sofiamunoz05/llamada-informacion';
const WHATSAPP_URL = 'https://wa.me/34651382833?text=Hola%2C%20me%20gustar%C3%ADa%20informaci%C3%B3n%20sobre%20M%C3%A9todo%20Kiru.';

/** Páginas del menú: clave => [texto, archivo]. */
function web_paginas(): array
{
    return [
        'inicio'    => ['Inicio', 'index.php'],
        'programas' => ['Programas', 'programas.php'],
        'tarifas'   => ['Tarifas', 'tarifas.php'],
        'familias'  => ['Área de Familias', 'familias.php'],
        'conocenos' => ['Conócenos', 'conocenos.php'],
        'faq'       => ['Preguntas Frecuentes', 'faq.php'],
    ];
}

function web_head(string $title, string $bodyClass = ''): void
{
    ?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= asset('assets/img/favicon.svg') ?>">
  <link rel="icon" type="image/png" sizes="64x64" href="<?= asset('assets/img/favicon.png') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/web.css') ?>">
</head>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
<?php
}

/** Cabecera común de todas las páginas: marca + Accede + hamburguesa. */
function web_nav(): void
{
    ?>
    <nav class="web-nav">
      <?php web_brand() ?>
      <div class="nav-actions">
        <a href="<?= url('login.php') ?>" class="btn-accede">Accede</a>
        <?php web_menu_btn() ?>
      </div>
    </nav>
<?php
}

function web_menu(string $activo = ''): void
{
    ?>
  <!-- MENÚ LATERAL -->
  <div class="menu-overlay">
    <aside class="menu-panel">

      <div class="menu-top">
        <?php web_brand() ?>
        <button class="close-menu" aria-label="Cerrar menú">✕</button>
      </div>

      <nav class="menu-links">
        <?php foreach (web_paginas() as $clave => [$texto, $archivo]): ?>
          <a href="<?= url($archivo) ?>" class="<?= $clave === $activo ? 'activo' : '' ?>"><?= e($texto) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="menu-acciones">
        <a href="<?= url('login.php') ?>" class="btn-accede">Accede</a>
        <a href="<?= CALENDLY_URL ?>" target="_blank" class="menu-cta">Reservar llamada</a>
      </div>

    </aside>
  </div>
<?php
}

/** Marca KIRU (el logo, recreado en CSS); al pulsarla vuelve a la portada. */
function web_brand(): void
{
    ?><a href="<?= url('index.php') ?>" class="brand-kiru" title="Método Kiru">
      <span class="brand-logo">
        <span class="brand-logo-kiru">KIRU</span>
        <span class="brand-logo-sub">Mentoría a<br>domicilio</span>
      </span>
    </a><?php
}

/** Botón hamburguesa (dos barras que se cruzan al abrir). */
function web_menu_btn(): void
{
    ?><button class="menu-btn" aria-label="Abrir menú" aria-expanded="false">
      <span></span><span></span>
    </button><?php
}

function web_foot(): void
{
    ?>
  <!-- PIE COMÚN -->
  <footer class="web-foot">
    <div class="web-foot-top">
      <?php web_brand() ?>
      <nav class="web-foot-links">
        <?php foreach (web_paginas() as [$texto, $archivo]): ?>
          <a href="<?= url($archivo) ?>"><?= e($texto) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="web-foot-bottom">
      <span>© <?= date('Y') ?> Método Kiru · Mentoría educativa a domicilio</span>
      <div class="web-foot-contacto">
        <a href="<?= WHATSAPP_URL ?>" target="_blank">WhatsApp</a>
        <a href="<?= CALENDLY_URL ?>" target="_blank">Reservar llamada</a>
        <a href="<?= url('login.php') ?>">Campus Virtual</a>
      </div>
    </div>
  </footer>

  <script src="<?= asset('assets/js/web.js') ?>"></script>

</body>
</html>
<?php
}
