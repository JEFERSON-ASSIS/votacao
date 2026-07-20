/**
 * Alerta global de votação aberta (lista de sessões, perfil, etc.) — SSE + polling.
 */
(function () {
    'use strict';

    var cfg = window.PARL_GLOBAL_VOTO || {};
    var url = cfg.url || '';
    if (!url) return;

    var alerta = document.getElementById('parl-alerta-global');
    if (!alerta) return;

    var sub = document.getElementById('parl-alerta-global-sub');
    var linkIr = document.getElementById('parl-alerta-global-ir');
    var btnFechar = document.getElementById('parl-alerta-global-fechar');
    var dismissRodada = 0;
    var ultimoHash = '';
    var ultimaSessaoStream = 0;
    var es = null;
    var sseAtivo = false;
    var pollTimer = null;
    var pollRapido = 500;
    var pollLento = 2000;

    function reiniciarPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(buscarResumo, sseAtivo ? pollLento : pollRapido);
    }

    function atualizarTabela(sessoes) {
        if (!sessoes || !sessoes.length) return;
        sessoes.forEach(function (s) {
            var chip = document.querySelector('[data-sessao-status="' + s.id + '"]');
            if (!chip) return;
            chip.textContent = s.status_label || '';
            chip.className = 'status-chip ' + (s.status_chip || 'open');
        });
    }

    function mostrarAlerta(d) {
        if (!d.tem_votacao || !d.rodada_id) {
            ocultarAlerta();
            return;
        }
        if (dismissRodada === d.rodada_id) return;

        if (sub) {
            var txt = (d.rodada_titulo || d.sessao_titulo || 'Votação').trim();
            if (d.sessao_titulo && d.rodada_titulo) {
                txt = d.rodada_titulo + ' — ' + d.sessao_titulo;
            }
            sub.textContent = txt;
        }
        if (linkIr && d.url_acompanhar) {
            linkIr.href = d.url_acompanhar;
        }

        alerta.setAttribute('data-rodada-id', String(d.rodada_id || 0));
        alerta.hidden = false;
        alerta.classList.add('is-visible');
        alerta.classList.remove('parl-alerta-votacao--pulse');
        void alerta.offsetWidth;
        alerta.classList.add('parl-alerta-votacao--pulse');

        conectarStream(d);
    }

    function ocultarAlerta() {
        alerta.hidden = true;
        alerta.classList.remove('is-visible', 'parl-alerta-votacao--pulse');
        fecharStream();
        dismissRodada = 0;
    }

    function fecharStream() {
        if (es) {
            es.close();
            es = null;
        }
        sseAtivo = false;
        ultimaSessaoStream = 0;
        reiniciarPolling();
    }

    function conectarStream(d) {
        if (!d.url_stream || typeof EventSource === 'undefined') {
            fecharStream();
            return;
        }
        var sid = d.sessao_id || 0;
        if (sid === ultimaSessaoStream && es) return;

        fecharStream();
        ultimaSessaoStream = sid;
        try {
            es = new EventSource(d.url_stream);
        } catch (e) {
            reiniciarPolling();
            return;
        }

        es.addEventListener('open', function () {
            sseAtivo = true;
            reiniciarPolling();
        });

        es.addEventListener('estado', function (ev) {
            try {
                var estado = JSON.parse(ev.data);
                var rid = estado.rodada_id || 0;
                if (rid > 0 && rid !== parseInt(alerta.getAttribute('data-rodada-id') || '0', 10)) {
                    dismissRodada = 0;
                }
                if (
                    estado.pode_registrar_voto &&
                    !estado.ja_votou &&
                    !estado.meu_voto &&
                    rid > 0 &&
                    dismissRodada !== rid
                ) {
                    var merge = {
                        ok: true,
                        tem_votacao: true,
                        sessao_id: sid,
                        sessao_titulo: d.sessao_titulo,
                        rodada_id: rid,
                        rodada_titulo: estado.rodada_titulo || estado.titulo_momento,
                        pode_registrar_voto: true,
                        ja_votou: false,
                        url_acompanhar: d.url_acompanhar,
                        url_stream: d.url_stream,
                    };
                    mostrarAlerta(merge);
                } else {
                    ocultarAlerta();
                }
            } catch (err) { /* ignore */ }
        });

        es.addEventListener('reconnect', function () {
            fecharStream();
            buscarResumo();
        });

        es.onerror = function () {
            sseAtivo = false;
            if (es) es.close();
            es = null;
            reiniciarPolling();
            setTimeout(buscarResumo, 300);
        };
    }

    function precisaAlertaGlobal(d) {
        if (!d || !d.tem_votacao || !d.rodada_id) return false;
        if (d.ja_votou || d.meu_voto) return false;
        if (d.pode_registrar_voto === false) return false;
        return true;
    }

    function aplicarResumo(d) {
        if (!d || !d.ok) return;

        var rid = d.rodada_id || 0;
        var ridAnterior = parseInt(alerta.getAttribute('data-rodada-id') || '0', 10);
        if (rid > 0 && rid !== ridAnterior) {
            dismissRodada = 0;
        }

        var hash = JSON.stringify({
            tem: d.tem_votacao,
            rid: rid,
            modo: d.modo || '',
            voto: d.meu_voto || '',
            ja: d.ja_votou ? 1 : 0,
        });
        ultimoHash = hash;

        atualizarTabela(d.sessoes);

        if (precisaAlertaGlobal(d)) {
            if (dismissRodada !== rid) {
                mostrarAlerta(d);
            } else if (linkIr && d.url_acompanhar) {
                linkIr.href = d.url_acompanhar;
            }
        } else {
            ocultarAlerta();
        }
    }

    function buscarResumo() {
        return fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(aplicarResumo)
            .catch(function () { /* silencioso */ });
    }

    if (btnFechar) {
        btnFechar.addEventListener('click', function () {
            var rid = parseInt(alerta.getAttribute('data-rodada-id') || '0', 10);
            if (rid) dismissRodada = rid;
            alerta.hidden = true;
            alerta.classList.remove('is-visible', 'parl-alerta-votacao--pulse');
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) buscarResumo();
    });

    buscarResumo();
    reiniciarPolling();
})();
