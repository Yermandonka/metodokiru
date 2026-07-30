<?php
require_once __DIR__ . '/_common.php';
$user = require_login(['admin']);
padres_migracion();

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'crear_mentor' || $accion === 'crear_alumno' || $accion === 'crear_padre') {
            $username = post_texto('username', 30);
            $nombre   = post_texto('nombre', 50);
            $pass     = is_scalar($_POST['password'] ?? null) ? (string) $_POST['password'] : '';
            if ($nombre === '' || strlen($pass) < 4 || strlen($pass) > 72) {
                throw new RuntimeException('Completa nombre, usuario y contraseña (4-72 caracteres).');
            }
            if (!username_valido($username)) {
                throw new RuntimeException('El usuario debe tener 3-30 caracteres (letras, números, _ o .).');
            }
            $chk = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE username=?');
            $chk->execute([$username]);
            if ($chk->fetchColumn()) {
                throw new RuntimeException('Ese usuario ya existe.');
            }
            $rol = match ($accion) {
                'crear_mentor' => 'mentor',
                'crear_padre'  => 'padre',
                default        => 'alumno',
            };
            db()->prepare('INSERT INTO usuarios (username, password_hash, rol, nombre) VALUES (?,?,?,?)')
                ->execute([$username, password_hash($pass, PASSWORD_DEFAULT), $rol, $nombre]);
            $uid = (int) db()->lastInsertId();
            if ($rol === 'alumno') {
                $mentorId = ($_POST['mentor_id'] ?? '') !== '' ? post_int('mentor_id', 1) : null;
                db()->prepare('INSERT INTO alumnos (usuario_id, edad, mentor_id, mensaje_diario) VALUES (?,?,?,?)')
                    ->execute([$uid, post_int('edad', 5, 18, 12), $mentorId, '¡Bienvenido a Método Kiru!']);
            }
            flash(ucfirst($rol) . ' «' . $nombre . '» creado.');
            redirect('admin/usuarios.php');
        } elseif ($accion === 'asignar') {
            $mentorId = ($_POST['mentor_id'] ?? '') !== '' ? post_int('mentor_id', 1) : null;
            db()->prepare('UPDATE alumnos SET mentor_id=? WHERE id=?')->execute([$mentorId, post_int('alumno_id', 1)]);
            flash('Asignación actualizada.');
            redirect('admin/usuarios.php');
        } elseif ($accion === 'asignar_padre') {
            $padreId = ($_POST['padre_id'] ?? '') !== '' ? post_int('padre_id', 1) : null;
            db()->prepare('UPDATE alumnos SET padre_id=? WHERE id=?')->execute([$padreId, post_int('alumno_id', 1)]);
            flash('Familia actualizada.');
            redirect('admin/usuarios.php');
        }
    } catch (RuntimeException $ex) {
        $errores[] = $ex->getMessage();
    }
}

$mentores = db()->query("SELECT * FROM usuarios WHERE rol='mentor' ORDER BY nombre")->fetchAll();
$padres   = db()->query("SELECT * FROM usuarios WHERE rol='padre' ORDER BY nombre")->fetchAll();
$alumnos  = db()->query(
    "SELECT a.id, a.mentor_id, a.padre_id, a.edad, u.nombre, u.username
     FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id ORDER BY u.nombre"
)->fetchAll();

render_header('Usuarios', 'panel', '', nav_admin('usuarios'));
?>
<div class="page-head"><h1>Gestión de usuarios</h1></div>
<?php foreach ($errores as $er): ?><div class="login-error"><?= e($er) ?></div><?php endforeach; ?>

<div class="grid-2">
    <div class="card">
        <h3>Crear mentor</h3>
        <form method="post" class="form">
            <input type="hidden" name="accion" value="crear_mentor">
            <label>Nombre <input type="text" name="nombre" required></label>
            <label>Usuario <input type="text" name="username" required></label>
            <label>Contraseña <input type="text" name="password" required></label>
            <button class="btn">Crear mentor</button>
        </form>
    </div>

    <div class="card">
        <h3>Crear alumno</h3>
        <form method="post" class="form">
            <input type="hidden" name="accion" value="crear_alumno">
            <label>Nombre <input type="text" name="nombre" required></label>
            <label>Usuario <input type="text" name="username" required></label>
            <label>Contraseña <input type="text" name="password" required></label>
            <div class="row">
                <label>Edad <input type="number" name="edad" value="12" min="5" max="18"></label>
                <label>Mentor
                    <select name="mentor_id">
                        <option value="">— sin asignar —</option>
                        <?php foreach ($mentores as $m): ?>
                            <option value="<?= (int)$m['id'] ?>"><?= e($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <button class="btn">Crear alumno</button>
        </form>
    </div>
</div>

<div class="card">
    <h3>Crear padre/madre</h3>
    <p class="muted">Las cuentas de familia solo ven el progreso de sus hijos (sin editar nada). Vincula los hijos en la tabla de abajo.</p>
    <form method="post" class="form">
        <input type="hidden" name="accion" value="crear_padre">
        <div class="row">
            <label>Nombre <input type="text" name="nombre" required></label>
            <label>Usuario <input type="text" name="username" required></label>
            <label>Contraseña <input type="text" name="password" required></label>
        </div>
        <button class="btn">Crear padre/madre</button>
    </form>
</div>

<div class="card">
    <h3>Asignar mentor y familia a cada alumno</h3>
    <table class="tabla">
        <thead><tr><th>Alumno</th><th>Usuario</th><th>Mentor</th><th>Familia</th></tr></thead>
        <tbody>
        <?php foreach ($alumnos as $a): ?>
            <tr>
                <td><?= e($a['nombre']) ?></td>
                <td class="muted">@<?= e($a['username']) ?></td>
                <td>
                    <form method="post" class="form-inline">
                        <input type="hidden" name="accion" value="asignar">
                        <input type="hidden" name="alumno_id" value="<?= (int)$a['id'] ?>">
                        <select name="mentor_id" onchange="this.form.submit()">
                            <option value="">— sin asignar —</option>
                            <?php foreach ($mentores as $m): ?>
                                <option value="<?= (int)$m['id'] ?>" <?= (int)$a['mentor_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                                    <?= e($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td>
                    <form method="post" class="form-inline">
                        <input type="hidden" name="accion" value="asignar_padre">
                        <input type="hidden" name="alumno_id" value="<?= (int)$a['id'] ?>">
                        <select name="padre_id" onchange="this.form.submit()">
                            <option value="">— sin familia —</option>
                            <?php foreach ($padres as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= (int)$a['padre_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Mentores</h3>
    <ul class="editlist">
        <?php foreach ($mentores as $m):
            $n = (int) db()->query("SELECT COUNT(*) FROM alumnos WHERE mentor_id=" . (int)$m['id'])->fetchColumn(); ?>
            <li><span><?= e($m['nombre']) ?> <small class="muted">@<?= e($m['username']) ?> · <?= $n ?> alumno(s)</small></span></li>
        <?php endforeach; ?>
        <?php if (!$mentores): ?><li class="muted">Aún no hay mentores.</li><?php endif; ?>
    </ul>
</div>
<?php render_footer(); ?>
