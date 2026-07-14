<?php
require_once __DIR__ . '/../includes/alumno_ctx.php';
require_once __DIR__ . '/../includes/layout.php';
$aid = (int) $alumno['id'];

$total  = sc_total($aid);
$semana = sc_semana($aid);
$mes    = sc_mes($aid);

// Próxima recompensa
$rc = db()->prepare('SELECT * FROM recompensas WHERE alumno_id = ? AND activa = 1 AND conseguida = 0 ORDER BY costo LIMIT 1');
$rc->execute([$aid]);
$recompensa = $rc->fetch();
$faltan = $recompensa ? max(0, (int)$recompensa['costo'] - $total) : 0;

// Histórico reciente
$hist = db()->prepare('SELECT * FROM skillcoins_log WHERE alumno_id = ? ORDER BY fecha DESC LIMIT 20');
$hist->execute([$aid]);
$hist = $hist->fetchAll();

render_header('SkillCoins', 'alumno', $mundo, nav_alumno('skillcoins'));
?>
<h1 class="page-title">SkillCoins 🪙</h1>

<div class="coins-row big">
    <div class="coin-box"><span class="coin-num"><?= $total ?></span><span class="coin-lbl">Total</span></div>
    <div class="coin-box"><span class="coin-num"><?= $semana ?></span><span class="coin-lbl">Esta semana</span></div>
    <div class="coin-box"><span class="coin-num"><?= $mes ?></span><span class="coin-lbl">Este mes</span></div>
    <div class="coin-box"><span class="coin-num"><?= $faltan ?></span><span class="coin-lbl">Para el próximo premio</span></div>
</div>

<?php if ($recompensa): $pct = min(100, round($total / max(1,(int)$recompensa['costo']) * 100)); ?>
<div class="card">
    <div class="card-head"><h3>Próxima recompensa: <?= e($recompensa['titulo']) ?></h3><span><?= (int)$recompensa['costo'] ?> 🪙</span></div>
    <div class="progress"><div class="progress-fill gold" style="width:<?= $pct ?>%"></div></div>
</div>
<?php endif; ?>

<div class="card">
    <h3>Histórico reciente</h3>
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Motivo</th><th class="right">SkillCoins</th></tr></thead>
        <tbody>
            <?php foreach ($hist as $h): ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i', strtotime($h['fecha']))) ?></td>
                    <td><?= e($h['motivo']) ?></td>
                    <td class="right <?= $h['cantidad'] < 0 ? 'neg' : 'pos' ?>"><?= ($h['cantidad'] >= 0 ? '+' : '') . (int)$h['cantidad'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$hist): ?><tr><td colspan="3" class="muted">Todavía no has ganado SkillCoins.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php render_footer(); ?>
