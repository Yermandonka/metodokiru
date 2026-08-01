<?php
/**
 * Ficha del alumno (mentor/admin), en dos pestañas:
 *  - Resumen: vista del alumno — estadísticas, sus tareas de inglés con la
 *    nota de cada una, nota media y radar de destrezas (feedback).
 *  - Ajustes: todo lo que el mentor modifica — perfil, objetivos, hábitos,
 *    recompensas y ajuste de SkillCoins.
 */
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
$tab = ($_GET['t'] ?? '') === 'ajustes' ? 'ajustes' : 'resumen';

/* ---------- Acciones (todas viven en la pestaña Ajustes) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    switch ($accion) {
        case 'perfil':
            $avatar = post_opcion('avatar', array_keys(avatares()), $alumno['avatar'] ?: 'zorro');
            db()->prepare('UPDATE alumnos SET edad=?, mensaje_diario=?, avatar=?, nivel_id=? WHERE id=?')
                ->execute([
                    post_int('edad', 5, 18, (int) $alumno['edad']),
                    post_texto('mensaje_diario', 255),
                    $avatar,
                    (($_POST['nivel_id'] ?? '') === '' ? null : post_int('nivel_id', 1)),
                    $aid,
                ]);
            flash('Perfil actualizado.');
            break;
        case 'add_objetivo':
            $titulo = post_texto('titulo', 100);
            if ($titulo === '') {
                flash('El objetivo necesita un título.');
                break;
            }
            db()->prepare('INSERT INTO objetivos (alumno_id, titulo, descripcion, prioridad, fecha, recompensa, skillcoins) VALUES (?,?,?,?,?,?,?)')
                ->execute([$aid, $titulo, post_texto('descripcion', 255),
                    post_opcion('prioridad', ['alta', 'media', 'baja'], 'media'),
                    fecha_o_null($_POST['fecha'] ?? null), post_texto('recompensa', 100),
                    post_int('skillcoins', 0, 1000, 0)]);
            flash('Objetivo añadido.');
            break;
        case 'del_objetivo':
            db()->prepare('DELETE FROM objetivos WHERE id=? AND alumno_id=?')->execute([post_int('id', 1), $aid]);
            break;
        case 'add_habito':
            $titulo = post_texto('titulo', 100);
            if ($titulo === '') {
                break;
            }
            db()->prepare('INSERT INTO habitos (alumno_id, titulo, fecha) VALUES (?,?,CURDATE())')
                ->execute([$aid, $titulo]);
            flash('Hábito asignado.');
            break;
        case 'del_habito':
            db()->prepare('DELETE FROM habitos WHERE id=? AND alumno_id=?')->execute([post_int('id', 1), $aid]);
            break;
        case 'add_recompensa':
            $titulo = post_texto('titulo', 100);
            if ($titulo === '') {
                break;
            }
            db()->prepare('INSERT INTO recompensas (alumno_id, titulo, costo, activa) VALUES (?,?,?,1)')
                ->execute([$aid, $titulo, post_int('costo', 0, 100000, 0)]);
            flash('Recompensa añadida.');
            break;
        case 'del_recompensa':
            db()->prepare('DELETE FROM recompensas WHERE id=? AND alumno_id=?')->execute([post_int('id', 1), $aid]);
            break;
        case 'dar_sc':
            $c = post_int('cantidad', -10000, 10000, 0);
            if ($c !== 0) {
                sc_add($aid, $c, post_texto('motivo', 100) ?: 'Ajuste del mentor');
                flash("SkillCoins ajustados: " . ($c > 0 ? '+' : '') . $c);
            }
            break;
    }
    redirect('mentor/alumno.php?id=' . $aid . '&t=ajustes');
}

/* ---------- Datos comunes ---------- */
$total  = sc_total($aid);
$semana = sc_semana($aid);
$nivelActual = nivel_actual($alumno);
$avatares = avatares();

render_header('Perfil de ' . $alumno['nombre'], 'panel', '', [
    ['← Volver', $volver, false],
]);
?>
<div class="page-head">
    <h1><?= $avatares[$alumno['avatar']] ?? '🦊' ?> <?= e($alumno['nombre']) ?></h1>
    <p class="muted">@<?= e($alumno['username']) ?> · <?= (int)$alumno['edad'] ?> años
        · Nivel: <strong><?= e($nivelActual['nombre']) ?></strong>
        · <?= $total ?> 🪙 (<?= $semana ?> esta semana)</p>
</div>

<!-- Pestañas: vista del alumno / ajustes -->
<nav class="tabs">
    <a class="<?= $tab === 'resumen' ? 'active' : '' ?>" href="<?= url('mentor/alumno.php?id=' . $aid) ?>">📊 Resumen</a>
    <a class="<?= $tab === 'ajustes' ? 'active' : '' ?>" href="<?= url('mentor/alumno.php?id=' . $aid . '&t=ajustes') ?>">⚙️ Ajustes</a>
    <a href="<?= url('mentor/ingles.php?id=' . $aid) ?>">🇬🇧 Inglés</a>
</nav>

