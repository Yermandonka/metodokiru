<?php
require_once __DIR__ . '/../includes/alumno_ctx.php';
require_once __DIR__ . '/../includes/layout.php';
$aid = (int) $alumno['id'];

// SkillCoins ganadas hoy jugando (informativo)
$st = db()->prepare("SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log
                     WHERE alumno_id = ? AND motivo LIKE 'Inglés%' AND DATE(fecha) = CURDATE()");
$st->execute([$aid]);
$hoyIngles = (int) $st->fetchColumn();

render_header('Inglés', 'alumno', $mundo, nav_alumno('ingles'));
?>
<h1 class="page-title">Inglés de verano 🇬🇧</h1>

<!-- Portada -->
<div class="card" id="pantallaInicio">
    <div class="ingles-hero">
        <div class="ingles-ico">🎮</div>
        <div>
            <h3>Mini-juegos para aprender inglés</h3>
            <p class="muted">Cada lección tiene 10 retos distintos: traducir, completar frases,
                ordenar palabras, unir parejas y escuchar. Cada acierto vale <strong>1 SkillCoin</strong>
                (máximo 50 al día). ¡Cada lección es diferente!</p>
            <p class="muted">Hoy has ganado <strong><?= $hoyIngles ?></strong> 🪙 con el inglés.</p>
        </div>
    </div>
    <button class="btn" id="inglesEmpezar" type="button">Empezar lección</button>
</div>

<!-- Juego -->
<div class="card" id="pantallaJuego" hidden>
    <div class="ingles-top">
        <div class="progress"><div class="progress-fill" id="inglesBarra" style="width:0%"></div></div>
        <span class="muted" id="inglesPaso"></span>
    </div>
    <div id="ejercicio"></div>
    <div class="ingles-feedback" id="inglesFeedback" hidden></div>
    <button class="btn" id="inglesAccion" type="button" disabled>Comprobar</button>
</div>

<!-- Resultado -->
<div class="card" id="pantallaFin" hidden>
    <div class="ingles-fin">
        <div class="ingles-fin-ico" id="finIco">🏆</div>
        <h3 id="finTitulo"></h3>
        <p class="muted" id="finDetalle"></p>
        <button class="btn" id="inglesOtra" type="button">Otra lección</button>
    </div>
</div>

<script>
    window.KIRU_INGLES = { api: <?= json_encode(url('alumno/ingles_api.php')) ?> };
</script>
<script src="<?= asset('assets/js/ingles.js') ?>"></script>
<?php render_footer(); ?>
