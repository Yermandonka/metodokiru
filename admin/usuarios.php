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
        } elseif ($accion === 'del_usuario') {
            $uid = post_int('usuario_id', 1);
            if ($uid === (int) $user['id']) {
                throw new RuntimeException('No puedes eliminar tu propia cuenta.');
            }
            $st = db()->prepare('SELECT * FROM usuarios WHERE id=?');
            $st->execute([$uid]);
            $u = $st->fetch();
            if (!$u) {
                throw new RuntimeException('Usuario no encontrado.');
            }
            if ($u['rol'] === 'alumno') {
                $a = alumno_por_usuario($uid);
                if ($a) {
                    // Tablas sin clave foránea: se limpian a mano antes de borrar.
                    foreach (['objetivos', 'habitos', 'recompensas', 'skillcoins_log', 'conexiones', 'materias', 'notificaciones'] as $t) {
                        db()->prepare("DELETE FROM $t WHERE alumno_id=?")->execute([(int) $a['id']]);
                    }
                }
            } elseif ($u['rol'] === 'mentor') {
                db()->prepare('UPDATE alumnos SET mentor_id=NULL WHERE mentor_id=?')->execute([$uid]);
                db()->prepare('DELETE FROM notificaciones WHERE mentor_id=?')->execute([$uid]);
            } elseif ($u['rol'] === 'padre') {
                db()->prepare('UPDATE alumnos SET padre_id=NULL WHERE padre_id=?')->execute([$uid]);
            }
            // Borra el usuario; la ficha de alumno y las tablas con clave foránea caen en cascada.
            db()->prepare('DELETE FROM usuarios WHERE id=?')->execute([$uid]);
            flash(ucfirst($u['rol']) . ' «' . $u['nombre'] . '» eliminado.');
            redirect('admin/usuarios.php');
        }
    } catch (RuntimeException $ex) {
        $errores[] = $ex->getMessage();
    }
}

$mentores = db()->query("SELECT * FROM usuarios WHERE rol='mentor' ORDER BY nombre")->fetchAll();
$padres   = db()->query("SELECT * FROM usuarios WHERE rol='padre' ORDER BY nombre")->fetchAll();
$alumnos  = db()->query(
    "SELECT a.id, a.mentor_id, a.padre_id, a.edad, u.id AS usuario_id, u.nombre, u.username
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
        <thead><tr><th>Alumno</th><th>Usuario</th><th>Mentor</th><th>Familia</th><th></th></tr></thead>
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
                <td class="right">
                    <form method="post" class="inline"
                          onsubmit="return confirm('¿Eliminar definitivamente a <?= e($a['nombre']) ?> y todos sus datos?')">
                        <input type="hidden" name="accion" value="del_usuario">
                        <input type="hidden" name="usuario_id" value="<?= (int)$a['usuario_id'] ?>">
                        <button class="btn-x" title="Eliminar alumno">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Mentores</h3>
        <ul class="editlist">
            <?php foreach ($mentores as $m):
                $n = (int) db()->query("SELECT COUNT(*) FROM alumnos WHERE mentor_id=" . (int)$m['id'])->fetchColumn(); ?>
                <li>
                    <span><?= e($m['nombre']) ?> <small class="muted">@<?= e($m['username']) ?> · <?= $n ?> alumno(s)</small></span>
                    <form method="post" class="inline"
                          onsubmit="return confirm('¿Eliminar al mentor <?= e($m['nombre']) ?>? Sus alumnos quedarán sin mentor.')">
                        <input type="hidden" name="accion" value="del_usuario">
                        <input type="hidden" name="usuario_id" value="<?= (int)$m['id'] ?>">
                        <button class="btn-x" title="Eliminar mentor">✕</button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if (!$mentores): ?><li class="muted">Aún no hay mentores.</li><?php endif; ?>
        </ul>
    </div>

    <div class="card">
        <h3>Familias</h3>
        <ul class="editlist">
            <?php foreach ($padres as $p):
                $n = (int) db()->query("SELECT COUNT(*) FROM alumnos WHERE padre_id=" . (int)$p['id'])->fetchColumn(); ?>
                <li>
                    <span><?= e($p['nombre']) ?> <small class="muted">@<?= e($p['username']) ?> · <?= $n ?> hijo(s)</small></span>
                    <form method="post" class="inline"
                          onsubmit="return confirm('¿Eliminar la cuenta de familia de <?= e($p['nombre']) ?>?')">
                        <input type="hidden" name="accion" value="del_usuario">
                        <input type="hidden" name="usuario_id" value="<?= (int)$p['id'] ?>">
                        <button class="btn-x" title="Eliminar cuenta de familia">✕</button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if (!$padres): ?><li class="muted">Aún no hay cuentas de familia.</li><?php endif; ?>
        </ul>
    </div>
</div>
<?php render_footer(); ?>
