<?php
/**
 * Área de familias (rol padre): solo visualización.
 * Muestra el progreso de cada hijo vinculado (SkillCoins, racha, objetivos,
 * radar de inglés) y los enlaces de pago de la mentoría.
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
    <h1>Área de familias 👨‍👩‍👧</h1>
    <p class="muted">Aquí puedes seguir el progreso de tus hijos. Esta vista es solo informativa:
        los cambios los gestiona su mentor.</p>
</div>

<!-- Pagos -->
<div class="card">
    <h3>Pagos 💳</h3>
    <?php if (PAGO_URL || PAGO_BIZUM): ?>
        <p class="muted">Puedes hacer el pago de la mentoría por cualquiera de estas vías:</p>
        <?php if (PAGO_URL): ?>
            <p><a class="btn" href="<?= e(PAGO_URL) ?>" target="_blank" rel="noopener">Pagar online</a></p>
        <?php endif; ?>
        <?php if (PAGO_BIZUM): ?>
            <p><strong>Bizum:</strong> <?= e(PAGO_BIZUM) ?></p>
        <?php endif; ?>
    <?php else: ?>
        <p class="muted">Los pagos online estarán disponibles muy pronto. Mientras tanto, tu mentor
            te indicará cómo hacer el pago.</p>
    <?php endif; ?>
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
?>
    <div class="card">
        <h3><?= e($h['nombre']) ?> <small class="muted"><?= $h['edad'] ? (int) $h['edad'] . ' años' : '' ?></small></h3>
        <div class="stat-row">
            <div class="stat-card"><span class="stat-num">🪙 <?= sc_total($aid) ?></span><span>SkillCoins totales</span></div>
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
                    <p class="muted">Aparecerá cuando juegue sus primeras lecciones de inglés.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php render_footer(); ?>
