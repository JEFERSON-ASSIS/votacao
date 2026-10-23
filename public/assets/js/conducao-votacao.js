(function () {
    'use strict';

    var root = document.getElementById('conducao-votacao-live');
    if (!root) return;

    var estadoUrl = root.getAttribute('data-estado-url') || '';
    var streamUrl = root.getAttribute('data-stream-url') || '';
    var pendentesBox = document.getElementById('conducao-votos-pendentes');
    var concluirBtn = document.getElementById('conducao-concluir-votacao');
    var resumoBox = document.getElementById('conducao-votos-resumo');
    var ultimoResumoHtml = '';
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
            // Os nomes já aparecem na lista de votos; aqui fica só a quantidade.
            var total = pendentes.length;
            pendentesBox.innerHTML =
                '<strong>Aguardando ' + total + ' ' + (total === 1 ? 'voto' : 'votos') + '</strong>';
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

    function renderResumo(estado) {
        if (!resumoBox) return;
        var resumo = estado.resumo || {};
        var lista = Array.isArray(estado.parlamentares) ? estado.parlamentares : [];
        var html =
            '<div class="conducao-placar" role="group" aria-label="Placar da votação">' +
            '<span class="conducao-placar-item conducao-placar-item--sim">Sim <strong data-placar="SIM">' + (resumo.SIM | 0) + '</strong></span>' +
            '<span class="conducao-placar-item conducao-placar-item--nao">Não <strong data-placar="NAO">' + (resumo.NAO | 0) + '</strong></span>' +
            '<span class="conducao-placar-item conducao-placar-item--abst">Abstenção <strong data-placar="ABSTENCAO">' + (resumo.ABSTENCAO | 0) + '</strong></span>' +
            '</div><ul class="conducao-votos-lista" id="conducao-votos-lista">' +
            lista.map(function (p) {
                var aguardando = !!p.aguardando;
                return (
                    '<li class="conducao-voto-item' + (aguardando ? ' conducao-voto-item--aguardando' : '') + '">' +
                    '<span class="conducao-voto-nome">' + escapar(p.nome) + '</span>' +
                    (aguardando
                        ? '<span class="conducao-voto-aguardando">Aguardando</span>'
                        : '<span class="voto-chip ' + escapar(p.voto_classe || '') + '">' + escapar(p.voto_label || p.voto || '') + '</span>') +
                    '</li>'
                );
            }).join('') +
            '</ul>';
        // Só redesenha quando algo mudou.
        if (html === ultimoResumoHtml) return;
        ultimoResumoHtml = html;
        resumoBox.innerHTML = html;
    }

    function aplicarEstado(estado) {
        if (!estado || !estado.ok || !estado.aberta) return;

        var pendentes = Array.isArray(estado.pendentes) ? estado.pendentes : [];
        var podeEncerrar = !!estado.pode_encerrar;
        var mensagem = estado.encerrar_mensagem || '';

        renderResumo(estado);
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

    var emVoo = false;

    function buscarEstado() {
        // Uma consulta por vez: com o servidor lento, elas se acumulavam.
        if (!estadoUrl || emVoo) return;
        emVoo = true;
        fetch(estadoUrl + (estadoUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (resposta) {
                if (!resposta.ok) throw new Error('HTTP ' + resposta.status);
                return resposta.json();
            })
            .then(aplicarEstado)
            .catch(function () {})
            .then(function () {
                emVoo = false;
            });
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
