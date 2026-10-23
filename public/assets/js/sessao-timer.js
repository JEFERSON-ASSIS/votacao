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

    // Avisos sonoros no padrão de plenário (gongo de dois tons):
    //   falta 1 minuto  -> um "ding-dong" suave
    //   faltam 30 s     -> dois "ding-dong"
    //   tempo esgotado  -> três toques graves
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

    // Harmônicos de um sino de carrilhão: [proporção da frequência, intensidade].
    var HARMONICOS_SINO = [[1, 1], [2, 0.42], [2.76, 0.3], [5.4, 0.12], [8.93, 0.05]];

    function sino(ctx, inicio, frequencia, volume, duracao) {
        HARMONICOS_SINO.forEach(function (h) {
            var oscilador = ctx.createOscillator();
            var ganho = ctx.createGain();
            // Harmônicos altos somem antes, como num sino de verdade.
            var fimHarmonico = inicio + duracao / Math.sqrt(h[0]);
            oscilador.type = 'sine';
            oscilador.frequency.setValueAtTime(frequencia * h[0], inicio);
            ganho.gain.setValueAtTime(0.0001, inicio);
            ganho.gain.linearRampToValueAtTime(volume * h[1], inicio + 0.006);
            ganho.gain.exponentialRampToValueAtTime(0.0001, fimHarmonico);
            oscilador.connect(ganho);
            ganho.connect(ctx.destination);
            oscilador.start(inicio);
            oscilador.stop(fimHarmonico + 0.05);
        });
    }

    function dingDong(ctx, inicio) {
        sino(ctx, inicio, 659.25, 0.16, 1.6);        // Mi
        sino(ctx, inicio + 0.6, 523.25, 0.16, 2.0);  // Dó
    }

    function tocarAviso(tipo) {
        var ctx = contextoAudio();
        if (!ctx) return;
        var t = ctx.currentTime + 0.03;
        if (tipo === 60) {
            dingDong(ctx, t);
        } else if (tipo === 30) {
            dingDong(ctx, t);
            dingDong(ctx, t + 1.5);
        } else {
            // Tempo esgotado: três toques graves e firmes (Dó grave).
            sino(ctx, t, 261.63, 0.22, 1.2);
            sino(ctx, t + 0.42, 261.63, 0.22, 1.2);
            sino(ctx, t + 0.84, 261.63, 0.24, 2.2);
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
        if (anterior > 0 && restante <= 0 && !avisosTocados[fim + ':0']) {
            avisosTocados[fim + ':0'] = true;
            tocarAviso(0);
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
        // Toca um aviso na hora (60 = falta 1 minuto, 30 = faltam 30 s, 0 = esgotado). Para testar o som.
        testarAviso: function (tipo) {
            tocarAviso(tipo === 30 || tipo === 0 ? tipo : 60);
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
