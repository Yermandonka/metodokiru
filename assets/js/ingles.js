/* Método Kiru — Inglés de verano: mini-juegos tipo Duolingo */
(function () {
    if (!window.KIRU_INGLES) return;

    function $(id) { return document.getElementById(id); }
    var pInicio = $('pantallaInicio'), pJuego = $('pantallaJuego'), pFin = $('pantallaFin');
    var cont = $('ejercicio'), barra = $('inglesBarra'), paso = $('inglesPaso');
    var feedback = $('inglesFeedback'), btnAccion = $('inglesAccion');
    var btnEmpezar = $('inglesEmpezar'), btnOtra = $('inglesOtra');

    var ejercicios = [], idx = 0, aciertos = 0;
    var evaluar = null;      // función del ejercicio actual → {ok, solucion}
    var resuelto = false;

    /* ---------- Utilidades ---------- */
    function hablar(texto) {
        if (!('speechSynthesis' in window)) return;
        var u = new SpeechSynthesisUtterance(texto);
        u.lang = 'en-GB';
        u.rate = 0.8;
        speechSynthesis.cancel();
        speechSynthesis.speak(u);
    }
    function normalizar(s) {
        return s.toLowerCase().replace(/[.,!?¡¿'’"]/g, '').replace(/\s+/g, ' ').trim();
    }
    function el(tag, clase, texto) {
        var n = document.createElement(tag);
        if (clase) n.className = clase;
        if (texto !== undefined) n.textContent = texto;
        return n;
    }
    function barajar(arr) {
        for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
        }
    }

    /* ---------- Tipos con opciones (elección, hueco, escucha) ---------- */
    function pintarOpciones(enunciado, opciones, correcta, audioTexto) {
        cont.appendChild(el('p', 'ingles-enunciado', enunciado));
        if (audioTexto) {
            var play = el('button', 'ingles-audio', '🔊 Escuchar otra vez');
            play.type = 'button';
            play.addEventListener('click', function () { hablar(audioTexto); });
            cont.appendChild(play);
            setTimeout(function () { hablar(audioTexto); }, 400);
        }
        var grid = el('div', 'ingles-opciones');
        var sel = -1;
        opciones.forEach(function (op, i) {
            var b = el('button', 'ingles-opcion', op);
            b.type = 'button';
            b.addEventListener('click', function () {
                if (resuelto) return;
                sel = i;
                grid.querySelectorAll('.sel').forEach(function (x) { x.classList.remove('sel'); });
                b.classList.add('sel');
                btnAccion.disabled = false;
            });
            grid.appendChild(b);
        });
        cont.appendChild(grid);
        evaluar = function () {
            grid.children[correcta].classList.add('ok');
            if (sel !== correcta) grid.children[sel].classList.add('mal');
            return { ok: sel === correcta, solucion: opciones[correcta] };
        };
    }

    /* ---------- Ordenar palabras ---------- */
    function pintarOrdenar(e) {
        cont.appendChild(el('p', 'ingles-enunciado', 'Traduce al inglés: «' + e.es + '»'));
        var zona = el('div', 'ingles-zona');
        var banco = el('div', 'ingles-banco');
        cont.appendChild(zona);
        cont.appendChild(banco);
        e.palabras.forEach(function (palabra) {
            var chip = el('button', 'ingles-chip', palabra);
            chip.type = 'button';
            chip.addEventListener('click', function () {
                if (resuelto) return;
                (chip.parentNode === banco ? zona : banco).appendChild(chip);
                btnAccion.disabled = zona.children.length === 0;
            });
            banco.appendChild(chip);
        });
        evaluar = function () {
            var frase = Array.prototype.map.call(zona.children, function (c) { return c.textContent; }).join(' ');
            return { ok: normalizar(frase) === normalizar(e.solucion), solucion: e.solucion };
        };
    }

    /* ---------- Unir parejas ---------- */
    function pintarParejas(e) {
        cont.appendChild(el('p', 'ingles-enunciado', 'Une cada palabra con su traducción'));
        var grid = el('div', 'ingles-parejas');
        cont.appendChild(grid);
        var colEs = el('div', 'ingles-col'), colEn = el('div', 'ingles-col');
        grid.appendChild(colEs);
        grid.appendChild(colEn);

        var fallos = 0, unidas = 0, selEs = null;
        var izq = e.pares.slice(), der = e.pares.map(function (p) { return p.en; });
        barajar(izq);
        barajar(der);

        izq.forEach(function (p) {
            var b = el('button', 'ingles-opcion', p.es);
            b.type = 'button';
            b.dataset.en = p.en;
            b.addEventListener('click', function () {
                if (resuelto || b.classList.contains('ok')) return;
                if (selEs) selEs.classList.remove('sel');
                selEs = b;
                b.classList.add('sel');
            });
            colEs.appendChild(b);
        });
        der.forEach(function (en) {
            var b = el('button', 'ingles-opcion', en);
            b.type = 'button';
            b.addEventListener('click', function () {
                if (resuelto || b.classList.contains('ok') || !selEs) return;
                if (selEs.dataset.en === en) {
                    b.classList.add('ok');
                    selEs.classList.remove('sel');
                    selEs.classList.add('ok');
                    selEs = null;
                    unidas++;
                    if (unidas === e.pares.length) resolver(); // se resuelve solo
                } else {
                    fallos++;
                    b.classList.add('mal');
                    setTimeout(function () { b.classList.remove('mal'); }, 500);
                }
            });
            colEn.appendChild(b);
        });
        evaluar = function () {
            return { ok: fallos === 0, parejas: true, fallos: fallos };
        };
    }

    /* ---------- Flujo de la lección ---------- */
    function pintar() {
        var e = ejercicios[idx];
        resuelto = false;
        evaluar = null;
        paso.textContent = (idx + 1) + ' / ' + ejercicios.length;
        barra.style.width = (idx / ejercicios.length * 100) + '%';
        feedback.hidden = true;
        feedback.className = 'ingles-feedback';
        btnAccion.textContent = 'Comprobar';
        btnAccion.disabled = true;
        btnAccion.dataset.modo = 'comprobar';
        cont.innerHTML = '';

        if (e.tipo === 'eleccion') pintarOpciones(e.pregunta, e.opciones, e.correcta);
        else if (e.tipo === 'hueco') pintarOpciones('Completa la frase: ' + e.frase, e.opciones, e.correcta);
        else if (e.tipo === 'escucha') pintarOpciones('Escucha y elige lo que oyes 👂', e.opciones, e.correcta, e.texto);
        else if (e.tipo === 'ordenar') pintarOrdenar(e);
        else if (e.tipo === 'parejas') pintarParejas(e);
    }

    function resolver() {
        if (resuelto || !evaluar) return;
        resuelto = true;
        var r = evaluar();
        if (r.ok) aciertos++;
        feedback.hidden = false;
        feedback.classList.add(r.ok ? 'ok' : 'mal');
        if (r.parejas) {
            feedback.textContent = r.ok
                ? '¡Perfecto! Todas a la primera ✅ +1 🪙'
                : 'Completado con ' + r.fallos + ' fallo(s). ¡La próxima saldrá mejor!';
        } else {
            feedback.textContent = r.ok
                ? '¡Correcto! ✅ +1 🪙'
                : 'Casi… La respuesta era: «' + r.solucion + '»';
        }
        btnAccion.textContent = idx + 1 >= ejercicios.length ? 'Ver resultado' : 'Continuar';
        btnAccion.disabled = false;
        btnAccion.dataset.modo = 'continuar';
    }

    function terminarLeccion() {
        barra.style.width = '100%';
        var fd = new FormData();
        fd.append('accion', 'terminar');
        fd.append('aciertos', aciertos);
        fetch(KIRU_INGLES.api, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .catch(function () { return {}; })
            .then(function (d) {
                pJuego.hidden = true;
                pFin.hidden = false;
                var pct = aciertos / ejercicios.length;
                $('finIco').textContent = pct >= 0.9 ? '🏆' : pct >= 0.6 ? '🌟' : '💪';
                $('finTitulo').textContent = aciertos + ' de ' + ejercicios.length + ' aciertos';
                $('finDetalle').textContent = typeof d.coins === 'number'
                    ? (d.coins > 0
                        ? 'Has ganado +' + d.coins + ' SkillCoins 🪙'
                        : 'Hoy ya tienes el máximo de SkillCoins de inglés. ¡Sigue jugando por diversión!')
                    : '';
            });
    }

    btnAccion.addEventListener('click', function () {
        if (btnAccion.dataset.modo === 'comprobar') {
            resolver();
            return;
        }
        idx++;
        if (idx >= ejercicios.length) terminarLeccion();
        else pintar();
    });

    function empezar(boton) {
        boton.disabled = true;
        var txt = boton.textContent;
        boton.textContent = 'Preparando lección…';
        var fd = new FormData();
        fd.append('accion', 'nueva');
        fetch(KIRU_INGLES.api, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var lista = d.ejercicios || [];
                // Sin síntesis de voz no se pueden jugar los de escucha.
                if (!('speechSynthesis' in window)) {
                    lista = lista.filter(function (e) { return e.tipo !== 'escucha'; });
                }
                if (!lista.length) throw new Error('sin ejercicios');
                ejercicios = lista;
                idx = 0;
                aciertos = 0;
                pInicio.hidden = true;
                pFin.hidden = true;
                pJuego.hidden = false;
                pintar();
            })
            .catch(function () {
                alert('No se pudo preparar la lección. Vuelve a intentarlo.');
            })
            .finally(function () {
                boton.disabled = false;
                boton.textContent = txt;
            });
    }

    if (btnEmpezar) btnEmpezar.addEventListener('click', function () { empezar(btnEmpezar); });
    if (btnOtra) btnOtra.addEventListener('click', function () { empezar(btnOtra); });
})();
