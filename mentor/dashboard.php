<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_login(['mentor']);
$mid = (int) $user['id'];

// Marcar notificación como leída
if (($_GET['leer'] ?? '') !== '') {
    db()->prepare('UPDATE notificaciones SET leida = 1 WHERE id = ? AND mentor_id = ?')
        ->execute([(int) $_GET['leer'], $mid]);
    redirect('mentor/dashboard.php');
}

$alumnos = db()->prepare(
    'SELECT a.*, u.nombre, u.username FROM alumnos a
     JOIN usuarios u ON u.id = a.usuario_id
     WHERE a.mentor_id = ? ORDER BY u.nombre'
);
$alumnos->execute([$mid]);
$alumnos = $alumnos->fetchAll();

$notis = db()->prepare('SELECT n.*, u.nombre AS alumno_nombre FROM notificaciones n
     LEFT JOIN alumnos a ON a.id = n.alumno_id
     LEFT JOIN usuarios u ON u.id = a.usuario_id
     WHERE n.mentor_id = ? ORDER BY n.leida, n.creada_en DESC');
$notis->execute([$mid]);
$notis = $notis->fetchAll();

$nav = [
    ['Dashboard', 'mentor/dashboard.php', true],
    ['Zona académica', 'mentor/academia.php', false],
    ['Inglés', 'mentor/ingles.php', false],
];
render_header('Panel del mentor', 'panel', '', $nav);
?>
<div class="page-head">
    <h1>Hola, <?= e($user['nombre']) ?></h1>
    <p class="muted">Tienes <?= count($alumnos) ?> alumno(s) a tu cargo.</p>
</div>

<div class="grid-main">
    <section>
        <h3 class="section-title">Tus alumnos</h3>
        <div class="alumno-cards">
            <?php foreach ($alumnos as $a):
                $aid = (int) $a['id'];
                $nivel = nivel_actual($a);
                $notaMedia = nota_media_ingles($aid);
                $racha = racha_dias($aid);
                $tareas = ingles_tareas_de($aid);
                $pendientes = count(array_filter($tareas, fn($t) => $t['resultado_id'] === null));
                $ultima = $a['ultima_conexion'] ? date('d/m/Y H:i', strtotime($a['ultima_conexion'])) : 'Nunca';
                $dias = $a['ultima_conexion'] ? (int)((time() - strtotime($a['ultima_conexion'])) / 86400) : null;
            ?>
            <a class="alumno-card" href="<?= url('mentor/alumno.php?id=' . $aid) ?>">
                <div class="ac-avatar"><?= avatar_emoji($a['avatar']) ?></div>
                <div class="ac-body">
                    <div class="ac-name"><?= e($a['nombre']) ?> <span class="muted">· <?= (int)$a['edad'] ?> años</span></div>
                    <div class="ac-tags">
                        <span class="tag nivel"><?= e($nivel['nombre']) ?></span>
                        <span class="tag gold"><?= sc_total($aid) ?> 🪙</span>
                        <?php if ($racha > 0): ?><span class="tag">🔥 <?= $racha ?></span><?php endif; ?>
                    </div>
                    <div class="ac-nota">
                        <?php if ($notaMedia !== null): ?>
                            Nota media: <strong><?= nota_txt($notaMedia) ?></strong>/10
                        <?php else: ?>
                            <span class="muted">Sin tareas completadas aún</span>
                        <?php endif; ?>
                    </div>
                    <div class="ac-meta">
                        <span><?= $pendientes ?> tarea(s) de inglés pendiente(s)<?= $pendientes > 0 ? ' ⬜' : ' ✅' ?></span>
                        <span>Esta semana: <?= sc_semana($aid) ?> 🪙</span>
                        <span>Última conexión: <?= e($ultima) ?><?= $dias !== null && $dias >= 5 ? ' ⚠️' : '' ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
            <?php if (!$alumnos): ?><p class="muted">El administrador aún no te ha asignado alumnos.</p><?php endif; ?>
        </div>
    </section>

    <aside>
        <h3 class="section-title">Notificaciones</h3>
        <div class="noti-list">
            <?php foreach ($notis as $n): ?>
                <div class="noti <?= $n['leida'] ? 'leida' : '' ?>">
                    <p><?= e($n['mensaje']) ?></p>
                    <div class="noti-foot">
                        <small><?= e(date('d/m H:i', strtotime($n['creada_en']))) ?></small>
                        <?php if (!$n['leida']): ?>
                            <a href="<?= url('mentor/dashboard.php?leer=' . (int)$n['id']) ?>">Marcar leída</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$notis): ?><p class="muted">Sin notificaciones.</p><?php endif; ?>
        </div>
    </aside>
</div>
<?php render_footer(); ?>
