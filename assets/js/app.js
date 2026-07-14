/* Método Kiru — control de tiempo de uso del alumno */
(function () {
    if (!window.KIRU) return;

    var uso = KIRU.uso;
    var max = KIRU.max;
    var texto = document.getElementById('usoTexto');
    var overlay = document.getElementById('usoOverlay');

    function pintar() {
        if (texto) texto.textContent = uso;
        if (uso >= max && overlay) {
            overlay.hidden = false;
            document.body.classList.add('bloqueado');
        }
    }
    pintar();

    // Cada minuto suma 1 al servidor (solo si la pestaña está visible).
    setInterval(function () {
        if (document.hidden) return;
        if (uso >= max) { pintar(); return; }
        fetch(KIRU.base + '/alumno/heartbeat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mins=1'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (typeof d.uso === 'number') { uso = d.uso; max = d.max; }
                pintar();
            })
            .catch(function () {});
    }, 60000);
})();
