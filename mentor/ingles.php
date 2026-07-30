<?php
/**
 * Inglés (mentor): configura el juego de cada alumno (cantidad, SkillCoins,
 * tiempo, temas), sube PDFs para generar ejercicios con su contenido y ve
 * el feedback del alumno: lecciones jugadas, radar de destrezas y fallos comunes.
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
$topFallos = [];
$precisionMedia = null;

/* Radar de destrezas: cada tipo de reto puntúa en una o dos destrezas del
   idioma. Las lecciones recientes pesan más, así el radar refleja el nivel
   actual y se mueve con cada lección que juega el alumno. */
$DESTREZAS = [
    'vocabulario' => '📚 Vocabulario',
    'gramatica'   => '🧩 Gramática',
    'lectura'     => '🧠 Comprensión',
    'escucha'     => '👂 Escucha',
    'escritura'   => '⌨️ Escritura',
];
$TIPO_DESTREZA = [
    'eleccion'  => ['vocabulario' => 1.0, 'lectura' => 0.5],
    'parejas'   => ['vocabulario' => 1.0],
    'emoji'     => ['vocabulario' => 1.0],
    'hueco'     => ['gramatica' => 1.0, 'lectura' => 0.5],
    'ordenar'   => ['gramatica' => 1.0, 'escritura' => 0.5],
    'verdadero' => ['lectura' => 1.0],
    'escucha'   => ['escucha' => 1.0],
    'escribir'  => ['escritura' => 1.0, 'vocabulario' => 0.5],
];
$radar = [];
foreach ($DESTREZAS as $k => $nombre) {
    $radar[$k] = ['nombre' => $nombre, 'ok' => 0.0, 'peso' => 0.0, 'n' => 0, 'pct' => null];
}

if ($alumno) {
    $st = db()->prepare('SELECT * FROM ingles_material WHERE alumno_id=? ORDER BY id DESC');
    $st->execute([$aid]);
    $materiales = $st->fetchAll();

    $st = db()->prepare('SELECT * FROM ingles_resultados WHERE alumno_id=? ORDER BY fecha DESC LIMIT 30');
    $st->execute([$aid]);
    $resultados = $st->fetchAll();

    // Patrones: destrezas del radar y fallos más repetidos.
    $fallos = [];
    $sumAciertos = 0;
    $sumTotal = 0;
    foreach ($resultados as $i => $r) {
        $sumAciertos += (int) $r['aciertos'];
        $sumTotal    += (int) $r['total'];
        $reciente = pow(0.93, $i); // $resultados va de la más nueva a la más vieja
        foreach ((array) json_decode((string) $r['detalle'], true) as $d) {
            if (!is_array($d) || empty($d['tipo'])) {
                continue;
            }
            foreach ($TIPO_DESTREZA[$d['tipo']] ?? [] as $dk => $w) {
                $radar[$dk]['peso'] += $w * $reciente;
                $radar[$dk]['n']++;
                if (!empty($d['ok'])) {
                    $radar[$dk]['ok'] += $w * $reciente;
                }
            }
            if (empty($d['ok']) && ($d['clave'] ?? '') !== '') {
                $fallos[$d['clave']] = ($fallos[$d['clave']] ?? 0) + 1;
            }
        }
    }
    foreach ($radar as $k => $v) {
        if ($v['peso'] > 0) {
            $radar[$k]['pct'] = (int) round($v['ok'] / $v['peso'] * 100);
        }
    }
    arsort($fallos);
    $topFallos = array_slice($fallos, 0, 8, true);
    $precisionMedia = $sumTotal ? round($sumAciertos / $sumTotal * 100) : null;
}
$hayRadar = (bool) array_filter($radar, fn($v) => $v['pct'] !== null);

