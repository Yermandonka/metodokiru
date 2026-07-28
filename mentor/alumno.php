<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_login(['mentor', 'admin']);

$aid = (int) ($_GET['id'] ?? $_POST['alumno_id'] ?? 0);
$alumno = alumno_por_id($aid);
if (!$alumno) {
    die('Alumno no encontrado.');
}
// Un mentor solo puede tocar a sus alumnos; el admin, a todos.
if ($user['rol'] === 'mentor' && (int) $alumno['mentor_id'] !== (int) $user['id']) {
    http_response_code(403);
    die('Este alumno no está asignado a ti.');
}
$volver = $user['rol'] === 'admin' ? 'admin/resumen.php' : 'mentor/dashboard.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    switch ($accion) {
        case 'perfil':
            db()->prepare('UPDATE alumnos SET edad=?, mensaje_diario=?, avatar=?, nivel_id=? WHERE id=?')
                ->execute([
                    (int) $_POST['edad'],
                    trim($_POST['mensaje_diario']),
                    $_POST['avatar'] ?: 'zorro',
                    ($_POST['nivel_id'] === '' ? null : (int) $_POST['nivel_id']),
                    $aid,
                ]);
            flash('Perfil actualizado.');
            break;
        case 'add_objetivo':
            db()->prepare('INSERT INTO objetivos (alumno_id, titulo, descripcion, prioridad, fecha, recompensa, skillcoins) VALUES (?,?,?,?,?,?,?)')
                ->execute([$aid, trim($_POST['titulo']), trim($_POST['descripcion']),
                    $_POST['prioridad'], ($_POST['fecha'] ?: null), trim($_POST['recompensa']), (int) $_POST['skillcoins']]);
            flash('Objetivo añadido.');
            break;
        case 'del_objetivo':
            db()->prepare('DELETE FROM objetivos WHERE id=? AND alumno_id=?')->execute([(int) $_POST['id'], $aid]);
            break;
        case 'add_habito':
            db()->prepare('INSERT INTO habitos (alumno_id, titulo, fecha) VALUES (?,?,CURDATE())')
                ->execute([$aid, trim($_POST['titulo'])]);
            flash('Hábito asignado.');
            break;
        case 'del_habito':
            db()->prepare('DELETE FROM habitos WHERE id=? AND alumno_id=?')->execute([(int) $_POST['id'], $aid]);
            break;
        case 'add_recompensa':
            db()->prepare('INSERT INTO recompensas (alumno_id, titulo, costo, activa) VALUES (?,?,?,1)')
                ->execute([$aid, trim($_POST['titulo']), (int) $_POST['costo']]);
            flash('Recompensa añadida.');
            break;
        case 'del_recompensa':
            db()->prepare('DELETE FROM recompensas WHERE id=? AND alumno_id=?')->execute([(int) $_POST['id'], $aid]);
            break;
        case 'dar_sc':
            $c = (int) $_POST['cantidad'];
            if ($c !== 0) {
                sc_add($aid, $c, trim($_POST['motivo']) ?: 'Ajuste del mentor');
                flash("SkillCoins ajustados: " . ($c > 0 ? '+' : '') . $c);
            }
            break;
    }
    redirect('mentor/alumno.php?id=' . $aid);
}

/* ---------- Datos ---------- */
$total  = sc_total($aid);
$semana = sc_semana($aid);
$nivelActual = nivel_actual($alumno);
$niveles = niveles_todos();

$objetivos = db()->prepare('SELECT * FROM objetivos WHERE alumno_id=? ORDER BY completado, id');
$objetivos->execute([$aid]);
$objetivos = $objetivos->fetchAll();

$habitos = db()->prepare('SELECT * FROM habitos WHERE alumno_id=? ORDER BY id');
$habitos->execute([$aid]);
$habitos = $habitos->fetchAll();

$recompensas = db()->prepare('SELECT * FROM recompensas WHERE alumno_id=? ORDER BY conseguida, costo');
$recompensas->execute([$aid]);
$recompensas = $recompensas->fetchAll();

$conexiones = (int) db()->query("SELECT COUNT(*) FROM conexiones WHERE alumno_id=$aid")->fetchColumn();
$objOk      = (int) db()->query("SELECT COUNT(*) FROM objetivos WHERE alumno_id=$aid AND completado=1")->fetchColumn();

$avatares = avatares();

render_header('Perfil de ' . $alumno['nombre'], 'panel', '', [['← Volver', $volver, false]]);
?>
<div class="page-head">
    <h1><?= $avatares[$alumno['avatar']] ?? '🦊' ?> <?= e($alumno['nombre']) ?></h1>
    <p class="muted">@<?= e($alumno['username']) ?> · Nivel actual: <strong><?= e($nivelActual['nombre']) ?></strong>
        · <?= $total ?> 🪙 (<?= $semana ?> esta semana)</p>
</div>

<!-- Estadísticas -->
<div class="stat-row">
    <div class="stat-card"><span class="stat-num"><?= $conexiones ?></span><span>Conexiones</span></div>
    <div class="stat-card"><span class="stat-num"><?= $semana ?></span><span>SkillCoins esta semana</span></div>
    <div class="stat-card"><span class="stat-num"><?= $total ?></span><span>SkillCoins</span></div>
    <div class="stat-card"><span class="stat-num"><?= $objOk ?></span><span>Objetivos cumplidos</span></div>
