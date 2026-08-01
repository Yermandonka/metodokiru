<?php
/**
 * Inglés (mentor): crea las tareas de cada alumno (una por destreza, con título
 * y fecha de entrega), configura los ejercicios (cantidad, SkillCoins, tiempo,
 * temas), sube PDFs para generar contenido propio y ve el feedback del alumno:
 * tareas completadas con su nota y radar de destrezas.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_login(['mentor', 'admin']);
ingles_tablas();

// Alumnos que puede ver
if ($user['rol'] === 'admin') {
    $alumnos = db()->query("SELECT a.id, u.nombre FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id ORDER BY u.nombre")->fetchAll();
} else {
    $st = db()->prepare("SELECT a.id, u.nombre FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.mentor_id=? ORDER BY u.nombre");
    $st->execute([(int) $user['id']]);
    $alumnos = $st->fetchAll();
}

$aid = (int) ($_GET['id'] ?? $_POST['alumno_id'] ?? ($alumnos[0]['id'] ?? 0));
$alumno = $aid ? alumno_por_id($aid) : null;
// Un mentor solo puede tocar a sus alumnos; el admin, a todos.
if ($alumno && $user['rol'] === 'mentor' && (int) $alumno['mentor_id'] !== (int) $user['id']) {
    http_response_code(403);
    die('Este alumno no está asignado a ti.');
}

$errores = [];

/* ---------- Acciones ---------- */
if ($alumno && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'add_tarea') {
            $titulo = post_texto('titulo', 100);
            if ($titulo === '') {
                throw new RuntimeException('La tarea necesita un título.');
            }
            $destreza = post_opcion('destreza', array_keys(ingles_destrezas()), 'vocabulario');
            db()->prepare('INSERT INTO ingles_tareas (alumno_id, titulo, destreza, fecha_entrega) VALUES (?,?,?,?)')
                ->execute([$aid, $titulo, $destreza, fecha_o_null($_POST['fecha_entrega'] ?? null)]);
            flash('Tarea «' . $titulo . '» creada.');
            redirect('mentor/ingles.php?id=' . $aid);

        } elseif ($accion === 'add_tareas_todas') {
            // Una tarea por cada destreza evaluable, con la misma fecha de entrega.
            $fecha = fecha_o_null($_POST['fecha_entrega'] ?? null);
            $st = db()->prepare('INSERT INTO ingles_tareas (alumno_id, titulo, destreza, fecha_entrega) VALUES (?,?,?,?)');
            foreach (ingles_destrezas() as $clave => $nombre) {
                // El nombre lleva un emoji delante («📚 Vocabulario»): se quita para el título.
                $limpio = trim(preg_replace('/^[^\p{L}]+/u', '', $nombre));
                $st->execute([$aid, 'Tarea de ' . mb_strtolower($limpio), $clave, $fecha]);
            }
            flash('Creadas ' . count(ingles_destrezas()) . ' tareas, una por destreza.');
            redirect('mentor/ingles.php?id=' . $aid);

        } elseif ($accion === 'del_tarea') {
            db()->prepare('DELETE FROM ingles_tareas WHERE id=? AND alumno_id=?')
                ->execute([post_int('tarea_id', 1), $aid]);
            redirect('mentor/ingles.php?id=' . $aid);

        } elseif ($accion === 'config') {
            ingles_config_guardar($aid, [
                'ejercicios'    => post_int('ejercicios', 4, 20, 10),
                'coins_acierto' => post_int('coins_acierto', 0, 5, 1),
                'tope_diario'   => post_int('tope_diario', 0, 500, 50),
                'tiempo_seg'    => post_int('tiempo_seg', 0, 120, 0),
                'temas'         => post_texto('temas', 255),
            ]);
            flash('Configuración de los ejercicios guardada.');
            redirect('mentor/ingles.php?id=' . $aid);

        } elseif ($accion === 'subir_pdf') {
            $f = $_FILES['pdf'] ?? null;
            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Elige un archivo PDF.');
            }
            if ($f['size'] > 8 * 1024 * 1024) {
                throw new RuntimeException('El PDF no puede superar los 8 MB.');
            }
            $bin = (string) file_get_contents($f['tmp_name']);
            if (!str_starts_with($bin, '%PDF')) {
                throw new RuntimeException('El archivo no parece un PDF válido.');
            }
            if (!GEMINI_API_KEY) {
                throw new RuntimeException('Para leer PDFs hace falta la clave de Gemini (variable GEMINI_API_KEY).');
            }
            $titulo = post_texto('titulo', 100)
                ?: limpiar_texto(pathinfo($f['name'] ?? 'Material', PATHINFO_FILENAME), 100)
                ?: 'Material';
            $contenido = ai_pdf_extraer(base64_encode($bin));
            db()->prepare('INSERT INTO ingles_material (alumno_id, titulo, contenido, activo) VALUES (?,?,?,1)')
                ->execute([$aid, $titulo, $contenido]);
            flash('Material «' . $titulo . '» listo: las próximas lecciones usarán su contenido.');
            redirect('mentor/ingles.php?id=' . $aid);

        } elseif ($accion === 'toggle_material') {
            db()->prepare('UPDATE ingles_material SET activo = 1 - activo WHERE id=? AND alumno_id=?')
                ->execute([post_int('material_id', 1), $aid]);
            redirect('mentor/ingles.php?id=' . $aid);

        } elseif ($accion === 'del_material') {
            db()->prepare('DELETE FROM ingles_material WHERE id=? AND alumno_id=?')
                ->execute([post_int('material_id', 1), $aid]);
            redirect('mentor/ingles.php?id=' . $aid);
        }
    } catch (RuntimeException $ex) {
        $errores[] = $ex->getMessage();
    }
}

