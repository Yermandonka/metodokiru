<?php
/**
 * Inglés (mentor): un único panel para crear tareas. Cada tarea se crea con
 * su título, su fecha de entrega y su PDF (obligatorio): los ejercicios se
 * generan a partir del contenido del PDF, en las 5 áreas evaluables (una
 * tarea por destreza). Debajo, el feedback del alumno: notas y radar.
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
        if ($accion === 'crear_tarea') {
            $titulo = post_texto('titulo', 80);
            if ($titulo === '') {
                throw new RuntimeException('La tarea necesita un título.');
            }
            // El PDF es obligatorio: los ejercicios se crean con su contenido.
            $f = $_FILES['pdf'] ?? null;
            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Sube el PDF con el contenido de la tarea (es obligatorio).');
            }
            if ($f['size'] > 8 * 1024 * 1024) {
                throw new RuntimeException('El PDF no puede superar los 8 MB.');
            }
            $bin = (string) file_get_contents($f['tmp_name']);
            if (!str_starts_with($bin, '%PDF')) {
                throw new RuntimeException('El archivo no parece un PDF válido.');
            }
            if (!GEMINI_API_KEY) {
                throw new RuntimeException('Para leer el PDF hace falta la clave de Gemini (variable GEMINI_API_KEY).');
            }

            // Ajustes de los ejercicios (se guardan para el alumno).
            ingles_config_guardar($aid, [
                'ejercicios'    => post_int('ejercicios', 4, 20, 10),
                'coins_acierto' => post_int('coins_acierto', 0, 5, 1),
                'tope_diario'   => post_int('tope_diario', 0, 500, 50),
                'tiempo_seg'    => post_int('tiempo_seg', 0, 120, 0),
                'temas'         => ingles_config($aid)['temas'],
            ]);

            // El PDF se lee una vez y queda ligado a la tarea.
            $contenido = ai_pdf_extraer(base64_encode($bin));
            db()->prepare('INSERT INTO ingles_material (alumno_id, titulo, contenido, activo) VALUES (?,?,?,1)')
                ->execute([$aid, $titulo, $contenido]);
            $materialId = (int) db()->lastInsertId();

            // La tarea se crea directamente en las 5 áreas: una por destreza.
            $fecha = fecha_o_null($_POST['fecha_entrega'] ?? null);
            $st = db()->prepare('INSERT INTO ingles_tareas (alumno_id, titulo, destreza, fecha_entrega, material_id) VALUES (?,?,?,?,?)');
            foreach (ingles_destrezas() as $clave => $nombre) {
                // El nombre lleva un emoji delante («📚 Vocabulario»): se quita para el título.
                $limpio = trim(preg_replace('/^[^\p{L}]+/u', '', $nombre));
                $st->execute([$aid, $titulo . ' · ' . $limpio, $clave, $fecha, $materialId]);
            }
            flash('Tarea «' . $titulo . '» creada en las ' . count(ingles_destrezas()) . ' áreas con el contenido del PDF.');
            redirect('mentor/ingles.php?id=' . $aid);

        } elseif ($accion === 'del_tarea') {
            $tid = post_int('tarea_id', 1);
            $st = db()->prepare('SELECT material_id FROM ingles_tareas WHERE id=? AND alumno_id=?');
            $st->execute([$tid, $aid]);
            $materialId = (int) $st->fetchColumn();
            db()->prepare('DELETE FROM ingles_tareas WHERE id=? AND alumno_id=?')->execute([$tid, $aid]);
            // Si ninguna otra tarea usa ese PDF, se limpia también su contenido.
            if ($materialId) {
                $st = db()->prepare('SELECT COUNT(*) FROM ingles_tareas WHERE material_id=?');
                $st->execute([$materialId]);
                if (!$st->fetchColumn()) {
                    db()->prepare('DELETE FROM ingles_material WHERE id=? AND alumno_id=?')->execute([$materialId, $aid]);
                }
            }
            redirect('mentor/ingles.php?id=' . $aid);
        }
    } catch (RuntimeException $ex) {
        $errores[] = $ex->getMessage();
    }
}

/* ---------- Datos ---------- */
$cfg = $alumno ? ingles_config($aid) : null;

