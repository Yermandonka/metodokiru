/* Método Kiru — Summer English: mini-juegos tipo Duolingo (interfaz en inglés sencillo) */
(function () {
    if (!window.KIRU_INGLES) return;

    function $(id) { return document.getElementById(id); }
    var pInicio = $('pantallaInicio'), pJuego = $('pantallaJuego'), pFin = $('pantallaFin');
    var cont = $('ejercicio'), barra = $('inglesBarra'), paso = $('inglesPaso');
    var feedback = $('inglesFeedback'), btnAccion = $('inglesAccion');
    var btnEmpezar = $('inglesEmpezar'), btnOtra = $('inglesOtra'), chipRacha = $('inglesRacha');

    var ejercicios = [], idx = 0, aciertos = 0, racha = 0;
    var evaluar = null;          // función del ejercicio actual → {ok, solucion}
    var resuelto = false;
    var redibujarParejas = null; // para redibujar las cuerdas al girar el móvil

    var TIPOS = {
        eleccion:  { txt: '🎯 Translate', clase: 't-eleccion' },
        hueco:     { txt: '✏️ Complete', clase: 't-hueco' },
        ordenar:   { txt: '🧩 Order', clase: 't-ordenar' },
        parejas:   { txt: '🔗 Match', clase: 't-parejas' },
        escucha:   { txt: '👂 Listen', clase: 't-escucha' },
        verdadero: { txt: '🤔 True or false?', clase: 't-verdadero' },
        escribir:  { txt: '⌨️ Write', clase: 't-escribir' },
        emoji:     { txt: '🖼️ What is it?', clase: 't-emoji' },
    };
    var FRASES_OK  = ['Great job!', 'Awesome!', 'You got it!', 'Perfect!', 'Super!', 'Way to go!'];
    var FRASES_MAL = ['Almost!', 'Oops!', 'Good try!', 'Next time!'];
    var ICONOS_OK  = ['🎉', '🌟', '🦊', '🚀', '💚', '🏅'];
    var ICONOS_MAL = ['🙈', '💪', '😅', '🤏'];

    function azar(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

    /* ---------- Sonidos (WebAudio, sin archivos) ---------- */
    var ctxAudio = null;
    function nota(freq, cuando, dur, tipo) {
        try {
            if (!ctxAudio) ctxAudio = new (window.AudioContext || window.webkitAudioContext)();
            var t = ctxAudio.currentTime + cuando;
            var o = ctxAudio.createOscillator(), g = ctxAudio.createGain();
            o.type = tipo || 'triangle';
            o.frequency.value = freq;
            g.gain.setValueAtTime(0.001, t);
            g.gain.exponentialRampToValueAtTime(0.08, t + 0.02);
            g.gain.exponentialRampToValueAtTime(0.001, t + dur);
            o.connect(g);
            g.connect(ctxAudio.destination);
            o.start(t);
            o.stop(t + dur + 0.05);
        } catch (e) { /* sin sonido */ }
    }
    function sonidoAcierto() { nota(523, 0, 0.15); nota(659, 0.1, 0.15); nota(784, 0.2, 0.25); }
    function sonidoFallo() { nota(220, 0, 0.2, 'sawtooth'); nota(185, 0.15, 0.3, 'sawtooth'); }
    function sonidoFinal() { [523, 659, 784, 1047].forEach(function (f, i) { nota(f, i * 0.12, 0.3); }); }

    /* ---------- Confeti ---------- */
    function confeti(n) {
        var colores = ['#58cc02', '#1cb0f6', '#ffc800', '#ff9600', '#ce82ff', '#ff4b4b'];
        for (var i = 0; i < n; i++) {
            var p = document.createElement('span');
            p.className = 'confeti';
            p.style.left = (5 + Math.random() * 90) + 'vw';
            p.style.background = azar(colores);
            p.style.animationDuration = (1.6 + Math.random() * 1.6) + 's';
            p.style.animationDelay = (Math.random() * 0.3) + 's';
            document.body.appendChild(p);
            setTimeout(function (q) { return function () { q.remove(); }; }(p), 3800);
        }
    }

    /* ---------- Voz (síntesis del navegador) ---------- */
    var vozEn = null, vozFunciona = false;
    function cargarVozEn() {
        var voces = speechSynthesis.getVoices();
        for (var i = 0; i < voces.length; i++) {
            if (/^en([-_]|$)/i.test(voces[i].lang)) { vozEn = voces[i]; break; }
        }
    }
    if ('speechSynthesis' in window) {
        cargarVozEn();
        if ('onvoiceschanged' in speechSynthesis) {
            speechSynthesis.onvoiceschanged = cargarVozEn;
        }
    }

    /**
     * Pronuncia el texto en inglés. Si el navegador no llega a reproducir nada
     * (sin voces instaladas, TTS bloqueado…), llama a alFallar una sola vez.
     */
    function hablar(texto, alFallar) {
        var fallado = false;
        function fallo() {
            if (!fallado && alFallar) { fallado = true; alFallar(); }
        }
        if (!('speechSynthesis' in window)) { fallo(); return; }
        speechSynthesis.cancel();
        // Pequeña espera tras cancel(): en Chrome, hablar justo después se pierde.
        setTimeout(function () {
            var u = new SpeechSynthesisUtterance(texto);
            u.lang = (vozEn && vozEn.lang) || 'en-US';
            if (vozEn) u.voice = vozEn;
            u.rate = 0.8;
            var empezo = false;
            u.onstart = function () { empezo = true; vozFunciona = true; };
            u.onerror = function () { if (!empezo) fallo(); };
            speechSynthesis.speak(u);
            speechSynthesis.resume(); // Chrome a veces lo deja pausado
            setTimeout(function () {
                if (!empezo && !vozFunciona && !speechSynthesis.speaking) fallo();
            }, 2000);
        }, 100);
    }

    /* ---------- Utilidades ---------- */
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
    /* Relanza una animación CSS aunque el elemento ya tuviera la clase. */
    function anima(elemento, clase) {
        elemento.classList.remove(clase);
        void elemento.offsetWidth;
        elemento.classList.add(clase);
    }

    /* ---------- Tipos con opciones (elección, hueco, escucha) ---------- */
    /* Se corrige solo al tocar una opción: sin botón de comprobar. */
    function pintarOpciones(enunciado, opciones, correcta, audioTexto, extra) {
        cont.appendChild(el('p', 'ingles-enunciado', enunciado));
        if (extra) cont.appendChild(extra);
        if (audioTexto) {
            var play = el('button', 'ingles-audio', '🔊 Listen again');
            play.type = 'button';
            play.addEventListener('click', function () { hablar(audioTexto); });
            cont.appendChild(play);
            setTimeout(function () { hablar(audioTexto, saltarEscucha); }, 400);
        }
        var grid = el('div', 'ingles-opciones');
        var sel = -1;
        opciones.forEach(function (op, i) {
            var b = el('button', 'ingles-opcion', op);
            b.type = 'button';
            b.addEventListener('click', function () {
                if (resuelto) return;
                sel = i;
                anima(b, 'sel');
                setTimeout(resolver, 250); // se ve la selección y se corrige sola
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
    /* Se corrige sola al colocar la última palabra. */
    function pintarOrdenar(e) {
        cont.appendChild(el('p', 'ingles-enunciado', 'Say it in English: «' + e.es + '»'));
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
                anima(chip, 'pop');
                if (banco.children.length === 0) {
                    setTimeout(function () {
                        if (!resuelto && banco.children.length === 0) resolver();
                    }, 350);
                }
            });
            banco.appendChild(chip);
        });
        evaluar = function () {
            var frase = Array.prototype.map.call(zona.children, function (c) { return c.textContent; }).join(' ');
            return { ok: normalizar(frase) === normalizar(e.solucion), solucion: e.solucion };
        };
    }

    /* ---------- Unir parejas con cuerdas ---------- */
    var SVG_NS = 'http://www.w3.org/2000/svg';
    function pintarParejas(e) {
        cont.appendChild(el('p', 'ingles-enunciado', 'Match the pairs! Tap a word, then its friend.'));
        var wrap = el('div', 'ingles-parejas-wrap');
        var grid = el('div', 'ingles-parejas');
        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('class', 'ingles-cuerdas');
        wrap.appendChild(grid);
        wrap.appendChild(svg);
        cont.appendChild(wrap);
        var colEs = el('div', 'ingles-col'), colEn = el('div', 'ingles-col');
        grid.appendChild(colEs);
        grid.appendChild(colEn);

        var COLORES = ['#58cc02', '#1cb0f6', '#ff9600', '#ce82ff'];
        var fallos = 0, unidas = 0, selEs = null;
        var uniones = [];
        var izq = e.pares.slice(), der = e.pares.map(function (p) { return p.en; });
        barajar(izq);
        barajar(der);

        /* Dibuja una cuerda (curva que cuelga) de la caja a a la caja b. */
        function dibujarCuerda(a, b, color) {
            var w = wrap.getBoundingClientRect();
            var ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
            var x1 = ra.right - w.left - 4, y1 = ra.top - w.top + ra.height / 2;
            var x2 = rb.left - w.left + 4, y2 = rb.top - w.top + rb.height / 2;
            var dx = Math.max(24, (x2 - x1) / 3);
            var path = document.createElementNS(SVG_NS, 'path');
            path.setAttribute('d', 'M' + x1 + ' ' + y1
                + ' C' + (x1 + dx) + ' ' + (y1 + 16) + ', ' + (x2 - dx) + ' ' + (y2 + 16)
                + ', ' + x2 + ' ' + y2);
            path.setAttribute('class', 'cuerda');
            path.setAttribute('stroke', color);
            svg.appendChild(path);
            var largo = path.getTotalLength();
            path.style.strokeDasharray = largo;
            path.style.strokeDashoffset = largo;
            requestAnimationFrame(function () { path.style.strokeDashoffset = '0'; });
            [[x1, y1], [x2, y2]].forEach(function (p) {
                var nudo = document.createElementNS(SVG_NS, 'circle');
                nudo.setAttribute('cx', p[0]);
                nudo.setAttribute('cy', p[1]);
                nudo.setAttribute('r', '5');
                nudo.setAttribute('fill', color);
                svg.appendChild(nudo);
            });
        }
        function redibujar() {
            svg.innerHTML = '';
            uniones.forEach(function (u) { dibujarCuerda(u.a, u.b, u.color); });
        }
        redibujarParejas = redibujar;

        izq.forEach(function (p) {
            var b = el('button', 'ingles-opcion', p.es);
            b.type = 'button';
            b.dataset.en = p.en;
            b.addEventListener('click', function () {
                if (resuelto || b.classList.contains('ok')) return;
                if (selEs) selEs.classList.remove('sel');
                selEs = b;
                anima(b, 'sel');
            });
            colEs.appendChild(b);
        });
        der.forEach(function (en) {
            var b = el('button', 'ingles-opcion', en);
            b.type = 'button';
            b.addEventListener('click', function () {
                if (resuelto || b.classList.contains('ok') || !selEs) return;
                if (selEs.dataset.en === en) {
                    var color = COLORES[unidas % COLORES.length];
                    anima(b, 'ok');
                    selEs.classList.remove('sel');
                    anima(selEs, 'ok');
                    b.style.borderColor = color;
                    selEs.style.borderColor = color;
                    uniones.push({ a: selEs, b: b, color: color });
                    dibujarCuerda(selEs, b, color);
                    selEs = null;
                    unidas++;
                    nota(440 + unidas * 110, 0, 0.12);
                    if (unidas === e.pares.length) resolver(); // se resuelve solo
                } else {
                    fallos++;
                    anima(b, 'mal');
                    setTimeout(function () { b.classList.remove('mal'); }, 500);
                }
            });
            colEn.appendChild(b);
        });
        evaluar = function () {
            return { ok: fallos === 0, parejas: true, fallos: fallos };
        };
    }

    /* ---------- Escribir la palabra ---------- */
    function pintarEscribir(e) {
        cont.appendChild(el('p', 'ingles-enunciado', e.pregunta));
        var fila = el('div', 'ingles-escribir');
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'ingles-input';
        input.placeholder = 'Type here…';
        input.autocapitalize = 'off';
        input.autocomplete = 'off';
        input.spellcheck = false;
        var ok = el('button', 'ingles-check', '✔');
        ok.type = 'button';
        function comprobar() {
            if (resuelto || normalizar(input.value) === '') return;
            resolver();
        }
        input.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') comprobar(); });
        ok.addEventListener('click', comprobar);
        fila.appendChild(input);
        fila.appendChild(ok);
        cont.appendChild(fila);
        setTimeout(function () { input.focus(); }, 250);
        evaluar = function () {
            var bien = normalizar(input.value) === normalizar(e.solucion);
            input.classList.add(bien ? 'ok' : 'mal');
            input.disabled = true;
            return { ok: bien, solucion: e.solucion };
        };
    }

    /* Si la voz no funciona en este dispositivo, se saltan los retos de escucha. */
    function saltarEscucha() {
        if (resuelto) return;
        ejercicios = ejercicios.filter(function (e, i) {
            return i < idx || (i > idx && e.tipo !== 'escucha');
        });
        if (idx >= ejercicios.length) {
            terminarLeccion();
            return;
        }
        pintar();
        feedback.innerHTML = '';
        feedback.appendChild(el('span', 'fb-ico', '🔇'));
        feedback.appendChild(el('span', '', 'No audio on this device. We skip the listening games!'));
        feedback.classList.add('info');
        feedback.hidden = false;
    }

    /* ---------- Flujo de la lección ---------- */
    function pintar() {
        var e = ejercicios[idx];
        resuelto = false;
        evaluar = null;
        redibujarParejas = null;
        paso.textContent = (idx + 1) + ' / ' + ejercicios.length;
        barra.style.width = (idx / ejercicios.length * 100) + '%';
        feedback.hidden = true;
        feedback.className = 'ingles-feedback';
        btnAccion.hidden = true;
        btnAccion.disabled = false;
        btnAccion.classList.remove('mal');
        cont.innerHTML = '';

        // Mini-lección antes del reto (si la trae y aún no se ha visto).
        if (e.leccion && !e.leccionVista) {
            e.leccionVista = true;
            cont.appendChild(el('span', 'ingles-tipo t-leccion', '📖 Lesson'));
            cont.appendChild(el('div', 'ingles-leccion', e.leccion));
            btnAccion.textContent = 'Got it!';
            btnAccion.hidden = false;
            btnAccion.dataset.modo = 'leccion';
            anima(cont, 'entrada');
            return;
        }
        btnAccion.dataset.modo = 'continuar';

        var tipo = TIPOS[e.tipo];
        if (tipo) cont.appendChild(el('span', 'ingles-tipo ' + tipo.clase, tipo.txt));

        if (e.tipo === 'eleccion') pintarOpciones(e.pregunta, e.opciones, e.correcta);
        else if (e.tipo === 'hueco') pintarOpciones('Complete the sentence: ' + e.frase, e.opciones, e.correcta);
        else if (e.tipo === 'escucha') pintarOpciones('Listen and choose what you hear', e.opciones, e.correcta, e.texto);
        else if (e.tipo === 'ordenar') pintarOrdenar(e);
        else if (e.tipo === 'parejas') pintarParejas(e);
        else if (e.tipo === 'verdadero') pintarOpciones(e.afirmacion, ['✅ True', '❌ False'], e.correcta ? 0 : 1);
        else if (e.tipo === 'escribir') pintarEscribir(e);
        else if (e.tipo === 'emoji') pintarOpciones('What is this?', e.opciones, e.correcta, null, el('div', 'ingles-emoji', e.emoji));

        anima(cont, 'entrada');
    }

    function pintarRacha() {
        if (racha >= 2) {
            chipRacha.hidden = false;
            chipRacha.textContent = '🔥 ' + racha;
            anima(chipRacha, 'pop');
        } else {
            chipRacha.hidden = true;
        }
    }

    function resolver() {
        if (resuelto || !evaluar) return;
        resuelto = true;
        var r = evaluar();
        if (r.ok) { aciertos++; racha++; } else { racha = 0; }
        pintarRacha();

        var texto;
        if (r.parejas) {
            texto = r.ok
                ? azar(FRASES_OK) + ' All pairs, no mistakes! +1 🪙'
                : azar(FRASES_MAL) + ' You made ' + r.fallos + ' mistake(s).';
        } else {
            texto = r.ok
                ? azar(FRASES_OK) + ' +1 🪙'
                : azar(FRASES_MAL) + ' The answer was: «' + r.solucion + '»';
        }
        if (r.ok && racha >= 3) texto += ' 🔥 Streak: ' + racha + '!';

        feedback.innerHTML = '';
        feedback.appendChild(el('span', 'fb-ico', r.ok ? azar(ICONOS_OK) : azar(ICONOS_MAL)));
        feedback.appendChild(el('span', '', texto));
        feedback.hidden = false;
        feedback.classList.add(r.ok ? 'ok' : 'mal');

        if (r.ok) {
            sonidoAcierto();
            confeti(12);
        } else {
            sonidoFallo();
            btnAccion.classList.add('mal');
            anima(cont, 'tiembla');
        }

        btnAccion.textContent = idx + 1 >= ejercicios.length ? 'See result' : 'Continue';
        btnAccion.hidden = false;
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
                var pct = ejercicios.length ? aciertos / ejercicios.length : 0;
                $('finIco').textContent = pct >= 0.9 ? '🏆' : pct >= 0.6 ? '🌟' : '💪';
                $('finTitulo').textContent = aciertos + ' of ' + ejercicios.length + ' correct!';
                sonidoFinal();
                confeti(pct >= 0.6 ? 40 : 16);

                var detalle = $('finDetalle');
                if (typeof d.coins === 'number' && d.coins > 0) {
                    // Contador animado de monedas.
                    var v = 0;
                    var timer = setInterval(function () {
                        v++;
                        detalle.textContent = 'You won +' + v + ' SkillCoins 🪙';
                        if (v >= d.coins) clearInterval(timer);
                    }, 700 / d.coins);
                } else if (typeof d.coins === 'number') {
                    detalle.textContent = "You have today's maximum of SkillCoins. Keep playing for fun!";
                } else {
                    detalle.textContent = '';
                }
            });
    }

    btnAccion.addEventListener('click', function () {
        if (btnAccion.dataset.modo === 'leccion') {
            pintar(); // ahora sí, el reto de esa lección
            return;
        }
        idx++;
        if (idx >= ejercicios.length) terminarLeccion();
        else pintar();
    });

    window.addEventListener('resize', function () {
        if (redibujarParejas) redibujarParejas();
    });

    /* En móvil, el juego se abre a pantalla completa.
       En iPhone (sin API de fullscreen) se usa un modo inmersivo por CSS:
       se ocultan las barras de la app y el juego ocupa toda la pantalla. */
    function pantallaCompletaMovil() {
        if (!window.matchMedia('(max-width: 820px)').matches) return;
        document.body.classList.add('ingles-jugando');
        document.body.classList.add('ingles-inmersivo');
        var raiz = document.documentElement;
        var pedir = raiz.requestFullscreen || raiz.webkitRequestFullscreen;
        if (!pedir) return;
        try {
            var p = pedir.call(raiz);
            if (p && p.catch) p.catch(function () {});
        } catch (e) { /* sin fullscreen nativo: basta el modo inmersivo */ }
    }

    function salirPantallaCompleta() {
        document.body.classList.remove('ingles-inmersivo');
        var salir = document.exitFullscreen || document.webkitExitFullscreen;
        if ((document.fullscreenElement || document.webkitFullscreenElement) && salir) {
            try { salir.call(document); } catch (e) { /* nada */ }
        }
    }
    var btnSalir = $('inglesSalir');
    if (btnSalir) btnSalir.addEventListener('click', salirPantallaCompleta);
    // Botón ⛶: vuelve a la pantalla completa después de haber salido.
    var btnEntrar = $('inglesEntrar');
    if (btnEntrar) btnEntrar.addEventListener('click', pantallaCompletaMovil);

    function empezar(boton) {
        pantallaCompletaMovil(); // dentro del toque del usuario
        boton.disabled = true;
        var txt = boton.textContent;
        boton.textContent = 'Loading…';
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
                racha = 0;
                pintarRacha();
                pInicio.hidden = true;
                pFin.hidden = true;
                pJuego.hidden = false;
                pintar();
            })
            .catch(function () {
                alert('Oops! The lesson did not load. Try again!');
            })
            .finally(function () {
                boton.disabled = false;
                boton.textContent = txt;
            });
    }

    if (btnEmpezar) btnEmpezar.addEventListener('click', function () { empezar(btnEmpezar); });
    if (btnOtra) btnOtra.addEventListener('click', function () { empezar(btnOtra); });
})();
