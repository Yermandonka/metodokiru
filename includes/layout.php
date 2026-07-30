<?php
require_once __DIR__ . '/functions.php';

/**
 * Cabecera común.
 * @param string $titulo   Título de la pestaña.
 * @param string $tema     'panel' (mentor/admin, minimalista) o 'alumno' (épico).
 * @param string $mundo    Para alumno: bosque|ciudad|montana|cielo.
 * @param array|null $nav  Elementos de menú [ [texto, href, activo], ... ].
 */
function render_header(string $titulo, string $tema = 'panel', string $mundo = '', ?array $nav = null): void
{
    $user = current_user();
    $bodyClass = $tema === 'alumno' ? 'tema-alumno mundo-' . $mundo : 'tema-panel';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($titulo) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="topbar">
    <a class="brand" href="<?= url('index.php') ?>" title="Ir a la página principal">
        <span class="brand-mark">狐</span>
        <span class="brand-name"><?= e(APP_NAME) ?></span>
    </a>
    <?php if ($nav): ?>
    <nav class="mainnav">
        <?php foreach ($nav as $item): ?>
            <a href="<?= url($item[1]) ?>" class="<?= !empty($item[2]) ? 'active' : '' ?>"><?= e($item[0]) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if ($user): ?>
    <div class="topbar-user">
        <span class="rolechip rol-<?= e($user['rol']) ?>"><?= e(ucfirst($user['rol'])) ?></span>
        <span class="uname"><?= e($user['nombre']) ?></span>
        <a class="btn-logout" href="<?= url('logout.php') ?>">Salir</a>
    </div>
    <?php endif; ?>
</header>
<main class="wrap">
<?php
    if ($msg = flash()) {
        echo '<div class="flash">' . e($msg) . '</div>';
    }
}

function render_footer(): void
{
    ?>
</main>
<footer class="foot">© <?= date('Y') ?> Método Kiru · Mentoría educativa a domicilio</footer>
</body>
</html>
<?php
}