</div>

<div class="grid-2">
    <!-- Perfil -->
    <div class="card">
        <h3>Perfil y ajustes</h3>
        <form method="post" class="form">
            <input type="hidden" name="accion" value="perfil">
            <label>Edad <input type="number" name="edad" value="<?= (int)$alumno['edad'] ?>" min="5" max="18"></label>
            <label>Mensaje diario (recordatorio)
                <textarea name="mensaje_diario" rows="2"><?= e($alumno['mensaje_diario']) ?></textarea>
            </label>
            <label>Avatar
                <select name="avatar">
                    <?php foreach ($avatares as $k => $emo): ?>
                        <option value="<?= $k ?>" <?= $alumno['avatar'] === $k ? 'selected' : '' ?>><?= $emo ?> <?= ucfirst($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nivel
                <select name="nivel_id">
                    <option value="">Automático (por SkillCoins)</option>
                    <?php foreach ($niveles as $n): ?>
                        <option value="<?= (int)$n['id'] ?>" <?= (int)$alumno['nivel_id'] === (int)$n['id'] ? 'selected' : '' ?>>
                            <?= e($n['nombre']) ?> — <?= e(ucfirst($n['mundo'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn">Guardar</button>
        </form>

        <hr>
        <h4>Ajustar SkillCoins</h4>
        <form method="post" class="form-inline">
            <input type="hidden" name="accion" value="dar_sc">
            <input type="number" name="cantidad" placeholder="+/- cantidad" required>
            <input type="text" name="motivo" placeholder="Motivo">
            <button class="btn-sm">Aplicar</button>
        </form>
    </div>

    <!-- Objetivos -->
    <div class="card">
        <h3>Objetivos</h3>
        <ul class="editlist">
            <?php foreach ($objetivos as $o): ?>
                <li>
                    <span><?= $o['completado'] ? '✅' : '⬜' ?> <?= e($o['titulo']) ?>
                        <small class="muted">(<?= e($o['prioridad']) ?>, +<?= (int)$o['skillcoins'] ?>🪙)</small></span>
                    <form method="post" class="inline"><input type="hidden" name="accion" value="del_objetivo">
                        <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                        <button class="btn-x" title="Eliminar">✕</button></form>
                </li>
            <?php endforeach; ?>
            <?php if (!$objetivos): ?><li class="muted">Sin objetivos.</li><?php endif; ?>
        </ul>
        <form method="post" class="form">
            <input type="hidden" name="accion" value="add_objetivo">
            <label>Título <input type="text" name="titulo" required></label>
            <label>Descripción <input type="text" name="descripcion"></label>
            <div class="row">
                <label>Prioridad
                    <select name="prioridad"><option value="alta">Alta</option><option value="media" selected>Media</option><option value="baja">Baja</option></select>
                </label>
                <label>Fecha <input type="date" name="fecha"></label>
                <label>SkillCoins <input type="number" name="skillcoins" value="10" min="0"></label>
            </div>
            <label>Recompensa asociada <input type="text" name="recompensa"></label>
            <button class="btn-sm">Añadir objetivo</button>
        </form>
    </div>

    <!-- Hábitos -->
    <div class="card">
        <h3>Hábitos</h3>
        <ul class="editlist">
            <?php foreach ($habitos as $h): ?>
                <li>
                    <span><?= $h['completado'] ? '✅' : '⬜' ?> <?= e($h['titulo']) ?></span>
                    <form method="post" class="inline"><input type="hidden" name="accion" value="del_habito">
                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                        <button class="btn-x">✕</button></form>
                </li>
            <?php endforeach; ?>
            <?php if (!$habitos): ?><li class="muted">Sin hábitos.</li><?php endif; ?>
        </ul>
        <form method="post" class="form-inline">
            <input type="hidden" name="accion" value="add_habito">
            <input type="text" name="titulo" placeholder="Nuevo hábito" required>
            <button class="btn-sm">Añadir</button>
        </form>
    </div>

    <!-- Recompensas -->
    <div class="card">
        <h3>Recompensas</h3>
        <ul class="editlist">
            <?php foreach ($recompensas as $r): ?>
                <li>
                    <span>🎁 <?= e($r['titulo']) ?> <small class="muted">(<?= (int)$r['costo'] ?>🪙)</small>
                        <?= $r['conseguida'] ? '<span class="tag">conseguida</span>' : '' ?></span>
                    <form method="post" class="inline"><input type="hidden" name="accion" value="del_recompensa">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn-x">✕</button></form>
                </li>
            <?php endforeach; ?>
            <?php if (!$recompensas): ?><li class="muted">Sin recompensas.</li><?php endif; ?>
        </ul>
        <form method="post" class="form-inline">
            <input type="hidden" name="accion" value="add_recompensa">
            <input type="text" name="titulo" placeholder="Premio" required>
            <input type="number" name="costo" placeholder="Coste 🪙" required style="max-width:120px">
            <button class="btn-sm">Añadir</button>
        </form>
    </div>
</div>
<?php render_footer(); ?>
