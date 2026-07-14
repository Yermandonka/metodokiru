<?php
require_once __DIR__ . '/../includes/alumno_ctx.php';
require_once __DIR__ . '/../includes/layout.php';
$aid = (int) $alumno['id'];

/* SkillCoins de las últimas 6 semanas */
$semanas = [];
for ($i = 5; $i >= 0; $i--) {
    $ini = (new DateTime("monday this week"))->modify("-$i week");
    $fin = (clone $ini)->modify('+7 days');
    $st = db()->prepare('SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log WHERE alumno_id=? AND fecha>=? AND fecha<?');
    $st->execute([$aid, $ini->format('Y-m-d'), $fin->format('Y-m-d')]);
    $semanas[] = ['lbl' => $ini->format('d/m'), 'val' => (int) $st->fetchColumn()];
}

/* SkillCoins de los últimos 6 meses */
$meses = [];
for ($i = 5; $i >= 0; $i--) {
    $m = (new DateTime('first day of this month'))->modify("-$i month");
    $st = db()->prepare('SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log WHERE alumno_id=? AND YEAR(fecha)=? AND MONTH(fecha)=?');
    $st->execute([$aid, $m->format('Y'), $m->format('n')]);
    $meses[] = ['lbl' => $m->format('M'), 'val' => (int) $st->fetchColumn()];
}

$racha       = racha_dias($aid);
$habitosOk   = (int) db()->query("SELECT COUNT(*) FROM habitos WHERE alumno_id=$aid AND completado=1")->fetchColumn();
$objetivosOk = (int) db()->query("SELECT COUNT(*) FROM objetivos WHERE alumno_id=$aid AND completado=1")->fetchColumn();
$conexiones  = (int) db()->query("SELECT COUNT(*) FROM conexiones WHERE alumno_id=$aid")->fetchColumn();

function barras(array $datos, string $clase = ''): void
{
    $max = max(1, max(array_column($datos, 'val')));
    echo '<div class="barchart">';
    foreach ($datos as $d) {
        $h = round($d['val'] / $max * 100);
        echo '<div class="bar-col"><div class="bar ' . e($clase) . '" style="height:' . $h . '%" title="' . $d['val'] . '"></div>'
           . '<span class="bar-val">' . $d['val'] . '</span><span class="bar-lbl">' . e($d['lbl']) . '</span></div>';
    }
    echo '</div>';
}

render_header('Estadísticas', 'alumno', $mundo, nav_alumno('estadisticas'));
?>
<h1 class="page-title">Tus estadísticas</h1>

<div class="stat-row">
    <div class="stat-card"><span class="stat-num">🔥 <?= $racha ?></span><span>Días seguidos</span></div>
    <div class="stat-card"><span class="stat-num">✅ <?= $habitosOk ?></span><span>Hábitos completados</span></div>
    <div class="stat-card"><span class="stat-num">🎯 <?= $objetivosOk ?></span><span>Objetivos cumplidos</span></div>
    <div class="stat-card"><span class="stat-num">📅 <?= $conexiones ?></span><span>Días de conexión</span></div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>SkillCoins por semana</h3>
        <?php barras($semanas); ?>
    </div>
    <div class="card">
        <h3>Evolución mensual</h3>
        <?php barras($meses, 'gold'); ?>
    </div>
</div>
<?php render_footer(); ?>
