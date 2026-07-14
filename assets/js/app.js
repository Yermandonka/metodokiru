/* Método Kiru — control de tiempo de uso del alumno */
(function () {
    if (!window.KIRU) return;

    var uso = KIRU.uso;
    var max = KIRU.max;
    var unlimited = !(max > 0);      // max <= 0  ->  sin límite
    var texto = document.getElementById('usoTexto');
    var overlay = document.getElementById('usoOverlay');

    function pintar() {
        if (texto) texto.textContent = uso;
        if (!unlimited && uso >= max && overlay) {
            overlay.hidden = false;
            document.body.classList.add('bloqueado');
        }
    }
    pintar();

    // Cada minuto suma 1 al servidor (solo si la pestaña está visible).
    setInterval(function () {
        if (document.hidden) return;
        // Si hay límite y ya se alcanzó, no seguimos sumando.
        if (!unlimited && uso >= max) { pintar(); return; }
        fetch(KIRU.base + '/alumno/heartbeat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mins=1'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (typeof d.uso === 'number') {
                    uso = d.uso;
                    max = d.max;
                    unlimited = !(max > 0);
                }
                pintar();
            })
            .catch(function () {});
    }, 60000);
})();
