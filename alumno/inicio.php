<?php
require_once __DIR__ . '/../includes/alumno_ctx.php';
require_once __DIR__ . '/../includes/layout.php';

$aid       = (int) $alumno['id'];
$total     = sc_total($aid);
$semana    = sc_semana($aid);
$racha     = racha_dias($aid);
$siguiente = nivel_siguiente($nivel);

// Mentor
$mentorNombre = '—';
if (!empty($alumno['mentor_id'])) {
    $st = db()->prepare('SELECT nombre FROM usuarios WHERE id = ?');
    $st->execute([$alumno['mentor_id']]);
    $mentorNombre = $st->fetchColumn() ?: '—';
}

// Objetivos de hoy
$obj = db()->prepare('SELECT * FROM objetivos WHERE alumno_id = ? ORDER BY completado, FIELD(prioridad,"alta","media","baja") LIMIT 5');
$obj->execute([$aid]);
$objetivos = $obj->fetchAll();

// Recompensa activa (para el recordatorio)
$rc = db()->prepare('SELECT * FROM recompensas WHERE alumno_id = ? AND activa = 1 AND conseguida = 0 ORDER BY id LIMIT 1');
$rc->execute([$aid]);
$recompensa = $rc->fetch();

// Progreso hacia el siguiente nivel
$nivelPct = 100;
$faltanNivel = 0;
if ($siguiente) {
    $base = (int) $nivel['skillcoins_necesarias'];
    $meta = (int) $siguiente['skillcoins_necesarias'];
    $rango = max(1, $meta - $base);
    $nivelPct = max(0, min(100, round((($total - $base) / $rango) * 100)));
    $faltanNivel = max(0, $meta - $total);
}

render_header('Inicio', 'alumno', $mundo, nav_alumno('index'));
?>
<div class="hero">
    <div class="hero-avatar">
        <div class="avatar avatar-<?= e($alumno['avatar']) ?>">🦊</div>
        <span class="nivel-badge"><?= e($nivel['nombre']) ?></span>
    </div>
    <div class="hero-info">
        <h1><?= e($alumno['nombre']) ?></h1>
        <div class="hero-meta">
            <span>Edad: <strong><?= e((string)$alumno['edad']) ?></strong></span>
            <span>Mentor: <strong><?= e($mentorNombre) ?></strong></span>
            <span>Mundo: <strong><?= e(ucfirst($mundo)) ?></strong></span>
        </div>
        <div class="coins-row">
            <div class="coin-box">
                <span class="coin-num">🪙 <?= $total ?></span>
                <span class="coin-lbl">SkillCoins totales</span>
            </div>
            <div class="coin-box">
                <span class="coin-num">✨ <?= $semana ?></span>
                <span class="coin-lbl">Esta semana</span>
            </div>
            <div class="coin-box">
                <span class="coin-num">🔥 <?= $racha ?></span>
                <span class="coin-lbl">Días de racha</span>
            </div>
        </div>
        <?php if ($siguiente): ?>
        <div class="nivel-progress">
            <div class="progress"><div class="progress-fill" style="width:<?= $nivelPct ?>%"></div></div>
            <small>Te faltan <strong><?= $faltanNivel ?></strong> SkillCoins para ser <strong><?= e($siguiente['nombre']) ?></strong></small>
        </div>
        <?php else: ?>
        <div class="nivel-progress"><small>¡Has alcanzado el nivel máximo: <strong>Maestro</strong>! 🌟</small></div>
        <?php endif; ?>
    </div>
</div>

<!-- Recordatorio diario -->
<?php if (!empty($alumno['mensaje_diario'])): ?>
<div class="card recordatorio">
    <span class="recordatorio-ico">📌</span>
    <div>
        <h3>Recuerda</h3>
        <p><?= e($alumno['mensaje_diario']) ?></p>
    </div>
</div>
<?php endif; ?>

<div class="grid-2">
    <!-- Objetivos -->
    <div class="card">
        <div class="card-head"><h3>Tus objetivos de hoy</h3><a class="link" href="<?= url('alumno/objetivos.php') ?>">Ver todos</a></div>
        <ul class="checklist">
            <?php foreach ($objetivos as $o): ?>
                <li class="<?= $o['completado'] ? 'done' : '' ?>">
                    <span class="check"><?= $o['completado'] ? '✅' : '⬜' ?></span>
                    <span><?= e($o['titulo']) ?></span>
                    <?php if ($o['skillcoins']): ?><span class="pill">+<?= (int)$o['skillcoins'] ?> 🪙</span><?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if (!$objetivos): ?><li class="muted">Aún no tienes objetivos asignados.</li><?php endif; ?>
        </ul>
    </div>

    <!-- Recompensa -->
    <div class="card">
        <div class="card-head"><h3>Tu recompensa</h3><a class="link" href="<?= url('alumno/recompensas.php') ?>">Detalle</a></div>
        <?php if ($recompensa):
            $faltan = max(0, (int)$recompensa['costo'] - $total);
            $pct = min(100, round($total / max(1, (int)$recompensa['costo']) * 100)); ?>
            <div class="reward">
                <div class="reward-title">🎁 <?= e($recompensa['titulo']) ?></div>
                <div class="progress"><div class="progress-fill gold" style="width:<?= $pct ?>%"></div></div>
                <?php if ($faltan > 0): ?>
                    <small>Te faltan <strong><?= $faltan ?></strong> SkillCoins</small>
                <?php else: ?>
                    <small class="ready">¡Ya puedes conseguirla! 🎉</small>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="muted">No hay recompensa activa. Habla con tu mentor.</p>
        <?php endif; ?>
    </div>
</div>

<?php
render_footer();
?>
