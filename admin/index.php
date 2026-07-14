<?php
require_once __DIR__ . '/_common.php';
$user = require_login(['admin']);

$nAlumnos  = (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE rol='alumno'")->fetchColumn();
$nMentores = (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE rol='mentor'")->fetchColumn();
$nSC       = (int) db()->query("SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log")->fetchColumn();

$alumnos = db()->query(
    "SELECT a.*, u.nombre, u.username, m.nombre AS mentor_nombre
     FROM alumnos a
     JOIN usuarios u ON u.id = a.usuario_id
     LEFT JOIN usuarios m ON m.id = a.mentor_id
     ORDER BY u.nombre"
)->fetchAll();

render_header('Administración', 'panel', '', nav_admin('index'));
?>
<div class="page-head"><h1>Resumen de la plataforma</h1></div>

<div class="stat-row">
    <div class="stat-card"><span class="stat-num"><?= $nAlumnos ?></span><span>Alumnos</span></div>
    <div class="stat-card"><span class="stat-num"><?= $nMentores ?></span><span>Mentores</span></div>
    <div class="stat-card"><span class="stat-num"><?= $nSC ?></span><span>SkillCoins totales</span></div>
</div>

<div class="card">
    <div class="card-head"><h3>Todos los alumnos</h3><a class="btn-sm" href="<?= url('admin/usuarios.php') ?>">Gestionar usuarios</a></div>
    <table class="tabla">
        <thead><tr><th>Alumno</th><th>Usuario</th><th>Edad</th><th>Mentor</th><th>Nivel</th><th class="right">SkillCoins</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($alumnos as $a):
            $nivel = nivel_actual($a); ?>
            <tr>
                <td><?= e($a['nombre']) ?></td>
                <td class="muted">@<?= e($a['username']) ?></td>
                <td><?= (int)$a['edad'] ?></td>
                <td><?= e($a['mentor_nombre'] ?? '— sin asignar —') ?></td>
                <td><span class="tag nivel"><?= e($nivel['nombre']) ?></span></td>
                <td class="right"><?= sc_total((int)$a['id']) ?> 🪙</td>
                <td class="right"><a class="link" href="<?= url('mentor/alumno.php?id=' . (int)$a['id']) ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$alumnos): ?><tr><td colspan="7" class="muted">No hay alumnos. Créalos en «Usuarios».</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php render_footer(); ?>
