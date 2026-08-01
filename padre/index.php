<?php
/**
 * Área de familias (rol padre): solo visualización.
 * Muestra el progreso de cada hijo vinculado: SkillCoins, racha, objetivos,
 * objetivos de recompensa (ej: ir al zoo) y radar de destrezas de inglés.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_login(['padre']);
padres_migracion();

$hijos = hijos_de_padre((int) $user['id']);

/** Barras de SkillCoins de las últimas 6 semanas (mismo estilo que el campus). */
function barras_semanas(int $alumnoId): void
{
    $semanas = [];
    for ($i = 5; $i >= 0; $i--) {
        $ini = (new DateTime('monday this week'))->modify("-$i week");
        $fin = (clone $ini)->modify('+7 days');
        $st = db()->prepare('SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log WHERE alumno_id=? AND fecha>=? AND fecha<?');
        $st->execute([$alumnoId, $ini->format('Y-m-d'), $fin->format('Y-m-d')]);
        $semanas[] = ['lbl' => $ini->format('d/m'), 'val' => (int) $st->fetchColumn()];
    }
    $max = max(1, max(array_column($semanas, 'val')));
    echo '<div class="barchart">';
    foreach ($semanas as $d) {
        $h = round($d['val'] / $max * 100);
        echo '<div class="bar-col"><div class="bar" style="height:' . $h . '%" title="' . $d['val'] . '"></div>'
           . '<span class="bar-val">' . $d['val'] . '</span><span class="bar-lbl">' . e($d['lbl']) . '</span></div>';
    }
    echo '</div>';
}

render_header('Familia', 'panel', '', [['Mis hijos', 'padre/index.php', true]]);
?>
<div class="page-head">
    <h1>Área de familias</h1>
    <p class="muted">Aquí puedes seguir el progreso de tus hijos. Esta vista es solo informativa:
        los cambios los gestiona su mentor.</p>
</div>

<?php if (!$hijos): ?>
    <div class="card">
        <p class="muted">Aún no tienes ningún hijo vinculado a tu cuenta. Pídeselo al mentor o al
            equipo de Método Kiru y aparecerán aquí automáticamente.</p>
    </div>
<?php endif; ?>

<?php foreach ($hijos as $h):
    $aid = (int) $h['id'];
    $radar = ingles_radar($aid);
    $hayRadar = (bool) array_filter($radar, fn($v) => $v['pct'] !== null);

    $st = db()->prepare('SELECT COALESCE(SUM(aciertos),0), COALESCE(SUM(total),0) FROM ingles_resultados WHERE alumno_id=?');
    $st->execute([$aid]);
    [$aciertos, $total] = $st->fetch(PDO::FETCH_NUM);
    $precision = $total ? round($aciertos / $total * 100) . '%' : '—';

    $st = db()->prepare('SELECT COUNT(*) FROM objetivos WHERE alumno_id=? AND completado=1');
    $st->execute([$aid]);
    $objetivosOk = (int) $st->fetchColumn();

    // Objetivos de recompensa del hijo (ej: ir al zoo) y su progreso en SkillCoins.
    $st = db()->prepare('SELECT * FROM recompensas WHERE alumno_id=? ORDER BY conseguida, costo');
    $st->execute([$aid]);
    $recompensas = $st->fetchAll();
    $scTotal = sc_total($aid);
?>
    <div class="card">
        <h3><?= e($h['nombre']) ?> <small class="muted"><?= $h['edad'] ? (int) $h['edad'] . ' años' : '' ?></small></h3>
        <div class="stat-row">
            <div class="stat-card"><span class="stat-num">🪙 <?= $scTotal ?></span><span>SkillCoins totales</span></div>
            <div class="stat-card"><span class="stat-num">🔥 <?= racha_dias($aid) ?></span><span>Días seguidos</span></div>
            <div class="stat-card"><span class="stat-num">🎯 <?= $objetivosOk ?></span><span>Objetivos cumplidos</span></div>
            <div class="stat-card"><span class="stat-num">🇬🇧 <?= $precision ?></span><span>Precisión en inglés</span></div>
        </div>

        <div class="grid-2">
            <div>
                <h4 class="muted" style="margin:6px 0 4px">SkillCoins por semana</h4>
                <?php barras_semanas($aid); ?>
            </div>
            <div>
                <h4 class="muted" style="margin:6px 0 4px">Destrezas de inglés</h4>
                <?php if ($hayRadar): ?>
                    <div class="ingles-radar"><?= radar_svg($radar) ?></div>
                <?php else: ?>
                    <p class="muted">Aparecerá cuando haga sus primeras tareas de inglés.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($recompensas): ?>
        <h4 class="muted" style="margin:14px 0 8px">Objetivos de recompensa</h4>
        <div class="reward-grid">
            <?php foreach ($recompensas as $r):
                $faltan = max(0, (int)$r['costo'] - $scTotal);
                $pct = min(100, round($scTotal / max(1, (int)$r['costo']) * 100)); ?>
                <div class="reward">
                    <div class="reward-title">🎁 <?= e($r['titulo']) ?> <small class="muted">(<?= (int)$r['costo'] ?> 🪙)</small></div>
                    <div class="progress"><div class="progress-fill gold" style="width:<?= $pct ?>%"></div></div>
                    <?php if ($r['conseguida']): ?>
                        <small class="ready">¡Conseguida! 🎉</small>
                    <?php elseif ($faltan > 0): ?>
                        <small>Le faltan <strong><?= $faltan ?></strong> SkillCoins</small>
                    <?php else: ?>
                        <small class="ready">¡Ya puede reclamarla!</small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php render_footer(); ?>
