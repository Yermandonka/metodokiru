<?php
/**
 * Inglés (mentor): configura el juego de cada alumno (cantidad, SkillCoins,
 * tiempo, temas), sube PDFs para generar ejercicios con su contenido y ve
 * el feedback del alumno: lecciones jugadas, precisión por tipo y fallos comunes.
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
        if ($accion === 'config') {
            ingles_config_guardar($aid, [
                'ejercicios'    => post_int('ejercicios', 4, 20, 10),
                'coins_acierto' => post_int('coins_acierto', 0, 5, 1),
                'tope_diario'   => post_int('tope_diario', 0, 500, 50),
                'tiempo_seg'    => post_int('tiempo_seg', 0, 120, 0),
                'temas'         => post_texto('temas', 255),
            ]);
            flash('Configuración del juego guardada.');
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
$porTipo = [];
$topFallos = [];
$precisionMedia = null;

if ($alumno) {
    $st = db()->prepare('SELECT * FROM ingles_material WHERE alumno_id=? ORDER BY id DESC');
    $st->execute([$aid]);
    $materiales = $st->fetchAll();

    $st = db()->prepare('SELECT * FROM ingles_resultados WHERE alumno_id=? ORDER BY fecha DESC LIMIT 30');
    $st->execute([$aid]);
    $resultados = $st->fetchAll();

    // Patrones: precisión por tipo de ejercicio y fallos más repetidos.
    $fallos = [];
    $sumAciertos = 0;
    $sumTotal = 0;
    foreach ($resultados as $r) {
        $sumAciertos += (int) $r['aciertos'];
        $sumTotal    += (int) $r['total'];
        foreach ((array) json_decode((string) $r['detalle'], true) as $d) {
            if (!is_array($d) || empty($d['tipo'])) {
                continue;
            }
            $t = $d['tipo'];
            $porTipo[$t] ??= ['ok' => 0, 'total' => 0];
            $porTipo[$t]['total']++;
            if (!empty($d['ok'])) {
                $porTipo[$t]['ok']++;
            } elseif (($d['clave'] ?? '') !== '') {
                $fallos[$d['clave']] = ($fallos[$d['clave']] ?? 0) + 1;
            }
        }
    }
    uasort($porTipo, fn($a, $b) => ($a['ok'] / max(1, $a['total'])) <=> ($b['ok'] / max(1, $b['total'])));
    arsort($fallos);
    $topFallos = array_slice($fallos, 0, 8, true);
    $precisionMedia = $sumTotal ? round($sumAciertos / $sumTotal * 100) : null;
}

$tipoNombre = [
    'eleccion' => '🎯 Traducir', 'hueco' => '✏️ Completar', 'ordenar' => '🧩 Ordenar',
    'parejas' => '🔗 Parejas', 'escucha' => '👂 Escuchar', 'verdadero' => '🤔 V/F',
    'escribir' => '⌨️ Escribir', 'emoji' => '🖼️ Emoji',
];

$nav = [
    ['Dashboard', 'mentor/dashboard.php', false],
    ['Zona académica', 'mentor/academia.php', false],
    ['Inglés', 'mentor/ingles.php', true],
];
render_header('Inglés', 'panel', '', $nav);
?>
<div class="page-head">
    <h1>Inglés del alumno 🇬🇧</h1>
    <p class="muted">Configura los mini-juegos, sube PDFs con contenido propio y revisa cómo va el alumno.</p>
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

<div class="grid-2">
    <!-- Configuración del juego -->
    <div class="card">
        <h3>Configuración del juego</h3>
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
    <div class="stat-card"><span class="stat-num"><?= count($resultados) ?></span><span>Lecciones (últimas 30)</span></div>
    <div class="stat-card"><span class="stat-num"><?= $precisionMedia !== null ? $precisionMedia . '%' : '—' ?></span><span>Precisión media</span></div>
    <div class="stat-card"><span class="stat-num"><?= array_sum(array_column($resultados, 'coins')) ?></span><span>SkillCoins ganadas</span></div>
    <div class="stat-card"><span class="stat-num"><?= $resultados ? e(date('d/m', strtotime($resultados[0]['fecha']))) : '—' ?></span><span>Última lección</span></div>
</div>

<div class="grid-2">
    <!-- Patrones por tipo -->
    <div class="card">
        <h3>Patrones por tipo de ejercicio</h3>
        <?php if (!$porTipo): ?>
            <p class="muted">Todavía no hay datos: aparecerán cuando el alumno juegue.</p>
        <?php else: ?>
            <?php foreach ($porTipo as $t => $v): $pct = round($v['ok'] / max(1, $v['total']) * 100); ?>
                <div style="margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between">
                        <strong><?= e($tipoNombre[$t] ?? $t) ?></strong>
                        <span class="muted"><?= $v['ok'] ?>/<?= $v['total'] ?> · <?= $pct ?>%</span>
                    </div>
                    <div class="progress"><div class="progress-fill <?= $pct < 60 ? '' : 'gold' ?>" style="width:<?= $pct ?>%"></div></div>
                </div>
            <?php endforeach; ?>
            <p class="muted">Los tipos con menos precisión salen primero: son los que conviene reforzar.</p>
        <?php endif; ?>
    </div>

    <!-- Fallos más repetidos -->
    <div class="card">
        <h3>Fallos más repetidos</h3>
        <?php if (!$topFallos): ?>
            <p class="muted">Sin fallos registrados por ahora. 🎉</p>
        <?php else: ?>
            <ul class="editlist">
                <?php foreach ($topFallos as $clave => $n): ?>
                    <li><span><?= e($clave) ?></span><span class="tag"><?= $n ?> <?= $n === 1 ? 'fallo' : 'fallos' ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Últimas lecciones -->
<div class="card">
    <h3>Últimas lecciones</h3>
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Aciertos</th><th>Precisión</th><th class="right">SkillCoins</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($resultados, 0, 15) as $r): $pct = $r['total'] ? round($r['aciertos'] / $r['total'] * 100) : 0; ?>
            <tr>
                <td><?= e(date('d/m/Y H:i', strtotime($r['fecha']))) ?></td>
                <td><?= (int)$r['aciertos'] ?> / <?= (int)$r['total'] ?></td>
                <td><?= $pct ?>%</td>
                <td class="right pos">+<?= (int)$r['coins'] ?> 🪙</td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$resultados): ?><tr><td colspan="4" class="muted">El alumno aún no ha jugado ninguna lección.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>
<?php render_footer(); ?>
