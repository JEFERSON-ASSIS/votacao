/**
 * Login expirado durante a sessão plenária.
 * Quando o servidor responde 401 a uma chamada em segundo plano, mostra o
 * aviso "Sua sessão expirou" e para as consultas e canais ao vivo da tela
 * (antes a tela ficava em "Reconectando…" para sempre).
 * Carregar antes dos demais scripts; o link de login vem de data-login-url.
 */
(function () {
    'use strict';

    if (window.__sessaoExpiradaAtivo) return;
    window.__sessaoExpiradaAtivo = true;

    var script = document.currentScript;
    var loginUrl = (script && script.getAttribute('data-login-url')) || '?r=auth/login';
    var expirada = false;

    function mostrarAviso() {
        if (document.getElementById('sessao-expirada-aviso')) return;
        var aviso = document.createElement('div');
        aviso.id = 'sessao-expirada-aviso';
        aviso.className = 'sessao-expirada-aviso';
        aviso.setAttribute('role', 'alert');

        var texto = document.createElement('span');
        texto.textContent = 'Sua sessão expirou. As informações desta tela pararam de atualizar.';
        var link = document.createElement('a');
        link.className = 'btn btn-primary btn--sm';
        link.href = loginUrl;
        link.textContent = 'Entrar novamente';

        aviso.appendChild(texto);
        aviso.appendChild(link);
        (document.body || document.documentElement).appendChild(aviso);
    }

    function marcarExpirada() {
        if (expirada) return;
        expirada = true;
        if (document.body) {
            mostrarAviso();
        } else {
            document.addEventListener('DOMContentLoaded', mostrarAviso);
        }
    }

    var fetchOriginal = window.fetch;
    if (typeof fetchOriginal === 'function') {
        window.fetch = function () {
            if (expirada) {
                return Promise.reject(new Error('sessao_expirada'));
            }
            return fetchOriginal.apply(window, arguments).then(function (resposta) {
                if (resposta.status === 401) marcarExpirada();
                return resposta;
            });
        };
    }

    var EventSourceOriginal = window.EventSource;
    if (typeof EventSourceOriginal === 'function') {
        var EventSourceControlado = function (url, config) {
            if (expirada) {
                // Canal "fechado": os scripts da tela seguem funcionando sem reconectar.
                return {
                    readyState: 2,
                    url: String(url),
                    close: function () {},
                    addEventListener: function () {},
                    removeEventListener: function () {},
                };
            }
            return new EventSourceOriginal(url, config);
        };
        EventSourceControlado.prototype = EventSourceOriginal.prototype;
        EventSourceControlado.CONNECTING = 0;
        EventSourceControlado.OPEN = 1;
        EventSourceControlado.CLOSED = 2;
        window.EventSource = EventSourceControlado;
    }
})();
