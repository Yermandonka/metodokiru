<?php
require_once __DIR__ . '/includes/functions.php';

// Si ya hay sesión, al panel.
if (current_user()) {
    redirect('dashboard.php');
}

$errores = [];
$datos = ['nombre' => '', 'username' => '', 'rol' => 'alumno', 'edad' => '12'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos['nombre']   = trim($_POST['nombre'] ?? '');
    $datos['username'] = trim($_POST['username'] ?? '');
    $datos['rol']      = $_POST['rol'] ?? 'alumno';
    $datos['edad']     = (int) ($_POST['edad'] ?? 0);
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    // Solo se permite registrarse como alumno o mentor (nunca admin).
    if (!in_array($datos['rol'], ['alumno', 'mentor'], true)) {
        $errores[] = 'Rol no válido.';
    }
    if ($datos['nombre'] === '') {
        $errores[] = 'Escribe tu nombre.';
    }
    if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $datos['username'])) {
        $errores[] = 'El usuario debe tener 3-30 caracteres (letras, números, _ o .).';
    }
    if (strlen($pass) < 4) {
        $errores[] = 'La contraseña debe tener al menos 4 caracteres.';
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
            db()->prepare('INSERT INTO alumnos (usuario_id, edad, mentor_id, mensaje_diario, tiempo_maximo)
                           VALUES (?,?,NULL,?,0)')
                ->execute([$uid, $datos['edad'], '¡Bienvenido a Método Kiru! 🦊']);
        }

        // Inicia sesión automáticamente.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $uid;
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
        <div class="login-logo">狐</div>
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
            <button type="submit">Crear cuenta</button>
        </form>

        <p class="login-hint">¿Ya tienes cuenta? <a href="<?= url('index.php') ?>">Inicia sesión</a></p>
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
