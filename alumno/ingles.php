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
<h1 class="page-title">Summer English 🇬🇧</h1>

<!-- Portada -->
<div class="card" id="pantallaInicio">
    <div class="ingles-hero">
        <div class="ingles-ico">🎮</div>
        <div>
            <h3>Play mini-games and learn English!</h3>
            <p class="muted">Each lesson has 10 fun games: translate, complete, order,
                match and listen. One win = <strong>1 SkillCoin</strong> (max 50 a day).
                Every lesson is new!</p>
            <p class="muted">Today you have <strong><?= $hoyIngles ?></strong> 🪙 from English.</p>
        </div>
    </div>
    <button class="btn" id="inglesEmpezar" type="button">Start lesson</button>
</div>

<!-- Juego -->
<div class="card" id="pantallaJuego" hidden>
    <div class="ingles-top">
        <div class="progress"><div class="progress-fill" id="inglesBarra" style="width:0%"></div></div>
        <span class="ingles-racha" id="inglesRacha" hidden></span>
        <span class="muted" id="inglesPaso"></span>
    </div>
    <div id="ejercicio"></div>
    <div class="ingles-feedback" id="inglesFeedback" hidden></div>
    <button class="btn" id="inglesAccion" type="button" hidden>Continue</button>
</div>

<!-- Resultado -->
<div class="card" id="pantallaFin" hidden>
    <div class="ingles-fin">
        <div class="ingles-fin-ico" id="finIco">🏆</div>
        <h3 id="finTitulo"></h3>
        <p class="muted" id="finDetalle"></p>
        <button class="btn" id="inglesOtra" type="button">Play again</button>
    </div>
</div>

<!-- Sale del modo pantalla completa / inmersivo (solo visible en móvil jugando) -->
<button class="ingles-salir" id="inglesSalir" type="button" aria-label="Exit full screen">✕</button>

<script>
    window.KIRU_INGLES = { api: <?= json_encode(url('alumno/ingles_api.php')) ?> };
</script>
<script src="<?= asset('assets/js/ingles.js') ?>"></script>
<?php render_footer(); ?>
