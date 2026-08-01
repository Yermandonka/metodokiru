<?php
require_once __DIR__ . '/includes/functions.php';

// Si ya hay sesión, al panel que corresponda.
if ($u = current_user()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post_texto('username', 30);
    $password = is_scalar($_POST['password'] ?? null) ? (string) $_POST['password'] : '';
    $stmt = db()->prepare('SELECT * FROM usuarios WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        // Mantiene la sesión iniciada aunque se cierre el navegador.
        if (!empty($_POST['recuerdame'])) {
            remember_crear((int) $user['id']);
        }
        redirect('dashboard.php');
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('assets/img/favicon.svg') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= asset('assets/img/favicon.png') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="login-body">
    <div class="login-card">
        <a class="login-logo" href="<?= url('index.php') ?>" title="Ir a la página principal">
            <img class="login-logo-icono" src="<?= asset('assets/img/logo.svg') ?>" alt="Método Kiru">
            <span class="brand-logo-kiru">KIRU</span>
            <span class="brand-logo-sub">Mentoría a<br>domicilio</span>
        </a>
        <h1>Método Kiru</h1>
        <p class="login-sub">Entra en tu mundo</p>
        <?php if ($error): ?><div class="login-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <label>Usuario
                <input type="text" name="username" autocomplete="username" required autofocus>
            </label>
            <label>Contraseña
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <label class="login-check">
                <input type="checkbox" name="recuerdame" checked>
                Mantener la sesión iniciada
            </label>
            <button type="submit">Entrar</button>
        </form>
        <p class="login-hint">¿No tienes cuenta? <a href="<?= url('registro.php') ?>">Crea una aquí</a></p>
        <p class="login-hint">Prueba: <code>alumno</code> / <code>alumno123</code></p>
    </div>
</body>
</html>
