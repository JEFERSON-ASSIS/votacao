/**
 * Painel da presidência — votos dos vereadores em tempo real (SSE + polling).
 */
(function () {
    'use strict';

    var root = document.getElementById('votacao-painel');
    if (!root) return;

    var estadoUrl = root.getAttribute('data-estado-url') || '';
    var streamUrl = root.getAttribute('data-stream-url') || '';
    var rodadaId = parseInt(root.getAttribute('data-rodada-id') || '0', 10);
    var ehPresidente = root.getAttribute('data-eh-presidente') === '1';
    var situacaoUrl = root.getAttribute('data-situacao-url') || '';
    var csrfInput = root.querySelector('input[name="csrf_token"]');
    var votacaoAberta = false;
    if (!estadoUrl || !rodadaId) return;

    var grid = document.getElementById('votacao-painel-grid');
    var liveBadge = document.getElementById('votacao-painel-live');
    var empateBox = document.getElementById('votacao-painel-empate-box');
    var presidenteCard = document.getElementById('votacao-painel-presidente-card');
    var encerrarForm = document.getElementById('votacao-encerrar-form');
    var encerrarBtn = document.getElementById('votacao-encerrar-btn');
    var encerrarAviso = document.getElementById('votacao-encerrar-aviso');
    var es = null;
    var sseAtivo = false;
    var pollTimer = null;

    function setLive(on) {
        if (!liveBadge) return;
        liveBadge.classList.toggle('is-offline', !on);
    }

    function reiniciarPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(buscarEstado, sseAtivo ? 2000 : 500);
    }

    function atualizarResumo(resumo) {
        if (!resumo) return;
        ['SIM', 'NAO', 'ABSTENCAO'].forEach(function (k) {
            var el = root.querySelector('[data-resumo="' + k + '"]');
            if (el) el.textContent = String(resumo[k] || 0);
        });
    }

    function formSituacao(p, situacao, rotulo, titulo, mensagem, variante) {
        return (
            '<form method="post" class="votacao-situacao-form" action="' + escapeAttr(situacaoUrl) + '"' +
            ' data-confirm-title="' + escapeAttr(titulo) + '"' +
            ' data-confirm="' + escapeAttr(mensagem) + '"' +
            ' data-confirm-ok="' + escapeAttr(rotulo) + '"' +
            ' data-confirm-variant="' + variante + '">' +
            '<input type="hidden" name="csrf_token" value="' + escapeAttr(csrfInput ? csrfInput.value : '') + '">' +
            '<input type="hidden" name="user_id" value="' + p.id + '">' +
            '<input type="hidden" name="situacao" value="' + situacao + '">' +
            '<button type="submit" class="btn btn-soft btn--sm">' + escapeHtml(rotulo) + '</button>' +
            '</form>'
        );
    }

    function acoesSituacaoHtml(p) {
        if (!ehPresidente || !votacaoAberta || !situacaoUrl || !csrfInput) return '';
        var nome = p.nome || 'este vereador';
        if (p.aguardando) {
            return (
                '<div class="votacao-situacao-acoes">' +
                formSituacao(p, 'IMPEDIDO', 'Impedido', 'Declarar impedimento',
                    nome + ' ficará impedido nesta votação e não poderá votar. O impedimento fica registrado na ata.', 'danger') +
                formSituacao(p, 'AUSENTE', 'Saiu do plenário', 'Retirar da votação',
                    nome + ' deixará de ser aguardado nesta votação e constará como ausente na ata.', 'danger') +
                '</div>'
            );
        }
        if (p.situacao === 'IMPEDIDO' || p.situacao === 'AUSENTE') {
            return (
                '<div class="votacao-situacao-acoes">' +
                formSituacao(p, 'PENDENTE', 'Desfazer', 'Devolver à votação',
                    nome + ' volta a ser aguardado e poderá votar.', 'primary') +
                '</div>'
            );
        }
        return '';
    }

    function cardHtml(p) {
        var foto = p.foto_url
            ? '<img src="' + escapeAttr(p.foto_url) + '" alt="" width="56" height="56" loading="lazy">'
            : '<span class="votacao-vereador-iniciais">' + escapeHtml(p.iniciais || '?') + '</span>';
        var votoSlot;
        if (p.voto) {
            votoSlot =
                '<span class="voto-chip votacao-vereador-voto ' +
                escapeAttr(p.voto_classe || '') +
                '">' +
                escapeHtml(p.voto_label || p.voto) +
                '</span>';
        } else {
            votoSlot = '<span class="votacao-vereador-aguardando">Aguardando voto…</span>';
        }
        var cls = p.voto
            ? ' votacao-vereador-card--votou'
            : ' votacao-vereador-card--aguardando';
        return (
            '<li class="votacao-vereador-card votacao-vereador-card--ao-vivo' +
            cls +
            '" data-user-id="' +
            p.id +
            '">' +
            '<div class="votacao-vereador-foto" aria-hidden="true">' +
            foto +
            '</div>' +
            '<div class="votacao-vereador-info">' +
            '<strong class="votacao-vereador-nome">' +
            escapeHtml(p.nome) +
            '</strong>' +
            '<span class="votacao-vereador-partido">' +
            escapeHtml(p.partido || '—') +
            '</span>' +
            '</div>' +
            '<span class="votacao-vereador-voto-slot">' +
            votoSlot +
            '</span>' +
            acoesSituacaoHtml(p) +
            '</li>'
        );
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }

    function atualizarGrid(parlamentares) {
        if (!grid || !parlamentares) return;
        // Com a confirmação aberta, redesenhar apagaria o formulário que está sendo confirmado.
        if (document.body.classList.contains('app-confirm-open')) return;
        var html = '';
        parlamentares.forEach(function (p) {
            html += cardHtml(p);
        });
        grid.innerHTML = html;
    }

    function toggleEmpate(d) {
        var show = !!d.empate && d.aberta;
        if (empateBox) empateBox.hidden = !show;
        if (presidenteCard && ehPresidente) presidenteCard.hidden = !show;
    }

    function atualizarEncerrar(d) {
        if (!encerrarBtn || !encerrarForm) return;
        var pode = d.pode_encerrar !== false;
        encerrarBtn.disabled = !pode;
        if (encerrarAviso) {
            encerrarAviso.style.display = pode ? 'none' : '';
            if (!pode && d.encerrar_mensagem) {
                encerrarAviso.textContent = d.encerrar_mensagem;
            }
        }
        if (pode) {
            encerrarForm.setAttribute(
                'data-confirm',
                'Esta ação encerra a votação em andamento e apura o resultado como Aprovado ou Reprovado.'
            );
            encerrarForm.setAttribute('data-confirm-title', 'Encerrar votação');
            encerrarForm.setAttribute('data-confirm-ok', 'Encerrar e apurar');
            encerrarForm.setAttribute('data-confirm-variant', 'danger');
        } else {
            encerrarForm.removeAttribute('data-confirm');
            encerrarForm.removeAttribute('data-confirm-title');
            encerrarForm.removeAttribute('data-confirm-ok');
            encerrarForm.removeAttribute('data-confirm-variant');
        }
    }

    function aplicarEstado(d) {
        if (!d || !d.ok) return;
        setLive(true);
        votacaoAberta = !!d.aberta;
        atualizarResumo(d.resumo);
        atualizarGrid(d.parlamentares);
        toggleEmpate(d);
        atualizarEncerrar(d);

        if (ehPresidente && d.presidente_voto) {
            var msg = document.getElementById('votacao-presidente-voto-msg');
            if (!msg && presidenteCard) {
                msg = document.createElement('p');
                msg.className = 'panel-meta';
                msg.id = 'votacao-presidente-voto-msg';
                msg.style.marginTop = '10px';
                presidenteCard.appendChild(msg);
            }
            if (msg) {
                msg.innerHTML =
                    'Seu voto: <span class="voto-chip ' +
                    escapeAttr(d.presidente_voto_classe || '') +
                    '">' +
                    escapeHtml(d.presidente_voto_label || '') +
                    '</span>';
            }
        }
    }

    var emVoo = false;

    function buscarEstado() {
        // Uma consulta por vez: com o servidor lento, elas se acumulavam.
        if (emVoo) return Promise.resolve();
        emVoo = true;
        return fetch(estadoUrl + (estadoUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(aplicarEstado)
            .catch(function () {
                setLive(false);
            })
            .then(function () {
                emVoo = false;
            });
    }

    function conectarStream() {
        if (!streamUrl || typeof EventSource === 'undefined') {
            reiniciarPolling();
            return;
        }
        if (es) es.close();
        try {
            es = new EventSource(streamUrl);
        } catch (e) {
            reiniciarPolling();
            return;
        }

        es.addEventListener('open', function () {
            sseAtivo = true;
            setLive(true);
            reiniciarPolling();
        });

        es.addEventListener('estado', function (ev) {
            try {
                aplicarEstado(JSON.parse(ev.data));
            } catch (err) { /* ignore */ }
        });

        es.addEventListener('reconnect', function () {
            if (es) es.close();
            setTimeout(conectarStream, 200);
        });

        es.onerror = function () {
            sseAtivo = false;
            setLive(false);
            if (es) es.close();
            reiniciarPolling();
            buscarEstado();
            setTimeout(conectarStream, 1500);
        };
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) buscarEstado();
    });

    buscarEstado();
    reiniciarPolling();
    conectarStream();
})();