$resultados = [];
$tareas = [];
$precisionMedia = null;
$radar = [];
$destrezas = ingles_destrezas();

if ($alumno) {
    $tareas = ingles_tareas_de($aid);

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
    <p class="muted">Crea una tarea con su PDF y se genera en las 5 áreas evaluables. Debajo, las notas y el radar del alumno.</p>
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

<!-- Tareas: lista + creación, todo en un único panel -->
<div class="card">
    <h3>Tareas de inglés</h3>
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

    <hr>
    <h4>Nueva tarea</h4>
    <p class="muted">Sube el PDF con el contenido (apuntes, vocabulario del cole…): los ejercicios se
        generan a partir de él y la tarea se crea directamente en las <?= count($destrezas) ?> áreas
        (vocabulario, gramática, comprensión, escucha y escritura).</p>
    <form method="post" enctype="multipart/form-data" class="form" id="formNuevaTarea">
        <input type="hidden" name="accion" value="crear_tarea">
        <input type="hidden" name="alumno_id" value="<?= $aid ?>">
        <div class="row">
            <label>Título <input type="text" name="titulo" placeholder="Ej: Unidad 4 — La comida" required></label>
            <label>Fecha de entrega <input type="date" name="fecha_entrega"></label>
        </div>
        <label>Archivo PDF (obligatorio, máx. 8 MB)
            <input type="file" name="pdf" accept="application/pdf,.pdf" required>
        </label>
        <details class="ajustes-plegable">
            <summary>Ajustes de los ejercicios (opcional)</summary>
            <div class="row">
                <label>Ejercicios por tarea
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
        </details>
        <button class="btn">Crear tarea en las <?= count($destrezas) ?> áreas</button>
    </form>
    <?php if (!GEMINI_API_KEY): ?>
        <p class="muted" style="margin-top:10px">💡 Para leer los PDF hace falta la clave de Gemini en <code>GEMINI_API_KEY</code>.</p>
    <?php endif; ?>
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

<script>
/* Barra de progreso al crear la tarea: leer el PDF con la IA tarda unos
   segundos y así el mentor ve cuánto lleva. La estimación se afina con la
   duración real de la última creación (guardada en localStorage). */
(function () {
    var form = document.getElementById('formNuevaTarea');
    if (!form) return;

    // Si venimos de una creación, guarda cuánto tardó para la próxima barra.
    var t0 = parseInt(localStorage.getItem('kiruTareaT0') || '', 10);
    if (t0) {
        localStorage.removeItem('kiruTareaT0');
        var ms = Date.now() - t0;
        if (ms > 1000 && ms < 120000) localStorage.setItem('kiruTareaCargaMs', String(ms));
    }

    form.addEventListener('submit', function () {
        var btn = form.querySelector('button.btn');
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        btn.hidden = true;

        var caja = document.createElement('div');
        caja.className = 'ingles-carga';
        caja.innerHTML = '<div class="ingles-carga-barra"><div class="ingles-carga-fill"></div></div>'
            + '<p class="ingles-carga-txt">Leyendo el PDF y creando la tarea en las 5 áreas… ⏳</p>';
        btn.parentNode.insertBefore(caja, btn);
        var fill = caja.querySelector('.ingles-carga-fill');

        var estimado = parseInt(localStorage.getItem('kiruTareaCargaMs') || '', 10) || 15000;
        var inicio = Date.now();
        localStorage.setItem('kiruTareaT0', String(inicio));
        // Avanza rápido al principio y se frena rozando el 96 % hasta que
        // el servidor termina y la página cambia sola.
        (function paso() {
            var f = (Date.now() - inicio) / estimado;
            fill.style.width = (96 * (1 - Math.exp(-3 * f))).toFixed(1) + '%';
            requestAnimationFrame(paso);
        })();
    });
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
