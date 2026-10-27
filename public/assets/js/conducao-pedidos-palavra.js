/*
 * Pedidos de palavra na tela do presidente.
 * Consulta a fila a cada 2 s. Quando chega um pedido novo, o painel pisca,
 * toca um bipe curto e o título da aba mostra quantos estão esperando.
 * "Conceder" coloca o vereador no telão com o tempo escolhido no bloco do telão.
 */
(function () {
    'use strict';

    var painel = document.getElementById('conducao-pedidos');
    if (!painel) return;

    var lista = painel.querySelector('[data-pedidos-lista]');
    var contador = painel.querySelector('[data-pedidos-count]');
    var url = painel.getAttribute('data-url');
    var urlConceder = painel.getAttribute('data-conceder-url');
    var urlRecusar = painel.getAttribute('data-recusar-url');
    var csrf = painel.getAttribute('data-csrf');
    var tituloOriginal = document.title;
    var vistos = {};
    var primeiraCarga = true;
    var emVoo = false;

    function el(tag, cls, texto) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (texto != null) n.textContent = texto;
        return n;
    }

    function tempoDesde(seg) {
        if (seg < 60) return 'agora';
        var min = Math.floor(seg / 60);
        return 'há ' + min + ' min';
    }

    function formAcao(acao, pedidoId, rotulo, classeBotao) {
        var f = el('form');
        f.method = 'post';
        f.action = acao;
        f.className = 'conducao-pedido-form';
        [['csrf_token', csrf], ['pedido_id', String(pedidoId)]].forEach(function (par) {
            var i = el('input');
            i.type = 'hidden';
            i.name = par[0];
            i.value = par[1];
            f.appendChild(i);
        });
        var b = el('button', 'btn ' + classeBotao, rotulo);
        b.type = 'submit';
        f.appendChild(b);
        return f;
    }

    function itemPedido(p, novo, posicao) {
        var li = el('li', 'conducao-pedido' + (novo ? ' is-novo' : ''));
        li.setAttribute('data-id', p.id);

        var num = el('span', 'conducao-pedido-pos', String(posicao) + 'º');
        num.title = posicao + 'º a pedir a palavra';
        li.appendChild(num);

        var foto = el('img', 'conducao-pedido-foto');
        foto.src = p.foto_url || '';
        foto.alt = '';
        li.appendChild(foto);

        var info = el('div', 'conducao-pedido-info');
        var nome = el('strong', 'conducao-pedido-nome', p.nome);
        info.appendChild(nome);
        if (p.partido) info.appendChild(el('span', 'conducao-pedido-partido', '(' + p.partido + ')'));
        info.appendChild(el('span', 'conducao-pedido-quando', 'pede a palavra · ' + tempoDesde(p.desde_seg || 0)));
        li.appendChild(info);

        var acoes = el('div', 'conducao-pedido-acoes');
        var conceder = formAcao(urlConceder, p.id, 'Conceder', 'btn-primary');
        conceder.addEventListener('submit', function () {
            // Usa o tempo de fala escolhido no bloco do telão.
            var tempo = document.getElementById('tempo-destaque');
            var i = el('input');
            i.type = 'hidden';
            i.name = 'tempo_seg';
            i.value = tempo ? tempo.value : '300';
            conceder.appendChild(i);
        });
        acoes.appendChild(conceder);
        acoes.appendChild(formAcao(urlRecusar, p.id, 'Recusar', 'btn-soft'));
        li.appendChild(acoes);

        return li;
    }

    function bipe() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            var ctx = bipe.ctx || (bipe.ctx = new Ctx());
            var osc = ctx.createOscillator();
            var vol = ctx.createGain();
            osc.frequency.value = 880;
            vol.gain.setValueAtTime(0.0001, ctx.currentTime);
            vol.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.02);
            vol.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
            osc.connect(vol);
            vol.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.4);
        } catch (e) {
            /* sem som: tudo bem */
        }
    }

    var chaveAtual = null;

    function render(pedidos) {
        // Mesma fila: só atualiza o "há X min", sem trocar os botões embaixo do mouse.
        var chave = pedidos.map(function (p) { return p.id; }).join(',');
        if (chave === chaveAtual) {
            pedidos.forEach(function (p) {
                var q = lista.querySelector('[data-id="' + p.id + '"] .conducao-pedido-quando');
                if (q) q.textContent = 'pede a palavra · ' + tempoDesde(p.desde_seg || 0);
            });
            return;
        }
        chaveAtual = chave;

        var chegouNovo = false;
        var idsAtuais = {};
        lista.innerHTML = '';
        pedidos.forEach(function (p, i) {
            idsAtuais[p.id] = true;
            var novo = !vistos[p.id] && !primeiraCarga;
            if (novo) chegouNovo = true;
            lista.appendChild(itemPedido(p, novo || (primeiraCarga && (p.desde_seg || 0) < 10), i + 1));
        });
        vistos = idsAtuais;

        painel.hidden = pedidos.length === 0;
        contador.textContent = String(pedidos.length);
        document.title = pedidos.length
            ? '✋ (' + pedidos.length + ') ' + tituloOriginal
            : tituloOriginal;

        if (chegouNovo) {
            painel.classList.remove('is-alerta');
            void painel.offsetWidth; // reinicia a animação
            painel.classList.add('is-alerta');
            bipe();
        }
        primeiraCarga = false;
    }

    function buscar() {
        if (emVoo || !url) return;
        emVoo = true;
        fetch(url + '&_=' + Date.now(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        })
            .then(function (r) {
                return r.ok ? r.json() : null;
            })
            .then(function (d) {
                if (d && d.ok && Array.isArray(d.pedidos)) render(d.pedidos);
            })
            .catch(function () {
                /* tenta de novo na próxima volta */
            })
            .then(function () {
                emVoo = false;
            });
    }

    var inicial = [];
    try {
        inicial = JSON.parse(painel.getAttribute('data-inicial') || '[]');
    } catch (e) {
        inicial = [];
    }
    render(inicial);
    setInterval(buscar, 2000);
})();
