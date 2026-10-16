(function () {
    'use strict';

    var root = document.getElementById('conducao-votacao-live');
    if (!root) return;

    var estadoUrl = root.getAttribute('data-estado-url') || '';
    var streamUrl = root.getAttribute('data-stream-url') || '';
    var pendentesBox = document.getElementById('conducao-votos-pendentes');
    var concluirBtn = document.getElementById('conducao-concluir-votacao');
    var stream = null;
    var polling = null;

    function escapar(texto) {
        var el = document.createElement('div');
        el.textContent = texto == null ? '' : String(texto);
        return el.innerHTML;
    }

    function renderPendentes(pendentes, mensagem) {
        if (!pendentesBox) return;

        if (pendentes.length) {
            var total = pendentes.length;
            pendentesBox.innerHTML =
                '<strong>Aguardando ' + total + ' ' + (total === 1 ? 'voto' : 'votos') + ':</strong>' +
                '<ul>' + pendentes.map(function (p) {
                    return '<li>' + escapar(p.nome || 'Parlamentar') + '</li>';
                }).join('') + '</ul>';
            pendentesBox.hidden = false;
            return;
        }

        if (mensagem) {
            pendentesBox.textContent = mensagem;
            pendentesBox.hidden = false;
            return;
        }

        pendentesBox.textContent = '';
        pendentesBox.hidden = true;
    }

    function aplicarEstado(estado) {
        if (!estado || !estado.ok || !estado.aberta) return;

        var pendentes = Array.isArray(estado.pendentes) ? estado.pendentes : [];
        var podeEncerrar = !!estado.pode_encerrar;
        var mensagem = estado.encerrar_mensagem || '';

        renderPendentes(pendentes, podeEncerrar ? '' : mensagem);
        if (concluirBtn) {
            concluirBtn.disabled = !podeEncerrar;
            if (podeEncerrar) {
                concluirBtn.removeAttribute('title');
            } else {
                concluirBtn.title = mensagem;
            }
        }
    }

    function buscarEstado() {
        if (!estadoUrl) return;
        fetch(estadoUrl + (estadoUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (resposta) {
                if (!resposta.ok) throw new Error('HTTP ' + resposta.status);
                return resposta.json();
            })
            .then(aplicarEstado)
            .catch(function () {});
    }

    function iniciarPolling() {
        if (polling) clearInterval(polling);
        polling = setInterval(buscarEstado, 1000);
    }

    function conectarStream() {
        if (!streamUrl || typeof EventSource === 'undefined') return;
        stream = new EventSource(streamUrl);
        stream.addEventListener('estado', function (evento) {
            try {
                aplicarEstado(JSON.parse(evento.data));
            } catch (erro) {}
        });
        stream.addEventListener('reconnect', function () {
            stream.close();
            stream = null;
            setTimeout(conectarStream, 500);
        });
        stream.onerror = function () {
            if (stream) stream.close();
            stream = null;
            setTimeout(conectarStream, 2000);
        };
    }

    buscarEstado();
    iniciarPolling();
    conectarStream();
})();
