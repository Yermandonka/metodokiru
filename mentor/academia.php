<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_login(['mentor', 'admin']);

// Alumnos que puede ver
if ($user['rol'] === 'admin') {
    $alumnos = db()->query("SELECT a.id, u.nombre FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id ORDER BY u.nombre")->fetchAll();
} else {
    $st = db()->prepare("SELECT a.id, u.nombre FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.mentor_id=? ORDER BY u.nombre");
    $st->execute([(int) $user['id']]);
    $alumnos = $st->fetchAll();
}

$aid = (int) ($_GET['id'] ?? $_POST['alumno_id'] ?? ($alumnos[0]['id'] ?? 0));
$generado = '';
$errorIA = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'toggle_materia') {
        db()->prepare('UPDATE materias SET activa = 1 - activa WHERE id=? AND alumno_id=?')
            ->execute([(int) $_POST['materia_id'], $aid]);
        redirect('mentor/academia.php?id=' . $aid);
    } elseif ($accion === 'add_materia') {
        db()->prepare('INSERT INTO materias (alumno_id, nombre, activa) VALUES (?,?,1)')
            ->execute([$aid, trim($_POST['nombre'])]);
        redirect('mentor/academia.php?id=' . $aid);
    } elseif ($accion === 'generar') {
        try {
            $edad = (int) (db()->query("SELECT edad FROM alumnos WHERE id=$aid")->fetchColumn() ?: 12);
            $generado = ai_generar(trim($_POST['tema']), $edad);
        } catch (RuntimeException $ex) {
            $errorIA = $ex->getMessage();
        }
    }
}

$materias = [];
if ($aid) {
    $st = db()->prepare('SELECT * FROM materias WHERE alumno_id=? ORDER BY nombre');
    $st->execute([$aid]);
    $materias = $st->fetchAll();
}

$nav = [['Dashboard', 'mentor/dashboard.php', false], ['Zona académica', 'mentor/academia.php', true]];
render_header('Zona académica', 'panel', '', $nav);
?>
<div class="page-head">
    <h1>Zona académica <span class="tag">Fase 2</span></h1>
    <p class="muted">Activa materias y genera contenido con IA adaptado a la edad del alumno.</p>
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

<?php if (!$aid): ?>
    <div class="card"><p class="muted">No hay alumnos disponibles.</p></div>
<?php else: ?>
<div class="grid-2">
    <div class="card">
        <h3>Materias</h3>
        <ul class="editlist">
            <?php foreach ($materias as $m): ?>
                <li>
                    <span><?= $m['activa'] ? '☑' : '☐' ?> <?= e($m['nombre']) ?></span>
                    <form method="post" class="inline">
                        <input type="hidden" name="accion" value="toggle_materia">
                        <input type="hidden" name="alumno_id" value="<?= $aid ?>">
                        <input type="hidden" name="materia_id" value="<?= (int)$m['id'] ?>">
                        <button class="btn-sm"><?= $m['activa'] ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if (!$materias): ?><li class="muted">Sin materias.</li><?php endif; ?>
        </ul>
        <form method="post" class="form-inline">
            <input type="hidden" name="accion" value="add_materia">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
            <input type="text" name="nombre" placeholder="Nueva materia" required>
            <button class="btn-sm">Añadir</button>
        </form>
    </div>

    <div class="card">
        <h3>Generar contenido con IA</h3>
        <form method="post" class="form">
            <input type="hidden" name="accion" value="generar">
            <input type="hidden" name="alumno_id" value="<?= $aid ?>">
            <label>Tema a repasar
                <input type="text" name="tema" placeholder="Ej: Repasar fracciones" required>
            </label>
            <button class="btn">Generar teoría y ejercicios</button>
        </form>
        <?php if (!GEMINI_API_KEY): ?>
            <p class="muted" style="margin-top:10px">💡 Para activar la IA (gratis con Gemini), añade tu clave en la variable <code>GEMINI_API_KEY</code> o en el archivo <code>config/gemini_key.txt</code>.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($errorIA): ?><div class="login-error"><?= e($errorIA) ?></div><?php endif; ?>
<?php if ($generado): ?>
<div class="card">
    <h3>Contenido generado</h3>
    <pre style="white-space:pre-wrap;font-family:inherit;line-height:1.6"><?= e($generado) ?></pre>
</div>
<?php endif; ?>
<?php endif; ?>
<?php render_footer(); ?>
