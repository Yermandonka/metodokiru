<?php
/**
 * Plantilla de la web pública (réplica de www.metodokiru.es).
 * Helpers para no repetir la cabecera y el menú en cada página.
 */
require_once __DIR__ . '/functions.php';

const CALENDLY_URL = 'https://calendly.com/m-sofiamunoz05/llamada-informacion';
const WHATSAPP_URL = 'https://wa.me/34651382833?text=Hola%2C%20me%20gustar%C3%ADa%20informaci%C3%B3n%20sobre%20M%C3%A9todo%20Kiru.';

function web_head(string $title): void
{
    ?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= asset('assets/css/web.css') ?>">
</head>
<body>
<?php
}

function web_menu(): void
{
    ?>
  <!-- MENÚ -->
  <div class="menu-overlay">
    <button class="close-menu">✕</button>

    <a href="<?= url('index.php') ?>">Inicio</a>
    <a href="<?= url('programas.php') ?>">Programas</a>
    <a href="<?= url('tarifas.php') ?>">Tarifas</a>
    <a href="<?= url('familias.php') ?>">Área de Familias</a>
    <a href="<?= url('conocenos.php') ?>">Conócenos</a>
    <a href="<?= url('faq.php') ?>">Preguntas Frecuentes</a>

    <a href="<?= CALENDLY_URL ?>" target="_blank">
      Reservar llamada
    </a>
  </div>
<?php
}

function web_foot(): void
{
    ?>
  <script src="<?= asset('assets/js/web.js') ?>"></script>

</body>
</html>
<?php
}
