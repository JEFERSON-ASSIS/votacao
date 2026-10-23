(function () {
    'use strict';

    // Diferença (ms) entre o relógio do servidor e o deste aparelho. O telão
    // não pode depender do relógio local (notebook com data errada mostrava
    // "1443:28"), então tudo é calculado no horário do servidor.
    var offsetMs = 0;

    function agoraServidor() {
        return Math.floor((Date.now() + offsetMs) / 1000);
    }

    function sincronizar(serverTs) {
        serverTs = parseInt(serverTs, 10);
        if (serverTs > 0) {
            offsetMs = serverTs * 1000 - Date.now();
        }
    }

    function formatar(segundos) {
        segundos = Math.max(0, segundos | 0);
        var m = Math.floor(segundos / 60);
        var s = segundos % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    // Avisos sonoros: 1 toque quando falta 1 minuto e 2 toques quando faltam 30 s.
    // O som é gerado pelo navegador (sem arquivo). Só toca na passagem pelo marco
    // e uma vez por cronômetro, mesmo que ele apareça repetido na tela.
    var audio = null;
    var restanteAnterior = {};
    var avisosTocados = {};

    function contextoAudio() {
        var Contexto = window.AudioContext || window.webkitAudioContext;
        if (!Contexto) return null;
        if (!audio) {
            try {
                audio = new Contexto();
            } catch (e) {
                return null;
            }
        }
        if (audio.state === 'suspended' && audio.resume) audio.resume();
        return audio;
    }

    // O navegador só libera o som depois de um clique, toque ou tecla na página.
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (evento) {
        window.addEventListener(evento, contextoAudio, { passive: true });
    });

    function tom(ctx, inicio, frequencia, duracao, volume) {
        var oscilador = ctx.createOscillator();
        var ganho = ctx.createGain();
        oscilador.type = 'sine';
        oscilador.frequency.setValueAtTime(frequencia, inicio);
        ganho.gain.setValueAtTime(0.0001, inicio);
        ganho.gain.linearRampToValueAtTime(volume, inicio + 0.015);
        ganho.gain.exponentialRampToValueAtTime(0.0001, inicio + duracao);
        oscilador.connect(ganho);
        ganho.connect(ctx.destination);
        oscilador.start(inicio);
        oscilador.stop(inicio + duracao + 0.05);
    }

    function tocarAviso(tipo) {
        var ctx = contextoAudio();
        if (!ctx) return;
        var t = ctx.currentTime + 0.02;
        if (tipo === 60) {
            // Sino suave: fundamental + harmônico, decaimento longo.
            tom(ctx, t, 880, 1.2, 0.22);
            tom(ctx, t, 1320, 0.9, 0.07);
        } else {
            // Dois toques curtos.
            tom(ctx, t, 988, 0.32, 0.22);
            tom(ctx, t + 0.45, 988, 0.32, 0.22);
        }
    }

    function verificarAvisos(fim, restante) {
        var anterior = restanteAnterior[fim];
        restanteAnterior[fim] = restante;
        if (anterior === undefined) return;
        if (anterior > 60 && restante <= 60 && restante > 30 && !avisosTocados[fim + ':60']) {
            avisosTocados[fim + ':60'] = true;
            tocarAviso(60);
        }
        if (anterior > 30 && restante <= 30 && restante > 0 && !avisosTocados[fim + ':30']) {
            avisosTocados[fim + ':30'] = true;
            tocarAviso(30);
        }
    }

    function tickUm(el) {
        var fim = parseInt(el.getAttribute('data-fim-ts') || '0', 10);
        if (!fim) {
            return;
        }
        var restante = Math.max(0, fim - agoraServidor());
        verificarAvisos(fim, restante);
        var valor = el.querySelector('[data-sessao-timer-valor]');
        if (valor) {
            valor.textContent = restante <= 0 ? 'Esgotado' : formatar(restante);
        }
        el.classList.toggle('sessao-timer--esgotado', restante <= 0);
        el.classList.toggle('sessao-timer--alerta', restante > 0 && restante <= 30);
    }

    function tickTodos() {
        document.querySelectorAll('[data-sessao-timer]').forEach(tickUm);
    }

    window.SessaoTimer = {
        formatar: formatar,
        tick: tickTodos,
        sincronizar: sincronizar,
        // Toca um aviso na hora (60 = falta 1 minuto, 30 = faltam 30 s). Útil para testar o som.
        testarAviso: function (tipo) {
            tocarAviso(tipo === 30 ? 30 : 60);
        },
        html: function (tempo, opts) {
            opts = opts || {};
            if (!tempo || !tempo.fim_ts) {
                return '';
            }
            var cls = 'sessao-timer' + (opts.className ? ' ' + opts.className : '');
            if (tempo.esgotado) {
                cls += ' sessao-timer--esgotado';
            }
            var label = opts.label || 'Tempo';
            var valor =
                tempo.esgotado || (tempo.restante_seg | 0) <= 0
                    ? 'Esgotado'
                    : formatar(tempo.restante_seg | 0);
            return (
                '<div class="' +
                cls +
                '" data-sessao-timer data-fim-ts="' +
                (tempo.fim_ts | 0) +
                '" role="timer" aria-live="polite">' +
                '<span class="sessao-timer-label">' +
                label +
                '</span>' +
                '<strong class="sessao-timer-valor" data-sessao-timer-valor>' +
                valor +
                '</strong></div>'
            );
        },
    };

    // Páginas renderizadas no servidor informam o horário dele em data-server-ts.
    var marcado = document.querySelector('[data-server-ts]');
    if (marcado) {
        sincronizar(marcado.getAttribute('data-server-ts'));
    }

    tickTodos();
    setInterval(tickTodos, 1000);
})();
