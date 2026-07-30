<?php
require_once __DIR__ . '/includes/functions.php';

// Si ya hay sesión, al panel.
if (current_user()) {
    redirect('dashboard.php');
}

$errores = [];
$datos = ['nombre' => '', 'username' => '', 'rol' => 'alumno', 'edad' => '12'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos['nombre']   = post_texto('nombre', 50);
    $datos['username'] = post_texto('username', 30);
    $datos['rol']      = post_opcion('rol', ['alumno', 'mentor'], ''); // nunca admin
    $datos['edad']     = post_int('edad', 0, 120, 0);
    $pass  = is_scalar($_POST['password'] ?? null) ? (string) $_POST['password'] : '';
    $pass2 = is_scalar($_POST['password2'] ?? null) ? (string) $_POST['password2'] : '';

    // Solo se permite registrarse como alumno o mentor (nunca admin).
    if ($datos['rol'] === '') {
        $datos['rol'] = 'alumno';
        $errores[] = 'Rol no válido.';
    }
    if ($datos['nombre'] === '') {
        $errores[] = 'Escribe tu nombre.';
    }
    if (!username_valido($datos['username'])) {
        $errores[] = 'El usuario debe tener 3-30 caracteres (letras, números, _ o .).';
    }
    if (strlen($pass) < 4 || strlen($pass) > 72) {
        $errores[] = 'La contraseña debe tener entre 4 y 72 caracteres.';
    }
    if ($pass !== $pass2) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    if ($datos['rol'] === 'alumno' && ($datos['edad'] < 5 || $datos['edad'] > 18)) {
        $errores[] = 'La edad debe estar entre 5 y 18 años.';
    }
    if (!$errores) {
        $chk = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE username = ?');
        $chk->execute([$datos['username']]);
        if ($chk->fetchColumn()) {
            $errores[] = 'Ese nombre de usuario ya está en uso.';
        }
    }

    if (!$errores) {
        db()->prepare('INSERT INTO usuarios (username, password_hash, rol, nombre) VALUES (?,?,?,?)')
            ->execute([$datos['username'], password_hash($pass, PASSWORD_DEFAULT), $datos['rol'], $datos['nombre']]);
        $uid = (int) db()->lastInsertId();

        if ($datos['rol'] === 'alumno') {
            db()->prepare('INSERT INTO alumnos (usuario_id, edad, mentor_id, mensaje_diario)
                           VALUES (?,?,NULL,?)')
                ->execute([$uid, $datos['edad'], '¡Bienvenido a Método Kiru! 🦊']);
        }

        // Inicia sesión automáticamente y la mantiene iniciada.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $uid;
        if (!empty($_POST['recuerdame'])) {
            remember_crear($uid);
        }
        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="login-body">
    <div class="login-card">
        <a class="login-logo" href="<?= url('index.php') ?>" title="Ir a la página principal">
            <span class="brand-logo-kiru">KIRU</span>
            <span class="brand-logo-sub">Mentoría a<br>domicilio</span>
        </a>
        <h1>Crear cuenta</h1>
        <p class="login-sub">Únete a Método Kiru</p>

        <?php foreach ($errores as $er): ?>
            <div class="login-error"><?= e($er) ?></div>
        <?php endforeach; ?>

        <form method="post" autocomplete="off">
            <label>Nombre
                <input type="text" name="nombre" value="<?= e($datos['nombre']) ?>" required autofocus>
            </label>
            <label>Quiero registrarme como
                <select name="rol" id="rolSelect">
                    <option value="alumno" <?= $datos['rol'] === 'alumno' ? 'selected' : '' ?>>Alumno</option>
                    <option value="mentor" <?= $datos['rol'] === 'mentor' ? 'selected' : '' ?>>Mentor</option>
                </select>
            </label>
            <label id="edadCampo">Edad
                <input type="number" name="edad" value="<?= e((string)$datos['edad']) ?>" min="5" max="18">
            </label>
            <label>Usuario
                <input type="text" name="username" value="<?= e($datos['username']) ?>" required>
            </label>
            <label>Contraseña
                <input type="password" name="password" required>
            </label>
            <label>Repite la contraseña
                <input type="password" name="password2" required>
            </label>
            <label class="login-check">
                <input type="checkbox" name="recuerdame" checked>
                Mantener la sesión iniciada
            </label>
            <button type="submit">Crear cuenta</button>
        </form>

        <p class="login-hint">¿Ya tienes cuenta? <a href="<?= url('login.php') ?>">Inicia sesión</a></p>
    </div>

    <script>
        // Muestra el campo de edad solo si el rol es alumno.
        var rol = document.getElementById('rolSelect');
        var edad = document.getElementById('edadCampo');
        function toggleEdad() { edad.style.display = rol.value === 'alumno' ? '' : 'none'; }
        rol.addEventListener('change', toggleEdad);
        toggleEdad();
    </script>
</body>
</html>