/* ---------- Datos ---------- */
$cfg = $alumno ? ingles_config($aid) : null;

$materiales = [];
$resultados = [];
$tareas = [];
$precisionMedia = null;
$radar = [];
$destrezas = ingles_destrezas();

if ($alumno) {
    $tareas = ingles_tareas_de($aid);

    $st = db()->prepare('SELECT * FROM ingles_material WHERE alumno_id=? ORDER BY id DESC');
    $st->execute([$aid]);
    $materiales = $st->fetchAll();

    $st = db()->prepare('SELECT r.*, t.titulo AS tarea_titulo FROM ingles_resultados r
                         LEFT JOIN ingles_tareas t ON t.id = r.tarea_id
                         WHERE r.alumno_id=? ORDER BY r.fecha DESC LIMIT 30');
    $st->execute([$aid]);
    $resultados = $st->fetchAll();

    $radar = ingles_radar($aid);

    $sumAciertos = array_sum(array_map(fn($r) => (int) $r['aciertos'], $resultados));
    $sumTotal    = array_sum(array_map(fn($r) => (int) $r['total'], $resultados));
    $precisionMedia = $sumTotal ? round($sumAciertos / $sumTotal * 100) : null;
}
$hayRadar = (bool) array_filter($radar, fn($v) => $v['pct'] !== null);

$nav = [
    ['Dashboard', 'mentor/dashboard.php', false],
    ['Zona académica', 'mentor/academia.php', false],
    ['Inglés', 'mentor/ingles.php', true],
];
render_header('Inglés', 'panel', '', $nav);
?>
<div class="page-head">
    <h1>Inglés del alumno 🇬🇧</h1>
    <p class="muted">Crea tareas (una por destreza), sube PDFs con contenido propio y revisa las notas del alumno.</p>
</div>

<form method="get" class="form-inline" style="margin-bottom:18px">
    <label style="margin:0">Alumno
        <select name="id" onchange="this.form.submit()">
            <?php foreach ($alumnos as $al): ?>
                <option value="<?= (int)$al['id'] ?>" <?= $aid === (int)$al['id'] ? 'selected' : '' ?>><?= e($al['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<?php foreach ($errores as $er): ?><div class="login-error"><?= e($er) ?></div><?php endforeach; ?>

<?php if (!$alumno): ?>
    <div class="card"><p class="muted">No hay alumnos disponibles.</p></div>
<?php else: ?>

<!-- Tareas del alumno -->
<div class="card">
    <h3>Tareas de inglés</h3>
    <p class="muted">El alumno ve estas tareas en su panel, con su fecha de entrega, y al hacerlas recibe su nota.</p>
    <ul class="editlist">
        <?php foreach ($tareas as $t):
            $hecha = $t['resultado_id'] !== null; ?>
            <li>
                <span><?= $hecha ? '✅' : '⬜' ?> <?= e($t['titulo']) ?>
                    <small class="muted">
                        (<?= e($destrezas[$t['destreza']] ?? $t['destreza']) ?><?=
                            $t['fecha_entrega'] ? ' · entrega ' . e(date('d/m/Y', strtotime($t['fecha_entrega']))) : '' ?>)
                    </small>
                    <?php if ($hecha): ?>
                        <span class="tag gold">Nota: <?= nota_txt(nota_sobre_10((int)$t['aciertos'], (int)$t['total'])) ?>/10</span>
                    <?php endif; ?>
                </span>
                <form method="post" class="inline"
                      onsubmit="return confirm('¿Eliminar la tarea «<?= e($t['titulo']) ?>»?')">
                    <input type="hidden" name="accion" value="del_tarea">
                    <input type="hidden" name="alumno_id" value="<?= $aid ?>">
                    <input type="hidden" name="tarea_id" value="<?= (int)$t['id'] ?>">
                    <button class="btn-x" title="Eliminar tarea">✕ Eliminar</button>
                </form>
            </li>
        <?php endforeach; ?>
        <?php if (!$tareas): ?><li class="muted">El alumno aún no tiene tareas.</li><?php endif; ?>
    </ul>

    <div class="grid-2" style="margin-top:14px">
        <form method="post" class="form">
            <input type="hidden" name="accion" value="add_tarea">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
            <label>Título <input type="text" name="titulo" placeholder="Ej: Vocabulario de la comida" required></label>
            <div class="row">
                <label>Destreza
                    <select name="destreza">
                        <?php foreach ($destrezas as $clave => $nombre): ?>
                            <option value="<?= $clave ?>"><?= e($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Fecha de entrega <input type="date" name="fecha_entrega"></label>
            </div>
            <button class="btn-sm">Añadir tarea</button>
        </form>
        <form method="post" class="form">
            <input type="hidden" name="accion" value="add_tareas_todas">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
            <label>Crear una tarea de cada destreza (<?= count($destrezas) ?>)
                <input type="date" name="fecha_entrega" title="Fecha de entrega común (opcional)">
            </label>
            <button class="btn-sm">Crear las <?= count($destrezas) ?> tareas</button>
        </form>
    </div>
</div>

<div class="grid-2">
    <!-- Configuración de los ejercicios -->
    <div class="card">
        <h3>Configuración de los ejercicios</h3>
        <form method="post" class="form">
            <input type="hidden" name="accion" value="config">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
            <div class="row">
                <label>Ejercicios por lección
                    <input type="number" name="ejercicios" value="<?= $cfg['ejercicios'] ?>" min="4" max="20">
                </label>
                <label>SkillCoins por acierto
                    <input type="number" name="coins_acierto" value="<?= $cfg['coins_acierto'] ?>" min="0" max="5">
                </label>
            </div>
            <div class="row">
                <label>Tope de SkillCoins al día
                    <input type="number" name="tope_diario" value="<?= $cfg['tope_diario'] ?>" min="0" max="500">
                </label>
                <label>Segundos por ejercicio (0 = sin límite)
                    <input type="number" name="tiempo_seg" value="<?= $cfg['tiempo_seg'] ?>" min="0" max="120">
                </label>
            </div>
            <label>Temas para la IA (separados por comas; vacío = variados)
                <input type="text" name="temas" value="<?= e($cfg['temas']) ?>" placeholder="Ej: los animales, la comida, el colegio">
            </label>
            <button class="btn">Guardar configuración</button>
        </form>
    </div>

    <!-- Material PDF -->
    <div class="card">
        <h3>Contenido desde PDF</h3>
        <p class="muted">Sube un PDF (apuntes, vocabulario del cole…) y las lecciones se generarán con su contenido.</p>
        <form method="post" enctype="multipart/form-data" class="form">
            <input type="hidden" name="accion" value="subir_pdf">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
            <label>Título (opcional)
                <input type="text" name="titulo" placeholder="Ej: Unidad 4 — La comida" maxlength="100">
            </label>
            <label>Archivo PDF (máx. 8 MB)
                <input type="file" name="pdf" accept="application/pdf,.pdf" required>
            </label>
            <button class="btn">Subir y preparar ejercicios</button>
        </form>
        <?php if (!GEMINI_API_KEY): ?>
            <p class="muted" style="margin-top:10px">💡 Para leer PDFs hace falta la clave de Gemini en <code>GEMINI_API_KEY</code>.</p>
        <?php endif; ?>

        <?php if ($materiales): ?>
        <hr>
        <ul class="editlist">
            <?php foreach ($materiales as $m): ?>
                <li>
                    <span><?= $m['activo'] ? '📗' : '📕' ?> <?= e($m['titulo']) ?>
                        <small class="muted"><?= e(date('d/m/Y', strtotime($m['creado_en']))) ?><?= $m['activo'] ? '' : ' · pausado' ?></small></span>
                    <span>
                        <form method="post" class="inline">
                            <input type="hidden" name="accion" value="toggle_material">
                            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
                            <input type="hidden" name="material_id" value="<?= (int)$m['id'] ?>">
                            <button class="btn-sm"><?= $m['activo'] ? 'Pausar' : 'Activar' ?></button>
                        </form>
                        <form method="post" class="inline">
                            <input type="hidden" name="accion" value="del_material">
                            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
                            <input type="hidden" name="material_id" value="<?= (int)$m['id'] ?>">
                            <button class="btn-x" title="Eliminar">✕</button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Feedback del alumno -->
<div class="stat-row">
    <div class="stat-card"><span class="stat-num"><?= count($resultados) ?></span><span>Tareas hechas (últimas 30)</span></div>
    <div class="stat-card"><span class="stat-num"><?= $precisionMedia !== null ? $precisionMedia . '%' : '—' ?></span><span>Precisión media</span></div>
    <div class="stat-card"><span class="stat-num"><?= array_sum(array_column($resultados, 'coins')) ?></span><span>SkillCoins ganadas</span></div>
    <div class="stat-card"><span class="stat-num"><?= $resultados ? e(date('d/m', strtotime($resultados[0]['fecha']))) : '—' ?></span><span>Última tarea</span></div>
</div>

<!-- Radar de destrezas -->
<div class="card">
    <h3>Radar de destrezas</h3>
    <?php if (!$hayRadar): ?>
        <p class="muted">Todavía no hay datos: el radar aparecerá cuando el alumno haga sus tareas.</p>
    <?php else: ?>
        <div class="ingles-radar"><?= radar_svg($radar) ?></div>
        <ul class="editlist radar-lista">
            <?php foreach ($radar as $v): ?>
                <li>
                    <span><?= e($v['nombre']) ?></span>
                    <span class="muted"><?= $v['pct'] !== null
                        ? $v['pct'] . ' % · ' . $v['n'] . ($v['n'] === 1 ? ' reto' : ' retos')
                        : 'sin datos' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- Últimas tareas completadas -->
<div class="card">
    <h3>Últimas tareas completadas</h3>
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Tarea</th><th>Aciertos</th><th>Nota</th><th class="right">SkillCoins</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($resultados, 0, 15) as $r): ?>
            <tr>
                <td><?= e(date('d/m/Y H:i', strtotime($r['fecha']))) ?></td>
                <td><?= e($r['tarea_titulo'] ?? 'Práctica libre') ?></td>
                <td><?= (int)$r['aciertos'] ?> / <?= (int)$r['total'] ?></td>
                <td><?= nota_txt(nota_sobre_10((int)$r['aciertos'], (int)$r['total'])) ?>/10</td>
                <td class="right pos">+<?= (int)$r['coins'] ?> 🪙</td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$resultados): ?><tr><td colspan="5" class="muted">El alumno aún no ha completado ninguna tarea.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>
<?php render_footer(); ?>
