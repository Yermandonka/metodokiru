/* Método Kiru — Inglés de verano: mini-juegos tipo Duolingo */
(function () {
    if (!window.KIRU_INGLES) return;

    function $(id) { return document.getElementById(id); }
    var pInicio = $('pantallaInicio'), pJuego = $('pantallaJuego'), pFin = $('pantallaFin');
    var cont = $('ejercicio'), barra = $('inglesBarra'), paso = $('inglesPaso');
    var feedback = $('inglesFeedback'), btnAccion = $('inglesAccion');
    var btnEmpezar = $('inglesEmpezar'), btnOtra = $('inglesOtra'), chipRacha = $('inglesRacha');

    var ejercicios = [], idx = 0, aciertos = 0, racha = 0;
    var evaluar = null;      // función del ejercicio actual → {ok, solucion}
    var resuelto = false;

    var TIPOS = {
        eleccion: { txt: '🎯 Traduce', clase: 't-eleccion' },
        hueco:    { txt: '✏️ Completa', clase: 't-hueco' },
        ordenar:  { txt: '🧩 Ordena', clase: 't-ordenar' },
        parejas:  { txt: '🔗 Parejas', clase: 't-parejas' },
        escucha:  { txt: '👂 Escucha', clase: 't-escucha' },
    };
    var FRASES_OK  = ['¡Genial!', '¡Increíble!', '¡Eso es!', '¡Sigue así!', '¡Perfecto!', '¡Toma ya!'];
    var FRASES_MAL = ['¡Casi!', '¡Uy!', 'No pasa nada…', '¡A por la siguiente!'];
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
    function pintarOpciones(enunciado, opciones, correcta, audioTexto) {
        cont.appendChild(el('p', 'ingles-enunciado', enunciado));
        if (audioTexto) {
            var play = el('button', 'ingles-audio', '🔊 Escuchar otra vez');
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
                grid.querySelectorAll('.sel').forEach(function (x) { x.classList.remove('sel'); });
                anima(b, 'sel');
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
                anima(chip, 'pop');
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
                    anima(b, 'ok');
                    selEs.classList.remove('sel');
                    anima(selEs, 'ok');
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
        feedback.appendChild(el('span', '', 'El audio no funciona en este dispositivo: saltamos los retos de escucha.'));
        feedback.classList.add('info');
        feedback.hidden = false;
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
        btnAccion.classList.remove('mal');
        btnAccion.dataset.modo = 'comprobar';
        cont.innerHTML = '';

        var tipo = TIPOS[e.tipo];
        if (tipo) cont.appendChild(el('span', 'ingles-tipo ' + tipo.clase, tipo.txt));

        if (e.tipo === 'eleccion') pintarOpciones(e.pregunta, e.opciones, e.correcta);
        else if (e.tipo === 'hueco') pintarOpciones('Completa la frase: ' + e.frase, e.opciones, e.correcta);
        else if (e.tipo === 'escucha') pintarOpciones('Escucha y elige lo que oyes', e.opciones, e.correcta, e.texto);
        else if (e.tipo === 'ordenar') pintarOrdenar(e);
        else if (e.tipo === 'parejas') pintarParejas(e);

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
                ? azar(FRASES_OK) + ' Todas a la primera +1 🪙'
                : azar(FRASES_MAL) + ' Completado con ' + r.fallos + ' fallo(s).';
        } else {
            texto = r.ok
                ? azar(FRASES_OK) + ' +1 🪙'
                : azar(FRASES_MAL) + ' La respuesta era: «' + r.solucion + '»';
        }
        if (r.ok && racha >= 3) texto += ' 🔥 ¡Racha de ' + racha + '!';

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
                sonidoFinal();
                confeti(pct >= 0.6 ? 40 : 16);

                var detalle = $('finDetalle');
                if (typeof d.coins === 'number' && d.coins > 0) {
                    // Contador animado de monedas.
                    var v = 0;
                    var timer = setInterval(function () {
                        v++;
                        detalle.textContent = 'Has ganado +' + v + ' SkillCoins 🪙';
                        if (v >= d.coins) clearInterval(timer);
                    }, 700 / d.coins);
                } else if (typeof d.coins === 'number') {
                    detalle.textContent = 'Hoy ya tienes el máximo de SkillCoins de inglés. ¡Sigue jugando por diversión!';
                } else {
                    detalle.textContent = '';
                }
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
                racha = 0;
                pintarRacha();
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
