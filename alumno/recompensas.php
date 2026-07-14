<?php
require_once __DIR__ . '/../includes/alumno_ctx.php';
require_once __DIR__ . '/../includes/layout.php';
$aid = (int) $alumno['id'];
$total = sc_total($aid);

$rc = db()->prepare('SELECT * FROM recompensas WHERE alumno_id = ? ORDER BY conseguida, activa DESC, costo');
$rc->execute([$aid]);
$recompensas = $rc->fetchAll();

render_header('Recompensas', 'alumno', $mundo, nav_alumno('recompensas'));
?>
<h1 class="page-title">Recompensas 🎁</h1>
<div class="reward-grid">
    <?php foreach ($recompensas as $r):
        $faltan = max(0, (int)$r['costo'] - $total);
        $pct = min(100, round($total / max(1,(int)$r['costo']) * 100)); ?>
        <div class="card reward-card <?= $r['conseguida'] ? 'won' : '' ?>">
            <div class="reward-title">🎁 <?= e($r['titulo']) ?></div>
            <div class="reward-cost"><?= (int)$r['costo'] ?> 🪙</div>
            <div class="progress"><div class="progress-fill gold" style="width:<?= $pct ?>%"></div></div>
            <?php if ($r['conseguida']): ?>
                <small class="ready">¡Conseguida! 🎉</small>
            <?php elseif ($faltan > 0): ?>
                <small>Te faltan <strong><?= $faltan ?></strong> SkillCoins</small>
            <?php else: ?>
                <small class="ready">¡Ya puedes reclamarla!</small>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$recompensas): ?><p class="muted">Tu mentor aún no ha añadido recompensas.</p><?php endif; ?>
</div>
<?php render_footer(); ?>