/** Dibuja el radar (SVG) con un eje por destreza; pct null = sin datos aún. */
function radar_svg(array $ejes): string
{
    $n = count($ejes);
    $cx = 240.0; $cy = 178.0; $R = 104.0;
    $ang = fn(int $i) => -M_PI / 2 + $i * 2 * M_PI / $n;
    $punto = function (int $i, float $r) use ($ang, $cx, $cy) {
        return [round($cx + $r * cos($ang($i)), 1), round($cy + $r * sin($ang($i)), 1)];
    };
    $poligono = function (float $r) use ($n, $punto) {
        $p = [];
        for ($i = 0; $i < $n; $i++) { [$x, $y] = $punto($i, $r); $p[] = "$x,$y"; }
        return implode(' ', $p);
    };

    $s = '<svg viewBox="0 0 480 336" role="img" aria-label="Radar de destrezas de inglés">';
    // Rejilla: anillos al 25/50/75/100 % y radios hasta cada vértice.
    foreach ([.25, .5, .75, 1] as $f) {
        $s .= '<polygon points="' . $poligono($R * $f) . '" fill="none" stroke="var(--linea)" stroke-width="1"/>';
    }
    for ($i = 0; $i < $n; $i++) {
        [$x, $y] = $punto($i, $R);
        $s .= "<line x1=\"$cx\" y1=\"$cy\" x2=\"$x\" y2=\"$y\" stroke=\"var(--linea)\" stroke-width=\"1\"/>";
    }
    foreach ([25, 50, 75, 100] as $v) {
        $s .= '<text x="' . ($cx + 5) . '" y="' . round($cy - $R * $v / 100 + 4, 1) . '" font-size="9" fill="var(--muted)">' . $v . '</text>';
    }
    // Polígono del alumno.
    $p = [];
    foreach (array_values($ejes) as $i => $e) {
        [$x, $y] = $punto($i, $R * max(0, min(100, (int) ($e['pct'] ?? 0))) / 100);
        $p[] = "$x,$y";
    }
    $s .= '<polygon points="' . implode(' ', $p) . '" fill="#7c8a5a" fill-opacity=".14" stroke="#7c8a5a" stroke-width="2" stroke-linejoin="round"/>';
    // Puntos (huecos si esa destreza aún no tiene datos) y etiquetas.
    foreach (array_values($ejes) as $i => $e) {
        $con = $e['pct'] !== null;
        [$x, $y] = $punto($i, $R * ($con ? max(0, min(100, (int) $e['pct'])) : 0) / 100);
        $s .= $con
            ? "<circle cx=\"$x\" cy=\"$y\" r=\"4.5\" fill=\"#7c8a5a\" stroke=\"var(--crema2)\" stroke-width=\"2\"/>"
            : "<circle cx=\"$x\" cy=\"$y\" r=\"4\" fill=\"var(--crema2)\" stroke=\"var(--muted)\" stroke-width=\"1.5\"/>";
        $cos = cos($ang($i)); $sin = sin($ang($i));
        [$lx, $ly] = $punto($i, $R + 22);
        $anchor = $cos > .15 ? 'start' : ($cos < -.15 ? 'end' : 'middle');
        $sube = $sin < -.3 ? -12 : ($sin > .3 ? 12 : 0); // separa la etiqueta del vértice
        $s .= '<text x="' . $lx . '" y="' . round($ly + $sube, 1) . '" text-anchor="' . $anchor . '" font-size="13" font-weight="700" fill="var(--texto)">' . e($e['nombre']) . '</text>';
        $s .= '<text x="' . $lx . '" y="' . round($ly + $sube + 15, 1) . '" text-anchor="' . $anchor . '" font-size="12" fill="var(--muted)">'
            . ($con ? (int) $e['pct'] . ' %' : 'sin datos') . '</text>';
    }
    return $s . '</svg>';
}

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
    <!-- Radar de destrezas -->
    <div class="card">
        <h3>Radar de destrezas</h3>
        <?php if (!$hayRadar): ?>
            <p class="muted">Todavía no hay datos: el radar aparecerá cuando el alumno juegue.</p>
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
            <p class="muted">Cuanto más lejos del centro, más fuerte esa destreza. Las lecciones
                recientes pesan más, así el radar refleja el nivel actual y se actualiza con cada
                lección. Las zonas cercanas al centro son las que conviene reforzar.</p>
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