<?php if ($tab === 'resumen'): ?>
<?php
    /* ---------- Datos del resumen ---------- */
    $racha      = racha_dias($aid);
    $conexiones = (int) db()->query("SELECT COUNT(*) FROM conexiones WHERE alumno_id=$aid")->fetchColumn();
    $objOk      = (int) db()->query("SELECT COUNT(*) FROM objetivos WHERE alumno_id=$aid AND completado=1")->fetchColumn();
    $notaMedia  = nota_media_ingles($aid);
    $tareas     = ingles_tareas_de($aid);
    $destrezas  = ingles_destrezas();
    $radar      = ingles_radar($aid);
    $hayRadar   = (bool) array_filter($radar, fn($v) => $v['pct'] !== null);

    $st = db()->prepare('SELECT r.*, t.titulo AS tarea_titulo FROM ingles_resultados r
                         LEFT JOIN ingles_tareas t ON t.id = r.tarea_id
                         WHERE r.alumno_id=? ORDER BY r.fecha DESC LIMIT 10');
    $st->execute([$aid]);
    $resultados = $st->fetchAll();
?>

<!-- Estadísticas -->
<div class="stat-row">
    <div class="stat-card"><span class="stat-num"><?= $notaMedia !== null ? nota_txt($notaMedia) . '/10' : '—' ?></span><span>Nota media de tareas</span></div>
    <div class="stat-card"><span class="stat-num">🔥 <?= $racha ?></span><span>Días de racha</span></div>
    <div class="stat-card"><span class="stat-num"><?= $conexiones ?></span><span>Días de conexión</span></div>
    <div class="stat-card"><span class="stat-num">🎯 <?= $objOk ?></span><span>Objetivos cumplidos</span></div>
</div>

<div class="grid-2">
    <!-- Tareas de inglés con su nota -->
    <div class="card">
        <div class="card-head">
            <h3>Tareas de inglés</h3>
            <a class="link" href="<?= url('mentor/ingles.php?id=' . $aid) ?>">Gestionar</a>
        </div>
        <ul class="editlist">
            <?php foreach ($tareas as $t):
                $hecha = $t['resultado_id'] !== null; ?>
                <li>
                    <span><?= $hecha ? '✅' : '⬜' ?> <?= e($t['titulo']) ?>
                        <small class="muted"><?=
                            $t['fecha_entrega'] ? 'entrega ' . e(date('d/m/Y', strtotime($t['fecha_entrega']))) : '' ?></small>
                    </span>
                    <?php if ($hecha): ?>
                        <span class="tag gold"><?= nota_txt(nota_sobre_10((int)$t['aciertos'], (int)$t['total'])) ?>/10</span>
                    <?php else: ?>
                        <span class="tag prio-media">Pendiente</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if (!$tareas): ?><li class="muted">Sin tareas creadas. Créalas en «Inglés».</li><?php endif; ?>
        </ul>
    </div>

    <!-- Feedback: radar de destrezas -->
    <div class="card">
        <h3>Radar de destrezas</h3>
        <?php if ($hayRadar): ?>
            <div class="ingles-radar"><?= radar_svg($radar) ?></div>
        <?php else: ?>
            <p class="muted">Aparecerá cuando el alumno haga sus tareas de inglés.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Notas de las tareas completadas -->
<div class="card">
    <h3>Tareas completadas</h3>
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Tarea</th><th>Aciertos</th><th>Nota</th><th class="right">SkillCoins</th></tr></thead>
        <tbody>
        <?php foreach ($resultados as $r): ?>
            <tr>
                <td><?= e(date('d/m/Y H:i', strtotime($r['fecha']))) ?></td>
                <td><?= e($r['tarea_titulo'] ?? 'Práctica libre') ?></td>
                <td><?= (int)$r['aciertos'] ?> / <?= (int)$r['total'] ?></td>
                <td><?= nota_txt(nota_sobre_10((int)$r['aciertos'], (int)$r['total'])) ?>/10</td>
                <td class="right pos">+<?= (int)$r['coins'] ?> 🪙</td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$resultados): ?><tr><td colspan="5" class="muted">Aún no ha completado ninguna tarea.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php else: /* ---------- Pestaña Ajustes ---------- */ ?>
<?php
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
?>
<div class="grid-2">
    <!-- Perfil -->
    <div class="card">
        <h3>Perfil y ajustes</h3>
        <form method="post" class="form">
            <input type="hidden" name="accion" value="perfil">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
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
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
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
                        <input type="hidden" name="alumno_id" value="<?= $aid ?>">
                        <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                        <button class="btn-x" title="Eliminar">✕</button></form>
                </li>
            <?php endforeach; ?>
            <?php if (!$objetivos): ?><li class="muted">Sin objetivos.</li><?php endif; ?>
        </ul>
        <form method="post" class="form">
            <input type="hidden" name="accion" value="add_objetivo">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
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
                        <input type="hidden" name="alumno_id" value="<?= $aid ?>">
                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                        <button class="btn-x">✕</button></form>
                </li>
            <?php endforeach; ?>
            <?php if (!$habitos): ?><li class="muted">Sin hábitos.</li><?php endif; ?>
        </ul>
        <form method="post" class="form-inline">
            <input type="hidden" name="accion" value="add_habito">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
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
                        <input type="hidden" name="alumno_id" value="<?= $aid ?>">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn-x">✕</button></form>
                </li>
            <?php endforeach; ?>
            <?php if (!$recompensas): ?><li class="muted">Sin recompensas.</li><?php endif; ?>
        </ul>
        <form method="post" class="form-inline">
            <input type="hidden" name="accion" value="add_recompensa">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
            <input type="text" name="titulo" placeholder="Premio" required>
            <input type="number" name="costo" placeholder="Coste 🪙" required style="max-width:120px">
            <button class="btn-sm">Añadir</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php render_footer(); ?>
