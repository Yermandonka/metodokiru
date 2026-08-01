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

function web_head(string $title, string $bodyClass = '', string $desc = ''): void
{
    $desc = $desc ?: 'Mentoría educativa a domicilio: acompañamos a tu hijo en casa, a su ritmo, con un mentor de confianza.';
    // URL absoluta (las previews de WhatsApp/Telegram exigen rutas completas).
    $abs = (es_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'www.metodokiru.es');
    ?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">

  <!-- Preview al compartir el enlace (WhatsApp, Telegram, redes) -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Método Kiru">
  <meta property="og:locale" content="es_ES">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <meta property="og:url" content="<?= e($abs . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
  <meta property="og:image" content="<?= e($abs . url('assets/img/og.png')) ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta name="twitter:card" content="summary_large_image">

  <link rel="icon" type="image/svg+xml" href="<?= asset('assets/img/favicon.svg') ?>">
  <link rel="icon" type="image/png" sizes="64x64" href="<?= asset('assets/img/favicon.png') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/web.css') ?>">
</head>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
<?php
}

/** Cabecera común: marca + enlaces visibles + WhatsApp + Accede (hamburguesa solo en móvil). */
function web_nav(): void
{
    $enlaces = [
        ['Programas', 'programas.php'],
        ['Tarifas', 'tarifas.php'],
        ['Familias', 'familias.php'],
        ['Conócenos', 'conocenos.php'],
        ['FAQ', 'faq.php'],
    ];
    ?>
    <nav class="web-nav">
      <?php web_brand() ?>
      <div class="nav-links">
        <?php foreach ($enlaces as [$texto, $archivo]): ?>
          <a href="<?= url($archivo) ?>"><?= e($texto) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="nav-actions">
        <a href="<?= WHATSAPP_URL ?>" target="_blank" class="btn-whatsapp" title="Escríbenos por WhatsApp">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
          WhatsApp
        </a>
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
        <a href="<?= WHATSAPP_URL ?>" target="_blank" class="menu-cta">WhatsApp</a>
        <a href="https://calculalo.app" target="_blank" rel="noopener" class="menu-cta">Calculalo.app</a>
      </div>

    </aside>
  </div>
<?php
}

/** Marca KIRU (icono + nombre); al pulsarla vuelve a la portada. */
function web_brand(): void
{
    ?><a href="<?= url('index.php') ?>" class="brand-kiru" title="Método Kiru">
      <span class="brand-logo">
        <img class="brand-icono" src="<?= asset('assets/img/logo.svg') ?>" alt="Kiru">
        <span class="brand-texto">
          <span class="brand-logo-kiru">KIRU</span>
          <span class="brand-logo-sub">Mentoría a<br>domicilio</span>
        </span>
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
        <a href="https://calculalo.app" target="_blank" rel="noopener">Calculalo.app</a>
        <a href="<?= url('login.php') ?>">Campus Virtual</a>
      </div>
    </div>
  </footer>

  <script src="<?= asset('assets/js/web.js') ?>"></script>

</body>
</html>
<?php
}
