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

    function tickUm(el) {
        var fim = parseInt(el.getAttribute('data-fim-ts') || '0', 10);
        if (!fim) {
            return;
        }
        var restante = Math.max(0, fim - agoraServidor());
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
